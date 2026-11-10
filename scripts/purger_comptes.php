<?php

declare(strict_types=1);

/** Traitement CLI : simulation par défaut. Aucun appel depuis une route web. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI uniquement');
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Config\Database;
use App\Service\RetentionComptes;
use App\Tools\Heure;

$args = array_slice($argv, 1);
if ($args !== [] && $args !== ['--executer']) {
    fwrite(STDERR, "Usage : php scripts/purger_comptes.php [--executer]\n");
    exit(2);
}
$executer = $args === ['--executer'];
try {
    $bilan = (new RetentionComptes(Database::store()))->traiter(Heure::maintenantUtc(), $executer);
    if ($executer) { echo 'EXÉCUTION'; } else { echo 'SIMULATION'; }
    foreach ($bilan as $cle => $valeur) { echo " $cle=$valeur"; }
    echo "\n";
    if ($bilan['commandes'] > 0 || $bilan['dates_invalides'] > 0) {
        echo "ATTENTION : comptes conservés pour examen ; journaux et sauvegardes non traités.\n";
    }
} catch (Throwable $erreur) {
    fwrite(STDERR, "Échec du traitement : " . $erreur->getMessage() . "\n");
    exit(1);
}
