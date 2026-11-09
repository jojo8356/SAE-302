"""Test du contrôle complet dans une copie temporaire ; aucune donnée applicative."""
import json
import shutil
import subprocess
import tempfile
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]


@unittest.skipUnless(shutil.which('php'), 'PHP CLI natif absent')
class ControleCodeMortTest(unittest.TestCase):
    def test_reference_nouveaux_et_resolus(self):
        with tempfile.TemporaryDirectory() as temporaire:
            root = Path(temporaire)
            for d in ('app', 'public', 'scripts', 'tests/php', 'tests/phpunit'):
                (root / d).mkdir(parents=True, exist_ok=True)
            for f in ('code_mort.php', 'code_mort_lib.php'):
                shutil.copy(ROOT / 'scripts' / f, root / 'scripts' / f)
            cible = root / 'app' / 'exemple.php'
            cible.write_text('<?php function calcule($param) { $inutile = 3; return 1; }', encoding='utf8')
            reference = root / 'scripts' / 'code_mort_reference.json'
            reference.write_text('{"candidats": []}', encoding='utf8')

            def lancer(*args):
                return subprocess.run(['php', str(root / 'scripts/code_mort.php'), *args], cwd=root, text=True, capture_output=True, timeout=30)

            premier = lancer()
            self.assertEqual(premier.returncode, 1, premier.stdout + premier.stderr)
            self.assertIn('Candidat nouveau', premier.stdout)
            export = lancer('--json')
            self.assertEqual(export.returncode, 0)
            alertes = json.loads(export.stdout)
            self.assertTrue(any(a['nom'] == '$inutile' for a in alertes))
            self.assertTrue(any(a['nom'] == '$param' for a in alertes))
            reference.write_text(json.dumps({'candidats': [a['cle'] for a in alertes]}), encoding='utf8')
            self.assertEqual(lancer().returncode, 0)
            cible.write_text('<?php function calcule($param) { return $param; }', encoding='utf8')
            resolu = lancer()
            self.assertEqual(resolu.returncode, 1)
            self.assertIn('Candidat résolu ou modifié', resolu.stdout)


if __name__ == '__main__':
    unittest.main()
