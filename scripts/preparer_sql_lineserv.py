#!/usr/bin/env python3
"""Prépare une COPIE locale du schéma, pour une base vide choisie par l'utilisateur.

Ne contacte aucun serveur et ne modifie ni le dépôt ni une base. Les fichiers
02/03 restent inchangés et contiennent des DROP d'objets : une base de recette
neuve et dédiée est indispensable pour l'import.
"""
from __future__ import annotations

import os
import re
import sys
import tempfile
from pathlib import Path

source = Path(__file__).resolve().parent.parent / 'sql' / '01_minishop_schema.sql'
try:
    original = source.read_text(encoding='utf-8')
    # Refuser toute modification amont imprévue : pas de heuristique silencieuse.
    motifs = [
        r'^DROP DATABASE IF EXISTS minishop;[^\n]*$',
        r'^CREATE DATABASE IF NOT EXISTS minishop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;[^\n]*$',
        r'^USE minishop;[^\n]*$',
        r'^DROP TABLE IF EXISTS parametre;[^\n]*$',
        r'^SET FOREIGN_KEY_CHECKS = 0;[^\n]*$',
        r'^SET FOREIGN_KEY_CHECKS = 1;[^\n]*$',
    ]
    texte = original
    for motif in motifs:
        texte, nombre = re.subn(motif, '-- Instruction retirée pour la recette en base vide.', texte, flags=re.MULTILINE)
        if nombre != 1:
            raise ValueError(f'Le schéma a changé : {nombre} occurrence(s) de {motif!r}; arrêt sans export.')
    # Les commentaires peuvent citer des exemples : contrôler les instructions actives.
    interdit = re.search(r'^\s*(?:DROP\s+(?:DATABASE|TABLE)|CREATE\s+DATABASE|USE\s+|SET\s+FOREIGN_KEY_CHECKS\s*=\s*0)\b', texte, re.IGNORECASE | re.MULTILINE)
    if interdit:
        raise ValueError('Une instruction de changement de base ou de suppression de table subsiste ; arrêt sans export.')
    if len(re.findall(r'^CREATE TABLE\s+', texte, re.MULTILINE)) != 8:
        raise ValueError('Le nombre de tables du schéma a changé ; vérifier le guide avant export.')
    dossier = Path(tempfile.mkdtemp(prefix='minishop-lineserv-'))
    sortie = dossier / '01_schema_pour_base_vide.sql'
    fd = os.open(sortie, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(fd, 'w', encoding='utf-8', newline='\n') as fichier:
        fichier.write('-- COPIE POUR UNE BASE DE RECETTE VIDE, SÉLECTIONNÉE DANS PHPMYADMIN.\n')
        fichier.write('-- Ne pas réimporter sur une base existante. Voir docs/14-guide-lineserv.md.\n')
        fichier.write(texte)
except (OSError, UnicodeError, ValueError) as exc:
    print(f'Préparation impossible : {exc}', file=sys.stderr)
    sys.exit(1)

print(f'Copie créée : {sortie}')
print('Le dépôt et la base distante ne sont pas modifiés.')
print('IMPORTANT : base de recette neuve et vide uniquement ; voir docs/14-guide-lineserv.md.')
print('Le schéma contient des comptes de démonstration connus : accès restreint obligatoire.')
