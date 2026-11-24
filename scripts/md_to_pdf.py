#!/usr/bin/env python3
"""MiniShop — convertit un ou plusieurs documents Markdown en PDF académique A4 (fpdf2).

    python3 scripts/md_to_pdf.py docs/27-rapport-universitaire-projet-minishop.md --livrable
    python3 scripts/md_to_pdf.py docs/01-cahier-des-charges-MiniShop.md \
        --out dist/pdf/01-cahier-des-charges-MiniShop.pdf

Mise en page académique / grandes écoles (« format très droit ») :
* marges A4 strictes (20 mm gauche/droite, 20 mm haut/bas) ;
* cartouche institutionnel en page 1 (Université Côte d'Azur · SAÉ 302) ;
* corps en DejaVu Serif (9,8 pt), titres en DejaVu Sans Bold marine (#0F294A)
  avec protection anti-titre orphelin et code en DejaVu Sans Mono ;
* tableaux de style LaTeX booktabs (filets horizontaux uniquement) ;
* encadrés droits multi-lignes à barre latérale marine ;
* en-tête courant avec « Page X / Y » et pied de page normalisé.
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

MARINE = (15, 41, 74)          # #0F294A
MARINE_DOUX = (30, 58, 95)     # #1E3A5F
ENCRE = (15, 23, 42)           # #0F172A
ENCRE_DOUCE = (71, 85, 105)    # #475569
TRAIT = (203, 213, 225)        # #CBD5E1
TRAIT_FIN = (226, 232, 240)    # #E2E8F0
FOND_CODE = (248, 250, 252)    # #F8FAFC
FOND_ENTETE = (238, 242, 246)  # #EEF2F6

SUBSTITUTIONS = {
    "✅": "[OK]", "✔": "[OK]", "☐": "[ ]", "⬜": "[ ]", "◐": "[~]",
    "⚠️": "!", "⚠": "!", "✘": "x", "✓": "v",
    "→": "->", "←": "<-", "↔": "<->", "⇄": "<->",
    "≤": "<=", "≥": ">=", "≠": "!=", "…": "...", "€": "EUR",
    "—": "-", "–": "-", "’": "'", "‘": "'", "“": '"', "”": '"',
    "•": "-", "·": "·", " ": " ", " ": " ", "−": "-",
}


def nettoyer(texte: str) -> str:
    """Remplace les caractères Unicode typographiques hors Latin-1 et les émojis.

    # Voir ADR-0004 : les polices DejaVu intégrées couvrent le jeu cible, mais
    # les tirets longs et guillemets courbes sont normalisés pour la lisibilité.

    Args:
        texte: Chaîne Unicode brute à normaliser pour FPDF2.

    Returns:
        Chaîne normalisée sans émojis hors plage.

    Example:
        >>> nettoyer("MiniShop — « Achat » ✅")
        'MiniShop - « Achat » [OK]'
    """
    for source, cible in SUBSTITUTIONS.items():
        texte = texte.replace(source, cible)
    return re.sub(r"[\U0001F000-\U0001FAFF\u2600-\u27BF\uFE0F]", "", texte)


def trouver_polices() -> str:
    """Recherche le dossier contenant les polices DejaVu TrueType sur le système.

    Returns:
        Chemin absolu du premier dossier contenant ``DejaVuSans.ttf``.

    Raises:
        SystemExit: Si aucune police DejaVu n'est disponible localement.
    """
    for candidat in (
        "/usr/share/fonts/truetype/dejavu",
        "/usr/share/fonts/dejavu",
        "/usr/local/share/fonts/dejavu",
        os.path.expanduser("~/.fonts"),
    ):
        if os.path.isdir(candidat) and os.path.isfile(os.path.join(candidat, "DejaVuSans.ttf")):
            return candidat
    print("✘ polices DejaVu introuvables : installez fonts-dejavu-core", file=sys.stderr)
    raise SystemExit(2)


class Document(FPDF):
    """PDF A4 académique avec cartouche initial, en-tête paginé et pied de page."""

    def __init__(self, titre_courant: str, ref_source: str) -> None:
        """Initialise la géométrie A4, les marges et les polices embarquées.

        Args:
            titre_courant: Titre affiché dans l'en-tête des pages intérieures.
            ref_source: Chemin relatif du document Markdown source.
        """
        super().__init__(orientation="P", unit="mm", format="A4")
        self.titre_courant = nettoyer(titre_courant)
        self.ref_source = nettoyer(ref_source)
        self.alias_nb_pages()
        self.set_auto_page_break(auto=True, margin=20)
        self.set_margins(20, 20, 20)

        dossier = trouver_polices()
        sans = os.path.join(dossier, "DejaVuSans.ttf")
        sans_b = os.path.join(dossier, "DejaVuSans-Bold.ttf")
        serif = os.path.join(dossier, "DejaVuSerif.ttf")
        serif_b = os.path.join(dossier, "DejaVuSerif-Bold.ttf")
        mono = os.path.join(dossier, "DejaVuSansMono.ttf")
        mono_b = os.path.join(dossier, "DejaVuSansMono-Bold.ttf")

        self.add_font("Sans", "", sans)
        self.add_font("Sans", "B", sans_b)
        self.add_font("Sans", "I", sans)
        if os.path.isfile(serif):
            self.add_font("Doc", "", serif)
            self.add_font("Doc", "B", serif_b if os.path.isfile(serif_b) else serif)
            self.add_font("Doc", "I", serif)
        else:
            self.add_font("Doc", "", sans)
            self.add_font("Doc", "B", sans_b)
            self.add_font("Doc", "I", sans)
        self.add_font("Mono", "", mono)
        self.add_font("Mono", "B", mono_b if os.path.isfile(mono_b) else mono)

        self.set_font("Doc", "", 9.8)
        self.set_text_color(*ENCRE)

    def header(self) -> None:
        """Trace l'en-tête paginé à partir de la deuxième page."""
        if self.page_no() == 1:
            return
        self.set_font("Sans", "", 7.8)
        self.set_text_color(*ENCRE_DOUCE)
        largeur = self.w - self.l_margin - self.r_margin
        self.cell(largeur * 0.78, 5, self.titre_courant[:82], align="L")
        self.cell(largeur * 0.22, 5, f"Page {self.page_no()} / {{nb}}", align="R",
                  new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        self.set_draw_color(*TRAIT)
        self.set_line_width(0.2)
        self.line(self.l_margin, self.get_y() + 0.5, self.w - self.r_margin, self.get_y() + 0.5)
        self.ln(4.5)
        self.set_text_color(*ENCRE)

    def footer(self) -> None:
        """Trace le pied de page institutionnel sur chaque page."""
        self.set_y(-14)
        self.set_draw_color(*TRAIT)
        self.set_line_width(0.2)
        self.line(self.l_margin, self.get_y() - 1.2, self.w - self.r_margin, self.get_y() - 1.2)
        self.set_font("Sans", "", 7.3)
        self.set_text_color(*ENCRE_DOUCE)
        largeur = self.w - self.l_margin - self.r_margin
        self.cell(largeur * 0.7, 5, "Université Côte d'Azur · BUT Informatique · SAÉ 302 — MiniShop", align="L")
        self.cell(largeur * 0.3, 5, "Dossier technique", align="R")

    def cartouche_initial(self) -> None:
        """Dessine le bandeau institutionnel d'ouverture sur la première page."""
        self.set_font("Sans", "B", 7.8)
        self.set_text_color(*MARINE)
        self.cell(0, 4.2, "UNIVERSITÉ CÔTE D'AZUR  ·  IUT DE NICE  ·  BUT INFORMATIQUE (SAÉ 302)",
                  new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        self.set_font("Sans", "", 8)
        self.set_text_color(*ENCRE_DOUCE)
        self.cell(0, 4.4, f"Projet MiniShop  ·  Johan Polsinelli & Chahd Gouiaa  ·  Source : {self.ref_source}",
                  new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        y = self.get_y() + 1.2
        self.set_draw_color(*MARINE)
        self.set_line_width(0.55)
        self.line(self.l_margin, y, self.w - self.r_margin, y)
        self.set_line_width(0.18)
        self.line(self.l_margin, y + 1.0, self.w - self.r_margin, y + 1.0)
        self.set_y(y + 4.5)
        self.set_text_color(*ENCRE)


MOTIFS = [
    ("code", re.compile(r"`([^`]+)`")),
    ("gras", re.compile(r"\*\*(.+?)\*\*")),
    ("ital", re.compile(r"(?<![\w*])\*([^*\n]+)\*(?![\w*])")),
]


def segments(texte: str) -> list[tuple[str, str]]:
    """Découpe une ligne Markdown en segments (texte, code, gras, italique).

    Args:
        texte: Chaîne Markdown inline à segmenter.

    Returns:
        Liste de couples ``(genre, contenu)``.

    Example:
        >>> segments("Règle **RB-01** (`SQL`)")
        [('texte', 'Règle '), ('gras', 'RB-01'), ('texte', ' ('), ('code', 'SQL'), ('texte', ')')]
    """
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


def enlever_marquage_inline(texte: str) -> str:
    """Retire le marquage inline gras, italique et code d'une chaîne.

    Args:
        texte: Texte pouvant contenir du formatage Markdown inline.

    Returns:
        Texte brut sans délimiteurs Markdown.

    Example:
        >>> enlever_marquage_inline("**Titre** avec *accent* et `code`")
        'Titre avec accent et code'
    """
    texte = re.sub(r"`([^`]+)`", r"\1", texte)
    texte = re.sub(r"\*\*(.+?)\*\*", r"\1", texte)
    return re.sub(r"(?<![\w*])\*([^*\n]+)\*(?![\w*])", r"\1", texte)


class Redacteur:
    """Moteur de mise en page des blocs Markdown vers un objet Document FPDF."""

    def __init__(self, pdf: Document, source: Path) -> None:
        """Associe un document PDF cible et le fichier Markdown source.

        Args:
            pdf: Instance ``Document`` dans laquelle écrire.
            source: Chemin du fichier source servant de référence aux images.
        """
        self.pdf = pdf
        self.source = source

    def _police(self, genre: str, taille: float) -> None:
        if genre == "code":
            self.pdf.set_font("Mono", "", max(taille - 1.1, 6.5))
            self.pdf.set_text_color(*MARINE_DOUX)
        elif genre == "gras":
            self.pdf.set_font("Doc", "B", taille)
            self.pdf.set_text_color(*ENCRE)
        elif genre == "ital":
            self.pdf.set_font("Doc", "I", taille)
            self.pdf.set_text_color(*ENCRE_DOUCE)
        else:
            self.pdf.set_font("Doc", "", taille)
            self.pdf.set_text_color(*ENCRE)

    def ligne_riche(self, texte: str, taille: float, hauteur_ligne: float) -> None:
        """Écrit une ligne de texte en alternant polices normale, grasse, italique et code.

        Args:
            texte: Chaîne Markdown inline à rendre.
            taille: Taille de police en points.
            hauteur_ligne: Hauteur de ligne en millimètres.
        """
        for genre, contenu in segments(nettoyer(texte)):
            self._police(genre, taille)
            self.pdf.write(hauteur_ligne, contenu)

    def titre(self, texte: str, niveau: int) -> None:
        """Rend un titre hiérarchique avec garde anti-titre orphelin et filet.

        Args:
            texte: Intitulé du titre Markdown.
            niveau: Niveau de titre de 1 à 4.
        """
        tailles = {1: 16.5, 2: 12.5, 3: 10.8, 4: 9.8}
        taille = tailles.get(niveau, 9.8)
        # Garde anti-titre orphelin
        if niveau >= 2 and self.pdf.get_y() > self.pdf.h - self.pdf.b_margin - 24:
            self.pdf.add_page()
        if niveau > 1:
            self.pdf.ln(3.2 if niveau == 2 else 2.2)
        self.pdf.set_font("Sans", "B", taille)
        self.pdf.set_text_color(*(MARINE if niveau <= 2 else MARINE_DOUX))
        self.pdf.multi_cell(0, taille * 0.52, nettoyer(enlever_marquage_inline(texte)),
                            new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        if niveau == 1:
            self.pdf.set_draw_color(*MARINE)
            self.pdf.set_line_width(0.45)
            y = self.pdf.get_y() + 0.8
            self.pdf.line(self.pdf.l_margin, y, self.pdf.w - self.pdf.r_margin, y)
            self.pdf.ln(3.5)
        elif niveau == 2:
            self.pdf.set_draw_color(*TRAIT)
            self.pdf.set_line_width(0.22)
            y = self.pdf.get_y() + 0.5
            self.pdf.line(self.pdf.l_margin, y, self.pdf.w - self.pdf.r_margin, y)
            self.pdf.ln(2.6)
        else:
            self.pdf.ln(1.4)
        self.pdf.set_text_color(*ENCRE)

    def paragraphe(self, texte: str, retrait: float = 0) -> None:
        """Rend un paragraphe justifié à gauche avec retrait optionnel.

        Args:
            texte: Contenu du paragraphe Markdown.
            retrait: Décalage horizontal en millimètres depuis la marge gauche.
        """
        self.pdf.set_x(self.pdf.l_margin + retrait)
        self.ligne_riche(texte, 9.8, 4.9)
        self.pdf.ln(6.2)

    def puce(self, texte: str, niveau: int = 0) -> None:
        """Rend un élément de liste à puces indenté selon son niveau.

        Args:
            texte: Texte de l'item Markdown.
            niveau: Profondeur d'imbrication (0 ou 1).
        """
        retrait = 4 + niveau * 5
        self.pdf.set_x(self.pdf.l_margin + retrait)
        self.pdf.set_font("Sans", "B", 9.5)
        self.pdf.set_text_color(*MARINE)
        self.pdf.write(4.8, "• " if niveau == 0 else "– ")
        self.ligne_riche(texte, 9.6, 4.8)
        self.pdf.ln(5.8)

    def numerotee(self, numero: int, texte: str) -> None:
        """Rend un élément de liste numérotée.

        Args:
            numero: Numéro d'ordre affiché.
            texte: Contenu de l'élément de liste.
        """
        self.pdf.set_x(self.pdf.l_margin + 4)
        self.pdf.set_font("Sans", "B", 9.5)
        self.pdf.set_text_color(*MARINE)
        self.pdf.write(4.8, f"{numero}. ")
        self.ligne_riche(texte, 9.6, 4.8)
        self.pdf.ln(5.8)

    def code(self, lignes: list[str]) -> None:
        """Rend un bloc de code monospace sur fond gris avec filet gauche.

        Args:
            lignes: Lignes brutes du bloc de code Markdown.
        """
        if not lignes:
            return
        self.pdf.ln(1.2)
        self.pdf.set_font("Mono", "", 7.8)
        largeur = self.pdf.w - self.pdf.l_margin - self.r_margin_val
        hauteur = 4.1
        for brute in lignes:
            morceaux = textwrap.wrap(
                nettoyer(brute).replace("\t", "    "),
                width=94,
                replace_whitespace=False,
                drop_whitespace=False,
            ) or [""]
            for morceau in morceaux:
                if self.pdf.get_y() + hauteur > self.pdf.h - self.pdf.b_margin - 2:
                    self.pdf.add_page()
                y = self.pdf.get_y()
                self.pdf.set_fill_color(*FOND_CODE)
                self.pdf.set_draw_color(*TRAIT)
                self.pdf.set_line_width(0.2)
                self.pdf.rect(self.pdf.l_margin, y, largeur, hauteur, style="F")
                self.pdf.line(self.pdf.l_margin, y, self.pdf.l_margin, y + hauteur)
                self.pdf.line(self.pdf.l_margin + largeur, y, self.pdf.l_margin + largeur, y + hauteur)
                self.pdf.set_draw_color(*MARINE)
                self.pdf.set_line_width(0.6)
                self.pdf.line(self.pdf.l_margin, y, self.pdf.l_margin, y + hauteur)
                self.pdf.set_xy(self.pdf.l_margin + 2.5, y)
                self.pdf.set_text_color(*ENCRE)
                self.pdf.cell(largeur - 3.5, hauteur, morceau, new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        self.pdf.ln(2.6)
        self.pdf.set_font("Doc", "", 9.8)
        self.pdf.set_text_color(*ENCRE)

    @property
    def r_margin_val(self) -> float:
        """Retourne la marge droite courante du document PDF en millimètres."""
        return self.pdf.r_margin

    def citation(self, lignes: list[str]) -> None:
        """Rend un bloc de citation encadré sur fond clair avec barre latérale.

        Args:
            lignes: Lignes composant la citation.
        """
        if not lignes:
            return
        largeur = self.pdf.w - self.pdf.l_margin - self.pdf.r_margin
        texte_complet = "\n".join(nettoyer(enlever_marquage_inline(l)) for l in lignes if l.strip())
        if not texte_complet:
            return
        lignes_wrap: list[str] = []
        for bloc in texte_complet.split("\n"):
            lignes_wrap.extend(textwrap.wrap(bloc, width=90) or [""])
        hauteur_ligne = 4.6
        hauteur_totale = len(lignes_wrap) * hauteur_ligne + 3.2
        if self.pdf.get_y() + hauteur_totale > self.pdf.h - self.pdf.b_margin:
            self.pdf.add_page()
        y = self.pdf.get_y()
        self.pdf.set_fill_color(*FOND_CODE)
        self.pdf.set_draw_color(*TRAIT)
        self.pdf.set_line_width(0.2)
        self.pdf.rect(self.pdf.l_margin, y, largeur, hauteur_totale, style="DF")
        self.pdf.set_fill_color(*MARINE)
        self.pdf.rect(self.pdf.l_margin, y, 1.1, hauteur_totale, style="F")
        self.pdf.set_font("Doc", "", 9.1)
        self.pdf.set_text_color(*ENCRE)
        for idx, ligne in enumerate(lignes_wrap):
            self.pdf.set_xy(self.pdf.l_margin + 3.8, y + 1.6 + idx * hauteur_ligne)
            self.pdf.cell(largeur - 5.5, hauteur_ligne, ligne)
        self.pdf.set_xy(self.pdf.l_margin, y + hauteur_totale + 2.2)

    def tableau(self, lignes: list[list[str]]) -> None:
        """Rend un tableau Markdown avec largeurs proportionnelles et filets booktabs.

        Args:
            lignes: Lignes découpées du tableau (en-tête, séparateur, données).
        """
        if len(lignes) < 2:
            return
        entetes = [nettoyer(enlever_marquage_inline(c)).strip() for c in lignes[0]]
        corps = [[nettoyer(enlever_marquage_inline(c)).strip() for c in ligne] for ligne in lignes[2:]]
        colonnes = len(entetes)
        largeur = self.pdf.w - self.pdf.l_margin - self.pdf.r_margin

        poids = []
        for index in range(colonnes):
            longueur = max([len(entetes[index])] + [len(l[index]) if index < len(l) else 0 for l in corps])
            poids.append(max(7, min(longueur, 45)))
        total = sum(poids)
        largeurs = [largeur * p / total for p in poids]

        def tracer_ligne(valeurs: list[str], est_entete: bool, pair: bool) -> None:
            """Trace une ligne du tableau avec retour à la ligne multi-cellules."""
            hauteurs = []
            for index, valeur in enumerate(valeurs):
                lignes_cellule = textwrap.wrap(valeur, width=max(8, int(largeurs[index] / 1.65))) or [""]
                hauteurs.append(lignes_cellule)
            hauteur_ligne = 3.95
            hauteur = max(len(h) for h in hauteurs) * hauteur_ligne + 2.2
            if self.pdf.get_y() + hauteur > self.pdf.h - self.pdf.b_margin:
                self.pdf.add_page()
            y = self.pdf.get_y()
            x = self.pdf.l_margin
            fond = FOND_ENTETE if est_entete else (FOND_CODE if pair else None)
            if fond:
                self.pdf.set_fill_color(*fond)
                self.pdf.rect(self.pdf.l_margin, y, largeur, hauteur, style="F")
            # Filets booktabs (horizontaux uniquement)
            if est_entete:
                self.pdf.set_draw_color(*MARINE)
                self.pdf.set_line_width(0.45)
                self.pdf.line(self.pdf.l_margin, y, self.pdf.l_margin + largeur, y)
                self.pdf.set_line_width(0.32)
                self.pdf.line(self.pdf.l_margin, y + hauteur, self.pdf.l_margin + largeur, y + hauteur)
            else:
                self.pdf.set_draw_color(*TRAIT_FIN)
                self.pdf.set_line_width(0.18)
                self.pdf.line(self.pdf.l_margin, y + hauteur, self.pdf.l_margin + largeur, y + hauteur)

            for index, contenu in enumerate(hauteurs):
                self.pdf.set_font("Sans", "B" if est_entete else "", 7.9 if est_entete else 7.8)
                self.pdf.set_text_color(*(MARINE if est_entete else ENCRE))
                for numero, ligne in enumerate(contenu):
                    self.pdf.set_xy(x + 1.4, y + 1.1 + numero * hauteur_ligne)
                    self.pdf.cell(largeurs[index] - 2.6, hauteur_ligne, ligne)
                x += largeurs[index]
            self.pdf.set_xy(self.pdf.l_margin, y + hauteur)
            self.pdf.set_text_color(*ENCRE)

        tracer_ligne(entetes, True, False)
        for index, ligne in enumerate(corps):
            valeurs = (ligne + [""] * colonnes)[:colonnes]
            tracer_ligne(valeurs, False, bool(index % 2))
        # Filet bas booktabs
        y_fin = self.pdf.get_y()
        self.pdf.set_draw_color(*MARINE)
        self.pdf.set_line_width(0.45)
        self.pdf.line(self.pdf.l_margin, y_fin, self.pdf.l_margin + largeur, y_fin)
        self.pdf.ln(3.2)

    def image(self, chemin: str, legende: str) -> None:
        """Insère une image centrée et sa légende éventuelle dans le PDF.

        Args:
            chemin: Chemin relatif vers le fichier image.
            legende: Texte de légende affiché sous la figure.
        """
        cible = (self.source.parent / chemin).resolve()
        if not cible.is_file():
            cible = (RACINE / chemin).resolve()
        if not cible.is_file():
            self.pdf.set_font("Doc", "I", 8.5)
            self.pdf.set_text_color(*ENCRE_DOUCE)
            self.pdf.multi_cell(0, 4.5, f"[figure absente : {chemin}]", new_x=XPos.LMARGIN, new_y=YPos.NEXT)
            return
        largeur = self.pdf.w - self.pdf.l_margin - self.pdf.r_margin
        if self.pdf.get_y() > self.pdf.h - self.pdf.b_margin - 55:
            self.pdf.add_page()
        try:
            self.pdf.image(str(cible), w=min(largeur, 165), x=self.pdf.l_margin)
        except Exception as erreur:
            print(f"  ⚠ image ignorée ({cible.name}) : {erreur}", file=sys.stderr)
            return
        self.pdf.ln(2)
        if legende:
            self.pdf.set_font("Sans", "", 8.2)
            self.pdf.set_text_color(*ENCRE_DOUCE)
            self.pdf.multi_cell(0, 4.2, f"Figure — {nettoyer(legende)}", align="C",
                                new_x=XPos.LMARGIN, new_y=YPos.NEXT)
            self.pdf.set_text_color(*ENCRE)
        self.pdf.ln(3)


def convertir(source: Path, sortie: Path, titre: str | None = None) -> int:
    """Convertit un document Markdown en PDF A4 structuré.

    Args:
        source: Chemin du fichier Markdown source.
        sortie: Chemin du fichier PDF à créer.
        titre: Titre courant optionnel remplaçant le premier H1.

    Returns:
        Nombre total de pages générées.
    """
    markdown = source.read_text(encoding="utf-8")
    premier_titre = re.search(r"^#\s+(.+)$", markdown, flags=re.M)
    titre_document = titre or (premier_titre.group(1).strip() if premier_titre else source.stem)
    rel_source = str(source.relative_to(RACINE)) if source.is_relative_to(RACINE) else source.name

    pdf = Document(titre_document, rel_source)
    pdf.add_page()
    pdf.cartouche_initial()
    redacteur = Redacteur(pdf, source)

    lignes = markdown.splitlines()
    index = 0
    dans_code = False
    code: list[str] = []
    table: list[list[str]] = []
    citation: list[str] = []
    numero = 0

    def vider_table() -> None:
        """Écrit le tableau accumulé puis vide le tampon."""
        nonlocal table
        if table:
            redacteur.tableau(table)
            table = []

    def vider_citation() -> None:
        """Écrit la citation accumulée puis vide le tampon."""
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

        if re.match(r"^\s*<!--\s*logos:\s*.+?\s*-->\s*$", brute):
            index += 1
            continue

        if re.match(r"^\s*(\\newpage|<!--\s*pagebreak\s*-->)\s*$", brute):
            pdf.add_page()
            index += 1
            numero = 0
            continue

        if re.match(r"^\s*(---|\*\*\*)\s*$", brute):
            pdf.ln(2)
            pdf.set_draw_color(*TRAIT)
            pdf.set_line_width(0.25)
            y = pdf.get_y()
            pdf.line(pdf.l_margin, y, pdf.w - pdf.r_margin, y)
            pdf.ln(3.5)
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
            pdf.ln(1.4)
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
    """Point d'entrée CLI pour la conversion Markdown vers PDF A4.

    Args:
        argv: Arguments de ligne de commande optionnels.

    Returns:
        Code de sortie 0 en cas de succès, 2 en cas d'argument invalide ou absent.
    """
    analyseur = argparse.ArgumentParser(description="Markdown → PDF académique (SAÉ 302 — MiniShop).")
    analyseur.add_argument("sources", nargs="+", type=Path, help="Fichier(s) Markdown source(s)")
    analyseur.add_argument("--out", type=Path, help="Chemin du PDF cible (pour un seul fichier)")
    analyseur.add_argument("--livrable", action="store_true",
                           help="Écrit directement dans livrables/pdf/<nom>.pdf")
    analyseur.add_argument("--titre", help="Titre courant personnalisé")
    arguments = analyseur.parse_args(argv)

    if arguments.out and len(arguments.sources) > 1:
        print("✘ --out ne s'utilise qu'avec un seul fichier source", file=sys.stderr)
        return 2

    for entree in arguments.sources:
        source = entree if entree.is_absolute() else Path.cwd() / entree
        if not source.is_file():
            print(f"✘ source introuvable : {source}", file=sys.stderr)
            return 2
        if arguments.livrable:
            sortie = RACINE / "livrables" / "pdf" / f"{source.stem}.pdf"
        elif arguments.out:
            sortie = arguments.out if arguments.out.is_absolute() else Path.cwd() / arguments.out
        else:
            sortie = source.with_suffix(".pdf")

        pages = convertir(source, sortie, arguments.titre)
        print(f"✓ {sortie}  ({pages} pages, {sortie.stat().st_size // 1024} Ko)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
