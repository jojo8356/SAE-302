<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Fiche produit : valeurs du tableau
 * Caractéristiques cachées » (n° 11, UC-03, EF-VIS-04).
 *
 * Signalement : le tableau « Caractéristiques » de la fiche produit
 * n'affichait que les libellés (Prix HT, TVA, Prix TTC,
 * Disponibilité) ; les valeurs étaient repoussées à droite, hors
 * écran, derrière une barre de défilement — alors que pour deux
 * colonnes, tout doit tenir.
 *
 * Correctif verrouillé ici : la fiche présente un vrai tableau à
 * deux colonnes (libellé / valeur) pour ces quatre caractéristiques,
 * et la feuille du site le dimensionne pour tenir en entier à toute
 * largeur : largeur 100 %, répartition fixe des colonnes
 * (table-layout: fixed), valeurs longues repliées (overflow-wrap) —
 * aucun défilement horizontal. Les informations restent celles du
 * produit (prix calculés par le moteur, RB-16 ; disponibilité et
 * état de stock en temps réel).
 *
 * Usage :  php tests/php/fiche_produit_test.php
 *         node scripts/wasm_run.mjs tests/php/fiche_produit_test.php   (sandbox)
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

/** Rend la fiche produit comme CatalogueController::produit. */
function rendreFiche(array $produit, string $etat_stock): string
{
    $categorie = ['id_categorie' => 2, 'nom' => 'Audio'];

    ob_start();
    include dirname(__DIR__, 2) . '/app/View/catalogue/produit.php';

    return (string) ob_get_clean();
}

/** Extrait le tableau Caractéristiques du HTML rendu (null si absent). */
function tableauCaracteristiques(string $html): ?string
{
    if (preg_match('/<table class="tableau-caracteristiques">.*?<\/table>/s', $html, $m) === 1) {
        return $m[0];
    }

    return null;
}

/** Valeur (texte) de la ligne d'un libellé donné (null si ligne absente). */
function valeurLigne(?string $tableau, string $libelle): ?string
{
    if ($tableau === null) {
        return null;
    }
    $motif = '/<th scope="row">' . preg_quote($libelle, '/') . '<\/th>\s*<td>(.*?)<\/td>/s';
    if (preg_match($motif, $tableau, $m) === 1) {
        return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
    }

    return null;
}

$produit = [
    'id_produit' => 5,
    'nom' => 'Casque audio sans fil Aura',
    'reference' => 'CASQ-005',
    'prix_ht' => 129.00,
    'tva' => 20.00,
    'prix_ttc' => 154.80,
    'stock' => 16,
    'description' => 'Casque de démonstration, réduction de bruit.',
];

echo "MiniShop — Fiche produit : tableau Caractéristiques complet et tenu en page\n\n";

/* ── 1. Le tableau : quatre libellés, quatre valeurs ──────────────── */
echo "== 1 · Les quatre caractéristiques affichent leur valeur, côte à côte ==\n";
$html = rendreFiche($produit, 'DISPONIBLE');
$tableau = tableauCaracteristiques($html);
$check('le titre « Caractéristiques » est présent', str_contains($html, '<h2>Caractéristiques</h2>'));
$check('le tableau à deux colonnes existe', $tableau !== null);
$nbLignes = 'absent';
if ($tableau !== null) {
    $nbLignes = (string) substr_count($tableau, '<tr>');
}
$check('exactement 4 lignes (une par caractéristique)', $tableau !== null && substr_count($tableau, '<tr>') === 4,
    $nbLignes);
$check('exactement 2 cellules par ligne (libellé + valeur)',
    $tableau !== null && substr_count($tableau, '<th scope="row">') === 4 && substr_count($tableau, '<td>') === 4);
$check('Prix HT → 129,00 €', valeurLigne($tableau, 'Prix HT') === '129,00 €', (string) valeurLigne($tableau, 'Prix HT'));
$check('TVA → 20,00 %', valeurLigne($tableau, 'TVA') === '20,00 %', (string) valeurLigne($tableau, 'TVA'));
$check('Prix TTC → 154,80 €', valeurLigne($tableau, 'Prix TTC') === '154,80 €', (string) valeurLigne($tableau, 'Prix TTC'));
$dispo = valeurLigne($tableau, 'Disponibilité');
$check(
    'Disponibilité → en stock, quantité et état',
    $dispo !== null && str_contains($dispo, 'En stock') && str_contains($dispo, '16') && str_contains($dispo, 'Disponible'),
    (string) $dispo
);
$check(
    'aucune valeur vide ou « — » parmi les quatre lignes',
    valeurLigne($tableau, 'Prix HT') !== null && valeurLigne($tableau, 'Prix HT') !== ''
        && valeurLigne($tableau, 'TVA') !== null && valeurLigne($tableau, 'TVA') !== ''
        && valeurLigne($tableau, 'Prix TTC') !== null && valeurLigne($tableau, 'Prix TTC') !== ''
        && $dispo !== null && $dispo !== ''
);

/* ── 2. Emplacement et parcours préservés ─────────────────────────── */
echo "\n== 2 · La fiche reste complète : description, ajout panier, retour ==\n";
$posTableau = strpos($html, 'tableau-caracteristiques');
$posDescription = strpos($html, '<h2>Description</h2>');
$posAjout = strpos($html, 'action="/panier/ajouter"');
$check('le tableau est avant la description et l\'ajout au panier',
    $posTableau !== false && $posDescription !== false && $posAjout !== false
        && $posTableau < $posDescription && $posDescription < $posAjout);
$check('le formulaire d\'ajout est toujours là (avec CSRF et plafond de stock)',
    str_contains($html, 'name="_csrf"') && str_contains($html, 'max="16"'));
$check('le retour au catalogue est toujours là', str_contains($html, '<a href="/catalogue">Catalogue</a>'));
$check('les anciennes sections « Prix »/« Disponibilité » en prose ont laissé place au tableau',
    !str_contains($html, '<h2>Prix</h2>') && !str_contains($html, '<h2>Disponibilité</h2>'));

/* ── 3. Rupture de stock : la valeur dit l'indisponibilité ────────── */
echo "\n== 3 · Produit en rupture : disponibilité explicite, pas d'ajout possible ==\n";
$produitRupture = $produit;
$produitRupture['stock'] = 0;
$htmlRupture = rendreFiche($produitRupture, 'RUPTURE');
$tableauRupture = tableauCaracteristiques($htmlRupture);
$dispoRupture = valeurLigne($tableauRupture, 'Disponibilité');
$check(
    'Disponibilité → « Rupture de stock »',
    $dispoRupture !== null && str_contains($dispoRupture, 'Rupture de stock'),
    (string) $dispoRupture
);
$check('prix toujours affichés en rupture', valeurLigne($tableauRupture, 'Prix TTC') === '154,80 €');
$check('aucun formulaire d\'ajout en rupture', !str_contains($htmlRupture, 'action="/panier/ajouter"'));

/* ── 4. La feuille garantit que tout tient (pas de défilement) ────── */
echo "\n== 4 · CSS : deux colonnes qui tiennent en entier, à toute largeur ==\n";
$css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/css/app.css');
$bloc = null;
if (preg_match('/\.tableau-caracteristiques\s*\{([^}]*)\}/', $css, $m) === 1) {
    $bloc = $m[1];
}
$check('la règle .tableau-caracteristiques existe dans l\'unique feuille', $bloc !== null);
$check('largeur 100 % du tableau', $bloc !== null && str_contains($bloc, 'width: 100%'));
$check(
    'colonnes à répartition fixe (table-layout: fixed) : pas de débordement',
    $bloc !== null && str_contains($bloc, 'table-layout: fixed')
);
$check(
    'les valeurs longues se replient (overflow-wrap)',
    str_contains($css, '.tableau-caracteristiques td') && str_contains($css, 'overflow-wrap: break-word')
);
$check(
    'aucun défilement horizontal programmé sur ce tableau (pas d\'overflow-x)',
    $bloc !== null && !str_contains($bloc, 'overflow-x')
);
$check(
    'la colonne des libellés est dimensionnée (40 %)',
    preg_match('/\.tableau-caracteristiques th\s*\{[^}]*width: 40%/', $css) === 1
);
$gabarit = (string) file_get_contents(dirname(__DIR__, 2) . '/app/View/layout.php');
$check(
    'la feuille est bien chargée par le gabarit (fiche incluse)',
    str_contains($gabarit, '<link rel="stylesheet" href="/css/app.css">')
);

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Fiche produit (Caractéristiques) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
