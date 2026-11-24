#!/usr/bin/env python3
"""MiniShop — construit le diaporama de soutenance académique (PPTX 16:9).

    python3 scripts/md_to_pptx.py docs/12-diaporama-soutenance.md \
        --out dist/minishop-soutenance.pptx

Présentation sobre et structurée (« format très droit ») :
* fond blanc pur, bandeau supérieur et barre gauche bleu marine (#0F294A) ;
* sur-titre institutionnel Université Côte d'Azur · BUT Informatique ;
* police Serif (Georgia) pour les titres/messages et Sans-Serif (Calibri)
  pour les points clés ;
* encadré de synthèse droit avec barre latérale marine.
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

MARINE = RGBColor(0x0F, 0x29, 0x4A)
ENCRE = RGBColor(0x0F, 0x17, 0x2A)
DOUX = RGBColor(0x47, 0x55, 0x69)
FOND_ENCADRE = RGBColor(0xF1, 0xF5, 0xF9)
FILET = RGBColor(0xCB, 0xD5, 0xE1)
BLANC = RGBColor(0xFF, 0xFF, 0xFF)
RACINE = Path(__file__).resolve().parent.parent


def decouper(markdown: str) -> list[dict[str, object]]:
    r"""Découpe un fichier Markdown en diapositives séparées par ``---``.

    Args:
        markdown: Contenu brut du support de diapositives.

    Returns:
        Liste de dictionnaires décrivant chaque diapositive (titre, sous-titre,
        puces, message et image optionnelle).

    Example:
        >>> slides = decouper("# Titre\n- Point 1\n---\n# Suite\n- Point 2")
        >>> [s["titre"] for s in slides]
        ['Titre', 'Suite']
    """
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
        logos: list[str] = []
        row_images: list[str] = []
        for ligne in lignes:
            if not ligne.strip():
                continue
            trouve_logos = re.match(r"^\s*<!--\s*logos:\s*(.+?)\s*-->\s*$", ligne)
            if trouve_logos:
                logos.extend(trouve_logos.group(1).split())
                continue
            trouve_row_images = re.match(r"^\s*<!--\s*row-images:\s*(.+?)\s*-->\s*$", ligne)
            if trouve_row_images:
                row_images.extend(trouve_row_images.group(1).split())
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
        if titre or puces or image or logos or row_images:
            diapositives.append(
                {"titre": titre or "MiniShop", "sous_titre": sous_titre,
                 "puces": puces, "message": message, "image": image,
                 "logos": logos, "row_images": row_images}
            )
    return diapositives


def nettoyer(texte: str) -> str:
    """Supprime le marquage Markdown inline (gras, italique, code) d'un texte.

    Args:
        texte: Chaîne pouvant contenir ``**gras**``, ``*italique*`` ou ```code```.

    Returns:
        Texte brut prêt pour l'injection dans une zone PowerPoint.

    Example:
        >>> nettoyer("Test **MiniShop** avec `PHP` et *SQL*")
        'Test MiniShop avec PHP et SQL'
    """
    texte = re.sub(r"\*\*(.+?)\*\*", r"\1", texte)
    texte = re.sub(r"`([^`]+)`", r"\1", texte)
    return re.sub(r"(?<![\w*])\*([^*\n]+)\*(?![\w*])", r"\1", texte)


def construire(diapositives: list[dict[str, object]]) -> Presentation:
    """Construit une présentation PowerPoint 16:9 à partir des diapositives extraites.

    Args:
        diapositives: Liste de diapositives structurées produite par ``decouper``.

    Returns:
        Objet ``Presentation`` prêt à être enregistré via ``.save()``.
    """
    presentation = Presentation()
    presentation.slide_width = Inches(13.333)
    presentation.slide_height = Inches(7.5)
    largeur = presentation.slide_width

    for numero, diapositive in enumerate(diapositives, start=1):
        slide = presentation.slides.add_slide(presentation.slide_layouts[6])

        # Fond blanc pur
        fond = slide.shapes.add_shape(1, 0, 0, largeur, presentation.slide_height)
        fond.fill.solid()
        fond.fill.fore_color.rgb = BLANC
        fond.line.fill.background()
        fond.shadow.inherit = False

        # Bandeau supérieur droit + barre gauche sobre
        bandeau_haut = slide.shapes.add_shape(1, 0, 0, largeur, Inches(0.08))
        bandeau_haut.fill.solid()
        bandeau_haut.fill.fore_color.rgb = MARINE
        bandeau_haut.line.fill.background()
        bandeau_haut.shadow.inherit = False

        bandeau_gauche = slide.shapes.add_shape(1, 0, 0, Inches(0.14), presentation.slide_height)
        bandeau_gauche.fill.solid()
        bandeau_gauche.fill.fore_color.rgb = MARINE
        bandeau_gauche.line.fill.background()
        bandeau_gauche.shadow.inherit = False

        # Sur-titre institutionnel
        kicker = slide.shapes.add_textbox(Inches(0.85), Inches(0.28), Inches(11.6), Inches(0.28))
        r_k = kicker.text_frame.paragraphs[0].add_run()
        r_k.text = "UNIVERSITÉ CÔTE D'AZUR  ·  IUT DE NICE  ·  BUT INFORMATIQUE  ·  SAÉ 302"
        r_k.font.size = Pt(9.5)
        r_k.font.bold = True
        r_k.font.color.rgb = MARINE
        r_k.font.name = "Calibri"

        # Titre principal
        zone_titre = slide.shapes.add_textbox(Inches(0.85), Inches(0.58), Inches(11.6), Inches(0.9))
        cadre = zone_titre.text_frame
        cadre.word_wrap = True
        run = cadre.paragraphs[0].add_run()
        run.text = nettoyer(str(diapositive["titre"]))
        run.font.size = Pt(30 if numero > 1 else 36)
        run.font.bold = True
        run.font.color.rgb = MARINE
        run.font.name = "Georgia"

        # Filet sous titre
        filet_titre = slide.shapes.add_shape(1, Inches(0.85), Inches(1.42), Inches(11.6), Inches(0.02))
        filet_titre.fill.solid()
        filet_titre.fill.fore_color.rgb = MARINE
        filet_titre.line.fill.background()
        filet_titre.shadow.inherit = False

        sous_titre = str(diapositive.get("sous_titre") or "")
        if sous_titre:
            zone_sous_titre = slide.shapes.add_textbox(Inches(0.85), Inches(1.52), Inches(11.6), Inches(0.5))
            cadre = zone_sous_titre.text_frame
            cadre.word_wrap = True
            run = cadre.paragraphs[0].add_run()
            run.text = nettoyer(sous_titre)
            run.font.size = Pt(16)
            run.font.italic = True
            run.font.color.rgb = DOUX
            run.font.name = "Georgia"

        puces = diapositive["puces"] or []
        image = diapositive.get("image")
        row_images = [str(x) for x in (diapositive.get("row_images") or [])]
        largeur_texte = Inches(6.5) if image else Inches(11.6)
        top_corps = 2.15 if sous_titre else 1.78

        if puces and row_images:
            taille = 20 if len(puces) <= 4 else 17.5
            n_lignes = len(puces)
            haut_dispo = (6.65 - top_corps) if not diapositive.get("message") else (5.85 - top_corps)
            pas = haut_dispo / max(n_lignes, 1)
            y_depart = top_corps
            centre_x_img = Inches(10.80)
            for index, (niveau, texte) in enumerate(puces):
                centre_y = Inches(y_depart + pas * (index + 0.5))
                zone = slide.shapes.add_textbox(
                    Inches(0.85),
                    int(centre_y - Inches(0.28)),
                    Inches(8.60),
                    Inches(0.56),
                )
                cadre = zone.text_frame
                cadre.word_wrap = True
                paragraphe = cadre.paragraphs[0]
                run = paragraphe.add_run()
                run.text = ("▪  " if niveau == 0 else "–  ") + nettoyer(texte)
                run.font.size = Pt(taille)
                run.font.color.rgb = ENCRE if niveau == 0 else DOUX
                run.font.name = "Calibri"

                if index < len(row_images):
                    cible_row = (RACINE / row_images[index]).resolve()
                    if cible_row.is_file():
                        h_cible = Inches(0.68 if cible_row.stem == "php" else 0.86)
                        pic_tmp = slide.shapes.add_picture(str(cible_row), 0, 0, height=h_cible)
                        w_r = int(pic_tmp.width)
                        h_r = int(pic_tmp.height)
                        sp_el = pic_tmp._element
                        sp_el.getparent().remove(sp_el)
                        slide.shapes.add_picture(
                            str(cible_row),
                            int(centre_x_img - w_r / 2),
                            int(centre_y - h_r / 2),
                            width=w_r,
                            height=h_r,
                        )
        elif puces:
            hauteur = Inches(4.65 if not diapositive.get("message") else 3.95)
            zone = slide.shapes.add_textbox(Inches(0.85), Inches(top_corps), largeur_texte, hauteur)
            cadre = zone.text_frame
            cadre.word_wrap = True
            taille = 20 if len(puces) <= 4 else 17.5 if len(puces) <= 6 else 16
            for index, (niveau, texte) in enumerate(puces):
                paragraphe = cadre.paragraphs[0] if index == 0 else cadre.add_paragraph()
                paragraphe.level = min(int(niveau), 4)
                paragraphe.space_after = Pt(10 if niveau == 0 else 5)
                run = paragraphe.add_run()
                run.text = ("▪  " if niveau == 0 else "–  ") + nettoyer(texte)
                run.font.size = Pt(taille - 1.5 * int(niveau))
                run.font.color.rgb = ENCRE if niveau == 0 else DOUX
                run.font.name = "Calibri"

        image_left: int | None = None
        if image:
            cible = (RACINE / str(image)).resolve()
            if cible.is_file():
                try:
                    top_img = Inches(2.1 if sous_titre else 1.75)
                    pic = slide.shapes.add_picture(str(cible), Inches(7.65), top_img, width=Inches(4.9))
                    max_h = Inches(4.10 if diapositive.get("message") else 4.75)
                    if pic.height > max_h:
                        ratio = max_h / pic.height
                        pic.height = int(max_h)
                        pic.width = int(pic.width * ratio)
                        pic.left = int(Inches(7.65) + (Inches(4.9) - pic.width) / 2)
                    image_left = int(pic.left)
                except Exception as erreur:
                    print(f"  ⚠ image ignorée ({cible.name}) : {erreur}", file=sys.stderr)
            else:
                print(f"  ⚠ image introuvable : {image}", file=sys.stderr)

        logos = [str(x) for x in (diapositive.get("logos") or [])]
        if logos:
            specs: list[tuple[Path, int, int]] = []
            for chemin_logo in logos:
                cible_logo = (RACINE / chemin_logo).resolve()
                if not cible_logo.is_file():
                    continue
                h_cible = Inches(0.70 if cible_logo.stem == "php" else 0.88)
                pic_tmp = slide.shapes.add_picture(str(cible_logo), 0, 0, height=h_cible)
                specs.append((cible_logo, int(pic_tmp.width), int(pic_tmp.height)))
                sp_el = pic_tmp._element
                sp_el.getparent().remove(sp_el)
            if specs:
                gap = Inches(0.55)
                total_w = sum(w for _, w, _ in specs) + gap * (len(specs) - 1)
                ideal_left = int((largeur - total_w) / 2)
                max_right = (image_left - Inches(0.45)) if image_left is not None else (largeur - Inches(0.85))
                cur_x = max(int(Inches(0.85)), min(ideal_left, int(max_right - total_w)))
                mid_y = int(Inches(5.30))
                for cible_logo, w_logo, h_logo in specs:
                    slide.shapes.add_picture(
                        str(cible_logo),
                        cur_x,
                        int(mid_y - h_logo / 2),
                        width=w_logo,
                        height=h_logo,
                    )
                    cur_x += w_logo + gap

        message = str(diapositive.get("message") or "")
        if message:
            encadre = slide.shapes.add_shape(1, Inches(0.85), Inches(6.02), Inches(11.6), Inches(0.75))
            encadre.fill.solid()
            encadre.fill.fore_color.rgb = FOND_ENCADRE
            encadre.line.color.rgb = FILET
            encadre.shadow.inherit = False

            barre = slide.shapes.add_shape(1, Inches(0.85), Inches(6.02), Inches(0.08), Inches(0.75))
            barre.fill.solid()
            barre.fill.fore_color.rgb = MARINE
            barre.line.fill.background()
            barre.shadow.inherit = False

            cadre = encadre.text_frame
            cadre.word_wrap = True
            cadre.margin_left = Inches(0.22)
            cadre.margin_top = Emu(45720)
            paragraphe = cadre.paragraphs[0]
            paragraphe.alignment = PP_ALIGN.LEFT
            run = paragraphe.add_run()
            run.text = nettoyer(message)
            run.font.size = Pt(14.5)
            run.font.bold = True
            run.font.color.rgb = MARINE
            run.font.name = "Calibri"

        # Filet bas + pied de page
        filet_bas = slide.shapes.add_shape(1, Inches(0.85), Inches(6.9), Inches(11.6), Inches(0.015))
        filet_bas.fill.solid()
        filet_bas.fill.fore_color.rgb = FILET
        filet_bas.line.fill.background()
        filet_bas.shadow.inherit = False

        pied = slide.shapes.add_textbox(Inches(0.85), Inches(6.95), Inches(8.6), Inches(0.35))
        run_p = pied.text_frame.paragraphs[0].add_run()
        run_p.text = "SAÉ 302 · MiniShop · Johan Polsinelli & Chahd Gouiaa"
        run_p.font.size = Pt(10.5)
        run_p.font.color.rgb = DOUX
        run_p.font.name = "Calibri"

        num = slide.shapes.add_textbox(Inches(9.6), Inches(6.95), Inches(2.85), Inches(0.35))
        p_n = num.text_frame.paragraphs[0]
        p_n.alignment = PP_ALIGN.RIGHT
        run_n = p_n.add_run()
        run_n.text = f"Diapositive {numero} / {len(diapositives)}"
        run_n.font.size = Pt(10.5)
        run_n.font.bold = True
        run_n.font.color.rgb = MARINE
        run_n.font.name = "Calibri"

    return presentation


def main(argv: list[str] | None = None) -> int:
    """Point d'entrée CLI pour générer le diaporama PowerPoint depuis Markdown.

    Args:
        argv: Arguments de ligne de commande optionnels.

    Returns:
        Code de sortie 0 en cas de succès, 2 si la source est absente ou vide.
    """
    analyseur = argparse.ArgumentParser(description="Markdown → PPTX académique (SAÉ 302 — MiniShop).")
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
