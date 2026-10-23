<?php

declare(strict_types=1);

/**
 * MiniShop — chargement de la base MySQL/MariaDB (DDL + routines + triggers).
 *
 * Remplace un enchaînement de `mysql < sql/0x.sql` par du PHP/PDO : la même
 * commande fonctionne sous Linux, macOS, Windows et dans les conteneurs de CI
 * où le client `mysql` n'est pas installé. L'analyse des `DELIMITER` est faite
 * par App\Tools\SqlLoader.
 *
 * Usage :
 *   php scripts/load_db.php                       # base « minishop » + démo
 *   DB=minishop_test php scripts/load_db.php      # base de test
 *   SKIP_DEMO=1 php scripts/load_db.php           # sans sql/04 (jeu de démo)
 *
 * Variables : DB (minishop), DB_HOST (127.0.0.1), DB_PORT (3306),
 *             DB_USER (root), DB_PASS (''), SKIP_DEMO (0).
 *
 * Code retour : 0 succès, 1 échec de connexion ou de chargement.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Tools\SqlLoader;

$base = (string) (getenv('DB') ?: 'minishop');
if (preg_match('/^[A-Za-z0-9_]+$/', $base) !== 1) {
    fwrite(STDERR, "✘ nom de base invalide : $base\n");
    exit(2);
}

$hote = (string) (getenv('DB_HOST') ?: '127.0.0.1');
$port = (string) (getenv('DB_PORT') ?: '3306');
$utilisateur = (string) (getenv('DB_USER') ?: 'root');
$motDePasse = (string) (getenv('DB_PASS') ?: '');
$sansDemo = (getenv('SKIP_DEMO') ?: '0') === '1';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $serveur = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $hote, $port),
        $utilisateur,
        $motDePasse,
        $options,
    );
    $serveur->exec('CREATE DATABASE IF NOT EXISTS `' . $base . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
} catch (PDOException $erreur) {
    fwrite(STDERR, "✘ connexion MySQL impossible ($hote:$port) : " . $erreur->getMessage() . "\n");
    fwrite(STDERR, "  Vérifiez que le service tourne et que DB_USER/DB_PASS sont corrects.\n");
    exit(1);
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $hote, $port, $base),
        $utilisateur,
        $motDePasse,
        $options,
    );

    $fichiers = [
        dirname(__DIR__) . '/sql/01_minishop_schema.sql',
        dirname(__DIR__) . '/sql/02_minishop_procedures.sql',
        dirname(__DIR__) . '/sql/03_minishop_triggers.sql',
    ];
    if (!$sansDemo) {
        $fichiers[] = dirname(__DIR__) . '/sql/04_minishop_demo.sql';
    }

    $chargeur = new SqlLoader($pdo);
    foreach ($fichiers as $fichier) {
        $nombre = $chargeur->chargerFichier($fichier, true);
        printf("   %-45s %3d instruction(s)\n", basename($fichier), $nombre);
    }

    $objets = $chargeur->compteObjets();
    printf(
        "\n✔ base « %s » chargée : %d tables, %d procédures, %d fonction(s), %d déclencheurs, %d vues\n",
        $base,
        $objets['tables'],
        $objets['procedures'],
        $objets['fonctions'],
        $objets['triggers'],
        $objets['vues'],
    );
} catch (Throwable $erreur) {
    fwrite(STDERR, "✘ chargement échoué : " . $erreur->getMessage() . "\n");
    exit(1);
}
