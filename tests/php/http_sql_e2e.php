<?php

declare(strict_types=1);

/**
 * MiniShop — parcours HTTP SQL de bout en bout (pilote SQL par défaut).
 *
 *   php tests/php/http_sql_e2e.php            # base minishop_test_http, joue, supprime
 *   php tests/php/http_sql_e2e.php --garder   # laisse la base pour inspection
 *   php tests/php/http_sql_e2e.php --reinitialiser-base-existante
 *   php tests/php/http_sql_e2e.php --port=18099
 *
 * Cette suite prouve le chemin applicatif COMPLET sur le pilote SQL : un vrai
 * serveur `php -S` (routeur de développement, parité `.htaccess`), de vraies
 * requêtes HTTP avec cookies et jetons CSRF, et la base de données réelle
 * derrière chaque action. Les scénarios `T-*` (`sql_integration.php`)
 * prouvent les procédures ; l'E2E WASM prouve le pilote JSON ; ici, chaque
 * 302, chaque flash et chaque ligne de base sont vérifiés.
 *
 * Couverture (sections 4 à 9) : visiteur (catalogue, recherche, fiche,
 * produit masqué, 404), inscription (validations, doublon, CGV, CSRF),
 * connexion (`sp_get_credentials` via PDO + `password_verify`, régénération
 * de session, compte bloqué, messages identiques), panier (plafond au stock,
 * produit masqué), commande (numéro `CMD`, historique, annulation, IDOR en
 * 404 générique), back-office (produits, catégories, stocks motivés,
 * statuts, garde `SUPER` — RB-13).
 *
 * **Isolation.** Base dédiée `minishop_test_http` (jamais `DB_NAME`),
 * créée/supprimée par la suite sauf `--garder`. Sans serveur MySQL joignable
 * (ou sans `pdo_mysql`), la suite annonce NON JOUÉ et sort en code 2 :
 * `scripts/run-all-tests.php` le distingue d'un échec.
 */

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once __DIR__ . '/http_support.php';

use App\Config\Database;
use App\Config\DbConnexion;
use App\Tools\DbInstaller;
use MiniShop\Tests\HttpClient;
use MiniShop\Tests\ServeurTest;
use MiniShop\Tests\Verificateur;
use function MiniShop\Tests\environnement;
use function MiniShop\Tests\portLibre;

$racine = dirname(__DIR__, 2);
$garder = in_array('--garder', $_SERVER['argv'] ?? [], true);
$reinitialiser = in_array('--reinitialiser-base-existante', $_SERVER['argv'] ?? [], true);
$portForce = 0;
foreach ($_SERVER['argv'] ?? [] as $argument) {
    if (str_starts_with($argument, '--port=')) {
        $portForce = (int) substr($argument, strlen('--port='));
    }
}
$config = Database::config();
$baseDemandee = getenv('MINISHOP_TEST_DB');
$base = 'minishop_test_http';
if (is_string($baseDemandee) && $baseDemandee !== '') {
    $base = $baseDemandee;
}

echo "MiniShop — parcours HTTP SQL de bout en bout (base dédiée « " . $base . " »)\n";
echo str_repeat('-', 60) . "\n";

// Ne jamais toucher la base de l'application, même en mode explicite.
$baseApplication = (string) ($config['db_name'] ?? 'minishop');
if (strcasecmp($base, $baseApplication) === 0) {
    echo "❌ Refus : la base de test correspond à DB_NAME ; aucune action SQL.\n";
    exit(1);
}

// ------------------------------------------------------------- prérequis
if (PHP_SAPI !== 'cli' || PHP_BINARY === '' || !function_exists('proc_open')) {
    echo "⏭  NON JOUÉ : PHP CLI natif requis (SAPI actuel : " . PHP_SAPI . ").\n";
    exit(2);
}
if (!extension_loaded('pdo_mysql')) {
    echo "⏭  NON JOUÉ : l'extension pdo_mysql n'est pas chargée.\n";
    exit(2);
}

$installateur = new DbInstaller(
    (string) ($config['db_host'] ?? '127.0.0.1'),
    (string) ($config['db_port'] ?? '3306'),
    $base,
    (string) ($config['db_user'] ?? 'root'),
    (string) ($config['db_pass'] ?? ''),
    (string) ($config['db_socket'] ?? ''),
);

if (!$installateur->serveurJoignable()) {
    echo "⏭  NON JOUÉ : serveur MySQL injoignable — " . $installateur->derniereErreur() . "\n";
    exit(2);
}

// ------------------------------------------------------- base dédiée
echo "\n1. Base de test isolée\n";
try {
    if ($reinitialiser) {
        echo "   ⚠ Réinitialisation explicitement demandée : suppression de « " . $base . " » si elle existe.\n";
        $installateur->supprimerBase();
    }
    $installateur->creerBaseNouvelle();
    echo "   ✓ « " . $base . " » créée (utf8mb4_unicode_ci)\n";
} catch (Throwable $erreur) {
    $message = $erreur->getMessage();
    echo "   ❌ création impossible : " . $message . "\n";
    echo "   Si la base existe déjà, elle n'a pas été supprimée par défaut.\n";
    echo "   Sur un serveur isolé uniquement : --reinitialiser-base-existante.\n";
    exit(1);
}

echo "\n2. Chargement du schéma, des routines et du jeu de démonstration\n";
try {
    $resultats = $installateur->charger(DbInstaller::scripts(true), static function (string $fichier, int $nombre): void {
        printf("   %-32s %3d instruction(s)\n", $fichier, $nombre);
    });
    if ($resultats === []) {
        echo "   ❌ aucun script chargé\n";
        exit(1);
    }
} catch (Throwable $erreur) {
    echo "   ❌ chargement impossible : " . $erreur->getMessage() . "\n";
    exit(1);
}

// ------------------------------------------------------- serveur HTTP SQL
echo "\n3. Serveur HTTP sur le pilote SQL\n";
$port = portLibre();
if ($portForce > 0) {
    $port = $portForce;
}
$serveur = new ServeurTest($port, $racine, environnement([
    'STORAGE_DRIVER' => 'sql',
    'DB_HOST' => (string) ($config['db_host'] ?? '127.0.0.1'),
    'DB_PORT' => (string) ($config['db_port'] ?? '3306'),
    'DB_NAME' => $base,
    'DB_USER' => (string) ($config['db_user'] ?? 'root'),
    'DB_PASSWORD' => (string) ($config['db_pass'] ?? ''),
    'DB_SOCKET' => (string) ($config['db_socket'] ?? ''),
]));
if (!$serveur->demarrer()) {
    echo "   ❌ le serveur de test n'a pas démarré\n";
    exit(1);
}
echo "   ✓ php -S sur 127.0.0.1:{$port} (STORAGE_DRIVER=sql, base « {$base} »)\n";

$verif = new Verificateur();
$pdo = DbConnexion::ouvrir($base, $config);
$requeteId = static function (string $sql, array $params = []) use ($pdo): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
};
$requeteStock = static function (string $reference) use ($requeteId): int {
    return $requeteId('SELECT stock FROM produit WHERE reference = ?', [$reference]);
};

try {
    $idCasque = $requeteId('SELECT id_produit FROM produit WHERE reference = ?', ['CASQ-005']);
    $idBracelet = $requeteId('SELECT id_produit FROM produit WHERE reference = ?', ['BRAC-012']);
    $idCategorie = $requeteId('SELECT id_categorie FROM categorie ORDER BY id_categorie LIMIT 1');
    $stockCasqueInitial = $requeteStock('CASQ-005');
    $verif->ok($idCasque > 0 && $idBracelet > 0 && $idCategorie > 0, 'fixtures de démonstration présentes (CASQ-005, BRAC-012, catégorie)');

    // ------------------------------------------------------- §4 visiteur
    echo "\n4. Visiteur (sans compte)\n";
    $visiteur = new HttpClient($port);

    $accueil = $visiteur->get('/');
    $verif->egal(200, $accueil->statut, 'GET / → 200');
    $verif->contient($accueil->corps, 'Bienvenue chez MiniShop', 'accueil : titre de la boutique');
    $verif->contient($accueil->corps, 'href="/produit/', 'accueil : cartes produits (nouveautés)');

    $catalogue = $visiteur->get('/catalogue');
    $verif->egal(200, $catalogue->statut, 'GET /catalogue → 200');
    $verif->contient($catalogue->corps, 'Casque sans fil Aura', 'catalogue : produit visible présent');
    $verif->neContientPas($catalogue->corps, 'href="/produit/bracelet-connecte-band"', 'catalogue : produit masqué absent (BRAC-012)');

    $recherche = $visiteur->get('/recherche?q=casque');
    $verif->egal(200, $recherche->statut, 'GET /recherche?q=casque → 200');
    $verif->contient($recherche->corps, 'Casque sans fil Aura', 'recherche : résultat pertinent');

    $fiche = $visiteur->get('/produit/casque-sans-fil-aura');
    $verif->egal(200, $fiche->statut, 'GET /produit/casque-sans-fil-aura → 200');
    $verif->contient($fiche->corps, 'Casque sans fil Aura', 'fiche : nom du produit');
    $verif->contient($fiche->corps, '154,80 €', 'fiche : prix TTC formaté');

    $ficheMasquee = $visiteur->get('/produit/bracelet-connecte-band');
    $verif->egal(404, $ficheMasquee->statut, 'GET /produit/bracelet-connecte-band (masqué) → 404');

    $inconnue = $visiteur->get('/route-qui-nexiste-pas');
    $verif->egal(404, $inconnue->statut, 'GET /route-qui-nexiste-pas → 404');
    $verif->contient($inconnue->corps, 'Page introuvable', '404 : page du frontal');

    // ------------------------------------------------------- §5 inscription
    echo "\n5. Inscription (validations, doublon, CGV, CSRF)\n";
    $client = new HttpClient($port);

    $formulaire = $client->get('/inscription');
    $verif->egal(200, $formulaire->statut, 'GET /inscription → 200');
    $verif->ok($client->csrf($formulaire->corps) !== null, 'formulaire : jeton CSRF présent');

    $emailInvalide = $client->postFormulaire('/inscription', '/inscription', [
        'nom' => 'Test', 'prenom' => 'Http', 'email' => 'pas-un-email',
        'mot_de_passe' => 'MotDePasse123!', 'confirmation' => 'MotDePasse123!', 'cgv' => '1',
    ]);
    $verif->egal(200, $emailInvalide->statut, 'inscription email invalide → 200 (formulaire réaffiché)');
    $verif->contient($emailInvalide->corps, 'Adresse email invalide.', 'inscription : erreur email');

    $motDePasseCourt = $client->postFormulaire('/inscription', '/inscription', [
        'nom' => 'Test', 'prenom' => 'Http', 'email' => 'http.test@example.test',
        'mot_de_passe' => 'court', 'confirmation' => 'court', 'cgv' => '1',
    ]);
    $verif->contient($motDePasseCourt->corps, 'Le mot de passe doit contenir au moins 8 caractères.', 'inscription : mot de passe trop court');

    $confirmationFausse = $client->postFormulaire('/inscription', '/inscription', [
        'nom' => 'Test', 'prenom' => 'Http', 'email' => 'http.test@example.test',
        'mot_de_passe' => 'MotDePasse123!', 'confirmation' => 'AutreMotDePasse!', 'cgv' => '1',
    ]);
    $verif->contient($confirmationFausse->corps, 'La confirmation du mot de passe ne correspond pas.', 'inscription : confirmation différente');

    $sansCgv = $client->postFormulaire('/inscription', '/inscription', [
        'nom' => 'Test', 'prenom' => 'Http', 'email' => 'http.test@example.test',
        'mot_de_passe' => 'MotDePasse123!', 'confirmation' => 'MotDePasse123!',
    ]);
    $verif->contient($sansCgv->corps, 'Vous devez accepter les conditions générales de vente.', 'inscription : CGV non acceptées');

    $doublon = $client->postFormulaire('/inscription', '/inscription', [
        'nom' => 'Test', 'prenom' => 'Http', 'email' => 'alice@example.com',
        'mot_de_passe' => 'MotDePasse123!', 'confirmation' => 'MotDePasse123!', 'cgv' => '1',
    ]);
    $verif->contient($doublon->corps, 'Cette adresse email est déjà utilisée.', 'inscription : email déjà utilisé (alice)');

    $sansCsrf = $client->post('/inscription', [
        'nom' => 'Test', 'prenom' => 'Http', 'email' => 'http.test@example.test',
        'mot_de_passe' => 'MotDePasse123!', 'confirmation' => 'MotDePasse123!', 'cgv' => '1',
    ]);
    $verif->egal(302, $sansCsrf->statut, 'inscription sans CSRF → 302 (refus)');
    $verif->egal('/', $sansCsrf->location(), 'inscription sans CSRF → retour à l’accueil');

    $inscrit = $client->postFormulaire('/inscription', '/inscription', [
        'nom' => 'Test', 'prenom' => 'Http', 'email' => 'http.test@example.test',
        'mot_de_passe' => 'MotDePasse123!', 'confirmation' => 'MotDePasse123!', 'cgv' => '1',
    ]);
    $verif->egal(302, $inscrit->statut, 'inscription valide → 302');
    $verif->egal('/compte', $inscrit->location(), 'inscription valide → /compte');

    $compte = $client->get('/compte');
    $verif->egal(200, $compte->statut, 'GET /compte (connecté) → 200');
    $verif->contient($compte->corps, 'http.test@example.test', 'compte : email du client créé');

    // ------------------------------------------------------- §6 connexion
    echo "\n6. Connexion (sp_get_credentials + password_verify)\n";
    $connexion = new HttpClient($port);
    $pageConnexion = $connexion->get('/connexion');
    $verif->egal(200, $pageConnexion->statut, 'GET /connexion → 200');
    $sessionAvant = $connexion->cookieSession();
    $verif->ok($sessionAvant !== null && $sessionAvant !== '', 'session anonyme ouverte avant connexion');

    $connecte = $connexion->postFormulaire('/connexion', '/connexion', [
        'email' => 'alice@example.com', 'mot_de_passe' => 'Demo2026!',
    ]);
    $verif->egal(302, $connecte->statut, 'connexion alice → 302');
    $verif->egal('/compte', $connecte->location(), 'connexion alice → /compte');
    $sessionApres = $connexion->cookieSession();
    $verif->ok($sessionApres !== null && $sessionApres !== $sessionAvant, 'session régénérée à la connexion (anti-fixation)');

    $espace = $connexion->get('/compte');
    $verif->egal(200, $espace->statut, 'GET /compte après connexion → 200');

    $deconnexion = $connexion->get('/deconnexion');
    $verif->egal(302, $deconnexion->statut, 'GET /deconnexion → 302');
    $verif->egal('/', $deconnexion->location(), 'déconnexion → accueil');
    $apresDeconnexion = $connexion->get('/compte');
    $verif->egal(302, $apresDeconnexion->statut, 'GET /compte déconnecté → 302 (garde client)');
    $verif->egal('/connexion', $apresDeconnexion->location(), 'garde client → /connexion');

    $mauvaisMotDePasse = new HttpClient($port);
    $echec = $mauvaisMotDePasse->postFormulaire('/connexion', '/connexion', [
        'email' => 'alice@example.com', 'mot_de_passe' => 'MauvaisMotDePasse!',
    ]);
    $verif->egal(200, $echec->statut, 'connexion mot de passe erroné → 200');
    $verif->contient($echec->corps, 'Email ou mot de passe erroné.', 'connexion : message générique (SEC-05)');

    $emailInconnu = new HttpClient($port);
    $inconnu = $emailInconnu->postFormulaire('/connexion', '/connexion', [
        'email' => 'personne@example.test', 'mot_de_passe' => 'MotDePasse123!',
    ]);
    $verif->contient($inconnu->corps, 'Email ou mot de passe erroné.', 'connexion : même message pour un email inconnu');

    // Compte bloqué : bascule directe en base (actif = 0), puis tentative.
    $pdo->exec("UPDATE client SET actif = 0 WHERE email = 'carla@example.com'");
    $bloque = new HttpClient($port);
    $tentativeBloquee = $bloque->postFormulaire('/connexion', '/connexion', [
        'email' => 'carla@example.com', 'mot_de_passe' => 'Demo2026!',
    ]);
    $verif->contient($tentativeBloquee->corps, 'Ce compte est bloqué. Contactez le support.', 'connexion : compte bloqué signalé');
    $pdo->exec("UPDATE client SET actif = 1 WHERE email = 'carla@example.com'");
    $verif->egal(1, $requeteId("SELECT actif FROM client WHERE email = 'carla@example.com'"), 'compte débloqué après le test');

    // ------------------------------------------------------- §7 panier
    echo "\n7. Panier (plafond au stock, produit masqué)\n";
    $panierVide = $client->get('/panier');
    $verif->egal(200, $panierVide->statut, 'GET /panier → 200');

    $ajout = $client->postFormulaire('/panier', '/panier/ajouter', [
        'id_produit' => (string) $idCasque, 'quantite' => '2',
    ]);
    $verif->egal(302, $ajout->statut, 'POST /panier/ajouter → 302');
    $verif->egal('/panier', $ajout->location(), 'ajout → /panier');
    $panier = $client->get('/panier');
    $verif->contient($panier->corps, 'Casque sans fil Aura', 'panier : produit présent');
    $verif->contient($panier->corps, 'value="2"', 'panier : quantité 2');

    $plafond = $client->postFormulaire('/panier', '/panier/quantite', [
        'id_produit' => (string) $idCasque, 'quantite' => '99',
    ]);
    $verif->egal(302, $plafond->statut, 'POST /panier/quantite (99) → 302');
    $panierPlafonne = $client->get('/panier');
    $verif->contient($panierPlafonne->corps, 'value="' . $stockCasqueInitial . '"', 'panier : quantité plafonnée au stock serveur (' . $stockCasqueInitial . ')');

    $ajoutMasque = $client->postFormulaire('/panier', '/panier/ajouter', [
        'id_produit' => (string) $idBracelet, 'quantite' => '1',
    ]);
    $verif->egal(302, $ajoutMasque->statut, 'POST /panier/ajouter (masqué) → 302');
    $panierApresMasque = $client->get('/panier');
    $verif->neContientPas($panierApresMasque->corps, 'Bracelet connecté Band', 'panier : produit masqué refusé');

    // ------------------------------------------------------- §8 commande
    echo "\n8. Commande (numéro CMD, historique, annulation, IDOR)\n";
    $formulaireCommande = $client->get('/commande/valider');
    $verif->egal(200, $formulaireCommande->statut, 'GET /commande/valider → 200');

    $commande = $client->postFormulaire('/commande/valider', '/commande/valider', [
        'adresse' => '1 rue des Tests, 06000 Nice', 'paiement_simule' => '1',
    ]);
    $verif->egal(302, $commande->statut, 'POST /commande/valider → 302');
    $cibleCommande = $commande->location() ?? '';
    $verif->ok(str_starts_with($cibleCommande, '/mes-commandes/'), 'commande → détail de la commande créée');
    $idCommande = (int) substr($cibleCommande, strlen('/mes-commandes/'));
    $verif->ok($idCommande > 0, 'identifiant de commande extrait de la redirection');

    $detail = $client->get('/mes-commandes/' . $idCommande);
    $verif->egal(200, $detail->statut, 'GET /mes-commandes/{id} → 200');
    $verif->ok(preg_match('#CMD\d{4}-\d{6}#', $detail->corps) === 1, 'détail : numéro CMD{AAAA}-{6 chiffres}');
    $verif->contient($detail->corps, 'Casque sans fil Aura', 'détail : ligne de commande');

    $historique = $client->get('/mes-commandes');
    $verif->egal(200, $historique->statut, 'GET /mes-commandes → 200');
    $verif->ok(preg_match('#CMD\d{4}-\d{6}#', $historique->corps) === 1, 'historique : la commande y figure');

    $stockApresCommande = $requeteStock('CASQ-005');
    $verif->egal($stockCasqueInitial - $stockCasqueInitial, $stockApresCommande, 'stock décrémenté de ' . $stockCasqueInitial . ' unités (panier plafonné)');

    $confirmation = $client->get('/mes-commandes/' . $idCommande . '/annulation/confirmation');
    $verif->egal(200, $confirmation->statut, 'GET confirmation d’annulation → 200');
    $annulation = $client->postFormulaire(
        '/mes-commandes/' . $idCommande . '/annulation/confirmation',
        '/mes-commandes/' . $idCommande . '/annulation',
        []
    );
    $verif->egal(302, $annulation->statut, 'POST annulation → 302');
    $verif->egal('/mes-commandes', $annulation->location(), 'annulation → historique');
    $verif->egal($stockCasqueInitial, $requeteStock('CASQ-005'), 'stock restauré exactement après annulation');

    // IDOR : la commande d'un autre client répond 404 générique (SEC-08).
    $autreClient = new HttpClient($port);
    $autreClient->postFormulaire('/connexion', '/connexion', [
        'email' => 'bruno@example.com', 'mot_de_passe' => 'Demo2026!',
    ]);
    $idor = $autreClient->get('/mes-commandes/' . $idCommande);
    $verif->egal(404, $idor->statut, 'IDOR : commande d’autrui → 404 générique');
    $verif->contient($idor->corps, 'Page introuvable', 'IDOR : aucune information divulguée');
    $absente = $autreClient->get('/mes-commandes/999999');
    $verif->egal(404, $absente->statut, 'commande inexistante → 404');

    // Seconde commande (conservée) pour les transitions de statut admin (§9).
    $client->postFormulaire('/panier', '/panier/ajouter', [
        'id_produit' => (string) $idCasque, 'quantite' => '1',
    ]);
    $commande2 = $client->postFormulaire('/commande/valider', '/commande/valider', [
        'adresse' => '1 rue des Tests, 06000 Nice', 'paiement_simule' => '1',
    ]);
    $cibleCommande2 = $commande2->location() ?? '';
    $idCommande2 = (int) substr($cibleCommande2, strlen('/mes-commandes/'));
    $verif->ok($idCommande2 > 0 && $idCommande2 !== $idCommande, 'seconde commande créée pour le back-office');

    // ------------------------------------------------------- §9 back-office
    echo "\n9. Back-office (produits, catégories, stocks, statuts, RB-13)\n";
    $anonyme = new HttpClient($port);
    $gardeAnonyme = $anonyme->get('/admin');
    $verif->egal(302, $gardeAnonyme->statut, 'RB-13 : anonyme GET /admin → 302');
    $verif->egal('/admin/connexion', $gardeAnonyme->location(), 'RB-13 : anonyme → /admin/connexion');

    $gardeClient = $autreClient->get('/admin');
    $verif->egal(302, $gardeClient->statut, 'RB-13 : client GET /admin → 302 (séparation des sessions)');
    $verif->egal('/admin/connexion', $gardeClient->location(), 'RB-13 : client → /admin/connexion');

    $admin = new HttpClient($port);
    $connexionAdmin = $admin->postFormulaire('/admin/connexion', '/admin/connexion', [
        'email' => 'admin@minishop.fr', 'mot_de_passe' => 'Admin2026!',
    ]);
    $verif->egal(302, $connexionAdmin->statut, 'connexion admin → 302');
    $verif->egal('/admin', $connexionAdmin->location(), 'connexion admin → /admin');
    $tableauDeBord = $admin->get('/admin');
    $verif->egal(200, $tableauDeBord->statut, 'GET /admin → 200');

    $equipe = $admin->get('/admin/equipe');
    $verif->egal(200, $equipe->statut, 'GET /admin/equipe (SUPER) → 200');

    $listeProduits = $admin->get('/admin/produits');
    $verif->egal(200, $listeProduits->statut, 'GET /admin/produits → 200');
    $verif->contient($listeProduits->corps, 'CASQ-005', 'liste produits : CASQ-005 présent');

    $creation = $admin->postFormulaire('/admin/produit/nouveau', '/admin/produit/nouveau', [
        'reference' => 'TEST-HTTP-001', 'nom' => 'Produit Test HTTP', 'slug' => 'produit-test-http',
        'id_categorie' => (string) $idCategorie, 'description' => 'Produit créé par la suite HTTP.',
        'prix_ht' => '10.00', 'tva' => '20', 'stock' => '10', 'seuil_alerte' => '2',
        'image_url' => '', 'visible' => '1',
    ]);
    $verif->egal(302, $creation->statut, 'POST création produit → 302');
    $verif->egal('/admin/produits', $creation->location(), 'création → liste des produits');
    $idTest = $requeteId('SELECT id_produit FROM produit WHERE reference = ?', ['TEST-HTTP-001']);
    $verif->ok($idTest > 0, 'produit TEST-HTTP-001 créé en base');

    $formulaireModification = $admin->get('/admin/produit/' . $idTest . '/modification');
    $verif->egal(200, $formulaireModification->statut, 'GET formulaire de modification → 200');
    $modification = $admin->postFormulaire(
        '/admin/produit/' . $idTest . '/modification',
        '/admin/produit/' . $idTest . '/modification',
        [
            'reference' => 'TEST-HTTP-001', 'nom' => 'Produit Test HTTP Modifié', 'slug' => 'produit-test-http',
            'id_categorie' => (string) $idCategorie, 'description' => 'Produit créé par la suite HTTP.',
            'prix_ht' => '12.00', 'tva' => '20', 'stock' => '10', 'seuil_alerte' => '2',
            'image_url' => '', 'visible' => '1',
        ]
    );
    $verif->egal(302, $modification->statut, 'POST modification produit → 302');
    $nomModifie = (string) $pdo->query('SELECT nom FROM produit WHERE id_produit = ' . $idTest)->fetchColumn();
    $verif->egal('Produit Test HTTP Modifié', $nomModifie, 'modification appliquée en base');

    $listeCategories = $admin->get('/admin/categories');
    $verif->egal(200, $listeCategories->statut, 'GET /admin/categories → 200');
    $creationCategorie = $admin->postFormulaire('/admin/categories', '/admin/categories', [
        'nom' => 'Rayon Test HTTP', 'slug' => 'rayon-test-http', 'description' => 'Catégorie de test.',
    ]);
    $verif->egal(302, $creationCategorie->statut, 'POST création catégorie → 302');
    $idCategorieTest = $requeteId('SELECT id_categorie FROM categorie WHERE slug = ?', ['rayon-test-http']);
    $verif->ok($idCategorieTest > 0, 'catégorie rayon-test-http créée en base');

    // Suppression d'une catégorie non vide : refusée, catégorie conservée.
    $suppressionCategorie = $admin->postFormulaire('/admin/categories', '/admin/categories/' . $idCategorie . '/suppression', []);
    $verif->egal(302, $suppressionCategorie->statut, 'POST suppression catégorie non vide → 302');
    $verif->egal(1, $requeteId('SELECT COUNT(*) FROM categorie WHERE id_categorie = ' . $idCategorie), 'catégorie non vide conservée');

    $listeStocks = $admin->get('/admin/stocks');
    $verif->egal(200, $listeStocks->statut, 'GET /admin/stocks → 200');
    $stockAvant = $requeteStock('CASQ-005');
    $ajustement = $admin->postFormulaire('/admin/stocks', '/admin/stocks', [
        'id_produit' => (string) $idCasque, 'mode' => 'DELTA', 'quantite' => '5', 'motif' => 'Réassort test HTTP',
    ]);
    $verif->egal(302, $ajustement->statut, 'POST ajustement de stock → 302');
    $verif->egal('/admin/stocks', $ajustement->location(), 'ajustement → /admin/stocks');
    $verif->egal($stockAvant + 5, $requeteStock('CASQ-005'), 'stock ajusté de +5 avec motif');

    $listeCommandes = $admin->get('/admin/commandes');
    $verif->egal(200, $listeCommandes->statut, 'GET /admin/commandes → 200');
    $detailAdmin = $admin->get('/admin/commandes/' . $idCommande2);
    $verif->egal(200, $detailAdmin->statut, 'GET /admin/commandes/{id} → 200');

    $statutValide = $admin->postFormulaire('/admin/commandes/' . $idCommande2 . '/statut', '/admin/commandes/' . $idCommande2 . '/statut', [
        'statut' => 'EN_PREPARATION', 'commentaire' => 'Préparation test HTTP',
    ]);
    $verif->egal(302, $statutValide->statut, 'POST statut EN_PREPARATION → 302');
    $statutApres = (string) $pdo->query('SELECT statut FROM commande WHERE id_commande = ' . $idCommande2)->fetchColumn();
    $verif->egal('EN_PREPARATION', $statutApres, 'statut passé à EN_PREPARATION');

    $statutInterdit = $admin->postFormulaire('/admin/commandes/' . $idCommande2 . '/statut', '/admin/commandes/' . $idCommande2 . '/statut', [
        'statut' => 'BROUILLON', 'commentaire' => 'Retour interdit test HTTP',
    ]);
    $verif->egal(302, $statutInterdit->statut, 'POST statut interdit → 302 (refus)');
    $statutFinal = (string) $pdo->query('SELECT statut FROM commande WHERE id_commande = ' . $idCommande2)->fetchColumn();
    $verif->egal('EN_PREPARATION', $statutFinal, 'transition interdite refusée (RB-11)');

    // Suppression d'un produit déjà commandé : bascule visible = 0, ligne conservée.
    $suppressionProduit = $admin->postFormulaire('/admin/produits', '/admin/produits/' . $idCasque . '/suppression', []);
    $verif->egal(302, $suppressionProduit->statut, 'POST suppression produit commandé → 302');
    $verif->egal(1, $requeteId('SELECT COUNT(*) FROM produit WHERE id_produit = ' . $idCasque), 'produit commandé conservé (pas de suppression physique)');
    $verif->egal(0, $requeteId('SELECT visible FROM produit WHERE id_produit = ' . $idCasque), 'produit commandé masqué (visible = 0)');
} finally {
    $serveur->arreter();
}

// ------------------------------------------------------- nettoyage
echo "\n10. Nettoyage\n";
if ($garder) {
    echo "   ✓ base « {$base} » conservée (--garder)\n";
} else {
    try {
        $installateur->supprimerBase();
        echo "   ✓ base « {$base} » supprimée\n";
    } catch (Throwable $erreur) {
        echo "   ⚠ suppression impossible : " . $erreur->getMessage() . "\n";
    }
}

exit($verif->bilan('http-sql-e2e'));
