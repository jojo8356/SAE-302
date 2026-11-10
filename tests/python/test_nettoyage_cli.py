"""CLI et concurrence uniquement sur fichiers et dossiers temporaires."""
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
PHP = shutil.which('php')


@unittest.skipUnless(PHP and os.name == 'posix', 'PHP natif POSIX absent')
class NettoyageCliTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='minishop-cleanup-')
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name)
        self.backups = self.root / 'backups'
        self.backups.mkdir(mode=0o700)
        (self.backups / '.minishop-sauvegardes').write_text('MINISHOP_BACKUPS_V1\n')
        self.files = []
        for i in (2020, 2021, 2022):
            path = self.backups / f'minishop-{i}0101T000000Z-{"a" * 32}.backup'
            path.write_text('fixture sauvegarde')
            os.utime(path, (1577836800, 1577836800))
            self.files.append(path)
        self.env = dict(os.environ, NETTOYAGE_SAUVEGARDES=str(self.backups),
                        MINISHOP_ENV_FILE=str(self.root / 'absent.env'))

    def run_cli(self, *args, ok=True):
        r = subprocess.run([PHP, 'scripts/db_init.php', '--nettoyage', *args], cwd=ROOT,
                           env=self.env, capture_output=True, text=True, timeout=20)
        if ok:
            self.assertEqual(r.returncode, 0, r.stdout + r.stderr)
        else:
            self.assertNotEqual(r.returncode, 0, r.stdout + r.stderr)
        return r

    def test_simulation_puis_validation_separee_et_conservation(self):
        self.assertIn('eligible=1', self.run_cli('sauvegardes').stdout)
        self.assertTrue(all(p.exists() for p in self.files))
        self.run_cli('sauvegardes', '--executer', ok=False)
        self.run_cli('sauvegardes', '--validation-absence-gel', ok=False)
        self.run_cli('sauvegardes', '--yes', ok=False)
        self.assertTrue(all(p.exists() for p in self.files))
        self.assertIn('supprime=1', self.run_cli('sauvegardes', '--executer', '--validation-absence-gel').stdout)
        self.assertFalse(self.files[0].exists())
        self.assertTrue(all(p.exists() for p in self.files[1:]))
        self.assertIn('supprime=0', self.run_cli('sauvegardes', '--executer', '--validation-absence-gel').stdout)

    def test_marqueur_et_noms_fichiers_stricts(self):
        (self.backups / '.minishop-sauvegardes').unlink()
        self.run_cli('sauvegardes', '--executer', '--validation-absence-gel', ok=False)
        self.assertTrue(all(p.exists() for p in self.files))
        self.run_cli('--help')
        self.run_cli('contact', '--switch', ok=False)
        self.run_cli('comptes', '--check', ok=False)

    def test_ecritures_concurrentes_et_rotation(self):
        if subprocess.run([PHP, '-r', 'exit(extension_loaded("posix") ? 0 : 1);']).returncode:
            self.skipTest('Extension posix absente')
        journal = self.root / 'contact.jsonl'
        journal.write_text('{"date":"2020-01-01T00:00:00+00:00"}\n')
        bootstrap = str(ROOT / 'app/bootstrap.php')
        writer = r'''require $argv[1]; for ($i=0; $i<120; ++$i) {
          if (!\App\Service\NettoyageJournaux::ajouter($argv[2], "{\"date\":\"2026-09-01T00:00:00+00:00\",\"i\":".$i."}\n")) exit(2);
        }'''
        cleaner = r'''require $argv[1]; for ($i=0; $i<20; ++$i) {
          \App\Service\NettoyageJournaux::traiter($argv[2], 'date', 12, new DateTimeImmutable('2026-10-15T12:00:00+00:00'), true);
        }'''
        processes = [subprocess.Popen([PHP, '-r', code, bootstrap, str(journal)], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                     for code in (writer, cleaner)]
        for process in processes:
            stdout, stderr = process.communicate(timeout=30)
            self.assertEqual(process.returncode, 0, (stdout + stderr).decode())
        import json
        lines = [json.loads(line) for line in journal.read_text().splitlines()]
        self.assertEqual(sorted(x['i'] for x in lines), list(range(120)))


if __name__ == '__main__':
    unittest.main()
