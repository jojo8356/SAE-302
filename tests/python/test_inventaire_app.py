"""Vérifie que l'inventaire couvre les fichiers applicatifs versionnés, sans les modifier."""
from pathlib import Path
import re
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[2]
DOC = ROOT / 'docs/16-audit-code-mort.md'


def lignes_inventaire(texte):
    return re.findall(r'^\| `((?:app|public)/[^`]+)` \| [^|]+ \| [^|]+ \| (.+) \|$', texte, re.MULTILINE)


class InventaireTest(unittest.TestCase):
    def test_lecture_du_tableau(self):
        texte = '| `app/exemple.php` | 10 | 0 | Une description. |\n'
        self.assertEqual(lignes_inventaire(texte), [('app/exemple.php', 'Une description.')])

    def test_tous_les_fichiers_versionnes_et_non_vides(self):
        sortie = subprocess.run(['git', 'ls-files', '-z', '--', 'app', 'public'], cwd=ROOT, check=True, capture_output=True)
        suivis = set(sortie.stdout.decode('utf-8').split('\0')) - {''}
        lignes = lignes_inventaire(DOC.read_text(encoding='utf8'))
        chemins = [fichier for fichier, _ in lignes]
        self.assertEqual(len(chemins), len(set(chemins)), 'Doublon dans l’inventaire')
        self.assertEqual(set(chemins), suivis, 'Actualiser l’inventaire si des fichiers versionnés ont été ajoutés ou retirés')
        for fichier, role in lignes:
            self.assertTrue(role.strip(), f'Rôle manquant : {fichier}')
            cible = ROOT / fichier
            self.assertTrue(cible.is_file(), f'Fichier manquant : {fichier}')
            self.assertGreater(cible.stat().st_size, 0, f'Fichier vide à examiner : {fichier}')


if __name__ == '__main__':
    unittest.main()
