"""Contrôles automatisés de documentation du code (Python docstrings/doctest et PHPDoc)."""
import pathlib
import sys
import tempfile
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

from scripts import nouveau_postmortem, verifier_doc_code  # noqa: E402


class TestDocumentationCode(unittest.TestCase):
    def test_docstrings_et_doctests_python(self):
        erreurs = verifier_doc_code.verifier_python(ROOT)
        self.assertEqual(erreurs, [], '\n'.join(erreurs))

    def test_phpdoc_complet_sur_app_et_public(self):
        fichiers, erreurs = verifier_doc_code.verifier_php(ROOT)
        self.assertGreaterEqual(len(fichiers), 112)
        self.assertEqual(erreurs, [], '\n'.join(erreurs))

    def test_references_api_html_generees(self):
        py_index = ROOT / 'livrables' / 'reference' / 'python' / 'index.html'
        php_index = ROOT / 'livrables' / 'reference' / 'php' / 'index.html'
        self.assertTrue(py_index.is_file(), 'Référence HTML Python (pdoc) absente')
        self.assertTrue(php_index.is_file(), 'Référence HTML PHP absente')
        self.assertGreater(py_index.stat().st_size, 0)
        self.assertGreater(php_index.stat().st_size, 0)

    def test_adr_madr_et_numerotation(self):
        erreurs = verifier_doc_code.verifier_adr(ROOT)
        self.assertEqual(erreurs, [], '\n'.join(erreurs))

    def test_diagrammes_c4_complets_et_fideles_au_code(self):
        erreurs = verifier_doc_code.verifier_c4(ROOT)
        self.assertEqual(erreurs, [], '\n'.join(erreurs))

    def test_postmortem_blameless_et_generation(self):
        erreurs = verifier_doc_code.verifier_exploitation(ROOT)
        self.assertEqual(erreurs, [], '\n'.join(erreurs))
        with tempfile.TemporaryDirectory() as tmp:
            cible = pathlib.Path(tmp) / 'postmortem-2026-11-16-test.md'
            genere = nouveau_postmortem.generer_postmortem(
                'INC-2026-11-16-99',
                'Saisie test concurrence',
                date_iso='2026-11-16',
                sortie=cible,
            )
            texte = genere.read_text(encoding='utf-8')
            self.assertIn('INC-2026-11-16-99', texte)
            self.assertIn('Saisie test concurrence', texte)
            self.assertIn('Méthode des 5 pourquoi', texte)
