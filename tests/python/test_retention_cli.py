"""Exécution CLI et simulation sur copie JSON exclusivement temporaire."""
import datetime
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
PHP = shutil.which('php')


@unittest.skipUnless(PHP, 'PHP natif absent')
class RetentionCliTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='minishop-retention-cli-')
        self.addCleanup(self.tmp.cleanup)
        self.data = Path(self.tmp.name) / 'data'
        self.env = dict(os.environ, STORAGE_DRIVER='json', DATA_PATH=str(self.data),
                        MINISHOP_ENV_FILE=str(Path(self.tmp.name) / 'absent.env'), MINISHOP_TESTS='1')
        for key in ('DB', 'DB_NAME', 'DB_HOST', 'DB_USER', 'DB_PASSWORD', 'DB_PASS', 'DB_SOCKET'):
            self.env.pop(key, None)
        subprocess.run(
            [PHP, '-r', 'require "app/bootstrap.php"; App\\Model\\Data\\Seed::force(App\\Model\\Data\\JsonStore::open(getenv("DATA_PATH")));'],
            cwd=ROOT, env=self.env, check=True, capture_output=True, text=True, timeout=30,
        )
        path = self.data / 'client.json'
        self.clients = json.loads(path.read_text())
        now = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M:%S')
        for client in self.clients['rows']:
            client['date_creation'] = now
            client['derniere_connexion'] = now
        self.clients['rows'][0]['date_creation'] = '2020-01-01 00:00:00'
        self.clients['rows'][0]['derniere_connexion'] = '2020-01-01 00:00:00'
        path.write_text(json.dumps(self.clients))

    def run_cli(self, *args):
        return subprocess.run([PHP, 'scripts/purger_comptes.php', *args], cwd=ROOT,
                              env=self.env, capture_output=True, text=True, timeout=30)

    def test_simulation_puis_execution_et_repetition(self):
        avant = (self.data / 'client.json').read_bytes()
        simulation = self.run_cli()
        self.assertEqual(simulation.returncode, 0, simulation.stderr)
        self.assertIn('SIMULATION', simulation.stdout)
        self.assertIn('éligibles=1', simulation.stdout)
        self.assertEqual((self.data / 'client.json').read_bytes(), avant)
        execution = self.run_cli('--executer')
        self.assertEqual(execution.returncode, 0, execution.stderr)
        self.assertIn('supprimés=1', execution.stdout)
        nouvelle = json.loads((self.data / 'client.json').read_text())
        self.assertEqual(len(nouvelle['rows']), 2)
        encore = self.run_cli('--executer')
        self.assertEqual(encore.returncode, 0, encore.stderr)
        self.assertIn('supprimés=0', encore.stdout)

    def test_option_inconnue_refusee_sans_modifier_les_comptes(self):
        avant = (self.data / 'client.json').read_bytes()
        refus = self.run_cli('--option-inconnue')
        self.assertEqual(refus.returncode, 2)
        self.assertEqual((self.data / 'client.json').read_bytes(), avant)


if __name__ == '__main__':
    unittest.main()
