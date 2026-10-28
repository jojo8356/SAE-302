"""Contrôles de structure du dépôt versionné, sans exécution de code applicatif."""
import csv
import json
import pathlib
import shutil
import subprocess
import tokenize
import unittest
import xml.etree.ElementTree as ET
import zipfile

ROOT = pathlib.Path(__file__).resolve().parents[2]


def files():
    result = subprocess.check_output(['git', 'ls-files', '-z'], cwd=ROOT)
    return [ROOT / p.decode('utf-8') for p in result.split(b'\0') if p]


class AuditStructurel(unittest.TestCase):
    def test_fichiers_presents_et_non_vides(self):
        for path in files():
            with self.subTest(fichier=str(path.relative_to(ROOT))):
                self.assertTrue(path.is_file())
                self.assertGreater(path.stat().st_size, 0)

    def test_inventaire_global_a_jour(self):
        inventory = ROOT / 'docs/17-inventaire-projet.csv'
        with inventory.open(encoding='utf-8', newline='') as source:
            rows = list(csv.DictReader(source))
        listed = [row['chemin'] for row in rows]
        tracked = {str(path.relative_to(ROOT)) for path in files()}
        self.assertEqual(len(listed), len(set(listed)), 'Doublons dans l’inventaire')
        self.assertEqual(set(listed), tracked, 'Actualiser l’inventaire global')
        self.assertTrue(all(row['categorie'] and row['controle_structurel'] for row in rows))

    def test_python_compilable_sans_import(self):
        for path in files():
            if path.suffix != '.py':
                continue
            with self.subTest(fichier=str(path.relative_to(ROOT))):
                with tokenize.open(path) as source:
                    compile(source.read(), str(path), 'exec')

    def test_json_et_svg_parsables(self):
        for path in files():
            if path.suffix.lower() not in {'.json', '.svg'}:
                continue
            with self.subTest(fichier=str(path.relative_to(ROOT))):
                if path.suffix.lower() == '.json':
                    json.loads(path.read_text(encoding='utf-8'))
                else:
                    ET.parse(path)

    def test_archives_bureautiques_integrite(self):
        for path in files():
            if path.suffix.lower() not in {'.docx', '.xlsx', '.pptx', '.odt', '.zip'}:
                continue
            with self.subTest(fichier=str(path.relative_to(ROOT))):
                with zipfile.ZipFile(path) as archive:
                    self.assertIsNone(archive.testzip())

    @unittest.skipUnless(shutil.which('bash'), 'bash absent')
    def test_syntaxe_shell(self):
        for path in files():
            if path.suffix != '.sh':
                continue
            with self.subTest(fichier=str(path.relative_to(ROOT))):
                result = subprocess.run(['bash', '-n', str(path)], capture_output=True, text=True)
                self.assertEqual(result.returncode, 0, result.stderr)

    @unittest.skipUnless(shutil.which('node'), 'Node absent : syntaxe JS non vérifiée')
    def test_syntaxe_javascript(self):
        for path in files():
            if path.suffix not in {'.js', '.mjs', '.cjs'}:
                continue
            with self.subTest(fichier=str(path.relative_to(ROOT))):
                result = subprocess.run(['node', '--check', str(path)], capture_output=True, text=True)
                self.assertEqual(result.returncode, 0, result.stderr)


if __name__ == '__main__':
    unittest.main()
