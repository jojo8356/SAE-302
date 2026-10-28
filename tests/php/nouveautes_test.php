<?php

declare(strict_types=1);

/** Vérifie les derniers ajouts sur une fixture JSON jetable, jamais les données applicatives. */
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require __DIR__ . '/nouveautes_cases.php';

use App\Model\Data\JsonStore;
use App\Model\Data\Seed;

$dossier = sys_get_temp_dir() . '/minishop_nouveautes_' . getmypid() . '_' . bin2hex(random_bytes(4));
$store = JsonStore::open($dossier);
try {
    Seed::force($store);
    echo "Derniers ajouts — tests de chronologie, visibilité et pagination\n";
    $resultat = verifierNouveautes($store);
    // Contrat statique complémentaire : ne remplace pas l'exécution MariaDB.
    $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/sql/02_minishop_procedures.sql');
    ++$resultat['tests'];
    if (str_contains($sql, "WHEN p_trie_par = 'nouveaute' THEN ' ORDER BY p.date_creation DESC, p.id_produit DESC'")) {
        echo "  ✅ définition SQL : même départage décroissant (contrôle statique)\n";
    } else {
        ++$resultat['echecs'];
        echo "  ❌ définition SQL : départage décroissant absent\n";
    }
    if ($resultat['echecs'] === 0) {
        echo 'NOUVEAUTÉS : TOUT EST VERT ✅ (' . $resultat['tests'] . " vérifications)\n";
    } else {
        echo 'NOUVEAUTÉS : ' . $resultat['echecs'] . ' échec(s) sur ' . $resultat['tests'] . " vérifications ❌\n";
    }
} finally {
    $fichiers = glob($dossier . '/*');
    if (!is_array($fichiers)) {
        $fichiers = [];
    }
    foreach ($fichiers as $fichier) {
        if (is_file($fichier)) {
            unlink($fichier);
        }
    }
    if (is_dir($dossier)) {
        rmdir($dossier);
    }
}
if ($resultat['echecs'] !== 0) {
    exit(1);
}
