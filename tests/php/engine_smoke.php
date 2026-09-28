<?php

declare(strict_types=1);

/**
 * MiniShop — fumée du moteur JSON (exécutable en PHP CLI ou via PHP-WASM).
 *
 * Vérifie les briques critiques du pilote StorageDriver::JSON avant toute
 * construction au-dessus : schéma, seed, lecture/filtres/tris, écritures,
 * contraintes (UNIQUE/CHECK/FK/ENUM), les déclencheurs majeurs (RB-03/05/06/
 * 11/15/18), les transactions et le journal.
 *
 * Usage : php tests/php/engine_smoke.php   (exit 0 = tout est vert)
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Model\Data\BusinessError;
use App\Model\Data\ConstraintError;
use App\Model\Data\Filter;
use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use App\Model\Data\StorageDriver;
use App\Model\Data\Views;

$dataDir = sys_get_temp_dir() . '/minishop_smoke_' . getmypid();
if (is_dir($dataDir)) {
    array_map('unlink', glob($dataDir . '/*.json') ?: []);
    @rmdir($dataDir);
}

$failures = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$failures): void {
    echo ($ok ? '  ✅ ' : '  ❌ ') . $label . ($detail !== '' ? " — {$detail}" : '') . "\n";
    if (!$ok) {
        ++$failures;
    }
};
$expectBusiness = static function (string $code, callable $work) use (&$failures): void {
    try {
        $work();
        echo "  ❌ attendu {$code} — aucune erreur levée\n";
        ++$failures;
    } catch (BusinessError $e) {
        echo ($e->businessCode === $code ? '  ✅ ' : '  ❌ ') . 'refus ' . $e->businessCode . ($e->businessCode === $code ? '' : " (attendu {$code})") . "\n";
        if ($e->businessCode !== $code) {
            ++$failures;
        }
    } catch (\Throwable $e) {
        echo "  ❌ attendu {$code} — reçu " . get_class($e) . ' : ' . $e->getMessage() . "\n";
        ++$failures;
    }
};

echo "== 1. pilote & énumération ==\n";
$driver = StorageDriver::fromConfig('json');
$check('StorageDriver::JSON actif par défaut', $driver === StorageDriver::JSON);
$store = JsonStore::open($dataDir); // bac à sable isolé pour ce test
$check('le pilote JSON est un JsonStore', $store instanceof JsonStore);
$check('SqlStore refuse poliment (migration future)', (static function (): bool {
    try {
        \App\Model\Data\StorageDriver::SQL->createStore()->select('produit');

        return false;
    } catch (\RuntimeException $e) {
        return str_contains($e->getMessage(), 'StorageDriver::JSON');
    }
})());

echo "\n== 2. seed & lecture ==\n";
$check('base installée par le seed', Seed::ensure($store) === true);
$check('seed idempotent', Seed::ensure($store) === false);
$check('12 produits', $store->count('produit') === 12);
$check('11 produits visibles (RB-19)', $store->count('produit', [['visible', '=', 1]]) === 11);
$check('find par PK (hash)', ($store->find('produit', 1) ?? [])['reference'] === 'PC-PORT-001');
$check('LIKE insensible casse/accents (recherche "ecran")', $store->count('produit', [Filter::like('nom', '%ecran%')]) === 1);
$check('BETWEEN prix_ttc', $store->count('produit', [Filter::between('prix_ttc', 50, 200)]) === 7);
$check('tri multi-clés + limite', count($store->select('produit', ['order' => [Filter::sort('prix_ttc', 'desc')], 'limit' => 3])) === 3);
$check('paramètre frais_port = 4.90', ($store->find('parametre', 'frais_port') ?? [])['valeur'] === '4.90');

echo "\n== 3. vues ==\n";
$views = new Views($store);
$catalogue = $views->catalogue();
$check('v_catalogue : 11 lignes, catégorie jointe', count($catalogue) === 11 && $catalogue[0]['categorie'] === 'Informatique');
$check('v_catalogue : état RUPTURE pour la batterie', in_array('RUPTURE', array_column($catalogue, 'etat'), true));
$check('v_etat_stock : 12 lignes', count($views->etatStocks()) === 12);

echo "\n== 4. écritures & contraintes ==\n";
$id = $store->insert('client', [
    'nom' => 'Test', 'prenom' => 'Zoe', 'email' => 'zoe@example.com',
    'mot_de_passe_hash' => password_hash('Zoe2026!', PASSWORD_BCRYPT),
]);
$check('insert client -> id 4', $id === 4);

try {
    $store->insert('client', ['nom' => 'X', 'prenom' => 'Y', 'email' => 'zoe@example.com', 'mot_de_passe_hash' => str_repeat('h', 60)]);
    $check('UNIQUE email (RB-01)', false);
} catch (ConstraintError $e) {
    $check('UNIQUE email (RB-01)', str_contains($e->getMessage(), 'uk_client_email'));
}
try {
    // écriture DIRECTE (pas de repository) : c'est la contrainte CHECK qui parle,
    // comme un INSERT à la main sur MySQL — le code RB02_* vient du Repository.
    $store->insert('produit', ['reference' => 'X-1', 'nom' => 'X', 'slug' => 'x', 'prix_ht' => 0, 'id_categorie' => 1, 'stock' => 1]);
    $check('CHECK ck_produit_prix (RB-02, écriture directe)', false);
} catch (ConstraintError $e) {
    $check('CHECK ck_produit_prix (RB-02, écriture directe)', str_contains($e->getMessage(), 'ck_produit_prix'));
}
$expectBusiness('CATEGORIE_NON_VIDE', static fn () => $store->delete('categorie', [['id_categorie', '=', 1]]));
try {
    $store->insert('produit', ['reference' => 'X-2', 'nom' => 'X', 'slug' => 'x2', 'prix_ht' => 10, 'id_categorie' => 999, 'stock' => 1]);
    $check('FK id_categorie (RB-07)', false);
} catch (ConstraintError $e) {
    $check('FK id_categorie (RB-07)', str_contains($e->getMessage(), 'categorie'));
}

echo "\n== 5. commande : prix figé, stock, montants (RB-05/06/15/18) ==\n";
$store->insert('commande', ['numero' => 'CMD2026-000001', 'id_client' => $id, 'adresse_livraison' => '1 rue Test']);
$stockAvant = (int) ($store->find('produit', 4) ?? [])['stock'];
$store->insert('ligne_commande', ['id_commande' => 1, 'id_produit' => 4, 'quantite' => 3, 'prix_unitaire' => 1, 'total_ligne' => 1]);
$ligne = $store->find('ligne_commande', 1);
$check('RB-06 : prix figé = prix_ttc du produit (94.80)', (float) $ligne['prix_unitaire'] === 94.80 && (float) $ligne['total_ligne'] === 284.40);
$check('RB-18 : stock décrémenté par le déclencheur', (int) ($store->find('produit', 4) ?? [])['stock'] === $stockAvant - 3);
try {
    $store->insert('ligne_commande', ['id_commande' => 1, 'id_produit' => 4, 'quantite' => 0]);
    $check('CHECK ck_ligne_quantite (RB-05, écriture directe)', false);
} catch (ConstraintError $e) {
    $check('CHECK ck_ligne_quantite (RB-05, écriture directe)', str_contains($e->getMessage(), 'ck_ligne_quantite'));
}
$expectBusiness('STOCK_INSUFFISANT', static fn () => $store->insert('ligne_commande', ['id_commande' => 1, 'id_produit' => 2, 'quantite' => 99]));
$store->update('commande', [['id_commande', '=', 1]], ['frais_port' => 4.90, 'montant_total' => 289.30]);
$check('RB-15 : montant = lignes + port', (float) ($store->find('commande', 1) ?? [])['montant_total'] === 289.30);
$expectBusiness('RB15_MONTANT_CALCULE_INTERDIT', static fn () => $store->update('commande', [['id_commande', '=', 1]], ['montant_total' => 1.0]));
$expectBusiness('RB06_PRIX_ET_QUANTITE_NON_MODIFIABLE', static fn () => $store->update('ligne_commande', [['id_ligne', '=', 1]], ['quantite' => 2]));

echo "\n== 6. machine à états & historique (RB-11) ==\n";
$check('historique initial tracé (BROUILLON)', $store->count('order_status_history', [['order_id', '=', 1]]) === 1);
$store->update('commande', [['id_commande', '=', 1]], ['statut' => 'PAYEE']);
$check('transition BROUILLON→PAYEE ok', ($store->find('commande', 1) ?? [])['statut'] === 'PAYEE');
$check('historique enrichi par trg_history_statut', $store->count('order_status_history', [['order_id', '=', 1]]) === 2);
$expectBusiness('RB11_TRANSITION_STATUT_INTERDITE', static fn () => $store->update('commande', [['id_commande', '=', 1]], ['statut' => 'EN_PREPARATION']));
$expectBusiness('RB11_TRANSITION_STATUT_INTERDITE', static fn () => $store->update('commande', [['id_commande', '=', 1]], ['statut' => 'BROUILLON']));

echo "\n== 7. annulation : stock rendu, port annulé (UC-09, RB-20) ==\n";
foreach ($store->select('ligne_commande', [['id_commande', '=', 1]]) as $ligneDel) {
    $store->delete('ligne_commande', [['id_ligne', '=', $ligneDel['id_ligne']]]);
}
$store->update('commande', [['id_commande', '=', 1]], ['statut' => 'ANNULEE', 'frais_port' => 0, 'montant_total' => 0]);
$commande = $store->find('commande', 1);
$check('statut ANNULEE, montant 0, port 0', $commande['statut'] === 'ANNULEE' && (float) $commande['montant_total'] === 0.0 && (float) $commande['frais_port'] === 0.0);
$check('stock intégralement restitué (A3)', (int) ($store->find('produit', 4) ?? [])['stock'] === $stockAvant);

echo "\n== 8. transactions (ENF-16) ==\n";
$store->beginTransaction();
$store->insert('client', ['nom' => 'Roll', 'prenom' => 'Back', 'email' => 'rollback@example.com', 'mot_de_passe_hash' => str_repeat('h', 60)]);
$check('ligne visible dans la transaction', $store->exists('client', [['email', '=', 'rollback@example.com']]));
$store->rollback();
$check('rollback : ligne disparue', !$store->exists('client', [['email', '=', 'rollback@example.com']]));

$store->transactional(static function (JsonStore $s): void {
    $s->insert('client', ['nom' => 'Commit', 'prenom' => 'Ok', 'email' => 'commit@example.com', 'mot_de_passe_hash' => str_repeat('h', 60)]);
});
$check('transactional : commit persisté', $store->exists('client', [['email', '=', 'commit@example.com']]));

echo "\n== 9. fichiers & journal ==\n";
$files = glob($store->dataPath() . '/*.json') ?: [];
$check('8 tables sur disque', count($files) === 8, count($files) . ' fichiers');
$journal = $store->journalPath(); // hermétique : journal du bac à sable
$check('journal d’audit alimenté (EF-GEN-04)', is_file($journal) && count(file($journal, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) > 0);

// nettoyage du bac à sable
foreach (glob($store->dataPath() . '/*.json') ?: [] as $f) {
    @unlink($f);
}
@unlink($journal);
@rmdir($store->dataPath());

echo $failures === 0 ? "\nMOTEUR JSON : TOUT EST VERT ✅\n" : "\n{$failures} ÉCHEC(S) ❌\n";
exit($failures === 0 ? 0 : 1);
