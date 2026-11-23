"""Contrôles automatisés de documentation du code (Python docstrings/doctest et PHPDoc)."""
import pathlib
import sys
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

from scripts import verifier_doc_code  # noqa: E402


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
