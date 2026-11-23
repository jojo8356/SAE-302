# Changelog — MiniShop

**Propriétaire :** Équipe MiniShop (Johan Polsinelli & Chahd Gouiaa)  
**Format :** [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) · **Versioning :** SemVer pédagogique  
**Dernière mise à jour :** 2026-11-16

---

## [1.1.0] — 2026-11-16 — Gouvernance documentaire et documentation vivante

### Ajouté
- **Gouvernance documentaire complète** dans Git selon le cadre *Documenter : créer une documentation qui survit* (`docs/28-documentation-qui-survit-minishop.md`).
- **Registre des décisions d’architecture** (`docs/adr/`) : format MADR complet (`0000-utiliser-les-adr.md` à `0005-architecture-mvc-php-natif-sans-framework.md` + `template.md`).
- **Documents d’exploitation** (`docs/exploitation/`) : fiche de service MiniShop, *runbook* incidents, *runbook* sauvegarde/restauration (`db_init.php --nettoyage` + `libsodium`), modèle complet et guide de *postmortem blameless* (5 pourquoi, actions SMART, 4 checklists, `scripts/nouveau_postmortem.py` et `.github/workflows/postmortem-template.yml`).
- **Diagrammes C4 en code** (`docs/diagrams/src/`) : `c4_contexte.puml` et `c4_conteneurs.puml` prêts pour `docs/diagrams/render.sh` (`plantuml.jar` sous Java).
- **En-têtes PHPDoc complétés** sur les `10` vues réglementaires/préférences de `app/View/home/` (`112/112` fichiers `app/**/*.php` documentés).

### Modifié
- `README.md` : ajout de la carte de gouvernance documentaire (types Diátaxis, propriétaires, ADRs, runbooks et diagrammes C4).
- `docs/26-transition-exploitation-maintenance-build-to-run.md` : validation de la ligne `Documentation & runbooks` dans la checklist *Build → Run*.
- `docs/27-rapport-universitaire-projet-minishop.md` : enrichissement des sections `4.1.12` (ordonnanceurs/Saga), `4.1.13` (domaine de production, annuaire, IdP, IAM), `5.1.5` (plateformes d’exécution & orchestration) et `6.1.1` (pipeline de journaux & observabilité).
- `scripts/build-docs.sh` : inclusion de `CHANGELOG.md`, `docs/adr/*.md` et `docs/exploitation/*.md` dans la génération HTML/PDF/DOCX.

---

## [1.0.0] — 2026-10-25 — Socle applicatif, base 3FN, sécurité et validation VM

### Ajouté
- **Architecture MVC PHP 8.2+** sans framework (`public/index.php`, `app/Router.php`, `15` contrôleurs, `7` dépôts, `app/Security/`).
- **Base de données MariaDB 3FN (`sql/01..04`) :** `9` tables, `4` vues, `18` procédures stockées, `1` fonction et `16` déclencheurs (stock, historique de statuts et audit).
- **Double persistance par contrat (`StoreInterface`) :** `SqlStore` (PDO préparé par défaut) et `JsonStore` (`data/minishop/`) avec piste d’audit `var/journal/db.jsonl`.
- **Sécurité applicative (`tests/security/controles.sh`, 11/11) :** `bcrypt`, CSRF en temps constant (`hash_equals`), protection IDOR, sessions strictes (`HttpOnly`, `SameSite=Lax`, régénération d’ID), en-têtes CSP.
- **Conformité RGPD et juridique (`app/View/home/`) :** `8` pages réglementaires et tâche CLI `scripts/db_init.php --nettoyage` (`RetentionComptes`, `NettoyageJournaux`, `NettoyageSauvegardes` + chiffrement `libsodium`).
- **Validation multi-niveaux :** `14` diagrammes UML PlantUML (`docs/diagrams/`), pyramide de tests à `9` étages (`docs/11-strategie-tests.md`), recette VM Debian 13 (`60/60`, `docs/pv-recette.md`) et campagne performance `ENF-01` (`p95 = 6,0 ms` HTTP, `tests/perf/dernieres_mesures.txt`).
