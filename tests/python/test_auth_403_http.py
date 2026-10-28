"""Contrats HTTP réels : copie et serveur PHP temporaires, aucun accès au SQL."""
import hashlib
import http.cookiejar
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
PHP = shutil.which('php')


class SansRedirection(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


@unittest.skipUnless(PHP, 'PHP CLI absent : tests HTTP natifs non joués')
class Acces403Http(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tmp = tempfile.TemporaryDirectory(prefix='minishop-auth403-')
        cls.addClassCleanup(cls.tmp.cleanup)
        cls.root = Path(cls.tmp.name)
        for directory in ('app', 'public'):
            shutil.copytree(ROOT / directory, cls.root / directory)
        (cls.root / 'scripts').mkdir()
        for name in ('seed.php', 'dev-router.php'):
            shutil.copy2(ROOT / 'scripts' / name, cls.root / 'scripts' / name)
        (cls.root / 'sessions').mkdir()
        # Ne pas importer de .env, DATA_PATH, variables SQL ou configuration hôte.
        cls.env = {'PATH': os.environ.get('PATH', ''), 'HOME': str(cls.root),
                   'TMPDIR': str(cls.root), 'MINISHOP_TESTS': '1', 'STORAGE_DRIVER': 'json'}
        subprocess.run([PHP, 'scripts/seed.php'], cwd=cls.root, env=cls.env,
                       check=True, capture_output=True, text=True, timeout=60)
        fixture = r'''require 'app/bootstrap.php';
$store = \App\Model\Data\JsonStore::open(MINISHOP_ROOT . '/data/minishop');
$store->insert('administrateur', ['nom' => 'Test', 'prenom' => 'Gestionnaire',
  'email' => 'gestionnaire@example.test', 'mot_de_passe_hash' => password_hash('Test403!x', PASSWORD_BCRYPT),
  'role' => 'GESTIONNAIRE', 'actif' => true]);'''
        subprocess.run([PHP, '-r', fixture], cwd=cls.root, env=cls.env,
                       check=True, capture_output=True, text=True, timeout=30)
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        cls.url = f'http://127.0.0.1:{port}'
        cls.log = (cls.root / 'server.log').open('w+')
        cls.addClassCleanup(cls.log.close)
        cls.proc = subprocess.Popen([PHP, '-d', f'session.save_path={cls.root / "sessions"}',
                                     '-S', f'127.0.0.1:{port}', '-t', 'public', 'scripts/dev-router.php'],
                                    cwd=cls.root, env=cls.env, stdout=cls.log, stderr=cls.log)
        cls.addClassCleanup(cls.arreter_serveur)
        deadline = time.monotonic() + 15
        while time.monotonic() < deadline:
            if cls.proc.poll() is not None:
                raise RuntimeError('Le serveur PHP temporaire a quitté prématurément')
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=0.3):
                    break
            except OSError:
                time.sleep(0.05)
        else:
            raise RuntimeError('Serveur PHP temporaire non disponible')

    @classmethod
    def arreter_serveur(cls):
        if cls.proc.poll() is None:
            cls.proc.terminate()
            try:
                cls.proc.wait(timeout=5)
            except subprocess.TimeoutExpired:
                cls.proc.kill()
                cls.proc.wait(timeout=5)

    def navigateur(self):
        return urllib.request.build_opener(SansRedirection(), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def requete(self, browser, route, data=None):
        payload = None if data is None else urllib.parse.urlencode(data).encode()
        try:
            response = browser.open(self.url + route, data=payload, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            return response.status, response.headers, response.read().decode('utf-8', errors='replace')

    def connexion(self, email, mot_de_passe):
        browser = self.navigateur()
        status, _, page = self.requete(browser, '/admin/connexion')
        self.assertEqual(status, 200)
        match = re.search(r'name="_csrf" value="([a-f0-9]{64})"', page)
        self.assertIsNotNone(match)
        status, headers, _ = self.requete(browser, '/admin/connexion',
                                          {'_csrf': match.group(1), 'email': email, 'mot_de_passe': mot_de_passe})
        self.assertEqual(status, 302)
        self.assertEqual(headers.get('Location'), '/admin')
        return browser

    def test_roles_et_methodes_http(self):
        visiteur = self.navigateur()
        for data in (None, {'nom': 'test'}):
            status, headers, _ = self.requete(visiteur, '/admin/equipe', data)
            self.assertEqual(status, 302)
            self.assertEqual(headers.get('Location'), '/admin/connexion')

        gestionnaire = self.connexion('gestionnaire@example.test', 'Test403!x')
        status, headers, page = self.requete(gestionnaire, '/admin/equipe')
        self.assertEqual(status, 403)
        self.assertIsNone(headers.get('Location'))
        self.assertIn('<h1>Accès refusé</h1>', page)
        self.assertIn('href="/admin"', page)
        status, _, page = self.requete(gestionnaire, '/admin/produits')
        self.assertEqual(status, 200)
        jeton = re.search(r'name="_csrf" value="([a-f0-9]{64})"', page)
        self.assertIsNotNone(jeton)
        fichier = self.root / 'data/minishop/administrateur.json'
        avant = hashlib.sha256(fichier.read_bytes()).hexdigest()
        form = {'_csrf': jeton.group(1), 'nom': 'X', 'prenom': 'Y',
                'email': 'interdit@example.test', 'mot_de_passe': 'Test403!x', 'role': 'SUPER'}
        for route in ('/admin/equipe', '/admin/equipe/1/basculer'):
            status, headers, page = self.requete(gestionnaire, route, form)
            self.assertEqual(status, 403)
            self.assertIsNone(headers.get('Location'))
            self.assertIn('<h1>Accès refusé</h1>', page)
        self.assertEqual(hashlib.sha256(fichier.read_bytes()).hexdigest(), avant)

        super_admin = self.connexion('admin@minishop.fr', 'Admin2026!')
        status, _, page = self.requete(super_admin, '/admin/equipe')
        self.assertEqual(status, 200)
        self.assertNotIn('<h1>Accès refusé</h1>', page)


if __name__ == '__main__':
    unittest.main()
