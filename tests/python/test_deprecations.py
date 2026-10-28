"""Tests isolés du contrôle CLI ; aucune base ni donnée de l'application."""
import os
import re
import shutil
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
CONTROLE = ROOT / 'scripts' / 'verifier_deprecations.py'


class AnalyseDeprecationsTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.racine = Path(self.temp.name)
        self.php = self.racine / 'php-factice'
        self.php.write_text('''#!/usr/bin/env python3
import pathlib, sys
if '-r' in sys.argv:
    print('8.4.0 cli')
else:
    fichier = pathlib.Path(sys.argv[-1])
    contenu = fichier.read_text()
    if 'DEPRECIE' in contenu:
        print('PHP Deprecated: exemple volontaire', file=sys.stderr)
    if 'SYNTAXE_INVALIDE' in contenu:
        print('PHP Parse error: syntax error', file=sys.stderr)
        if 'CODE_ZERO' not in contenu:
            sys.exit(255)
    print('No syntax errors detected in ' + str(fichier))
''', encoding='utf8')
        self.php.chmod(0o755)
        self.sources = self.racine / 'sources'
        self.sources.mkdir()

    def execute(self, *args):
        return subprocess.run([sys.executable, str(CONTROLE), '--php', str(self.php), *args], text=True, capture_output=True)

    def test_sain_et_deduplication(self):
        fichier = self.sources / 'sain.php'
        fichier.write_text('<?php echo 1;', encoding='utf8')
        resultat = self.execute('--chemin', str(self.sources), '--chemin', str(fichier))
        self.assertEqual(resultat.returncode, 0, resultat.stdout + resultat.stderr)
        self.assertIn('1 fichier(s)', resultat.stdout)
        self.assertIn('8.4.0 cli', resultat.stdout)

    def test_deprecation_avec_code_zero(self):
        (self.sources / 'deprecated.php').write_text('DEPRECIE', encoding='utf8')
        resultat = self.execute('--chemin', str(self.sources))
        self.assertEqual(resultat.returncode, 1)
        self.assertIn('PHP Deprecated:', resultat.stdout)

    def test_erreur_syntaxe(self):
        (self.sources / 'erreur.php').write_text('SYNTAXE_INVALIDE', encoding='utf8')
        resultat = self.execute('--chemin', str(self.sources))
        self.assertEqual(resultat.returncode, 1)
        self.assertIn('Parse error', resultat.stdout)

    def test_erreur_syntaxe_avec_code_zero(self):
        (self.sources / 'erreur.php').write_text('SYNTAXE_INVALIDE CODE_ZERO', encoding='utf8')
        self.assertEqual(self.execute('--chemin', str(self.sources)).returncode, 1)

    def test_php_introuvable(self):
        self.php.unlink()
        resultat = self.execute('--chemin', str(self.sources))
        self.assertEqual(resultat.returncode, 2)
        self.assertIn('NON JOUÉ', resultat.stdout)

    def test_vide_et_chemin_absent(self):
        self.assertEqual(self.execute('--chemin', str(self.sources)).returncode, 1)
        self.assertEqual(self.execute('--chemin', str(self.sources / 'absent')).returncode, 1)

    @unittest.skipUnless(shutil.which('php'), 'PHP CLI natif absent')
    def test_garde_runtime_natif_masque_et_sortie_tamponnee(self):
        fichier = self.sources / 'runtime.php'
        fichier.write_text('<?php ob_start(); @trigger_error("obsolete", E_USER_DEPRECATED); ob_end_clean(); exit(0);', encoding='utf8')
        bootstrap = ROOT / 'tests/php/deprecations_bootstrap.php'
        resultat = subprocess.run(['php', '-d', f'auto_prepend_file={bootstrap}', str(fichier)], text=True, capture_output=True)
        self.assertEqual(resultat.returncode, 0)
        self.assertIn('❌ DÉPRÉCIATION PHP : obsolete', resultat.stderr)

    def test_pas_de_traversée_de_liens(self):
        (self.sources / 'sain.php').write_text('<?php', encoding='utf8')
        externe = self.racine / 'externe'
        externe.mkdir()
        (externe / 'danger.php').write_text('DEPRECIE', encoding='utf8')
        os.symlink(externe, self.sources / 'lien')
        self.assertEqual(self.execute('--chemin', str(self.sources)).returncode, 0)


class DeployIsolableTest(unittest.TestCase):
    def test_replacement_de_hashes_sql_isole(self):
        php = os.environ.get('MINISHOP_TEST_PHP') or shutil.which('php')
        if not php:
            self.skipTest('PHP CLI absent')
        with tempfile.TemporaryDirectory() as temporaire:
            racine = Path(temporaire)
            (racine / 'scripts').mkdir()
            (racine / 'sql').mkdir()
            shutil.copy(ROOT / 'scripts/deploy.php', racine / 'scripts/deploy.php')
            schema = racine / 'sql/01_minishop_schema.sql'
            shutil.copy(ROOT / 'sql/01_minishop_schema.sql', schema)
            resultat = subprocess.run([php, str(racine / 'scripts/deploy.php'), '--seed=fixture', '--write-sql'], capture_output=True, text=True, timeout=30)
            self.assertEqual(resultat.returncode, 0, resultat.stderr)
            self.assertIn('sql/01_minishop_schema.sql mis à jour', resultat.stdout)
            sql = schema.read_text(encoding='utf8')
            for nom, prenom in [('Nguyen', 'Alice'), ('Dupont', 'Alice'), ('Martin', 'Bruno'), ('Moretti', 'Carla')]:
                motif = r"\('" + nom + "','" + prenom + r"','[^']+','(\$2y\$[^']+)'"
                match = re.search(motif, sql)
                self.assertIsNotNone(match, f'Hash manquant pour {nom}')
                self.assertEqual(len(match.group(1)), 60)


if __name__ == '__main__':
    unittest.main()
