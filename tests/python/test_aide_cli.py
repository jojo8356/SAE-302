"""Vérifie la CLI dans une copie jetable, sans accès aux données du projet."""
import os
from pathlib import Path
import shutil
import socket
import time
import urllib.error
import urllib.request
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


@unittest.skipUnless(shutil.which('php'), 'PHP CLI natif absent')
class AideCliTest(unittest.TestCase):
    def test_aide_comptes_options_et_absence_de_mutation(self):
        with tempfile.TemporaryDirectory() as dossier:
            copie = Path(dossier)
            for chemin in ('scripts/aide.php', 'app/bootstrap.php', 'app/Security/xss.php', 'app/Model/Data/Seed.php'):
                source = ROOT / chemin
                destination = copie / chemin
                destination.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(source, destination)
            (copie / 'data').mkdir()
            sentinelle = copie / 'data' / 'sentinelle.json'
            sentinelle.write_text('{"intact":true}', encoding='utf-8')
            env_file = copie / '.env'
            env_file.write_text('NE_PAS_MODIFIER=oui\n', encoding='utf-8')
            avant = {str(p.relative_to(copie)): p.read_bytes() for p in copie.rglob('*') if p.is_file()}
            env = dict(os.environ, MINISHOP_ENV_FILE=str(env_file))

            def lancer(*args):
                return subprocess.run(['php', str(copie / 'scripts/aide.php'), *args], cwd=copie, env=env,
                                      text=True, capture_output=True, timeout=10)

            for args in ((), ('--help',), ('-h',)):
                aide = lancer(*args)
                self.assertEqual(aide.returncode, 0, aide.stderr)
                self.assertIn('--comptes-demo', aide.stdout)
                self.assertNotIn('alice@example.com', aide.stdout)
            comptes = lancer('--comptes-demo')
            self.assertEqual(comptes.returncode, 0, comptes.stderr)
            self.assertIn('alice@example.com', comptes.stdout)
            self.assertIn('admin@minishop.fr', comptes.stdout)
            self.assertIn('instance accessible sans protection', comptes.stdout)
            for args in (('--inconnu',), ('--help', '--comptes-demo')):
                invalide = lancer(*args)
                self.assertEqual(invalide.returncode, 2)
                self.assertIn('Usage', invalide.stderr)
                self.assertNotIn('alice@example.com', invalide.stdout + invalide.stderr)
            apres = {str(p.relative_to(copie)): p.read_bytes() for p in copie.rglob('*') if p.is_file()}
            self.assertEqual(avant, apres)


    def test_refus_sur_serveur_web(self):
        with tempfile.TemporaryDirectory() as dossier:
            copie = Path(dossier)
            (copie / 'scripts').mkdir()
            shutil.copy2(ROOT / 'scripts/aide.php', copie / 'scripts/aide.php')
            with socket.socket() as sock:
                sock.bind(('127.0.0.1', 0))
                port = sock.getsockname()[1]
            serveur = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(copie)],
                                      stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            try:
                url = f'http://127.0.0.1:{port}/scripts/aide.php?--comptes-demo'
                for tentative in range(50):
                    try:
                        with urllib.request.urlopen(url, timeout=1) as reponse:
                            self.fail(f'Accès HTTP non refusé : {reponse.status}')
                    except urllib.error.HTTPError as erreur:
                        self.assertEqual(erreur.code, 404)
                        self.assertEqual(erreur.read(), b'')
                        break
                    except urllib.error.URLError:
                        if serveur.poll() is not None or tentative == 49:
                            self.fail('Le serveur PHP de test ne démarre pas')
                        time.sleep(0.1)
            finally:
                serveur.terminate()
                serveur.wait(timeout=5)


if __name__ == '__main__':
    unittest.main()
