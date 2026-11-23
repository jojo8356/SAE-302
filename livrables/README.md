# Livrables — MiniShop (SAÉ 302)

Copie **versionnée** des documents rendus.
Les mêmes fichiers peuvent être régénérés dans `dist/` (répertoire généré, hors Git, cf. `.gitignore`) via `bash scripts/build-docs.sh` (les PDF obsolètes sous `livrables/pdf/` ont été supprimés).

| Format | Dossier | Usage |
| --- | --- | --- |
| HTML autonome | `livrables/html/` | lecture hors ligne, images intégrées en base64 |
| Word | `livrables/docx/` | relecture et annotations |
| Diaporama | `livrables/minishop-soutenance.pptx` | soutenance (12 diapositives, 16:9) |

## Documents

- **01-cahier-des-charges-MiniShop** — [HTML](html/01-cahier-des-charges-MiniShop.html) · [Word](docx/01-cahier-des-charges-MiniShop.docx)
- **02-document-tests-validation** — [HTML](html/02-document-tests-validation.html) · [Word](docx/02-document-tests-validation.docx)
- **03-support-soutenance** — [HTML](html/03-support-soutenance.html) · [Word](docx/03-support-soutenance.docx)
- **04-conception-bd-et-sql** — [HTML](html/04-conception-bd-et-sql.html) · [Word](docx/04-conception-bd-et-sql.docx)
- **05-wbs-projet** — [HTML](html/05-wbs-projet.html) · [Word](docx/05-wbs-projet.docx)
- **06-todolist** — [HTML](html/06-todolist.html) · [Word](docx/06-todolist.docx)
- **07-note-cybersecurite** — [HTML](html/07-note-cybersecurite.html) · [Word](docx/07-note-cybersecurite.docx)
- **08-fiche-suivi-commits** — [HTML](html/08-fiche-suivi-commits.html) · [Word](docx/08-fiche-suivi-commits.docx)
- **09-audit-ecarts** — [HTML](html/09-audit-ecarts.html) · [Word](docx/09-audit-ecarts.docx)
- **10-todo-corrections** — [HTML](html/10-todo-corrections.html) · [Word](docx/10-todo-corrections.docx)
- **11-strategie-tests** — [HTML](html/11-strategie-tests.html) · [Word](docx/11-strategie-tests.docx)
- **12-diaporama-soutenance** — [HTML](html/12-diaporama-soutenance.html) · [Word](docx/12-diaporama-soutenance.docx)
- **12-information-juridique** — [HTML](html/12-information-juridique.html) · [Word](docx/12-information-juridique.docx)
- **13-fiches-recette-uc** — [HTML](html/13-fiches-recette-uc.html) · [Word](docx/13-fiches-recette-uc.docx)
- **14-guide-lineserv** — [HTML](html/14-guide-lineserv.html) · [Word](docx/14-guide-lineserv.docx)
- **15-email-recette-lineserv** — [HTML](html/15-email-recette-lineserv.html) · [Word](docx/15-email-recette-lineserv.docx)
- **16-audit-code-mort** — [HTML](html/16-audit-code-mort.html) · [Word](docx/16-audit-code-mort.docx)
- **17-audit-global-projet** — [HTML](html/17-audit-global-projet.html) · [Word](docx/17-audit-global-projet.docx)
- **18-politique-conservation** — [HTML](html/18-politique-conservation.html) · [Word](docx/18-politique-conservation.docx)
- **19-audit-cahier-des-charges-todolist** — [HTML](html/19-audit-cahier-des-charges-todolist.html) · [Word](docx/19-audit-cahier-des-charges-todolist.docx)
- **20-charte-gitlab-revues-croisees** — [HTML](html/20-charte-gitlab-revues-croisees.html) · [Word](docx/20-charte-gitlab-revues-croisees.docx)
- **21-synthese-retours-audit** — [HTML](html/21-synthese-retours-audit.html) · [Word](docx/21-synthese-retours-audit.docx)
- **22-audit-coherence-fichiers** — [HTML](html/22-audit-coherence-fichiers.html) · [Word](docx/22-audit-coherence-fichiers.docx)
- **DA** — [HTML](html/DA.html) · [Word](docx/DA.docx)
- **README** — [HTML](html/README.html) · [Word](docx/README.docx)
- **annexe-cas-usage** — [HTML](html/annexe-cas-usage.html) · [Word](docx/annexe-cas-usage.docx)
- **annexe-enf** — [HTML](html/annexe-enf.html) · [Word](docx/annexe-enf.docx)
- **annexe-gitlab** — [HTML](html/annexe-gitlab.html) · [Word](docx/annexe-gitlab.docx)
- **annexe-php-patterns** — [HTML](html/annexe-php-patterns.html) · [Word](docx/annexe-php-patterns.docx)
- **annexe-sql** — [HTML](html/annexe-sql.html) · [Word](docx/annexe-sql.docx)
- **pv-recette** — [HTML](html/pv-recette.html) · [Word](docx/pv-recette.docx)
- **requetes-sql-a-ecrire** — [HTML](html/requetes-sql-a-ecrire.html) · [Word](docx/requetes-sql-a-ecrire.docx)

## Régénérer

```bash
bash scripts/build-docs.sh --no-livrables   # génère dans dist/
```

Dépendances : `markdown`, `fpdf2`, `python-docx`, `python-pptx` (`pip install -r requirements-docs.txt`).
