<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Catalogue : recherche et filtres en bas de
 * page » (UC-02/03, EF-VIS-02/03).
 *
 * Signalement : la recherche et les filtres se retrouvaient après les
 * 12 produits et la pagination — sur mobile, il fallait faire défiler
 * toute la liste avant de pouvoir filtrer (ou, formulaire déplié en
 * permanence, les produits étaient repoussés hors du premier écran).
 *
 * Correctif verrouillé ici : le bloc recherche/filtres est EN HAUT de
 * la page (avant le premier produit et la pagination), replié
 * derrière un bouton « Filtrer » — élément <details>/<summary> natif,
 * sans JavaScript (CSP sans script) — fermé par défaut, et OUVERT
 * automatiquement dès qu'un filtre est actif, pour que l'état courant
 * reste visible. Les critères restent conservés dans l'URL (EF-VIS-03)
 * et les champs restent pré-remplis.
 *
 * Usage :  php tests/php/catalogue_filtres_test.php
 *         node scripts/wasm_run.mjs tests/php/catalogue_filtres_test.php   (sandbox)
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

/** Rend le catalogue comme CatalogueController (critères paramétrables). */
function rendreCatalogue(array $criteres, bool $est_recherche = false): string
{
    $resultat = [
        'lignes' => [[
            'slug' => 'casque-audio', 'nom' => 'Casque audio', 'prix_ttc' => 154.80,
            'categorie' => 'Audio', 'reference' => 'CASQ-005', 'stock' => 5,
            'etat_stock' => 'DISPONIBLE', 'description' => 'Un casque de démonstration.',
        ]],
        'total' => 13, 'page' => 1, 'pages' => 2,
    ];
    $categories = [['id_categorie' => 2, 'nom' => 'Audio']];
    $tris = ['nom' => 'Nom (A→Z)', 'prix_asc' => 'Prix croissant'];
    $parametres_url = [];

    ob_start();
    include dirname(__DIR__, 2) . '/app/View/catalogue/index.php';

    return (string) ob_get_clean();
}

/** Critères « aucun filtre » tels que le contrôleur les fournit. */
function criteresVides(): array
{
    return [
        'mot_cle' => '', 'id_categorie' => null, 'prix_min' => null,
        'prix_max' => null, 'en_stock' => false, 'tri' => '',
    ];
}

/** Extrait la balise <details …> du panneau de filtres (null si absente). */
function baliseDetails(string $html): ?string
{
    if (preg_match('/<details class="filtres-catalogue"[^>]*>/', $html, $m) === 1) {
        return $m[0];
    }

    return null;
}

echo "MiniShop — Catalogue : recherche et filtres en haut, derrière « Filtrer »\n\n";

/* ── 1. Sans filtre : panneau en haut, fermé, produits visibles ───── */
echo "== 1 · Aucun filtre : « Filtrer » en haut, fermé, produits juste après ==\n";
$html = rendreCatalogue(criteresVides());
$posDetails = strpos($html, '<details class="filtres-catalogue"');
$posForm = strpos($html, '<form method="get"');
$posProduits = strpos($html, 'class="grille grille--produits"');
$posPagination = strpos($html, 'Pagination');
$check('le panneau de filtres existe', $posDetails !== false);
$check('le panneau est AVANT le formulaire qu\'il contient', $posDetails !== false && $posForm !== false && $posDetails < $posForm);
$check(
    'le formulaire est AVANT la liste des produits',
    $posForm !== false && $posProduits !== false && $posForm < $posProduits,
    "form={$posForm} produits={$posProduits}"
);
$check(
    'la liste des produits est AVANT la pagination',
    $posProduits !== false && $posPagination !== false && $posProduits < $posPagination
);
$check(
    'sans filtre, le panneau est FERMÉ (produits visibles d\'emblée, mobile)',
    baliseDetails($html) !== null && !str_contains((string) baliseDetails($html), ' open'),
    (string) baliseDetails($html)
);
$check('le bouton du panneau s\'appelle « Filtrer »', str_contains($html, '<summary>Filtrer</summary>'));
$check(
    'le panneau contient la recherche et les filtres habituels',
    str_contains($html, 'id="q"') && str_contains($html, 'id="cat"')
        && str_contains($html, 'id="prix_min"') && str_contains($html, 'id="tri"')
        && str_contains($html, '>Appliquer les filtres</button>')
);
$check('le formulaire part toujours en GET sur /catalogue', str_contains($html, '<form method="get" action="/catalogue"'));

/* ── 2. Avec filtre : panneau ouvert, état visible, champs remplis ── */
echo "\n== 2 · Filtre actif : le panneau s'ouvre, les critères restent affichés ==\n";
$criteres = criteresVides();
$criteres['mot_cle'] = 'casque';
$htmlActif = rendreCatalogue($criteres);
$check(
    'recherche « casque » → panneau OUVERT',
    baliseDetails($htmlActif) !== null && str_contains((string) baliseDetails($htmlActif), ' open'),
    (string) baliseDetails($htmlActif)
);
$check('le bouton signale « Filtrer (actifs) »', str_contains($htmlActif, '<summary>Filtrer (actifs)</summary>'));
$check('le mot-clé reste dans le champ', str_contains($htmlActif, 'value="casque"'));

$criteresCat = criteresVides();
$criteresCat['id_categorie'] = 2;
$check(
    'catégorie choisie → panneau ouvert aussi',
    str_contains((string) baliseDetails(rendreCatalogue($criteresCat)), ' open')
);

$criteresTriDefaut = criteresVides();
$criteresTriDefaut['tri'] = 'nom';
$check(
    'tri par défaut (Nom) → panneau fermé (ce n\'est pas un filtre)',
    !str_contains((string) baliseDetails(rendreCatalogue($criteresTriDefaut)), ' open')
);
$criteresTri = criteresVides();
$criteresTri['tri'] = 'prix_asc';
$check(
    'tri explicite → panneau ouvert',
    str_contains((string) baliseDetails(rendreCatalogue($criteresTri)), ' open')
);

/* ── 3. Recherche (page /recherche) : même comportement ───────────── */
echo "\n== 3 · Page Recherche : même panneau, formulaire vers /recherche ==\n";
$htmlRecherche = rendreCatalogue(criteresVides(), true);
$check('le formulaire de /recherche vise /recherche', str_contains($htmlRecherche, '<form method="get" action="/recherche"'));
$check('panneau fermé par défaut aussi en recherche', !str_contains((string) baliseDetails($htmlRecherche), ' open'));
$check('titre « Recherche » conservé', str_contains($htmlRecherche, '<h1>Recherche</h1>'));

/* ── 4. Aucun JavaScript, feuille mobile ──────────────────────────── */
echo "\n== 4 · Sans JavaScript, pensée mobile ==\n";
$check(
    'aucun <script>, aucun onclick : le panneau est natif',
    !str_contains($html, '<script') && !str_contains($html, 'onclick')
);
$css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/css/app.css');
$check(
    'le bouton « Filtrer » a une zone tactile dédiée (feuille du site)',
    str_contains($css, '.filtres-catalogue > summary') && str_contains($css, 'cursor: pointer')
);
$gabarit = (string) file_get_contents(dirname(__DIR__, 2) . '/app/View/layout.php');
$check(
    'le gabarit déclare le viewport mobile',
    str_contains($gabarit, 'name="viewport"') && str_contains($gabarit, 'width=device-width')
);

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Catalogue (filtres en haut) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
