# ADR-0004 : Adopter une documentation dans Git (*Docs-as-Code*) compilée en multi-formats

## Statut

Accepted — Date: 2026-11-16 · Auteurs: Johan Polsinelli, Chahd Gouiaa · Reviewers: Équipe MiniShop

## Contexte et problème

Une documentation stockée sur un wiki externe ou dans des fichiers Word édités à la main dérive rapidement par rapport au code source. MiniShop exige pourtant des livrables lisibles hors ligne (HTML autonome, PDF A4, DOCX, PPTX), des diagrammes UML/C4 à jour et une référence API synchronisée avec le code.

## Decision Drivers

- Garder code source, ADR, diagrammes et documentation dans le même dépôt et la même revue Git
- Produire des livrables PDF, HTML, DOCX et PPTX reproductibles sans intervention manuelle
- Bloquer automatiquement en CI les liens cassés, oublis d’inventaire, docstrings absentes et doctests faux
- Permettre la génération écrite même sans JVM ni accès réseau

## Options considérées

### Option 1 : Rédaction dans LibreOffice / Word et schémas dessinés à la main

**Avantages** : mise en page WYSIWYG immédiate des livrables finaux.
**Inconvénients** : diffs Git illisibles sur des binaires, fusion impossible et désynchronisation fréquente des diagrammes et extraits.

### Option 2 : Chaîne 100 % Java (`AsciidoctorJ` + `Apache FOP` + `PlantUML`)

**Avantages** : écosystème éprouvé pour AsciiDoc et diagrammes PlantUML.
**Inconvénients** : exige une JVM et des artefacts Maven Central indisponibles dans les environnements isolés hors ligne.

### Option 3 : Sources Markdown + PlantUML dans Git et chaîne Python locale (`fpdf2` / `pdoc`)

**Avantages** : sources texte lisibles dans Git (`README.md`, `CHANGELOG.md`, `docs/*.md`, `docs/diagrams/src/*.puml`), compilation hors ligne par `scripts/build-docs.sh`, référence API par `pdoc` / `verifier_doc_code.py`.
**Inconvénients** : maintien d’une chaîne de conversion Python interne (`scripts/md_to_*.py`) et dépendance à Java + `plantuml.jar` en CI pour régénérer les PNG.

## Décision

**Nous adoptons l’approche *Docs-as-Code* dans Git** avec compilation multi-formats Python (`scripts/build-docs.sh`), génération de référence API (`pdoc` + `verifier_doc_code.py`) et rendu PlantUML exécuté en CI (`docs/diagrams/render.sh`).

## Conséquences

### Positives

- `100 %` des livrables Markdown sont compilables hors ligne et vérifiés automatiquement (`ruff.toml`, `verifier_doc_code.py`, `verifier_fiches_uc.py`, `test_audit_structurel.py`).

### Négatives

- Double jeu d’artefacts versionnés (sources `.md` et rendus dans `livrables/`), imposant de recompiler et mettre à jour `docs/17-inventaire-projet.csv` avant commit.
- Sans JVM locale, seuls les `.puml` texte sont vérifiés sur poste ; le rendu PNG PlantUML repose sur le job `docs` de GitHub Actions.

### Neutres

- Les formats bureautiques (`docx`, `pptx`) restent dérivés des sources Markdown sans édition manuelle directe.

## Liens

- `scripts/build-docs.sh`, `scripts/md_to_pdf.py`, `scripts/verifier_doc_code.py`, `docs/diagrams/render.sh`, `ruff.toml`, `docs/28-documentation-qui-survit-minishop.md`
