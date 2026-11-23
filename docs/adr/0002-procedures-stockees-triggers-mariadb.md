# ADR-0002 : Porter les invariants transactionnels et d’audit par procédures stockées et déclencheurs MariaDB

## Statut

Accepted — Date: 2026-09-25 · Auteurs: Johan Polsinelli, Chahd Gouiaa · Reviewers: Équipe MiniShop

## Contexte et problème

Les opérations critiques de MiniShop (création de compte, validation de commande, décrémentation du stock, annulation et journalisation) subissent des accès concurrents et imposent des règles métier strictes (`RB-01` à `RB-21`). Deux requêtes simultanées sur le dernier article disponible ne doivent jamais créer de stock négatif ni omettre une trace d’audit.

## Decision Drivers

- Empêcher les *race conditions* de stock négatif sous concurrence réelle
- Garantir l’audit automatique des statuts et mouvements de stock quel que soit le client SQL
- Respecter le schéma relationnel normalisé 3FN (`sql/01` à `sql/04`) sur MariaDB `10.6+` / MySQL `8.0.16+`

## Options considérées

### Option 1 : Transactions et contrôles uniquement dans le code PHP

**Avantages** : logique regroupée dans un seul langage (PHP) ; débogage pas-à-pas direct.
**Inconvénients** : expose aux lectures concurrentes si un appel oublie `FOR UPDATE`, et tout script SQL manuel contourne l’audit et le calcul TTC.

### Option 2 : Contraintes `CHECK` et déclencheurs simples sans procédures

**Avantages** : protège les invariants de colonnes dans le SGBD.
**Inconvénients** : insuffisant pour orchestrer des transactions multi-tables atomiques comme `sp_confirm_order` (port, décrément stock, statut).

### Option 3 : Schéma 3FN + `18` procédures stockées (`FOR UPDATE`) + `16` déclencheurs

**Avantages** : verrous pessimistes `SELECT ... FOR UPDATE`, audit automatique garanti (`journal_stock`, `historique_statut_commande`) et calcul serveur du `prix_ttc`.
**Inconvénients** : logique répartie entre SQL et PHP et privilèges requis pour `--routines --triggers` lors des restaurations.

## Décision

**Nous plaçons les invariants transactionnels et d’audit dans MariaDB 3FN** via `sql/01_minishop_schema.sql`, `sql/02_minishop_procedures.sql` (`18` procédures + `1` fonction) et `sql/03_minishop_triggers.sql` (`16` déclencheurs). Toute erreur métier remonte un code normalisé (`SIGNAL SQLSTATE '45000'`) traduit en `BusinessError`.

## Conséquences

### Positives

- Cohérence transactionnelle vérifiée sous concurrence (`tests/php/concurrence_http.php`) et traçabilité complète des mouvements.

### Négatives

- Toute évolution vers une architecture distribuée V2 imposera une migration coordonnée des scripts `sql/`.
- Les imports nécessitent `SqlLoader` (`DELIMITER $$`) et des droits adaptés sur les routines en hébergement partagé.

### Neutres

- Le pilote secondaire `JsonStore` émule explicitement les mêmes codes `BusinessError` pour les tests hors SQL (`ADR-0001`).

## Liens

- `sql/01_minishop_schema.sql`, `sql/02_minishop_procedures.sql`, `sql/03_minishop_triggers.sql` · `docs/04-conception-bd-et-sql.md` · `docs/adr/0001-double-persistance-sqlstore-jsonstore.md`
