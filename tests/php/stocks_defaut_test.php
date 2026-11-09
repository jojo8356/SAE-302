<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Admin > Stocks : le mode par défaut écrase le
 * stock » (UC-12, EF-ADM-04).
 *
 * Signalement : le mode par défaut était « Fixer à » (SET) avec la
 * valeur 0. En voulant AJOUTER 3 unités à un stock de 5, l'admin a
 * cliqué « Appliquer » sans changer le mode : le stock est passé à 3
 * au lieu de 8 — l'intention « +3 » a été lue comme une valeur absolue.
 *
 * Correctif verrouillé ici, suivant la proposition du signalement :
 *   - le formulaire propose « Ajouter / retirer » (DELTA), renommé
 *     depuis « Varier de », EN PREMIER et PRÉ-SÉLECTIONNÉ ;
 *   - « Fixer à » (SET) reste disponible, mais seulement en choix
 *     explicite ;
 *   - le contrôleur applique le même défaut sûr : un mode absent vaut
 *     DELTA, jamais SET en silence — un oubli ne peut plus écraser un
 *     stock par une valeur absolue.
 * Le repository (adjustStock SET/DELTA, RB-03, motif) ne change pas.
 *
 * Usage :  php tests/php/stocks_defaut_test.php
 *         node scripts/wasm_run.mjs tests/php/stocks_defaut_test.php   (sandbox)
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Model\Data\Filter;
use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use App\Model\Data\StoreInterface;
use App\Repository\ProduitRepository;

ob_start();

$echecs = 0;
$tests = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$echecs, &$tests): void {
    ++$tests;
    $icone = '  ❌ ';
    if ($ok) {
        $icone = '  ✅ ';
    }
    echo $icone, $label;
    if (!$ok && $detail !== '') {
        echo " — ", $detail;
    }
    echo "\n";
    if (!$ok) {
        ++$echecs;
    }
};

function magasin(): StoreInterface
{
    static $n = 0;
    $dossier = sys_get_temp_dir() . '/minishop_stocks' . getmypid() . '_' . (++$n);
    if (is_dir($dossier)) {
        $anciens = glob($dossier . '/*.json');
        if (is_array($anciens)) {
            array_map('unlink', $anciens);
        }
        @rmdir($dossier);
    }
    $store = JsonStore::open($dossier);
    Seed::force($store);
    $store->setActor(['id' => 1, 'role' => 'ADMIN', 'nom' => 'Harnais']);

    return $store;
}

function pid(StoreInterface $store, string $reference): int
{
    return (int) $store->findOne('produit', [Filter::eq('reference', $reference)])['id_produit'];
}

/** Valeur de l'option sélectionnée du select « mode » rendu (null si aucune). */
function modeSelectionne(string $html): ?string
{
    if (preg_match('/<select\b[^>]*\bname="mode"[^>]*>(.*?)<\/select>/s', $html, $m) !== 1) {
        return null;
    }
    if (preg_match('/<option value="([A-Z]+)" selected>/', $m[1], $o) === 1) {
        return $o[1];
    }

    return null;
}

/** Libellé de l'option d'une valeur donnée dans le select « mode » (null si absente). */
function libelleOption(string $html, string $valeur): ?string
{
    if (preg_match('/<option value="' . $valeur . '"[^>]*>([^<]+)<\/option>/', $html, $m) === 1) {
        return trim($m[1]);
    }

    return null;
}

echo "MiniShop — Admin > Stocks : « Ajouter / retirer » par défaut\n\n";

/* ── 1. Le formulaire rendu : défaut sûr, libellés clairs ─────────── */
echo "== 1 · Vue admin/stocks : le mode par défaut ne peut plus écraser un stock ==\n";
$etats = [[
    'id_produit' => 5, 'reference' => 'CASQ-005', 'nom' => 'Casque audio',
    'stock' => 5, 'seuil_alerte' => 3, 'etat' => 'DISPONIBLE',
]];
$valeur_inventaire = 645.0;
$erreurs = [];
ob_start();
include dirname(__DIR__, 2) . '/app/View/admin/stocks.php';
$html = (string) ob_get_clean();

$check('le select « mode » est rendu', preg_match('/<select\b[^>]*\bname="mode"[^>]*>/', $html) === 1);
$check(
    'le mode sélectionné par défaut est DELTA',
    modeSelectionne($html) === 'DELTA',
    (string) modeSelectionne($html)
);
$check(
    'DELTA s\'appelle « Ajouter / retirer »',
    libelleOption($html, 'DELTA') === 'Ajouter / retirer',
    (string) libelleOption($html, 'DELTA')
);
$check(
    '« Fixer à » (SET) reste proposé, sans être sélectionné',
    libelleOption($html, 'SET') === 'Fixer à'
        && !str_contains($html, '<option value="SET" selected>')
);
$check('l\'ancien libellé ambigu « Varier de » a disparu', !str_contains($html, 'Varier de'));
$check(
    '« Ajouter / retirer » est la première option du select',
    strpos($html, 'value="DELTA"') < strpos($html, 'value="SET"')
);

/* ── 2. Le scénario signalé, au niveau métier ─────────────────────── */
echo "\n== 2 · Scénario signalé : stock 5, « +3 » sans toucher au mode → 8 ==\n";
$store = magasin();
$repo = new ProduitRepository($store);
$id = pid($store, 'CASQ-005');
$check('préparation : « Fixer à » explicite → stock 5', $repo->adjustStock($id, 'SET', 5, 'inventaire initial') === 5);
$check(
    'mode par défaut (DELTA) +3 → 8, et non 3',
    $repo->adjustStock($id, 'DELTA', 3, 'réception fournisseur') === 8,
    (string) $store->find('produit', $id)['stock']
);
$check('le stock persisté est bien 8', (int) $store->find('produit', $id)['stock'] === 8);
$check(
    '« Ajouter / retirer » sait aussi retirer : −3 → 5',
    $repo->adjustStock($id, 'DELTA', -3, 'sortie atelier') === 5
);
$check(
    '« Fixer à » reste possible en choix explicite : SET 3 → 3',
    $repo->adjustStock($id, 'SET', 3, 'inventaire') === 3
);

/* ── 3. Contrôleur : le même défaut sûr côté serveur ──────────────── */
echo "\n== 3 · Branchement : le contrôleur ne retombe jamais sur SET en silence ==\n";
$racine = dirname(__DIR__, 2);
$controleur = (string) file_get_contents($racine . '/app/Controller/Admin/StockAdminController.php');
$vue = (string) file_get_contents($racine . '/app/View/admin/stocks.php');
$modeParDefaut = null;
if (preg_match('/\$mode = \'([A-Z]+)\';/', $controleur, $m) === 1) {
    $modeParDefaut = $m[1];
}
$check(
    'le contrôleur initialise le mode à DELTA (première affectation)',
    $modeParDefaut === 'DELTA',
    (string) $modeParDefaut
);
$check(
    'SET n\'est retenu que s\'il est explicitement demandé',
    str_contains($controleur, "strtoupper(\$this->str('mode', 5)) === 'SET'")
);
$check(
    'la vue pré-sélectionne DELTA (« Ajouter / retirer »)',
    str_contains($vue, '<option value="DELTA" selected>Ajouter / retirer</option>')
);
$check(
    'le repository conserve ses deux modes explicites (SET absolu / DELTA ±)',
    str_contains((string) file_get_contents($racine . '/app/Repository/ProduitRepository.php'), "if (\$mode === 'DELTA')")
);

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Stocks (mode par défaut) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
