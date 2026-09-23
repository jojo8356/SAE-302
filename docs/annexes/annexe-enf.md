# Annexe 17.3 — Exigences non fonctionnelles (ENF)

Chaque exigence est **vérifiable** : la colonne « moyen de vérification » donne la commande, la requête ou
la manipulation qui prouve la conformité. Une exigence sans moyen de vérification a été supprimée du document.

| ID | Famille | Exigence | Valeur cible / règle | Moyen de vérification | Statut |
|---|---|---|---|---|---|
| `ENF-01` | Performance | Pages de liste en < 500 ms (p95) **sur la volumétrie retenue avec le client : 200 produits et 1 000 commandes/an** (jeu généré par `scripts/gen_volumes.sh`), mesuré sur le poste de soutenance et en base seule (`tests/perf/mesurer.sh --sql-only`) | p95 < 500 ms, moy < 300 ms | `bash tests/perf/mesurer.sh` (200 requêtes HTTP, `curl -w '%{time_total}'`) **et** `DB=minishop_perf ./tests/perf/mesurer_sql.sh 50` (base seule, mesuré : 15–17 ms de moyenne sur 212 produits / 992 commandes) ; nombre de requêtes via `general_log` ≤ 5 | protocole livré, mesure à exécuter |
| `ENF-02` | Volumétrie | **cible client 2026 : 200 produits / 1 000 commandes sur 12 mois / 10 clients actifs** ; le dimensionnement est gardé sûr jusqu'à 1 000 produits / 50 000 commandes (aucune partition, aucun cache) ; pas de dégradation de la recherche | modèle dimensionné, index adaptés, `scripts/gen_volumes.sh` pour reproduire le jeu de mesure | `EXPLAIN` sur les 4 requêtes types (catalogue, recherche, mes commandes, admin) → index utilisés, 0 `filesort` sur les filtres | plan d'exécution commenté en annexe du doc de tests |
| `ENF-03` | Disponibilité | application mono-nœud ; pas de haute disponibilité exigée, mais arrêt/reprise propre | redémarrage Apache sans perte de session fonctionnelle (sessions en fichiers) | arrêt/relance + rechargement d'une page privée | conforme par conception |
| `ENF-04` | Ergonomie | pagination 12 par défaut, borne haute 60, page demandée toujours honorée | 1 ≤ `par_page` ≤ 60 | `CALL sp_search_products(…, 500, …)` renvoie 60 lignes maximum (test `T-25`) | ✅ |
| `ENF-05` | Ergonomie | état des filtres conservé dans l'URL (partageable, rechargeable, retournable) | query string complet | test F-03 (copier l'URL, l'ouvrir en navigation privée) | ✅ attendu |
| `ENF-06` | Ergonomie | aucun formulaire ne perd la saisie en cas de refus serveur | 100 % des erreurs de règle re-rendent le formulaire | tests F-07, F-10, F-22 | ✅ attendu |
| `ENF-07` | Robustesse / accessibilité | **tout** le parcours d'achat fonctionne JavaScript désactivé | JS = confort uniquement | test F-13 (JS désactivé dans devtools : panier + commande OK) | ✅ attendu |
| `ENF-08` | Accessibilité | WCAG 2.1 AA sur les parcours clés : labels, focus visible, contrastes, annonces `aria-live` sur le panier | 0 erreur bloquante | audit manuel + `axe` (optionnel), check-list §7.4 | protocole livré |
| `ENF-09` | Maintenabilité | PHP 8 `strict_types`, PSR-12, PSR-4, aucune classe de plus de 250 lignes, aucune fonction de plus de 40 lignes | seuils vérifiables | `php -l` + `phpcs --standard=PSR12` + `wc -l` | à exécuter par l'équipe |
| `ENF-10` | Portabilité | fonctionne sur MySQL 8.0.16+ **et** MariaDB 10.6+ ; fonctionne sous Apache avec `mod_rewrite` | double chargement réussi | `scripts/load_db.sh` joué sur les deux SGBD (fait : MariaDB 11.8.6) | ✅ MySQL à re-vérifier par l'équipe |
| `ENF-11` | Sécurité des données | aucun mot de passe en clair, aucun jeton de session dans une URL | `SELECT COUNT(*) … WHERE CHAR_LENGTH(mot_de_passe_hash) < 60` → 0 | requête + `grep -R "password" app/` revue | ✅ (test `T-05` côté écriture) |
| `ENF-12` | Sécurité | aucune information sur l'existence d'une ressource appartenant à autrui | 404 (et non 403) pour un `id` étranger | test S-03 | ✅ attendu |
| `ENF-13` | Exploitation | alertes de stock calculées côté base (formule unique) | `RUPTURE` / `TRES_BAS` / `DISPONIBLE` | vue `v_etat_stock` + test `T-26` | ✅ |
| `ENF-14` | Conformité commerciale | prix affichés **TTC**, taux de TVA, **frais de livraison** et **montant total (marchandises + port)** indiqués **avant** la validation — directive 2011/83/UE art. 5 §1 e), code de la consommation art. L221-5 4° (sources : §8.9 du CDC) | mention obligatoire présente sur le récapitulatif **et** valeur `commande.frais_port` soldée à la validation (`RB-20`) | test F-16 (récapitulatif), `T-28` (port calculé puis figé, non saisissable) | ✅ **obtenu** côté base (T-28 conforme) |
| `ENF-15` | Exploitation | sauvegarde/restauration complète incluant procédures et triggers | `mysqldump --routines --triggers` | restauration sur base vide + `load_db.sh` non rejoué → tests SQL toujours 29/29 | à exécuter en S11 |
| `ENF-16` | Cohérence | aucune écriture multi-tables non transactionnelle ; un rejet ne laisse jamais d'état intermédiaire | `beginTransaction`/`commit`/`rollBack` systématiques | revue des 3 repositories + tests `F-18`, `T-09` | ✅ (prouvé au niveau base par `T-09`) |
| `ENF-17` | Observabilité | chaque rejet de règle est journalisé avec code, acteur et route, sans donnée sensible | JSON lines, 6 types d'évènements | test S-08 (recherche de mot de passe dans les logs → 0 occurrence) | ✅ attendu |
| `ENF-18` | Compatibilité | dernières versions majeures de Chrome, Firefox, Safari, Edge ; pas de support IE | pas de syntaxe non standard (modules ES2022, `fetch`) | tests visuels + `npm run lint` optionnel | protocole livré |

## 17.3.1 Exigences de qualité logicielle complémentaires

| ID | Exigence | Justification |
|---|---|---|
| `ENF-19` | toute règle `RB-*` est appliquée au **minimum** à deux niveaux (PHP + base) | défendu par le tableau §6.2 et prouvé par les tests de contournement |
| `ENF-20` | aucune dépendance runtime hors PHP/MySQL/Apache | réductibilité et auditabilité ; `composer` réservé au `require-dev` |
| `ENF-21` | chaque fichier livré a un auteur identifiable dans GitLab (pas de commit « fourre-tout » de plus de 400 lignes) | condition d'équité de la note de contribution |
| `ENF-13` | le `README.md` permet une installation complète en ≤ 15 min sur une machine propre | critère de recette n° 6 (§11.3) |

> **Rétention des données** (issue du même examen légal que `ENF-14`) : les durées de conservation ne sont pas
> écrites dans le code mais dans la table `parametre` (`conservation_compte` = 36 mois après le dernier contact,
> valeur indicative à valider par le client) — voyant §8.9 et §8.10 du cahier des charges.
