# Audit de conformité — cahier des charges, todolist et preuves

**Date de référence de l’audit : 2026-10-23** · **Révision examinée :** `b54ab9833eb5e7d5116a21a1f21cb3b7c845eb31` (`main`, base de la branche Arena courante).

> **Conclusion courte : non, la todolist n’est ni à jour ni suffisamment complète pour affirmer que toutes ses tâches sont achevées à 100 %.** L’application et les livrables sont bien présents, et de nombreuses vérifications automatisées existent. Il reste toutefois des écarts d’implémentation à une exigence explicite du CDC, des tests humains non joués, des mesures et actions externes non prouvées, ainsi que des incohérences de comptage et de dates entre documents.
>
> Ce rapport audite les fichiers versionnés de ce checkout. Il ne consulte pas le dépôt IUT, ne présume pas l’état du clone local de l’utilisateur et ne transforme pas une tâche externe ou humaine en tâche accomplie sans preuve.

## 1. Périmètre et verdict global

`docs/06-todolist.md` est une version 1.1 arrêtée au **23 septembre 2026**. Ses 86 identifiants WBS uniques sont bien présents, mais ses statuts et ses totaux ne décrivent plus ce checkout : elle annonce encore **0/146 h** pour l’application alors que `app/`, `public/`, les contrôleurs, les vues, les scripts et les tests sont livrés. Elle laisse aussi à faire des scripts qui existent déjà.

| Statut d’audit | Nombre sur 86 | Sens |
|---|---:|---|
| `OK*` | 52 | Livrable présent et preuve automatisée/structurelle repérée dans le dépôt ou la CI. **Ce n’est pas une certification absolue** ; la réserve temporelle de la CI est ci-dessous. |
| `PARTIEL` | 21 | Une partie existe, mais un critère, une intégration, une preuve ou une cohérence reste à résoudre. |
| `ÉCART` | 6 | Écart explicite entre la tâche/contrainte du CDC et l’application présente. |
| `[!]` | 7 | Tâche humaine, externe, mesure cible ou état impossible à vérifier depuis ce checkout. |
| **Total** | **86** | Les tâches non `OK*` empêchent de conclure à une réalisation à 100 %. |

Ces statuts évaluent les **86 tâches de la WBS**, pas tout le travail ultérieur. Les ajouts ultérieurs (langue/fuseau, informations légales, contact, conservation, etc.) ne figurent pas correctement dans cette WBS et doivent eux aussi être rattachés à des exigences ou à une liste d’évolutions.

## 2. Vérifications effectuées et limites

### Vérifications rejouées dans cette sandbox

- `bash tests/security/controles.sh` : **10/10** contrôles statiques réussis.
- `node scripts/wasm_verdict.mjs` : **9/9** cas du calcul de verdict réussis.
- `node --check` : **41** fichiers JavaScript/ES modules sans erreur de syntaxe.
- `bash -n` : **13** scripts shell sans erreur de syntaxe.
- Suites Python disponibles : **42 tests** découverts, **22 exécutés avec succès** et **20 sautés** parce que PHP natif ou les dépendances/services nécessaires manquent.
- `python3 scripts/verifier_fiches_uc.py` : **46 contrôles documentaires** réussis ; 14 UC, 164 scénarios, 36 couples de transitions, 200 entrées de suivi.
- `docs/13-suivi-recette-uc.csv` : **200 lignes**, toutes au statut `NON JOUÉ`, aucun identifiant dupliqué.

### Exécution complète impossible localement

Dans cette sandbox, `php`, `composer`, `mysql`, `mariadb`, `node_modules/` et `vendor/` sont absents. Je n’ai donc pas prétendu rejouer les suites PHP, PHPUnit, l’E2E PHP-WASM, les tests navigateur ou SQL localement et je n’ai installé aucune dépendance.

GitHub Actions fournit un enregistrement de succès **15/15 jobs** pour le SHA exact audité, incluant MySQL 8, MariaDB 11.8, PHP, PHPUnit, sécurité, E2E et Chromium : [run 43200590421](https://github.com/jojo8356/SAE-302/actions/runs/43200590421). **Réserve majeure :** l’API GitHub date ce run du **12 novembre 2026**, et le commit porte aussi un horodatage du 12 novembre, alors que la date de référence de cet audit est le **23 octobre 2026**. Cette incohérence chronologique doit être corrigée ; le run est rapporté comme preuve visible attachée au SHA, pas présenté comme une exécution antérieure au 23 octobre.

### Résultat humain / cible réelle

Les 200 scénarios des fiches de recette sont à ce jour non joués dans le CSV. La campagne responsive multi-largeurs **n’est pas** à réintroduire dans les fiches manuelles : selon la décision projet, elle relève des tests automatisés, où des contrôles Chromium sont prévus. Les essais sur hébergement réel, mesures de performance cible, restauration chronométrée, validation pédagogique et répétitions de soutenance ne sont pas démontrés ici.

## 3. Revue tâche par tâche — les 86 identifiants de `docs/06-todolist.md`

`OK*` signifie « preuve d’un livrable ou d’une suite repérée », pas « tous les environnements, critères métier et vérifications humaines sont certifiés ». Les raisons données priment sur le statut historique de la todolist.

### D1 — Pilotage, dépôt et rendu

| ID | État audité | Constat et preuve |
|---|---|---|
| `D1-L1-01` | `[!]` | Création/configuration du GitLab IUT, invitations, protection de branche et courriel de composition : informations externes à ce dépôt, non vérifiées. |
| `D1-L1-02` | `OK*` | `app/` et `public/` existent et sont peuplés ; le besoin d’un squelette vide est dépassé. |
| `D1-L1-03` | `PARTIEL` | Le README décrit prérequis et installation, mais pas la composition et les rôles d’équipe demandés. Aucun nom/rôle n’est inventé pour compléter ce champ. |
| `D1-L1-04` | `[!]` | L’issue GitLab épinglée ne peut pas être vérifiée depuis le dépôt GitHub. |
| `D1-L1-05` | `PARTIEL` | Charte/annexe GitLab et modèles de MR présents ; l’usage effectif des MR et les revues croisées ne sont pas prouvés par ces fichiers. |
| `D1-L1-06` | `PARTIEL` | `.gitlab-ci.yml` existe, mais aucune exécution GitLab n’est attestée ici. La CI GitHub est une autre chaîne et ne prouve pas le pipeline IUT. |
| `D1-L1-07` | `[!]` | Réunion de cadrage et décisions d’équipe : aucune preuve de compte rendu ou de validation humaine. |
| `D1-L15-01` | `PARTIEL` | `scripts/deploy.php` est présent ; un déploiement réel avec vérification des comptes/hashes sur la cible n’est pas prouvé. |
| `D1-L15-02` | `OK*` | README final présent ; `scripts/build-docs.sh` et l’aide des comptes de démonstration existent. La composition d’équipe reste à renseigner séparément. |
| `D1-L15-03` | `[!]` | Aucun tag n’est présent dans les références de ce checkout ; nettoyage et tag final sur le dépôt IUT non vérifiables sans accès/demande explicite. |

### D2 — Cahier des charges et UML

| ID | État audité | Constat et preuve |
|---|---|---|
| `D2-L2-01` | `PARTIEL` | §1–§2 existent, mais les dates et les affirmations d’état ne sont pas cohérentes avec la date de référence (voir §5). |
| `D2-L2-02` | `PARTIEL` | Acteurs et RACI documentés ; le CDC/WBS annonce 3 développeurs tandis que la todolist annonce 2. L’équipe réelle n’est pas attestée. |
| `D2-L2-03` | `PARTIEL` | Matrice fonctionnelle présente, mais le CDC, les annexes de tests et les nouvelles fonctionnalités ne forment pas encore une trace unique et sans contradictions. |
| `D2-L2-04` | `PARTIEL` | Contraintes écrites, mais le CDC présente MySQL/PDO comme contrainte alors que le README et `StorageDriver` indiquent JSON par défaut et SQL optionnel / en migration. |
| `D2-L2-05` | `PARTIEL` | Mesures de sécurité détaillées, mais la numérotation SEC et les preuves ne sont pas alignées partout ; SEC-14 est notamment cité sans fiche de contrôle dédiée. |
| `D2-L2-06` | `PARTIEL` | La relecture croisée et le pointage des pénalités ne disposent pas d’une preuve de revue ; des contradictions documentaires demeurent. |
| `D2-L3-01` | `OK*` | Les trois vues de diagrammes de cas d’utilisation sont présentes sous `docs/diagrams/`. |
| `D2-L3-02` | `OK*` | Les 14 UC et descriptions sont documentés ; le vérificateur documentaire passe 46 contrôles. Cela valide la structure des fiches, pas leur exécution manuelle. |
| `D2-L3-03` | `OK*` | Diagramme de classes et justifications documentés et rendus. |
| `D2-L3-04` | `PARTIEL` | RB-01…RB-20 sont écrites, mais les nombres de règles complémentaires annoncés dans le CDC ne concordent pas avec les identifiants listés. |
| `D2-L3-05` | `PARTIEL` | Cinq séquences sont présentes. C’est supérieur au minimum sujet de quatre et au nombre demandé dans cette tâche, mais le CDC prétend ailleurs en livrer six (annulation et back-office inclus) : il faut corriger la déclaration ou ajouter les diagrammes manquants. |
| `D2-L3-06` | `OK*` | Sources `.puml` et `docs/diagrams/render.sh` présents ; le job PlantUML existe dans la CI. |

### D3 — Conception de données et SQL

| ID | État audité | Constat et preuve |
|---|---|---|
| `D3-L4-01` | `OK*` | MCD et rendu présents. |
| `D3-L4-02` | `OK*` | Tableau des cardinalités et justifications présent dans le CDC et la conception BD. |
| `D3-L4-03` | `OK*` | MLD et vues documentés ; scripts SQL présents. |
| `D3-L4-04` | `OK*` | Dépendances fonctionnelles et analyse 1FN/2FN/3FN documentées. |
| `D3-L4-05` | `OK*` | Clés, redondances et requêtes de preuve documentées. L’exécution locale SQL n’était pas possible dans cette sandbox. |
| `D3-L4-06` | `[!]` | Relecture croisée humaine du document de conception non démontrable par le seul dépôt. |
| `D3-L5-01` | `OK*` | DDL présent ; chargement SQL et job MySQL sont définis dans la CI. |
| `D3-L5-02` | `OK*` | FK, contraintes et index dans le schéma ; contrôles SQL prévus dans la suite. |
| `D3-L5-03` | `OK*` | Les vues sont définies et couvertes par les tests SQL (`T-27`). |
| `D3-L5-04` | `OK*` | Seed et données de démonstration présents dans les scripts ; compteurs contrôlés par le chargeur/tests. |
| `D3-L5-05` | `OK*` | `scripts/load_db.sh` existe et est appelé dans le workflow SQL. La tâche restait pourtant `⬜` dans la todolist. |
| `D3-L6-01` | `OK*` | Fonction et procédures de compte présentes dans `sql/02_minishop_procedures.sql` ; couverture SQL associée. |
| `D3-L6-02` | `OK*` | `sp_search_products` présent ; recherche et tests de catalogue présents. |
| `D3-L6-03` | `OK*` | Procédures produit, catégorie et stock présentes dans le SQL livré. |
| `D3-L6-04` | `OK*` | Procédures de commande présentes dans le SQL livré. Réserve d’intégration applicative en D4. |
| `D3-L6-05` | `OK*` | Procédures de statut, annulation, rapport et frais de port présentes. |
| `D3-L6-06` | `OK*` | Fichiers SQL `T-01`…`T-12` présents. |
| `D3-L6-07` | `OK*` | Harnais et tests SQL `T-25`…`T-29` présents. |
| `D3-L7-01` | `OK*` | Triggers de ligne de commande présents et associés aux scénarios SQL. |
| `D3-L7-02` | `OK*` | Triggers produit/catégorie présents et associés aux scénarios SQL. |
| `D3-L7-03` | `OK*` | Triggers de commande et d’historique présents. |
| `D3-L7-04` | `OK*` | Documentation des 16 triggers présente. |
| `D3-L7-05` | `OK*` | Tests directs `T-13`…`T-24` présents dans la suite complète. |

### D4 — Application web

| ID | État audité | Constat et preuve |
|---|---|---|
| `D4-L8-01` | `OK*` | Front controller et routeur présents ; tests de routes disponibles. |
| `D4-L8-02` | `OK*` | Configuration PDO et options centralisées présentes. La connexion SQL de la sandbox n’est pas disponible. |
| `D4-L8-03` | `OK*` | Layout, vues et helper d’échappement présents ; contrôles statiques de vues disponibles. |
| `D4-L8-04` | `OK*` | Authentification, sessions et CSRF présents ; contrôles sécurité automatisés. |
| `D4-L8-05` | `PARTIEL` | Exemple `.env.php` et CI présents, mais `public/install.php` n’existe pas ; l’installation est portée par `scripts/db_init.php`/`run.sh`. Ce choix peut être préférable, mais la tâche doit être corrigée explicitement plutôt que cochée telle quelle. |
| `D4-L9-01` | `OK*` | Catalogue, pagination et tests correspondants présents. |
| `D4-L9-02` | `OK*` | Recherche/filtres/tri avec validation serveur et tests présents. |
| `D4-L9-03` | `OK*` | Fiche produit, visibilité et contrôles de stock présents. |
| `D4-L9-04` | `ÉCART` | Le contrôleur/repository crée un compte via le magasin générique ; aucun appel applicatif à `sp_create_account` n’est trouvé. |
| `D4-L9-05` | `ÉCART` | La connexion lit le magasin via le repository ; aucun appel applicatif à `sp_get_credentials` n’est trouvé. |
| `D4-L9-06` | `PARTIEL` | Parcours et tests automatisés présents, mais le parcours complet n’est pas consigné comme recette humaine ; la configuration par défaut est JSON, pas la chaîne SQL imposée par le CDC. |
| `D4-L10-01` | `OK*` | Panier de session, calculs serveur et tests présents. |
| `D4-L10-02` | `OK*` | Contrôles serveur de disponibilité avant ajout présents et testés. |
| `D4-L10-03` | `OK*` | Vues panier/récapitulatif/badge présents et couverts par les tests UI/HTTP. |
| `D4-L10-04` | `ÉCART` | Le parcours applicatif de création de commande ne passe pas par `sp_create_order_from_basket` ; le repository réalise les opérations via le magasin. |
| `D4-L10-05` | `PARTIEL` | Écrans et tests existent, mais le chemin applicatif SQL/procédure exigé n’est pas démontré de bout en bout. |
| `D4-L10-06` | `PARTIEL` | Historique/annulation et tests existent, mais les routes de l’application ne démontrent pas l’appel à la procédure SQL prévue ; les scénarios humains restent non joués. |
| `D4-L11-01` | `OK*` | Authentification et rôles administratifs présents, avec contrôles d’accès. |
| `D4-L11-02` | `ÉCART` | Les opérations produit de l’application passent par le repository/store ; aucun appel applicatif à `sp_save_product`/`sp_delete_product` n’est trouvé. |
| `D4-L11-03` | `ÉCART` | Les opérations catégorie/stock de l’application passent par le repository/store ; appels à `sp_save_category`/`sp_adjust_stock` non trouvés. |
| `D4-L11-04` | `ÉCART` | Les changements de commande utilisent le magasin/repository ; appel applicatif à `sp_update_order_status` non trouvé. |
| `D4-L11-05` | `OK*` | Vues back-office et tests de tableaux/formulaires présents. |
| `D4-L11-06` | `PARTIEL` | Parcours admin automatisé et interfaces présents ; aucune preuve de démonstration/recette humaine complète n’est inscrite au suivi. |
| `D4-L12-01` | `OK*` | Vérification CSRF sur les mutations, testée par les contrôles sécurité et tests HTTP. |
| `D4-L12-02` | `OK*` | Contrôle d’appartenance/IDOR présent et testé. |
| `D4-L12-03` | `OK*` | Échappement des vues contrôlé par le scanner statique et les tests. |
| `D4-L12-04` | `OK*` | En-têtes, cookies et erreurs génériques sont contrôlés par les tests disponibles. |
| `D4-L12-05` | `OK*` | `tests/security/controles.sh` existe et ses **10/10** contrôles viennent d’être rejoués avec succès localement. La todolist le laissait encore à faire. |
| `D4-L13-01` | `OK*` | JavaScript panier/AJAX présent, avec tests DOM/HTTP annoncés. |
| `D4-L13-02` | `OK*` | Validation côté client présente ; la validation serveur demeure la source d’autorité. |
| `D4-L13-03` | `OK*` | Labels, annonces et comportements clavier/focus couverts par les contrats disponibles. |
| `D4-L13-04` | `OK*` | Repli sans JavaScript spécifié et automatisé ; ne pas le confondre avec une recette manuelle non jouée. |

### D5 — Tests et validation

| ID | État audité | Constat et preuve |
|---|---|---|
| `D5-L14-01` | `PARTIEL` | Docs de stratégie et de recette présents, mais des affirmations de complétude/date et de nombres doivent être réconciliées. |
| `D5-L14-02` | `OK*` | 29 scénarios SQL existent ; CI MySQL/MariaDB correspondante enregistrée comme verte pour le SHA, avec réserve de date au §2. |
| `D5-L14-03` | `PARTIEL` | Couverture automatisée élargie au-delà de F-01…F-31 ; cependant les 200 scénarios manuels restent `NON JOUÉ`, donc « exécution + preuves » n’est pas intégralement achevé. |
| `D5-L14-04` | `PARTIEL` | `tests/charge/reserver.sh` existe et le stress JSON est couvert ; l’essai HTTP/SQL 20 demandes sur 12 unités reste à exécuter sur une base isolée/cible. |
| `D5-L14-05` | `PARTIEL` | Matrice de traçabilité présente ; PV non signé et campagne manuelle non renseignée. |
| `D5-L14-06` | `[!]` | Les scripts de mesure sont présents, mais p95 HTTP/SQL sur la volumétrie et la machine cible n’est pas prouvé ici. Ne pas reprendre les chiffres historiques du CDC comme mesure actuelle sans rapport rejouable. |

### D6 — Soutenance

| ID | État audité | Constat et preuve |
|---|---|---|
| `D6-L16-01` | `OK*` | Le support existe : `docs/12-diaporama-soutenance.md` et `livrables/minishop-soutenance.pptx` (ainsi que PDF/DOCX). La todolist le laissait partiel. |
| `D6-L16-02` | `OK*` | Trame et questions/réponses présentes dans `docs/03-support-soutenance.md`. |
| `D6-L16-03` | `[!]` | Les deux répétitions chronométrées avec la démo réelle ne sont pas attestées. |

## 4. Écart majeur entre le CDC et la réalisation

Le point bloquant le plus important n’est pas l’absence des procédures SQL : elles sont dans `sql/02_minishop_procedures.sql`. C’est **leur non-raccordement à l’application** :

1. `app/Model/Data/StorageDriver.php` déclare `JSON` comme `DEFAULT` et décrit SQL comme cible de migration ; le README confirme JSON par défaut et SQL optionnel.
2. Une recherche des appels applicatifs `callProcedure()`, `appeler()` et `CALL sp_…` ne trouve que les méthodes définies dans `SqlStore` et des appels du générateur de volumétrie ; aucun appel de procédure par les repositories applicatifs n’est présent.
3. `tests/php/sql_integration.php` valide des procédures/triggers et certaines intégrations `SqlStore`, mais le parcours HTTP/E2E principal est initialisé sur un magasin JSON. Cela **ne prouve pas** l’application complète en mode PDO/MySQL exécutant les procédures demandées.

Cela contredit notamment les critères du CDC CT-02/CT-03 et les tâches D4-L9-04/05, D4-L10-04 et D4-L11-02/03/04, ainsi que les affirmations de §16.2 selon lesquelles les procédures sont effectivement appelées par l’application. Il faut soit raccorder réellement ces procédures et ajouter une recette applicative SQL de bout en bout, soit faire valider une modification formelle du CDC. Les seuls tests SQL de la base ne suffisent pas pour cocher ces tâches à 100 %.

## 5. Incohérences à corriger dans le CDC et la todolist

1. **Comptage des tâches :** l’en-tête de `docs/06` annonce « 86 tâches terminées, 14 partielles, 34 à faire », soit **134** statuts, alors que le tableau détaillé contient 86 identifiants uniques.
2. **Heures :** l’en-tête indique 169 h faites / 191 h restantes ; §1.1 indique 162 h / 198 h. Les heures restantes par personne font 93 + 105 = 198, tandis que §8 donne 95 + 97 = 192. Les heures ne se réconcilient donc pas.
3. **Progression D4 périmée :** `docs/06` affiche encore 0/146 h et 0 %, malgré l’application complète présente. `scripts/load_db.sh`, `tests/security/controles.sh` et le diaporama existent également alors que leurs lignes sont encore « à faire »/« partiel ».
4. **Équipe :** `docs/05` et §13 du CDC planifient 3 développeurs / 360 h ; `docs/06` passe à 2 développeurs mais conserve 360 h et des tableaux de charge qui ne concordent pas. Il faut documenter le choix réel et recalculer les charges, pas supposer la composition.
5. **Exigences non fonctionnelles :** §4.2 et l’annexe 17.3 du CDC annoncent `ENF-01…ENF-16`, alors que `docs/annexes/annexe-enf.md` contient `ENF-01…ENF-22`.
6. **Sécurité :** `SEC-14` est cité dans §8.9, mais n’a pas de fiche dédiée dans la séquence §8.1–§8.7 ni de test identifié dans la stratégie de tests. Clarifier si cet identifiant est une exigence, puis lui attribuer un contrôle.
7. **Règles métier :** §6.1 annonce 8 règles complémentaires, puis l’encadré en annonce 9 ; `RB-09` et `RB-12…RB-20` représentent dix identifiants. Le partage « imposées / ajoutées » doit être recalculé.
8. **Séquences UML :** cinq PNG/sources de séquence sont présents, mais le pointage §1.7 affirme six diagrammes rendus. Le minimum de quatre est satisfait ; c’est le compte annoncé et les deux diagrammes bonus qui sont à mettre en cohérence.
9. **Dates/branche :** CDC §16.4, `docs/10-todo-corrections.md` et `docs/17-audit-global-projet.md` font état du 28/10/2026, postérieur à la date d’audit 23/10/2026 ; `docs/10` nomme aussi une autre branche (`arena/6e1d3706-sae-302`). Le checkout et le run CI portent, eux, des dates du 12/11/2026. Aucune de ces dates ne doit être recyclée comme preuve datée sans réconciliation.
10. **Source officielle absente :** le CDC prétend pointer ligne par ligne `sujetSAE.pdf`, mais ce PDF n’est pas suivi dans le checkout. L’audit peut comparer CDC, WBS, todolist, code et tests ; il ne peut pas refaire le pointage contre le sujet original absent.

## 6. Éléments de travail absents de la todolist d’origine

La todolist reprend la WBS de septembre, pas le périmètre effectivement ajouté ensuite. Il faut au minimum créer des entrées traçables (avec exigences, test et état) pour :

- préférences FR/EN et fuseau local, affichage/stockage UTC ;
- formulaire Contact et pages d’information/légales correspondant au périmètre de maquette ;
- responsive automatisé et tableaux mobiles étiquetés ;
- conservation, rétention, nettoyage/archives et isolation sûre des tests ;
- nouveautés/dates produits, alternatives de visuels et non-régression d’accessibilité ;
- revue de sécurité/test infrastructure, parcours E2E et migration MariaDB.

Ces éléments peuvent être **déjà livrés** dans le code et les tests : leur absence de la todolist signifie que la liste n’est pas exhaustive, pas qu’il faut les re-développer. La partie responsive reste à suivre uniquement dans les tests automatisés, conformément à la décision projet.

## 7. Prochaines actions recommandées

1. Traiter en priorité l’écart **JSON / PDO / procédures stockées** et décider si le CDC reste contractuel sur l’exécution MySQL par l’application.
2. Rebaser `docs/06-todolist.md` sur le code/test actuels, conserver les anciennes heures uniquement comme estimation historique et ajouter séparément les évolutions post-WBS.
3. Réconcilier les nombres, identifiants et dates des documents listés au §5 ; ne cocher que ce qui a une preuve datée et reproductible.
4. Garder `[!]` pour les 200 scénarios manuels tant que l’équipe ne les a pas exécutés ; ne pas marquer « recette 100 % » sur la seule base de la CI.
5. Planifier sur environnement isolé les tests de performance, la charge SQL, l’installation/restauration et le contrôle de déploiement ; conserver les sorties comme preuves.
6. Faire attester séparément les tâches GitLab, la composition/validation pédagogique et les répétitions de soutenance. Le dépôt IUT n’a pas été interrogé dans cet audit.

**Aucune modification du code applicatif n’a été faite pendant cet audit.** Ce document formalise les constats sans modifier artificiellement les statuts historiques de `docs/06-todolist.md`.
