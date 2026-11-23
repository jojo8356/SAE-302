#!/usr/bin/env python3
"""Génère un squelette de postmortem blameless daté pour MiniShop.

Crée un fichier Markdown dans ``docs/exploitation/postmortems/`` à partir
du gabarit ``docs/exploitation/postmortem-modele.md`` en renseignant la
date du jour, l'identifiant d'incident et un titre normalisé.
"""

from __future__ import annotations

import argparse
import datetime as dt
import pathlib
import re

RACINE = pathlib.Path(__file__).resolve().parent.parent
MODELE = RACINE / "docs" / "exploitation" / "postmortem-modele.md"
DOSSIER_SORTIE = RACINE / "docs" / "exploitation" / "postmortems"


def normaliser_slug(texte: str) -> str:
    """Transforme un titre ou identifiant d'incident en slug kebab-case sûr.

    Args:
        texte: Libellé brut saisi par l'utilisateur ou le workflow CI.

    Returns:
        Chaîne en minuscules composée uniquement de lettres ASCII, chiffres et tirets.

    Example:
        >>> normaliser_slug("Timeout MariaDB — Panier #12 !")
        'timeout-mariadb-panier-12'
    """
    translit = str.maketrans("àâäéèêëîïôöùûüçÀÂÄÉÈÊËÎÏÔÖÙÛÜÇ", "aaaeeeeiioouuucAAAEEEEIIOOUUUC")
    propre = texte.translate(translit).lower()
    propre = re.sub(r"[^a-z0-9]+", "-", propre).strip("-")
    return propre or "incident"


def construire_contenu(
    modele_texte: str,
    identifiant: str,
    titre: str,
    date_iso: str,
) -> str:
    """Instancie le texte du modèle de postmortem avec les métadonnées d'incident.

    Args:
        modele_texte: Contenu brut de ``docs/exploitation/postmortem-modele.md``.
        identifiant: Identifiant d'incident (ex. ``INC-2026-11-16-01``).
        titre: Titre court décrivant le symptôme observé.
        date_iso: Date de l'incident au format ``AAAA-MM-JJ``.

    Returns:
        Contenu Markdown prêt à être écrit dans ``docs/exploitation/postmortems/``.
    """
    resultat = modele_texte.replace("[Titre court de l’incident]", titre, 1)
    resultat = resultat.replace("`INC-AAAA-MM-JJ-01`", f"`{identifiant}`", 1)
    resultat = resultat.replace("`AAAA-MM-JJ`", f"`{date_iso}`", 1)
    resultat = resultat.replace("`En cours de rédaction | En cours de suivi | Clôturé`", "`En cours de rédaction`", 1)
    return resultat


def generer_postmortem(
    identifiant: str,
    titre: str,
    date_iso: str | None = None,
    sortie: pathlib.Path | None = None,
) -> pathlib.Path:
    """Crée le fichier de postmortem daté sur disque sans écraser d'archive existante.

    Args:
        identifiant: Identifiant de l'incident (ex. ``INC-2026-11-16-01``).
        titre: Titre court de l'incident.
        date_iso: Date au format ``AAAA-MM-JJ`` (par défaut la date UTC du jour).
        sortie: Chemin explicite de sortie optionnel.

    Returns:
        Chemin du fichier Markdown généré.

    Raises:
        FileExistsError: Si le fichier cible existe déjà.
    """
    date_finale = date_iso or dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%d")
    slug = normaliser_slug(titre)
    cible = sortie or (DOSSIER_SORTIE / f"postmortem-{date_finale}-{slug}.md")
    if cible.exists():
        raise FileExistsError(f"Un postmortem existe déjà à cet emplacement : {cible}")

    cible.parent.mkdir(parents=True, exist_ok=True)
    contenu = construire_contenu(MODELE.read_text(encoding="utf-8"), identifiant, titre, date_finale)
    cible.write_text(contenu, encoding="utf-8")
    return cible


def main(argv: list[str] | None = None) -> int:
    """Point d'entrée CLI pour générer un squelette de postmortem blameless.

    Args:
        argv: Arguments de ligne de commande optionnels.

    Returns:
        ``0`` si le fichier est créé, ``1`` si un fichier homonyme existe déjà.
    """
    parser = argparse.ArgumentParser(
        description="Génère un squelette de postmortem blameless MiniShop."
    )
    parser.add_argument("--id", required=True, dest="identifiant", help="Identifiant d'incident (ex. INC-2026-11-16-01)")
    parser.add_argument("--titre", required=True, help="Titre court de l'incident")
    parser.add_argument("--date", dest="date_iso", help="Date de l'incident (AAAA-MM-JJ, UTC par défaut)")
    parser.add_argument("--out", type=pathlib.Path, help="Chemin de sortie personnalisé")
    args = parser.parse_args(argv)

    try:
        cible = generer_postmortem(args.identifiant, args.titre, args.date_iso, args.out)
    except FileExistsError as exc:
        print(f"ERREUR: {exc}")
        return 1

    print(f"Squelette créé : {cible}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
