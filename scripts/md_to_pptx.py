#!/usr/bin/env python3
"""MiniShop — construit le diaporama de soutenance (PPTX) depuis un Markdown.

    python3 scripts/md_to_pptx.py docs/12-diaporama-soutenance.md \
        --out dist/minishop-soutenance.pptx

Format du fichier source (une diapositive par bloc, séparées par `---`) :

    # Titre de la diapositive
    ## Sous-titre (facultatif)
    - point clé
    - autre point
    ![légende](docs/diagrams/architecture_mvc.png)
    > message à retenir (encadré)

Rendu : 16:9, bandeau d'accent bleu, pied de page « SAÉ 302 · MiniShop »
avec numérotation, texte à taille adaptative (jamais moins de 16 pt).
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

try:
    from pptx import Presentation
    from pptx.dml.color import RGBColor
    from pptx.enum.text import PP_ALIGN
    from pptx.util import Emu, Inches, Pt
except ImportError:  # pragma: no cover
    print("✘ module Python « python-pptx » absent : pip install python-pptx", file=sys.stderr)
    raise SystemExit(2)

ACCENT = RGBColor(0x1D, 0x4E, 0xD8)
ENCRE = RGBColor(0x0F, 0x17, 0x2A)
DOUX = RGBColor(0x47, 0x55, 0x69)
FOND = RGBColor(0xFB, 0xFA, 0xF7)
BLANC = RGBColor(0xFF, 0xFF, 0xFF)
RACINE = Path(__file__).resolve().parent.parent


def decouper(markdown: str) -> list[dict[str, object]]:
    """Découpe le Markdown en diapositives (séparateur : ligne `---`)."""
    blocs = re.split(r"(?m)^\s*---\s*$", markdown)
    diapositives: list[dict[str, object]] = []
    for bloc in blocs:
        lignes = [ligne.rstrip() for ligne in bloc.strip().splitlines()]
        if not lignes:
            continue
        titre = ""
        sous_titre = ""
        puces: list[tuple[int, str]] = []
        message = ""
        image: str | None = None
        for ligne in lignes:
            if not ligne.strip():
                continue
            trouve = re.match(r"^#\s+(.*)$", ligne)
            if trouve and not titre:
                titre = trouve.group(1).strip()
                continue
            trouve = re.match(r"^##\s+(.*)$", ligne)
            if trouve and not sous_titre:
                sous_titre = trouve.group(1).strip()
                continue
            trouve = re.match(r"^(\s*)[-*+]\s+(.*)$", ligne)
            if trouve:
                puces.append((1 if len(trouve.group(1)) >= 2 else 0, trouve.group(2).strip()))
                continue
            trouve = re.match(r'^\s*!\[([^\]]*)\]\(([^)\s]+)[^)]*\)\s*$', ligne)
            if trouve:
                image = trouve.group(2)
                continue
            if ligne.startswith(">"):
                message = ligne.lstrip("> ").strip()
                continue
        if titre or puces or image:
            diapositives.append(
                {"titre": titre or "MiniShop", "sous_titre": sous_titre,
                 "puces": puces, "message": message, "image": image}
            )
    return diapositives


def nettoyer(texte: str) -> str:
    texte = re.sub(r"\*\*(.+?)\*\*", r"\1", texte)
    texte = re.sub(r"`([^`]+)`", r"\1", texte)
    return re.sub(r"(?<![\w*])\*([^*\n]+)\*(?![\w*])", r"\1", texte)


def construire(diapositives: list[dict[str, object]]) -> Presentation:
    presentation = Presentation()
    presentation.slide_width = Inches(13.333)
    presentation.slide_height = Inches(7.5)
    largeur = presentation.slide_width

    for numero, diapositive in enumerate(diapositives, start=1):
        slide = presentation.slides.add_slide(presentation.slide_layouts[6])  # vierge

        # fond
        fond = slide.shapes.add_shape(1, 0, 0, largeur, presentation.slide_height)
        fond.fill.solid()
        fond.fill.fore_color.rgb = FOND
        fond.line.fill.background()
        fond.shadow.inherit = False

        # bandeau d'accent
        bandeau = slide.shapes.add_shape(1, 0, 0, Inches(0.22), presentation.slide_height)
        bandeau.fill.solid()
        bandeau.fill.fore_color.rgb = ACCENT
        bandeau.line.fill.background()
        bandeau.shadow.inherit = False

        # titre
        zone_titre = slide.shapes.add_textbox(Inches(0.9), Inches(0.55), Inches(11.6), Inches(1.15))
        cadre = zone_titre.text_frame
        cadre.word_wrap = True
        paragraphe = cadre.paragraphs[0]
        run = paragraphe.add_run()
        run.text = nettoyer(str(diapositive["titre"]))
        run.font.size = Pt(34 if numero > 1 else 40)
        run.font.bold = True
        run.font.color.rgb = ENCRE
        run.font.name = "Calibri"

        sous_titre = str(diapositive.get("sous_titre") or "")
        if sous_titre:
            zone_sous_titre = slide.shapes.add_textbox(Inches(0.9), Inches(1.55), Inches(11.6), Inches(0.6))
            cadre = zone_sous_titre.text_frame
            cadre.word_wrap = True
            run = cadre.paragraphs[0].add_run()
            run.text = nettoyer(sous_titre)
            run.font.size = Pt(18)
            run.font.italic = True
            run.font.color.rgb = DOUX
            run.font.name = "Calibri"

        puces = diapositive["puces"] or []
        image = diapositive.get("image")
        largeur_texte = Inches(6.4) if image else Inches(11.4)

        if puces:
            hauteur = Inches(4.6 if not diapositive.get("message") else 3.9)
            zone = slide.shapes.add_textbox(Inches(0.9), Inches(2.25), largeur_texte, hauteur)
            cadre = zone.text_frame
            cadre.word_wrap = True
            taille = 22 if len(puces) <= 4 else 19 if len(puces) <= 6 else 17
            for index, (niveau, texte) in enumerate(puces):
                paragraphe = cadre.paragraphs[0] if index == 0 else cadre.add_paragraph()
                paragraphe.level = min(int(niveau), 4)
                paragraphe.space_after = Pt(8 if niveau == 0 else 4)
                run = paragraphe.add_run()
                run.text = ("• " if niveau == 0 else "– ") + nettoyer(texte)
                run.font.size = Pt(taille - 2 * int(niveau))
                run.font.color.rgb = ENCRE if niveau == 0 else DOUX
                run.font.name = "Calibri"

        if image:
            cible = (RACINE / str(image)).resolve()
            if cible.is_file():
                try:
                    slide.shapes.add_picture(str(cible), Inches(7.7), Inches(2.15), width=Inches(5.0))
                except Exception as erreur:  # image illisible : on continue
                    print(f"  ⚠ image ignorée ({cible.name}) : {erreur}", file=sys.stderr)
            else:
                print(f"  ⚠ image introuvable : {image}", file=sys.stderr)

        message = str(diapositive.get("message") or "")
        if message:
            encadre = slide.shapes.add_shape(1, Inches(0.9), Inches(6.05), Inches(11.5), Inches(0.75))
            encadre.fill.solid()
            encadre.fill.fore_color.rgb = RGBColor(0xDB, 0xEA, 0xFE)
            encadre.line.fill.background()
            encadre.shadow.inherit = False
            cadre = encadre.text_frame
            cadre.word_wrap = True
            cadre.margin_top = Emu(0)
            paragraphe = cadre.paragraphs[0]
            paragraphe.alignment = PP_ALIGN.LEFT
            run = paragraphe.add_run()
            run.text = nettoyer(message)
            run.font.size = Pt(16)
            run.font.bold = True
            run.font.color.rgb = RGBColor(0x1E, 0x3A, 0x8A)
            run.font.name = "Calibri"

        # pied de page
        pied = slide.shapes.add_textbox(Inches(0.9), Inches(6.95), Inches(11.6), Inches(0.35))
        cadre = pied.text_frame
        paragraphe = cadre.paragraphs[0]
        run = paragraphe.add_run()
        run.text = f"SAÉ 302 · MiniShop · {numero}/{len(diapositives)}"
        run.font.size = Pt(11)
        run.font.color.rgb = DOUX
        run.font.name = "Calibri"

    return presentation


def main(argv: list[str] | None = None) -> int:
    analyseur = argparse.ArgumentParser(description="Markdown → PPTX (SAÉ 302 — MiniShop).")
    analyseur.add_argument("source", type=Path)
    analyseur.add_argument("--out", type=Path, default=Path("dist/minishop-soutenance.pptx"))
    arguments = analyseur.parse_args(argv)

    source = arguments.source if arguments.source.is_absolute() else Path.cwd() / arguments.source
    if not source.is_file():
        print(f"✘ source introuvable : {source}", file=sys.stderr)
        return 2
    diapositives = decouper(source.read_text(encoding="utf-8"))
    if not diapositives:
        print("✘ aucune diapositive détectée (séparateur attendu : ---)", file=sys.stderr)
        return 2
    sortie = arguments.out if arguments.out.is_absolute() else Path.cwd() / arguments.out
    sortie.parent.mkdir(parents=True, exist_ok=True)
    construire(diapositives).save(str(sortie))
    print(f"✓ {sortie}  ({len(diapositives)} diapositives, {sortie.stat().st_size // 1024} Ko)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
