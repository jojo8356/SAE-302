#!/usr/bin/env python3
"""MiniShop — convertit un document Markdown en HTML5 académique autonome.

    python3 scripts/md_to_html.py docs/01-cahier-des-charges-MiniShop.md \
        --out dist/html/01-cahier-des-charges-MiniShop.html

Présentation académique et de recherche (« format très droit ») :
* mise en page feuillet A4 sur fond neutre, angles droits (border-radius: 0) ;
* cartouche institutionnel Université Côte d'Azur · SAÉ 302 ;
* corps en police Serif justifiée (Georgia / DejaVu Serif), titres Sans-Serif
  en bleu marine institutionnel (#0F294A) soulignés ;
* tableaux de style LaTeX booktabs (filets horizontaux uniquement) ;
* images intégrées en data URI (autonomie totale hors ligne) ;
* feuille d'impression A4 dédiée.
"""

from __future__ import annotations

import argparse
import base64
import html
import mimetypes
import re
import sys
from pathlib import Path

try:
    import markdown
except ImportError:  # pragma: no cover
    print("✘ module Python « markdown » absent : pip install markdown", file=sys.stderr)
    raise SystemExit(2)

RACINE = Path(__file__).resolve().parent.parent

CSS = """
:root {
  --encre: #0f172a;
  --encre-douce: #475569;
  --marine: #0f294a;
  --marine-clair: #f1f5f9;
  --fond-bureau: #e2e8f0;
  --papier: #ffffff;
  --trait: #cbd5e1;
  --trait-fin: #e2e8f0;
  --code-fond: #f8fafc;
}
* { box-sizing: border-box; }
html { font-size: 100%; -webkit-text-size-adjust: 100%; }
body {
  margin: 0;
  padding: 2rem 1rem 3.5rem;
  background: var(--fond-bureau);
  color: var(--encre);
  font-family: Georgia, "DejaVu Serif", "Liberation Serif", "Times New Roman", serif;
  font-size: 10.5pt;
  line-height: 1.65;
  text-rendering: optimizeLegibility;
}
main#contenu {
  margin: 0 auto;
  max-width: 210mm;
  background: var(--papier);
  border: 1px solid var(--trait);
  padding: 20mm 20mm 22mm;
  box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
}
header.cartouche {
  border-bottom: 2px solid var(--marine);
  padding-bottom: 0.7rem;
  margin-bottom: 1.5rem;
  font-family: "Inter", "Segoe UI", "DejaVu Sans", Arial, sans-serif;
}
header.cartouche .institution {
  font-size: 0.76rem;
  font-weight: 700;
  letter-spacing: 0.09em;
  text-transform: uppercase;
  color: var(--marine);
}
header.cartouche .meta {
  margin-top: 0.2rem;
  font-size: 0.82rem;
  color: var(--encre-douce);
}
h1, h2, h3, h4 {
  font-family: "Inter", "Segoe UI", "DejaVu Sans", Arial, sans-serif;
  line-height: 1.25;
  margin: 1.8em 0 0.55em;
  font-weight: 700;
  color: var(--marine);
  break-after: avoid;
}
h1 {
  font-size: 1.75rem;
  margin-top: 0.2rem;
  padding-bottom: 0.4rem;
  border-bottom: 2px solid var(--marine);
  letter-spacing: -0.01em;
}
h2 {
  font-size: 1.28rem;
  padding-bottom: 0.25rem;
  border-bottom: 1px solid var(--trait);
}
h3 { font-size: 1.08rem; color: #1e3a5f; }
h4 {
  font-size: 0.94rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--encre-douce);
}
p {
  margin: 0 0 0.8rem;
  text-align: justify;
  hyphens: auto;
}
li {
  margin: 0.25rem 0;
  text-align: left;
}
a { color: var(--marine); text-decoration: underline; text-underline-offset: 0.16em; }
a:hover { text-decoration-thickness: 2px; }
code {
  font-family: "DejaVu Sans Mono", ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 0.88em;
  background: var(--code-fond);
  border: 1px solid var(--trait-fin);
  padding: 0.06em 0.3em;
  border-radius: 0;
}
pre {
  background: var(--code-fond);
  padding: 0.85rem 1rem;
  border: 1px solid var(--trait);
  border-left: 3px solid var(--marine);
  border-radius: 0;
  overflow-x: auto;
  font-size: 0.84rem;
  line-height: 1.48;
  margin: 1rem 0 1.2rem;
}
pre code { background: none; border: 0; padding: 0; font-size: inherit; }
blockquote {
  margin: 1.2rem 0;
  padding: 0.75rem 1rem;
  border: 1px solid var(--trait);
  border-left: 3px solid var(--marine);
  background: #f8fafc;
  border-radius: 0;
}
blockquote p { margin: 0.3rem 0; text-align: left; }
/* Style LaTeX booktabs */
table {
  width: 100%;
  border-collapse: collapse;
  margin: 1.15rem 0 1.35rem;
  font-size: 0.91rem;
  border-top: 2px solid var(--marine);
  border-bottom: 2px solid var(--marine);
}
caption {
  caption-side: top;
  text-align: left;
  font-weight: 700;
  font-family: "Inter", "Segoe UI", "DejaVu Sans", Arial, sans-serif;
  color: var(--marine);
  padding-bottom: 0.35rem;
}
th, td {
  border-left: 0;
  border-right: 0;
  border-top: 0;
  border-bottom: 1px solid var(--trait-fin);
  padding: 0.45rem 0.6rem;
  text-align: left;
  vertical-align: top;
}
thead th {
  font-family: "Inter", "Segoe UI", "DejaVu Sans", Arial, sans-serif;
  background: var(--marine-clair);
  color: var(--marine);
  font-weight: 700;
  border-bottom: 1.5px solid var(--marine);
}
tbody tr:nth-child(even) { background: #f8fafc; }
tbody tr:last-child td { border-bottom: 0; }
img {
  max-width: 100%;
  height: auto;
  display: block;
  margin: 1.2rem auto;
  border: 1px solid var(--trait);
  border-radius: 0;
  padding: 4px;
  background: #ffffff;
}
hr { border: 0; border-top: 1px solid var(--trait); margin: 2rem 0; }
ul, ol { padding-left: 1.4rem; margin: 0.5rem 0 0.9rem; }
.toc {
  background: #f8fafc;
  border: 1px solid var(--trait);
  border-left: 3px solid var(--marine);
  border-radius: 0;
  padding: 0.9rem 1.2rem;
  margin: 1rem 0 1.6rem;
  font-family: "Inter", "Segoe UI", "DejaVu Sans", Arial, sans-serif;
  font-size: 0.9rem;
}
.toc::before {
  content: "SOMMAIRE";
  display: block;
  font-size: 0.76rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: var(--marine);
  margin-bottom: 0.45rem;
}
.toc > ul { list-style: none; padding-left: 0; margin: 0; }
.toc ul ul { padding-left: 1.1rem; }
.toc a { text-decoration: none; }
.toc a:hover { text-decoration: underline; }
footer.doc {
  margin-top: 3rem;
  padding-top: 0.75rem;
  border-top: 1px solid var(--marine);
  color: var(--encre-douce);
  font-family: "Inter", "Segoe UI", "DejaVu Sans", Arial, sans-serif;
  font-size: 0.8rem;
}
footer.doc p { text-align: left; margin: 0; }
@media print {
  @page { size: A4; margin: 18mm 18mm; }
  body { padding: 0; background: #ffffff; font-size: 10pt; }
  main#contenu { max-width: none; border: 0; padding: 0; box-shadow: none; }
  h1, h2, h3 { break-after: avoid; }
  table, pre, img, blockquote { break-inside: avoid; }
  a { color: inherit; text-decoration: none; }
  a[href^="http"]::after { content: " (" attr(href) ")"; font-size: 0.82em; color: #475569; }
  .toc { break-after: page; }
}
"""


def data_uri(chemin: Path) -> str:
    """Encode un fichier binaire (image) sous forme d'URI data base64.

    Args:
        chemin: Chemin vers le fichier image à encoder.

    Returns:
        Chaîne ``data:<mime>;base64,...``.
    """
    type_mime = mimetypes.guess_type(chemin.name)[0] or "application/octet-stream"
    contenu = base64.b64encode(chemin.read_bytes()).decode("ascii")
    return f"data:{type_mime};base64,{contenu}"


def resoudre(source: Path, cible: str) -> Path | None:
    """Résout un lien relatif d'image par rapport au document source ou au dépôt.

    Args:
        source: Chemin du fichier Markdown courant.
        cible: URL ou chemin relatif extrait de la balise image.

    Returns:
        Chemin résolu si le fichier existe localement, sinon ``None``.
    """
    if re.match(r"^[a-z]+://", cible) or cible.startswith("data:"):
        return None
    propre = cible.split("#", 1)[0].split(" ", 1)[0]
    if not propre:
        return None
    for base in (source.parent, RACINE, source.parent.parent):
        candidat = (base / propre).resolve()
        if candidat.is_file():
            return candidat
    return None


def integrer_images(source: Path, corps: str) -> tuple[str, int]:
    """Remplace les sources ``<img src="...">`` locales par des URI base64 autonomes.

    Args:
        source: Fichier Markdown source servant de base aux chemins relatifs.
        corps: Fragment HTML généré par Python-Markdown.

    Returns:
        Tuple ``(html_enrichi, nombre_images_integrees)``.
    """
    manquantes: list[str] = []
    compte = 0

    def remplacer(match: re.Match[str]) -> str:
        """Remplace l'attribut src d'une balise img si le fichier cible existe."""
        nonlocal compte
        balise, chemin = match.group(0), match.group(1)
        if chemin.startswith("data:"):
            return balise
        fichier = resoudre(source, chemin)
        if fichier is None:
            manquantes.append(chemin)
            return balise
        compte += 1
        return balise.replace(chemin, data_uri(fichier), 1)

    corps = re.sub(r'<img[^>]*src="([^"]+)"[^>]*>', remplacer, corps)
    if manquantes:
        print(f"  ⚠ images introuvables : {', '.join(sorted(set(manquantes)))}", file=sys.stderr)
    return corps, compte


def reecrire_liens(corps: str) -> str:
    """Convertit les liens internes ``.md`` vers leur équivalent ``.html``.

    Args:
        corps: Fragment HTML généré par Markdown.

    Returns:
        HTML avec les liens internes pointant vers ``.html``.

    Example:
        >>> reecrire_liens('<a href="docs/04-conception-bd-et-sql.md#mcd">MCD</a>')
        '<a href="04-conception-bd-et-sql.html#mcd">MCD</a>'
    """
    return re.sub(
        r'href="([^"#]+?)\.md(#[^"]*)?"',
        lambda m: f'href="{Path(m.group(1)).name}.html{m.group(2) or ""}"',
        corps,
    )


def convertir(source: Path, sortie: Path, titre_force: str | None = None) -> dict[str, object]:
    """Convertit un document Markdown en page HTML5 autonome hors ligne.

    Args:
        source: Chemin du fichier Markdown source.
        sortie: Chemin du fichier HTML cible à écrire.
        titre_force: Titre optionnel remplaçant le premier H1 détecté.

    Returns:
        Dictionnaire récapitulatif (sortie, titre, images, octets).
    """
    brut = source.read_text(encoding="utf-8")
    brut = brut.replace("- [x] ", "- [OK] ").replace("- [ ] ", "- [ ] ").replace("- [~] ", "- [~] ")
    brut = re.sub(r"^\s*\\newpage\s*$", '<div style="break-before: page;"></div>', brut, flags=re.M)
    convertisseur = markdown.Markdown(
        extensions=["tables", "fenced_code", "toc", "sane_lists", "attr_list", "md_in_html"],
        extension_configs={"toc": {"permalink": "", "toc_depth": "2-3"}},
    )
    corps = convertisseur.convert(brut)
    sommaire = getattr(convertisseur, "toc", "")
    corps, nombre_images = integrer_images(source, corps)
    corps = reecrire_liens(corps)

    premier_h1 = re.search(r"<h1[^>]*>(.*?)</h1>", corps, flags=re.S)
    titre = titre_force or (re.sub(r"<[^>]+>", "", premier_h1.group(1)).strip() if premier_h1 else source.stem)

    sommaire_html = ""
    if sommaire and sommaire.count("<li>") >= 3:
        sommaire_html = f'<nav class="toc" aria-label="Sommaire">{sommaire}</nav>\n'

    rel_source = str(source.relative_to(RACINE)) if source.is_relative_to(RACINE) else source.name

    page = f"""<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Université Côte d'Azur — SAÉ 302 MiniShop : {html.escape(titre)}">
<title>{html.escape(titre)} — MiniShop (SAÉ 302)</title>
<style>{CSS}</style>
</head>
<body>
<main id="contenu">
<header class="cartouche">
  <div class="institution">Université Côte d’Azur · IUT de Nice — BUT Informatique (SAÉ 302)</div>
  <div class="meta">Projet MiniShop · Johan Polsinelli &amp; Chahd Gouiaa · Référence : <code>{html.escape(rel_source)}</code></div>
</header>
{sommaire_html}{corps}
<footer class="doc">
  <p>Université Côte d’Azur · BUT2 Informatique · SAÉ 302 — MiniShop · Source : <code>{html.escape(rel_source)}</code> · {nombre_images} figure(s) intégrée(s).</p>
</footer>
</main>
</body>
</html>
"""
    sortie.parent.mkdir(parents=True, exist_ok=True)
    sortie.write_text(page, encoding="utf-8")
    return {"sortie": sortie, "titre": titre, "images": nombre_images, "octets": len(page.encode("utf-8"))}


def main(argv: list[str] | None = None) -> int:
    """Point d'entrée CLI pour la conversion Markdown vers HTML autonome.

    Args:
        argv: Arguments de ligne de commande optionnels.

    Returns:
        Code de sortie 0 en cas de succès, 2 en cas d'erreur de fichier.
    """
    analyseur = argparse.ArgumentParser(description="Markdown → HTML académique (SAÉ 302 — MiniShop).")
    analyseur.add_argument("source", type=Path, help="fichier .md à convertir")
    analyseur.add_argument("--out", type=Path, help="fichier .html de sortie")
    analyseur.add_argument("--titre", help="titre imposé")
    arguments = analyseur.parse_args(argv)

    source = arguments.source if arguments.source.is_absolute() else (Path.cwd() / arguments.source)
    if not source.is_file():
        print(f"✘ source introuvable : {source}", file=sys.stderr)
        return 2
    sortie = arguments.out or source.with_suffix(".html")
    if not sortie.is_absolute():
        sortie = Path.cwd() / sortie

    try:
        resultat = convertir(source, sortie, arguments.titre)
    except OSError as erreur:
        print(f"✘ écriture impossible : {erreur}", file=sys.stderr)
        return 2
    print(f"✓ {sortie.relative_to(Path.cwd()) if sortie.is_relative_to(Path.cwd()) else sortie}"
          f"  ({resultat['octets'] // 1024} Ko, {resultat['images']} image(s))")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
