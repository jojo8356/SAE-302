# ADR-0004 — Documentation dans Git (*Docs-as-Code*) et génération multi-formats

**Statut :** Accepté · **Date :** 2026-11-16 · **Propriétaire :** Équipe MiniShop  
**Fichiers concernés :** `scripts/build-docs.sh`, `scripts/md_to_*.py`, `docs/diagrams/render.sh`, `.github/workflows/ci.yml`

---

## 1. Contexte

Une documentation stockée sur un wiki externe ou dans des fichiers bureautiques non versionnés dérive rapidement par rapport au code. Le projet exige en outre des livrables lisibles hors ligne (HTML autonome, PDF A4, DOCX, diaporama) et des diagrammes reproductibles.

## 2. Décision

1. **Source unique dans Git :** toute la documentation vit en Markdown (`README.md`, `CHANGELOG.md`, `docs/*.md`, `docs/adr/`, `docs/exploitation/`) et en code PlantUML (`docs/diagrams/src/*.puml`) dans le même dépôt que le code.
2. **Chaîne de compilation reproductible :**
   - Les diagrammes PlantUML (`docs/diagrams/src/*.puml`, incluant UML et C4) sont compilés en PNG par **`docs/diagrams/render.sh`** via **Java (`temurin` 17) + `plantuml.jar`** (job CI `docs` de `.github/workflows/ci.yml`) ;
   - Les documents Markdown sont assemblés en HTML, PDF A4, DOCX et PPTX par **`scripts/build-docs.sh`** (`markdown`, `fpdf2`, `python-docx`, `python-pptx`).
3. **Garde-fous automatisés :** `scripts/verifier_fiches_uc.py` et `tests/python/test_audit_structurel.py` bloquent toute désynchronisation d’inventaire ou de liens.

## 3. Conséquences

- **Positives :** une pull request modifiant une route, une table SQL ou une procédure met à jour code, ADR et documentation dans le même commit.
- **Contrainte d’environnement :** la régénération des PNG PlantUML exige `java` + `plantuml.jar` ; en l’absence de JVM dans un bac à sable, les sources `.puml` restent néanmoins directement lisibles en texte brut et les PDF restent générables par `scripts/build-docs.sh`.
