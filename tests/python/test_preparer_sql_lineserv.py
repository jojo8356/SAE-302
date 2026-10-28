#!/usr/bin/env python3
"""Tests locaux de la préparation SQL : ne contacte aucune base ou serveur."""
from __future__ import annotations

import os
import re
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / 'scripts/preparer_sql_lineserv.py'
SOURCE = ROOT / 'sql/01_minishop_schema.sql'


class PreparationSqlTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temp = tempfile.TemporaryDirectory(prefix='minishop-sql-tests-')
        self.root = Path(self.temp.name)
        (self.root / 'scripts').mkdir()
        (self.root / 'sql').mkdir()
        (self.root / 'temp').mkdir()
        (self.root / 'scripts/preparer_sql_lineserv.py').write_bytes(SCRIPT.read_bytes())
        self.source = self.root / 'sql/01_minishop_schema.sql'
        self.original = SOURCE.read_text(encoding='utf-8')
        self.source.write_text(self.original, encoding='utf-8')

    def tearDown(self) -> None:
        self.temp.cleanup()

    def execute(self) -> subprocess.CompletedProcess[str]:
        env = dict(os.environ, TMPDIR=str(self.root / 'temp'))
        return subprocess.run([sys.executable, str(self.root / 'scripts/preparer_sql_lineserv.py')], env=env, capture_output=True, text=True, check=False)

    def test_copie_locale_dediee_et_sans_instructions_dangereuses(self) -> None:
        resultat = self.execute()
        self.assertEqual(0, resultat.returncode, resultat.stderr)
        copies = list((self.root / 'temp').glob('minishop-lineserv-*/01_schema_pour_base_vide.sql'))
        self.assertEqual(1, len(copies))
        texte = copies[0].read_text(encoding='utf-8')
        self.assertNotRegex(texte, r'(?im)^\s*(DROP\s+(DATABASE|TABLE)|CREATE\s+DATABASE|USE\s+|SET\s+FOREIGN_KEY_CHECKS)')
        self.assertEqual(8, len(re.findall(r'^CREATE TABLE\s+', texte, re.MULTILINE)))
        self.assertEqual(3, len(re.findall(r'^CREATE OR REPLACE VIEW\s+', texte, re.MULTILINE)))
        self.assertIn('INSERT INTO produit', texte)
        self.assertIn('INSERT INTO client', texte)
        self.assertEqual(self.original, self.source.read_text(encoding='utf-8'))
        self.assertEqual(0o700, copies[0].parent.stat().st_mode & 0o777)
        self.assertEqual(0o600, copies[0].stat().st_mode & 0o777)

    def test_modification_inattendue_arrete_sans_export(self) -> None:
        self.source.write_text(self.original.replace('DROP TABLE IF EXISTS parametre;', 'DROP TABLE parametre;'), encoding='utf-8')
        resultat = self.execute()
        self.assertNotEqual(0, resultat.returncode)
        self.assertIn('Le schéma a changé', resultat.stderr)
        self.assertEqual([], list((self.root / 'temp').iterdir()))

    def test_nouvelle_instruction_destructive_arrete_sans_export(self) -> None:
        self.source.write_text(self.original + '\nDROP DATABASE autre_base;\n', encoding='utf-8')
        resultat = self.execute()
        self.assertNotEqual(0, resultat.returncode)
        self.assertIn('instruction de changement de base', resultat.stderr)
        self.assertEqual([], list((self.root / 'temp').iterdir()))

    def test_deux_exports_ne_secrasent_pas(self) -> None:
        self.assertEqual(0, self.execute().returncode)
        self.assertEqual(0, self.execute().returncode)
        self.assertEqual(2, len(list((self.root / 'temp').glob('minishop-lineserv-*/01_schema_pour_base_vide.sql'))))


if __name__ == '__main__':
    unittest.main()
