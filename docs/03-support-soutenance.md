# Support et déroulé de la soutenance — MiniShop (15 minutes)

Document n° 4 du rendu (barème iv : soutenance + questions, 10 points). Le plan ci-dessous est une **trame chronométrée**, non un compte rendu de répétition. Les repères A/B/C sont des rôles de planification, pas des personnes : aucune attribution nominative ni répartition de parole réelle n’est confirmée. Ils ne désignent pas non plus des permissions GitLab ou les valeurs R/A/C/I du RACI.

## 1. Matériel à préparer

- support `docs/12-diaporama-soutenance.md` (et diaporama exporté `livrables/minishop-soutenance.pptx`, 12 diapositives) ; guide complet de préparation dans [`24-preparation-soutenance-complete.md`](24-preparation-soutenance-complete.md) ;
- configuration prête : `.env` (copié depuis `.env.example`), base créée par `./run.sh --db`
- poste prêt : `./run.sh` lancé (équivalent : `php -S 127.0.0.1:8000 -t public scripts/dev-router.php`), base **rejouée à froid** (`./scripts/load_db.sh`),
  deux navigateurs ouverts (front-office en navigation privée côté client, back-office à côté) ;
- terminal ouvert sur `mysql minishop` avec les 4 requêtes de preuve §12.3.5 déjà collées ;
- si un résultat de test est présenté, le rejouer sur une base temporaire isolée et conserver date, révision et sortie ; le fichier `rapport_tests_sql.md` porte une mention future du 27/10/2026 par rapport à la coupure du 23/10, qui n’est pas une preuve de rejeu acquis ;
- liens GitLab prêts (dépôt, MR, `git shortlog -sne`, README) ;
- **filet de sécurité** : 3 minutes de démo enregistrées (vidéo locale) au cas où rien ne se lance.

## 2. Déroulé minute par minute

| min | Qui | Contenu | Preuve affichée | Réf. CDC |
|---:|---|---|---|---|
| 0-1 | A | Accueil, socle technique (`SqlStore` / `JsonStore`, zéro dépendance) et besoin : problème initial, **14 cas d'utilisation**, **3 acteurs**, PHP 8.2+ POO, MySQL/MariaDB via PDO, traçabilité native | Diapos 1–2 (`cas_utilisation_global.png`, logos PHP/MariaDB/MySQL) | §1.1, §3 |
| 1-2 | A | Périmètre livré (Front-office, Espace client, Back-office, Transverse et CLI) et architecture issue de l'arborescence (`public/` seul exposé, `Router.php` `42` GET / `25` POST, triade MVC, `Database::store()`) | Diapos 3–4 (grille 2×2 + schéma `tree` & MVC) | §2, §4, §9 |
| 2-4 | B | **Modélisation BD & SQL** : schéma relationnel **9 tables 3FN** (`PK`/`FK`/`UK`, cardinalités `1:N`), tables d'audit (`stock_movement_history`, `order_status_history`), `administrateur`, `parametre`, **3 vues**, **1 fonction `fn_param`**, procédures stockées et déclencheurs | Diapo 5 (schéma ER + couche SQL) & `sql/01`–`03` | §12.1–§12.6 |
| 4-5 | B | **Logique métier et parité SQL / JSON** : cycle de commande (`BROUILLON → EN_PREPARATION → PAYEE → EXPEDIEE → LIVREE` + `ANNULEE` en 2 étapes avec retour stock avant expédition), panier recalculé serveur, verrous `SELECT … FOR UPDATE`, parité `Schema`/`Triggers`/`Views` | Diapo 6 (schéma cycle de vie + 3 piliers) | §6, §7.1, §12.5 |
| 5-6 | B | **Sécurité applicative** : données sensibles protégées par hachage `bcrypt` jamais en clair, sessions isolées et cookie `MINISHOPSESS`, CSRF `hash_equals`, XSS `e()` + CSP + `X-Frame-Options`, PDO préparé + `id_client`, **`11/11` contrôles statiques** (`controles.sh`, 17/11/2026) ; aucun test manuel LineServ revendiqué | Diapo 7 (grille 2×2 + bannière `11/11`) & `tests/security/controles.sh` | §8, §15.3 |
| 6-8 | C | **Tests, performances & UI** : campagne VM **60/60** (`docs/pv-recette.md`), **29/29** SQL/JSON, **105/105** HTTP SQL E2E, **8/8** concurrence (`20` clients / `12` unités), **338/338** E2E WASM, `48` états FR↔EN, benchmark `minishop_perf` (`236` produits / `1 004` commandes : SQL p95 ≤ `20,6 ms`, HTTP p95 = `6 ms`, `0` erreur), en distinguant les `200` scénarios manuels `NON JOUÉ` ; design system `app.css`, WCAG 2.1 AA, responsive `320–1536 px` | Diapos 8–9 (`docs/pv-recette.md`, `dernieres_mesures.txt`) | §15, doc tests |
| 8-12 | C & A | **Démonstration en direct (4 min)** en environnement local isolé sous SQL (`./run.sh --db` puis `./run.sh`, sans confondre avec LineServ) : front-office `/catalogue` → panier AJAX → commande → annulation 2 étapes ; back-office gestionnaire (statuts & stocks) ; super-admin (produits, paramètres, équipe) ; preuve SQL directe (`v_etat_stock`, `stock_movement_history`, `order_status_history`) | Diapo 10 (`seq_passer_commande.png`) + navigateurs + terminal MySQL | §4.2–§4.5, §7.1 |
| 12-15 | A | **Arbitrages, limites & bilan** : parité SQL/JSON et incidents VM résolus, arbitrages assumés, prérequis LineServ non testée, WBS `82` tâches / `348 h` soldé, documentation vivante (`113` PHP / `394` PHPDoc, `8` Python, `6` ADR, `4` C4, fiche service), suites hors périmètre ; contribution GitLab à vérifier sur le dépôt IUT | Diapos 11–12 & preuves dépôt | §9, §14, §11.2 |

**Contraintes de tenue du temps** : cible de 15 minutes, répétitions à planifier et à consigner si elles ont lieu. Aucun nombre de participants, durée de parole individuelle, répétition ou démonstration exécutée n’est attesté par cette trame.

## 3. Questions anticipées et réponses courtes (à connaître par cœur)

| Question probable | Réponse (10 à 30 s) | Réf. |
|---|---|---|
| Pourquoi une procédure stockée plutôt que du PHP pour la commande ? | SQL est le pilote par défaut et `CommandeRepository` appelle les procédures de commande ; elles portent atomicité et verrous côté serveur, logique commune et moins d’allers-retours. Le coût est la portabilité et le débogage. L’exécution PDO/MariaDB (`29/29` SQL et `105/105` HTTP SQL) est validée dans `docs/pv-recette.md`. | §12.5.1, §7.1 |
| Pourquoi garder `prix_ttc` et `montant_total` si c'est calculable ? | Deux dénormalisations **tracées et verrouillées** par trigger : elles portent la valeur contractuelle du moment de l'achat (facturation) et évitent une agrégation à chaque affichage ; sans le trigger, la redondance serait une anomalie | §12.3.2, §12.3.4 |
| Pourquoi `total_ligne` et pas `prix × quantité` à la demande ? | Pour la même raison : le prix payé ne doit plus jamais bouger ; le trigger le recalcule à l'insertion et l'immutabilité l'interdit après | RB-06, RB-09, T-11 |
| Pourquoi deux tables CLIENT et ADMINISTRATEUR et pas une table UTILISATEUR avec un rôle ? | Données et cycle de vie disjoints, aucune écriture possible sur la table admin depuis le front (SEC-10), contrôle d'accès plus simple à prouver ; le surcoût est nul à notre échelle | V4 du §12.8 |
| Pourquoi le `BROUILLON` existe-t-il, on ne peut pas créer la commande directement en `EN_PREPARATION` ? | Sinon `RB-04` (au moins une ligne) est violée à la création ; le brouillon est l'état « panier convertible », jamais exposé à l'admin (`COMMANDE_NON_TRAITABLE`) | V3 du §12.8, T-16 |
| Comment est visée la prévention de survente ? | Le chemin SQL par défaut utilise les procédures et verrous `SELECT … FOR UPDATE`. La campagne VM du 17/11 (`docs/pv-recette.md`) a validé `concurrence_http.php` (**8/8**, 20 acteurs / 12 unités) et `sql_integration.php` (**29/29**). Le mode JSON reste une option secondaire. | §2.3, C-01, §7.1 |
| Que prévoit la conception si un contrôle d'appartenance est oublié ? | `sp_confirm_order` vérifie l’appartenance, et le repository utilise cette procédure sur le chemin SQL. `T-18` (dans `29/29`) et `http_sql_e2e.php` (`105/105`) ont validé ce comportement sur VM isolée (`docs/pv-recette.md`). | SEC-08, T-18, §7.1 |
| Pourquoi un trigger et pas la procédure pour écrire l'historique ? | Un `UPDATE` direct (import, reprise, correction) doit aussi laisser une trace. Le scénario `T-22` (validé dans `29/29` sur VM, `docs/pv-recette.md`) vérifie l’absence de doublon lors d’un changement de statut. | A-02, T-22 |
| Quelle est la portée des vérifications anti-injection ? | `SEC-01`, les `11/11` contrôles statiques (`controles.sh`, dont `S-11`/`SEC-14`) et `http_sql_e2e.php` (`105/105`) ont été exécutés sur VM (`docs/pv-recette.md`), distinctement des scénarios offensifs manuels `S-01…S-10` et de LineServ. | §8.1, §15.3 |
| Et si on tente une écriture SQL qui met le stock à −5 ? | Le modèle applique un `CHECK` et un déclencheur ; `T-02` et `T-03` ont été validés en intégration SQL (`29/29`, `docs/pv-recette.md`) sur base temporaire isolée. | §6.1, §6.2 |
| Pourquoi le mot de passe est-il vérifié en PHP et pas en base ? | Seul `password_verify()` sait comparer bcrypt en temps constant ; la base ne renvoie donc que le hash via `sp_get_credentials`, la comparaison reste dans l'application | §8.4, séquence 1 |
| Quelle difficulté de conception est consignée ? | Le registre documentaire décrit un historique autour de `RB-06` et de l’ordre des triggers (`A-01`, `T-11`). Ne pas le présenter comme un rejeu de la version courante sans résultat daté. | `docs/02` §7 |
| Que feriez-vous en poursuite (hors périmètre) ? | Panier persistant (variante V1 prête), paiement réel avec journal de transactions, politique de prix par remise (une table `REMISE` + snapshot), export comptable, et tests Playwright bout en bout | §12.8, §11.2 |
| Où vérifier les contributions GitLab ? | Dans l’historique réel du dépôt IUT et les liens de MR disponibles. Ce checkout ne confirme pas les contributions individuelles ; permissions GitLab, valeurs R/A/C/I et rôles de planification A/B/C restent distincts. | §14 |
| Faut-il relancer la CI après un merge ? | Contrôler le statut et le commit de la PR GitHub dans GitHub Actions. La CI GitHub ne prouve pas un pipeline GitLab IUT, qui n’est pas configuré dans le périmètre actuel | §14.2 |
| Une MR GitLab peut-elle être validée par le statut de la CI GitHub ? | Non : les chaînes GitHub Actions et GitLab sont distinctes ; le statut d’une PR GitHub ne vaut pas pipeline GitLab. Le registre dédié est un modèle à renseigner après une revue réelle ; aucune revue croisée n’est attestée ici. | §14.2 |

### 3.1 Les trois décisions « hors sujet » à défendre en 30 secondes chacune

| Décision | Phrase à dire | Si le jury insiste |
|---|---|---|
| **Les frais de port sont dans le périmètre** | « Le CDC décrit `RB-20` comme une règle visant l’affichage du port avant validation. » | Montrer `T-28` (validé dans `29/29` SQL sur VM, `docs/pv-recette.md`) et les paramètres configurables côté admin. |
| **Ventes interdites à stock nul** | « Le modèle SQL décrit les contrôles de stock et l’isolation de l’écriture. » | `T-02` est validé en SQL (`29/29`) ; ne jamais lancer de test destructif sur une base de production ou partagée. |
| **Deux rôles d'administrateur, pas de MFA** | « L'énoncé dit simplement << rôle administrateur >>. On a appliqué le moindre privilège — **OWASP ASVS V4.1.3** et le guide **ANSSI** R27/R29 — en séparant `SUPER` (comptes d'équipe) de `GESTIONNAIRE` (catalogue et commandes). La MFA, exigée par ASVS V4.3.1, est **explicitement hors périmètre et écrite comme un écart** au §8.10 du cahier des charges, avec ce qu'il faudrait pour la livrer. » | Montrer `EF-ADM-10` (garde `SUPER`), `F-32` (un `GESTIONNAIRE` reçoit 403 sur la gestion d'équipe) et le §8.10 (tableau des écarts). |
| **Volumétrie cible et mesures VM** | « Le dimensionnement de conception retient 200 produits et 1 000 commandes par an. » | Campagne `run_perf_vm.sh` (`minishop_perf`, `236` produits, `1 004` commandes) : SQL p95 ≤ `20,6 ms`, HTTP p95 = `6 ms` (`tests/perf/dernieres_mesures.txt`). |

## 4. Check-list de la dernière demi-heure avant l'entrée en salle

- [ ] Confirmer la durée autorisée, l’environnement local isolé et le mode de stockage effectivement configuré ; exclure LineServ et les bases partagées.
- [ ] Si les scripts SQL sont rejoués en direct, utiliser une base temporaire dédiée ; pour les preuves consignées, s’appuyer sur `docs/pv-recette.md` (`f9b614f`, 17/11/2026, `60/60`) et `tests/perf/dernieres_mesures.txt` (`cb0f814`, HTTP p95 = `6 ms`).
- [ ] Vérifier les écrans de démonstration dans l’environnement réellement disponible, sans présenter un scénario documentaire comme résultat.
- [ ] Préparer les références des 11 contrôles statiques (dont `S-11`/`SEC-14`) et des 10 scénarios offensifs distincts.
- [ ] Ne lancer aucune requête destructive ou tentative de sécurité sur une base de production ou LineServ.
- [ ] `git shortlog -sne main` exécuté et visible (équipe : Johan Polsinelli et Chahd Gouiaa)
- [ ] diaporama exporté en PDF, projeté sans connexion
- [ ] chrono réglé sur 15 min, une personne désignée « gardien du temps »
