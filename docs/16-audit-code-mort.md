# Audit des fichiers applicatifs et du code potentiellement inutilisé

## Périmètre et méthode

Inventaire de **tous les fichiers versionnés sous `app/` et `public/`** à la date de l’audit : code PHP, vues, CSS, JS, configuration serveur, police et images. Ce périmètre couvre l’application livrée et ses outils publics ; ce premier inventaire détaillé reste limité à `app/` et `public/` ; le [rapport global](17-audit-global-projet.md) étend ensuite les contrôles à tout le dépôt. Ces dossiers ont aussi servi à retrouver des références. Aucune donnée de compte n’est affichée ici.

Les références sont des **indices textuels** et non une preuve d’exécution : autoload, routes, includes, appels dynamiques, liens directs et données SQL peuvent activer un fichier sans mention littérale. Le nombre `fonctions` compte les fonctions/méthodes nommées **ayant un corps**, détectées avec `token_get_all` ; il ne compte ni les signatures d’interface, ni les fermetures anonymes, ni les méthodes héritées. Un faible nombre ne signifie pas qu’un fichier est inutile. Ta copie locale peut comporter d’autres fichiers ou modifications non présents dans cet audit.

## Résultats et décisions

- **0 fichier de zéro octet** dans ce périmètre au moment de l’audit.
- **93 fichiers PHP** : **45 sans fonction nommée avec corps** ; 37 sont des vues. Les autres sont trois classes d’exception vides héritées, un document PHP, une interface à signatures, un modèle de configuration, le bootstrap et le contrôleur frontal. Ils ont chacun un rôle expliqué ci-dessous.
- **Quatre PHP avec une ou deux fonctions nommées** : `DashboardController.php` (une action), `ParametreAdminController.php` et `StockAdminController.php` (deux actions chacun), `BusinessError.php` (constructeur et message). Petites responsabilités ne signifie pas code mort.
- Les exceptions `QueryError`, `SchemaError`, `ConstraintError` n’ont pas de méthodes propres : leur type et l’héritage servent aux `throw`/`catch` et à l’autoload. `StoreInterface.php` a des signatures, mais leurs corps résident dans les pilotes.
- `Errors.php` est documentaire, pas une classe ; son commentaire disant que SQL était seulement futur a été corrigé. `env.example.php` est un ancien modèle compatible, non la configuration secrète courante.
- La méthode privée `JsonStore::maxPk` n’avait aucun appel trouvé et a été retirée. Dans `JsonStore::find`, une variable sans lecture a été retirée **en conservant** `Schema::table($table)` : cet appel valide la table et peut lever une erreur ; un test protège cet effet.
- `HomeController::page403` n’a pas de référence trouvée ; la vue 403 n’est atteinte que par cette méthode dans les sources examinées. Les gardes actuelles peuvent répondre autrement. À clarifier avec l’équipe avant suppression ou modification du parcours.
- `logo.svg` et `logo-mark.svg` apparaissent dans la DA et les tests mais aucune inclusion n’a été trouvée dans les vues ; ce sont des variantes d’identité, conservées. Le favicon est utilisé. Une URL directe ou un usage externe n’est pas exclu.
- Les classes/outils SQL et de volumétrie ne sont pas forcément sur le parcours Web normal : par exemple `VolumetryGenerator` est utilisé par `scripts/gen_volumes_cli.php`. Ne pas confondre « non utilisé sur une page » et « inutile ».

### Candidats restants à trier

Réexamen des **11 alertes initiales** : le référentiel `scripts/code_mort_reference.json` en contient désormais **7**. Trois disparaissent de l’analyse parce que des tests les citent, sans preuve d’usage applicatif ; `sudoDisponible` est maintenant utilisé par le provisionnement. Ce ne sont pas des preuves de code mort. Un nouvel indice fait échouer le contrôle ; les indices disparus doivent aussi être retirés du référentiel après revue. Les candidats connus restent affichables avec `php scripts/code_mort.php --rapport`.

| Candidat | Observation et décision prudente |
|---|---|
| `DbConnexion::dsnUtilise` | Accesseur public de diagnostic ; aucune référence détectée. API ou script externe possible : conserver en attendant validation. |
| `HomeController::page403` | Aucun appel identifié ; vue 403 non branchée. Refus SUPER réellement constaté : redirection 302 avec message. Le faux contrat prétendant prouver 403/500 a été corrigé ; décider avec l’équipe si une page 403 est souhaitée. |
| `Filter::isNotNull` | Conservé ; test réel côté JSON ajouté. Cette référence de test ne démontre pas un usage en production. |
| `Journal::tail` | Conservé ; limites zéro/négative corrigées et testées sur un fichier temporaire. La documentation ne prétend plus qu’un écran back-office le consulte. |
| `JsonTable::isDirty` | Conservé ; transition sale/propre après persistance testée. Référence de test seulement. |
| `Seed::comptesDemo` | Accesseur public non appelé dans le périmètre ; vérifier usages hors dépôt avant retrait. |
| `CommandeRepository::withStore` | Aucun appel trouvé ; doublon fonctionnel de la fabrique `for`, mais méthode publique conservée par compatibilité jusqu’à validation des utilisateurs externes. |
| `visuel_produit($nom)` | Paramètre non lu depuis que l’image est décorative (`alt=""`) ; les appels et la signature publique l’utilisent encore. Conserver sans changer l’accessibilité ni casser les appels nommés. |
| `DbInstaller::codeDerniereErreur` | Accesseur public de diagnostic sans appel détecté ; conserver avant revue des outils externes. |
| `DbProvision::sudoDisponible` | Désormais appelée avant d’écrire le fichier et lancer `sudo` ; diagnostic précoce si sudo manque. |
| `EnvFile::existe` | Accesseur public sans appel détecté ; vérifier les outils hors dépôt. |

Le contrôle détecte les paramètres non cités et certaines variables locales **écrites une seule fois**, ainsi que les noms de fonctions/méthodes sans autre mention textuelle. Il ne prouve ni l’atteignabilité ni l’absence d’utilisation : références indirectes, portée, héritage, récursion, `include`, `compact`, variables dynamiques, réflexion, dépendances et appels externes limitent le résultat. Il ne détecte pas toutes les écritures répétées sans lecture, les variables au niveau fichier ou le code mort CSS/JS/SQL. Aucune suppression automatique.

## Inventaire fichier par fichier

Tailles en octets historiques mesurées lors du premier audit (`870dda5`) ; les fichiers ont pu être modifiés depuis. `—` : fichier non PHP. Les références listées sont des exemples, pas une liste exhaustive.

| Fichier | Octets | Fonctions avec corps | Rôle et indice d’utilisation |
|---|---:|---:|---|
| `app/Config/Database.php` | 6905 | 10 | Choix du magasin JSON/SQL et lecture de la configuration. Références : `app/Config/DbConnexion.php`, `app/Controller/Admin/CategorieAdminController.php`, `app/Controller/Admin/CommandeAdminController.php`…. |
| `app/Config/DbConnexion.php` | 14077 | 17 | Connexion PDO, DSN, options et repli socket. Références : `app/Model/Data/SqlStore.php`, `app/Tools/DbInstaller.php`, `docs/10-todo-corrections.md`…. |
| `app/Config/Env.php` | 6736 | 11 | Lecteur et cache de configuration .env ; la classe Env n’est pas le fichier secret env.php. Références : `app/Config/Database.php`, `app/Config/env.example.php`, `app/Tools/EnvFile.php`…. |
| `app/Config/env.example.php` | 1607 | 0 | Ancien modèle de configuration PHP, conservé pour compatibilité ; la configuration recommandée est .env. Modèle à copier en env.php pour les anciennes configurations ; pas chargé directement. |
| `app/Controller/Admin/CategorieAdminController.php` | 2845 | 4 | Gestion des catégories. Références : `public/index.php`. |
| `app/Controller/Admin/CommandeAdminController.php` | 5062 | 3 | Gestion des commandes et de leurs statuts. Références : `public/index.php`. |
| `app/Controller/Admin/DashboardController.php` | 1857 | 1 | Tableau de bord administratif : un seul point d’entrée. Références : `public/index.php`. |
| `app/Controller/Admin/EquipeAdminController.php` | 3712 | 3 | Administration des comptes de l’équipe. Références : `public/index.php`. |
| `app/Controller/Admin/ParametreAdminController.php` | 2914 | 2 | Consultation et modification des paramètres métier. Références : `public/index.php`. |
| `app/Controller/Admin/ProduitAdminController.php` | 5421 | 8 | Création et modification des produits. Références : `public/index.php`. |
| `app/Controller/Admin/StockAdminController.php` | 3487 | 2 | État et ajustements de stock. Références : `public/index.php`, `docs/09-audit-ecarts.md`. |
| `app/Controller/ApiController.php` | 5723 | 9 | API JSON du panier, avec contrôles et CSRF. Références : `app/Security/Csrf.php`, `public/index.php`, `docs/10-todo-corrections.md`. |
| `app/Controller/AuthController.php` | 5273 | 8 | Inscription, connexions et déconnexion. Références : `public/index.php`, `docs/02-document-tests-validation.md`, `docs/07-note-cybersecurite.md`…. |
| `app/Controller/CatalogueController.php` | 3665 | 4 | Catalogue, recherche, filtres et fiche produit. Références : `public/index.php`, `docs/02-document-tests-validation.md`, `docs/08-fiche-suivi-commits.md`. |
| `app/Controller/CommandeController.php` | 5319 | 6 | Validation, détail et annulation de commandes client. Références : `public/index.php`, `docs/01-cahier-des-charges-MiniShop.md`, `docs/02-document-tests-validation.md`…. |
| `app/Controller/CompteController.php` | 3711 | 3 | Profil client et mot de passe. Références : `public/index.php`, `docs/02-document-tests-validation.md`, `docs/08-fiche-suivi-commits.md`. |
| `app/Controller/Controller.php` | 3136 | 9 | Base commune : rendu, messages, validation CSRF et lecture des entrées. Références : `app/Controller/Admin/CategorieAdminController.php`, `app/Controller/Admin/CommandeAdminController.php`, `app/Controller/Admin/DashboardController.php`…. |
| `app/Controller/HomeController.php` | 5712 | 17 | Accueil, pages informatives/légales et formulaire de contact. Références : `app/Controller/CatalogueController.php`, `app/Router.php`, `public/index.php`…. |
| `app/Controller/PanierController.php` | 1912 | 5 | Parcours panier HTML sans dépendre de JavaScript. Références : `public/index.php`, `docs/01-cahier-des-charges-MiniShop.md`, `docs/02-document-tests-validation.md`…. |
| `app/Model/Data/BusinessError.php` | 5928 | 2 | Exception métier avec code et traduction en message humain. Références : `app/Controller/Admin/CategorieAdminController.php`, `app/Controller/Admin/CommandeAdminController.php`, `app/Controller/Admin/EquipeAdminController.php`…. |
| `app/Model/Data/ConstraintError.php` | 426 | 0 | Type d’exception d’intégrité ; classe vide héritant de RuntimeException. Références : `app/Model/Data/Errors.php`, `app/Model/Data/JsonStore.php`, `app/Model/Data/SqlStore.php`…. |
| `app/Model/Data/Errors.php` | 1171 | 0 | Documentation de la taxonomie des exceptions ; ne contient volontairement pas de classe. Documentation citée dans les exceptions ; pas de chargement applicatif trouvé. |
| `app/Model/Data/Filter.php` | 3459 | 14 | Construction de filtres et tris pour le contrat de stockage. Références : `app/Controller/Admin/CommandeAdminController.php`, `app/Controller/Admin/DashboardController.php`, `app/Controller/Admin/EquipeAdminController.php`…. |
| `app/Model/Data/Journal.php` | 2769 | 4 | Journal JSONL des opérations ; méthode de lecture sans écran de consultation branché. Références : `app/Model/Data/JsonStore.php`, `app/View/home/apropos.php`, `public/gen_volumes.php`…. |
| `app/Model/Data/JsonStore.php` | 30823 | 44 | Stockage JSON, requêtes, contraintes et transactions. Références : `app/Config/Database.php`, `app/Model/Data/Schema.php`, `app/Model/Data/SqlStore.php`…. |
| `app/Model/Data/JsonTable.php` | 5990 | 16 | Table JSON : données, index, persistance et état de modification. Références : `app/Model/Data/JsonStore.php`, `docs/08-fiche-suivi-commits.md`, `scripts/code_mort_reference.json`. |
| `app/Model/Data/QueryError.php` | 406 | 0 | Type d’exception de requête incorrecte ; classe vide héritant de RuntimeException. Références : `app/Model/Data/Errors.php`, `app/Model/Data/JsonStore.php`, `docs/07-note-cybersecurite.md`…. |
| `app/Model/Data/Schema.php` | 12118 | 4 | Description des tables, colonnes, clés et contraintes. Références : `app/Model/Data/Errors.php`, `app/Model/Data/JsonStore.php`, `app/Model/Data/SchemaError.php`…. |
| `app/Model/Data/SchemaError.php` | 413 | 0 | Type d’exception de schéma inconnu ; classe vide héritant de RuntimeException. Références : `app/Model/Data/Errors.php`, `app/Model/Data/Schema.php`, `docs/08-fiche-suivi-commits.md`…. |
| `app/Model/Data/Seed.php` | 12280 | 4 | Données de démonstration et initialisation du magasin JSON. Références : `docs/05-wbs-projet.md`, `docs/06-todolist.md`, `docs/08-fiche-suivi-commits.md`…. |
| `app/Model/Data/SqlStore.php` | 25378 | 35 | Implémentation du stockage SQL/PDO ; choix alternatif à JSON. Références : `app/Config/Database.php`, `app/Config/DbConnexion.php`, `app/Model/Data/Errors.php`…. |
| `app/Model/Data/StorageDriver.php` | 2064 | 3 | Énumération et sélection des pilotes JSON et SQL. Références : `app/Config/Database.php`, `app/Config/env.example.php`, `app/Model/Data/BusinessError.php`…. |
| `app/Model/Data/StoreInterface.php` | 6374 | 0 | Contrat de stockage : signatures abstraites sans corps, implémentées par les pilotes. Références : `app/Config/Database.php`, `app/Model/Data/Filter.php`, `app/Model/Data/JsonStore.php`…. |
| `app/Model/Data/Text.php` | 6548 | 5 | Pliage de texte, comparaison et génération de slugs. Références : `app/Model/Data/JsonStore.php`, `app/Model/Data/Triggers.php`, `app/Repository/CategorieRepository.php`…. |
| `app/Model/Data/Triggers.php` | 14972 | 3 | Déclencheurs et invariants métier du pilote JSON. Références : `app/Controller/Admin/CommandeAdminController.php`, `app/Model/Data/BusinessError.php`, `app/Model/Data/JsonStore.php`…. |
| `app/Model/Data/Views.php` | 5105 | 5 | Vues calculées du catalogue, des stocks et des commandes. Références : `app/Controller/Admin/CommandeAdminController.php`, `app/Controller/Admin/StockAdminController.php`, `app/Controller/CatalogueController.php`…. |
| `app/Model/PanierSession.php` | 6462 | 12 | Panier en session ; AuthSession, classe interne au même fichier, délègue l’ouverture de session. Références : `app/Controller/ApiController.php`, `app/Controller/CommandeController.php`, `app/Controller/PanierController.php`…. |
| `app/Repository/CategorieRepository.php` | 3943 | 6 | Accès et règles métier des catégories. Références : `app/Controller/Admin/CategorieAdminController.php`, `app/Controller/Admin/ProduitAdminController.php`, `app/Controller/CatalogueController.php`…. |
| `app/Repository/ClientRepository.php` | 5125 | 8 | Comptes clients et identifiants. Références : `app/Controller/AuthController.php`, `app/Controller/CommandeController.php`, `app/Controller/CompteController.php`…. |
| `app/Repository/CommandeRepository.php` | 18042 | 14 | Commandes, lignes, transitions et règles métier. Références : `app/Controller/Admin/CommandeAdminController.php`, `app/Controller/Admin/DashboardController.php`, `app/Controller/CommandeController.php`…. |
| `app/Repository/ParametreRepository.php` | 3110 | 6 | Paramètres et calcul des frais de port. Références : `app/Controller/Admin/ParametreAdminController.php`, `app/Controller/Admin/ProduitAdminController.php`, `app/Model/PanierSession.php`…. |
| `app/Repository/ProduitRepository.php` | 11040 | 11 | Recherche, produits et ajustements de stock. Références : `app/Controller/Admin/DashboardController.php`, `app/Controller/Admin/ProduitAdminController.php`, `app/Controller/Admin/StockAdminController.php`…. |
| `app/Router.php` | 3089 | 4 | Associe routes, paramètres, gardes et contrôleurs ; affiche la 404. Références : `public/index.php`, `docs/01-cahier-des-charges-MiniShop.md`, `docs/07-note-cybersecurite.md`…. |
| `app/Security/Auth.php` | 10330 | 16 | Session, authentification et gardes de rôles. Références : `app/Controller/Admin/CategorieAdminController.php`, `app/Controller/Admin/CommandeAdminController.php`, `app/Controller/Admin/DashboardController.php`…. |
| `app/Security/Csrf.php` | 1906 | 4 | Génération et vérification des jetons CSRF. Références : `app/Controller/Admin/CategorieAdminController.php`, `app/Controller/Admin/CommandeAdminController.php`, `app/Controller/Admin/EquipeAdminController.php`…. |
| `app/Security/xss.php` | 9276 | 15 | Helpers d’échappement, formatage, icônes et visuels. Références : `app/bootstrap.php`, `docs/01-cahier-des-charges-MiniShop.md`, `docs/07-note-cybersecurite.md`…. |
| `app/Tools/DbInstaller.php` | 17780 | 28 | Outil d’installation et inventaire SQL, utilisé par les scripts de développement. Références : `app/Config/DbConnexion.php`, `docs/10-todo-corrections.md`, `scripts/code_mort_reference.json`…. |
| `app/Tools/DbProvision.php` | 10558 | 16 | Outil de provisionnement d’un compte SQL de développement. Références : `docs/10-todo-corrections.md`, `scripts/code_mort_reference.json`, `scripts/db_init.php`…. |
| `app/Tools/EnvFile.php` | 3962 | 7 | Lecture/écriture ciblée du fichier de configuration local .env. Références : `docs/10-todo-corrections.md`, `scripts/code_mort_reference.json`, `scripts/db_init.php`…. |
| `app/Tools/Revision.php` | 3935 | 5 | Empreinte de révision pour les outils de diagnostic. Références : `docs/10-todo-corrections.md`, `scripts/db_init.php`, `tests/php/unit_tests.php`. |
| `app/Tools/SqlLoader.php` | 10115 | 5 | Lecture et exécution des scripts SQL dans les outils. Références : `app/Tools/DbInstaller.php`, `docs/10-todo-corrections.md`, `scripts/gen_volumes_cli.php`…. |
| `app/Tools/VolumetryGenerator.php` | 8031 | 6 | Génération de volumétrie ; utilisée par scripts/gen_volumes_cli.php, pas une route de boutique. Références : `docs/09-audit-ecarts.md`, `scripts/gen_volumes_cli.php`. |
| `app/View/admin/categories.php` | 2694 | 0 | Vue HTML : gestion des catégories ; pas de méthode attendue. Références : `app/Controller/Admin/CategorieAdminController.php`, `app/View/layout.php`, `public/index.php`…. |
| `app/View/admin/commande_detail.php` | 3467 | 0 | Vue HTML : détail et actions d’une commande admin ; pas de méthode attendue. Références : `app/Controller/Admin/CommandeAdminController.php`. |
| `app/View/admin/commandes.php` | 2598 | 0 | Vue HTML : liste des commandes admin ; pas de méthode attendue. Références : `app/Controller/Admin/CommandeAdminController.php`, `app/View/admin/commande_detail.php`, `app/View/admin/dashboard.php`…. |
| `app/View/admin/dashboard.php` | 4755 | 0 | Vue HTML : tableau de bord ; pas de méthode attendue. Références : `app/Controller/Admin/DashboardController.php`. |
| `app/View/admin/equipe.php` | 2792 | 0 | Vue HTML : équipe et rôles ; pas de méthode attendue. Références : `app/Controller/Admin/EquipeAdminController.php`, `app/View/layout.php`, `public/index.php`…. |
| `app/View/admin/parametres.php` | 2556 | 0 | Vue HTML : paramètres métier ; pas de méthode attendue. Références : `app/Controller/Admin/ParametreAdminController.php`, `app/View/layout.php`, `public/index.php`…. |
| `app/View/admin/produit_form.php` | 4264 | 0 | Vue HTML : formulaire produit ; pas de méthode attendue. Références : `app/Controller/Admin/ProduitAdminController.php`. |
| `app/View/admin/produits.php` | 3448 | 0 | Vue HTML : liste et actions produits ; pas de méthode attendue. Références : `app/Controller/Admin/ProduitAdminController.php`, `app/View/admin/produit_form.php`, `app/View/layout.php`…. |
| `app/View/admin/stocks.php` | 4329 | 0 | Vue HTML : stocks et ajustements ; pas de méthode attendue. Références : `app/Controller/Admin/StockAdminController.php`, `app/View/admin/dashboard.php`, `app/View/admin/produit_form.php`…. |
| `app/View/auth/admin_connexion.php` | 1400 | 0 | Vue HTML : connexion administration ; pas de méthode attendue. Références : `app/Controller/AuthController.php`, `tests/php/css_contract.php`. |
| `app/View/auth/connexion.php` | 1419 | 0 | Vue HTML : connexion client ; pas de méthode attendue. Références : `app/Controller/AuthController.php`, `tests/php/css_contract.php`. |
| `app/View/auth/inscription.php` | 2422 | 0 | Vue HTML : inscription client ; pas de méthode attendue. Références : `app/Controller/AuthController.php`, `tests/php/css_contract.php`, `tests/php/information_juridique_test.php`. |
| `app/View/catalogue/index.php` | 8878 | 0 | Vue HTML : liste catalogue et recherche ; pas de méthode attendue. Références : `app/Controller/CatalogueController.php`, `tests/php/cartes_accessibilite_test.php`, `tests/php/css_contract.php`…. |
| `app/View/catalogue/produit.php` | 3852 | 0 | Vue HTML : fiche produit ; pas de méthode attendue. Références : `app/Controller/CatalogueController.php`, `tests/php/css_contract.php`. |
| `app/View/commande/detail.php` | 3114 | 0 | Vue HTML : détail commande client ; pas de méthode attendue. Références : `app/Controller/CommandeController.php`, `tests/php/information_juridique_test.php`. |
| `app/View/commande/mes.php` | 1471 | 0 | Vue HTML : liste des commandes client ; pas de méthode attendue. Références : `app/Controller/CommandeController.php`. |
| `app/View/commande/valider.php` | 3185 | 0 | Vue HTML : validation de commande ; pas de méthode attendue. Références : `app/Controller/CommandeController.php`, `app/View/panier/index.php`, `public/index.php`…. |
| `app/View/compte/index.php` | 2475 | 0 | Vue HTML : profil client ; pas de méthode attendue. Références : `app/Controller/CompteController.php`. |
| `app/View/errors/403.php` | 521 | 0 | Vue HTML : page d’accès refusé, appelée uniquement depuis page403 candidate ; pas de méthode attendue. HomeController::page403 ; méthode non référencée dans les sources inspectées, affichage à vérifier. |
| `app/View/errors/404.php` | 585 | 0 | Vue HTML : page introuvable ; pas de méthode attendue. Références : `app/Controller/HomeController.php`. |
| `app/View/errors/500.php` | 847 | 0 | Vue HTML : page d’erreur serveur ; pas de méthode attendue. Références : `public/index.php`, `docs/07-note-cybersecurite.md`. |
| `app/View/home/aide.php` | 1936 | 0 | Vue HTML : aide ; pas de méthode attendue. Références : `app/Controller/HomeController.php`. |
| `app/View/home/apropos.php` | 1852 | 0 | Vue HTML : à propos ; pas de méthode attendue. Références : `app/Controller/HomeController.php`. |
| `app/View/home/categories.php` | 1074 | 0 | Vue HTML : catégories publiques ; pas de méthode attendue. Références : `app/Controller/HomeController.php`, `tests/php/css_contract.php`. |
| `app/View/home/cgu.php` | 1090 | 0 | Vue HTML : conditions générales d’utilisation ; pas de méthode attendue. Références : `app/Controller/HomeController.php`. |
| `app/View/home/cgv.php` | 3695 | 0 | Vue HTML : conditions générales de vente ; pas de méthode attendue. Références : `app/Controller/HomeController.php`, `tests/php/information_juridique_test.php`. |
| `app/View/home/confidentialite.php` | 4687 | 0 | Vue HTML : confidentialité ; pas de méthode attendue. Références : `app/Controller/HomeController.php`, `tests/php/information_juridique_test.php`. |
| `app/View/home/contact.php` | 2577 | 0 | Vue HTML : formulaire de contact ; pas de méthode attendue. Références : `app/Controller/HomeController.php`, `tests/php/css_contract.php`, `tests/php/information_juridique_test.php`. |
| `app/View/home/cookies.php` | 3044 | 0 | Vue HTML : cookies ; pas de méthode attendue. Références : `app/Controller/HomeController.php`, `tests/php/information_juridique_test.php`. |
| `app/View/home/garanties.php` | 1643 | 0 | Vue HTML : garanties ; pas de méthode attendue. Références : `app/Controller/HomeController.php`, `tests/php/information_juridique_test.php`. |
| `app/View/home/garanties_encadre.php` | 4297 | 0 | Vue HTML : encadré légal inclus dans d’autres vues ; pas de méthode attendue. Références : `app/View/home/cgv.php`, `app/View/home/garanties.php`, `tests/php/information_juridique_test.php`. |
| `app/View/home/index.php` | 5443 | 0 | Vue HTML : accueil ; pas de méthode attendue. Références : `app/Controller/HomeController.php`, `tests/php/cartes_accessibilite_test.php`, `tests/php/css_contract.php`…. |
| `app/View/home/livraison.php` | 1375 | 0 | Vue HTML : livraison ; pas de méthode attendue. Références : `app/Controller/HomeController.php`. |
| `app/View/home/mentions_legales.php` | 1630 | 0 | Vue HTML : mentions légales ; pas de méthode attendue. Références : `app/Controller/HomeController.php`. |
| `app/View/home/retractation.php` | 4486 | 0 | Vue HTML : rétractation ; pas de méthode attendue. Références : `app/Controller/HomeController.php`, `tests/php/information_juridique_test.php`. |
| `app/View/layout.php` | 9576 | 0 | Vue HTML : gabarit commun, liens CSS/JS et navigation ; pas de méthode attendue. Références : `app/Controller/Controller.php`, `docs/01-cahier-des-charges-MiniShop.md`, `docs/08-fiche-suivi-commits.md`…. |
| `app/View/panier/index.php` | 5627 | 0 | Vue HTML : panier HTML ; pas de méthode attendue. Références : `app/Controller/PanierController.php`. |
| `app/bootstrap.php` | 994 | 0 | Amorce : autoload App et helpers, fuseau horaire. Références : `app/Model/Data/ConstraintError.php`, `app/Model/Data/QueryError.php`, `app/Model/Data/SchemaError.php`…. |
| `public/.htaccess` | 1408 | — | Réécriture Apache et en-têtes ; entrée par le serveur, pas par PHP. Chargé par Apache si configuré ; aucun appel PHP attendu. |
| `public/css/app.css` | 48961 | — | Styles, composants et adaptations responsive du site. Références : `app/View/layout.php`, `docs/10-todo-corrections.md`, `docs/DA.md`…. |
| `public/css/print.css` | 1098 | — | Styles dédiés à l’impression. Références : `app/View/layout.php`, `docs/10-todo-corrections.md`, `docs/DA.md`…. |
| `public/fonts/OFL.txt` | 4406 | — | Fichier accompagnant la police locale ; pas de méthodes applicatives. Document associé à minishop-euro.woff2 ; pas chargé par le navigateur. |
| `public/fonts/minishop-euro.woff2` | 1044 | — | Police locale utilisée par la feuille de style. Références : `public/css/app.css`, `docs/10-todo-corrections.md`, `docs/DA.md`…. |
| `public/gen_volumes.php` | 46314 | 9 | Outil HTTP de volumétrie réservé au développement ; écrit des données SQL. Références : `app/Tools/VolumetryGenerator.php`, `public/mesurer.php`, `docs/07-note-cybersecurite.md`…. |
| `public/img/favicon.svg` | 426 | — | Icône de l’onglet déclarée dans le layout. Références : `app/View/layout.php`, `docs/DA.md`, `tests/php/css_contract.php`. |
| `public/img/illustration-achat.jpg` | 63157 | — | Illustration de la page d’accueil. Références : `app/View/home/index.php`, `docs/10-todo-corrections.md`, `docs/DA.md`…. |
| `public/img/logo-mark.svg` | 442 | — | Variante compacte d’identité ; références dans la DA et les tests, pas d’inclusion trouvée dans les vues. docs/DA.md et tests/php/css_contract.php ; pas de lien trouvé dans app/ ou public/. |
| `public/img/logo.svg` | 752 | — | Variante horizontale d’identité ; références dans la DA et les tests, pas d’inclusion trouvée dans les vues. docs/DA.md et tests/php/css_contract.php ; pas de lien trouvé dans app/ ou public/. |
| `public/img/produits/aura.jpg` | 38875 | — | Visuel réutilisé pour la famille casques dans les produits de démonstration. Références : `app/Model/Data/Seed.php`, `data/minishop/produit.json`, `docs/annexes/annexe-sql.md`…. |
| `public/img/produits/band.jpg` | 20959 | — | Visuel réutilisé pour la famille bracelets connectés dans les produits de démonstration. Références : `app/Model/Data/Seed.php`, `data/minishop/produit.json`, `docs/annexes/annexe-sql.md`…. |
| `public/img/produits/boom.jpg` | 32234 | — | Visuel réutilisé pour la famille enceintes dans les produits de démonstration. Références : `app/Model/Data/Seed.php`, `data/minishop/produit.json`, `docs/annexes/annexe-sql.md`…. |
| `public/img/produits/fit2.jpg` | 21677 | — | Visuel réutilisé pour la famille montres connectées dans les produits de démonstration. Références : `app/Model/Data/Seed.php`, `data/minishop/produit.json`, `docs/annexes/annexe-sql.md`…. |
| `public/img/produits/k87.jpg` | 45866 | — | Visuel réutilisé pour la famille claviers dans les produits de démonstration. Références : `app/Model/Data/Seed.php`, `data/minishop/produit.json`, `docs/annexes/annexe-sql.md`…. |
| `public/img/produits/power20.jpg` | 15357 | — | Visuel réutilisé pour la famille batteries dans les produits de démonstration. Références : `app/Model/Data/Seed.php`, `data/minishop/produit.json`, `docs/annexes/annexe-sql.md`…. |
| `public/img/produits/procast.jpg` | 29927 | — | Visuel réutilisé pour la famille micros dans les produits de démonstration. Références : `app/Model/Data/Seed.php`, `data/minishop/produit.json`, `docs/annexes/annexe-sql.md`…. |
| `public/img/produits/qhd27.jpg` | 19002 | — | Visuel réutilisé pour la famille écrans dans les produits de démonstration. Références : `app/Model/Data/Seed.php`, `data/minishop/produit.json`, `docs/annexes/annexe-sql.md`…. |
| `public/img/produits/sac25.jpg` | 33156 | — | Visuel réutilisé pour la famille sacs dans les produits de démonstration. Références : `app/Model/Data/Seed.php`, `data/minishop/produit.json`, `docs/annexes/annexe-sql.md`…. |
| `public/img/produits/turbox.jpg` | 32649 | — | Visuel réutilisé pour la famille ordinateurs fixes dans les produits de démonstration. Références : `app/Model/Data/Seed.php`, `data/minishop/produit.json`, `docs/annexes/annexe-sql.md`…. |
| `public/img/produits/usbc.jpg` | 48545 | — | Visuel réutilisé pour la famille câbles dans les produits de démonstration. Références : `app/Model/Data/Seed.php`, `data/minishop/produit.json`, `docs/annexes/annexe-sql.md`…. |
| `public/img/produits/zen14.jpg` | 23727 | — | Visuel réutilisé pour la famille ordinateurs portables dans les produits de démonstration. Références : `app/Model/Data/Seed.php`, `data/minishop/produit.json`, `docs/annexes/annexe-sql.md`…. |
| `public/index.php` | 8437 | 0 | Contrôleur frontal : en-têtes, routes et gestion des erreurs. Point d’entrée HTTP / réécriture dans public/.htaccess. |
| `public/js/panier.js` | 5740 | — | Amélioration progressive du panier via l’API. Références : `app/View/layout.php`, `docs/02-document-tests-validation.md`, `docs/09-audit-ecarts.md`…. |
| `public/js/ui.js` | 4134 | — | Interactions d’interface et navigation. Références : `app/View/layout.php`, `docs/10-todo-corrections.md`, `docs/DA.md`…. |
| `public/mesurer.php` | 34558 | 3 | Outil HTTP de mesure des temps réservé au développement. Références : `app/View/home/cookies.php`, `docs/07-note-cybersecurite.md`, `docs/08-fiche-suivi-commits.md`…. |
