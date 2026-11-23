# ADR-0001 : Adopter une double persistance `SqlStore` et `JsonStore` derrière `StoreInterface`

## Statut

Accepted

Date: 2026-09-20
Auteurs: Johan Polsinelli, Chahd Gouiaa
Reviewers: Équipe MiniShop

## Contexte et problème

MiniShop cible une base relationnelle MariaDB/MySQL (`STORAGE_DRIVER=sql`) pour garantir l’intégrité transactionnelle, les clés étrangères et les procédures stockées. Cependant, les tests unitaires isolés en CI sans serveur SQL, le rejeu en navigateur PHP-WASM et les démonstrations sur poste dépourvu de SGBD nécessitent de pouvoir exécuter toute l’application sans base de données.

## Decision Drivers

- Respecter l’exigence `CT-03` (SQL pur, requêtes préparées PDO et procédures stockées en production)
- Permettre l’exécution des suites isolées (`--sans-sql`) et des tests E2E PHP-WASM sans serveur MariaDB
- Éviter toute duplication de la logique métier dans les contrôleurs PHP
- Conserver une bascule simple par configuration sans modifier le code applicatif

## Options considérées

### Option 1 : SQL uniquement (MariaDB/MySQL obligatoire partout)

**Avantages** :

- Une seule implémentation de persistance à tester et maintenir
- Zéro écart possible entre moteur principal et moteur de test

**Inconvénients** :

- Bloque l’exécution des tests rapides sans SGBD et rend impossible la démonstration PHP-WASM hors ligne

### Option 2 : ORM complet (Doctrine ORM ou Eloquent) avec SQLite en test

**Avantages** :

- Abstraction standardisée des entités et requêtes

**Inconvénients** :

- Masque le SQL explicite et contourne les procédures stockées imposées par le cahier des charges (`CT-03`)
- Ajoute des dépendances Composer lourdes contraires au socle léger retenu

### Option 3 : Contrat `StoreInterface` avec pilotes `SqlStore` et `JsonStore`

**Avantages** :

- Les dépôts (`app/Repository/`) ne dépendent que de `StoreInterface` et de capacités optionnelles (`StoredProcedureExecutor`, `RowLockingStore`)
- `SqlStore` exécute PDO + les `18` procédures stockées en production, tandis que `JsonStore` assure les tests isolés et WASM via fichiers JSON verrouillés (`flock`)

**Inconvénients** :

- Impose de maintenir la parité comportementale entre `SqlStore` et `Triggers.php` côté JSON
- `JsonStore` reste limité à un nœud unique et ne convient pas à une forte concurrence réelle

## Décision

**Nous choisissons le contrat `StoreInterface` servi par `SqlStore` (par défaut) et `JsonStore` (secondaire explicite)**.

Raisons principales :

1. La variable `STORAGE_DRIVER` dans `.env` bascule d’un moteur à l’autre sans toucher aux contrôleurs ni aux vues.
2. Les invariants SQL restent au cœur de `SqlStore` tandis que `JsonStore` débloque l’isolation des tests et PHP-WASM.

## Conséquences

### Positives

- Parité fonctionnelle vérifiée en CI sur les deux pilotes
- Isolation complète des données de test et exécution hors ligne garantie

### Négatives

- Toute nouvelle règle portée par un déclencheur SQL doit être répliquée et testée dans `app/Model/Data/Triggers.php`
- Double surface de tests à maintenir pour `SqlStore` et `JsonStore`

### Neutres

- Les horodatages stockés sont uniformisés en `UTC` dans les deux pilotes (`App\Tools\Heure`)

## Liens

- Implémentation : `app/Model/Data/StoreInterface.php`, `app/Model/Data/SqlStore.php`, `app/Model/Data/JsonStore.php`
- Architecture générale : `ARCHITECTURE.md`
