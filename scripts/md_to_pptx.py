#!/usr/bin/env python3
"""MiniShop — construit le diaporama de soutenance académique (PPTX 16:9).

    python3 scripts/md_to_pptx.py docs/12-diaporama-soutenance.md \
        --out dist/minishop-soutenance.pptx

Présentation sobre et structurée (« format très droit ») :
* fond blanc pur, bandeau supérieur et barre gauche bleu marine (#0F294A) ;
* sur-titre institutionnel Université Côte d'Azur · BUT Informatique ;
* police Serif (Georgia) pour les titres/messages et Sans-Serif (Calibri)
  pour les points clés ;
* mises en page variées via ``<!-- layout: ... -->`` (grille Bento 2×2,
  image à gauche, indicateurs KPI, piliers verticaux, étapes numérotées,
  comparaison à deux panneaux).
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

try:
    from pptx import Presentation
    from pptx.dml.color import RGBColor
    from pptx.enum.text import MSO_ANCHOR, PP_ALIGN
    from pptx.util import Emu, Inches, Pt
except ImportError:  # pragma: no cover
    print("✘ module Python « python-pptx » absent : pip install python-pptx", file=sys.stderr)
    raise SystemExit(2)

MARINE = RGBColor(0x0F, 0x29, 0x4A)
ACCENT = RGBColor(0x1D, 0x4E, 0xD8)
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
        puces, message, mise en page et médias optionnels).

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
        layout = ""
        for ligne in lignes:
            if not ligne.strip():
                continue
            trouve_layout = re.match(r"^\s*<!--\s*layout:\s*([a-z0-9_-]+)\s*-->\s*$", ligne)
            if trouve_layout:
                layout = trouve_layout.group(1).strip()
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
                {
                    "titre": titre or "MiniShop",
                    "sous_titre": sous_titre,
                    "puces": puces,
                    "message": message,
                    "image": image,
                    "logos": logos,
                    "row_images": row_images,
                    "layout": layout,
                }
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


def _separer_label(texte: str) -> tuple[str, str]:
    """Sépare un point clé en (titre, description) sur `` · `` ou `` : ``."""
    propre = nettoyer(texte)
    for sep in (" · ", " : "):
        if sep in propre:
            tete, corps = propre.split(sep, 1)
            return tete.strip(), corps.strip()
    return "", propre


def _rect(
    slide: object,
    left: int,
    top: int,
    width: int,
    height: int,
    fill_color: RGBColor,
    border_color: RGBColor | None = None,
) -> object:
    """Ajoute un rectangle plat sans ombre."""
    shape = slide.shapes.add_shape(1, left, top, width, height)
    shape.fill.solid()
    shape.fill.fore_color.rgb = fill_color
    if border_color is None:
        shape.line.fill.background()
    else:
        shape.line.color.rgb = border_color
    shape.shadow.inherit = False
    return shape


def _dessiner_grid_2x2(
    slide: object,
    puces: list[tuple[int, str]],
    row_images: list[str],
    top_y: float,
) -> None:
    """Rend une grille Bento 2x2 avec icône en haut à droite de chaque carte."""
    gap_x = 0.32
    gap_y = 0.28
    card_w = (11.60 - gap_x) / 2
    card_h = (6.62 - top_y - gap_y) / 2
    for idx, (_, texte) in enumerate(puces[:4]):
        col = idx % 2
        row = idx // 2
        x = 0.85 + col * (card_w + gap_x)
        y = top_y + row * (card_h + gap_y)
        _rect(slide, Inches(x), Inches(y), Inches(card_w), Inches(card_h), FOND_ENCADRE, FILET)
        accent_c = MARINE if idx in (0, 3) else ACCENT
        _rect(slide, Inches(x), Inches(y), Inches(card_w), Inches(0.07), accent_c)

        titre_carte, corps_carte = _separer_label(texte)
        icon_w_used = 0.0
        if idx < len(row_images):
            cible = (RACINE / row_images[idx]).resolve()
            if cible.is_file():
                pic_tmp = slide.shapes.add_picture(str(cible), 0, 0, height=Inches(0.68))
                w_px, h_px = int(pic_tmp.width), int(pic_tmp.height)
                pic_tmp._element.getparent().remove(pic_tmp._element)
                slide.shapes.add_picture(
                    str(cible),
                    int(Inches(x + card_w - 0.24) - w_px),
                    Inches(y + 0.22),
                    width=w_px,
                    height=h_px,
                )
                icon_w_used = w_px / 914400 + 0.35

        zone_t = slide.shapes.add_textbox(
            Inches(x + 0.26),
            Inches(y + 0.22),
            Inches(card_w - 0.45 - icon_w_used),
            Inches(0.55),
        )
        ct = zone_t.text_frame
        ct.word_wrap = True
        rt = ct.paragraphs[0].add_run()
        rt.text = titre_carte or "Module"
        rt.font.size = Pt(20)
        rt.font.bold = True
        rt.font.color.rgb = MARINE
        rt.font.name = "Georgia"

        zone_b = slide.shapes.add_textbox(
            Inches(x + 0.26),
            Inches(y + 0.88),
            Inches(card_w - 0.52),
            Inches(card_h - 1.05),
        )
        cb = zone_b.text_frame
        cb.word_wrap = True
        rb = cb.paragraphs[0].add_run()
        rb.text = corps_carte
        rb.font.size = Pt(16)
        rb.font.color.rgb = ENCRE
        rb.font.name = "Calibri"


def _dessiner_bento_sec(
    slide: object,
    puces: list[tuple[int, str]],
    top_y: float,
) -> None:
    """Rend 4 cartes sécurité 2x2 et une bannière d'audit marine en bas."""
    gap_x = 0.28
    gap_y = 0.22
    card_w = (11.60 - gap_x) / 2
    banner_h = 0.90
    card_h = (6.62 - top_y - banner_h - gap_y * 2) / 2

    for idx, (_, texte) in enumerate(puces[:4]):
        col = idx % 2
        row = idx // 2
        x = 0.85 + col * (card_w + gap_x)
        y = top_y + row * (card_h + gap_y)
        _rect(slide, Inches(x), Inches(y), Inches(card_w), Inches(card_h), FOND_ENCADRE, FILET)
        _rect(slide, Inches(x), Inches(y), Inches(0.08), Inches(card_h), MARINE if row == 0 else ACCENT)
        titre_c, corps_c = _separer_label(texte)
        zt = slide.shapes.add_textbox(Inches(x + 0.24), Inches(y + 0.14), Inches(card_w - 0.40), Inches(1.48))
        tf = zt.text_frame
        tf.word_wrap = True
        p0 = tf.paragraphs[0]
        p0.space_after = Pt(4)
        r0 = p0.add_run()
        r0.text = titre_c
        r0.font.size = Pt(17)
        r0.font.bold = True
        r0.font.color.rgb = MARINE
        r0.font.name = "Georgia"
        p1 = tf.add_paragraph()
        r1 = p1.add_run()
        r1.text = corps_c
        r1.font.size = Pt(14.5)
        r1.font.color.rgb = ENCRE
        r1.font.name = "Calibri"

    if len(puces) >= 5:
        by = top_y + 2 * (card_h + gap_y)
        _rect(slide, Inches(0.85), Inches(by), Inches(11.60), Inches(banner_h), MARINE)
        _rect(slide, Inches(0.85), Inches(by), Inches(0.10), Inches(banner_h), ACCENT)
        titre_b, corps_b = _separer_label(puces[4][1])
        zb = slide.shapes.add_textbox(Inches(1.15), Inches(by + 0.08), Inches(11.10), Inches(banner_h - 0.16))
        tf = zb.text_frame
        tf.word_wrap = True
        tf.vertical_anchor = MSO_ANCHOR.MIDDLE
        p = tf.paragraphs[0]
        r0 = p.add_run()
        r0.text = f"{titre_b}  —  " if titre_b else ""
        r0.font.size = Pt(16)
        r0.font.bold = True
        r0.font.color.rgb = BLANC
        r0.font.name = "Georgia"
        r1 = p.add_run()
        r1.text = corps_b
        r1.font.size = Pt(15.5)
        r1.font.color.rgb = BLANC
        r1.font.name = "Calibri"


def _dessiner_kpi_4(
    slide: object,
    puces: list[tuple[int, str]],
    top_y: float,
) -> None:
    """Rend 4 colonnes d'indicateurs chiffrés avec en-tête métrique coloré."""
    gap = 0.24
    col_w = (11.60 - gap * 3) / 4
    total_h = 6.55 - top_y
    head_h = 1.65

    for idx, (_, texte) in enumerate(puces[:4]):
        x = 0.85 + idx * (col_w + gap)
        is_hero = idx == 0
        head_bg = MARINE if is_hero else FOND_ENCADRE
        body_bg = FOND_ENCADRE if is_hero else BLANC
        head_fg = BLANC if is_hero else MARINE
        sub_fg = BLANC if is_hero else ACCENT

        _rect(slide, Inches(x), Inches(top_y), Inches(col_w), Inches(total_h), body_bg, FILET)
        _rect(slide, Inches(x), Inches(top_y), Inches(col_w), Inches(head_h), head_bg, FILET)
        if not is_hero:
            _rect(slide, Inches(x), Inches(top_y), Inches(col_w), Inches(0.07), ACCENT)

        titre_k, corps_k = _separer_label(texte)
        morceaux = titre_k.split(" ", 1) if titre_k else ["", ""]
        chiffre = morceaux[0]
        label = morceaux[1] if len(morceaux) > 1 else ""

        zh = slide.shapes.add_textbox(Inches(x + 0.16), Inches(top_y + 0.16), Inches(col_w - 0.32), Inches(head_h - 0.26))
        tfh = zh.text_frame
        tfh.word_wrap = True
        tfh.vertical_anchor = MSO_ANCHOR.MIDDLE
        p0 = tfh.paragraphs[0]
        p0.alignment = PP_ALIGN.CENTER
        p0.space_after = Pt(4)
        r0 = p0.add_run()
        r0.text = chiffre
        r0.font.size = Pt(30 if len(chiffre) <= 7 else 24)
        r0.font.bold = True
        r0.font.color.rgb = head_fg
        r0.font.name = "Georgia"
        if label:
            p1 = tfh.add_paragraph()
            p1.alignment = PP_ALIGN.CENTER
            r1 = p1.add_run()
            r1.text = label
            r1.font.size = Pt(13.5)
            r1.font.bold = True
            r1.font.color.rgb = sub_fg
            r1.font.name = "Calibri"

        zb = slide.shapes.add_textbox(
            Inches(x + 0.20),
            Inches(top_y + head_h + 0.20),
            Inches(col_w - 0.40),
            Inches(total_h - head_h - 0.35),
        )
        tfb = zb.text_frame
        tfb.word_wrap = True
        rb = tfb.paragraphs[0].add_run()
        rb.text = corps_k
        rb.font.size = Pt(15)
        rb.font.color.rgb = ENCRE
        rb.font.name = "Calibri"


def _dessiner_pillars(
    slide: object,
    puces: list[tuple[int, str]],
    top_y: float,
    nb_cols: int,
) -> None:
    """Rend 3 ou 4 piliers verticaux, avec bannière basse si une 4e puce existe en mode 3 colonnes."""
    gap = 0.24 if nb_cols == 4 else 0.30
    col_w = (11.60 - gap * (nb_cols - 1)) / nb_cols
    has_banner = nb_cols == 3 and len(puces) > 3
    col_h = (6.55 - top_y - (1.18 if has_banner else 0.0))

    for idx, (_, texte) in enumerate(puces[:nb_cols]):
        x = 0.85 + idx * (col_w + gap)
        _rect(slide, Inches(x), Inches(top_y), Inches(col_w), Inches(col_h), FOND_ENCADRE, FILET)
        _rect(slide, Inches(x), Inches(top_y), Inches(col_w), Inches(0.08), MARINE if idx % 2 == 0 else ACCENT)

        titre_p, corps_p = _separer_label(texte)
        zt = slide.shapes.add_textbox(Inches(x + 0.20), Inches(top_y + 0.22), Inches(col_w - 0.40), Inches(0.75))
        tft = zt.text_frame
        tft.word_wrap = True
        rt = tft.paragraphs[0].add_run()
        rt.text = titre_p
        rt.font.size = Pt(17.5 if nb_cols == 4 else 19)
        rt.font.bold = True
        rt.font.color.rgb = MARINE
        rt.font.name = "Georgia"

        _rect(slide, Inches(x + 0.20), Inches(top_y + 0.98), Inches(col_w - 0.40), Inches(0.015), FILET)

        zb = slide.shapes.add_textbox(
            Inches(x + 0.20),
            Inches(top_y + 1.14),
            Inches(col_w - 0.40),
            Inches(col_h - 1.30),
        )
        tfb = zb.text_frame
        tfb.word_wrap = True
        rb = tfb.paragraphs[0].add_run()
        rb.text = corps_p
        rb.font.size = Pt(15 if nb_cols == 4 else 16)
        rb.font.color.rgb = ENCRE
        rb.font.name = "Calibri"

    if has_banner:
        by = top_y + col_h + 0.22
        _rect(slide, Inches(0.85), Inches(by), Inches(11.60), Inches(0.96), MARINE)
        _rect(slide, Inches(0.85), Inches(by), Inches(0.10), Inches(0.96), ACCENT)
        titre_b, corps_b = _separer_label(puces[3][1])
        zb = slide.shapes.add_textbox(Inches(1.15), Inches(by + 0.08), Inches(11.10), Inches(0.80))
        tf = zb.text_frame
        tf.word_wrap = True
        tf.vertical_anchor = MSO_ANCHOR.MIDDLE
        p = tf.paragraphs[0]
        r0 = p.add_run()
        r0.text = f"{titre_b}  —  " if titre_b else ""
        r0.font.size = Pt(16)
        r0.font.bold = True
        r0.font.color.rgb = BLANC
        r0.font.name = "Georgia"
        r1 = p.add_run()
        r1.text = corps_b
        r1.font.size = Pt(15.5)
        r1.font.color.rgb = BLANC
        r1.font.name = "Calibri"


def _dessiner_steps(
    slide: object,
    puces: list[tuple[int, str]],
    top_y: float,
) -> None:
    """Rend 4 étapes numérotées (badges 01..04) à gauche d'un diagramme."""
    n = min(len(puces), 4)
    pas = (6.55 - top_y) / max(n, 1)
    badge_size = 0.50
    if n > 1:
        line_top = top_y + pas * 0.5
        line_h = pas * (n - 1)
        _rect(slide, Inches(0.85 + badge_size / 2 - 0.015), Inches(line_top), Inches(0.03), Inches(line_h), FILET)

    for idx, (_, texte) in enumerate(puces[:4]):
        centre_y = top_y + pas * (idx + 0.5)
        badge_top = centre_y - badge_size / 2
        badge_bg = MARINE if idx % 2 == 0 else ACCENT
        badge = _rect(slide, Inches(0.85), Inches(badge_top), Inches(badge_size), Inches(badge_size), badge_bg)
        tf_b = badge.text_frame
        tf_b.vertical_anchor = MSO_ANCHOR.MIDDLE
        pb = tf_b.paragraphs[0]
        pb.alignment = PP_ALIGN.CENTER
        rb = pb.add_run()
        rb.text = f"0{idx + 1}"
        rb.font.size = Pt(15)
        rb.font.bold = True
        rb.font.color.rgb = BLANC
        rb.font.name = "Calibri"

        titre_s, corps_s = _separer_label(texte)
        zt = slide.shapes.add_textbox(Inches(1.52), Inches(centre_y - 0.46), Inches(5.80), Inches(0.92))
        tf = zt.text_frame
        tf.word_wrap = True
        tf.vertical_anchor = MSO_ANCHOR.MIDDLE
        p = tf.paragraphs[0]
        if titre_s:
            r0 = p.add_run()
            r0.text = f"{titre_s}  —  "
            r0.font.size = Pt(16.5)
            r0.font.bold = True
            r0.font.color.rgb = MARINE
            r0.font.name = "Georgia"
        r1 = p.add_run()
        r1.text = corps_s
        r1.font.size = Pt(15.5)
        r1.font.color.rgb = ENCRE
        r1.font.name = "Calibri"


def _dessiner_compare_2(
    slide: object,
    puces: list[tuple[int, str]],
    top_y: float,
) -> None:
    """Rend deux panneaux comparatifs côte à côte (Résolu vs Arbitrages)."""
    gap = 0.36
    panel_w = (11.60 - gap) / 2
    panel_h = 6.55 - top_y
    head_h = 0.62
    panneaux = [
        ("Problèmes résolus dans le code", MARINE, puces[:2]),
        ("Arbitrages et cible LineServ", ACCENT, puces[2:4]),
    ]
    for col, (entete, couleur, items) in enumerate(panneaux):
        x = 0.85 + col * (panel_w + gap)
        _rect(slide, Inches(x), Inches(top_y), Inches(panel_w), Inches(panel_h), FOND_ENCADRE, FILET)
        hdr = _rect(slide, Inches(x), Inches(top_y), Inches(panel_w), Inches(head_h), couleur)
        tfh = hdr.text_frame
        tfh.vertical_anchor = MSO_ANCHOR.MIDDLE
        tfh.margin_left = Inches(0.24)
        rh = tfh.paragraphs[0].add_run()
        rh.text = entete
        rh.font.size = Pt(18)
        rh.font.bold = True
        rh.font.color.rgb = BLANC
        rh.font.name = "Georgia"

        slot_h = (panel_h - head_h - 0.45) / max(len(items), 1)
        for idx, (_, texte) in enumerate(items):
            iy = top_y + head_h + 0.25 + idx * slot_h
            titre_i, corps_i = _separer_label(texte)
            zi = slide.shapes.add_textbox(
                Inches(x + 0.26),
                Inches(iy),
                Inches(panel_w - 0.52),
                Inches(slot_h - 0.14),
            )
            tf = zi.text_frame
            tf.word_wrap = True
            p0 = tf.paragraphs[0]
            p0.space_after = Pt(6)
            r0 = p0.add_run()
            r0.text = titre_i
            r0.font.size = Pt(17.5)
            r0.font.bold = True
            r0.font.color.rgb = MARINE
            r0.font.name = "Georgia"
            p1 = tf.add_paragraph()
            r1 = p1.add_run()
            r1.text = corps_i
            r1.font.size = Pt(16)
            r1.font.color.rgb = ENCRE
            r1.font.name = "Calibri"


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

        _rect(slide, 0, 0, largeur, presentation.slide_height, BLANC)
        _rect(slide, 0, 0, largeur, Inches(0.08), MARINE)
        _rect(slide, 0, 0, Inches(0.14), presentation.slide_height, MARINE)

        kicker = slide.shapes.add_textbox(Inches(0.85), Inches(0.28), Inches(11.6), Inches(0.28))
        r_k = kicker.text_frame.paragraphs[0].add_run()
        r_k.text = "UNIVERSITÉ CÔTE D'AZUR  ·  IUT DE NICE  ·  BUT INFORMATIQUE  ·  SAÉ 302"
        r_k.font.size = Pt(9.5)
        r_k.font.bold = True
        r_k.font.color.rgb = MARINE
        r_k.font.name = "Calibri"

        zone_titre = slide.shapes.add_textbox(Inches(0.85), Inches(0.58), Inches(11.6), Inches(0.9))
        cadre = zone_titre.text_frame
        cadre.word_wrap = True
        run = cadre.paragraphs[0].add_run()
        run.text = nettoyer(str(diapositive["titre"]))
        run.font.size = Pt(30 if numero > 1 else 36)
        run.font.bold = True
        run.font.color.rgb = MARINE
        run.font.name = "Georgia"

        _rect(slide, Inches(0.85), Inches(1.42), Inches(11.6), Inches(0.02), MARINE)

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

        puces = list(diapositive["puces"] or [])
        image = diapositive.get("image")
        row_images = [str(x) for x in (diapositive.get("row_images") or [])]
        layout = str(diapositive.get("layout") or "")
        top_corps = 2.15 if sous_titre else 1.78
        image_left: int | None = None

        if layout == "grid-2x2":
            _dessiner_grid_2x2(slide, puces, row_images, top_corps)
        elif layout == "bento-sec":
            _dessiner_bento_sec(slide, puces, top_corps)
        elif layout == "kpi-4":
            _dessiner_kpi_4(slide, puces, top_corps)
        elif layout == "pillars-4":
            _dessiner_pillars(slide, puces, top_corps, 4)
        elif layout == "pillars-3":
            _dessiner_pillars(slide, puces, top_corps, 3)
        elif layout == "compare-2":
            _dessiner_compare_2(slide, puces, top_corps)
        elif layout == "steps":
            _dessiner_steps(slide, puces, top_corps)
        elif layout == "image-left" and puces:
            zone = slide.shapes.add_textbox(Inches(6.05), Inches(top_corps), Inches(6.40), Inches(4.70))
            cadre = zone.text_frame
            cadre.word_wrap = True
            taille = 19 if len(puces) <= 4 else 16.5
            for index, (niveau, texte) in enumerate(puces):
                paragraphe = cadre.paragraphs[0] if index == 0 else cadre.add_paragraph()
                paragraphe.level = min(int(niveau), 4)
                paragraphe.space_after = Pt(14 if niveau == 0 else 6)
                run = paragraphe.add_run()
                run.text = ("▪  " if niveau == 0 else "–  ") + nettoyer(texte)
                run.font.size = Pt(taille - 1.5 * int(niveau))
                run.font.color.rgb = ENCRE if niveau == 0 else DOUX
                run.font.name = "Calibri"
        elif puces and row_images:
            max_len = max((len(nettoyer(t)) for _, t in puces), default=0)
            taille = 18.5 if max_len > 80 else (20 if len(puces) <= 4 else 17.5)
            n_lignes = len(puces)
            haut_dispo = (6.65 - top_corps) if not diapositive.get("message") else (5.85 - top_corps)
            pas = haut_dispo / max(n_lignes, 1)
            centre_x_img = Inches(10.80)
            for index, (niveau, texte) in enumerate(puces):
                centre_y = Inches(top_corps + pas * (index + 0.5))
                zone = slide.shapes.add_textbox(
                    Inches(0.85),
                    int(centre_y - Inches(0.48)),
                    Inches(8.70),
                    Inches(0.96),
                )
                cadre = zone.text_frame
                cadre.word_wrap = True
                cadre.vertical_anchor = MSO_ANCHOR.MIDDLE
                run = cadre.paragraphs[0].add_run()
                run.text = ("▪  " if niveau == 0 else "–  ") + nettoyer(texte)
                run.font.size = Pt(taille)
                run.font.color.rgb = ENCRE if niveau == 0 else DOUX
                run.font.name = "Calibri"

                if index < len(row_images):
                    cible_row = (RACINE / row_images[index]).resolve()
                    if cible_row.is_file():
                        h_cible = Inches(0.68 if cible_row.stem == "php" else 0.86)
                        pic_tmp = slide.shapes.add_picture(str(cible_row), 0, 0, height=h_cible)
                        w_r, h_r = int(pic_tmp.width), int(pic_tmp.height)
                        pic_tmp._element.getparent().remove(pic_tmp._element)
                        slide.shapes.add_picture(
                            str(cible_row),
                            int(centre_x_img - w_r / 2),
                            int(centre_y - h_r / 2),
                            width=w_r,
                            height=h_r,
                        )
        elif puces:
            largeur_texte = Inches(6.5) if image else Inches(11.6)
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

        if image:
            cible = (RACINE / str(image)).resolve()
            if cible.is_file():
                try:
                    top_img = Inches(2.1 if sous_titre else 1.75)
                    box_left = Inches(0.85 if layout == "image-left" else 7.65)
                    box_w = Inches(4.9)
                    pic = slide.shapes.add_picture(str(cible), box_left, top_img, width=box_w)
                    max_h = Inches(4.10 if diapositive.get("message") else 4.75)
                    if pic.height > max_h:
                        ratio = max_h / pic.height
                        pic.height = int(max_h)
                        pic.width = int(pic.width * ratio)
                        pic.left = int(box_left + (box_w - pic.width) / 2)
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
                pic_tmp._element.getparent().remove(pic_tmp._element)
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
            encadre = _rect(slide, Inches(0.85), Inches(6.02), Inches(11.6), Inches(0.75), FOND_ENCADRE, FILET)
            _rect(slide, Inches(0.85), Inches(6.02), Inches(0.08), Inches(0.75), MARINE)

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

        _rect(slide, Inches(0.85), Inches(6.9), Inches(11.6), Inches(0.015), FILET)

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
