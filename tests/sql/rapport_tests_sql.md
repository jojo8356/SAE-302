# Rapport de la campagne de tests SQL — MiniShop

*PV de recette côté base — généré par le harnais `tests/sql/` (source de vérité : `tests/sql/manifest.txt`).*

| | |
|---|---|
| **SGBD** | MariaDB 11.8.6 (Debian) — cible : MySQL 8.0.16+ ou MariaDB 10.6+ |
| **Date de la campagne** | 23 septembre 2026 |
| **Jeu de données** | `sql/01` (référence) + `sql/04` (commandes créées par `CALL`) + `tests/sql/fixture.sql` (état déterministe) |

## 1. Chargement et inventaire (chaîne rejouée de zéro)

| Étape | Commande | Résultat |
|---|---|---|
| 1. DDL + données | `mysql -u root -p < sql/01_minishop_schema.sql` | ✅ sans erreur |
| 2. Procédures | `mysql -u root -p minishop < sql/02_minishop_procedures.sql` | ✅ sans erreur |
| 3. Déclencheurs | `mysql -u root -p minishop < sql/03_minishop_triggers.sql` | ✅ sans erreur |
| 4. Démo via `CALL` | `mysql -u root -p minishop < sql/04_minishop_demo.sql` | ✅ sans erreur |

Inventaire vérifié dans `information_schema` : **8 tables · 3 vues · 1 fonction · 17 procédures · 16 déclencheurs**
(minima du sujet : 5 procédures, 5 déclencheurs) ; 9 traces dans `order_status_history`
(`NULL→BROUILLON` ×4, `BROUILLON→EN_PREPARATION` ×3, `EN_PREPARATION→PAYEE`, `PAYEE→EXPEDIEE`).

Données de démonstration retrouvées avec les montants exacts attendus :

| Numéro | Client | Statut | Montant | Port | Lignes |
|---|---|---|---:|---:|---:|
| CMD2026-000001 | alice | EN_PREPARATION | 324,60 | 0,00 (franchise) | 2 |
| CMD2026-000002 | bruno | BROUILLON | 0,00 | 0,00 | 0 |
| CMD2026-000003 | carla | EXPEDIEE | 405,48 | 0,00 (franchise) | 2 |
| CMD2026-000004 | alice | EN_PREPARATION | 63,70 | **4,90** | 1 |

## 2. Gardes des règles métier vérifiés en SQL direct (hors application)

Chaque ligne ci-dessous est un rejet **attendu** observé sur la base réelle :

| Règle | Tentative | Rejet observé |
|---|---|---|
| RB-01 | `INSERT` client avec email existant | `Duplicate entry` (uk_client_email) |
| RB-02 | produit à prix nul | `ck_produit_prix` |
| RB-03 | `UPDATE produit SET stock = -1` | `RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF` |
| RB-05 | ligne avec quantité 0 | `ck_ligne_quantite` |
| RB-06/09 | `UPDATE` d'une ligne (prix, quantité) | `RB06_PRIX_ET_QUANTITE_NON_MODIFIABLE` |
| RB-06 | prix envoyé 999,99 à l'insertion | écrasé par le snapshot : 154,80 |
| RB-09 | `DELETE` d'une commande validée | `RB09_COMMANDE_VALIDATEE_INTERDITE_DE_SUPPRIMER` |
| RB-11 | `BROUILLON→EXPEDIEE`, `PAYEE→LIVREE`, sortie de `LIVREE` | `RB11_TRANSITION_STATUT_INTERDITE` (×3) |
| RB-11 | transition légale `EXPEDIEE→LIVREE` | ✅ acceptée |
| RB-14 | suppression d'une catégorie occupée / d'un produit vendu | `CATEGORIE_NON_VIDE` / `PRODUIT_REFERENCE_INTERDIT_DE_SUPPRIMER` |
| RB-15 | `UPDATE commande SET montant_total = 1` | `RB15_MONTANT_CALCULE_INTERDIT` |
| RB-04 | purge d'une commande non vide | `RB04_COMMANDE_DOIT_CONTENIR_AU_MOINS_UNE_LIGNE` |
| RB-18 | `sp_add_order_line` au-delà du stock | `STOCK_INSUFFISANT` (code retour, sans exception) |
| RB-19 | `sp_create_order_from_basket('[]')` | `RB19_PANIER_VIDE` |

## 3. Procédures : scénarios nominale et alternatifs

| Procédure | Scénario | Résultat |
|---|---|---|
| `sp_create_account` | email en doublon → code `EMAIL_DEJA_UTILISE` ; hash court → `SIGNAL HASH_MOT_DE_PASSE_INVALIDE` | ✅ |
| `sp_get_credentials` | client connu → `id, OK` ; inconnu → `NULL, INCONNU` (message non différencié, SEC-05) | ✅ |
| `sp_adjust_stock` | `DELTA -1000` → code `RB03_…` ; `DELTA +5` → stock mis à jour | ✅ |
| `sp_create_order_from_basket` | panier JSON valide → commande + montants + statut ; **stock insuffisant → ROLLBACK intégral** (stock inchangé, 0 commande créée) | ✅ |
| `sp_update_order_status` | transition + trace écrite **par le trigger seul** (pas de doublon) | ✅ |
| `sp_cancel_order` | restitution du stock (+1 constaté) + `frais_port` et `montant_total` à 0 + statut `ANNULEE` | ✅ |
| `sp_delete_product` | produit vendu → masqué (`visible = 0`) ; produit jamais vendu → supprimé | ✅ |
| `sp_search_products` | 2 jeux de résultats (page + `total_trouves`), tri liste blanche | ✅ |

Mots de passe de démonstration : les 4 hash du seed sont des sorties **bcrypt réelles**
(`password_hash()`), vérifiés contre `Demo2026!` (clients) et `Admin2026!` (admin).

## 4. Suite complète (29 tests du manifeste)

Le manifeste `tests/sql/manifest.txt` décrit les 29 tests (attendu `ERREUR:<motif>` ou `OK`).
Rejeu de la suite complète :

```bash
./scripts/load_db.sh && ./scripts/run_sql_tests.sh   # attendu : « Tests conformes : 29 / 29 »
```

Le détail exécutable par test (`tNN_*.sql`) et les journaux bruts sont régénérés par le harnais ;
seuls les éléments stables sont versionnés (`.gitignore` : `tests/sql/out/*` exclu, ce rapport conservé).
