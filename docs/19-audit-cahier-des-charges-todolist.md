# Audit de conformité — cahier des charges, todolist et preuves

**Révision examinée :** date de coupure documentaire retenue pour l’analyse : 23/10/2026. La branche active est `arena/10afc76d-sae-302`, sur un working tree basé sur `e0c554f` avec modifications locales ; les métadonnées Git de ce commit indiquent le 13/11/2026, date postérieure à la coupure. Le checkout n’est donc pas présenté comme un instantané qui aurait existé au 23/10 : cette date sert uniquement à qualifier les mentions et résultats datés des documents. Les contrôles locaux de §2 ont été rejoués le 23/10/2026 sur le working tree présent dans la sandbox ; ils attestent uniquement les commandes et fichiers concernés lors de ce run, pas une CI globale ni un état historique au 23/10. Aucun accès au dépôt IUT ni au clone personnel inaccessible n’a été effectué.

> **Conclusion courte : non, la todolist n’est ni à jour ni suffisamment complète pour affirmer que toutes ses tâches sont achevées à 100 %.** L’application et les livrables sont bien présents, et de nombreuses vérifications automatisées existent. Il reste toutefois des écarts d’implémentation à une exigence explicite du CDC, des tests humains non joués, des mesures et actions externes non prouvées, ainsi que des mentions futures non recevables comme preuves à la coupure. Les comptages ciblés des règles métier, des séquences et des charges WBS/todolist sont réconciliés dans cette révision.
>
> Ce rapport audite les fichiers versionnés et le working tree de ce checkout, sans consulter directement le dépôt IUT. Les éléments externes rapportés sont distingués selon leur provenance ; ils ne présument pas l’état du clone local de l’utilisateur et ne transforment pas une tâche externe ou humaine en tâche accomplie sans preuve.

## 1. Périmètre et verdict global

`docs/06-todolist.md` et `docs/05-wbs-projet.md` ont été régénérées en version 1.4/1.2 : 84 tâches, 355 h estimées. Après révision de D1-L1-07 pour accepter un suivi asynchrone, les statuts sont 37 terminées, 8 partielles et 39 à faire ; 164 h sont **classées terminées** et 191 h restent estimées. Ces heures découlent des estimations résiduelles, pas d’un relevé de temps réel. D1-L1-07 est clos sur la synthèse des retours continus attestés par l’utilisateur ; aucune réunion complète n’est revendiquée. La relecture croisée de D2-L2-06 reste ouverte. Dans D3, 11/98 h restent estimées, dont les 4 h de référence de D3-L7-05 non créditées comme réalisées ; dans D4, 4/146 h sont classées terminées (le contrôle de sécurité) et 142 h restent. Cela ne signifie ni que l’application est achevée ni que les autres tâches sont rejouées. Le périmètre déploiement/staging est retiré sans tâche de remplacement ; aucun déploiement n’est revendiqué.

| Statut d’audit | Nombre sur 84 | Sens |
|---|---:|---|
| `OK` | 3 | Critère de fin documentaire attesté selon la définition révisée de la WBS, sans élargir la portée de la preuve. |
| `OK*` | 61 | Livrable documentaire ou contrôle ciblé présent ; les limites et attestations déclaratives sont précisées ci-dessous. **Ce n’est pas une certification absolue ni une validation pédagogique.** |
| `PARTIEL` | 10 | Une partie existe, mais un critère, une intégration, une preuve ou une validation humaine reste à résoudre. |
| `ÉCART` | 6 | Écart explicite entre la tâche/contrainte du CDC et l’application présente. |
| `[!]` | 4 | Tâche humaine, externe ou mesure cible impossible à vérifier depuis ce checkout. |
| **Total** | **84** | Les tâches `PARTIEL`, `ÉCART` et `[!]` restent ouvertes ; les réserves des `OK*` sont précisées dans les constats. |

Ces statuts évaluent les **84 tâches de la WBS de référence**, pas toutes les évolutions postérieures. Les ajouts (langue/fuseau, informations légales, contact, conservation, etc.) sont reliés dans le CDC aux `EV-*` et aux scénarios `F-*`, mais leur absence de la WBS ne prouve ni leur exécution ni leur validation.

**Datation des contenus et des contrôles :** la coupure documentaire retenue est le 23/10/2026 ; le commit de base porte une date Git du 13/11/2026, postérieure à cette coupure, et ne peut donc être présenté comme un instantané historique du 23/10. Des fichiers versionnés portent aussi des dates postérieures à la coupure (rapport SQL du 27/10, archives/to-do du 28/10 et références CI des 12–13/11). Ces métadonnées ne prouvent aucun événement ni résultat acquis au 23/10. Les contrôles locaux de §2 ont été rejoués le 23/10/2026 sur le working tree consulté et n’établissent pas l’état d’un autre SHA. Les métriques de résultat sans run/SHA — notamment les tableaux de la stratégie de tests — sont traitées comme contenu d’archive, pas comme preuve courante.

## 2. Vérifications effectuées et limites

### Preuve externe fournie par l’utilisateur — clone IUT

Le relevé terminal communiqué montre `origin` configuré sur `https://iut-git.unice.fr/but2_s302/s302.git` et une requête `git ls-remote --heads origin` réussie. Les références visibles sont `chahd`, `dev`, `pj509414/dev` (SHA `bcca4d1d527b2ef859295a44dd1c65134aa93ab5`) et `pj509414/poc-auth-jwt` ; aucune référence `main` n’apparaît dans cette sortie. Cela prouve que le dépôt existe et est lisible depuis ce clone. La capture GitLab « Membres » fournie montre l’enseignante NGUYEN Thanh-Phuong comme membre direct avec le rôle `Reporter`, et Chahd comme `Developer` hérité du groupe ; elle confirme l’accès de l’enseignante au projet. L’utilisateur confirme avoir envoyé le courriel de composition rédigé dans la conversation, qui comprenait l’adresse GitLab ; cette confirmation n’a pas été vérifiée indépendamment dans la boîte d’envoi.

### Source officielle fournie par l’utilisateur — `sujetSAE.pdf`

Le PDF officiel joint a été lu pour vérifier les exigences pédagogiques concernées. À la **page 8**, il demande d’envoyer par courriel la composition du groupe à `thanh-phuong.nguyen@univ-cotedazur.fr` avec l’objet `Composition SAE`, puis de communiquer l’adresse GitLab et de partager l’accès avec l’enseignante. Il demande aussi de livrer un script SQL de génération de la base (absence pénalisée de 3 points) et un document de tests/validation ; la page 9 rappelle la pénalité pour l’absence de ce document et demande une contribution régulière sur GitLab ainsi qu’un README d’installation. **Le sujet ne demande ni base de test distincte ni nom/suffixe particulier tel que `*_test`.** Le CDC a donc été épuré de toute base séparée pour la CI GitHub ; il indique le plan de réaliser les essais pré-démo sur l’instance LineServer puis de la réinitialiser avant la démonstration. Les détails techniques des campagnes automatisées restent dans le document de tests/validation, et ne constituent pas une exigence pédagogique. Le PDF ne demande pas de dépôt privé, ne mentionne pas de branche `main` protégée, de blocage du push direct, de squash ou de suppression automatique de branche source. La mention « pages privées » à la page 5 concerne les pages de l’application, pas la visibilité du dépôt. Le privé et les règles de branche figurant dans la WBS/annexe GitLab sont donc des choix ajoutés par les documents de projet, pas des exigences vérifiées du sujet officiel.

### Contrôles locaux du working tree — rejoués le 23/10/2026

- `bash -n tests/security/controles.sh` puis `bash tests/security/controles.sh` : **11/11 contrôles statiques** réussis ; contrôle ciblé S-11/SEC-14 inclus. Ce n’est ni un test offensif, ni une recette applicative générale, ni un essai LineServ.
- `python3 -m unittest discover -s tests/python -v` : **42 tests**, 22 réussis et 20 ignorés faute de PHP CLI natif ou d’extensions POSIX. Aucun test PHP natif n’est réputé passé par les tests ignorés.
- `python3 scripts/verifier_fiches_uc.py` : **46 contrôles documentaires** réussis ; 14 UC, 164 scénarios, 36 couples de transitions.
- `docs/13-suivi-recette-uc.csv` : **200 entrées `NON JOUÉ`** ; aucune campagne manuelle n’est déclarée.
- `node scripts/wasm_verdict.mjs` : **9/9** cas de verdict réussis.
- `node --check` : **41** fichiers JavaScript/ES modules sans erreur de syntaxe ; `bash -n` : **13** scripts Shell sans erreur ; `py_compile` : 3 outils Python compilés sans erreur.
- `.tools/build_wbs.py` et `.tools/build_todolist.py` compilés et rejoués avec Markdown 3.11.3 issu de `requirements-docs.txt` : WBS/todolist Markdown et HTML générés ; 84 tâches, 355 h estimées, 37/8/39 statuts, 164 h classées terminées et 191 h restantes. A/B/C sont des rôles de planification, pas des personnes.
- Le build documentaire complet a produit **91 rendus** HTML/PDF/DOCX/diaporama sans erreur ; les HTML `artefacts/01`…`04` ont aussi été régénérés. Après la clôture asynchrone de D1-L1-07, les sources modifiées 05, 06, 19 et 21 ont été reconstruites dans leurs formats HTML/PDF/DOCX et les HTML `artefacts/05`/`06` ont été mis à jour par leurs générateurs.
- `git diff --check` a été exécuté après les corrections documentaires et n’a signalé aucun problème d’espacement. Aucune suite applicative globale n’est revendiquée ici.

### Exécution complète impossible localement

Dans cette sandbox, `php`, `composer`, `mysql`, `mariadb`, `node_modules/` et `vendor/` sont absents. Les suites PHP/PHPUnit, navigateur, SQL et l’intégration de bout en bout MySQL/PDO ne sont donc pas rejouées. L’installation de Markdown a servi uniquement à générer les documents ; aucune dépendance d’exécution de l’application n’a été ajoutée.

### CI et contenus futurs

Aucun résultat CI global pour le working tree courant n’est revendiqué. Les références à des exécutions datées des 12–13/11/2026 présentes dans des rapports versionnés sont postérieures à la coupure et ne sont pas utilisées ici comme preuve. De même, `tests/sql/rapport_tests_sql.md` porte une mention de 29/29 datée du 27/10/2026 ; à la date de cette revue, cette mention future ne prouve pas un rejeu. La validation SQL du 23/09 relatée par `docs/04` demeure historique et n’a pas été rejouée dans cette révision.

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
| `D2-L2-04` | `OK*` | Le CDC précise que PHP/MySQL/PDO est la cible contractuelle du sujet, tandis que le checkout utilise JSON par défaut et SQL optionnel ; CT-02/CT-03 et §16.4 identifient explicitement le désaccord comme un écart d’intégration, sans le déclarer résolu. Les tâches d’application SQL restent `ÉCART`. |
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
| `D4-L12-05` | `OK*` | `tests/security/controles.sh` contient **11 contrôles statiques** ; 11/11 ont été rejoués localement le 23/10, dont S-11/SEC-14. Le périmètre reste celui des sources inspectées par le script. |
| `D4-L13-01` | `OK*` | JavaScript panier/AJAX présent, avec tests DOM/HTTP annoncés. |
| `D4-L13-02` | `OK*` | Validation côté client présente ; la validation serveur demeure la source d’autorité. |
| `D4-L13-03` | `OK*` | Labels, annonces et comportements clavier/focus couverts par les contrats disponibles. |
| `D4-L13-04` | `OK*` | Repli sans JavaScript spécifié et automatisé ; ne pas le confondre avec une recette manuelle non jouée. |

### D5 — Tests et validation

| ID | État audité | Constat et preuve |
|---|---|---|
| `D5-L14-01` | `OK*` | `docs/02` distingue stratégie, scénarios, rapports historiques, preuve locale de sécurité et recette humaine ; le CSV reste explicitement `NON JOUÉ`. |
| `D5-L14-02` | `PARTIEL` | Classement d’audit documentaire : les 29 scénarios et leur harnais sont présents, mais aucun rejeu SQL de cette révision n’a été possible. Dans `docs/06-todolist.md`, la tâche reste explicitement `⬜` avec 4 h estimées à faire ; le rapport du 27/10 et les références CI de novembre sont postérieurs à la coupure et ne prouvent pas un rejeu. |
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

Cela reste un écart d’implémentation aux critères du sujet et aux tâches D4-L9-04/05, D4-L10-04 et D4-L11-02/03/04. Les documents révisés le nomment explicitement ; ils ne le résolvent pas. Il faudra soit raccorder les procédures et prouver le parcours HTTP/PDO/MySQL sur base isolée, soit faire valider formellement une modification du CDC. Les seuls tests SQL directs ne suffisent pas pour cocher ces tâches à 100 %.

## 5. Constats réconciliés au 23/10/2026

1. **Tâches et périmètre :** 84 tâches de niveau 4 ; le déploiement/staging est retiré sans remplacement. Après clôture de D1-L1-07 comme synthèse asynchrone sur attestation utilisateur, le suivi généré compte 37 terminées, 8 partielles et 39 à faire.
2. **Heures :** 355 h estimées = 164 h classées terminées + 191 h restantes. Ventilation des estimations par rôle A/B/C : 117/117/121 h ; heures classées terminées : 36/100/28 ; restantes : 81/17/93. Ce sont des calculs de statut/estimations résiduelles, pas des heures réellement travaillées.
3. **D3/D4 :** D3 compte 11/98 h restantes ; D3-L7-05 reste estimée à 4 h (même estimation que dans la WBS) sans crédit de progression lié au rapport futur du 27/10. D4 compte 4/146 h classées terminées par le contrôle de sécurité et 142 h restantes. Le code présent ne transforme pas une tâche d’intégration ou de recette en tâche achevée. Le stockage par défaut est JSON, SQL optionnel.
4. **Équipe et RACI :** le README nomme deux membres ; le CDC/WBS garde des rôles de planification A/B/C et des valeurs R/A/C/I du RACI explicitement distincts. Aucune lettre n’est attribuée nominativement et aucune capacité individuelle n’est déduite.
5. **Identifiants de contraintes :** le CDC et l’annexe listent ENF-01…ENF-22 ; les contraintes techniques sont CT-01…CT-12.
6. **Sécurité :** SEC-14 est décrit et relié au contrôle statique ciblé S-11. Les 10 scénarios offensifs S-01…S-10 sont distincts des 11 contrôles statiques ; seul le script statique a été rejoué ici.
7. **Règles métier :** 20 identifiants, RB-01…RB-11 classées par le CDC comme issues du sujet et RB-12…RB-20 comme 9 compléments. Le PDF pédagogique n’est pas versionné dans le checkout ; le pointage ne prétend pas remplacer une vérification pédagogique.
8. **Séquences UML :** cinq sources/rendus, soit quatre séquences prévues comme imposées et une bonus ; les diagrammes d’activité et d’état ne sont pas des séquences. Aucune sixième n’est revendiquée.
9. **Dates et branches :** la coupure documentaire retenue est le 23/10/2026 ; le commit de base `e0c554f` porte une date Git du 13/11/2026 et le checkout n’est donc pas présenté comme un snapshot disponible au 23/10. Les références au rapport du 27/10, aux archives du 28/10 et aux runs de novembre sont exclues des preuves à cette coupure. Les contrôles locaux listés en §2 ont été rejoués le 23/10 sur le working tree consulté. `docs/10` contient un ancien nom de branche, distinct de la branche active `arena/10afc76d-sae-302`.
10. **Inventaire :** le CSV comprend 532 chemins suivis ; le contrôle structurel local vérifie sa synchronisation et l’absence de doublons. Cela ne constitue pas une revue du contenu de chaque fichier.
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

1. Traiter séparément l’écart **JSON / PDO / MySQL / procédures stockées** : intégration applicative avec preuve isolée, ou modification formelle du CDC approuvée par l’équipe pédagogique.
2. Garder les heures WBS comme estimations de planification ; revalider les états historiques et la disponibilité avant d’utiliser le graphe ou le planning S6.
3. Ne pas compter le rapport SQL daté du 27/10 ni les références CI de novembre comme preuves à la coupure du 23/10 ; les revalider uniquement après les dates portées, avec run, SHA et sorties vérifiables.
4. Laisser les 200 scénarios manuels `NON JOUÉ` tant que des testeurs ne les ont pas exécutés ; aucune validation LineServ, déploiement ou restauration n’est à ajouter rétrospectivement.
5. Laisser `D2-L2-06` ouvert jusqu’à une trace de relecture croisée entre étudiants distincts ; le pointage de l’assistant dans le CDC n’est pas cette relecture.
6. Faire attester séparément les tâches GitLab restantes, la validation pédagogique et les répétitions de soutenance. Le courriel de composition est une attestation utilisateur non vérifiée dans la boîte d’envoi ; le dépôt IUT n’a pas été consulté ici.

**Portée de cette révision :** clarification des preuves, dates et références de tests dans les documents, correction des générateurs WBS/todolist et du contrôle statique de sécurité ; aucun code métier applicatif n’a été modifié. Les sorties WBS/todolist ont été régénérées. D1-L1-07 est clos pour la synthèse écrite des retours asynchrones, sur attestation utilisateur ; aucune réunion complète n’est revendiquée. Les affirmations archivées de `docs/11` ne sont pas retenues comme résultats courants sans run/SHA. Aucun déploiement, test manuel LineServ, restauration de base, relecture étudiante ou validation pédagogique n’est revendiqué ; les suites impossibles faute d’outils restent explicitement non jouées.
