#!/usr/bin/env python3
"""MiniShop — convertit un document Markdown en HTML autonome.

    python3 scripts/md_to_html.py docs/01-cahier-des-charges-MiniShop.md \
        --out dist/html/01-cahier-des-charges-MiniShop.html

Caractéristiques (aucune dépendance à internet, aucune ressource externe) :

* **autonome** : les images (`docs/diagrams/*.png`, `uploads/*.png`) et les
  feuilles de style sont intégrées en `data:` / `<style>` — le fichier
  s'ouvre hors ligne, se projette et s'archive tel quel ;
* **navigation** : les liens `…md` deviennent `…html` (le dossier `dist/html/`
  est autosuffisant), les ancres sont conservées ;
* **lisibilité** : feuille de style d'impression (A4, marges, pas de coupure
  de tableau), sommaire dépliable, colonnes de texte bornées à 72 caractères
  (règle de composition GOV.UK) ;
* **GPU/typographie** : pile de polices système, taille en `rem`, `lang="fr"`.

Le script échoue proprement (code 2) si un fichier n'existe pas ; il ne
réécrit jamais un document absent.
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
except ImportError:  # pragma: no cover - message explicite
    print("✘ module Python « markdown » absent : pip install markdown", file=sys.stderr)
    raise SystemExit(2)

RACINE = Path(__file__).resolve().parent.parent

# --------------------------------------------------------------------------- #
#  Feuille de style du document (imprimable, palette du design system)
# --------------------------------------------------------------------------- #
CSS = """
:root {
  --encre: #0f172a;
  --encre-douce: #475569;
  --papier: #fbfaf7;
  --carte: #ffffff;
  --trait: #e2e8f0;
  --accent: #1d4ed8;
  --accent-clair: #dbeafe;
  --code: #f1f5f9;
  --mesure: 72ch;
}
@media (prefers-color-scheme: dark) {
  :root {
    --encre: #f8fafc; --encre-douce: #cbd5e1; --papier: #0b1220;
    --carte: #111c2e; --trait: #24344d; --accent: #93c5fd;
    --accent-clair: #1e3a8a; --code: #16223a;
  }
}
* { box-sizing: border-box; }
html { font-size: 100%; -webkit-text-size-adjust: 100%; }
body {
  margin: 0 auto;
  padding: 2.5rem 1.25rem 5rem;
  max-width: calc(var(--mesure) + 6rem);
  background: var(--papier);
  color: var(--encre);
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Ubuntu, Cantarell, "Helvetica Neue", Arial, sans-serif;
  font-size: 1rem;
  line-height: 1.65;
  text-rendering: optimizeLegibility;
}
h1, h2, h3, h4 { line-height: 1.25; margin: 2.2em 0 0.6em; font-weight: 700; }
h1 { font-size: 2rem; margin-top: 0; letter-spacing: -0.01em; }
h2 { font-size: 1.4rem; padding-bottom: 0.3rem; border-bottom: 2px solid var(--accent-clair); }
h3 { font-size: 1.15rem; }
h4 { font-size: 1rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--encre-douce); }
p, li { max-width: var(--mesure); }
a { color: var(--accent); text-underline-offset: 0.15em; }
a:hover { text-decoration-thickness: 2px; }
code {
  font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
  font-size: 0.9em; background: var(--code); padding: 0.1em 0.35em; border-radius: 4px;
}
pre {
  background: var(--code); padding: 1rem 1.1rem; border-radius: 10px;
  overflow-x: auto; border: 1px solid var(--trait); font-size: 0.85rem; line-height: 1.5;
}
pre code { background: none; padding: 0; font-size: inherit; }
blockquote {
  margin: 1.5rem 0; padding: 0.85rem 1.1rem; border-left: 4px solid var(--accent);
  background: var(--accent-clair); border-radius: 0 8px 8px 0;
}
blockquote p { margin: 0.35rem 0; }
table { width: 100%; border-collapse: collapse; margin: 1.25rem 0; font-size: 0.92rem; }
caption { caption-side: top; text-align: left; font-weight: 600; padding-bottom: 0.4rem; }
th, td { border: 1px solid var(--trait); padding: 0.5rem 0.65rem; text-align: left; vertical-align: top; }
thead th { background: var(--accent-clair); }
tbody tr:nth-child(even) { background: color-mix(in srgb, var(--code) 55%, transparent); }
img { max-width: 100%; height: auto; display: block; margin: 1.25rem auto; border-radius: 8px; }
hr { border: 0; border-top: 1px solid var(--trait); margin: 2.5rem 0; }
ul, ol { padding-left: 1.4rem; }
li { margin: 0.25rem 0; }
li input[type="checkbox"] { margin-right: 0.4rem; }
.toc { background: var(--carte); border: 1px solid var(--trait); border-radius: 12px; padding: 1rem 1.25rem; }
.toc > ul { list-style: none; padding-left: 0; }
.toc ul ul { padding-left: 1rem; }
footer.doc { margin-top: 4rem; padding-top: 1rem; border-top: 1px solid var(--trait); color: var(--encre-douce); font-size: 0.85rem; }
@media print {
  :root { --papier: #fff; --carte: #fff; }
  @page { size: A4; margin: 18mm 16mm; }
  body { padding: 0; max-width: none; font-size: 10.5pt; background: #fff; }
  h1, h2, h3 { break-after: avoid; }
  table, pre, img, blockquote { break-inside: avoid; }
  a { color: inherit; text-decoration: none; }
  a[href^="http"]::after { content: " (" attr(href) ")"; font-size: 0.8em; color: #555; }
  .toc { break-after: page; }
}
"""


def data_uri(chemin: Path) -> str:
    """Encode un fichier local en data URI (image uniquement)."""
    type_mime = mimetypes.guess_type(chemin.name)[0] or "application/octet-stream"
    contenu = base64.b64encode(chemin.read_bytes()).decode("ascii")
    return f"data:{type_mime};base64,{contenu}"


def resoudre(source: Path, cible: str) -> Path | None:
    """Résout une référence Markdown relative au document, sinon à la racine."""
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
    """Remplace les <img src> locaux par des data URI. Renvoie (html, nombre)."""
    manquantes: list[str] = []
    compte = 0

    def remplacer(match: re.Match[str]) -> str:
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
    """`…md` → `…html` pour que le dossier de rendu soit autonome."""
    return re.sub(r'href="([^"#]+?)\.md(#[^"]*)?"', lambda m: f'href="{m.group(1)}.html{m.group(2) or ""}"', corps)


def convertir(source: Path, sortie: Path, titre_force: str | None = None) -> dict[str, object]:
    brut = source.read_text(encoding="utf-8")
    # cases à cocher lisibles partout (le rendu Markdown les laisserait brutes)
    brut = brut.replace("- [x] ", "- ✅ ").replace("- [ ] ", "- ☐ ").replace("- [~] ", "- ◐ ")
    convertisseur = markdown.Markdown(
        extensions=["tables", "fenced_code", "toc", "sane_lists", "attr_list", "md_in_html"],
        extension_configs={"toc": {"permalink": "#", "toc_depth": "2-3"}},
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

    page = f"""<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="description" content="SAÉ 302 — MiniShop : {html.escape(titre)}">
<title>{html.escape(titre)} — MiniShop</title>
<style>{CSS}</style>
</head>
<body>
<main id="contenu">
{sommaire_html}{corps}
<footer class="doc">
  <p>SAÉ 302 — MiniShop · document généré depuis <code>{html.escape(str(source.relative_to(RACINE)))}</code>
  par <code>scripts/md_to_html.py</code> · aucune ressource externe ({nombre_images} image(s) intégrée(s)).</p>
</footer>
</main>
</body>
</html>
"""
    sortie.parent.mkdir(parents=True, exist_ok=True)
    sortie.write_text(page, encoding="utf-8")
    return {"sortie": sortie, "titre": titre, "images": nombre_images, "octets": len(page.encode("utf-8"))}


def main(argv: list[str] | None = None) -> int:
    analyseur = argparse.ArgumentParser(description="Markdown → HTML autonome (SAÉ 302 — MiniShop).")
    analyseur.add_argument("source", type=Path, help="fichier .md à convertir")
    analyseur.add_argument("--out", type=Path, help="fichier .html de sortie (défaut : à côté du .md)")
    analyseur.add_argument("--titre", help="titre imposé (défaut : premier titre de niveau 1)")
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
