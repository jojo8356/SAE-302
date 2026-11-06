<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Produit/Référence alignées à droite sous
 * des titres à gauche » (EF-VIS-03, EF-ADM-04/05).
 *
 * Signalement : sur trois écrans — le panier, Admin > Stocks et les
 * alertes du tableau de bord — les colonnes Produit et Référence
 * (des libellés) apparaissaient alignées à droite, sous des titres
 * alignés à gauche : les valeurs ne tombaient pas sous leurs
 * en-têtes. Cause : alignements jamais explicités écran par écran
 * (par défaut navigateur pour les stocks et les alertes).
 *
 * Correctif verrouillé ici : dans l'unique feuille du site, les
 * colonnes Produit/Référence portent des classes dédiées
 * (.produit, .reference) alignées à gauche, en-têtes et cellules
 * ensemble, sur les trois écrans (.tableau-panier, .tableau-stocks,
 * .tableau-alertes). Au panier, seuls les montants restent alignés
 * à droite — c'est la règle lue partout : libellés à gauche,
 * montants à droite.
 *
 * Les trois vues sont rendues pour de vrai (fixtures), comme pour
 * les régressions panier, commandes et catégories.
 *
 * Usage :  php tests/php/alignement_colonnes_test.php
 *         node scripts/wasm_run.mjs tests/php/alignement_colonnes_test.php   (sandbox)
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

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

/** Rend la vue panier avec un article, retourne le HTML. */
function rendrePanier(): string
{
    $recap = [
        'articles' => [[
            'id_produit' => 5,
            'slug' => 'casque-audio-sans-fil-aura',
            'nom' => 'Casque audio sans fil Aura',
            'reference' => 'CASQ-005',
            'stock_disponible' => 16,
            'prix_unitaire' => 154.80,
            'quantite' => 1,
            'total_ligne' => 154.80,
            'plafonne' => false,
        ]],
        'sous_total' => 154.80,
        'frais_port' => 4.90,
        'total' => 159.70,
        'port_motif' => 'offerts dès 80,00 € de marchandises',
    ];
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/panier/index.php';

    return (string) ob_get_clean();
}

/** Rend la vue Admin > Stocks avec deux états, retourne le HTML. */
function rendreStocks(): string
{
    $etats = [
        ['id_produit' => 5, 'reference' => 'CASQ-005', 'nom' => 'Casque audio sans fil Aura', 'stock' => 16, 'seuil_alerte' => 5, 'etat' => 'DISPONIBLE'],
        ['id_produit' => 6, 'reference' => 'ENCH-006', 'nom' => 'Enceinte connectée Halo', 'stock' => 1, 'seuil_alerte' => 3, 'etat' => 'TRES_BAS'],
    ];
    $valeur_inventaire = 2451.60;
    $erreurs = [];
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/admin/stocks.php';

    return (string) ob_get_clean();
}

/** Rend le tableau de bord admin avec une alerte, retourne le HTML. */
function rendreDashboard(): string
{
    $indicateurs = ['commandes' => 12, 'en_preparation' => 3, 'clients' => 3, 'produits' => 12, 'ca_total' => 1540.50];
    $rapport = [
        'par_statut' => [
            ['statut' => 'PAYEE', 'nb_commandes' => 8, 'montant_total' => 1200.00, 'montant_moyen' => 150.00],
        ],
        'top_produits' => [
            ['nom' => 'Casque audio sans fil Aura', 'reference' => 'CASQ-005', 'unites' => 9, 'ca' => 1393.20],
        ],
    ];
    $alertes = [
        ['reference' => 'ENCH-006', 'nom' => 'Enceinte connectée Halo', 'stock' => 1, 'seuil_alerte' => 3, 'etat' => 'TRES_BAS'],
    ];
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/admin/dashboard.php';

    return (string) ob_get_clean();
}

/** Extrait le bloc <table class="$classe">…</table> du HTML (null si absent). */
function extraireTableau(string $html, string $classe): ?string
{
    if (preg_match('/<table class="' . $classe . '">.*?<\/table>/s', $html, $m) === 1) {
        return $m[0];
    }

    return null;
}

/** Bloc de règles CSS d'un sélecteur donné (null si absent). */
function blocCss(string $css, string $selecteur): ?string
{
    $motif = '/' . preg_quote($selecteur, '/') . '\s*\{([^}]*)\}/s';
    if (preg_match($motif, $css, $m) === 1) {
        return $m[1];
    }

    return null;
}

echo "MiniShop — Alignement Produit/Référence : panier, stocks, alertes\n\n";

/* ── 1. Panier ─────────────────────────────────────────────────────── */
echo "== 1 · Panier : la colonne Produit à gauche, les montants à droite ==\n";
$panier = extraireTableau(rendrePanier(), 'tableau-panier');
$check('panier : le tableau est rendu', $panier !== null);
$check(
    'panier : l\'en-tête Produit porte la classe produit (à gauche)',
    $panier !== null && str_contains($panier, '<th scope="col" class="produit">Produit</th>')
);
$check(
    'panier : la cellule Produit (nom + référence CASQ-005) porte la classe produit',
    $panier !== null && str_contains($panier, '<td class="produit">') && str_contains($panier, 'Réf. CASQ-005')
);
$check(
    'panier : les montants gardent leur classe montant (la règle ne change pas pour eux)',
    $panier !== null && str_contains($panier, '<td class="montant">154,80 €</td>')
);

/* ── 2. Admin > Stocks ─────────────────────────────────────────────── */
echo "\n== 2 · Admin > Stocks : Réf. et Produit à gauche, sous leurs titres ==\n";
$stocks = extraireTableau(rendreStocks(), 'tableau-stocks');
$check('stocks : le tableau porte la classe tableau-stocks', $stocks !== null);
$check(
    'stocks : les en-têtes Réf. et Produit portent leurs classes d\'alignement',
    $stocks !== null
        && str_contains($stocks, '<th scope="col" class="reference">Réf.</th>')
        && str_contains($stocks, '<th scope="col" class="produit">Produit</th>')
);
$check(
    'stocks : chaque ligne a sa référence et son produit en cellules alignées comme les titres',
    $stocks !== null
        && str_contains($stocks, '<td class="reference">CASQ-005</td>')
        && str_contains($stocks, '<td class="produit">Casque audio sans fil Aura</td>')
        && str_contains($stocks, '<td class="reference">ENCH-006</td>')
);
$check(
    'stocks : non-régression — l\'ajustement reste là, mode « Ajouter / retirer » présélectionné',
    $stocks !== null
        && str_contains($stocks, '<option value="DELTA" selected>Ajouter / retirer</option>')
        && substr_count($stocks, '>Appliquer</button>') === 2
);

/* ── 3. Tableau de bord : alertes ──────────────────────────────────── */
echo "\n== 3 · Tableau de bord, alertes : Référence et Produit à gauche ==\n";
$dashboard = rendreDashboard();
$alertes = extraireTableau($dashboard, 'tableau-alertes');
$check('alertes : le tableau porte la classe tableau-alertes', $alertes !== null);
$check(
    'alertes : les en-têtes Référence et Produit portent leurs classes d\'alignement',
    $alertes !== null
        && str_contains($alertes, '<th scope="col" class="reference">Référence</th>')
        && str_contains($alertes, '<th scope="col" class="produit">Produit</th>')
);
$check(
    'alertes : la ligne d\'alerte a sa référence et son produit en cellules alignées comme les titres',
    $alertes !== null
        && str_contains($alertes, '<td class="reference">ENCH-006</td>')
        && str_contains($alertes, '<td class="produit">Enceinte connectée Halo</td>')
);
$check(
    'alertes : non-régression — le renvoi « Gérer les stocks → » reste sous le tableau',
    str_contains($dashboard, '<a href="/admin/stocks">Gérer les stocks →</a>')
);

/* ── 4. CSS : la règle écrite, pas le défaut du navigateur ─────────── */
echo "\n== 4 · Feuille du site : libellés à gauche explicites sur les trois écrans ==\n";
$css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/minishop.css');
$blocPanierProduit = blocCss($css, '.tableau-panier .produit');
$check(
    'panier : .tableau-panier .produit est aligné à gauche',
    $blocPanierProduit !== null && str_contains($blocPanierProduit, 'text-align: left'),
    (string) $blocPanierProduit
);
$blocCellules = null;
if (preg_match('/\.tableau-stocks th,\s*\.tableau-stocks td,\s*\.tableau-alertes th,\s*\.tableau-alertes td\s*\{([^}]*)\}/s', $css, $m) === 1) {
    $blocCellules = $m[1];
}
$check(
    'stocks et alertes : en-têtes et cellules partagent l\'alignement à gauche',
    $blocCellules !== null && str_contains($blocCellules, 'text-align: left'),
    (string) $blocCellules
);
$blocLibelles = null;
if (preg_match('/\.tableau-stocks \.reference,\s*\.tableau-stocks \.produit,\s*\.tableau-alertes \.reference,\s*\.tableau-alertes \.produit\s*\{([^}]*)\}/s', $css, $m) === 1) {
    $blocLibelles = $m[1];
}
$check(
    'stocks et alertes : .reference et .produit sont explicitement à gauche',
    $blocLibelles !== null && str_contains($blocLibelles, 'text-align: left'),
    (string) $blocLibelles
);
$blocMontant = blocCss($css, '.tableau-panier .montant');
$check(
    'cohérence d\'ensemble : au panier, seuls les montants sont à droite',
    $blocMontant !== null && str_contains($blocMontant, 'text-align: right')
);

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Alignement colonnes (panier, stocks, alertes) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
