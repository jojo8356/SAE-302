<?php

declare(strict_types=1);

/**
 * MiniShop — générateur de volumétrie en ligne de commande (ENF-01/ENF-02).
 *
 * Écrit dans MySQL/MariaDB via PDO, en passant par les procédures stockées
 * pour les commandes — jamais par des INSERT bruts : le jeu de mesure est
 * donc cohérent avec les règles métier (TTC, montants, stock).
 *
 * Usage :
 *   php scripts/gen_volumes_cli.php                    # 200 produits / 1 000 commandes
 *   php scripts/gen_volumes_cli.php --produits 500 --commandes 5000
 *   php scripts/gen_volumes_cli.php --purge
 *   php scripts/gen_volumes_cli.php --load             # charge sql/01→04 avant de générer
 *
 * Variables : DB_HOST (127.0.0.1), DB_PORT (3306), DB_NAME (minishop_perf),
 *             DB_USER (root), DB_PASS ('').
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Config\DbConnexion;
use App\Tools\SqlLoader;
use App\Tools\VolumetryGenerator;

/** @return never */
function usage(string $message = ''): void
{
    if ($message !== '') {
        fwrite(STDERR, "✘ $message\n\n");
    }
    fwrite(STDERR, "Usage : php scripts/gen_volumes_cli.php [--produits N] [--commandes N] [--clients N] [--purge] [--load]\n");
    exit($message === '' ? 0 : 2);
}

$options = [
    'produits' => 200,
    'commandes' => 1000,
    'clients' => 10,
    'purge' => false,
    'load' => false,
];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--purge') {
        $options['purge'] = true;
    } elseif ($arg === '--load') {
        $options['load'] = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        usage();
    } elseif (preg_match('/^--(produits|commandes|clients)=(\d+)$/', $arg, $m) === 1) {
        $options[$m[1]] = (int) $m[2];
    } else {
        usage("option inconnue : $arg");
    }
}

$host = (string) (getenv('DB_HOST') ?: '127.0.0.1');
$port = (string) (getenv('DB_PORT') ?: '3306');
$user = (string) (getenv('DB_USER') ?: 'root');
$pass = (string) (getenv('DB_PASS') ?: '');
$dbName = (string) (getenv('DB_NAME') ?: 'minishop_perf');

if ($options['load']) {
    // Chargement sans shell : SQL exécuté par PDO via App\Tools\SqlLoader
    // (aucun binaire `mysql`, aucune fonction d'exécution — contrôle 9).
    echo "→ chargement de la base $dbName (sql/01→04) via PDO\n";
    try {
        $pdoChargement = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        DbConnexion::imposerFuseauUtc($pdoChargement);
        $pdoChargement->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $dbName)
            . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdoChargement->exec('USE `' . str_replace('`', '', $dbName) . '`');
        $chargeur = new SqlLoader($pdoChargement);
        $fichiers = glob(dirname(__DIR__) . '/sql/*.sql');
        sort($fichiers);
        $instructions = $chargeur->chargerFichiers($fichiers ?: [], true);
        $objets = $chargeur->compteObjets();
        printf(
            "   %d instructions — %d tables, %d procédures, %d fonction(s), %d déclencheurs, %d vues\n",
            $instructions,
            $objets['tables'],
            $objets['procedures'],
            $objets['fonctions'],
            $objets['triggers'],
            $objets['vues'],
        );
    } catch (PDOException $e) {
        fwrite(STDERR, "✘ chargement échoué : " . $e->getMessage() . "\n");
        exit(1);
    }
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName),
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );
    DbConnexion::imposerFuseauUtc($pdo);
} catch (PDOException $e) {
    fwrite(STDERR, "✘ connexion impossible à $host:$port/$dbName — " . $e->getMessage() . "\n");
    fwrite(STDERR, "  Astuce : ./scripts/load_db.sh puis relancer, ou ajouter --load.\n");
    exit(1);
}

$generateur = new VolumetryGenerator($pdo);
$log = [];

if ($options['purge']) {
    echo "== purge du jeu de mesure ==\n";
    foreach ($generateur->purge() as $table => $n) {
        printf("   %-10s %d ligne(s) supprimée(s)\n", $table, $n);
    }
    $options['produits'] = 0;
    $options['commandes'] = 0;
}

$avant = $generateur->currentCounts();
echo "== avant : " . json_encode($avant, JSON_UNESCAPED_UNICODE) . "\n";

if ($options['produits'] > 0) {
    $n = $generateur->generateProduits((int) $options['produits'], $log);
    echo "→ produits : $n insérés\n";
}
if ($options['clients'] > 0) {
    $generateur->generateClients((int) $options['clients'], $log);
}
if ($options['commandes'] > 0) {
    $resultat = $generateur->generateCommandes((int) $options['commandes'], 50, $log);
    echo "→ commandes : {$resultat['ok']} créées, {$resultat['errors']} refusées\n";
}

$apres = $generateur->currentCounts();
echo "== après : " . json_encode($apres, JSON_UNESCAPED_UNICODE) . "\n";

$verdict = true;
foreach (['ttc_incoherents', 'montants_incoherents'] as $cle) {
    $valeur = (int) ($apres[$cle] ?? 0);
    printf("   %-22s %d %s\n", $cle, $valeur, $valeur === 0 ? '✓' : '✘');
    if ($valeur !== 0) {
        $verdict = false;
    }
}

echo $verdict
    ? "✓ volumétrie cohérente — mesure possible : DB=$dbName ./tests/perf/mesurer_sql.sh 50\n"
    : "✘ incohérences détectées : les règles métier ne tiennent pas sur ce jeu\n";

exit($verdict ? 0 : 1);
