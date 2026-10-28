"""Contrat Git des secrets : aucune écriture dans le dépôt de l'utilisateur."""
import pathlib
import shutil
import subprocess
import tempfile
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]


class IgnoreSecretsTest(unittest.TestCase):
    def test_configurations_et_cles_ignorees_sauf_modele_racine(self):
        with tempfile.TemporaryDirectory() as tmp:
            repo = pathlib.Path(tmp)
            subprocess.run(['git', 'init', '-q', str(repo)], check=True)
            (repo / '.gitignore').write_bytes((ROOT / '.gitignore').read_bytes())
            for path in ['.env', '.env.local', '.env.production', 'app/.env.development',
                         'app/.env.example', 'secret.pem', 'secret.key', 'cert.p12',
                         'cert.pfx', 'id_rsa', 'id_ed25519']:
                with self.subTest(path=path):
                    proc = subprocess.run(['git', 'check-ignore', '--no-index', '-q', path], cwd=repo)
                    self.assertEqual(proc.returncode, 0, path)
            proc = subprocess.run(['git', 'check-ignore', '--no-index', '-q', '.env.example'], cwd=repo)
            self.assertEqual(proc.returncode, 1)


    def test_controle_securite_dans_un_worktree_git(self):
        with tempfile.TemporaryDirectory() as tmp:
            repo = pathlib.Path(tmp) / 'repo'
            repo.mkdir()
            for directory in ['app', 'public', 'scripts', 'tests/security']:
                shutil.copytree(ROOT / directory, repo / directory, dirs_exist_ok=True)
            for name in ['.gitignore', '.env.example']:
                shutil.copy2(ROOT / name, repo / name)
            def git(*args):
                return subprocess.run(['git', '-C', str(repo), *args], check=True, capture_output=True)
            git('init', '-q')
            git('config', 'user.email', 'test@example.invalid')
            git('config', 'user.name', 'Test temporaire')
            git('add', '.')
            git('commit', '-qm', 'fixture')
            worktree = pathlib.Path(tmp) / 'worktree'
            git('worktree', 'add', '--detach', str(worktree))
            self.assertTrue((worktree / '.git').is_file())
            result = subprocess.run(['bash', 'tests/security/controles.sh'], cwd=worktree,
                                    capture_output=True, text=True)
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
            self.assertIn('10 contrôles conformes', result.stdout)


if __name__ == '__main__':
    unittest.main()
