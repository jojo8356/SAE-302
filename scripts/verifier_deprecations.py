#!/usr/bin/env python3
"""Analyse les fichiers PHP sans les exécuter ; ne contacte ni serveur ni base.

La détection suit la version du binaire PHP choisi. Un contrôle `php -l` ne
peut pas détecter les dépréciations déclenchées seulement à l'exécution.
"""
from __future__ import annotations

import argparse
import os
import re
import shutil
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DOSSIERS = ('app', 'public', 'scripts', 'tests/php', 'tests/phpunit')
ERREUR_PHP = re.compile(r'(?im)^\s*(?:PHP\s+)?(?:Parse error|Fatal error)\s*:')
DEPRECATION = re.compile(r'(?im)^\s*(?:PHP\s+)?(?:Deprecated|User Deprecated)\s*:')


def erreur_marche(erreur: OSError) -> None:
    raise erreur


def collecter(chemins: list[Path]) -> list[Path]:
    fichiers: set[Path] = set()
    for chemin in chemins:
        if not chemin.exists():
            raise ValueError(f'chemin absent : {chemin}')
        if chemin.is_symlink():
            raise ValueError(f'lien symbolique non analysé : {chemin}')
        if chemin.is_file():
            if chemin.suffix.lower() != '.php':
                raise ValueError(f'fichier non PHP : {chemin}')
            fichiers.add(chemin.resolve())
        else:
            for dossier, sous_dossiers, noms in os.walk(chemin, followlinks=False, onerror=erreur_marche):
                sous_dossiers[:] = [nom for nom in sous_dossiers if nom not in ('vendor', 'node_modules', '.git') and not (Path(dossier) / nom).is_symlink()]
                for nom in noms:
                    fichier = Path(dossier) / nom
                    if fichier.suffix.lower() == '.php' and not fichier.is_symlink():
                        fichiers.add(fichier.resolve())
    return sorted(fichiers)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php', default='php', help='binaire PHP CLI à analyser (défaut : php dans le PATH)')
    parser.add_argument('--chemin', action='append', help='fichier ou dossier PHP ; répétable (défaut : code et suites du dépôt)')
    args = parser.parse_args()
    programme = shutil.which(args.php)
    if programme is None:
        print(f'⏭ NON JOUÉ : PHP introuvable : {args.php}.')
        return 2
    chemins = [Path(p) for p in args.chemin] if args.chemin else [ROOT / p for p in DOSSIERS]
    try:
        fichiers = collecter(chemins)
    except (OSError, ValueError) as exc:
        print(f'❌ Analyse impossible : {exc}', file=sys.stderr)
        return 1
    if not fichiers:
        print('❌ Aucun fichier PHP à analyser.', file=sys.stderr)
        return 1
    options = ['-d', 'error_reporting=-1', '-d', 'display_errors=1', '-d', 'log_errors=0']
    try:
        version = subprocess.run([programme, *options, '-r', 'echo PHP_VERSION, " ", PHP_SAPI;'], capture_output=True, text=True, errors='replace', timeout=30)
    except (OSError, subprocess.TimeoutExpired) as exc:
        print(f'❌ Impossible de lancer PHP : {exc}', file=sys.stderr)
        return 1
    if version.returncode != 0 or not version.stdout.strip():
        print(f'❌ Impossible de déterminer la version PHP.\n{version.stdout}{version.stderr}', file=sys.stderr)
        return 1
    print(f'Analyse PHP : {version.stdout.strip()} ; {len(fichiers)} fichier(s).')
    if DEPRECATION.search(version.stdout + '\n' + version.stderr):
        print('❌ Dépréciation lors du démarrage de PHP :')
        print(version.stdout + version.stderr)
        return 1
    echecs = 0
    for fichier in fichiers:
        try:
            resultat = subprocess.run([programme, *options, '-l', str(fichier)], capture_output=True, text=True, errors='replace', timeout=30)
        except (OSError, subprocess.TimeoutExpired) as exc:
            print(f'❌ {fichier} : analyse impossible : {exc}')
            echecs += 1
            continue
        sortie = resultat.stdout + '\n' + resultat.stderr
        if resultat.returncode != 0 or DEPRECATION.search(sortie) or ERREUR_PHP.search(sortie):
            echecs += 1
            try:
                libelle = str(fichier.relative_to(ROOT))
            except ValueError:
                libelle = str(fichier)
            print(f'❌ {libelle} :')
            print(sortie.strip())
    if echecs:
        print(f'DÉPRÉCIATIONS/SYNTAXE : {echecs} fichier(s) en échec. Corriger les messages ci-dessus.')
        return 1
    print(f'✅ DÉPRÉCIATIONS/SYNTAXE : aucune détectée à l’analyse dans {len(fichiers)} fichier(s), avec ce PHP.')
    print('Limites : les dépréciations à l’exécution et celles propres à une autre version nécessitent des tests complémentaires.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
