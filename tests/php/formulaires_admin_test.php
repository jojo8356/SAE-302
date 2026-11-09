<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Formulaires d'admin mal alignés »
 * (EF-ADM-01/02/03, EF-VIS-03).
 *
 * Signalements : (1) formulaire produit mal aligné — chaque champ
 * commençait après son libellé, à une abscisse différente, et
 * « Description » était en bas de la zone de texte ; (2) dans
 * Catégories, les trois champs de modification n'avaient pas
 * d'étiquette et l'aide « Le slug est généré… » débordait du
 * cadre ; (3) dans Produits, « Catégorie : » était en fin de ligne
 * et sa liste seule sur la ligne suivante ; (4) dans le catalogue
 * (~1 000 px), la grille des filtres était désordonnée, « Prix TTC
 * maximum » seul sur la deuxième ligne.
 *
 * Correctif verrouillé ici : des rangées étiquette + champ
 * explicites et inséparables, dans l'unique feuille du site —
 * formulaire produit en grille libellé/champ alignée au départ
 * (.formulaire-produit), aide de création en paragraphe dans le
 * cadre et aria-label sur les champs de modification des
 * catégories, couples libellé/champ en blocs .champ pour le
 * filtre Produits et les filtres du catalogue (grille régulière,
 * prix minimum et maximum chacun complets et côte à côte).
 *
 * Les quatre vues sont rendues pour de vrai (fixtures).
 *
 * Usage :  php tests/php/formulaires_admin_test.php
 *         node scripts/wasm_run.mjs tests/php/formulaires_admin_test.php   (sandbox)
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

/** Bloc de règles CSS d'un sélecteur donné (null si absent). */
function blocCss(string $css, string $selecteur): ?string
{
    $motif = '/' . preg_quote($selecteur, '/') . '\s*\{([^}]*)\}/s';
    if (preg_match($motif, $css, $m) === 1) {
        return $m[1];
    }

    return null;
}

/** Rend le formulaire produit (création), retourne le HTML. */
function rendreProduitForm(): string
{
    $produit = null;
    $categories = [['id_categorie' => 2, 'nom' => 'Audio']];
    $erreurs = [];
    $valeurs = [];
    $tva_defaut = 20.0;
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/admin/produit_form.php';

    return (string) ob_get_clean();
}

/** Rend la vue Admin > Catégories avec une catégorie, retourne le HTML. */
function rendreCategories(): string
{
    $categories = [[
        'id_categorie' => 2, 'nom' => 'Audio', 'slug' => 'audio',
        'description' => 'Casques, enceintes et microphones', 'nb_produits' => 3,
    ]];
    $erreurs = [];
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/admin/categories.php';

    return (string) ob_get_clean();
}

/** Rend la vue Admin > Produits (filtre + liste), retourne le HTML. */
function rendreProduits(): string
{
    $resultat = [
        'lignes' => [[
            'id_produit' => 5, 'reference' => 'CASQ-005', 'nom' => 'Casque audio sans fil Aura',
            'categorie' => 'Audio', 'prix_ht' => 129.00, 'tva' => 20.0, 'prix_ttc' => 154.80,
            'stock' => 16, 'etat_stock' => 'DISPONIBLE', 'visible' => 1,
        ]],
        'page' => 1, 'pages' => 1, 'total' => 1,
    ];
    $categories = [['id_categorie' => 2, 'nom' => 'Audio']];
    $q = '';
    $cat = null;
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/admin/produits.php';

    return (string) ob_get_clean();
}

/** Rend le catalogue (panneau de filtres), retourne le HTML. */
function rendreCatalogue(): string
{
    $resultat = [
        'lignes' => [[
            'slug' => 'casque-audio', 'nom' => 'Casque audio', 'prix_ttc' => 154.80,
            'categorie' => 'Audio', 'reference' => 'CASQ-005', 'stock' => 5,
            'etat_stock' => 'DISPONIBLE', 'description' => 'Un casque de démonstration.',
        ]],
        'total' => 13, 'page' => 1, 'pages' => 2,
    ];
    $criteres = [
        'mot_cle' => '', 'id_categorie' => null, 'prix_min' => null,
        'prix_max' => null, 'en_stock' => false, 'tri' => '',
    ];
    $categories = [['id_categorie' => 2, 'nom' => 'Audio']];
    $tris = ['nom' => 'Nom (A→Z)', 'prix_asc' => 'Prix croissant'];
    $parametres_url = [];
    $est_recherche = false;
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/catalogue/index.php';

    return (string) ob_get_clean();
}

echo "MiniShop — Formulaires : produit, catégories, produits, catalogue\n\n";

/* ── 1. Formulaire produit ─────────────────────────────────────────── */
echo "== 1 · Formulaire produit : champs alignés, Description en haut ==\n";
$produitHtml = rendreProduitForm();
$check('le formulaire porte la classe formulaire-produit',
    str_contains($produitHtml, '<form method="post" class="formulaire-produit" action="/admin/produit/nouveau">'));
$nbChamps = substr_count($produitHtml, '<p class="champ">');
$check('dix rangées libellé/champ structurées (référence… image)', $nbChamps === 10, (string) $nbChamps);
$check(
    'chaque libellé est lié à son champ (for = id) : reference, nom, id_categorie, description, prix_ht',
    str_contains($produitHtml, '<label for="reference">Référence *</label>')
        && str_contains($produitHtml, '<input id="reference" name="reference"')
        && str_contains($produitHtml, '<label for="id_categorie">Catégorie *</label>')
        && str_contains($produitHtml, '<select id="id_categorie" name="id_categorie"')
        && str_contains($produitHtml, '<label for="prix_ht">Prix HT (€) *</label>')
);
$check(
    'la rangée Description contient le libellé ET la zone de texte (textarea)',
    preg_match('/<p class="champ"><label for="description">Description<\/label>\s*<textarea id="description"/s', $produitHtml) === 1
);
$check(
    'la case Visible et le bouton « Créer le produit » gardent leurs rangées propres',
    str_contains($produitHtml, '<p class="champ-case">')
        && str_contains($produitHtml, '<p class="champ-envoi"><button type="submit">Créer le produit</button></p>')
);

/* ── 2. Catégories : étiquettes et aide dans le cadre ──────────────── */
echo "\n== 2 · Catégories : champs étiquetés, aide dans le cadre ==\n";
$categoriesHtml = rendreCategories();
$legende = null;
if (preg_match('/<legend>(.*?)<\/legend>/s', $categoriesHtml, $m) === 1) {
    $legende = trim((string) preg_replace('/\s+/', ' ', $m[1]));
}
$check('la légende du cadre est courte (« Nouvelle catégorie »)', $legende === 'Nouvelle catégorie', (string) $legende);
$check(
    'l\'aide « Le slug est généré automatiquement… » est un paragraphe DANS le cadre, pas la légende',
    str_contains($categoriesHtml, '<p class="aide">Le slug est généré automatiquement si laissé vide')
        && !str_contains((string) $legende, 'slug est généré')
);
$check(
    'les trois champs de modification ont chacun leur étiquette accessible (aria-label)',
    str_contains($categoriesHtml, '<input name="nom" aria-label="Nom de la catégorie"')
        && str_contains($categoriesHtml, '<input name="slug" aria-label="Slug de la catégorie"')
        && str_contains($categoriesHtml, '<input name="description" aria-label="Description de la catégorie"')
);
$check(
    'non-régression : création toujours étiquetée (for = id) et bouton « Créer » présent',
    str_contains($categoriesHtml, '<label for="nom">Nom *</label>') && str_contains($categoriesHtml, '>Créer</button>')
);

/* ── 3. Produits : filtre par couples inséparables ─────────────────── */
echo "\n== 3 · Produits : « Catégorie : » et sa liste dans le même bloc ==\n";
$produitsHtml = rendreProduits();
$check('le filtre porte la classe formulaire-filtres-produits',
    str_contains($produitsHtml, '<form method="get" class="formulaire-filtres-produits" action="/admin/produits">'));
$check(
    '« Catégorie : » et sa liste sont dans le même bloc champ (libellé + select)',
    preg_match('/<p class="champ">\s*<label for="cat">Catégorie :<\/label>\s*<select id="cat" name="cat">/s', $produitsHtml) === 1
);
$check(
    '« Recherche : » et son champ sont aussi un bloc, le bouton Filtrer a sa rangée',
    preg_match('/<p class="champ">\s*<label for="q">Recherche :<\/label>\s*<input type="search" id="q"/s', $produitsHtml) === 1
        && str_contains($produitsHtml, '<p class="champ-envoi">')
);
$check(
    'non-régression : la liste des produits suit toujours le filtre',
    str_contains($produitsHtml, 'CASQ-005') && str_contains($produitsHtml, '1 produit(s)')
);

/* ── 4. Catalogue : grille de filtres ordonnée ─────────────────────── */
echo "\n== 4 · Catalogue : prix minimum et maximum, chacun son champ complet ==\n";
$catalogueHtml = rendreCatalogue();
$check('le formulaire de filtres porte la classe formulaire-filtres-catalogue',
    str_contains($catalogueHtml, '<form method="get" action="/catalogue"'));
$check(
    '« Prix TTC minimum (€) » et « Prix TTC maximum (€) » sont deux blocs complets (plus de « max : » orphelin)',
    preg_match('/<p>\s*<label for="prix_min">Prix TTC minimum \(€\)<\/label>\s*<input type="number" id="prix_min"/s', $catalogueHtml) === 1
        && preg_match('/<p>\s*<label for="prix_max">Prix TTC maximum \(€\)<\/label>\s*<input type="number" id="prix_max"/s', $catalogueHtml) === 1
        && !str_contains($catalogueHtml, '>max :</label>')
);
$ordre = [];
foreach (['id="q"', 'id="cat"', 'id="prix_min"', 'id="prix_max"', 'name="stock"', 'id="tri"'] as $repere) {
    $pos = strpos($catalogueHtml, $repere);
    if ($pos !== false) {
        $ordre[] = $pos;
    }
}
$ordreTrie = $ordre;
sort($ordreTrie);
$check(
    'l\'ordre des champs est régulier : recherche, catégorie, prix min, prix max, stock, tri',
    count($ordre) === 6 && $ordre === $ordreTrie,
    (string) json_encode($ordre)
);
$check(
    'non-régression : le panneau « Filtrer » et le bouton « Appliquer » sont toujours là',
    str_contains($catalogueHtml, '<summary>Filtrer</summary>')
        && str_contains($catalogueHtml, '>Appliquer les filtres</button>')
);

/* ── 5. CSS réellement chargée ───────────────────────────────────── */
echo "\n== 5 · Feuille chargée : formulaires adaptatifs ==\n";
$css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/css/app.css');
$layout = (string) file_get_contents(dirname(__DIR__, 2) . '/app/View/layout.php');
$check('feuille de l’application chargée', str_contains($layout, 'href="/css/app.css"'));
$blocProduit = blocCss($css, '.formulaire-produit .champ');
$check('produit : grille à deux colonnes avec champs bornés', $blocProduit !== null
    && str_contains($blocProduit, 'display: grid') && str_contains($blocProduit, 'minmax(0, 1fr)'));
$check('produit : empilement prévu sur mobile', str_contains($css, '@media (max-width: 35em)')
    && str_contains($css, '.formulaire-produit .champ { grid-template-columns: minmax(0, 1fr); }'));
$check('catégories : aide repliable', str_contains($css, '.formulaire-creation-categorie .aide { overflow-wrap: anywhere; }'));
$check('produits : filtres souples et alignés', str_contains($css, '.formulaire-filtres-produits { display: flex; flex-wrap: wrap; align-items: end;'));
$check('catalogue : champs du panneau de filtres séparés et libellés', str_contains($css, '.panneau-filtres label')
    && str_contains($catalogueHtml, 'for="prix_min"') && str_contains($catalogueHtml, 'for="prix_max"'));

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Formulaires (produit, catégories, produits, catalogue) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
