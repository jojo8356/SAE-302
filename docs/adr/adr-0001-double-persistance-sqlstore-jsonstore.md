# ADR-0001 — Double persistance `SqlStore` et `JsonStore` derrière `StoreInterface`

**Statut :** Accepté · **Date :** 2026-09-20 · **Propriétaire :** Équipe MiniShop  
**Fichiers concernés :** `app/Model/Data/StoreInterface.php`, `app/Model/Data/SqlStore.php`, `app/Model/Data/JsonStore.php`, `app/Config/Env.php`

---

## 1. Contexte

MiniShop doit fonctionner en production pédagogique sur MariaDB/MySQL (`STORAGE_DRIVER=sql`), mais les tests unitaires rapides en CI sans base de données, l’environnement PHP-WASM et les démonstrations autonomes exigent un démarrage sans serveur SGBD.

## 2. Décision

Toutes les opérations d’accès aux données des contrôleurs passent par des dépôts (`app/Repository/`) qui consomment uniquement le contrat **`StoreInterface`**. Deux implémentations coexistent :
- **`SqlStore` (pilote par défaut) :** exécute les requêtes préparées PDO et les `18` procédures stockées sur MariaDB/MySQL ;
- **`JsonStore` (pilote secondaire explicite) :** stocke les tables sous `data/minishop/*.json` avec verrous de fichiers (`flock`), émule les règles des déclencheurs (`Triggers.php`) et écrit le journal `var/journal/db.jsonl` (`Journal.php`).

## 3. Alternatives écartées

- **ORM lourd (Doctrine / Eloquent) :** masque le SQL pur et les procédures stockées exigées par le cahier des charges (`CT-03`).
- **SQL uniquement sans mode secours :** empêche l’exécution des suites isolées `--sans-sql` et des tests WASM en navigateur.

## 4. Conséquences

- **Positives :** parité fonctionnelle testée en CI ; bascule d’environnement par simple variable `STORAGE_DRIVER` dans `.env`.
- **Limites assumées :** `JsonStore` ne supporte ni le multi-nœuds ni la concurrence forte (`RowLockingStore`) ; la production réelle doit impérativement utiliser `STORAGE_DRIVER=sql`.
