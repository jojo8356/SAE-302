"""Deux processus PHP, un magasin jetable : contrôle des annulations simultanées."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import time
import unittest

ROOT = Path(__file__).resolve().parents[2]
WORKER = ROOT / 'tests/php/annulation_concurrente_worker.php'
PHP = shutil.which('php')


@unittest.skipUnless(PHP, 'PHP natif absent : concurrence interprocessus non jouée')
class AnnulationConcurrente(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='minishop-concurrent-')
        self.addCleanup(self.tmp.cleanup)
        self.path = Path(self.tmp.name)
        self.env = dict(os.environ, MINISHOP_CONCURRENCY_TEST='1', MINISHOP_TESTS='1')
        self.fixture = self.appel('seed')

    def appel(self, *arguments):
        proc = subprocess.run([PHP, str(WORKER), arguments[0], str(self.path), *map(str, arguments[1:])],
                              env=self.env, cwd=ROOT, capture_output=True, text=True, timeout=30, check=True)
        return json.loads(proc.stdout)

    def concurrents(self, ids, clients):
        processus = [subprocess.Popen([PHP, str(WORKER), 'cancel', str(self.path), str(identifiant), str(client), str(i)],
                                      cwd=ROOT, env=self.env, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
                     for i, (identifiant, client) in enumerate(zip(ids, clients))]
        try:
            limite = time.monotonic() + 20
            while not all((self.path / f'pret-{i}').is_file() for i in range(len(processus))):
                if time.monotonic() > limite or any(p.poll() is not None for p in processus):
                    self.fail('Les deux processus n’ont pas atteint la barrière de départ')
                time.sleep(.01)
            (self.path / 'depart').touch()
            resultats = []
            for p in processus:
                sortie, erreur = p.communicate(timeout=30)
                self.assertEqual(p.returncode, 0, erreur)
                resultats.append(json.loads(sortie))
            return resultats
        finally:
            for p in processus:
                if p.poll() is None:
                    p.kill()
                    p.communicate()

    def verifier_historique(self, etat, identifiant, attendu):
        lignes = [l for l in etat['lignes'] if int(l['id_commande']) == identifiant]
        self.assertEqual(len(lignes), 1)
        historiques = [h for h in etat['historique'] if int(h['order_id']) == identifiant and h['new_status'] == 'ANNULEE']
        self.assertEqual(len(historiques), attendu)

    def test_double_annulation_refusee_et_stock_restitue_une_seule_fois(self):
        ordre = self.fixture['commandes'][0]
        identifiant = int(ordre['id_commande'])
        client = self.fixture['client']
        resultats = self.concurrents([identifiant, identifiant], [client, client])
        self.assertCountEqual([r['resultat'] for r in resultats], ['OK', 'COMMANDE_NON_ANNULABLE'])
        etat = self.appel('etat', self.fixture['produit'])
        self.assertEqual(int(etat['stock']), self.fixture['stock_initial'] - 1)
        commande = next(c for c in etat['commandes'] if int(c['id_commande']) == identifiant)
        self.assertEqual(commande['statut'], 'ANNULEE')
        self.assertEqual(float(commande['montant_total']), 0)
        self.assertAlmostEqual(float(commande['montant_avant_annulation']), float(ordre['montant']), places=2)
        self.verifier_historique(etat, identifiant, 1)

    def test_annulations_de_deux_commandes_partageant_le_stock(self):
        ordres = self.fixture['commandes']
        ids = [int(o['id_commande']) for o in ordres]
        client = self.fixture['client']
        self.assertEqual([r['resultat'] for r in self.concurrents(ids, [client, client])], ['OK', 'OK'])
        etat = self.appel('etat', self.fixture['produit'])
        self.assertEqual(int(etat['stock']), self.fixture['stock_initial'])
        for identifiant in ids:
            self.verifier_historique(etat, identifiant, 1)
            self.assertEqual(next(c for c in etat['commandes'] if int(c['id_commande']) == identifiant)['statut'], 'ANNULEE')

    def test_verrou_libere_si_processus_interrompu(self):
        p = subprocess.Popen([PHP, str(WORKER), 'hold', str(self.path)], cwd=ROOT, env=self.env,
                             stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        try:
            limite = time.monotonic() + 20
            while not (self.path / 'verrou-pret').is_file():
                if time.monotonic() > limite or p.poll() is not None:
                    self.fail('Le processus n’a pas acquis le verrou')
                time.sleep(.01)
            p.kill()
            p.communicate(timeout=10)
            identifiant = int(self.fixture['commandes'][0]['id_commande'])
            resultats = self.concurrents([identifiant, identifiant], [self.fixture['client'], self.fixture['client']])
            self.assertCountEqual([r['resultat'] for r in resultats], ['OK', 'COMMANDE_NON_ANNULABLE'])
            self.assertEqual(int(self.appel('etat', self.fixture['produit'])['stock']), self.fixture['stock_initial'] - 1)
        finally:
            if p.poll() is None:
                p.kill()
                p.communicate()

    def test_refus_non_autorise_ne_restitue_pas_le_stock(self):
        identifiant = int(self.fixture['commandes'][0]['id_commande'])
        resultats = self.concurrents([identifiant, identifiant], [self.fixture['client'] + 10000, self.fixture['client']])
        self.assertCountEqual([r['resultat'] for r in resultats], ['OK', 'ACCES_NON_AUTORISE'])
        etat = self.appel('etat', self.fixture['produit'])
        self.assertEqual(int(etat['stock']), self.fixture['stock_initial'] - 1)
        self.verifier_historique(etat, identifiant, 1)


if __name__ == '__main__':
    unittest.main(verbosity=2)
