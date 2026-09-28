<?php

declare(strict_types=1);

/**
 * MiniShop — peuplement initial du magasin de données (CLI).
 *
 * Crée (ou recrée) data/minishop/*.json à partir du schéma et du jeu de
 * démonstration : 4 catégories, 12 produits, 3 clients + 1 admin, 4 paramètres.
 *
 * Usage : php scripts/seed.php          (sur machine normale)
 *        node scripts/wasm_run.mjs scripts/seed.php   (dans la sandbox)
 *
 * Le moteur SQL n'étant pas encore branché (pivot JSON), ce script écrit
 * directement via StorageDriver::JSON. Il est STRICTEMENT idempotent :
 * un second passage réinitialise les données à l'état « usine ».
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Model\Data\JsonStore;
use App\Model\Data\Seed;

$dossier = MINISHOP_ROOT . '/data/minishop';

// réinitialisation explicite (le seed d'une base déjà peuplée est un reset,
// jamais un doublon : cohérent avec TRUNCATE des scripts SQL)
if (is_dir($dossier)) {
    $anciens = glob($dossier . '/*.json');
    if (is_array($anciens)) {
        foreach ($anciens as $fichier) {
            @unlink($fichier);
        }
    }
}

$store = JsonStore::open($dossier);
Seed::force($store);

$nb = [];
foreach (['administrateur', 'client', 'categorie', 'produit', 'commande', 'ligne_commande', 'parametre'] as $table) {
    $nb[$table] = $store->count($table);
}

echo "Base JSON peuplée dans {$dossier}\n";
foreach ($nb as $table => $total) {
    echo "  - {$table} : {$total}\n";
}
echo "Comptes de démonstration : admin@minishop.fr / Admin2026! (SUPER),\n" .
    "  alice@example.com / Demo2026! (client) — cf. Seed.php.\n";
exit(0);
