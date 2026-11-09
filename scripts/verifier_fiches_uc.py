#!/usr/bin/env python3
"""Contrôle documentaire en lecture seule ; ne lance aucun test métier."""
from __future__ import annotations

import csv
import re
import sys
from pathlib import Path
from urllib.parse import unquote

ROOT = Path(__file__).resolve().parent.parent
DOC = ROOT / 'docs/13-fiches-recette-uc.md'
CSV = ROOT / 'docs/13-suivi-recette-uc.csv'
TRIGGERS = ROOT / 'app/Model/Data/Triggers.php'
checks = 0
failures = []


def check(label: str, condition: bool) -> None:
    global checks
    checks += 1
    print(('✅ ' if condition else '❌ ') + label)
    if not condition:
        failures.append(label)


def load(path: Path, encoding: str = 'utf-8') -> str:
    try:
        return path.read_text(encoding=encoding)
    except (OSError, UnicodeError) as exc:
        failures.append(f'Lecture {path.relative_to(ROOT)} : {exc}')
        return ''


text = load(DOC)
code = load(TRIGGERS)
headings = re.findall(r'^## (UC-\d{2}) — ', text, re.MULTILINE)
check('14 UC distincts dans l’ordre', headings == [f'UC-{i:02d}' for i in range(1, 15)])

pattern = re.compile(r'^\| (TC(?:\d{2}|X)-\d{2}) \| (P[012]) ([MRI](?:/[MRI])*) (APP|SPEC) \| (.*?) \| (.*?) \|$', re.MULTILINE)
cases = pattern.findall(text)
ids = [row[0] for row in cases]
check('identifiants de scénarios uniques', len(ids) == len(set(ids)) and bool(ids))
all_case_ids = re.findall(r'^\| (TC(?:\d{2}|X)-\d{2}) \|', text, re.MULTILINE)
check('toutes les lignes de scénarios sont analysées', all_case_ids == ids)
check('scénarios avec actions et résultats explicites', all(row[4].strip() and row[5].strip() for row in cases))
for i in range(1, 15):
    found = [x for x in ids if x.startswith(f'TC{i:02d}-')]
    sequential = [f'TC{i:02d}-{j:02d}' for j in range(1, len(found) + 1)]
    check(f'UC-{i:02d} : scénarios présents et numérotés', bool(found) and found == sequential)
    if found:
        check(f'UC-{i:02d} : synthèse cohérente', f'{found[0]} à {found[-1]}' in text)
transverse = [x for x in ids if x.startswith('TCX-')]
check('scénarios transversaux ordonnés', transverse == [f'TCX-{i:02d}' for i in range(1, len(transverse) + 1)] and bool(transverse))

states = ['BROUILLON', 'EN_PREPARATION', 'PAYEE', 'EXPEDIEE', 'LIVREE', 'ANNULEE']
rows = re.findall(r'^\| (BROUILLON|EN_PREPARATION|PAYEE|EXPEDIEE|LIVREE|ANNULEE) \| ([AIX]) \| ([AIX]) \| ([AIX]) \| ([AIX]) \| ([AIX]) \| ([AIX]) \|$', text, re.MULTILINE)
check('matrice : six lignes et 36 couples', [row[0] for row in rows] == states and len(rows) == 6)
block = code.split('public const TRANSITIONS = [', 1)
transitions = {}
if len(block) == 2:
    block = block[1].split('public const STATUTS', 1)[0]
    for source, values in re.findall(r"'([A-Z_]+)'\s*=>\s*\[([^]]*)\]", block):
        transitions[source] = re.findall(r"'([A-Z_]+)'", values)
check('matrice : conforme à Triggers::TRANSITIONS', bool(transitions) and all(
    cell == ('I' if source == target else ('A' if target in transitions.get(source, []) else 'X'))
    for source, *cells in rows for target, cell in zip(states, cells)
))

links = re.findall(r'\[[^\]]+\]\(([^)]+)\)', text)
local = [unquote(link.split('#', 1)[0]) for link in links if not re.match(r'^(?:https?://|mailto:|#)', link)]
missing = [link for link in local if link and not (DOC.parent / link).is_file()]
check('liens locaux de la fiche existants', not missing)
if missing:
    print('  liens absents : ' + ', '.join(missing))
code_paths = sorted(set(re.findall(r'`((?:tests|app|sql|docs|scripts)/[^`\s]+)`', text)))
missing_paths = [path for path in code_paths if not (ROOT / path).is_file()]
check('fichiers du dépôt cités existants', bool(code_paths) and not missing_paths)
if missing_paths:
    print('  fichiers absents : ' + ', '.join(missing_paths))
check('méthodes documentées et limites explicites', all(term in text for term in ['Microsoft', 'Atlassian', 'ISTQB', 'Google', 'NON JOUÉ', 'pas une promesse']))

try:
    with CSV.open(encoding='utf-8-sig', newline='') as file:
        csv_rows = list(csv.DictReader(file, delimiter=';'))
except (OSError, UnicodeError, csv.Error) as exc:
    failures.append(f'Lecture suivi CSV : {exc}')
    csv_rows = []
fields = ['ID', 'UC', 'Priorité', 'Méthode', 'Base', 'Préconditions', 'Actions', 'Résultat attendu', 'Statut', 'Résultat observé', 'Preuve / anomalie', 'Testeur', 'Date']
check('colonnes du suivi CSV complètes', bool(csv_rows) and list(csv_rows[0]) == fields)
csv_ids = [row.get('ID', '') for row in csv_rows]
matrix_ids = [f'TC14-09/{source}→{target}' for source in states for target in states]
check('suivi : chaque scénario et chaque couple exactement une fois', len(csv_ids) == len(set(csv_ids)) and csv_ids == ids + matrix_ids)
allowed = {'NON JOUÉ', 'OK', 'KO', 'BLOQUÉ', 'NON APPLICABLE'}
check('suivi : statuts reconnus', bool(csv_rows) and all(row.get('Statut') in allowed for row in csv_rows))
check('suivi : actions et résultats attendus renseignés', bool(csv_rows) and all(row.get('Actions') and row.get('Résultat attendu') and row.get('Préconditions') for row in csv_rows))

print(f'\nFiches UC : {checks} contrôles ; {len(ids)} scénarios, {len(matrix_ids)} couples, {len(csv_ids)} lignes de suivi ; {len(failures)} erreur(s).')
if failures:
    for failure in failures:
        print('  - ' + failure)
    sys.exit(1)
print('Contrôle documentaire réussi : aucune exécution métier n’a été effectuée.')
