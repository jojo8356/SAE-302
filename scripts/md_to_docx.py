#!/usr/bin/env python3
"""MiniShop — convertit un document Markdown en DOCX académique (python-docx).

    python3 scripts/md_to_docx.py docs/02-document-tests-validation.md \
        --out dist/02-document-tests-validation.docx

Mise en page académique (« format très droit ») :
* cartouche institutionnel Université Côte d'Azur · SAÉ 302 ;
* corps en Georgia 10,5 pt justifié (interligne 1,2), titres en Cambria/Calibri
  bleu marine (#0F294A) soulignés et protégés contre les titres orphelins ;
* tableaux de style LaTeX booktabs (filets horizontaux uniquement) ;
* encadrés droits à bordure gauche marine ;
* en-tête courant avec numérotation dynamique PAGE / NUMPAGES et pied de page.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

try:
    from docx import Document
    from docx.enum.table import WD_TABLE_ALIGNMENT
    from docx.enum.text import WD_ALIGN_PARAGRAPH
    from docx.oxml import OxmlElement
    from docx.oxml.ns import qn
    from docx.shared import Cm, Pt, RGBColor
except ImportError:  # pragma: no cover
    print("✘ module Python « python-docx » absent : pip install python-docx", file=sys.stderr)
    raise SystemExit(2)

MARINE = RGBColor(0x0F, 0x29, 0x4A)
MARINE_DOUX = RGBColor(0x1E, 0x3A, 0x5F)
ENCRE = RGBColor(0x0F, 0x17, 0x2A)
ENCRE_DOUCE = RGBColor(0x47, 0x55, 0x69)
RACINE = Path(__file__).resolve().parent.parent
LARGEUR_UTILE = Cm(16)

MOTIFS = [
    ("code", re.compile(r"`([^`]+)`")),
    ("gras", re.compile(r"\*\*(.+?)\*\*")),
    ("ital", re.compile(r"(?<![\w*])\*([^*\n]+)\*(?![\w*])")),
]


def ombrer(element, couleur: str) -> None:
    proprietes = element.get_or_add_tcPr() if element.tag.endswith("tc") else element.get_or_add_pPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), couleur)
    proprietes.append(shd)


def bordure_paragraphe(paragraphe, cote: str, couleur: str, taille_eighths: int) -> None:
    pPr = paragraphe._p.get_or_add_pPr()
    pBdr = pPr.find(qn("w:pBdr"))
    if pBdr is None:
        pBdr = OxmlElement("w:pBdr")
        pPr.append(pBdr)
    el = OxmlElement(f"w:{cote}")
    el.set(qn("w:val"), "single")
    el.set(qn("w:sz"), str(taille_eighths))
    el.set(qn("w:space"), "4")
    el.set(qn("w:color"), couleur)
    pBdr.append(el)


def style_tableau_booktabs(tableau) -> None:
    tblPr = tableau._tbl.tblPr
    tblBorders = OxmlElement("w:tblBorders")
    for nom, val, sz, color in (
        ("top", "single", "12", "0F294A"),
        ("bottom", "single", "12", "0F294A"),
        ("insideH", "single", "4", "E2E8F0"),
        ("left", "none", "0", "auto"),
        ("right", "none", "0", "auto"),
        ("insideV", "none", "0", "auto"),
    ):
        b = OxmlElement(f"w:{nom}")
        b.set(qn("w:val"), val)
        if val != "none":
            b.set(qn("w:sz"), sz)
            b.set(qn("w:space"), "0")
            b.set(qn("w:color"), color)
        tblBorders.append(b)
    tblPr.append(tblBorders)


def bordure_ligne_entete(ligne) -> None:
    for cellule in ligne.cells:
        tcPr = cellule._tc.get_or_add_tcPr()
        tcBorders = OxmlElement("w:tcBorders")
        bottom = OxmlElement("w:bottom")
        bottom.set(qn("w:val"), "single")
        bottom.set(qn("w:sz"), "8")
        bottom.set(qn("w:space"), "0")
        bottom.set(qn("w:color"), "0F294A")
        tcBorders.append(bottom)
        tcPr.append(tcBorders)


def ajouter_champ(paragraphe, nom_champ: str) -> None:
    run = paragraphe.add_run()
    run.font.name = "Calibri"
    run.font.size = Pt(8.5)
    run.font.color.rgb = ENCRE_DOUCE
    fldChar1 = OxmlElement("w:fldChar")
    fldChar1.set(qn("w:fldCharType"), "begin")
    instrText = OxmlElement("w:instrText")
    instrText.set(qn("xml:space"), "preserve")
    instrText.text = f" {nom_champ} "
    fldChar2 = OxmlElement("w:fldChar")
    fldChar2.set(qn("w:fldCharType"), "separate")
    fldChar3 = OxmlElement("w:fldChar")
    fldChar3.set(qn("w:fldCharType"), "end")
    run._r.append(fldChar1)
    run._r.append(instrText)
    run._r.append(fldChar2)
    run._r.append(fldChar3)


def segments(texte: str) -> list[tuple[str, str]]:
    sortie: list[tuple[str, str]] = []
    reste = texte
    while reste:
        meilleur = None
        for genre, motif in MOTIFS:
            trouve = motif.search(reste)
            if trouve and (meilleur is None or trouve.start() < meilleur[0].start()):
                meilleur = (trouve, genre)
        if meilleur is None:
            sortie.append(("texte", reste))
            break
        trouve, genre = meilleur
        if trouve.start() > 0:
            sortie.append(("texte", reste[: trouve.start()]))
        sortie.append((genre, trouve.group(1)))
        reste = reste[trouve.end():]
    return sortie


def ecrire_riche(paragraphe, texte: str, taille: float = 10.5, couleur: RGBColor = ENCRE,
                 police: str = "Georgia") -> None:
    for genre, contenu in segments(texte):
        run = paragraphe.add_run(contenu)
        run.font.name = police
        run.font.size = Pt(taille)
        run.font.color.rgb = couleur
        if genre == "code":
            run.font.name = "Consolas"
            run.font.size = Pt(taille - 1)
            run.font.color.rgb = MARINE_DOUX
        elif genre == "gras":
            run.bold = True
        elif genre == "ital":
            run.italic = True


def styler_document(document: Document, titre_courant: str, ref_source: str) -> None:
    section = document.sections[0]
    section.page_width = Cm(21)
    section.page_height = Cm(29.7)
    section.top_margin = section.bottom_margin = Cm(2.0)
    section.left_margin = section.right_margin = Cm(2.5)
    section.different_first_page_header_footer = True

    normal = document.styles["Normal"]
    normal.font.name = "Georgia"
    normal.font.size = Pt(10.5)
    normal.font.color.rgb = ENCRE
    normal.paragraph_format.space_after = Pt(5)
    normal.paragraph_format.line_spacing = 1.2

    for niveau, taille in ((1, 18), (2, 13.5), (3, 11.5), (4, 10.5)):
        style = document.styles[f"Heading {niveau}"]
        style.font.name = "Calibri"
        style.font.size = Pt(taille)
        style.font.bold = True
        style.font.color.rgb = MARINE if niveau <= 2 else MARINE_DOUX
        style.paragraph_format.space_before = Pt(12 if niveau <= 2 else 8)
        style.paragraph_format.space_after = Pt(5)
        style.paragraph_format.keep_with_next = True

    # Cartouche institutionnel en tête de page 1
    p_inst = document.add_paragraph()
    p_inst.paragraph_format.space_after = Pt(2)
    r1 = p_inst.add_run("UNIVERSITÉ CÔTE D'AZUR  ·  IUT DE NICE  ·  BUT INFORMATIQUE (SAÉ 302)")
    r1.font.name = "Calibri"
    r1.font.size = Pt(8.5)
    r1.font.bold = True
    r1.font.color.rgb = MARINE

    p_meta = document.add_paragraph()
    p_meta.paragraph_format.space_after = Pt(10)
    r2 = p_meta.add_run(f"Projet MiniShop · Johan Polsinelli & Chahd Gouiaa · Source : {ref_source}")
    r2.font.name = "Calibri"
    r2.font.size = Pt(8.5)
    r2.font.color.rgb = ENCRE_DOUCE
    bordure_paragraphe(p_meta, "bottom", "0F294A", 12)

    # En-tête des pages suivantes
    entete = section.header.paragraphs[0]
    entete.paragraph_format.space_after = Pt(4)
    r_hdr = entete.add_run(f"{titre_courant[:75]}    |    Page ")
    r_hdr.font.name = "Calibri"
    r_hdr.font.size = Pt(8.5)
    r_hdr.font.color.rgb = ENCRE_DOUCE
    ajouter_champ(entete, "PAGE")
    r_sep = entete.add_run(" / ")
    r_sep.font.name = "Calibri"
    r_sep.font.size = Pt(8.5)
    r_sep.font.color.rgb = ENCRE_DOUCE
    ajouter_champ(entete, "NUMPAGES")
    bordure_paragraphe(entete, "bottom", "CBD5E1", 4)

    # Pied de page (page 1 et suivantes)
    for footer in (section.first_page_footer, section.footer):
        pied = footer.paragraphs[0]
        pied.alignment = WD_ALIGN_PARAGRAPH.CENTER
        r_f = pied.add_run("Université Côte d'Azur · BUT Informatique · SAÉ 302 — MiniShop")
        r_f.font.name = "Calibri"
        r_f.font.size = Pt(8)
        r_f.font.color.rgb = ENCRE_DOUCE
        bordure_paragraphe(pied, "top", "CBD5E1", 4)


def ajouter_code(document: Document, lignes: list[str]) -> None:
    if not lignes:
        return
    paragraphe = document.add_paragraph()
    paragraphe.paragraph_format.space_before = Pt(4)
    paragraphe.paragraph_format.space_after = Pt(8)
    paragraphe.paragraph_format.left_indent = Cm(0.35)
    paragraphe.paragraph_format.right_indent = Cm(0.2)
    ombrer(paragraphe._p, "F8FAFC")
    bordure_paragraphe(paragraphe, "left", "0F294A", 14)
    for numero, ligne in enumerate(lignes):
        run = paragraphe.add_run(ligne)
        run.font.name = "Consolas"
        run.font.size = Pt(8.5)
        run.font.color.rgb = ENCRE
        if numero < len(lignes) - 1:
            run.add_break()


def ajouter_citation(document: Document, lignes: list[str]) -> None:
    if not lignes:
        return
    paragraphe = document.add_paragraph()
    paragraphe.paragraph_format.left_indent = Cm(0.4)
    paragraphe.paragraph_format.right_indent = Cm(0.3)
    paragraphe.paragraph_format.space_before = Pt(4)
    paragraphe.paragraph_format.space_after = Pt(6)
    ombrer(paragraphe._p, "F8FAFC")
    bordure_paragraphe(paragraphe, "left", "0F294A", 14)
    for idx, ligne in enumerate(lignes):
        ecrire_riche(paragraphe, ligne, taille=9.8, couleur=ENCRE, police="Georgia")
        if idx < len(lignes) - 1:
            paragraphe.add_run().add_break()


def ajouter_tableau(document: Document, lignes: list[list[str]]) -> None:
    if len(lignes) < 2:
        return
    entetes = [nettoyer_cellule(c) for c in lignes[0]]
    corps = [[nettoyer_cellule(c) for c in ligne] for ligne in lignes[2:]]
    colonnes = len(entetes)
    tableau = document.add_table(rows=1, cols=colonnes)
    tableau.alignment = WD_TABLE_ALIGNMENT.CENTER
    style_tableau_booktabs(tableau)

    entete = tableau.rows[0]
    for index, texte in enumerate(entetes):
        cellule = entete.cells[index]
        cellule.text = ""
        paragraphe = cellule.paragraphs[0]
        ecrire_riche(paragraphe, texte, taille=9, couleur=MARINE, police="Calibri")
        for run in paragraphe.runs:
            run.bold = True
        ombrer(cellule._tc, "EEF2F6")
    bordure_ligne_entete(entete)

    for index, ligne in enumerate(corps):
        valeurs = (ligne + [""] * colonnes)[:colonnes]
        cellule_ligne = tableau.add_row()
        for position, texte in enumerate(valeurs):
            cellule = cellule_ligne.cells[position]
            cellule.text = ""
            ecrire_riche(cellule.paragraphs[0], texte, taille=9, couleur=ENCRE, police="Georgia")
            if index % 2:
                ombrer(cellule._tc, "F8FAFC")
    document.add_paragraph()


def nettoyer_cellule(texte: str) -> str:
    texte = re.sub(r"\*\*(.+?)\*\*", r"\1", texte)
    texte = re.sub(r"`([^`]+)`", r"\1", texte)
    return re.sub(r"(?<![\w*])\*([^*\n]+)\*(?![\w*])", r"\1", texte).strip()


def ajouter_image(document: Document, source: Path, chemin: str, legende: str) -> None:
    cible = (source.parent / chemin).resolve()
    if not cible.is_file():
        cible = (RACINE / chemin).resolve()
    if not cible.is_file():
        document.add_paragraph(f"[figure absente : {chemin}]")
        return
    paragraphe = document.add_paragraph()
    paragraphe.alignment = WD_ALIGN_PARAGRAPH.CENTER
    try:
        paragraphe.add_run().add_picture(str(cible), width=LARGEUR_UTILE)
    except Exception as erreur:
        print(f"  ⚠ image ignorée ({cible.name}) : {erreur}", file=sys.stderr)
        return
    if legende:
        texte = document.add_paragraph()
        texte.alignment = WD_ALIGN_PARAGRAPH.CENTER
        run = texte.add_run(f"Figure — {legende}")
        run.font.name = "Calibri"
        run.italic = True
        run.font.size = Pt(9)
        run.font.color.rgb = ENCRE_DOUCE


def convertir(source: Path, sortie: Path) -> tuple[int, int]:
    markdown = source.read_text(encoding="utf-8")
    markdown = markdown.replace("- [x] ", "- [OK] ").replace("- [ ] ", "- [ ] ").replace("- [~] ", "- [~] ")
    premier_titre = re.search(r"^#\s+(.+)$", markdown, flags=re.M)
    titre_doc = premier_titre.group(1).strip() if premier_titre else source.stem
    rel_source = str(source.relative_to(RACINE)) if source.is_relative_to(RACINE) else source.name

    document = Document()
    styler_document(document, titre_doc, rel_source)

    lignes = markdown.splitlines()
    index = 0
    dans_code = False
    code: list[str] = []
    table: list[list[str]] = []
    citation: list[str] = []
    images = 0
    paragraphes = 0

    def vider_table() -> None:
        nonlocal table, paragraphes
        if table:
            ajouter_tableau(document, table)
            paragraphes += 1
            table = []

    def vider_citation() -> None:
        nonlocal citation, paragraphes
        if citation:
            ajouter_citation(document, citation)
            paragraphes += 1
            citation = []

    while index < len(lignes):
        brute = lignes[index].rstrip()

        if brute.startswith("```"):
            vider_table(); vider_citation()
            if dans_code:
                ajouter_code(document, code)
                paragraphes += 1
                code = []
                dans_code = False
            else:
                dans_code = True
            index += 1
            continue
        if dans_code:
            code.append(brute)
            index += 1
            continue

        if brute.startswith("|") and brute.rstrip().endswith("|"):
            vider_citation()
            table.append([c for c in brute.strip().strip("|").split("|")])
            index += 1
            continue
        vider_table()

        if brute.startswith(">"):
            citation.append(brute.lstrip("> ").strip())
            index += 1
            continue
        vider_citation()

        trouve = re.match(r"^(#{1,4})\s+(.*)$", brute)
        if trouve:
            niveau = len(trouve.group(1))
            h = document.add_heading(trouve.group(2).strip(), level=niveau)
            if niveau <= 2:
                bordure_paragraphe(h, "bottom", "0F294A" if niveau == 1 else "CBD5E1", 8 if niveau == 1 else 4)
            paragraphes += 1
            index += 1
            continue

        trouve_image = re.match(r'^\s*!\[([^\]]*)\]\(([^)\s]+)[^)]*\)\s*$', brute)
        if trouve_image:
            ajouter_image(document, source, trouve_image.group(2), trouve_image.group(1))
            images += 1
            index += 1
            continue

        trouve = re.match(r"^(\s*)[-*+]\s+(.*)$", brute)
        if trouve:
            style = "List Bullet 2" if len(trouve.group(1)) >= 2 else "List Bullet"
            paragraphe = document.add_paragraph(style=style)
            ecrire_riche(paragraphe, trouve.group(2))
            paragraphes += 1
            index += 1
            continue

        trouve = re.match(r"^\s*\d+[.)]\s+(.*)$", brute)
        if trouve:
            paragraphe = document.add_paragraph(style="List Number")
            ecrire_riche(paragraphe, trouve.group(1))
            paragraphes += 1
            index += 1
            continue

        if re.match(r"^\s*(---|\*\*\*)\s*$", brute):
            sep = document.add_paragraph()
            bordure_paragraphe(sep, "bottom", "CBD5E1", 4)
            index += 1
            continue

        if not brute.strip():
            index += 1
            continue

        paragraphe_lignes = [brute.strip()]
        suivant = index + 1
        while suivant < len(lignes):
            candidat = lignes[suivant].rstrip()
            if (not candidat.strip() or candidat.startswith(("```", "|", ">", "#"))
                    or re.match(r"^\s*[-*+]\s+", candidat) or re.match(r"^\s*\d+[.)]\s+", candidat)
                    or re.match(r"^\s*(---|\*\*\*)\s*$", candidat)
                    or re.match(r'^\s*!\[', candidat)):
                break
            paragraphe_lignes.append(candidat.strip())
            suivant += 1
        paragraphe = document.add_paragraph()
        paragraphe.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
        ecrire_riche(paragraphe, " ".join(paragraphe_lignes))
        paragraphes += 1
        index = suivant

    vider_table()
    vider_citation()
    if code:
        ajouter_code(document, code)

    sortie.parent.mkdir(parents=True, exist_ok=True)
    document.save(str(sortie))
    return paragraphes, images


def main(argv: list[str] | None = None) -> int:
    analyseur = argparse.ArgumentParser(description="Markdown → DOCX académique (SAÉ 302 — MiniShop).")
    analyseur.add_argument("source", type=Path)
    analyseur.add_argument("--out", type=Path)
    arguments = analyseur.parse_args(argv)

    source = arguments.source if arguments.source.is_absolute() else Path.cwd() / arguments.source
    if not source.is_file():
        print(f"✘ source introuvable : {source}", file=sys.stderr)
        return 2
    sortie = arguments.out or source.with_suffix(".docx")
    if not sortie.is_absolute():
        sortie = Path.cwd() / sortie

    paragraphes, images = convertir(source, sortie)
    print(f"✓ {sortie}  ({paragraphes} blocs, {images} image(s), {sortie.stat().st_size // 1024} Ko)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
