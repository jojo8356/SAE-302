"""Copies chiffrées et accès uniquement dans des répertoires jetables privés."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
PHP = shutil.which('php')


@unittest.skipUnless(PHP and os.name == 'posix', 'PHP natif POSIX absent')
class ArchiveRestreinteTest(unittest.TestCase):
    def setUp(self):
        check = subprocess.run([PHP, '-r', 'exit(extension_loaded("sodium") && extension_loaded("posix") ? 0 : 1);'])
        if check.returncode:
            self.skipTest('Extensions PHP sodium/posix absentes')
        self.tmp = tempfile.TemporaryDirectory(prefix='minishop-archive-')
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name)
        self.keys = self.root / 'cles'
        self.archives = self.root / 'archives'
        self.output = self.root / 'sortie'
        for path in [self.keys, self.archives, self.output]:
            path.mkdir(mode=0o700)
        self.source = self.root / 'contact.txt'
        self.secret = b'CLIENT DISTINCT alice@example.test secret message\n\x00'
        self.source.write_bytes(self.secret)
        self.call('generer-cles', '--dossier', str(self.keys))
        self.public = self.keys / 'cle-publique.txt'
        self.private = self.keys / 'cle-privee.txt'

    def call(self, *args, ok=True):
        result = subprocess.run([PHP, 'scripts/archive_restreinte.php', *args], cwd=ROOT,
                                capture_output=True, text=True, timeout=30)
        if ok:
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        else:
            self.assertNotEqual(result.returncode, 0, result.stdout + result.stderr)
        return result

    def seal(self, execute=True, **changes):
        args = ['--source', str(self.source), '--dossier', str(self.archives),
                '--cle-publique', str(self.public), '--categorie', 'contact',
                '--echeance', '2099-01-01', '--justification', 'droits']
        for option, value in changes.items():
            args[args.index('--' + option.replace('_', '-')) + 1] = str(value)
        if execute:
            args.append('--executer')
        return self.call('sceller', *args)

    def open(self, archive, private=None, output=None, ok=True):
        return self.call('consulter', '--archive', str(archive), '--cle-privee', str(private or self.private),
                         '--dossier-sortie', str(output or self.output), '--motif', 'controle', ok=ok)

    def test_simulation_chiffrement_restitution_et_trace(self):
        self.assertIn('SIMULATION', self.seal(False).stdout)
        self.assertEqual(list(self.archives.iterdir()), [])
        self.seal()
        archives = list(self.archives.glob('*.msa'))
        self.assertEqual(len(archives), 1)
        self.assertNotIn(self.secret, archives[0].read_bytes())
        self.assertNotIn(b'alice@example.test', archives[0].read_bytes())
        self.assertEqual(archives[0].stat().st_mode & 0o777, 0o600)
        self.assertEqual(self.private.stat().st_mode & 0o777, 0o600)
        self.assertEqual(self.source.read_bytes(), self.secret)
        self.open(archives[0])
        restored = list(self.output.glob('restitution-*.bin'))
        self.assertEqual(len(restored), 1)
        self.assertEqual(restored[0].read_bytes(), self.secret)
        self.assertEqual(restored[0].stat().st_mode & 0o777, 0o600)
        events = [json.loads(line) for line in (self.output / 'acces.jsonl').read_text().splitlines()]
        self.assertEqual(len(events), 1)
        self.assertEqual(events[0]['motif'], 'controle')
        self.assertNotIn('alice', json.dumps(events))
        self.open(archives[0])
        self.assertEqual(len((self.output / 'acces.jsonl').read_text().splitlines()), 2)

    def test_mauvaise_cle_fichier_corrompu_et_journal_inutilisable(self):
        self.seal()
        archive = next(self.archives.glob('*.msa'))
        other = self.root / 'autres-cles'
        other.mkdir(mode=0o700)
        self.call('generer-cles', '--dossier', str(other))
        self.open(archive, other / 'cle-privee.txt', ok=False)
        corrupted = self.archives / 'alteree.msa'
        data = bytearray(archive.read_bytes())
        data[-1] ^= 1
        corrupted.write_bytes(data)
        self.open(corrupted, ok=False)
        self.assertEqual(list(self.output.iterdir()), [])
        (self.output / 'acces.jsonl').symlink_to(self.source)
        self.open(archive, ok=False)
        self.assertEqual(self.source.read_bytes(), self.secret)
        self.assertEqual(list(self.output.glob('restitution-*')), [])

    def test_refus_permissions_liens_valeurs_et_ecrasement(self):
        self.call('generer-cles', '--dossier', str(self.keys), ok=False)
        self.assertEqual(len(list(self.keys.iterdir())), 2)
        self.archives.chmod(0o755)
        self.call('sceller', '--source', str(self.source), '--dossier', str(self.archives),
                  '--cle-publique', str(self.public), '--categorie', 'contact', '--echeance', '2099-01-01',
                  '--justification', 'droits', '--executer', ok=False)
        self.archives.chmod(0o700)
        link = self.root / 'lien'
        link.symlink_to(self.source)
        self.call('sceller', '--source', str(link), '--dossier', str(self.archives),
                  '--cle-publique', str(self.public), '--categorie', 'contact', '--echeance', '2099-01-01',
                  '--justification', 'droits', '--executer', ok=False)
        self.call('sceller', '--source', str(self.source), '--dossier', str(self.archives),
                  '--cle-publique', str(self.public), '--categorie', 'contact', '--echeance', '2020-01-01',
                  '--justification', 'droits', '--executer', ok=False)
        self.assertEqual(list(self.archives.iterdir()), [])
        self.seal()
        self.private.chmod(0o644)
        self.open(next(self.archives.glob('*.msa')), ok=False)
        self.assertEqual(list(self.output.iterdir()), [])

    def test_lectures_trop_volumineuses_refusees_et_dossier_projet_refuse(self):
        self.source.write_bytes(b'x' * (8 * 1024 * 1024 + 1))
        result = self.call('sceller', '--source', str(self.source), '--dossier', str(self.archives),
                           '--cle-publique', str(self.public), '--categorie', 'sauvegarde', '--echeance', '2099-01-01',
                           '--justification', 'restauration', '--executer', ok=False)
        self.assertIn('volumineux', result.stderr)
        self.assertFalse(list(self.archives.iterdir()))
        self.call('generer-cles', '--dossier', str(ROOT), ok=False)


if __name__ == '__main__':
    unittest.main()
