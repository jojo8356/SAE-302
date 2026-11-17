# MiniShop

[![CI GitHub Actions](https://github.com/jojo8356/SAE-302/actions/workflows/ci.yml/badge.svg)](https://github.com/jojo8356/SAE-302/actions/workflows/ci.yml)

> **MiniShop** est une application Web de boutique en ligne réalisée dans un cadre pédagogique : catalogue, panier, commandes et back-office. Le projet est développé en **PHP** et utilise MySQL/MariaDB via PDO comme pilote de stockage par défaut ; un mode JSON secondaire reste disponible pour les démonstrations et tests isolés.

> **Cadre** — SAE — BUT Informatique (BUT2), Université Côte d'Azur.

> **Périmètre : maquette scolaire.** Les paiements et les livraisons sont simulés. Le formulaire de contact n’envoie pas de courriel. Le projet n’est pas destiné à traiter de vraies ventes ni à être déployé tel quel en production.

## Équipe, responsabilités et accès

Le groupe de réalisation comprend deux étudiants. Les permissions du dépôt GitLab IUT sont présentées séparément des responsabilités de planification.

| Membre | Accès au dépôt GitLab IUT |
|---|---|
| **Johan Polsinelli** | `Owner` |
| **Chahd Gouiaa** | `Developer` |

La WBS et la todolist utilisent des lettres A/B/C pour les responsabilités de projet et le RACI. Aucune correspondance entre ces lettres et les personnes nommées n’est établie dans ce README. Les permissions GitLab (`Owner`, `Developer`) ne sont pas des rôles RACI. La charte [`docs/20-charte-gitlab-revues-croisees.md`](docs/20-charte-gitlab-revues-croisees.md) décrit le processus de revue attendu ; ce README n’atteste pas que des MR ou des revues croisées ont déjà eu lieu.

**Encadrement** — NGUYEN Thanh-Phuong, enseignante référente, est membre directe du projet GitLab IUT avec le rôle `Reporter` ; elle ne fait pas partie du groupe étudiant.

## Fonctionnalités

- **Catalogue public** : catégories, recherche, filtres, fiches produit et gestion du panier.
- **Espace client** : inscription, connexion, coordonnées, commandes et annulation selon leur état.
- **Back-office** : gestion des produits, catégories, stocks, commandes et paramètres de la boutique.
- **Préférences personnelles** : interface en français ou en anglais et fuseau horaire choisi manuellement ou estimé localement à la demande.
- **Interface responsive** et pages d’information adaptées au périmètre pédagogique.

Les décisions d’interface s’appuient sur l’étude de design de [`docs/DA.md`](docs/DA.md). Les contrôles d’accès par rôle, protections CSRF, requêtes préparées et politique CSP sont décrits dans le code et les documents de sécurité.

## Technologies et données

| Domaine | Technologies / choix |
|---|---|
| Application | PHP **8.2 ou plus récent**, architecture MVC, HTML, CSS et JavaScript natif |
| Stockage par défaut | MySQL **8.0.16+** ou MariaDB **10.6+**, via PDO (`pdo_mysql`) |
| Stockage secondaire explicite | Fichiers JSON sous `data/minishop/`, sans serveur de base de données |
| Base de données | Schéma relationnel, procédures stockées, déclencheurs et scripts d’initialisation dans `sql/` |
| Tests | Suites PHP et Python ; contrôles complémentaires dans GitHub Actions |

Le mode SQL est celui utilisé par défaut (`STORAGE_DRIVER=sql` dans `.env.example`) : MySQL/MariaDB, PDO et une base initialisée sont donc requis pour démarrer l’application. Pour choisir le mode JSON sans serveur, définir explicitement `STORAGE_DRIVER=json`. Les secrets et réglages propres à une machine doivent rester dans `.env`, qui n’est pas destiné à être versionné ; seul le modèle `.env.example` est suivi par Git. **Node.js et npm ne sont pas nécessaires pour lancer l’application.**

## Lancer l’application en local

### Prérequis

- PHP **8.2+** avec les extensions `json`, `mbstring` et `pdo_mysql` ;
- serveur MySQL **8.0.16+** ou MariaDB **10.6+**, base locale dédiée et accès configuré dans `.env` ;
- Bash pour `run.sh`.

Le SQL est le pilote par défaut. Pour le mode JSON secondaire, définir `STORAGE_DRIVER=json` dans `.env` ; ce mode n’exige ni `pdo_mysql` ni serveur SQL. Python 3 est requis pour lancer l’ensemble des suites PHP avec le runner présenté ci-dessous.

### Démarrage rapide

```bash
git clone https://github.com/jojo8356/SAE-302.git
cd SAE-302
cp .env.example .env
# Renseigner les accès à une base locale dédiée dans .env, puis :
./run.sh --db
./run.sh
```

`run.sh --db` installe le schéma et les données de démonstration dans la base configurée, puis le second lancement démarre le serveur PHP de développement. Si la base existe déjà avec des tables, l’installateur demande confirmation avant de la supprimer : vérifier la cible et les données avant toute validation. Pour une base déjà initialisée, configurer `.env` et lancer directement `./run.sh`. Le serveur de développement n’est pas destiné à être exposé sur Internet. Ouvrir ensuite <http://localhost:8000/>.

Pour choisir un autre port :

```bash
PORT=8080 ./run.sh
```

Pages utiles :

- Boutique : <http://localhost:8000/>
- Catalogue : <http://localhost:8000/catalogue>
- Connexion client : <http://localhost:8000/connexion>
- Connexion administration : <http://localhost:8000/admin/connexion>
- Préférences : <http://localhost:8000/preferences>

Le serveur PHP intégré est réservé au développement local. Ne pas l’exposer directement à Internet.

### Comptes de démonstration

Les comptes du seed sont réservés aux environnements locaux isolés. Les adresses, mots de passe et hashes ne sont pas reproduits dans la documentation ni dans les supports. Pour une recette LineServ, utiliser uniquement un compte de test autorisé, avec les accès transmis séparément par canal privé ; ne pas réutiliser les comptes du seed sur une instance de production ou accessible au public.

## Configuration et pilote SQL par défaut

Pour personnaliser la configuration locale, copier le modèle puis modifier `.env` :

```bash
cp .env.example .env
```

Les paramètres disponibles incluent le pilote (`STORAGE_DRIVER`), le chemin des données JSON et, pour SQL, les coordonnées de connexion (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_SOCKET`). Garder les mots de passe et autres secrets hors du dépôt.

Le fichier `.env.example` active déjà SQL. Renseigner les paramètres `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` et éventuellement `DB_SOCKET`, puis initialiser une base locale dédiée :

```bash
./run.sh --db
./run.sh
```

L’installation charge `sql/01` à `sql/04`. `./run.sh --db` peut supprimer une base existante après confirmation ; l’utiliser uniquement sur une cible locale vérifiée et dédiée. Pour activer le mode secondaire sans base, définir `STORAGE_DRIVER=json` dans `.env` ; ce mode utilise les fichiers de `data/minishop/`. Les détails du schéma et les limites des procédures sont dans [`docs/04-conception-bd-et-sql.md`](docs/04-conception-bd-et-sql.md).

## Tests

### Suites locales sans serveur SQL ni dépendances Node

Avec PHP 8.2+ et Python 3 installés, lancer les suites PHP et les contrôles documentaires sans accéder à une base SQL ni démarrer l’E2E :

```bash
php scripts/run-all-tests.php --sans-sql --sans-e2e
```

Le runner détaille les suites exécutées et signale explicitement les parties non jouées. Cette commande ne valide ni l’intégration SQL ni les parcours E2E ; ne pas les considérer comme réussis lorsqu’ils sont ignorés.

### Campagnes intégrées

Le runner complet est :

```bash
php scripts/run-all-tests.php
```

L’intégration SQL nécessite un serveur de test isolé : elle crée puis supprime une base dédiée nommée `minishop_test` par défaut, distincte de `DB_NAME`. Vérifier la configuration avant le lancement ; ne jamais utiliser une base de développement ou de production. L’E2E PHP-WASM et certains tests JavaScript ne sont exécutés que si leurs outils optionnels sont présents. Vérifier le résumé final du runner pour distinguer les tests **joués**, **non joués** et **en échec**.

Le workflow automatisé du dépôt est défini dans [`ci.yml`](.github/workflows/ci.yml). Il couvre notamment la syntaxe PHP, les suites de tests, des contrôles de sécurité, SQL sur bases jetables et des vérifications responsive en navigateur. Cette CI GitHub ne constitue pas une preuve de pipeline GitLab IUT.

Les campagnes automatisées sur VM Debian 13 (PHP 8.4 / MariaDB 11.8.6) et les mesures de performance isolées sont consignées dans [`docs/pv-recette.md`](docs/pv-recette.md) (`scripts/generer_pv.php`, `60/60` étapes) et [`tests/perf/dernieres_mesures.txt`](tests/perf/dernieres_mesures.txt) (`scripts/run_perf_vm.sh`).

### Recette manuelle

- [`docs/13-fiches-recette-uc.md`](docs/13-fiches-recette-uc.md) décrit les scénarios et leur déroulement.
- [`docs/13-suivi-recette-uc.csv`](docs/13-suivi-recette-uc.csv) sert à suivre leur exécution.
- La campagne manuelle LineServ, décrite dans [`docs/14-guide-lineserv.md`](docs/14-guide-lineserv.md), vise la base réelle prévue par le protocole ; elle n’a pas été jouée et aucune restauration n’a été effectuée. Les bases jetables de CI restent distinctes.

Un scénario ne doit être marqué comme joué qu’après son exécution réelle. Les campagnes responsives multi-largeurs sont couvertes par les tests automatisés, pas par la checklist manuelle. Le dépôt ne fournit pas de procédure automatisée de déploiement.

## Documentation du projet

| Document | Contenu |
|---|---|
| [Cahier des charges](docs/01-cahier-des-charges-MiniShop.md) | Besoin, exigences fonctionnelles et règles métier |
| [Tests et validation](docs/02-document-tests-validation.md) | Stratégie, résultats et protocole de recette |
| [Procès-verbal de recette](docs/pv-recette.md) | Campagne automatisée complète (`60/60`), décision et visas nominatifs |
| [Mesures de performances](tests/perf/dernieres_mesures.txt) | Campagne VM `ENF-01` (`run_perf_vm.sh`) : SQL et HTTP sur `minishop_perf` |
| [Architecture logicielle](ARCHITECTURE.md) | Couches, parcours HTTP, règles métier, persistance, sécurité, tests et limites |
| [Stack technique](STACK.md) | Langages, prérequis, bibliothèques et outils par contexte d’utilisation |
| [Conception de la base](docs/04-conception-bd-et-sql.md) | Modèle de données et choix SQL |
| [Stratégie de tests](docs/11-strategie-tests.md) | Périmètre et limites des tests automatisés |
| [Étude de design](docs/DA.md) | Analyse concurrentielle et direction visuelle |
| [Sécurité](docs/07-note-cybersecurite.md) | Mesures mises en place et limites connues |
| [Informations juridiques](docs/12-information-juridique.md) | Cadre légal de la maquette pédagogique |
| [Conservation des données](docs/18-politique-conservation.md) | Politique proposée et limites des outils de la maquette |

## Organisation du dépôt

```text
app/          contrôleurs, services, dépôts, sécurité et vues
public/       point d’entrée HTTP, feuilles CSS, JavaScript et visuels
data/         données du moteur JSON
docs/         cahier des charges, conception, tests et livrables documentaires
sql/          schéma, procédures, déclencheurs, données de démonstration et scripts d’initialisation
scripts/      lancement, configuration, tests et outils de développement
tests/        tests PHP, Python, JavaScript et scénarios SQL
```

## Licence et réutilisation

MiniShop est un projet pédagogique de SAE en BUT Informatique à l’IUT Nice Côte d’Azur. Le dépôt ne fournit pas de licence libre distincte ; toute réutilisation en dehors du cadre pédagogique doit être autorisée par les ayants droit.
