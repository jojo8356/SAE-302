#!/usr/bin/env python3
"""MiniShop — convertit un document Markdown en PDF (fpdf2).

    python3 scripts/md_to_pdf.py docs/01-cahier-des-charges-MiniShop.md \
        --out dist/pdf/01-cahier-des-charges-MiniShop.pdf

Markdown pris en charge : titres (1→4), paragraphes, gras/italique/code en
ligne, listes à puces et numérotées (un niveau d'imbrication), blocs de code
clos, tableaux à tuyaux, citations, règles horizontales et **images PNG/JPEG**
(redimensionnées à la largeur utile, jamais coupées).

Le rendu est volontairement sobre : A4, marges de 18 mm, en-tête avec le
titre du document et pagination, pied de page avec la mention SAÉ 302.
Les caractères que DejaVu ne sait pas dessiner (emoji de suivi de tâches)
sont traduits en équivalents ASCII lisibles.
"""

from __future__ import annotations

import argparse
import os
import re
import sys
import textwrap
from pathlib import Path

try:
    from fpdf import FPDF
    from fpdf.enums import XPos, YPos
except ImportError:  # pragma: no cover
    print("✘ module Python « fpdf2 » absent : pip install fpdf2", file=sys.stderr)
    raise SystemExit(2)

RACINE = Path(__file__).resolve().parent.parent

ACCENT = (29, 78, 216)        # #1D4ED8
ENCRE = (15, 23, 42)          # #0F172A
ENCRE_DOUCE = (71, 85, 105)   # #475569
TRAIT = (203, 213, 225)       # #CBD5E1
FOND = (241, 245, 249)        # #F1F5F9

SUBSTITUTIONS = {
    "✅": "[x]", "☐": "[ ]", "◐": "[~]", "⚠️": "!", "⚠": "!", "✘": "x",
    "✓": "v", "→": "->", "←": "<-", "↔": "<->", "≤": "<=", "≥": ">=",
    "≠": "!=", "…": "...", "€": "EUR", "—": "-", "–": "-", "’": "'",
    "“": '"', "”": '"', "•": "-", "·": "-", " ": " ", " ": " ",
}


def nettoyer(texte: str) -> str:
    """Remplace les caractères hors DejaVu et normalise les espaces."""
    for source, cible in SUBSTITUTIONS.items():
        texte = texte.replace(source, cible)
    # emoji et pictogrammes résiduels
    return re.sub(r"[\U0001F000-\U0001FAFF\u2600-\u27BF\uFE0F]", "", texte)


def trouver_polices() -> str:
    for candidat in ("/usr/share/fonts/truetype/dejavu", "/usr/share/fonts/dejavu",
                     "/usr/local/share/fonts/dejavu", os.path.expanduser("~/.fonts")):
        if os.path.isdir(candidat) and os.path.isfile(os.path.join(candidat, "DejaVuSans.ttf")):
            return candidat
    print("✘ polices DejaVu introuvables : installez fonts-dejavu-core", file=sys.stderr)
    raise SystemExit(2)


class Document(FPDF):
    """PDF A4 avec en-tête (titre courant) et pied de page normalisés."""

    def __init__(self, titre_courant: str) -> None:
        super().__init__(orientation="P", unit="mm", format="A4")
        self.titre_courant = nettoyer(titre_courant)
        self.set_auto_page_break(auto=True, margin=18)
        self.set_margins(18, 18, 18)
        dossier = trouver_polices()
        self.add_font("Doc", "", os.path.join(dossier, "DejaVuSans.ttf"))
        self.add_font("Doc", "B", os.path.join(dossier, "DejaVuSans-Bold.ttf"))
        self.add_font("Doc", "I", os.path.join(dossier, "DejaVuSans.ttf"))
        self.add_font("Mono", "", os.path.join(dossier, "DejaVuSansMono.ttf"))
        self.add_font("Mono", "B", os.path.join(dossier, "DejaVuSansMono-Bold.ttf"))
        self.set_font("Doc", "", 10)
        self.set_text_color(*ENCRE)

    def header(self) -> None:
        if self.page_no() == 1:
            return
        self.set_font("Doc", "", 8)
        self.set_text_color(*ENCRE_DOUCE)
        self.cell(0, 6, self.titre_courant[:90], align="L")
        self.cell(0, 6, f"page {self.page_no()}", align="R", new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        self.set_draw_color(*TRAIT)
        self.line(self.l_margin, self.get_y(), self.w - self.r_margin, self.get_y())
        self.ln(4)
        self.set_text_color(*ENCRE)

    def footer(self) -> None:
        self.set_y(-14)
        self.set_font("Doc", "", 7.5)
        self.set_text_color(*ENCRE_DOUCE)
        self.cell(0, 5, "SAÉ 302 — MiniShop · document généré par scripts/md_to_pdf.py", align="C")


# --------------------------------------------------------------------------- #
#  Rendu en ligne (gras / italique / code)
# --------------------------------------------------------------------------- #
MOTIFS = [
    ("code", re.compile(r"`([^`]+)`")),
    ("gras", re.compile(r"\*\*(.+?)\*\*")),
    ("ital", re.compile(r"(?<![\w*])\*([^*\n]+)\*(?![\w*])")),
]


def segments(texte: str) -> list[tuple[str, str]]:
    """Découpe une ligne en segments (type, contenu)."""
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


class Redacteur:
    def __init__(self, pdf: Document, source: Path) -> None:
        self.pdf = pdf
        self.source = source

    # -- helpers ------------------------------------------------------------
    def _police(self, genre: str, taille: float) -> None:
        if genre == "code":
            self.pdf.set_font("Mono", "", max(taille - 1, 6))
            self.pdf.set_text_color(30, 58, 138)
        elif genre == "gras":
            self.pdf.set_font("Doc", "B", taille)
            self.pdf.set_text_color(*ENCRE)
        elif genre == "ital":
            self.pdf.set_font("Doc", "I", taille)
            self.pdf.set_text_color(*ENCRE)
        else:
            self.pdf.set_font("Doc", "", taille)
            self.pdf.set_text_color(*ENCRE)

    def ligne_riche(self, texte: str, taille: float, hauteur_ligne: float) -> None:
        for genre, contenu in segments(nettoyer(texte)):
            self._police(genre, taille)
            self.pdf.write(hauteur_ligne, contenu)

    # -- blocs --------------------------------------------------------------
    def titre(self, texte: str, niveau: int) -> None:
        tailles = {1: 19, 2: 14, 3: 11.5, 4: 10.5}
        taille = tailles.get(niveau, 10)
        self.pdf.ln(4 if niveau > 1 else 0)
        self.pdf.set_font("Doc", "B", taille)
        self.pdf.set_text_color(*(ACCENT if niveau <= 2 else ENCRE))
        self.pdf.multi_cell(0, taille * 0.55, nettoyer(texte), new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        if niveau <= 2:
            self.pdf.set_draw_color(*ACCENT)
            y = self.pdf.get_y() + 0.6
            self.pdf.line(self.pdf.l_margin, y, self.pdf.w - self.pdf.r_margin, y)
        self.pdf.ln(2.4)
        self.pdf.set_text_color(*ENCRE)

    def paragraphe(self, texte: str, retrait: float = 0) -> None:
        self.pdf.set_x(self.pdf.l_margin + retrait)
        self.ligne_riche(texte, 10, 5.6)
        self.pdf.ln(7.4)

    def puce(self, texte: str, niveau: int = 0) -> None:
        retrait = 4 + niveau * 5
        self.pdf.set_x(self.pdf.l_margin + retrait)
        self.pdf.set_font("Doc", "", 10)
        self.pdf.set_text_color(*ENCRE)
        self.pdf.write(5.6, "• " if niveau == 0 else "– ")
        self.ligne_riche(texte, 10, 5.6)
        self.pdf.ln(6.6)

    def numerotee(self, numero: int, texte: str) -> None:
        self.pdf.set_x(self.pdf.l_margin + 5)
        self.pdf.set_font("Doc", "", 10)
        self.pdf.write(5.6, f"{numero}. ")
        self.ligne_riche(texte, 10, 5.6)
        self.pdf.ln(6.6)

    def code(self, lignes: list[str]) -> None:
        if not lignes:
            return
        self.pdf.ln(1)
        self.pdf.set_font("Mono", "", 8)
        largeur = self.pdf.w - self.pdf.l_margin - self.pdf.r_margin
        hauteur = 4.4
        for brute in lignes:
            morceaux = textwrap.wrap(nettoyer(brute).replace("\t", "    "), width=98,
                                     replace_whitespace=False, drop_whitespace=False) or [""]
            for morceau in morceaux:
                if self.pdf.get_y() + hauteur > self.pdf.h - self.pdf.b_margin - 2:
                    self.pdf.add_page()
                self.pdf.set_fill_color(*FOND)
                self.pdf.set_text_color(30, 41, 59)
                self.pdf.cell(largeur, hauteur, " " + morceau, fill=True, new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        self.pdf.ln(3)
        self.pdf.set_font("Doc", "", 10)
        self.pdf.set_text_color(*ENCRE)

    def citation(self, lignes: list[str]) -> None:
        if not lignes:
            return
        largeur = self.pdf.w - self.pdf.l_margin - self.pdf.r_margin
        for ligne in lignes:
            if self.pdf.get_y() + 6 > self.pdf.h - self.pdf.b_margin:
                self.pdf.add_page()
            y = self.pdf.get_y()
            self.pdf.set_fill_color(219, 234, 254)
            self.pdf.rect(self.pdf.l_margin, y, largeur, 6.4, style="F")
            self.pdf.set_fill_color(*ACCENT)
            self.pdf.rect(self.pdf.l_margin, y, 1.2, 6.4, style="F")
            self.pdf.set_xy(self.pdf.l_margin + 4, y + 1.1)
            self.ligne_riche(ligne, 9.5, 5.4)
            self.pdf.ln(7.2)
        self.pdf.ln(1)

    def tableau(self, lignes: list[list[str]]) -> None:
        if len(lignes) < 2:
            return
        entetes = [nettoyer(c).strip() for c in lignes[0]]
        corps = [[nettoyer(c).strip() for c in ligne] for ligne in lignes[2:]]
        colonnes = len(entetes)
        largeur = self.pdf.w - self.pdf.l_margin - self.pdf.r_margin
        # largeur proportionnelle au contenu (bornée pour rester lisible)
        poids = []
        for index in range(colonnes):
            longueur = max([len(entetes[index])] + [len(l[index]) if index < len(l) else 0 for l in corps])
            poids.append(max(6, min(longueur, 45)))
        total = sum(poids)
        largeurs = [largeur * p / total for p in poids]

        def cellules(valeurs: list[str], gras: bool, fond: tuple[int, int, int] | None) -> None:
            hauteurs = []
            for index, valeur in enumerate(valeurs):
                lignes_cellule = textwrap.wrap(valeur, width=max(8, int(largeurs[index] / 1.55))) or [""]
                hauteurs.append(lignes_cellule)
            hauteur_ligne = 4.1
            hauteur = max(len(h) for h in hauteurs) * hauteur_ligne + 1.8
            if self.pdf.get_y() + hauteur > self.pdf.h - self.pdf.b_margin:
                self.pdf.add_page()
            y = self.pdf.get_y()
            x = self.pdf.l_margin
            for index, contenu in enumerate(hauteurs):
                self.pdf.set_draw_color(*TRAIT)
                if fond:
                    self.pdf.set_fill_color(*fond)
                self.pdf.rect(x, y, largeurs[index], hauteur, style="DF" if fond else "D")
                self.pdf.set_xy(x + 1.3, y + 1)
                self.pdf.set_font("Doc", "B" if gras else "", 8)
                self.pdf.set_text_color(*(ENCRE if not gras else (255, 255, 255)))
                for numero, ligne in enumerate(contenu):
                    if numero:
                        self.pdf.set_xy(x + 1.3, y + 1 + numero * hauteur_ligne)
                    self.pdf.cell(largeurs[index] - 2, hauteur_ligne, ligne,
                                  new_x=XPos.RIGHT, new_y=YPos.TOP)
                x += largeurs[index]
            self.pdf.set_xy(self.pdf.l_margin, y + hauteur)
            self.pdf.set_text_color(*ENCRE)

        cellules(entetes, True, ACCENT)
        for index, ligne in enumerate(corps):
            valeurs = (ligne + [""] * colonnes)[:colonnes]
            cellules(valeurs, False, FOND if index % 2 else None)
        self.pdf.ln(3)

    def image(self, chemin: str, legende: str) -> None:
        cible = (self.source.parent / chemin).resolve()
        if not cible.is_file():
            cible = (RACINE / chemin).resolve()
        if not cible.is_file():
            self.pdf.set_font("Doc", "I", 8.5)
            self.pdf.set_text_color(*ENCRE_DOUCE)
            self.pdf.multi_cell(0, 4.6, f"[image absente : {chemin}]", new_x=XPos.LMARGIN, new_y=YPos.NEXT)
            return
        largeur = self.pdf.w - self.pdf.l_margin - self.pdf.r_margin
        try:
            infos = self.pdf.image(str(cible), w=min(largeur, 150), x=self.pdf.l_margin)
        except Exception as erreur:  # image exotique : on n'interrompt pas le rendu
            print(f"  ⚠ image ignorée ({cible.name}) : {erreur}", file=sys.stderr)
            return
        self.pdf.ln(2)
        if legende:
            self.pdf.set_font("Doc", "I", 8.5)
            self.pdf.set_text_color(*ENCRE_DOUCE)
            self.pdf.multi_cell(0, 4.4, nettoyer(legende), align="C",
                                new_x=XPos.LMARGIN, new_y=YPos.NEXT)
            self.pdf.set_text_color(*ENCRE)
        self.pdf.ln(3)
        del infos


def convertir(source: Path, sortie: Path, titre: str | None = None) -> int:
    markdown = source.read_text(encoding="utf-8")
    premier_titre = re.search(r"^#\s+(.+)$", markdown, flags=re.M)
    titre_document = titre or (premier_titre.group(1).strip() if premier_titre else source.stem)
    pdf = Document(titre_document)
    pdf.add_page()
    redacteur = Redacteur(pdf, source)

    lignes = markdown.splitlines()
    index = 0
    dans_code = False
    code: list[str] = []
    table: list[list[str]] = []
    citation: list[str] = []
    numero = 0

    def vider_table() -> None:
        nonlocal table
        if table:
            redacteur.tableau(table)
            table = []

    def vider_citation() -> None:
        nonlocal citation
        if citation:
            redacteur.citation(citation)
            citation = []

    while index < len(lignes):
        brute = lignes[index].rstrip()

        if brute.startswith("```"):
            vider_table(); vider_citation()
            if dans_code:
                redacteur.code(code)
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

        if re.match(r"^\s*(---|\*\*\*)\s*$", brute):
            pdf.ln(2)
            pdf.set_draw_color(*TRAIT)
            y = pdf.get_y()
            pdf.line(pdf.l_margin, y, pdf.w - pdf.r_margin, y)
            pdf.ln(4)
            index += 1
            continue

        trouve = re.match(r"^(#{1,4})\s+(.*)$", brute)
        if trouve:
            redacteur.titre(trouve.group(2), len(trouve.group(1)))
            index += 1
            numero = 0
            continue

        trouve_image = re.match(r'^\s*!\[([^\]]*)\]\(([^)\s]+)[^)]*\)\s*$', brute)
        if trouve_image:
            redacteur.image(trouve_image.group(2), trouve_image.group(1))
            index += 1
            continue

        trouve = re.match(r"^(\s*)[-*+]\s+(.*)$", brute)
        if trouve:
            redacteur.puce(trouve.group(2), 1 if len(trouve.group(1)) >= 2 else 0)
            index += 1
            numero = 0
            continue

        trouve = re.match(r"^\s*\d+[.)]\s+(.*)$", brute)
        if trouve:
            numero += 1
            redacteur.numerotee(numero, trouve.group(1))
            index += 1
            continue

        if not brute.strip():
            pdf.ln(1.5)
            index += 1
            numero = 0
            continue

        paragraphe = [brute.strip()]
        suivant = index + 1
        while suivant < len(lignes):
            candidat = lignes[suivant].rstrip()
            if (not candidat.strip() or candidat.startswith("#") or candidat.startswith("```")
                    or candidat.startswith("|") or candidat.startswith(">")
                    or re.match(r"^\s*[-*+]\s+", candidat) or re.match(r"^\s*\d+[.)]\s+", candidat)
                    or re.match(r"^\s*(---|\*\*\*)\s*$", candidat)
                    or re.match(r'^\s*!\[', candidat)):
                break
            paragraphe.append(candidat.strip())
            suivant += 1
        redacteur.paragraphe(" ".join(paragraphe))
        index = suivant
        numero = 0

    vider_table()
    vider_citation()
    if code:
        redacteur.code(code)

    sortie.parent.mkdir(parents=True, exist_ok=True)
    pdf.output(str(sortie))
    return pdf.page_no()


def main(argv: list[str] | None = None) -> int:
    analyseur = argparse.ArgumentParser(description="Markdown → PDF (SAÉ 302 — MiniShop).")
    analyseur.add_argument("source", type=Path)
    analyseur.add_argument("--out", type=Path)
    analyseur.add_argument("--titre")
    arguments = analyseur.parse_args(argv)

    source = arguments.source if arguments.source.is_absolute() else Path.cwd() / arguments.source
    if not source.is_file():
        print(f"✘ source introuvable : {source}", file=sys.stderr)
        return 2
    sortie = arguments.out or source.with_suffix(".pdf")
    if not sortie.is_absolute():
        sortie = Path.cwd() / sortie

    pages = convertir(source, sortie, arguments.titre)
    print(f"✓ {sortie}  ({pages} pages, {sortie.stat().st_size // 1024} Ko)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
