<?php

declare(strict_types=1);

/**
 * MiniShop — intégration SQL : rejoue les 29 scénarios sur une base RÉELLE.
 *
 *   php tests/php/sql_integration.php            # crée minishop_test, joue, supprime
 *   php tests/php/sql_integration.php --garder   # laisse la base pour inspection
 *
 * Les autres suites tournent sur le moteur JSON : elles valident la logique,
 * jamais le SQL. Ce test est le seul qui exécute réellement les 17 procédures,
 * la fonction, les 16 déclencheurs et les 3 vues — c'est-à-dire la partie que
 * le correcteur SQL du PDO ne voit pas.
 *
 * **Isolation.** Le harnais `tests/sql/run_tests.sh` réinitialise l'état via
 * `fixture.sql` avant chaque scénario : le lancer sur la base de développement
 * détruirait les données de démonstration. Cette enveloppe crée donc une base
 * dédiée (`minishop_test` par défaut), y charge `sql/01 → 04`, joue la
 * campagne, puis la supprime. La base applicative n'est jamais touchée.
 *
 * **Dégradation.** Sans serveur joignable (ou sans client `mysql`/`mariadb`),
 * le test annonce NON JOUÉ et sort avec le code 2 : `scripts/run-all-tests.php`
 * le distingue d'un échec.
 */

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Config\Database;
use App\Config\DbConnexion;
use App\Tools\DbInstaller;

$racine = dirname(__DIR__, 2);
$garder = in_array('--garder', $_SERVER['argv'] ?? [], true);
$config = Database::config();
$baseDemandee = getenv('MINISHOP_TEST_DB');
$base = 'minishop_test';
if (is_string($baseDemandee) && $baseDemandee !== '') {
    $base = $baseDemandee;
}

echo "MiniShop — intégration SQL (base dédiée « " . $base . " »)\n";
echo str_repeat('-', 60) . "\n";

// ------------------------------------------------------------- prérequis
if (!extension_loaded('pdo_mysql')) {
    echo "⏭  NON JOUÉ : l'extension pdo_mysql n'est pas chargée.\n";
    echo "    Debian/Ubuntu : sudo apt install php" . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . "-mysql\n";
    exit(2);
}

$client = null;
foreach (['mariadb', 'mysql'] as $candidat) {
    $sortie = [];
    $code = 1;
    @exec('command -v ' . $candidat . ' 2>/dev/null', $sortie, $code);
    if ($code === 0 && $sortie !== []) {
        $client = $candidat;
        break;
    }
}
if ($client === null) {
    echo "⏭  NON JOUÉ : aucun client « mariadb » ou « mysql » dans le PATH\n";
    echo "    (le harnais tests/sql/run_tests.sh s'appuie sur le client en ligne de commande).\n";
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
echo "1. Base de test isolée\n";
try {
    $installateur->supprimerBase();
    $installateur->creerBase();
    echo "   ✓ « " . $base . " » créée (utf8mb4_unicode_ci)\n";
} catch (Throwable $erreur) {
    $message = $erreur->getMessage();
    echo "   ❌ création impossible : " . $message . "\n";

    // 1044 : pas le droit de créer la base ; 1142 : pas le droit d'y écrire.
    // Ce n'est pas un bug du test : le compte applicatif n'a volontairement de
    // droits que sur sa base. Le remède est une commande, pas un contournement.
    $refus = str_contains($message, '1044')
        || str_contains($message, '1142')
        || str_contains($message, 'Access denied');
    if ($refus) {
        echo "\n   Le compte « " . (string) ($config['db_user'] ?? '?') . " » n'a pas de droits sur"
            . " « " . $base . " » — c'est voulu (moindre privilège).\n";
        echo "   Créez la base de test une fois pour toutes, puis relancez :\n\n";
        echo "       php scripts/db_init.php --test-db\n\n";
        echo "   (crée « " . $base . " » et y accorde les droits ; sudo demandé une fois)\n";
    }
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

// Contrat de pagination du repository, sur la même base dédiée et en lecture seule.
// Jamais sur la base applicative. Les scénarios sont partagés avec le pilote JSON.
echo "\n3. Catalogue : ruptures en dernier (pilote SQL)\n";
$echecsCatalogue = 0;
try {
    require_once __DIR__ . '/catalogue_stock_cases.php';
    $stockSql = new \App\Model\Data\SqlStore(DbConnexion::ouvrir($base, $config));
    $resultatCatalogue = verifierCatalogueStock($stockSql);
    $echecsCatalogue = $resultatCatalogue['echecs'];
    if ($echecsCatalogue === 0) {
        echo '   ✅ ' . $resultatCatalogue['tests'] . " scénarios de catalogue SQL\n";
    } else {
        echo '   ❌ catalogue SQL : ' . $echecsCatalogue . " échec(s)\n";
    }
} catch (Throwable $erreur) {
    $echecsCatalogue = 1;
    echo '   ❌ catalogue SQL : ' . $erreur->getMessage() . "\n";
}

// ------------------------------------------------------- campagne SQL
echo "\n4. Campagne de scénarios SQL (tests/sql/run_tests.sh)\n";
$harnais = $racine . '/tests/sql/run_tests.sh';
if (!is_file($harnais)) {
    echo "   ❌ harnais absent : " . $harnais . "\n";
    exit(1);
}

$variables = [
    'DB_TEST' => $base,
    'DB_HOST' => (string) ($config['db_host'] ?? '127.0.0.1'),
    'DB_PORT' => (string) ($config['db_port'] ?? '3306'),
    'DB_USER' => (string) ($config['db_user'] ?? 'root'),
    'DB_PASS' => (string) ($config['db_pass'] ?? ''),
];
$prefixe = '';
foreach ($variables as $cle => $valeur) {
    $prefixe .= $cle . '=' . escapeshellarg($valeur) . ' ';
}

$sortie = [];
$code = 0;
@exec($prefixe . 'bash ' . escapeshellarg($harnais) . ' 2>&1', $sortie, $code);
echo implode("\n", $sortie) . "\n";
if ($echecsCatalogue !== 0 && $code === 0) {
    $code = 1;
}

// ------------------------------------------------------------ ménage
if ($garder) {
    echo "\n(base « " . $base . " » conservée — --garder)\n";
} else {
    try {
        $installateur->supprimerBase();
        echo "\n(base « " . $base . " » supprimée : les données de développement sont intactes)\n";
    } catch (Throwable $erreur) {
        echo "\n⚠ la base « " . $base . " » n'a pas pu être supprimée : " . $erreur->getMessage() . "\n";
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
if ($code === 0) {
    echo "SQL : CAMPAGNE CONFORME ✅\n";
    exit(0);
}
echo "SQL : ÉCHECS DANS LA CAMPAGNE ❌ (code " . $code . ")\n";
exit(1);
