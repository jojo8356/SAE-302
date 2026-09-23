# .tools — chaîne de génération des documents (fichiers cachés)

Scripts internes du projet : ils **génèrent** les documents officiels (`docs/*.md`) et les
artefacts rendus (`artefacts/*.html`) à partir d'une seule source de données. Ne pas livrer
au dépôt GitLab du rendu (hors périmètre du barème) — d'où le dossier caché `.tools/`.

| Script | Génère | Source |
|---|---|---|
| `build_doc04.py` | `docs/04-conception-bd-et-sql.md` | `docs/01-cahier-des-charges-MiniShop.md` (§12) + `docs/annexes/annexe-sql.md` (titres d'annexes) + inventaire de `sql/`, `tests/` |
| `build_doc04_html.py` | `artefacts/04-conception-bd-et-sql.html` | `docs/04-conception-bd-et-sql.md` (images MCD/MLD intégrées en base64) |
| `build_wbs.py` | `docs/05-wbs-projet.md` + `artefacts/05-wbs-projet.html` | données WBS embarquées (6 divisions, 16 lots, 86 tâches, assertions bottom-up 360 h) |
| `build_todolist.py` | `docs/06-todolist.md` + `artefacts/06-todolist.html` | statuts réels des 86 tâches (fait/partiel/à faire) + classification asynchrone vs dépendante ; assertions : 162 h faites / 198 h restantes / 360 h total |

## Régénération complète

```bash
python3 .tools/build_doc04.py        # doc de conception (.md officiel)
python3 .tools/build_doc04_html.py   # artefact HTML autoportant
python3 .tools/build_wbs.py          # WBS (.md officiel + artefact HTML)
```

Dépendance : `pip install markdown` (les assertions de cohérence échouent volontairement
si les totaux ne remontent plus exactement — garde-fou anti-dérive des charges).

## Ce qui reste dans `uploads/`

Archive des fichiers d'origine téléversés : les 4 scripts SQL `.txt` **avant correction**
(les versions livrées et validées sont `sql/*.sql`) et les 12 brouillons `test_global_*.png`
(la version finale du diagramme global est `docs/diagrams/cas_utilisation_global.png`).
