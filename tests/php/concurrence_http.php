<?php

declare(strict_types=1);

/**
 * MiniShop — concurrence HTTP : 20 demandes pour 12 unités (scénario C-01).
 *
 *   php tests/php/concurrence_http.php            # 20 acteurs, stock 12
 *   php tests/php/concurrence_http.php --garder
 *   php tests/php/concurrence_http.php --reinitialiser-base-existante
 *   php tests/php/concurrence_http.php --demandes=20 --stock=12
 *
 * Version HTTP de `tests/charge/reserver.sh` : 20 acteurs jouent le parcours
 * client COMPLET en parallèle (inscription, panier, commande) contre un
 * produit `CHARGE-001` de 12 unités, sur un vrai serveur `php -S` multi-worker
 * (`PHP_CLI_SERVER_WORKERS`) et la base de test dédiée.
 *
 * Critère : exactement 12 commandes créées, 8 refus, stock final 0 (jamais
 * négatif), numéros de commande distincts — vérifiés côté acteurs ET en base.
 *
 * **Isolation.** Base dédiée `minishop_test_concurrence` (jamais `DB_NAME`),
 * supprimée sauf `--garder`. Sans serveur MySQL joignable (ou sans
 * `pdo_mysql`), la suite annonce NON JOUÉ et sort en code 2.
 */

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once __DIR__ . '/http_support.php';

use App\Config\Database;
use App\Config\DbConnexion;
use App\Tools\DbInstaller;
use MiniShop\Tests\ServeurTest;
use MiniShop\Tests\Verificateur;
use function MiniShop\Tests\environnement;
use function MiniShop\Tests\portLibre;

$racine = dirname(__DIR__, 2);
$garder = in_array('--garder', $_SERVER['argv'] ?? [], true);
$reinitialiser = in_array('--reinitialiser-base-existante', $_SERVER['argv'] ?? [], true);
$demandes = 20;
$stock = 12;
foreach ($_SERVER['argv'] ?? [] as $argument) {
    if (str_starts_with($argument, '--demandes=')) {
        $demandes = (int) substr($argument, strlen('--demandes='));
    }
    if (str_starts_with($argument, '--stock=')) {
        $stock = (int) substr($argument, strlen('--stock='));
    }
}
if ($demandes < 1 || $stock < 0) {
    fwrite(STDERR, "Options invalides : --demandes >= 1 et --stock >= 0 requis.\n");
    exit(1);
}
$config = Database::config();
$baseDemandee = getenv('MINISHOP_TEST_DB');
$base = 'minishop_test_concurrence';
if (is_string($baseDemandee) && $baseDemandee !== '') {
    $base = $baseDemandee;
}

echo "MiniShop — concurrence HTTP : {$demandes} demandes pour {$stock} unités (base « {$base} »)\n";
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

$pdo = DbConnexion::ouvrir($base, $config);
$idCategorie = (int) $pdo->query('SELECT id_categorie FROM categorie ORDER BY id_categorie LIMIT 1')->fetchColumn();
$pdo->prepare(
    'INSERT INTO produit (reference, nom, slug, description, prix_ht, tva, prix_ttc, stock, seuil_alerte, id_categorie, visible, image_url)'
    . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([
    'CHARGE-001', 'Produit Charge Concurrence', 'produit-charge-concurrence',
    'Produit de test de concurrence (C-01).', '10.00', '20.00', '12.00',
    $stock, 2, $idCategorie, 1, '',
]);
$idProduit = (int) $pdo->query("SELECT id_produit FROM produit WHERE reference = 'CHARGE-001'")->fetchColumn();
$maxCommandeAvant = (int) $pdo->query('SELECT COALESCE(MAX(id_commande), 0) FROM commande')->fetchColumn();
echo "   ✓ produit CHARGE-001 créé (stock {$stock}, id {$idProduit})\n";

// ------------------------------------------------------- serveur multi-worker
echo "\n3. Serveur HTTP multi-worker\n";
$port = portLibre();
$serveur = new ServeurTest($port, $racine, environnement([
    'STORAGE_DRIVER' => 'sql',
    'DB_HOST' => (string) ($config['db_host'] ?? '127.0.0.1'),
    'DB_PORT' => (string) ($config['db_port'] ?? '3306'),
    'DB_NAME' => $base,
    'DB_USER' => (string) ($config['db_user'] ?? 'root'),
    'DB_PASSWORD' => (string) ($config['db_pass'] ?? ''),
    'DB_SOCKET' => (string) ($config['db_socket'] ?? ''),
    'PHP_CLI_SERVER_WORKERS' => (string) max(2, $demandes),
]));
if (!$serveur->demarrer()) {
    echo "   ❌ le serveur de test n'a pas démarré\n";
    exit(1);
}
echo "   ✓ php -S sur 127.0.0.1:{$port} (PHP_CLI_SERVER_WORKERS=" . max(2, $demandes) . ")\n";

// ------------------------------------------------------- acteurs parallèles
echo "\n4. {$demandes} acteurs HTTP en parallèle\n";
$dossierResultats = sys_get_temp_dir() . '/minishop_concurrence_' . getmypid();
if (!is_dir($dossierResultats)) {
    mkdir($dossierResultats, 0777, true);
}
$processus = [];
for ($i = 1; $i <= $demandes; ++$i) {
    $fichier = $dossierResultats . '/acteur_' . $i . '.json';
    $descripteurs = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $tuyaux = [];
    $commande = [PHP_BINARY, __DIR__ . '/concurrence_worker.php', (string) $port, (string) $i, (string) $idProduit, $fichier];
    $proc = proc_open($commande, $descripteurs, $tuyaux, $racine, environnement([]));
    if (!is_resource($proc)) {
        fwrite(STDERR, "proc_open a échoué pour l'acteur {$i}.\n");
        continue;
    }
    fclose($tuyaux[0]);
    $processus[$i] = ['proc' => $proc, 'tuyaux' => $tuyaux, 'fichier' => $fichier];
}
echo "   ✓ " . count($processus) . " acteurs lancés\n";

$echeance = microtime(true) + 180.0;
$restants = $processus;
while ($restants !== [] && microtime(true) < $echeance) {
    foreach ($restants as $i => $entree) {
        $statut = proc_get_status($entree['proc']);
        if (!$statut['running']) {
            foreach ($entree['tuyaux'] as $tuyau) {
                if (is_resource($tuyau)) {
                    fclose($tuyau);
                }
            }
            proc_close($entree['proc']);
            unset($restants[$i]);
        }
    }
    if ($restants !== []) {
        usleep(100000);
    }
}
foreach ($restants as $entree) {
    proc_terminate($entree['proc']);
    proc_close($entree['proc']);
}
if ($restants !== []) {
    echo "   ⚠ " . count($restants) . " acteur(s) tué(s) après 180 s\n";
}
$serveur->arreter();

// ------------------------------------------------------- résultats
echo "\n5. Résultats des acteurs\n";
$commandes = 0;
$refus = 0;
$erreurs = 0;
for ($i = 1; $i <= $demandes; ++$i) {
    $fichier = $dossierResultats . '/acteur_' . $i . '.json';
    $statut = 'erreur';
    $detail = 'fichier de résultat absent';
    if (is_file($fichier)) {
        $lu = file_get_contents($fichier);
        if (is_string($lu)) {
            $decode = json_decode($lu, true);
            if (is_array($decode)) {
                $statut = (string) ($decode['statut'] ?? 'erreur');
                $detail = (string) ($decode['detail'] ?? '');
            }
        }
    }
    if ($statut === 'commande') {
        ++$commandes;
    } elseif ($statut === 'refuse') {
        ++$refus;
    } else {
        ++$erreurs;
    }
    printf("   acteur %2d : %-8s %s\n", $i, $statut, $detail);
    if (is_file($fichier)) {
        unlink($fichier);
    }
}
if (is_dir($dossierResultats)) {
    rmdir($dossierResultats);
}

// ------------------------------------------------------- contre-vérification en base
echo "\n6. Contre-vérification en base\n";
$verif = new Verificateur();
$verif->egal($stock, $commandes, "exactement {$stock} commandes créées");
$verif->egal($demandes - $stock, $refus, ($demandes - $stock) . ' refus pour stock insuffisant');
$verif->egal(0, $erreurs, 'aucune erreur technique');

$stockFinal = (int) $pdo->query("SELECT stock FROM produit WHERE reference = 'CHARGE-001'")->fetchColumn();
$verif->egal(0, $stockFinal, 'stock final 0 (jamais négatif)');
$verif->ok($stockFinal >= 0, 'stock jamais négatif');

$nouveaux = $pdo->query('SELECT numero FROM commande WHERE id_commande > ' . $maxCommandeAvant)->fetchAll(PDO::FETCH_COLUMN);
$verif->egal($stock, count($nouveaux), 'commandes en base = commandes côté acteurs');
$verif->egal(count($nouveaux), count(array_unique($nouveaux)), 'numéros de commande distincts');
$minimum = (int) $pdo->query('SELECT MIN(stock) FROM produit')->fetchColumn();
$verif->ok($minimum >= 0, 'aucun produit à stock négatif en base');

// ------------------------------------------------------- nettoyage
echo "\n7. Nettoyage\n";
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

exit($verif->bilan('concurrence-http'));
