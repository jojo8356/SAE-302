# Architecture logicielle de MiniShop

> Ce document décrit le **code présent dans le dépôt**, et non une architecture cible. Il complète le [README](README.md), l’[étude de design](docs/DA.md), la [conception de la base](docs/04-conception-bd-et-sql.md) et la [note de sécurité](docs/07-note-cybersecurite.md). MiniShop reste une maquette pédagogique : les paiements sont simulés, le contact n’envoie pas d’e-mail et ce document ne constitue ni une recette de déploiement ni un audit de sécurité.

## 1. Vue d’ensemble

MiniShop est une application PHP rendue principalement côté serveur. Le navigateur reçoit des pages HTML construites par des vues PHP ; CSS et JavaScript natifs fournissent le style et des améliorations progressives. Le cœur n’est ni une SPA, ni une application bâtie sur un framework MVC tiers, ni un ORM.

```text
Navigateur
  ├── ressources statiques ───────────────> public/css, public/js, public/img, public/fonts
  └── requête GET / POST
       └── Apache (.htaccess) ou serveur local PHP
            └── public/index.php  (contrôleur frontal)
                 ├── app/bootstrap.php (autoloader minimal, helpers, fuseau UTC)
                 ├── Router + gardes Auth
                 └── Controllers
                      ├── Repositories + modèles/helpers métier
                      │    └── Database::store() : StoreInterface
                      │         ├── JsonStore (pilote par défaut)
                      │         │    ├── Schema / Triggers / Views
                      │         │    ├── JsonTable -> fichiers JSON
                      │         │    └── Journal -> var/journal/db.jsonl (si accessible en écriture)
                      │         └── SqlStore (option SQL)
                      │              └── PDO -> MySQL ou MariaDB
                      └── Views PHP -> HTML dans le layout commun
```

Le diagramme UML versionné [`docs/diagrams/architecture_mvc.png`](docs/diagrams/architecture_mvc.png) donne une représentation complémentaire des couches.

## 2. Entrée HTTP et routage

### Contrôleur frontal

`public/index.php` est l’entrée unique des routes applicatives. Il charge l’amorçage, envoie des en-têtes HTTP de protection, démarre l’authentification/session, déclare les routes `GET` et `POST`, puis transmet la requête au routeur. Les fichiers statiques existants sont servis directement par le serveur Web.

- En développement local, `run.sh` utilise le serveur intégré PHP avec `scripts/dev-router.php`. Ce routeur sert les fichiers présents sous `public/` et transmet les autres URL à `public/index.php`, car le serveur PHP intégré n’applique pas `.htaccess`.
- Sous Apache, `public/.htaccess` réécrit les URL qui ne correspondent pas à un fichier ou dossier existant vers `index.php`. Le `DocumentRoot` prévu est `public/`.
- Une installation sous un préfixe de chemin (par exemple un sous-dossier d’un hébergement étudiant) n’est pas automatiquement prise en charge : les liens et les chemins du site sont écrits depuis `/`. Il faudrait adapter puis vérifier ce contexte séparément.

### Routeur et contrôles d’accès

`app/Router.php` contient une liste explicite de routes. Il ne construit pas de nom de contrôleur à partir d’une URL arbitraire. Chaque route indique sa méthode, son action et une garde parmi `public`, `client`, `admin` ou `super`. Le routeur gère les motifs avec paramètres (`{id}`, `{slug}`) ; une méthode ou un chemin non déclaré aboutit à une 404.

Les gardes s’exécutent avant l’action. Les contrôleurs d’administration partagent des routes protégées par le rôle `admin` ; la gestion de l’équipe est réservée au rôle `super`. Les autorisations de rôle ne remplacent pas les vérifications d’appartenance dans les opérations métier : le détail et l’annulation d’une commande vérifient également côté serveur qu’elle appartient au client connecté.

## 3. Couches applicatives et responsabilités

| Couche | Emplacement | Responsabilité principale |
|---|---|---|
| Présentation Web | `public/` | Point d’entrée HTTP, CSS, JavaScript, images, polices et réécriture Apache. |
| Contrôle / orchestration | `app/Controller/` | Traduire une requête en action, exiger l’accès nécessaire, vérifier les formulaires/jetons, appeler les repositories, puis rendre une vue, produire une réponse JSON ou rediriger. |
| Routage | `app/Router.php` | Résoudre uniquement les routes déclarées et appliquer les gardes de rôle. |
| Vues | `app/View/` | Générer le HTML des pages, formulaires et fragments. Le gabarit `layout.php` fournit la structure et la navigation communes ; le back-office y reçoit sa navigation dédiée. |
| Repositories | `app/Repository/` | Donner aux contrôleurs des opérations cohérentes sur produits, catégories, clients, commandes et paramètres. Ils portent une partie importante des règles métier et délèguent la persistance à `StoreInterface`. |
| Modèle et règles partagées | `app/Model/`, `app/Model/Data/`, `app/Service/`, `app/Tools/` | Panier en session, schéma et contraintes, déclencheurs, vues de données, journalisation, temps, rétention et fonctions techniques. |
| Configuration / persistance | `app/Config/`, `app/Model/Data/` | Charger la configuration et sélectionner le pilote JSON ou SQL derrière `StoreInterface`. |
| Outillage non-Web | `scripts/`, `tests/`, `sql/`, `docs/` | Démarrage, installation, vérification, tests, définition du schéma relationnel et documents. Ces fichiers ne constituent pas des routes de l’application. |

C’est un MVC **pragmatique** : les contrôleurs et vues sont clairement séparés, mais il n’existe pas de couche générique d’entités/ORM. Les repositories et les classes de données portent les opérations métier et les contrôles nécessaires.

### Contrôleurs

`app/Controller/` regroupe les parcours publics, clients et administrateurs. `Controller` centralise le rendu dans le gabarit, les redirections, les messages flash et des accesseurs typés simples pour `GET`/`POST`. Les validations de format et les règles métier plus fortes restent dans les contrôleurs concernés et les repositories ; les accesseurs de base ne remplacent pas une validation complète.

`ApiController` expose une petite API JSON interne au panier. Elle ne constitue pas une API publique autonome : elle utilise les sessions de l’application, exige un jeton CSRF lors des écritures et retourne les prix, stocks et totaux calculés côté serveur. Le panier HTML reste utilisable sans JavaScript ; l’API sert à son amélioration progressive.

### Repositories et logique métier

Les repositories fournissent notamment la recherche et la gestion du catalogue, les catégories, les comptes clients, la création et le suivi des commandes, les changements d’état et les paramètres de boutique. Ils traduisent les refus métier en `BusinessError` et utilisent les interfaces de données, non les fichiers JSON directement.

`PanierSession` conserve dans la session seulement les identifiants produit et quantités. Lors de l’affichage ou de la validation, il relit les produits et paramètres du serveur : les prix, le stock disponible, les frais de port et les totaux ne sont pas acceptés depuis le navigateur comme source de vérité. Les données du panier ne deviennent des lignes de commande qu’au moment de la validation.

## 4. Parcours d’une requête

1. **Réception.** Le serveur sert un fichier statique existant ou dirige l’URL applicative vers `public/index.php`.
2. **Amorçage.** `app/bootstrap.php` définit `MINISHOP_ROOT`, enregistre un autoloader minimal `App\` → `app/`, charge les helpers d’affichage et fixe le fuseau PHP par défaut à UTC. Aucun autoloader Composer n’est requis pour l’application.
3. **Sécurité et session.** Le contrôleur frontal ajoute les en-têtes pour les réponses routées, puis `Auth` ouvre la session. Sur un PHP standard, les sessions PHP natives sont utilisées ; le SAPI PHP-WASM de la sandbox dispose d’un mécanisme de session spécial réservé à cet environnement de test.
4. **Résolution et garde.** `Router` extrait le chemin de l’URI, cherche une route de la méthode déclarée et applique la garde. Il n’existe pas de route générique qui transforme n’importe quel chemin en action.
5. **Traitement HTTP.** Le contrôleur lit les valeurs, vérifie les champs/formats utiles et appelle explicitement la vérification CSRF sur les opérations d’écriture. Une erreur d’accès est rendue en 403 ; une erreur métier est présentée sous forme de message utilisateur ; une exception technique est journalisée et traitée par la page 500.
6. **Règles et persistance.** Le repository demande le magasin applicatif à `Database::store()`, qui sélectionne le pilote depuis la configuration. Il effectue les opérations de lecture/écriture et, pour un parcours composé, les regroupe dans une transaction lorsque nécessaire.
7. **Réponse.** Un parcours HTML transmet les données à une vue puis au layout partagé ; un parcours API écrit du JSON ; un formulaire accepté redirige souvent après avoir posé un message flash.
8. **Rendu navigateur.** Les vues échappent les données textuelles avec `e()`. Le JavaScript natif met à jour le panier, les préférences et certains comportements d’interface, sans rendre le navigateur autoritaire sur les règles métier.

La vérification CSRF est fournie par `Controller::verifierCsrf()` / `Csrf` et appelée par les actions d’écriture concernées ; le routeur ne l’insère pas automatiquement dans chaque action. La couverture de cette convention dépend donc de l’implémentation des actions et des tests associés.

## 5. Persistance et stockage

### Contrat et sélection du pilote

`app/Model/Data/StoreInterface.php` est la frontière entre les repositories et le stockage. Le contrat décrit des opérations déclaratives de sélection, insertion, mise à jour, suppression et transaction. `Database` met en cache un magasin par requête et choisit le pilote selon `STORAGE_DRIVER` : `json` par défaut ou `sql`. La configuration suit les valeurs par défaut, les fichiers historiques de configuration, `.env`, puis les variables d’environnement du processus ; l’environnement peut donc surcharger `.env`.

### Pilote JSON (par défaut)

`JsonStore` associe une table à un fichier dans `data/minishop/` (ou au chemin choisi par `DATA_PATH`). `JsonTable` charge les lignes à la demande, maintient un index de clé primaire, puis écrit une table modifiée via un fichier temporaire et un renommage. `Schema` applique les types, valeurs par défaut, champs requis, unicités, `CHECK` et clés étrangères. `Triggers` exécute les règles métier partagées ; `Views` fournit des ensembles dérivés tels que l’état de stock et le catalogue.

Les lectures de filtre parcourent les lignes en mémoire. Les écritures ouvrent un verrou coopératif local `.minishop.lock` ; les changements sont différés jusqu’au commit et peuvent être annulés avant l’écriture. L’écriture d’un fichier individuel est atomique, mais un commit qui modifie plusieurs fichiers **n’est pas une transaction de base de données résistante à une panne** : une interruption du processus pendant le commit peut laisser une partie des fichiers déjà remplacée. Tout processus qui modifie les JSON sans passer par `JsonStore` contourne aussi le verrou et les règles.

Le moteur est donc adapté à la démonstration et aux petits volumes, pas à un stockage multi-hôte, à une forte concurrence ni à une charge de production.

### Pilote SQL (optionnel)

`SqlStore` implémente le même contrat sur MySQL/MariaDB via PDO. Il valide les noms de table/colonne sur la liste de `Schema`, utilise des marqueurs et des requêtes préparées natives pour les valeurs, et délègue les transactions à PDO. Les opérations de verrouillage SQL utilisent `SELECT … FOR UPDATE` dans une transaction. `DbConnexion` centralise l’ouverture de connexion, la collation de session et le fuseau SQL UTC.

Les scripts versionnés dans `sql/` décrivent 8 tables relationnelles, 3 vues, 1 fonction, 17 procédures stockées et 16 déclencheurs. Les tables et règles PHP doivent rester alignées avec ce schéma. **Précision importante sur l’exécution actuelle :** `SqlStore` expose des méthodes d’appel de procédures/fonctions, mais les repositories de l’application ne les appellent pas directement dans le code courant ; ils utilisent les opérations du contrat de magasin. En mode SQL, il ne faut donc pas déduire du seul inventaire SQL que chaque action applicative passe par une procédure stockée. Les contraintes et déclencheurs définis en base restent des garde-fous des écritures SQL.

Le pilote SQL exige que la base ait déjà été préparée et que PHP dispose de `pdo_mysql`. La configuration ou l’installation du schéma n’est pas un service de base de données inclus dans l’application. Les scripts d’initialisation et d’essai sur base doivent être réservés à une base jetable/dédiée ; ils ne doivent pas être lancés contre une base à conserver.

### Données et règles métier centrales

Le schéma logique contient les tables `administrateur`, `client`, `categorie`, `produit`, `parametre`, `commande`, `ligne_commande` et `order_status_history`. Les règles principales comprennent :

- adresses e-mail, références et slugs uniques ; catégories protégées si des produits les référencent ; produits ayant un historique conservés/masqués plutôt que supprimés arbitrairement ;
- prix TTC calculé à partir du prix HT et de la TVA ; prix et quantité figés sur chaque ligne de commande ; total contrôlé à partir des lignes et des frais de port ;
- stock non négatif ; un produit masqué est refusé par le panier, les quantités sont plafonnées au stock serveur et le stock est recontrôlé à la création de commande ;
- panier validé dans une transaction logique, avec contrôle des stocks ; transitions de commande contraintes par une matrice d’états et changements consignés dans l’historique ;
- frais de port et seuil de gratuité lus depuis les paramètres de boutique, puis figés sur la commande ; valeurs de démonstration par défaut : 4,90 € et gratuité à partir de 80 € ;
- annulation avec conservation de l’historique des lignes ; restitution du stock selon l’état et le parcours d’annulation prévu. Le cas d’une commande déjà expédiée ne provoque pas de restitution automatique du stock.

Il n’y a ni passerelle de paiement, ni système d’expédition, ni fournisseur d’e-mail branché à l’application. Les statuts de paiement et les parcours décrivent une simulation pédagogique.

## 6. Présentation, préférences et temps

Les vues de `app/View/` sont des templates PHP et partagent `layout.php`. La sortie utilisateur est échappée au moyen du helper `e()` de `app/Security/xss.php`. Les ressources visuelles et scripts sont servis localement depuis `public/`; l’application ne dépend pas d’un CDN pour son rendu principal.

`public/css/app.css` porte les règles d’interface et de responsive design ; `public/css/print.css` traite l’impression. `public/js/ui.js` gère les interactions transverses, `panier.js` améliore le panier, `i18n-en.js` porte les traductions et `preferences.js` gère les préférences du navigateur. La langue et le fuseau horaire personnel sont conservés dans `localStorage` et non dans le profil en base. Le fuseau par défaut suit la langue (Paris en français, New York en anglais). La géolocalisation est explicitement déclenchée par l’utilisateur ; `tz-lookup.js` effectue localement la conversion des coordonnées en fuseau, sans service géographique tiers ni conservation des coordonnées par l’application.

Les instants sont enregistrés en UTC. `Heure` traite les conversions et les bornes de dates ; le navigateur adapte l’affichage des éléments `<time>` à ses préférences. Le fuseau choisi n’est pas une donnée de compte.

## 7. Authentification et sécurité applicative

Les contrôles suivants sont implémentés dans le code ; leur présence ne vaut pas certification de sécurité :

- **Sessions et rôles :** `Auth` distingue les identifiants client et administrateur, vérifie qu’un compte est actif et renouvelle l’identifiant de session lors d’une connexion/déconnexion. Le cookie utilise `HttpOnly`, `SameSite=Lax` et `Secure` lorsque la requête est HTTPS ; le mode strict des sessions est activé.
- **Mots de passe :** les comptes créés enregistrent un hash par `password_hash()` ; la vérification passe par `password_verify()`. Les cas client « adresse inconnue » et « mot de passe incorrect » partagent un message. Le compte bloqué dispose cependant d’un message distinct.
- **CSRF :** `Csrf` crée un jeton aléatoire par session et le compare en temps constant avec `hash_equals()`. Les formulaires d’écriture et les écritures de l’API l’envoient ; l’API renvoie une erreur JSON plutôt qu’une redirection HTML.
- **XSS / contenus :** les valeurs textuelles dans les templates passent par `htmlspecialchars()` via `e()`. Les URL des visuels sont limitées aux fichiers locaux présents et les chemins suspects sont refusés.
- **CSP et en-têtes :** le contrôleur frontal définit une CSP restrictive et les en-têtes de protection associés. Les ressources de la page sont servies depuis l’origine du site.
- **SQL :** `SqlStore` ne concatène pas les valeurs métier dans les requêtes préparées ; les identifiants SQL passent par une validation sur la définition du schéma.
- **Erreurs :** les erreurs techniques sont écrites dans le journal PHP et la réponse 500 est générique hors mode debug ; les erreurs métier disposent de messages traduits pour l’interface.

Les paramètres de configuration et secrets locaux sont placés dans `.env` (non versionné) ; `.env.example` est le modèle. Le stockage et les logs doivent être situés dans des répertoires adaptés au contexte local. Les comptes de démonstration et le mode debug rendent inapproprié l’usage de la maquette comme service public réel.

## 8. Tests et vérification

Le dépôt comporte plusieurs niveaux, avec des prérequis différents :

- **Contrats PHP et tests du moteur** sous `tests/php/` : syntaxe, couches applicatives, règles métier, routes HTTP, contrats CSS/responsive et cas de fonctionnalités ; `tests/php/run_tests.php` rejoue notamment les scénarios fonctionnels contre un magasin JSON isolé.
- **Tests au format PHPUnit** sous `tests/phpunit/`, exécutables avec PHPUnit en environnement de développement ou avec `tests/phpunit/run-local.php` dans le contexte hors ligne prévu par le projet.
- **Tests SQL** sous `tests/sql/` et intégration sous `tests/php/sql_integration.php` : exigent un serveur MySQL/MariaDB et des outils appropriés ; seules des bases de test isolées doivent être utilisées.
- **Tests JavaScript et navigateur** sous `tests/node/` et `scripts/` : JSDOM couvre le DOM ; Playwright lance Chromium pour des tests de navigateur réels ; `@php-wasm/node` sert au runtime PHP-WASM et à certains parcours HTTP isolés.
- **Tests Python** sous `tests/python/` : tests d’outils CLI, contrôles structurels et autres vérifications s’appuyant surtout sur la bibliothèque standard.
- **Contrôles shell** sous `tests/security/`, tests de charge et scripts d’orchestration : ils n’équivalent pas à une campagne de recette réelle sur l’hébergement visé.

Les workflows déclarés se trouvent dans [`.github/workflows/ci.yml`](.github/workflows/ci.yml) et [`.gitlab-ci.yml`](.gitlab-ci.yml). Ils configurent notamment des jobs PHP, MySQL, MariaDB, Node/Chromium et documentation. La définition d’un job ne prouve pas son succès : il faut consulter le résultat du pipeline correspondant au commit.

[!] Ce document n’affirme pas qu’une exécution complète des tests, une recette manuelle ou un déploiement LineServ a été réalisé pendant sa rédaction. Les vérifications qui nécessitent un serveur SQL, un navigateur Chromium réel, des services externes ou l’environnement IUT doivent être confirmées dans leur environnement propre ; aucune mesure ni identité de test n’est inventée ici.

## 9. Limites des choix techniques

1. **JSON n’est pas un SGBD.** Les filtres parcourent les données en mémoire, le verrou est coopératif et local, et l’atomicité multi-fichier n’est pas garantie en cas d’arrêt pendant le commit. Éviter le mode JSON pour de la concurrence élevée ou plusieurs hôtes.
2. **Règles présentes à plusieurs endroits.** Les règles existent à la fois dans les repositories PHP, le schéma PHP, les callbacks JSON et les scripts SQL. Malgré les tests, ce parallélisme crée un risque de divergence. Le fait que les procédures SQL existent ne signifie pas que les repositories les invoquent.
3. **Dépendance au processus PHP.** Sessions, rendus et API sont étroitement liés au cycle HTTP PHP. Il n’y a pas de service asynchrone, de file de messages, de cache distribué ou de déploiement multi-instance documenté.
4. **Couverture du navigateur.** Le JavaScript est léger et améliore le rendu serveur ; le stockage des préférences dépend de `localStorage` et les dates personnalisées dépendent des capacités navigateur. Les tests automatisés ne remplacent pas les vérifications sur appareils et technologies d’assistance réels.
5. **Déploiement non garanti.** Le mode développement est simple à lancer, mais la configuration Apache, les droits de fichiers, TLS, la sécurité des comptes de démonstration et le contexte LineServ doivent être contrôlés séparément. La maquette n’implémente pas de paiement réel ni de livraison réelle.

## 10. Fichiers de référence

- Entrée et routage : [`public/index.php`](public/index.php), [`app/Router.php`](app/Router.php), [`app/bootstrap.php`](app/bootstrap.php).
- Configuration et stockage : [`app/Config/Database.php`](app/Config/Database.php), [`app/Model/Data/StoreInterface.php`](app/Model/Data/StoreInterface.php), [`app/Model/Data/JsonStore.php`](app/Model/Data/JsonStore.php), [`app/Model/Data/SqlStore.php`](app/Model/Data/SqlStore.php).
- Sécurité : [`app/Security/Auth.php`](app/Security/Auth.php), [`app/Security/Csrf.php`](app/Security/Csrf.php), [`app/Security/xss.php`](app/Security/xss.php).
- Schémas et règles : [`app/Model/Data/Schema.php`](app/Model/Data/Schema.php), [`app/Model/Data/Triggers.php`](app/Model/Data/Triggers.php), [`sql/`](sql/).
- Tests et développement : [`tests/`](tests/), [`run.sh`](run.sh), [`scripts/`](scripts/).
