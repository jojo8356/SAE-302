# Annexe 17.3 — Exigences non fonctionnelles (ENF)

Chaque exigence est **vérifiable en principe** : la colonne « moyen de vérification » définit une commande, une requête ou une manipulation à exécuter. La présence d’un protocole ou d’un identifiant `T-*`/`F-*` ne prouve pas son exécution ni la conformité ; un résultat doit être rattaché à un run daté, au SHA testé, à l’environnement et aux sorties conservées.

| ID | Famille | Exigence | Valeur cible / règle | Moyen de vérification | Statut |
|---|---|---|---|---|---|
| `ENF-01` | Performance | Pages de liste en < 500 ms (p95) **sur la volumétrie cible : 200 produits et 1 000 commandes/an** (jeu prévu par `scripts/gen_volumes.sh`) | p95 < 500 ms, moyenne < 300 ms | `bash tests/perf/mesurer.sh` (200 requêtes HTTP) et `DB=minishop_perf ./tests/perf/mesurer_sql.sh 50` ; consigner les mesures et le nombre de requêtes via `general_log` | protocole présent ; mesure cible non revendiquée dans cette révision. Une ancienne valeur 15–17 ms est une mention d’archive sans preuve rattachée au SHA examiné |
| `ENF-02` | Volumétrie | **cible client 2026 : 200 produits / 1 000 commandes sur 12 mois / 10 clients actifs** ; le dimensionnement est gardé sûr jusqu'à 1 000 produits / 50 000 commandes (aucune partition, aucun cache) ; pas de dégradation de la recherche | modèle dimensionné, index adaptés, `scripts/gen_volumes.sh` pour reproduire le jeu de mesure | `EXPLAIN` sur les 4 requêtes types (catalogue, recherche, mes commandes, admin) → index utilisés, 0 `filesort` sur les filtres | plan d'exécution commenté en annexe du doc de tests |
| `ENF-03` | Disponibilité | application mono-nœud ; pas de haute disponibilité exigée, mais arrêt/reprise propre | redémarrage Apache sans perte de session fonctionnelle (sessions en fichiers) | arrêt/relance + rechargement d'une page privée | conforme par conception |
| `ENF-04` | Ergonomie | pagination 12 par défaut, borne haute 60, page demandée toujours honorée | 1 ≤ `par_page` ≤ 60 | scénario `T-25` prévu pour contrôler `CALL sp_search_products(…, 500, …)` | à rejouer ; pas de résultat actuel revendiqué |
| `ENF-05` | Ergonomie | état des filtres conservé dans l'URL (partageable, rechargeable, retournable) | query string complet | scénario `F-03` prévu (copier l'URL, l'ouvrir en navigation privée) | résultat attendu ; à rejouer |
| `ENF-06` | Ergonomie | aucun formulaire ne perd la saisie en cas de refus serveur | 100 % des erreurs de règle re-rendent le formulaire | scénarios `F-07`, `F-10`, `F-22` prévus | résultat attendu ; à rejouer |
| `ENF-07` | Robustesse / accessibilité | **tout** le parcours d'achat fonctionne JavaScript désactivé | JS = confort uniquement | scénario `F-13` prévu avec JavaScript désactivé | résultat attendu ; à rejouer |
| `ENF-08` | Accessibilité | WCAG 2.1 AA sur les parcours clés : labels, focus visible, contrastes, annonces `aria-live` sur le panier | 0 erreur bloquante | audit manuel + `axe` (optionnel), check-list §7.4 | protocole livré |
| `ENF-09` | Maintenabilité | PHP 8 `strict_types`, PSR-12, PSR-4, aucune classe de plus de 250 lignes, aucune fonction de plus de 40 lignes | seuils vérifiables | `php -l` + `phpcs --standard=PSR12` + `wc -l` | à exécuter par l'équipe |
| `ENF-10` | Portabilité | cible MySQL 8.0.16+ **et** MariaDB 10.6+ ; exécution sous Apache avec `mod_rewrite` | chargement et parcours à vérifier séparément sur chaque cible | protocole `scripts/load_db.sh` ; une mention historique de MariaDB 11.8.6 n’est pas une preuve pour le SHA examiné | à rejouer sur les deux SGBD ; aucun résultat courant revendiqué |
| `ENF-11` | Sécurité des données | aucun mot de passe en clair, aucun jeton de session dans une URL | requête de contrôle des hashes et revue ciblée des sources | requête SQL + recherche de sources ; `T-05` est le scénario prévu côté écriture | à rejouer ; pas de résultat SQL actuel revendiqué |
| `ENF-12` | Sécurité | aucune information sur l'existence d'une ressource appartenant à autrui | 404 (et non 403) pour un `id` étranger | scénario `S-03` prévu | résultat attendu ; à rejouer |
| `ENF-13` | Exploitation | alertes de stock calculées côté base (formule unique) | `RUPTURE` / `TRES_BAS` / `DISPONIBLE` | vue `v_etat_stock` + scénario `T-26` prévu | à rejouer ; pas de résultat SQL actuel revendiqué |
| `ENF-14` | Conformité commerciale | prix affichés **TTC**, taux de TVA, **frais de livraison** et **montant total (marchandises + port)** indiqués **avant** la validation — directive 2011/83/UE art. 5 §1 e), code de la consommation art. L221-5 4° (sources : §8.9 du CDC) | mention obligatoire sur le récapitulatif **et** valeur `commande.frais_port` soldée à la validation (`RB-20`) | scénarios `F-16` et `T-28` prévus pour contrôler le récapitulatif et le calcul/figement du port | conception documentée ; résultat d’exécution à établir sur un run daté/SHA |
| `ENF-15` | Exploitation | sauvegarde/restauration complète incluant procédures et triggers | `mysqldump --routines --triggers` | protocole de restauration sur base vide puis chargement ; les scénarios SQL doivent être rejoués séparément | à exécuter ; aucune sauvegarde/restauration ni campagne SQL n’est revendiquée |
| `ENF-16` | Cohérence | aucune écriture multi-tables non transactionnelle ; un rejet ne laisse pas d'état intermédiaire | `beginTransaction`/`commit`/`rollBack` systématiques | revue des repositories + scénarios `F-18` et `T-09` prévus | à vérifier par revue et rejeu daté ; aucun résultat actuel revendiqué |
| `ENF-17` | Observabilité | chaque rejet de règle est journalisé avec code, acteur et route, sans donnée sensible | JSON lines, 6 types d'évènements | scénario `S-08` prévu pour rechercher des données sensibles dans les logs | résultat attendu ; à rejouer |
| `ENF-18` | Compatibilité | dernières versions majeures de Chrome, Firefox, Safari, Edge ; pas de support IE | pas de syntaxe non standard (modules ES2022, `fetch`) | tests visuels + `npm run lint` optionnel | protocole livré |

## 17.3.1 Exigences de qualité logicielle complémentaires

| ID | Exigence | Justification |
|---|---|---|
| `ENF-19` | toute règle `RB-*` doit être appliquée au **minimum** à deux niveaux (PHP + base) | tableau §6.2 et scénarios de contournement prévus ; leur présence ne prouve pas l’intégration ni l’exécution |
| `ENF-20` | aucune dépendance runtime hors PHP/MySQL/Apache | réductibilité et auditabilité ; `composer` réservé au `require-dev` |
| `ENF-21` | chaque fichier livré a un auteur identifiable dans GitLab (pas de commit « fourre-tout » de plus de 400 lignes) | condition d'équité de la note de contribution |
| `ENF-22` | le `README.md` permet une installation complète en ≤ 15 min sur une machine propre | critère de recette n° 6 (§11.3) |

> **Rétention des données** (issue du même examen légal que `ENF-14`) : les durées de conservation ne sont pas
> écrites dans le code mais dans la table `parametre` (`conservation_compte` = 36 mois après le dernier contact,
> valeur indicative à valider par le client) — voyant §8.9 et §8.10 du cahier des charges.
