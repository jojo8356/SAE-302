# MiniShop — Documentation complète qui survit (Gouvernance, ADR, C4 et Exploitation)

**Type Diátaxis :** Guide de référence & Explication · **Propriétaires :** Johan Polsinelli & Chahd Gouiaa  
**Dernière vérification :** 2026-11-16 · **Cible :** Développeurs, Administrateurs / SRE, Architectes et Jury SAÉ 302

---

## 1. Principe : pourquoi et pour qui documenter MiniShop ?

Une documentation absente coûte cher lors d’un incident à 2 h du matin, et une documentation obsolète coûte encore plus cher parce qu’on lui fait confiance. Dans MiniShop, chaque document répond à un **lecteur précis** et vit **dans le même dépôt Git** que le code qu’il décrit, avec un propriétaire et des contrôles automatisés.

| Type de document (Diátaxis) | Question du lecteur | Fichiers MiniShop correspondants | Propriétaire |
|---|---|---|---|
| **Tutoriel** (découvrir) | *« Comment démarrer MiniShop sur mon poste ? »* | `README.md` (Démarrage rapide `./run.sh --db`), `docs/14-guide-lineserv.md` | Équipe MiniShop |
| **Guide pratique** (agir sous pression) | *« Comment rétablir MariaDB ou restaurer une sauvegarde ? »* | `docs/exploitation/runbook-incidents.md`, `docs/exploitation/runbook-sauvegarde-restauration.md`, `docs/exploitation/postmortem-modele.md` | Exploitation / SRE |
| **Référence** (vérifier un fait exact) | *« Quelles routes, tables, procédures et fiches existent ? »* | `CHANGELOG.md`, `docs/exploitation/fiche-service-minishop.md`, `docs/04-conception-bd-et-sql.md`, `docs/13-fiches-recette-uc.md`, en-têtes PHPDoc (`112/112` fichiers `app/`) | Dev / DBA |
| **Explication** (comprendre pourquoi) | *« Pourquoi SQL + JSON, pourquoi ces ADRs, comment évoluer ? »* | `ARCHITECTURE.md`, `docs/adr/0000..0005`, `docs/diagrams/src/c4_*.puml`, `docs/27-rapport-universitaire-projet-minishop.md` | Architectes |

*Tableau 1 — Cartographie complète de la documentation MiniShop selon les 4 familles Diátaxis.*

---

## 2. Niveau 1 : Couverture complète dans chacun des fichiers du dépôt

1. **Documentation dans le code source** (`112/112` PHP + `8/8` public + `8/8` SQL) :
   - Chaque classe, contrôleur, dépôt, service, script SQL et vue (`app/`, `public/`, `sql/`) porte désormais un en-tête documentaire précisant son rôle, sa route ou sa règle métier (`app/View/home/*.php` complété).
2. **Porte d’entrée et historique** (`README.md` et `CHANGELOG.md`) :
   - `README.md` oriente immédiatement le lecteur vers le lancement local, les tests et la carte des documents ; `CHANGELOG.md` trace les versions livrées (`[1.0.0]` et `[1.1.0]`).
3. **Décisions d’architecture versionnées** (`docs/adr/`, format MADR) :
   - `0000` (adopter les ADR), `0001` (double persistance `SqlStore`/`JsonStore`), `0002` (procédures stockées & déclencheurs MariaDB), `0003` (sessions strictes, RBAC et zéro dépendance CDN), `0004` (*Docs-as-Code*) et `0005` (MVC PHP 8.2+ natif sans framework lourd), accompagnés de `template.md`.
4. **Schémas C4 et UML écrits en code** (`docs/diagrams/src/`) :
   - En complément des `14` diagrammes UML historiques, deux diagrammes C4 en texte PlantUML (`c4_contexte.puml` et `c4_conteneurs.puml`) décrivent le système, ses utilisateurs et ses frontières de confiance tout en restant lisibles sans rendu graphique.
5. **Documents d’exploitation** (`docs/exploitation/`) :
   - Fiche de service, *runbook* incidents (4 scénarios critiques), *runbook* sauvegarde/restauration (`--nettoyage` + `mysqldump --routines --triggers` + `libsodium`) et modèle de *postmortem* sans blâme.

---

## 3. Niveau 2 : Chaîne de génération (Outils Java vs Chaîne Python du dépôt)

Pour survivre, la documentation est **compilée et vérifiée automatiquement** :

| Chaîne de génération | Outils utilisés | Rôle exact dans MiniShop | État / Limite d’exécution |
|---|---|---|---|
| **Chaîne Java (Diagrammes & Docs)** | **Java 17 (Temurin) + `plantuml.jar`** (`docs/diagrams/render.sh`, job CI `docs` de `.github/workflows/ci.yml`) ; écosystème Java PDF (`AsciidoctorJ PDF` / `Apache FOP`) | Transforme les sources texte `.puml` (UML + C4) en images PNG/SVG reproductibles ; dans les projets Java, `AsciidoctorJ`/`FOP` convertit aussi vers PDF. | Le dépôt est écrit en PHP/Python : `docs/diagrams/render.sh` pilote Java + `plantuml.jar` en CI GitHub. Dans un bac à sable sans JVM ni accès à Maven Central / `release-assets.githubusercontent.com`, les `.puml` restent lisibles en clair et vérifiés dans Git. |
| **Chaîne Python du dépôt (Livrables multi-formats)** | `scripts/build-docs.sh` + `scripts/md_to_pdf.py` (`fpdf2`), `md_to_html.py`, `md_to_docx.py`, `md_to_pptx.py` | Génère **hors ligne** l’ensemble des **PDF A4**, HTML autonomes (images base64), DOCX et PPTX dans `livrables/` (`README`, `CHANGELOG`, `docs/adr/`, `docs/exploitation/`, `docs/01..28`). | **Actif et exécuté localement :** produit chaque PDF en quelques secondes sans dépendance réseau. |
| **Contrôles anti-dérive (CI)** | `scripts/verifier_fiches_uc.py`, `scripts/verifier_doc_code.py`, `ruff.toml` (`select = ["D"]`), `tests/python/test_audit_structurel.py`, `tests/python/test_doc_code.py`, `tests/security/controles.sh` | Vérifie que 100 % des fichiers suivis sont inventoriés, que chaque script Python respecte PEP 257 / Google (`ruff`) et passe `doctest`, et que 100 % des méthodes publiques PHP portent un PHPDoc. | **Actif :** bloquant en local et en CI GitHub Actions. |

*Tableau 2 — Chaînes de publication et de vérification automatisée de la documentation MiniShop.*

---

## 4. Niveau 3 : Documenter le code (commentaires « pourquoi », docstrings et référence générée)

1. **Commentaires qui disent *pourquoi* (et jamais *quoi*) :**
   - Les commentaires internes expliquent les contraintes externes et les choix d’architecture (ex. `// Voir ADR-0001..0004` dans `SqlStore`, `JsonStore`, `Auth`, `Router`, `public/index.php`, `md_to_pdf.py`) sans paraphraser la syntaxe ni conserver de code mort commenté.
2. **Docstrings Python (PEP 257 + Google) & `doctest` :**
   - `ruff.toml` impose `select = ["D"]` et `convention = "google"` (`Args:`, `Returns:`, `Raises:`, `Example:` avec `r"""` dès qu’un antislash apparaît) sur `scripts/*.py` et `.tools/*.py`.
   - Les modules Python importables embarquent des exemples vérifiables exécutés par `python3 -m doctest` et par `scripts/verifier_doc_code.py`.
3. **PHPDoc complet & Référence HTML générée :**
   - Les `113` fichiers PHP (`app/` et `public/index.php`) et leurs `394` méthodes publiques sont documentés par blocs `/** ... */`.
   - Les références HTML autonomes sont générées directement depuis le code dans `livrables/reference/python/` (`pdoc --docformat google`) et `livrables/reference/php/` (`scripts/verifier_doc_code.py --gen-php-ref`), et liées depuis `README.md`.
