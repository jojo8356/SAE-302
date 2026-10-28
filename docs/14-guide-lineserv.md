# MiniShop S302 — préparation LineServ, initialisation et recette

> **État au moment de la rédaction : préparation, pas déploiement validé.** Aucun accès au LineServ, import SQL ni test de navigateur réel n'a été effectué depuis ce dépôt. Les 150 scénarios de recette restent non joués. Avant d'envoyer un lien de test, lever les blocages du §1 et vérifier l'instance réelle.
>
> Ce guide reprend les indications communiquées pour le cours : SFTP `linserv-info-01.campus.unice.fr`, fichiers dans `www/`, URL en `https://linserv-info-01.campus.unice.fr/~login/…`, phpMyAdmin à `https://linserv-info-01.campus.unice.fr/phpmyadmin`. La disponibilité, les droits et la configuration actuels n'ont **pas** été vérifiés. Suivre les consignes de l'IUT si elles diffèrent.

## 1. Décision indispensable avant la mise en ligne

L'exemple visé pour ce compte est `https://linserv-info-01.campus.unice.fr/~pj509414/s302/`. **Ne pas considérer cette URL comme fonctionnelle ou publiée.** La procédure simple d'un fichier `sqlconnect.php` de TD ne suffit pas pour cette application :

1. **Sous-répertoire : non pris en charge en l'état.** `public/.htaccess` contient `RewriteBase /`, le routeur traite le chemin depuis la racine et des liens, formulaires, scripts et redirections commencent par `/`. Depuis `/~pj509414/s302/`, ils peuvent sortir du dossier, casser les images et les routes ou rediriger ailleurs. Il faut adapter et tester le préfixe pour l'ensemble de ces usages ; ne pas simplement changer `RewriteBase`.
2. **Racine publique : seul `public/` doit être servi.** `public/index.php` charge `../app/bootstrap.php` et suppose que `app/` est à côté de `public/`. Copié seul dans `www/s302/`, il ne trouve donc pas l'application. Copier l'ensemble du projet dans `www/s302/` exposerait potentiellement `.env`, données, code et documents ; les protections Apache ne sont pas garanties. **Ne pas le faire.** Il faut une séparation validée : application et `.env` hors de `www/`, `public/` seul accessible, avec chemin de bootstrap et configuration Apache adaptés ; ou un hébergement dédié dont le DocumentRoot pointe vers `public/` et qui accepte les routes de l'application.
3. **Sécurité de la recette :** restreindre l'accès au site de test avant toute exposition, car les comptes du jeu SQL ont des mots de passe publics connus. L'accès SFTP ne protège pas les pages HTTP. Confirmer avec l'IUT si un contrôle d'accès est possible et comment il est appliqué. Ne pas publier un admin de démonstration accessible à tous.
4. **Pré-requis à confirmer :** PHP Web ≥ 8.2 avec `pdo_mysql`, `mbstring`, `json`, `intl` ; MariaDB ≥ 10.6 ou MySQL ≥ 8.0.16 ; `mod_rewrite` et `.htaccess` autorisés ; droits sur tables, vues, routines et triggers ; système de fichiers et session PHP utilisables ; base de recette autorisée. Le PHP en ligne de commande et celui du serveur web peuvent différer.

**Point d'arrêt :** tant que les points 1 à 4 ne sont pas levés, on peut préparer les fichiers localement et, si l'équipe y consent, initialiser une base isolée ; on ne doit pas demander à la collègue de considérer le site comme disponible. Aucune commande d'installation système, `sudo`, `npm install`, ouverture de port ou déploiement automatique n'est requise par ce guide.

## 2. Informations à obtenir et à noter

| Élément | Valeur / décision |
|---|---|
| Compte SFTP, hôte et répertoire | login étudiant ; `linserv-info-01.campus.unice.fr` ; confirmer `www/` et l'espace privé hors web |
| URL de recette | `https://linserv-info-01.campus.unice.fr/~pj509414/s302/` **à confirmer** |
| Accès du site | mécanisme réel de restriction, personnes autorisées et disponibilité réseau |
| Configuration serveur | PHP Web et extensions, réécriture, configuration UserDir, chemins et permissions |
| Base de recette | nom réellement créé ou attribué par l'IUT ; vide et distincte d'autres projets |
| Connexion applicative | hôte, port ou socket, utilisateur SQL autorisé et méthode de transmission confidentielle |
| Stockage autorisé | espace privé pour `.env` et journaux `var/`, accessible au processus PHP sans lecture publique |
| Révision déployée | identifiant Git **et** éventuelles modifications locales, à reporter lors du transfert |

**Ne jamais écrire un mot de passe ici, dans Git, dans une capture d'écran ou dans l'e-mail de recette.** Ne pas supposer que les identifiants phpMyAdmin sont ceux du compte SFTP ou que `localhost`, `127.0.0.1`, le port ou le nom `minishop` conviennent.

## 3. Préparer la bonne version en local

1. Dans FileZilla, le dossier local de travail de l'utilisateur est `/home/jojokes/Documents/BUT/Projet/s302`. Ne pas supprimer ni remplacer les fichiers personnels ou les modifications locales : notamment `PdoStore.php`, `scripts/seed_mysql.php`, `tests/perf/charge-rb18.sh` et `.githooks/` s'ils existent dans sa copie. Le contenu local peut différer de celui de cette branche ; le constater **avant** tout transfert.
2. Relever l'état sans rien modifier :

```bash
cd /home/jojokes/Documents/BUT/Projet/s302
git branch --show-current
git rev-parse --short HEAD
git status --short
```

Résultat attendu : nom de branche, identifiant court puis, éventuellement, liste de modifications. Une liste non vide signifie simplement que certains fichiers ne correspondent pas au commit : ne pas les écraser. Pas de `git reset`, `clean`, `checkout --`, `pull` ou fusion automatique dans ce guide.
3. Vérifier que le guide et les scripts à transférer sont bien présents dans **cette copie**. Si ce n'est pas le cas, récupérer les changements après résolution des éventuels conflits, sans remplacer le travail local. Le commit `HEAD` seul ne décrit pas les modifications non commitées : noter aussi leur présence.
4. Conserver une sauvegarde du projet et des données existantes selon les règles de l'équipe. Ne pas copier à l'aveugle les données locales ni les secrets vers le serveur ; une recette doit utiliser un jeu dédié fictif.

## 4. Accès SFTP avec FileZilla — préparation, sans publication

1. Ouvrir FileZilla. Dans le gestionnaire de sites, choisir **SFTP – SSH File Transfer Protocol**, hôte `linserv-info-01.campus.unice.fr`, port `22` sauf indication contraire de l'IUT, identifiant étudiant et méthode d'authentification demandée par l'IUT. Vérifier l'empreinte de la clé du serveur auprès de l'IUT lors de la première connexion. Ne pas transmettre le mot de passe à un tiers.
2. À gauche : dossier local indiqué au §3. À droite : constater l'arborescence reçue, repérer `www/` et **demander quel répertoire hors `www/` est autorisé** pour le projet privé. Ne rien supprimer et ne pas remplacer un dossier existant.
3. Si l'IUT autorise cet espace, un dossier de préparation privé distinct peut être créé et les fichiers nécessaires y être copiés après validation de l'équipe. Ne pas téléverser `.git/`, `node_modules/`, `vendor/`, `.env` de la machine locale, sauvegardes, journaux ni données de travail personnelles. Conserver `app/`, `public/`, `.env.example`, les ressources indispensables et la structure du projet. Les documents et SQL restent hors web.
4. **Ne pas encore copier le projet entier dans `www/s302/` ni publier le site.** Une fois le sous-dossier adapté et le point d'entrée configuré, ne publier que le contenu web autorisé, sans `public/mesurer.php` ni `public/gen_volumes.php`, et tester que les fichiers privés ne sont pas accessibles. La disposition finale dépend des possibilités réelles de LineServ ; un simple glisser-déposer n'établit pas cette séparation.
5. Les transferts suivants doivent éviter d'écraser les configurations ou données de recette. Garder une sauvegarde et faire relire le plan de mise à jour ; ne jamais réinitialiser pour mettre à jour le code.

## 5. Initialiser une base **neuve et vide** avec phpMyAdmin

### 5.1 Avant l'import

- Se connecter à <https://linserv-info-01.campus.unice.fr/phpmyadmin> avec le compte autorisé par l'IUT. Créer une base **spécifique à S302 et à la recette**, ou faire attribuer une base neuve. Si l'IUT impose un nom préfixé par le login, utiliser ce nom exact. Vérifier visuellement qu'elle est sélectionnée et qu'elle ne contient aucune table, vue, routine ni trigger ; en cas de doute, **arrêter**, ne pas vider la base.
- Le fichier original `sql/01_minishop_schema.sql` contient `DROP DATABASE IF EXISTS minishop`, `CREATE DATABASE`, `USE minishop` et `DROP TABLE IF EXISTS parametre`. Ne **jamais** le coller ou l'importer tel quel sur LineServ et ne pas se contenter de « copier depuis CREATE TABLE » : cela conserverait notamment le `DROP TABLE`. La copie préparée ci-dessous ne cible aucun nom de base et retire ces quatre instructions et les bascules `FOREIGN_KEY_CHECKS` inutiles pour une base vide ; ce n'est pas une migration pour base existante.
- Les fichiers `02` et `03` comprennent des `DROP … IF EXISTS` de routines/triggers avant leur recréation. Ils sont acceptables uniquement sur la base neuve dédiée et sélectionnée, jamais sur une base partagée ou déjà utilisée. L'import peut échouer partiellement : ne pas relancer à l'aveugle.
- Le schéma contient des données fictives et des comptes dont les identifiants sont publiés dans le dépôt. Ne pas activer un site HTTP accessible sans restriction avec ces comptes. La base de recette ne doit contenir aucune donnée réelle.

### 5.2 Préparer la copie localement

```bash
cd /home/jojokes/Documents/BUT/Projet/s302
python3 scripts/preparer_sql_lineserv.py
```

Sortie attendue : `Copie créée : /tmp/minishop-lineserv-…/01_schema_pour_base_vide.sql`, suivie d'un rappel de sécurité. Le nom exact est aléatoire. Le script ne contacte aucun serveur, ne touche pas à l'original et refuse si les instructions à retirer ne correspondent plus au schéma. Il crée un dossier temporaire privé ; copier le chemin affiché pour le sélecteur de fichiers phpMyAdmin. Garder le terminal ouvert. Cette copie est une préparation, **pas** la preuve d'un import réussi.

Vérification facultative du préparateur, sans serveur ni base :

```bash
cd /home/jojokes/Documents/BUT/Projet/s302
python3 tests/python/test_preparer_sql_lineserv.py -v
```

Sortie attendue : quatre tests `ok` et `OK`. Ces tests travaillent sur des fichiers temporaires ; ils ne valident ni l'import sur phpMyAdmin ni le fonctionnement de MariaDB.

### 5.3 Import par l'interface

1. Dans phpMyAdmin, cliquer sur la **base de recette neuve et vide**, vérifier son nom à l'écran. Onglet **Importer** → choisir le fichier temporaire `01_schema_pour_base_vide.sql` → format SQL → exécuter. L'import comprend les tables, le jeu de démonstration et les vues. Patienter jusqu'au message de succès ; à la moindre erreur, arrêter et relever le message expurgé.
2. Toujours dans la même base, importer `sql/02_minishop_procedures.sql`, puis `sql/03_minishop_triggers.sql`, dans cet ordre. Ils contiennent des `DELIMITER` : privilégier **Importer** le fichier complet, ne pas supprimer les délimiteurs au hasard ni coller des morceaux dans l'onglet SQL. La prise en charge exacte dépend de la version de phpMyAdmin. Si erreur de syntaxe ou de privilèges, arrêter et demander la configuration adaptée à l'IUT ; ne pas contourner par `root` ou `SUPER`.
3. Facultatif et seulement après accord de l'équipe : importer une fois `sql/04_minishop_demo.sql` **après** 01, 02 et 03. Il crée des commandes et modifie les stocks. Ce fichier n'est pas idempotent : ne pas le rejouer pour « vérifier ». Le catalogue et les comptes fictifs existent déjà à l'étape 1, même sans 04.
4. Dans **Structure**, **Routines** et **Déclencheurs**, vérifier la présence des objets. À partir de la version de ces fichiers : 8 tables, 3 vues, 17 procédures, 1 fonction et 16 triggers sont attendus ; les interfaces et privilèges peuvent masquer certains objets. Compter l'inventaire visible et demander à l'IUT si une liste manque ; ce comptage ne valide pas leur fonctionnement.
5. À titre de vérification en lecture seule, l'onglet SQL de la base sélectionnée peut exécuter :

```sql
SELECT COUNT(*) AS categories FROM categorie;
SELECT COUNT(*) AS produits FROM produit;
SELECT COUNT(*) AS clients FROM client;
```

Avec le jeu SQL livré inchangé, attendre respectivement `4`, `36`, `3`. Cela indique uniquement que ces lignes ont été importées. Si les valeurs diffèrent, noter le résultat et arrêter avant une remise à zéro. Les données ou les scripts ont pu changer.

**En cas d'import incomplet :** ne pas réimporter l'ensemble ni supprimer des tables. Relever la base ciblée, l'étape, le code/message d'erreur sans secrets, les objets créés et demander conseil. Ne jamais lancer sur LineServ `run.sh --db`, `scripts/db_init.php`, `scripts/seed.php`, les tests SQL ou un script de charge : ils peuvent créer, supprimer ou réinitialiser des données et nécessitent d'autres droits.

## 6. Configuration applicative — après validation de l'architecture

1. Créer `.env` **côté serveur dans le dossier privé de la racine applicative**, depuis `.env.example`, seulement après avoir déterminé où le processus PHP lit la racine. Ce fichier est ignoré par Git ; ne pas le téléverser sous `www/` ni le joindre à un e-mail. Protéger la lecture selon les permissions et l'identité du processus PHP indiquées par l'IUT : un `chmod 600` arbitraire peut empêcher le serveur de lire le fichier si les utilisateurs diffèrent.
2. Renseigner `APP_ENV=prod`, `APP_DEBUG=0`, `STORAGE_DRIVER=sql`, `DB_HOST`, `DB_PORT` ou `DB_SOCKET`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` avec les valeurs **réellement attribuées**. Utiliser un compte limité à la base de recette, avec les droits applicatifs nécessaires et sans privilèges d'administration globale. Les variables d'environnement du processus priment sur `.env` ; vérifier ce point si une valeur semble ignorée.
3. Prévoir les répertoires privés nécessaires aux journaux et s'assurer que PHP peut y écrire, sans les rendre consultables par HTTP. Vérifier les cookies de session en HTTPS, y compris le drapeau `Secure` si un proxy termine TLS. Ne pas déposer de `phpinfo()` public.
4. Une fois l'adaptation du préfixe, du bootstrap et de la réécriture réalisée, faire valider que `app/`, `.env`, `data/`, `var/`, `.git/`, `sql/` ne sont pas servis. Vérifier depuis le navigateur que les URL privées répondent 403/404, **sans afficher ni partager leurs contenus** ; interrompre immédiatement en cas d'exposition et faire retirer l'accès. Une 403/404 seule ne prouve pas toutes les protections.
5. Activer l'accès restreint à la recette, neutraliser les comptes de démonstration publics ou remplacer leurs mots de passe, et fournir les nouveaux accès individuellement par canal confidentiel. Ne pas inventer de mot de passe dans les documents. Ne pas ouvrir le site tant que ces mesures ne sont pas vérifiées.

## 7. Contrôles sur l'instance LineServ avant invitation

Sur une instance de recette isolée, utiliser uniquement des comptes et données fictifs. Relever l'URL exacte, la date et la révision ; comparer aux fichiers réellement publiés.

- [ ] L'accueil, les images et CSS se chargent ; cliquer catalogue, recherche et fiche produit ne sort pas de `/~pj509414/s302/`.
- [ ] Formulaires et navigation conservent le préfixe ; les URL directes et rechargements fonctionnent ; une route inconnue donne une 404 sans détail interne.
- [ ] Inscription, connexion, panier, commande simulée, historique et déconnexion fonctionnent avec les comptes de recette.
- [ ] Après connexion admin autorisée : listes, modification d'un produit fictif et évolution d'une commande de test fonctionnent ; aucune action ne modifie d'autres projets.
- [ ] Répéter un parcours avec deux sessions isolées ; vérifier qu'aucune commande d'un autre compte n'est exposée.
- [ ] Test sur ordinateur et téléphone réel ; clavier et zoom ; vérifier une page connectée avec WAVE (§8).
- [ ] Pas d'accès public aux fichiers privés ou aux outils de développement ; débogage désactivé ; comptes de démonstration non exposés.
- [ ] Consigner les résultats réels et anomalies dans [la fiche](13-fiches-recette-uc.md) et [le suivi CSV](13-suivi-recette-uc.csv), pas dans ce guide comme s'ils étaient déjà validés.

Problèmes fréquents : **404 sur `/catalogue` ou CSS absent** → préfixe, réécriture et liens non adaptés ; **500 dès l'accueil** → chemin du bootstrap, droits de lecture, version PHP ou base ; **connexion DB refusée** → coordonnées et droits attribués, sans montrer le mot de passe ; **import SQL incomplet** → routines, délimiteurs ou privilèges, à constater sans réinitialiser. Ne pas activer le debug public pour obtenir un message ; voir les journaux privés avec l'accord de l'IUT.

## 8. Logiciels pour l'accessibilité et le responsive

### WAVE : repérer les problèmes d'accessibilité

[WAVE de WebAIM](https://wave.webaim.org/extension/) est une extension gratuite pour Firefox, Chrome et Edge. Elle analyse la page **rendue**, donc fonctionne avec un site PHP ; l'extension peut aussi analyser une page d'intranet ou une page où l'on est connecté. Selon WebAIM, l'analyse de l'extension s'effectue dans le navigateur, sans envoyer le contenu au serveur WAVE. Utiliser l'extension plutôt que le formulaire en ligne pour les pages privées.

1. Installer l'extension depuis le lien officiel et ouvrir l'URL LineServ réellement validée.
2. Sur l'accueil, cliquer l'icône WAVE. Relever les **Errors**, erreurs de contraste et **Alerts** : titre, description, élément et capture expurgée. Une alerte demande une vérification humaine, elle n'est pas forcément un défaut.
3. Recommencer pour catalogue, produit, panier, inscription, commande et les pages connectées/admin autorisées, y compris quand un message d'erreur est affiché. Une analyse ne couvre que la page et l'état affichés.
4. Tester en plus au clavier (`Tab`, `Maj+Tab`, `Entrée`, `Espace`, `Échap`) : focus visible, ordre logique, liens et formulaires utilisables ; essayer un lecteur d'écran comme Orca sous Linux ou NVDA sous Windows et demander des retours de personnes concernées si possible.

**Limite :** WAVE détecte une partie des problèmes seulement. Ni absence d'erreurs ni un score ne prouve une conformité WCAG ou RGAA. Un audit humain approfondi et, le cas échéant, une évaluation experte restent nécessaires. Source : [aide officielle WAVE](https://wave.webaim.org/help).

### Outils de développement du navigateur : tester le responsive

Dans Chrome ou Chromium : `F12` ou `Ctrl+Maj+I`, puis `Ctrl+Maj+M` pour activer la barre d'appareils. Dans le menu, choisir **Responsive** et saisir successivement `320`, `375`, `768` et `1440` pixels de largeur. Refaire les parcours principaux à chaque taille : pas de contenu coupé ou superposé, menu et boutons accessibles, formulaires utilisables et pas de défilement horizontal global ; un tableau peut avoir son propre conteneur défilant si nécessaire. Tester également le zoom navigateur à 200 %, puis le reflow à 320 px de largeur CSS ; les exceptions de contenu bidimensionnel sont à examiner au cas par cas.

L'onglet **Lighthouse → Accessibility → Analyze page load** donne un rapport complémentaire, pas une certification. Attention : un audit en mode navigation peut recharger la page ou modifier son état ; vérifier qu'on est toujours sur la bonne page et la bonne session, sans supprimer les données de recette. Enregistrer un rapport expurgé. Le mode appareil simule un écran, **pas** un vrai téléphone : compléter par un appareil physique et, si possible, un second navigateur. Voir la [documentation Chrome Device Mode](https://developer.chrome.com/docs/devtools/device-mode) et les [outils d'accessibilité Chrome](https://developer.chrome.com/docs/devtools/accessibility/reference).

## 9. Documents et communication

- [E-mail prêt à personnaliser pour la collègue](15-email-recette-lineserv.md), à envoyer seulement quand URL et accès sont réellement prêts.
- [Fiches des 14 UC](13-fiches-recette-uc.md) : actions, attendus, risques et protocole.
- [Tableau de suivi CSV](13-suivi-recette-uc.csv) : `OK`, `KO`, `BLOQUÉ`, `NON JOUÉ` ou `NON APPLICABLE`, preuves et résultats réels.
- [Cahier des charges](01-cahier-des-charges-MiniShop.md), [tests et validation](02-document-tests-validation.md), [information juridique de la maquette](12-information-juridique.md), [étude de design](DA.md).
- La maquette ne réalise ni paiement ni livraison ; l'annulation d'une commande n'est pas une fonction légale de rétractation. Aucune vente ni donnée personnelle réelle.

Sources : [phpMyAdmin — import dans une base sélectionnée](https://docs.phpmyadmin.net/en/latest/import_export.html), [WAVE](https://wave.webaim.org/extension/), [Chrome Device Mode](https://developer.chrome.com/docs/devtools/device-mode). Les indications propres à LineServ viennent de l'exemple de l'utilisateur et restent à confirmer sur place.
