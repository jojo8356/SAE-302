#!/usr/bin/env python3
"""MiniShop — convertit un document Markdown en DOCX (python-docx).

    python3 scripts/md_to_docx.py docs/02-document-tests-validation.md \
        --out dist/02-document-tests-validation.docx

Le rendu respecte la charte des documents SAÉ : titres hiérarchisés en bleu
`#1D4ED8`, corps en 11 pt interligne 1,15, tableaux à en-tête bleu, blocs de
code sur fond gris avec police à chasse fixe, images dimensionnées à la
largeur utile (16 cm) et pied de page « SAÉ 302 — MiniShop ».

Aucune dépendance à Word/LibreOffice : python-docx écrit l'OOXML directement.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

try:
    from docx import Document
    from docx.enum.section import WD_SECTION
    from docx.enum.table import WD_TABLE_ALIGNMENT
    from docx.enum.text import WD_ALIGN_PARAGRAPH
    from docx.oxml import OxmlElement
    from docx.oxml.ns import qn
    from docx.shared import Cm, Pt, RGBColor
except ImportError:  # pragma: no cover
    print("✘ module Python « python-docx » absent : pip install python-docx", file=sys.stderr)
    raise SystemExit(2)

ACCENT = RGBColor(0x1D, 0x4E, 0xD8)
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
    """Applique un fond (w:shd) à un paragraphe ou une cellule."""
    proprietes = element.get_or_add_tcPr() if element.tag.endswith("tc") else element.get_or_add_pPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), couleur)
    proprietes.append(shd)


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


def ecrire_riche(paragraphe, texte: str, taille: float = 11, couleur: RGBColor = ENCRE) -> None:
    for genre, contenu in segments(texte):
        run = paragraphe.add_run(contenu)
        run.font.size = Pt(taille)
        run.font.color.rgb = couleur
        if genre == "code":
            run.font.name = "Consolas"
            run.font.size = Pt(taille - 1)
            run.font.color.rgb = RGBColor(0x1E, 0x3A, 0x8A)
        elif genre == "gras":
            run.bold = True
        elif genre == "ital":
            run.italic = True


def styler_document(document: Document) -> None:
    section = document.sections[0]
    section.page_width = Cm(21)
    section.page_height = Cm(29.7)
    section.top_margin = section.bottom_margin = Cm(2)
    section.left_margin = section.right_margin = Cm(2.5)

    normal = document.styles["Normal"]
    normal.font.name = "Calibri"
    normal.font.size = Pt(11)
    normal.paragraph_format.space_after = Pt(6)
    normal.paragraph_format.line_spacing = 1.15

    for niveau, taille in ((1, 22), (2, 16), (3, 13), (4, 11.5)):
        style = document.styles[f"Heading {niveau}"]
        style.font.name = "Calibri"
        style.font.size = Pt(taille)
        style.font.bold = True
        style.font.color.rgb = ACCENT if niveau <= 2 else ENCRE
        style.paragraph_format.space_before = Pt(14 if niveau == 1 else 10)
        style.paragraph_format.space_after = Pt(6)
        style.paragraph_format.keep_with_next = True

    # pied de page
    pied = section.footer.paragraphs[0]
    pied.text = "SAÉ 302 — MiniShop · document généré par scripts/md_to_docx.py"
    pied.alignment = WD_ALIGN_PARAGRAPH.CENTER
    for run in pied.runs:
        run.font.size = Pt(8)
        run.font.color.rgb = ENCRE_DOUCE


def ajouter_code(document: Document, lignes: list[str]) -> None:
    if not lignes:
        return
    paragraphe = document.add_paragraph()
    paragraphe.paragraph_format.space_before = Pt(4)
    paragraphe.paragraph_format.space_after = Pt(8)
    paragraphe.paragraph_format.left_indent = Cm(0.3)
    ombrer(paragraphe._p, "F1F5F9")
    for numero, ligne in enumerate(lignes):
        run = paragraphe.add_run(ligne)
        run.font.name = "Consolas"
        run.font.size = Pt(8.5)
        if numero < len(lignes) - 1:
            run.add_break()


def ajouter_tableau(document: Document, lignes: list[list[str]]) -> None:
    if len(lignes) < 2:
        return
    entetes = [nettoyer_cellule(c) for c in lignes[0]]
    corps = [[nettoyer_cellule(c) for c in ligne] for ligne in lignes[2:]]
    colonnes = len(entetes)
    tableau = document.add_table(rows=1, cols=colonnes)
    tableau.style = "Table Grid"
    tableau.alignment = WD_TABLE_ALIGNMENT.CENTER

    entete = tableau.rows[0]
    for index, texte in enumerate(entetes):
        cellule = entete.cells[index]
        cellule.text = ""
        paragraphe = cellule.paragraphs[0]
        ecrire_riche(paragraphe, texte, taille=9, couleur=RGBColor(0xFF, 0xFF, 0xFF))
        for run in paragraphe.runs:
            run.bold = True
        ombrer(cellule._tc, "1D4ED8")

    for index, ligne in enumerate(corps):
        valeurs = (ligne + [""] * colonnes)[:colonnes]
        cellule_ligne = tableau.add_row()
        for position, texte in enumerate(valeurs):
            cellule = cellule_ligne.cells[position]
            cellule.text = ""
            ecrire_riche(cellule.paragraphs[0], texte, taille=9)
            if index % 2:
                ombrer(cellule._tc, "F1F5F9")
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
        document.add_paragraph(f"[image absente : {chemin}]")
        return
    paragraphe = document.add_paragraph()
    paragraphe.alignment = WD_ALIGN_PARAGRAPH.CENTER
    try:
        paragraphe.add_run().add_picture(str(cible), width=LARGEUR_UTILE)
    except Exception as erreur:  # format exotique : on n'interrompt pas
        print(f"  ⚠ image ignorée ({cible.name}) : {erreur}", file=sys.stderr)
        return
    if legende:
        texte = document.add_paragraph()
        texte.alignment = WD_ALIGN_PARAGRAPH.CENTER
        run = texte.add_run(legende)
        run.italic = True
        run.font.size = Pt(9)
        run.font.color.rgb = ENCRE_DOUCE


def convertir(source: Path, sortie: Path) -> tuple[int, int]:
    markdown = source.read_text(encoding="utf-8")
    markdown = markdown.replace("- [x] ", "- ✔ ").replace("- [ ] ", "- ☐ ").replace("- [~] ", "- ◐ ")

    document = Document()
    styler_document(document)

    lignes = markdown.splitlines()
    index = 0
    dans_code = False
    code: list[str] = []
    table: list[list[str]] = []
    citation: list[str] = []
    numero = 0
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
            for ligne in citation:
                paragraphe = document.add_paragraph(style="Intense Quote")
                ecrire_riche(paragraphe, ligne, taille=10.5)
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
            document.add_heading(trouve.group(2).strip(), level=niveau)
            paragraphes += 1
            index += 1
            numero = 0
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
            numero = 0
            continue

        trouve = re.match(r"^\s*\d+[.)]\s+(.*)$", brute)
        if trouve:
            numero += 1
            paragraphe = document.add_paragraph(style="List Number")
            ecrire_riche(paragraphe, trouve.group(1))
            paragraphes += 1
            index += 1
            continue

        if re.match(r"^\s*(---|\*\*\*)\s*$", brute):
            document.add_paragraph("―" * 30).alignment = WD_ALIGN_PARAGRAPH.CENTER
            index += 1
            continue

        if not brute.strip():
            index += 1
            numero = 0
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
        numero = 0

    vider_table()
    vider_citation()
    if code:
        ajouter_code(document, code)

    sortie.parent.mkdir(parents=True, exist_ok=True)
    document.save(str(sortie))
    return paragraphes, images


def main(argv: list[str] | None = None) -> int:
    analyseur = argparse.ArgumentParser(description="Markdown → DOCX (SAÉ 302 — MiniShop).")
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
