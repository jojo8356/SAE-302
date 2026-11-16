# Stack technique de MiniShop

> Inventaire établi à partir de `composer.json`, `package.json`, `package-lock.json`, `requirements-docs.txt`, des workflows CI et du code. Les outils de développement/tests et de génération documentaire ne sont pas des dépendances de l’application Web. Les versions indiquées sont les contraintes déclarées, sauf mention explicite d’une version exacte.

## 1. Résumé : ce qui est nécessaire pour exécuter l’application

| Mode | Prérequis principaux | Ce qui n’est pas nécessaire |
|---|---|---|
| **Mode standard (SQL, par défaut)** | PHP **8.2+**, extensions `json`, `mbstring` et `pdo_mysql`, serveur **MySQL 8.0.16+** ou **MariaDB 10.6+**, base et accès SQL configurés ; Bash pour `run.sh`. | Composer, Node.js, npm, Python et dépendances de génération documentaire. |
| **Mode secondaire (JSON explicite)** | PHP **8.2+**, extensions `json` et `mbstring`, système de fichiers accessible en écriture pour les données et, si utilisé, le journal ; Bash pour `run.sh` ; `STORAGE_DRIVER=json`. | Serveur SQL et PDO MySQL pour ce mode uniquement ; Node.js et outils de développement. |
| **Serveur Web de développement** | Le script `run.sh` démarre le serveur intégré de PHP et `scripts/dev-router.php`. Le script de lancement utilise Bash. | Apache n’est pas nécessaire pour ce démarrage local. Le serveur PHP intégré n’est pas une recommandation de production. |
| **Hébergement Apache** | DocumentRoot pointant vers `public/`, Apache avec `mod_rewrite` et configuration autorisant les règles de `public/.htaccess`. | Le serveur PHP intégré n’est pas utilisé derrière Apache de cette manière. La configuration réelle d’un hébergement doit être vérifiée. |

L’application peut se lancer sans installer les outils de qualité ou de test, mais le mode par défaut requiert un serveur MySQL/MariaDB opérationnel et un schéma initialisé. Les scripts SQL ne créent pas le service SGBD ; JSON reste un mode secondaire à activer explicitement.

## 2. Langages et technologies d’exécution

| Technologie | Version / exigence | Rôle |
|---|---|---|
| PHP | **8.2 minimum** (`composer.json`, `run.sh`) | Contrôleurs, règles serveur, repositories, vues PHP, sessions, accès aux données et outils CLI. Le code emploie notamment les enums et les types stricts. |
| Extensions PHP `json`, `mbstring` | Nécessaires au mode applicatif standard selon `run.sh` | JSON pour la configuration/données et opérations de chaînes Unicode telles que `mb_substr`. |
| Extension PHP `intl` | Optionnelle dans le code d’application | Services ICU (`Transliterator`, `Collator`) quand disponibles ; le code prévoit des replis si l’extension manque. Les environnements de test peuvent l’installer pour couvrir ces chemins. |
| PDO et `pdo_mysql` | Requis par le pilote SQL, utilisé par défaut | Connexion MySQL/MariaDB et requêtes préparées. Pas de connexion SQL si `STORAGE_DRIVER=json` est explicitement choisi. |
| HTML | HTML5 | Templates serveur sous `app/View/` et ressources publiques. |
| CSS | CSS natif | Design system, présentation responsive (`public/css/app.css`) et impression (`public/css/print.css`). Aucun compilateur CSS n’est requis par l’application. |
| JavaScript | JavaScript natif du navigateur | Préférences, traductions, comportements transverses et amélioration progressive du panier. Aucun framework front-end ni bundle à construire. |
| JSON | Format de données | Fichiers du pilote JSON et réponses de l’API interne du panier. |
| SQL | MySQL/MariaDB | Schéma, contraintes, vues, procédures, fonction, déclencheurs, données de démonstration et scripts d’initialisation dans `sql/`. |
| Shell Bash | Bash pour `run.sh` et plusieurs outils | Démarrage local, scripts de tests, contrôles et génération documentaire. |

### Extensions PHP : détail à connaître

- `run.sh` vérifie `json` et `mbstring` pour l’application ; avec le pilote SQL par défaut, il vérifie aussi `pdo_mysql` et l’état de la base configurée. La bibliothèque standard PHP fournit les autres fonctions utilisées.
- `pdo_mysql` et `pdo` sont requis par défaut ; ils ne sont pas nécessaires si `STORAGE_DRIVER=json` est explicitement choisi.
- `intl` n’est pas une condition générale de démarrage : `app/Model/Data/Text.php` vérifie la disponibilité des classes ICU et utilise des replis.
- `composer.json` déclare également `ext-tokenizer` dans sa section `require`. Cette extension sert surtout aux scripts d’analyse du code utilisant le tokenizer PHP ; l’application HTTP peut être amorcée par son autoloader local sans `vendor/` ni Composer.
- `mbstring` est un prérequis d’exécution vérifié par `run.sh`, mais ne figure pas dans la section `require` de Composer : il faut donc le traiter comme prérequis PHP même si Composer ne le signale pas.

## 3. Application PHP et stockage

### Dépendances d’exécution PHP

L’application n’utilise pas de framework PHP ou de dépendance Packagist chargée à chaque requête. `app/bootstrap.php` fournit un autoloader minimal pour le namespace `App\` et les helpers de sortie. Les templates sont rendus par PHP ; l’authentification utilise les sessions PHP standards (avec un adaptateur particulier dans le SAPI PHP-WASM de la sandbox).

Le stockage sélectionné par `STORAGE_DRIVER` est :

- **`sql` — valeur par défaut :** `app/Model/Data/SqlStore.php` via PDO MySQL/MariaDB. La base doit être créée et initialisée avant l’utilisation ; `scripts/db_init.php` fournit les outils d’installation. Les versions compatibles documentées par le projet sont MySQL **8.0.16+** et MariaDB **10.6+**.
- **`json` — mode secondaire explicite :** moteur local de `app/Model/Data/JsonStore.php`. Les tables sont stockées en fichiers JSON sous `data/minishop/`, ou sous le chemin configuré dans `DATA_PATH`. Activer par `STORAGE_DRIVER=json`.

La configuration passe par `.env.example` (modèle versionné), `.env` local ignoré par Git et variables d’environnement. Les clés comprennent `APP_ENV`, `APP_DEBUG`, `STORAGE_DRIVER`, `DATA_PATH`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` et `DB_SOCKET`. Une variable d’environnement réelle est prioritaire sur la valeur de `.env`. Ne jamais ajouter les secrets dans Git.

Les commandes d’initialisation SQL, les tests SQL et les scripts de réinitialisation doivent cibler une base locale jetable ou dédiée ; ne pas les lancer sur une base dont les données doivent être conservées.

## 4. Bibliothèques et outils de développement / tests

### Composer : outils PHP

Source : `composer.json`. Les contraintes sont celles déclarées dans le dépôt ; aucun `composer.lock` n’est versionné.

| Paquet | Contrainte déclarée | Usage |
|---|---:|---|
| `friendsofphp/php-cs-fixer` | `^3.64` | Vérification et correction de style PHP (`composer cs:check`, `composer cs:fix`). |
| `phpstan/phpstan` | `^1.12` | Analyse statique configurée par `phpstan.neon`. |
| `phpstan/phpstan-strict-rules` | `^1.6` | Règles PHPStan plus strictes. |
| `phpunit/phpunit` | `^11.0` | Suite au format PHPUnit, notamment `tests/phpunit/`. |
| `ext-json`, `ext-tokenizer` | `*` | Extensions déclarées dans `require` ; voir les précisions d’exécution PHP ci-dessus. |

Le code applicatif peut démarrer sans installation Composer. Composer et les paquets `require-dev` servent aux tâches de qualité et aux tests ; le dépôt comprend aussi `tests/phpunit/run-local.php` pour le contexte hors ligne prévu par le projet.

### Node.js : tests et environnement sandbox seulement

`package.json` décrit des outils privés de développement, pas un serveur applicatif Node. Il ne déclare pas de champ `engines` ; **Node 22 est la version utilisée dans les workflows CI**. `package-lock.json` est versionné pour verrouiller l’arbre npm. Aucun paquet npm n’est chargé par PHP pour servir les pages.

| Paquet | Contrainte déclarée | Usage |
|---|---:|---|
| `@php-wasm/node` | `0.1.28` | Exécuter PHP 8.2 en WebAssembly dans la sandbox/outillage de tests (`scripts/wasm_lib.mjs`). Pas nécessaire sur un serveur PHP natif. |
| `jsdom` | `^31.0.0` | Environnement DOM simulé pour les tests JavaScript et les scénarios d’interface. |
| `playwright` | `^1.56.1` | Pilotage de Chromium pour les tests navigateur réels, notamment responsive. Chromium et ses dépendances système sont requis pour cette campagne. |

Les tests JavaScript/Chromium et les outils sandbox sont optionnels pour lancer MiniShop. Leur présence dans `package.json` ne signifie pas que Node ou npm sont requis sur une machine servant l’application.

### Python : tests CLI et génération documentaire

Python **3.10+** est indiqué dans `requirements-docs.txt` pour les outils de génération. Les tests de `tests/python/` utilisent principalement la bibliothèque standard Python ; les paquets ci-dessous servent à produire les formats documentaires et ne font pas partie de l’application Web.

| Paquet Python | Contrainte | Usage |
|---|---:|---|
| `markdown` | `>=3.5` | Conversion Markdown vers HTML. |
| `fpdf2` | `>=2.7` | Production de PDF. |
| `python-docx` | `>=1.1` | Production de documents Word. |
| `python-pptx` | `>=0.6.23` | Production de présentations PowerPoint. |

`bash scripts/build-docs.sh` utilise ces dépendances pour les rendus HTML, PDF, DOCX et diaporama. Il n’est pas requis pour consulter ou exécuter l’application.

## 5. CI, SQL et outils de génération

| Outil / environnement | Version configurée dans le dépôt | Rôle et périmètre |
|---|---|---|
| GitHub Actions | `.github/workflows/ci.yml` | Automatisation des contrôles et tests du dépôt GitHub. Certains jobs utilisent PHP 8.2/8.5, Node 22, MySQL 8.0 ou MariaDB 11.8 selon la campagne. |
| Collaboration GitLab | Dépôt GitLab IUT (issues et MR) | Aucune configuration CI GitLab dans le périmètre courant ; l’intégration continue GitHub est décrite à la ligne précédente. |
| Client MySQL/MariaDB | Client `mysql` ou `mariadb` selon le script | Nécessaire à certains tests SQL et scripts Shell de test ; pas au fonctionnement JSON normal. Les tests d’intégration SQL demandent aussi le serveur et `pdo_mysql`. |
| PlantUML | JAR **1.2024.0** ; Java **17** dans la CI documentaire | Régénérer les diagrammes PNG depuis `docs/diagrams/src/*.puml`. Les images PNG sont déjà versionnées ; Java/PlantUML ne sont pas requis par l’application. |
| Git | Version fournie par l’environnement | Historique du code, certains contrôles structurels et opérations de dépôt. |

Un workflow CI décrit une procédure, pas son résultat : vérifier le statut et les journaux du pipeline du commit concerné avant d’affirmer qu’une suite a passé.

## 6. Actifs locaux inclus dans le front-end

- `public/css/app.css` et `public/css/print.css` : feuilles CSS livrées avec le dépôt.
- `public/js/panier.js`, `ui.js`, `i18n-en.js` et `preferences.js` : scripts locaux, sans framework.
- `public/js/tz-lookup.js` : code local de conversion coordonnées → fuseau horaire, utilisé après une demande explicite de géolocalisation. Sa version n’est pas indiquée dans `package.json` ou un autre manifeste de dépendances ; elle est servie localement et n’exige pas de compte auprès d’un service externe.
- `public/fonts/` et `public/img/` : ressources servies localement. Les icônes principales sont produites en SVG inline par les helpers PHP plutôt que chargées depuis un paquet d’icônes.

Aucun bundler front-end, CDN, framework JavaScript ou service de géolocalisation n’est requis pour le rendu courant.

## 7. Synthèse des dépendances par contexte

- **Pour le mode standard :** PHP 8.2+, `json`, `mbstring`, `pdo_mysql`, un serveur MySQL/MariaDB compatible et une base configurée/préparée ; démarrer avec `./run.sh`.
- **Pour le mode secondaire JSON :** ajouter `STORAGE_DRIVER=json` à `.env` ; le serveur SQL et `pdo_mysql` ne sont alors pas utilisés.
- **Pour les suites PHP natives :** PHP natif, Python 3 pour le runner global ; PHPUnit/Composer sont requis seulement pour les commandes qui utilisent le vrai PHPUnit. La suite SQL a ses prérequis distincts.
- **Pour les tests JavaScript/navigateur :** Node 22 dans la CI, les paquets verrouillés par `package-lock.json` et Chromium pour Playwright. Ce contexte est facultatif.
- **Pour régénérer les documents :** Python 3.10+ et les paquets listés dans `requirements-docs.txt`; Java 17 et PlantUML seulement pour recalculer les diagrammes.
