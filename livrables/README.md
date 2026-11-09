# Livrables — MiniShop (SAÉ 302)

Copie **versionnée** des documents rendus, régénérée par `bash scripts/build-docs.sh`.
Les mêmes fichiers existent dans `dist/` (répertoire généré, hors Git, cf. `.gitignore`).

| Format | Dossier | Usage |
| --- | --- | --- |
| HTML autonome | `livrables/html/` | lecture hors ligne, images intégrées en base64 |
| PDF A4 | `livrables/pdf/` | dépôt et impression |
| Word | `livrables/docx/` | relecture et annotations |
| Diaporama | `livrables/minishop-soutenance.pptx` | soutenance (12 diapositives, 16:9) |

## Documents

- **01-cahier-des-charges-MiniShop** — [HTML](html/01-cahier-des-charges-MiniShop.html) · [PDF](pdf/01-cahier-des-charges-MiniShop.pdf) · [Word](docx/01-cahier-des-charges-MiniShop.docx)
- **02-document-tests-validation** — [HTML](html/02-document-tests-validation.html) · [PDF](pdf/02-document-tests-validation.pdf) · [Word](docx/02-document-tests-validation.docx)
- **03-support-soutenance** — [HTML](html/03-support-soutenance.html) · [PDF](pdf/03-support-soutenance.pdf) · [Word](docx/03-support-soutenance.docx)
- **04-conception-bd-et-sql** — [HTML](html/04-conception-bd-et-sql.html) · [PDF](pdf/04-conception-bd-et-sql.pdf) · [Word](docx/04-conception-bd-et-sql.docx)
- **05-wbs-projet** — [HTML](html/05-wbs-projet.html) · [PDF](pdf/05-wbs-projet.pdf) · [Word](docx/05-wbs-projet.docx)
- **06-todolist** — [HTML](html/06-todolist.html) · [PDF](pdf/06-todolist.pdf) · [Word](docx/06-todolist.docx)
- **07-note-cybersecurite** — [HTML](html/07-note-cybersecurite.html) · [PDF](pdf/07-note-cybersecurite.pdf) · [Word](docx/07-note-cybersecurite.docx)
- **08-fiche-suivi-commits** — [HTML](html/08-fiche-suivi-commits.html) · [PDF](pdf/08-fiche-suivi-commits.pdf) · [Word](docx/08-fiche-suivi-commits.docx)
- **09-audit-ecarts** — [HTML](html/09-audit-ecarts.html) · [PDF](pdf/09-audit-ecarts.pdf) · [Word](docx/09-audit-ecarts.docx)
- **10-todo-corrections** — [HTML](html/10-todo-corrections.html) · [PDF](pdf/10-todo-corrections.pdf) · [Word](docx/10-todo-corrections.docx)
- **12-diaporama-soutenance** — [HTML](html/12-diaporama-soutenance.html) · [PDF](pdf/12-diaporama-soutenance.pdf) · [Word](docx/12-diaporama-soutenance.docx)
- **DA** — [HTML](html/DA.html) · [PDF](pdf/DA.pdf) · [Word](docx/DA.docx)
- **README** — [HTML](html/README.html) · [PDF](pdf/README.pdf) · [Word](docx/README.docx)
- **annexe-cas-usage** — [HTML](html/annexe-cas-usage.html) · [PDF](pdf/annexe-cas-usage.pdf) · [Word](docx/annexe-cas-usage.docx)
- **annexe-enf** — [HTML](html/annexe-enf.html) · [PDF](pdf/annexe-enf.pdf) · [Word](docx/annexe-enf.docx)
- **annexe-gitlab** — [HTML](html/annexe-gitlab.html) · [PDF](pdf/annexe-gitlab.pdf) · [Word](docx/annexe-gitlab.docx)
- **annexe-php-patterns** — [HTML](html/annexe-php-patterns.html) · [PDF](pdf/annexe-php-patterns.pdf) · [Word](docx/annexe-php-patterns.docx)
- **annexe-sql** — [HTML](html/annexe-sql.html) · [PDF](pdf/annexe-sql.pdf) · [Word](docx/annexe-sql.docx)
- **requetes-sql-a-ecrire** — [HTML](html/requetes-sql-a-ecrire.html) · [PDF](pdf/requetes-sql-a-ecrire.pdf) · [Word](docx/requetes-sql-a-ecrire.docx)

## Régénérer

```bash
bash scripts/build-docs.sh              # tout (HTML + PDF + DOCX + diaporama)
bash scripts/build-docs.sh --only pdf   # un seul format
```

Dépendances : `markdown`, `fpdf2`, `python-docx`, `python-pptx` (`pip install -r requirements-docs.txt`).
