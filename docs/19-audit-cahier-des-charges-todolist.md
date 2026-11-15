# Audit de conformité — cahier des charges, todolist et preuves

**Révision examinée :** date de coupure documentaire retenue pour l’audit historique : 23/10/2026. La branche active `arena/10afc76d-sae-302` est issue du commit `e0c554f` ; ses métadonnées Git indiquent le 13/11/2026, postérieure à la coupure. Le checkout n’est donc pas présenté comme un instantané qui aurait existé au 23/10 : cette date qualifie uniquement les constats historiques et le run restreint consigné en §2. Un addendum séparé décrit les contrôles non-SQL du 15/11/2026 sur l’état ultérieur du checkout. Aucun accès au dépôt IUT ni au clone personnel inaccessible n’a été effectué.

> **Addendum du 15/11/2026 — état postérieur à la coupure.** Dans le checkout du commit `b8edfc9` (branche `arena/10afc76d-sae-302`), SQL/MySQL/PDO est le pilote par défaut et JSON reste une option explicite. `node scripts/run-all-tests.mjs --sans-sql` a terminé avec le code 0 via PHP-WASM 8.2 ; il inclut l’E2E HTTP isolé (338 vérifications) et exclut l’intégration SQL. Le test distinct `node tests/node/preferences_test.mjs` a aussi terminé avec le code 0 (48 vues FR/EN, 45 messages métier). Ces résultats ne valident ni PDO/MySQL, ni PHP CLI natif, ni un navigateur réel ; l’analyse de compilation native reste NON JOUÉE. Les détails et les autres contrôles postérieurs figurent dans la section « Addendum » ci-dessous.

> **Conclusion courte : non, la todolist n’est ni à jour ni suffisamment complète pour affirmer que toutes ses tâches sont achevées à 100 %.** Dans le checkout `b8edfc9` vérifié le 15/11/2026, SQL/MySQL/PDO est le pilote par défaut, JSON une option explicite, et le scan statique repère un point d’appel repository pour chacune des 18 procédures. Ce raccordement n’a pas été rejoué sous PDO/MySQL et ne prouve pas un parcours HTTP complet. Les tests humains, mesures de cible et actions externes restent distincts ; les mentions futures ne sont pas des preuves à la coupure historique du 23/10. Les heures et statuts de la todolist demeurent une photographie de planification, pas un relevé d’exécution.
>
> Ce rapport audite les fichiers versionnés et le working tree de ce checkout, sans consulter directement le dépôt IUT. Les éléments externes rapportés sont distingués selon leur provenance ; ils ne présument pas l’état du clone local de l’utilisateur et ne transforment pas une tâche externe ou humaine en tâche accomplie sans preuve.

## 1. Périmètre et verdict global

`docs/06-todolist.md` et `docs/05-wbs-projet.md` ont été régénérées en version 1.4/1.2 : 84 tâches, 355 h estimées. Après révision de D1-L1-07 pour accepter un suivi asynchrone, les statuts sont 37 terminées, 8 partielles et 39 à faire ; 164 h sont **classées terminées** et 191 h restent estimées. Ces heures découlent des estimations résiduelles, pas d’un relevé de temps réel. D1-L1-07 est clos sur la synthèse des retours continus attestés par l’utilisateur ; aucune réunion complète n’est revendiquée. La relecture croisée de D2-L2-06 reste ouverte. Dans D3, 11/98 h restent estimées, dont les 4 h de référence de D3-L7-05 non créditées comme réalisées ; dans D4, 4/146 h sont classées terminées (le contrôle de sécurité) et 142 h restent. Cela ne signifie ni que l’application est achevée ni que les autres tâches sont rejouées. Le périmètre déploiement/staging est retiré sans tâche de remplacement ; aucun déploiement n’est revendiqué.

| Statut d’audit | Nombre sur 84 | Sens |
|---|---:|---|
| `OK` | 3 | Critère de fin documentaire attesté selon la définition révisée de la WBS, sans élargir la portée de la preuve. |
| `OK*` | 61 | Livrable documentaire ou contrôle ciblé présent ; les limites et attestations déclaratives sont précisées ci-dessous. **Ce n’est pas une certification absolue ni une validation pédagogique.** |
| `PARTIEL` | 16 | Le code ou le livrable est présent, mais l’exécution PDO/MySQL, un critère, une preuve ou une validation humaine reste à établir. |
| `ÉCART` | 0 | Aucun écart statique de raccordement de procédure n’est retenu après le scan repository ; les validations d’exécution restent ouvertes. |
| `[!]` | 4 | Tâche humaine, externe ou mesure cible impossible à vérifier depuis ce checkout. |
| **Total** | **84** | Les tâches `PARTIEL`, `ÉCART` et `[!]` restent ouvertes ; les réserves des `OK*` sont précisées dans les constats. |

Ces statuts évaluent les **84 tâches de la WBS de référence**, pas toutes les évolutions postérieures. Les ajouts (langue/fuseau, informations légales, contact, conservation, etc.) sont reliés dans le CDC aux `EV-*` et aux scénarios `F-*`, mais leur absence de la WBS ne prouve ni leur exécution ni leur validation.

**Datation des contenus et des contrôles :** la coupure documentaire retenue est le 23/10/2026 ; le commit de base porte une date Git du 13/11/2026, postérieure à cette coupure, et ne peut donc être présenté comme un instantané historique du 23/10. Des fichiers versionnés portent aussi des dates postérieures à la coupure (rapport SQL du 27/10, archives/to-do du 28/10 et références CI des 12–13/11). Ces métadonnées ne prouvent aucun événement ni résultat acquis au 23/10. Les contrôles historiques de §2 ont été rejoués le 23/10/2026 sur le working tree consulté ; ils n’établissent pas l’état d’un autre SHA. Les exécutions postérieures du 15/11 sont isolées dans l’addendum et ne sont pas réattribuées à la coupure. Les métriques d’archive sans run/SHA ne valent toujours pas preuve de l’état courant.

## 2. Vérifications effectuées et limites

### Preuve externe fournie par l’utilisateur — clone IUT

Le relevé terminal communiqué montre `origin` configuré sur `https://iut-git.unice.fr/but2_s302/s302.git` et une requête `git ls-remote --heads origin` réussie. Les références visibles sont `chahd`, `dev`, `pj509414/dev` (SHA `bcca4d1d527b2ef859295a44dd1c65134aa93ab5`) et `pj509414/poc-auth-jwt` ; aucune référence `main` n’apparaît dans cette sortie. Cela prouve que le dépôt existe et est lisible depuis ce clone. La capture GitLab « Membres » fournie montre l’enseignante NGUYEN Thanh-Phuong comme membre direct avec le rôle `Reporter`, et Chahd comme `Developer` hérité du groupe ; elle confirme l’accès de l’enseignante au projet. L’utilisateur confirme avoir envoyé le courriel de composition rédigé dans la conversation, qui comprenait l’adresse GitLab ; cette confirmation n’a pas été vérifiée indépendamment dans la boîte d’envoi.

### Source officielle fournie par l’utilisateur — `sujetSAE.pdf`

Le PDF officiel joint a été lu pour vérifier les exigences pédagogiques concernées. À la **page 8**, il demande d’envoyer par courriel la composition du groupe à `thanh-phuong.nguyen@univ-cotedazur.fr` avec l’objet `Composition SAE`, puis de communiquer l’adresse GitLab et de partager l’accès avec l’enseignante. Il demande aussi de livrer un script SQL de génération de la base (absence pénalisée de 3 points) et un document de tests/validation ; la page 9 rappelle la pénalité pour l’absence de ce document et demande une contribution régulière sur GitLab ainsi qu’un README d’installation. **Le sujet ne demande ni base de test distincte ni nom/suffixe particulier tel que `*_test`.** Le CDC a donc été épuré de toute base séparée pour la CI GitHub ; il indique le plan de réaliser les essais pré-démo sur l’instance LineServer puis de la réinitialiser avant la démonstration. Les détails techniques des campagnes automatisées restent dans le document de tests/validation, et ne constituent pas une exigence pédagogique. Le PDF ne demande pas de dépôt privé, ne mentionne pas de branche `main` protégée, de blocage du push direct, de squash ou de suppression automatique de branche source. La mention « pages privées » à la page 5 concerne les pages de l’application, pas la visibilité du dépôt. Le privé et les règles de branche figurant dans la WBS/annexe GitLab sont donc des choix ajoutés par les documents de projet, pas des exigences vérifiées du sujet officiel.

### Contrôles historiques à la coupure — 23/10/2026 (SHA non conservé)

Les sorties historiques ci-dessous sont rapportées telles qu’elles figurent dans l’archive ; le SHA de cette exécution n’a pas été conservé, elles ne prouvent donc pas l’état du checkout vérifié le 15/11.

- `python3 scripts/verifier_fiches_uc.py` : **46 contrôles documentaires** réussis ; 14 UC, 164 scénarios, 36 couples de transitions.
- `docs/13-suivi-recette-uc.csv` : **200 entrées `NON JOUÉ`** ; aucune campagne manuelle n’est déclarée.
- `node scripts/wasm_verdict.mjs` : **9/9** cas de verdict réussis.
- `node scripts/run-all-tests.mjs --sans-sql --sans-e2e` : terminé avec le code **0** via PHP-WASM 8.2. Les suites activées hors SQL/E2E sont vertes, dont T-01…T-29 sur JSON (**29/29**), `unit_tests.php` (**297/297 vérifications**), PHPUnit local (**57 tests**) et `strict_types_lint.php` (**167 fichiers, 0 violation**). Cette commande excluait explicitement l’intégration SQL et l’E2E ; l’analyse de compilation avec PHP CLI natif reste NON JOUÉE.
- `python3 -m unittest discover -s tests/python -v` : **42 tests**, 22 réussis et 20 ignorés faute de PHP CLI natif ou d’extensions POSIX. Aucun test natif n’est réputé passé par les tests ignorés.
- `bash -n tests/security/controles.sh && bash tests/security/controles.sh` : **11/11 contrôles statiques** réussis ; ce n’est ni un test offensif ni une recette applicative.
- `node --check` : **41** fichiers JavaScript/ES modules sans erreur de syntaxe ; `bash -n` : **13** scripts Shell sans erreur ; `py_compile` : 3 outils Python compilés sans erreur.

### Limites du run restreint du 23/10/2026

Dans cette sandbox, `php`, `composer`, `mysql` et `mariadb` étaient absents. Le run PHP-WASM du 23/10 a explicitement utilisé `--sans-sql --sans-e2e` : aucune base temporaire n’a été créée, et l’E2E HTTP a été sauté. Ce résultat ne constitue pas une validation SQL, un parcours HTTP SQL ou une analyse avec PHP CLI natif (NON JOUÉE). `node_modules/` et `vendor/` ne sont pas des livrables versionnés.

### Addendum — contrôles postérieurs du 15/11/2026

Ces résultats portent sur le checkout du commit `b8edfc9` (branche `arena/10afc76d-sae-302`), pas sur l’état à la coupure du 23/10. Le run `node scripts/run-all-tests.mjs --sans-sql` a terminé avec le code **0** via PHP-WASM 8.2 ; T-01…T-29 sur JSON (**29/29**), 297 vérifications, 57 tests PHPUnit, linter strict sur 167 fichiers sans violation et E2E HTTP isolé (**338 vérifications**, fichiers hôte témoins inchangés). `node tests/node/preferences_test.mjs` a également terminé avec le code **0** (48 vues FR/EN et 45 messages métier). La commande exclut l’intégration SQL ; aucun PDO/MySQL ni parcours HTTP SQL n’a été validé. PHP CLI natif et les outils SQL restent absents ; l’analyse de compilation native est NON JOUÉE, et aucun navigateur réel n’a été utilisé.

- Les générateurs `.tools/build_doc04.py`, `.tools/build_doc04_html.py`, `.tools/build_wbs.py` et `.tools/build_todolist.py` ont été rejoués le 15/11 : `docs/04`, `docs/05`, `docs/06` et leurs HTML sont synchronisés ; l’inventaire doc04 mentionne CT-01…CT-12, ENF-01…ENF-22 et SQL par défaut/JSON explicite ; WBS/TODO restent à 84 tâches, 355 h estimées, 164 h classées terminées et 191 h restantes.
- `PATH="/tmp/minishop-venv/bin:$PATH" bash scripts/build-docs.sh` s’est terminé sans erreur et a produit **91 rendus** HTML/PDF/DOCX/diaporama dans `livrables/`.
- `git diff --check` et la vérification de l’arbre généré sont à rejouer après les ultimes mises à jour documentaires ; aucun résultat de CI globale n’est revendiqué.

### CI et mentions postérieures à la coupure

Aucun résultat CI global pour le checkout vérifié le 15/11 n’est revendiqué. À la coupure du 23/10, les références à des exécutions des 12–13/11 et la mention de `tests/sql/rapport_tests_sql.md` (29/29, datée du 27/10) étaient futures et ne pouvaient servir de preuve historique. Au 15/11, ce rapport versionné reste une affirmation distincte, sans preuve de rattachement au checkout testé ici ; le run local du 15/11 utilisait `--sans-sql` et ne valide donc pas PDO/MySQL. La validation SQL du 23/09 relatée par `docs/04` demeure historique et n’a pas été rejouée dans cette révision.

### Résultat humain / cible réelle

Les 200 scénarios du registre de recette restent `NON JOUÉ`. La campagne responsive multi-largeurs relève des tests automatisés prévus, pas des fiches manuelles. Aucun test manuel LineServ, déploiement, restauration de base, mesure de performance cible, relecture croisée étudiante, approbation, validation pédagogique ou répétition de soutenance n’est attesté.

## 3. Revue tâche par tâche — les 84 identifiants de `docs/06-todolist.md`

`OK` signifie que le critère de fin explicite de la WBS est satisfait, avec la preuve relevée, pour le périmètre précis de la tâche. `OK*` signifie « preuve d’un livrable, d’une suite ou confirmation humaine fournie », sans certifier tous les environnements, critères métier et vérifications humaines. Une confirmation déclarative de l’utilisateur est distinguée d’une vérification indépendante. Les raisons données priment sur le statut historique de la todolist.

### D1 — Pilotage, dépôt et rendu

| ID | État audité | Constat et preuve |
|---|---|---|
| `D1-L1-01` | `OK*` | Le sujet officiel (PDF fourni, p. 8) demande le courriel de composition, la communication de l’adresse GitLab et le partage de l’accès avec l’enseignante. L’utilisateur confirme avoir envoyé le courriel proposé, avec la composition du groupe et l’adresse du dépôt ; la capture « Membres » montre l’enseignante comme membre directe `Reporter`, et `git ls-remote` confirme que le dépôt est lisible. L’envoi est attesté par l’utilisateur, mais n’a pas été vérifié indépendamment dans la boîte d’envoi. Le privé, la protection de `main`, le squash et la suppression des branches source sont des ajouts de la WBS/annexe, pas des exigences du PDF officiel. |
| `D1-L1-02` | `OK*` | `app/` et `public/` existent et sont peuplés ; le besoin d’un squelette vide est dépassé. |
| `D1-L1-03` | `OK*` | Le README présente les deux membres nommés et les permissions GitLab indiquées (`Owner` pour Johan Polsinelli, `Developer` pour Chahd Gouiaa), séparément des lettres A/B/C du RACI. Aucune correspondance nominative A/B/C n’est affirmée. L’enseignante NGUYEN Thanh-Phuong (`Reporter`) est indiquée séparément. Le livrable est présent ; sa lecture effective par chaque membre n’est pas vérifiable depuis le dépôt. |
| `D1-L1-04` | `OK` | La capture utilisateur montre l’issue GitLab `s302#2` créée avec les cinq livrables du barème et les pénalités §16.2 ; cela satisfait la définition de fin explicite de la WBS (« issue créée : 5 livrables + pénalités suivis »). L’épinglage n’est pas confirmé par la capture et n’est pas revendiqué ; ce `OK` porte sur ce critère de fin. |
| `D1-L1-05` | `OK` | La charte versionnée `docs/20-charte-gitlab-revues-croisees.md` et les modèles MR livrent le critère documentaire demandé. Ce `OK` valide uniquement la documentation ; aucune utilisation réelle de MR ni revue croisée n’est prouvée. |
| `D1-L1-07` | `OK` | Critère de fin révisé avec l’utilisateur : consigner les retours continus en mode asynchrone plutôt que prétendre à une réunion complète. `docs/21-synthese-retours-audit.md` rassemble les choix documentés ; l’utilisateur atteste que des feedbacks continus ont eu lieu. Ce `OK` clôt la synthèse écrite, sans affirmer qu’une réunion, une approbation collective ou une validation pédagogique s’est tenue. |
| `D1-L15-02` | `OK*` | README final présent ; `scripts/build-docs.sh` et l’aide des comptes de démonstration existent. La composition et les permissions GitLab sont indiquées séparément du RACI ; aucune correspondance nominative A/B/C n’est inventée. |
| `D1-L15-03` | `[!]` | Aucun tag n’est présent dans les références de ce checkout ; nettoyage et tag final sur le dépôt IUT non vérifiables sans accès/demande explicite. |

### D2 — Cahier des charges et UML

| ID | État audité | Constat et preuve |
|---|---|---|
| `D2-L2-01` | `OK*` | §1–§2 distinguent la coupure documentaire du 23/10/2026, les contrôles locaux rejoués ce jour-là et la date Git du commit de base (13/11, postérieure). Le checkout n’est pas présenté comme un instantané historique au 23/10 ; les mentions futures et les métriques sans SHA restent hors preuve. |
| `D2-L2-02` | `OK*` | Le CDC/WBS décrivent les codes A/B/C comme rôles de planification non nominatifs ; R/A/C/I du RACI et permissions GitLab sont séparés. Les 355 h (A 117, B 117, C 121) sont des estimations par rôle, sans correspondance avec les deux membres nommés ni capacité ou temps réel attribués. |
| `D2-L2-03` | `OK*` | CDC §4.1 relie EF/EV aux UC, écrans, règles et identifiants F/T/S ; chaque UC renvoie à l’annexe 17.1, les scénarios à `docs/02` et les fiches/registre à `docs/13`. La matrice `docs/02` §9 récapitule ces liens. La traçabilité documentaire ne vaut pas exécution ; 200 entrées restent `NON JOUÉ`. |
| `D2-L2-04` | `OK*` | Le CDC distingue la cible contractuelle PHP/MySQL/PDO de l’état du checkout : SQL est le pilote par défaut et JSON reste une option explicite. Le scan statique relève un point d’appel repository pour les 18 procédures, sans prétendre à une validation d’exécution PDO/MySQL. |
| `D2-L2-05` | `OK*` | SEC-14 possède une fiche et est reliée au contrôle statique ciblé S-11 ; les 10 scénarios offensifs S-01…S-10 sont distingués des 11 contrôles statiques. Le rejeu local 11/11 du 23/10 est limité à ce script et ne prouve ni test offensif ni sécurité générale. |
| `D2-L2-06` | `PARTIEL` | Le pointage documentaire des pénalités est présent dans §16.2 ; aucune relecture croisée entre étudiants distincts n’est attestée. La tâche reste ouverte jusqu’à une trace datée d’auteur et relecteur distincts. |
| `D2-L3-01` | `OK*` | Les trois vues de diagrammes de cas d’utilisation sont présentes sous `docs/diagrams/`. |
| `D2-L3-02` | `OK*` | Les 14 UC et descriptions sont documentés ; le vérificateur documentaire passe 46 contrôles. Cela valide la structure des fiches, pas leur exécution manuelle. |
| `D2-L3-03` | `OK*` | Diagramme de classes et justifications documentés et rendus. |
| `D2-L3-04` | `OK*` | Le CDC compte 20 identifiants : RB-01…RB-11 classées par convention comme venant du sujet et RB-12…RB-20 comme neuf compléments. Le PDF source n’est pas versionné dans le checkout ; la classification est explicite, sans prétendre à une validation pédagogique. |
| `D2-L3-05` | `OK*` | Cinq séquences sont listées et rendues : quatre prévues pour les cas imposés et une bonus catalogue/fiche. Les activités/états sont des types distincts ; aucune sixième séquence n’est annoncée. Elles restent des conceptions, pas une preuve d’intégration SQL. |
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
| `D3-L6-05` | `OK*` | Procédures de statut, annulation client et administrateur (`sp_cancel_order_admin`), rapport et frais de port présentes ; le nombre actuel est de 18 procédures. |
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
| `D4-L9-04` | `PARTIEL` | `ClientRepository` délègue à `ClientAccountGateway` ; la stratégie SQL appelle `sp_create_account` via `StoredProcedureExecutor`. SQL est le défaut ; le test d’intégration SQL et l’appel PDO réel restent à rejouer dans cet environnement. |
| `D4-L9-05` | `PARTIEL` | La stratégie SQL appelle `sp_get_credentials` ; `password_verify()` reste en PHP. SQL est le défaut, mais l’appel PDO réel et le parcours ne sont pas validés par exécution ici. |
| `D4-L9-06` | `PARTIEL` | Parcours et tests automatisés présents, mais aucune recette humaine complète n’est consignée et le parcours HTTP SQL n’a pas été rejoué. Les tests configurés en JSON restent une voie de test isolée, pas le pilote applicatif par défaut. |
| `D4-L10-01` | `OK*` | Panier de session, calculs serveur et tests présents. |
| `D4-L10-02` | `OK*` | Contrôles serveur de disponibilité avant ajout présents et testés. |
| `D4-L10-03` | `OK*` | Vues panier/récapitulatif/badge présents et couverts par les tests UI/HTTP. |
| `D4-L10-04` | `PARTIEL` | `CommandeRepository` appelle statiquement `sp_create_order_from_basket` sur le pilote SQL. L’appel n’a pas été rejoué sous PDO/MySQL et aucun parcours HTTP SQL complet n’est revendiqué. |
| `D4-L10-05` | `PARTIEL` | Écrans, tests et appels repository aux procédures de commande sont présents statiquement ; le chemin PDO/MySQL n’est pas démontré de bout en bout. |
| `D4-L10-06` | `PARTIEL` | Historique/annulation et appels repository aux procédures client/admin sont présents ; les scénarios humains restent non joués et l’exécution PDO/MySQL n’est pas validée. |
| `D4-L11-01` | `OK*` | Authentification et rôles administratifs présents, avec contrôles d’accès. |
| `D4-L11-02` | `PARTIEL` | `ProduitRepository` contient des appels statiques aux procédures d’enregistrement et de suppression ; leur exécution PDO/MySQL n’a pas été validée. |
| `D4-L11-03` | `PARTIEL` | `CategorieRepository` et `ProduitRepository` appellent statiquement les procédures catégorie/stock ; leur exécution PDO/MySQL reste à vérifier. |
| `D4-L11-04` | `PARTIEL` | `CommandeRepository` contient l’appel statique à `sp_update_order_status` ; l’exécution SQL et le parcours HTTP restent à valider. |
| `D4-L11-05` | `OK*` | Vues back-office et tests de tableaux/formulaires présents. |
| `D4-L11-06` | `PARTIEL` | Parcours admin automatisé et interfaces présents ; aucune preuve de démonstration/recette humaine complète n’est inscrite au suivi. |
| `D4-L12-01` | `OK*` | Vérification CSRF sur les mutations, testée par les contrôles sécurité et tests HTTP. |
| `D4-L12-02` | `OK*` | Contrôle d’appartenance/IDOR présent et testé. |
| `D4-L12-03` | `OK*` | Échappement des vues contrôlé par le scanner statique et les tests. |
| `D4-L12-04` | `OK*` | En-têtes, cookies et erreurs génériques sont contrôlés par les tests disponibles. |
| `D4-L12-05` | `OK*` | `tests/security/controles.sh` contient **11 contrôles statiques** ; 11/11 ont été rejoués localement le 23/10, dont S-11/SEC-14. Le périmètre reste celui des sources inspectées par le script. |
| `D4-L13-01` | `OK*` | JavaScript panier/AJAX présent ; le run PHP-WASM du 15/11 a inclus l’E2E HTTP isolé (338 vérifications), et `preferences_test.mjs` a validé le DOM FR/EN (48 vues, 45 messages métier). Ces preuves sur JSON isolé ne valident ni navigateur réel ni PDO/MySQL. |
| `D4-L13-02` | `OK*` | Validation côté client présente ; la validation serveur demeure la source d’autorité. |
| `D4-L13-03` | `OK*` | Labels, annonces et comportements clavier/focus couverts par les contrats disponibles. |
| `D4-L13-04` | `OK*` | Repli sans JavaScript spécifié et automatisé ; ne pas le confondre avec une recette manuelle non jouée. |

### D5 — Tests et validation

| ID | État audité | Constat et preuve |
|---|---|---|
| `D5-L14-01` | `OK*` | `docs/02` distingue stratégie, scénarios, rapports historiques, preuve locale de sécurité et recette humaine ; le CSV reste explicitement `NON JOUÉ`. |
| `D5-L14-02` | `PARTIEL` | Les 29 scénarios et leur harnais sont présents, mais aucun rejeu SQL/PDO du checkout vérifié le 15/11 n’a eu lieu : le run local utilisait `--sans-sql`. Dans `docs/06-todolist.md`, la tâche reste `⬜` avec 4 h estimées à faire. Le rapport du 27/10 est une affirmation d’archive non rattachée à ce checkout et ne remplace pas le rejeu requis. |
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

## 4. Intégration SQL et limites de validation

L’état actuel n’est plus celui de l’audit initial : SQL/MySQL/PDO est le pilote par défaut et JSON reste une option explicite (`STORAGE_DRIVER=json`). Les sources SQL définissent 18 procédures ; le scan statique des repositories repère un point d’appel pour chacune, dont `sp_cancel_order_admin`. Les capacités SQL spécialisées restent séparées par interfaces, notamment pour l’exécution de procédures et les résultats multiples.

Ce constat est **statique**. Aucun rejeu PDO/MySQL de cette révision ni parcours HTTP complet sur SQL n’est revendiqué. Les tests SQL sont configurés sur des bases temporaires, distinctes d’une cible manuelle ; l’absence de client MySQL/MariaDB dans cette sandbox empêche le rejeu local. Les tests JSON restent explicitement isolés et ne démontrent pas l’exécution SQL.

En conséquence, D4-L9-04/05, D4-L10-04/05/06 et D4-L11-02/03/04 sont classées `PARTIEL` : les appels repository sont repérés, mais leur exécution réelle et la validation de bout en bout restent à établir. Aucun écart statique de raccordement n’est retenu à ce scan ; les tests SQL directs seuls ne suffisent pas à prouver le parcours HTTP.

## 5. Constats réconciliés au 23/10/2026

1. **Tâches et périmètre :** 84 tâches de niveau 4 ; le déploiement/staging est retiré sans remplacement. Après clôture de D1-L1-07 comme synthèse asynchrone sur attestation utilisateur, le suivi généré compte 37 terminées, 8 partielles et 39 à faire.
2. **Heures :** 355 h estimées = 164 h classées terminées + 191 h restantes. Ventilation des estimations par rôle A/B/C : 117/117/121 h ; heures classées terminées : 36/100/28 ; restantes : 81/17/93. Ce sont des calculs de statut/estimations résiduelles, pas des heures réellement travaillées.
3. **D3/D4 :** les 11/98 h restantes en D3 et 142/146 h en D4 sont des estimations historiques de la todolist, pas un relevé du code ou du temps réalisé. D3-L7-05 n’est pas créditée sur la base du rapport SQL daté du 27/10, non rattaché au checkout examiné. Le pilote SQL par défaut et les appels statiques aux 18 procédures concernent l’état du checkout vérifié le 15/11 (addendum), pas une preuve d’exécution à la coupure du 23/10 ; la validation PDO/MySQL et la recette restent ouvertes.
4. **Équipe et RACI :** le README nomme deux membres ; le CDC/WBS garde des rôles de planification A/B/C et des valeurs R/A/C/I du RACI explicitement distincts. Aucune lettre n’est attribuée nominativement et aucune capacité individuelle n’est déduite.
5. **Identifiants de contraintes :** le CDC et l’annexe listent ENF-01…ENF-22 ; les contraintes techniques sont CT-01…CT-12.
6. **Sécurité :** SEC-14 est décrit et relié au contrôle statique ciblé S-11. Les 10 scénarios offensifs S-01…S-10 sont distincts des 11 contrôles statiques ; seul le script statique a été rejoué ici.
7. **Règles métier :** 20 identifiants, RB-01…RB-11 classées par le CDC comme issues du sujet et RB-12…RB-20 comme 9 compléments. Le PDF pédagogique n’est pas versionné dans le checkout ; le pointage ne prétend pas remplacer une vérification pédagogique.
8. **Séquences UML :** cinq sources/rendus, soit quatre séquences prévues comme imposées et une bonus ; les diagrammes d’activité et d’état ne sont pas des séquences. Aucune sixième n’est revendiquée.
9. **Dates et branches :** la coupure documentaire retenue est le 23/10/2026 ; le commit de base `e0c554f` porte une date Git du 13/11/2026 et le checkout n’est donc pas présenté comme un snapshot disponible au 23/10. Les références au rapport du 27/10, aux archives du 28/10 et aux runs de novembre sont exclues des preuves à cette coupure. Les contrôles locaux listés en §2 ont été rejoués le 23/10 sur le working tree consulté. `docs/10` contient un ancien nom de branche, distinct de la branche active `arena/10afc76d-sae-302`.
10. **Inventaire :** le CSV comprend 542 chemins suivis dans l’index courant ; les contrôles structurels réussis vérifient sa synchronisation et l’absence de doublons. Cela ne constitue pas une revue du contenu de chaque fichier. Le compte historique 532 du rapport daté du 28/10 correspond à un autre état et reste exclu des preuves à la coupure du 23/10.
11. **Source officielle :** le PDF du sujet, fourni par l’utilisateur mais non versionné dans le dépôt, demande le courriel de composition et le partage de l’URL GitLab ; la visibilité privée et la protection de `main` ne sont pas présentées comme des exigences du sujet.

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

1. Rejouer les tests d’intégration SQL sur une base temporaire dédiée, puis valider le parcours HTTP SQL ; le scan actuel repère un appel repository pour chacune des 18 procédures, mais ne prouve pas l’exécution PDO/MySQL. Conserver les tests JSON comme voie explicitement isolée, sans les confondre avec le pilote applicatif SQL par défaut.
2. Garder les heures WBS comme estimations de planification ; revalider les états historiques et la disponibilité avant d’utiliser le graphe ou le planning S6.
3. Ne pas compter le rapport SQL daté du 27/10 ni les références CI de novembre comme preuves à la coupure du 23/10 ; les revalider uniquement après les dates portées, avec run, SHA et sorties vérifiables.
4. Laisser les 200 scénarios manuels `NON JOUÉ` tant que des testeurs ne les ont pas exécutés ; aucune validation LineServ, déploiement ou restauration n’est à ajouter rétrospectivement.
5. Laisser `D2-L2-06` ouvert jusqu’à une trace de relecture croisée entre étudiants distincts ; le pointage de l’assistant dans le CDC n’est pas cette relecture.
6. Faire attester séparément les tâches GitLab restantes, la validation pédagogique et les répétitions de soutenance. Le courriel de composition est une attestation utilisateur non vérifiée dans la boîte d’envoi ; le dépôt IUT n’a pas été consulté ici.

**Portée de cette révision :** SQL/MySQL/PDO est le pilote par défaut et JSON reste une option explicite. Le scan statique repère des appels repository pour les 18 procédures ; cela n’atteste ni l’exécution PDO/MySQL ni un parcours HTTP complet. Les tâches de parcours restent partielles jusqu’à un rejeu sur base temporaire et une validation de bout en bout. D1-L1-07 demeure clos pour la synthèse écrite des retours asynchrones, sur attestation utilisateur ; aucune réunion complète n’est revendiquée. Aucun déploiement, test manuel LineServ, restauration de base, relecture étudiante ou validation pédagogique n’est revendiqué ; le rejeu PDO/MySQL n’a pas été effectué dans cette révision.
