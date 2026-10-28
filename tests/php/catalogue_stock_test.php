<?php

declare(strict_types=1);

/** Tests du tri des ruptures : fixture JSON temporaire, aucune donnée applicative modifiée. */
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require __DIR__ . '/catalogue_stock_cases.php';

use App\Model\Data\Filter;
use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use App\Repository\ProduitRepository;

$dossier = sys_get_temp_dir() . '/minishop_stock_' . getmypid() . '_' . bin2hex(random_bytes(4));
$store = JsonStore::open($dossier);
try {
    Seed::force($store);
    echo "Catalogue : recherche, filtres, tris et pagination — moteur JSON\n";
    $resultat = verifierCatalogueStock($store);
    $tests = $resultat['tests'];
    $echecs = $resultat['echecs'];

    // Contrôler aussi le changement de disponibilité et les deux extrêmes
    // sur cette fixture uniquement, jamais sur le magasin de l'application.
    $repo = new ProduitRepository($store);
    $check = static function (string $message, bool $ok) use (&$tests, &$echecs): void {
        ++$tests;
        if (!$ok) {
            ++$echecs;
            echo '  ❌ ' . $message . "\n";
        } else {
            echo '  ✅ ' . $message . "\n";
        }
    };
    $id = (int) $store->findOne('produit', [Filter::eq('reference', 'POWER-010')])['id_produit'];
    $avant = $repo->searchProducts(['mot_cle' => 'POWER', 'ruptures_fin' => true, 'par_page' => 60]);
    $store->update('produit', [Filter::eq('id_produit', $id)], ['stock' => 1]);
    $apres = $repo->searchProducts(['mot_cle' => 'POWER', 'ruptures_fin' => true, 'par_page' => 60]);
    $refsAvant = array_column($avant['lignes'], 'reference');
    $refsApres = array_column($apres['lignes'], 'reference');
    $check('réapprovisionnement : un stock faible repasse avant les ruptures',
        in_array('POWER-010', $refsAvant, true) && in_array('POWER-029', $refsAvant, true)
        && in_array('POWER-010', $refsApres, true) && in_array('POWER-030', $refsApres, true)
        && array_search('POWER-010', $refsAvant, true) > array_search('POWER-029', $refsAvant, true)
        && array_search('POWER-010', $refsApres, true) < array_search('POWER-030', $refsApres, true)
        && (int) $store->find('produit', $id)['stock'] === 1);
    $idDisponible = (int) $store->findOne('produit', [Filter::eq('reference', 'POWER-029')])['id_produit'];
    $store->update('produit', [Filter::eq('id_produit', $idDisponible)], ['stock' => 0]);
    $apresRupture = $repo->searchProducts(['mot_cle' => 'POWER', 'ruptures_fin' => true, 'par_page' => 1]);
    $check('passage en rupture : la première page ne contient que le produit disponible',
        $apresRupture['total'] === 3 && $apresRupture['pages'] === 3
        && ($apresRupture['lignes'][0]['reference'] ?? '') === 'POWER-010'
        && (int) $store->find('produit', $idDisponible)['stock'] === 0);
    $store->update('produit', [], ['stock' => 0]);
    $check('fixture : aucun stock positif', $store->count('produit', [Filter::gt('stock', 0)]) === 0);
    $toutRupture = verifierCatalogueStock($store);
    $tests += $toutRupture['tests'];
    $echecs += $toutRupture['echecs'];
    $check('fixture entièrement en rupture : matrice complète', $toutRupture['echecs'] === 0);
    $store->update('produit', [], ['stock' => 1]);
    $check('fixture : aucun stock nul', $store->count('produit', [Filter::lte('stock', 0)]) === 0);
    $toutDisponible = verifierCatalogueStock($store);
    $tests += $toutDisponible['tests'];
    $echecs += $toutDisponible['echecs'];
    $check('fixture entièrement disponible : matrice complète', $toutDisponible['echecs'] === 0);

    if ($echecs !== 0) {
        echo "CATALOGUE STOCK : {$echecs} échec(s) sur {$tests} scénarios ❌\n";
    } else {
        echo "CATALOGUE STOCK : TOUT EST VERT ✅ ({$tests} scénarios)\n";
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

if ($echecs !== 0) {
    exit(1);
}
