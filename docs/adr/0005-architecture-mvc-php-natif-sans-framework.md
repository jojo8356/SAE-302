# ADR-0005 : Construire une architecture MVC en PHP 8.2+ natif sans framework lourd

## Statut

Accepted — Date: 2026-09-12 · Auteurs: Johan Polsinelli, Chahd Gouiaa · Reviewers: Équipe MiniShop

## Contexte et problème

Au démarrage de la SAÉ 302, l’équipe devait choisir le socle applicatif PHP pour bâtir une boutique complète (catalogue, panier, commandes, back-office RBAC, double persistance SQL/JSON). Ce choix conditionne la maîtrise de la sécurité, le déploiement sur l’hébergement pédagogique LineServ et l’exécution sous PHP-WASM pour les tests E2E.

## Decision Drivers

- Maîtriser intégralement chaque couche HTTP, routage, session, CSRF et SQL sans boîte noire (objectif BUT2)
- Supprimer toute dépendance runtime tierce (`vendor/` absent en production)
- Assurer l’exécution directe sur PHP `8.2+` standard, LineServ et PHP-WASM avec faible latence

## Options considérées

### Option 1 : Framework complet (Symfony ou Laravel)

**Avantages** : outillage riche intégré (routeur, formulaires, sécurité, CLI, migrations) et conventions standards.
**Inconvénients** : masque PDO, les sessions et la mécanique CSRF derrière des abstractions lourdes, embarque des centaines de paquets `vendor/` et complique PHP-WASM / LineServ.

### Option 2 : Scripts PHP procéduraux par page (`catalogue.php`, `panier.php`)

**Avantages** : démarrage immédiat sans routeur ni autoloader.
**Inconvénients** : duplique les contrôles d’accès, CSRF et en-têtes HTTP sur chaque fichier et couple HTML, métier et SQL.

### Option 3 : Architecture MVC légère en PHP 8.2+ natif avec contrôleur frontal unique

**Avantages** : point d’entrée unique (`public/index.php`), routeur explicite (`App\Router`), séparation `Controller`/`Repository`/`Model`/`Security`/`Service` sous `app/`, autoloader PSR-4 léger sans `vendor/` runtime.
**Inconvénients** : l’équipe développe et maintient elle-même ses briques transversales (routeur, CSRF, échappement `e()`, chargement SQL), avec une convention interne à découvrir pour un nouvel arrivant.

## Décision

**Nous choisissons une architecture MVC en PHP 8.2+ natif sans framework tiers en production**, articulée autour de `public/index.php` et d’espaces de noms PSR-4 sous `app/`.

## Conséquences

### Positives

- Zéro dépendance runtime tierce à patcher, empreinte mémoire minimale et exécution directe en PHP-WASM.
- Séparation nette permettant les tests isolés, la sécurité statique (`11/11`) et la double persistance (`ADR-0001`).

### Négatives

- Maintenance interne du routeur, des aides de vues et du moteur de rendu ; une évolution vers une API REST complexe ou multi-équipes demanderait une migration de la couche `Controller`/`Router`.

### Neutres

- PHPUnit reste utilisable en développement et en CI (`tests/phpunit/`) sans être embarqué en production.

## Liens

- `ARCHITECTURE.md` · `STACK.md` · `public/index.php` · `app/Router.php`
