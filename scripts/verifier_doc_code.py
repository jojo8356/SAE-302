#!/usr/bin/env python3
"""Vérifie les docstrings Python/PHPDoc et génère la référence HTML PHP.

Ce script applique les contrôles inspirés de la leçon *Documenter le code* :
- présence des docstrings Python (module, classes et fonctions publiques) ;
- exécution des ``doctest`` sur les modules Python importables sans effet de bord ;
- présence des en-têtes PHPDoc et des blocs ``/** ... */`` sur toutes les
  méthodes publiques de ``app/**/*.php`` ;
- génération optionnelle (``--gen-php-ref``) de la référence HTML PHP dans
  ``livrables/reference/php/`` à partir des blocs PHPDoc du code.
"""

from __future__ import annotations

import argparse
import ast
import doctest
import html
import importlib
import pathlib
import re
import sys
from dataclasses import dataclass

RACINE = pathlib.Path(__file__).resolve().parent.parent

MODULES_DOCTEST = (
    "scripts.md_to_docx",
    "scripts.md_to_html",
    "scripts.md_to_pdf",
    "scripts.md_to_pptx",
    "scripts.rejeu_gitlab",
    "scripts.verifier_deprecations",
    "scripts.verifier_doc_code",
)


@dataclass(frozen=True)
class MethodePhp:
    """Description extraite d'une méthode publique PHP."""

    nom: str
    signature: str
    ligne: int
    phpdoc: str


@dataclass(frozen=True)
class FichierPhp:
    """Métadonnées documentaires extraites d'un fichier PHP."""

    chemin: str
    namespace: str
    classe: str
    description: str
    methodes: list[MethodePhp]


def nettoyer_phpdoc(bloc: str) -> str:
    r"""Extrait le texte lisible d'un bloc de commentaire ``/** ... */``.

    Args:
        bloc: Texte brut du commentaire PHPDoc.

    Returns:
        Texte nettoyé sur une ou plusieurs lignes sans astérisques de marge.

    Example:
        >>> nettoyer_phpdoc("/**\n * Bonjour.\n * Suite.\n */")
        'Bonjour.\nSuite.'
    """
    contenu = bloc.strip()
    if contenu.startswith("/**"):
        contenu = contenu[3:]
    if contenu.endswith("*/"):
        contenu = contenu[:-2]
    lignes: list[str] = []
    for ligne in contenu.splitlines():
        propre = re.sub(r"^\s*\*\s?", "", ligne).rstrip()
        lignes.append(propre)
    while lignes and not lignes[0]:
        lignes.pop(0)
    while lignes and not lignes[-1]:
        lignes.pop()
    return "\n".join(lignes)


def analyser_fichier_php(chemin: pathlib.Path, racine: pathlib.Path = RACINE) -> tuple[FichierPhp, list[str]]:
    """Analyse un fichier PHP et signale les PHPDoc manquants.

    Args:
        chemin: Chemin absolu ou relatif vers le fichier PHP.
        racine: Racine du dépôt pour normaliser le chemin affiché.

    Returns:
        Couple ``(FichierPhp, erreurs)`` listant les anomalies éventuelles.
    """
    texte = chemin.read_text(encoding="utf-8")
    lignes = texte.splitlines()
    relatif = str(chemin.relative_to(racine)).replace("\\", "/")
    erreurs: list[str] = []

    if "/**" not in texte:
        erreurs.append(f"{relatif}: aucun bloc PHPDoc (/** ... */) trouvé dans le fichier")

    ns_match = re.search(r"^\s*namespace\s+([^;]+);", texte, re.MULTILINE)
    namespace = ns_match.group(1).strip() if ns_match else "(global)"

    cls_match = re.search(
        r"^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)",
        texte,
        re.MULTILINE,
    )
    classe = cls_match.group(1) if cls_match else chemin.stem

    # Capture du premier bloc PHPDoc (en-tête fichier ou docblock de classe).
    doc_match = re.search(r"/\*\*.*?\*/", texte, re.DOTALL)
    description = nettoyer_phpdoc(doc_match.group(0)) if doc_match else ""

    methodes: list[MethodePhp] = []
    for idx, ligne in enumerate(lignes):
        m = re.match(r"^\s*(public(?:\s+static)?\s+function\s+(\w+)\s*\([^)]*\)(?:\s*:\s*[^\{;]+)?)", ligne)
        if not m:
            continue
        signature = re.sub(r"\s+", " ", m.group(1)).strip()
        nom = m.group(2)

        # Recherche d'un bloc PHPDoc immédiatement au-dessus de la méthode.
        phpdoc_trouve = ""
        j = idx - 1
        while j >= 0 and (lignes[j].strip() == "" or lignes[j].strip().startswith("#[")):
            j -= 1
        if j >= 0 and "*/" in lignes[j]:
            k = j
            while k >= 0 and "/**" not in lignes[k]:
                k -= 1
            if k >= 0:
                phpdoc_trouve = nettoyer_phpdoc("\n".join(lignes[k : j + 1]))

        if not phpdoc_trouve:
            erreurs.append(f"{relatif}:{idx + 1}: méthode publique `{nom}()` sans PHPDoc")

        methodes.append(
            MethodePhp(
                nom=nom,
                signature=signature,
                ligne=idx + 1,
                phpdoc=phpdoc_trouve,
            )
        )

    return FichierPhp(
        chemin=relatif,
        namespace=namespace,
        classe=classe,
        description=description,
        methodes=methodes,
    ), erreurs


def verifier_python(racine: pathlib.Path = RACINE) -> list[str]:
    """Vérifie la présence des docstrings et exécute les doctests Python.

    Args:
        racine: Racine du dépôt contenant ``scripts/`` et ``.tools/``.

    Returns:
        Liste des erreurs détectées (vide si tout est conforme).
    """
    erreurs: list[str] = []
    dossiers = [racine / "scripts", racine / ".tools"]
    fichiers = sorted(p for d in dossiers if d.is_dir() for p in d.glob("*.py"))

    for fichier in fichiers:
        relatif = str(fichier.relative_to(racine)).replace("\\", "/")
        arbre = ast.parse(fichier.read_text(encoding="utf-8"), filename=relatif)
        if not ast.get_docstring(arbre):
            erreurs.append(f"{relatif}: docstring de module manquante")
        for noeud in arbre.body:
            if isinstance(noeud, (ast.FunctionDef, ast.AsyncFunctionDef, ast.ClassDef)):
                if noeud.name.startswith("_"):
                    continue
                if not ast.get_docstring(noeud):
                    erreurs.append(f"{relatif}:{noeud.lineno}: `{noeud.name}` sans docstring")
                if isinstance(noeud, ast.ClassDef):
                    for sous in noeud.body:
                        if isinstance(sous, (ast.FunctionDef, ast.AsyncFunctionDef)):
                            if sous.name.startswith("_") and sous.name != "__init__":
                                continue
                            if not ast.get_docstring(sous):
                                erreurs.append(
                                    f"{relatif}:{sous.lineno}: `{noeud.name}.{sous.name}` sans docstring"
                                )

    if str(racine) not in sys.path:
        sys.path.insert(0, str(racine))
    for mod_nom in MODULES_DOCTEST:
        module = importlib.import_module(mod_nom)
        resultat = doctest.testmod(module, verbose=False)
        if resultat.failed > 0:
            erreurs.append(f"{mod_nom}: {resultat.failed} doctest(s) en échec")

    return erreurs


def verifier_php(racine: pathlib.Path = RACINE) -> tuple[list[FichierPhp], list[str]]:
    """Audit complet des PHPDoc de ``app/`` et ``public/index.php``.

    Args:
        racine: Racine du dépôt MiniShop.

    Returns:
        Couple ``(fichiers_analyses, erreurs)``.
    """
    cibles = sorted((racine / "app").rglob("*.php"))
    index_php = racine / "public" / "index.php"
    if index_php.is_file():
        cibles.append(index_php)

    fichiers: list[FichierPhp] = []
    erreurs: list[str] = []
    for cible in cibles:
        info, errs = analyser_fichier_php(cible, racine=racine)
        fichiers.append(info)
        erreurs.extend(errs)
    return fichiers, erreurs


def verifier_adr(racine: pathlib.Path = RACINE) -> list[str]:
    """Vérifie la structure MADR et la numérotation séquentielle des ADR.

    Args:
        racine: Racine du dépôt contenant ``docs/adr/``.

    Returns:
        Liste des anomalies détectées (vide si tous les ADR sont conformes).
    """
    dossier = racine / "docs" / "adr"
    erreurs: list[str] = []
    if not (dossier / "template.md").is_file():
        erreurs.append("docs/adr/template.md manquant")
    if not (dossier / "adr-index.md").is_file():
        erreurs.append("docs/adr/adr-index.md manquant")

    fichiers = sorted(dossier.glob("[0-9]*.md"))
    if not fichiers:
        return erreurs + ["docs/adr/: aucun fichier ADR numéroté (NNNN-*.md) trouvé"]

    sections_requises = (
        r"^## (Statut|Status)\b",
        r"^## (Contexte|Context)\b",
        r"^## Decision Drivers\b",
        r"^## Options considérées\b",
        r"^## (Décision|Decision)\b",
        r"^## (Conséquences|Consequences)\b",
        r"^### Positives\b",
        r"^### Négatives\b",
        r"^## (Liens|Links)\b",
    )

    attendu: int | None = None
    for fichier in fichiers:
        relatif = str(fichier.relative_to(racine)).replace("\\", "/")
        prefixe = fichier.name.split("-", 1)[0]
        if not re.fullmatch(r"\d{4}", prefixe):
            erreurs.append(f"{relatif}: préfixe numérique NNNN attendu")
            continue
        numero = int(prefixe, 10)
        if attendu is None:
            attendu = numero
        elif numero != attendu:
            erreurs.append(f"{relatif}: trou de numérotation (attendu {attendu:04d}, trouvé {numero:04d})")
        attendu = numero + 1

        contenu = fichier.read_text(encoding="utf-8")
        for motif in sections_requises:
            if not re.search(motif, contenu, re.MULTILINE):
                erreurs.append(f"{relatif}: section MADR manquante ({motif})")
        nb_options = len(re.findall(r"^### Option \d+", contenu, re.MULTILINE))
        if nb_options < 2:
            erreurs.append(f"{relatif}: au moins 2 options documentées requises (trouvé {nb_options})")

    return erreurs


def generer_reference_php(fichiers: list[FichierPhp], sortie: pathlib.Path) -> None:
    """Génère une référence HTML statique à partir des PHPDoc du code PHP.

    Args:
        fichiers: Liste des fiches PHP extraites par ``verifier_php()``.
        sortie: Dossier cible (ex. ``livrables/reference/php``).
    """
    sortie.mkdir(parents=True, exist_ok=True)
    # Ne conserver que les classes/contrôleurs/dépôts/modèles (exclure les vues gabarits).
    classes = [f for f in fichiers if not f.chemin.startswith("app/View/") or f.methodes]

    cartes_html: list[str] = []
    for f in classes:
        slug = f.chemin.replace("/", "__").replace(".php", ".html")
        methodes_html = []
        for m in f.methodes:
            doc_html = html.escape(m.phpdoc).replace("\n", "<br>")
            methodes_html.append(
                f"""<div class="method" id="m-{html.escape(m.nom)}">
  <code>{html.escape(m.signature)}</code>
  <p>{doc_html}</p>
</div>"""
            )
        if not methodes_html:
            methodes_html.append('<p class="muted">Aucune méthode publique (point d’entrée ou déclaration).</p>')

        page_html = f"""<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>{html.escape(f.namespace)}\\{html.escape(f.classe)} — Référence PHP MiniShop</title>
  <style>
    body {{ font-family: system-ui, -apple-system, sans-serif; max-width: 960px; margin: 2rem auto; padding: 0 1.2rem; color: #1f2937; line-height: 1.55; }}
    header {{ border-bottom: 2px solid #e5e7eb; padding-bottom: 1rem; margin-bottom: 1.5rem; }}
    .ns {{ color: #6b7280; font-size: .9rem; font-family: monospace; }}
    .doc {{ background: #f8fafc; border-left: 4px solid #2563eb; padding: .9rem 1.1rem; white-space: pre-wrap; }}
    .method {{ border: 1px solid #e5e7eb; border-radius: 6px; padding: .85rem 1rem; margin: .85rem 0; }}
    .method code {{ display: block; font-weight: 600; color: #0f172a; background: #f1f5f9; padding: .35rem .5rem; border-radius: 4px; overflow-x: auto; }}
    .muted {{ color: #6b7280; font-style: italic; }}
    a {{ color: #1d4ed8; text-decoration: none; }}
    a:hover {{ text-decoration: underline; }}
  </style>
</head>
<body>
  <nav><a href="index.html">← Index de la référence PHP</a> · <a href="../python/index.html">Référence Python (pdoc)</a></nav>
  <header>
    <div class="ns">{html.escape(f.namespace)} · <span>{html.escape(f.chemin)}</span></div>
    <h1>{html.escape(f.classe)}</h1>
  </header>
  <section>
    <h2>Description</h2>
    <div class="doc">{html.escape(f.description)}</div>
  </section>
  <section>
    <h2>Méthodes publiques ({len(f.methodes)})</h2>
    {"".join(methodes_html)}
  </section>
</body>
</html>
"""
        (sortie / slug).write_text(page_html, encoding="utf-8")
        resume = html.escape((f.description.splitlines()[0] if f.description else "")[:140])
        cartes_html.append(
            f'<tr><td><a href="{html.escape(slug)}">{html.escape(f.classe)}</a></td>'
            f'<td><code>{html.escape(f.chemin)}</code></td>'
            f'<td>{len(f.methodes)}</td>'
            f'<td>{resume}</td></tr>'
        )

    total_methodes = sum(len(f.methodes) for f in classes)
    index_html = f"""<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Référence API PHP — MiniShop</title>
  <style>
    body {{ font-family: system-ui, -apple-system, sans-serif; max-width: 1100px; margin: 2rem auto; padding: 0 1.2rem; color: #1f2937; line-height: 1.5; }}
    h1 {{ border-bottom: 2px solid #e5e7eb; padding-bottom: .6rem; }}
    table {{ width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: .92rem; }}
    th, td {{ border-bottom: 1px solid #e5e7eb; padding: .5rem .6rem; text-align: left; vertical-align: top; }}
    th {{ background: #f8fafc; }}
    a {{ color: #1d4ed8; text-decoration: none; }}
    a:hover {{ text-decoration: underline; }}
  </style>
</head>
<body>
  <p><a href="../python/index.html">Voir aussi la référence API Python (pdoc)</a></p>
  <h1>Référence API PHP — MiniShop</h1>
  <p>Générée automatiquement depuis les commentaires PHPDoc de <code>app/</code> et <code>public/index.php</code> : <strong>{len(classes)}</strong> unités documentées, <strong>{total_methodes}</strong> méthodes publiques.</p>
  <table>
    <thead><tr><th>Classe / unité</th><th>Fichier</th><th>Méthodes</th><th>Résumé</th></tr></thead>
    <tbody>
      {"".join(cartes_html)}
    </tbody>
  </table>
</body>
</html>
"""
    (sortie / "index.html").write_text(index_html, encoding="utf-8")


def main(argv: list[str] | None = None) -> int:
    """Point d'entrée CLI du vérificateur de documentation du code.

    Args:
        argv: Arguments de ligne de commande optionnels.

    Returns:
        ``0`` si la documentation et les doctests sont conformes, ``1`` sinon.
    """
    parser = argparse.ArgumentParser(
        description="Vérifie les docstrings Python, les doctests et les PHPDoc de MiniShop."
    )
    parser.add_argument(
        "--gen-php-ref",
        action="store_true",
        help="Génère aussi la référence HTML PHP dans livrables/reference/php/.",
    )
    args = parser.parse_args(argv)

    erreurs_py = verifier_python(RACINE)
    fichiers_php, erreurs_php = verifier_php(RACINE)
    erreurs_adr = verifier_adr(RACINE)

    if args.gen_php_ref:
        generer_reference_php(fichiers_php, RACINE / "livrables" / "reference" / "php")

    total_erreurs = erreurs_py + erreurs_php + erreurs_adr
    if total_erreurs:
        for err in total_erreurs:
            print(f"ERREUR: {err}", file=sys.stderr)
        print(f"Échec du contrôle documentaire : {len(total_erreurs)} anomalie(s).", file=sys.stderr)
        return 1

    total_methodes = sum(len(f.methodes) for f in fichiers_php)
    nb_adr = len(list((RACINE / "docs" / "adr").glob("[0-9]*.md")))
    print(
        f"OK — Python (docstrings + {len(MODULES_DOCTEST)} modules doctest), "
        f"PHP ({len(fichiers_php)} fichiers, {total_methodes} méthodes publiques PHPDoc) "
        f"et ADR ({nb_adr} fiches MADR) conformes."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
