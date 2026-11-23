# ADR-0002 — Procédures stockées et déclencheurs dans MariaDB 3FN

**Statut :** Accepté · **Date :** 2026-09-25 · **Propriétaire :** Équipe MiniShop  
**Fichiers concernés :** `sql/01_minishop_schema.sql`, `sql/02_minishop_procedures.sql`, `sql/03_minishop_triggers.sql`

---

## 1. Contexte

Le passage de commande, l’annulation et la gestion des stocks sont sujets aux accès concurrents (deux clients achetant le dernier article en même temps) et à des exigences strictes de traçabilité (chaque mouvement de stock et changement de statut doit être historisé).

## 2. Décision

Placer les invariants critiques au plus près des données dans MariaDB (`InnoDB`, `utf8mb4`) :
- Schéma normalisé **3FN** (`9` tables, `4` vues relationnelles) ;
- **`18` procédures stockées + `1` fonction** (`sp_create_order_from_basket`, `sp_confirm_order`, `sp_cancel_order`, etc.) exécutées dans des transactions explicites avec verrous pessimistes (`SELECT ... FOR UPDATE`) ;
- **`16` déclencheurs (`AFTER`/`BEFORE`)** assurant l’audit automatique dans `journal_stock` et `historique_statut_commande`.

## 3. Alternatives écartées

- **Gestion des transactions uniquement en PHP sans `FOR UPDATE` :** expose à des *race conditions* de stock négatif sous charge concurrente (`tests/php/concurrence_http.php`).

## 4. Conséquences

- **Positives :** cohérence transactionnelle forte même si plusieurs nœuds PHP attaquent MariaDB simultanément ; audit garanti quel que soit le client SQL.
- **Limites assumées :** logique métier répartie entre PHP et SQL ; toute évolution vers une *Marketplace* V2 nécessite une migration coordonnée des scripts `sql/`.
