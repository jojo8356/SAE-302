<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Admin > Catégories : page qui défile,
 * bouton de droite coupé » (UC-11, EF-ADM-03).
 *
 * Signalement : dans Admin > Catégories, avec une fenêtre d'environ
 * 1 000 px, toute la page défilait horizontalement et le bouton à
 * droite était coupé. Cause : le tableau des catégories n'avait
 * AUCUNE règle dédiée — le formulaire d'édition en ligne (trois
 * champs côte à côte et son bouton « Enregistrer » dans la première
 * colonne) étalait le tableau selon son contenu et repoussait la
 * colonne Actions (bouton « Supprimer ») hors de la page.
 *
 * Correctif verrouillé ici : une mise en page tenue en page
 * (public/minishop.css, classe .tableau-categories) — largeur 100 %,
 * répartition fixe des colonnes via <colgroup>, champs d'édition à
 * la largeur de leur colonne et empilés, boutons toujours en entier
 * dans la page, à toute largeur de fenêtre.
 *
 * La vue est rendue pour de vrai (fixture de catégories), comme pour
 * les régressions commandes, fiche produit et panier.
 *
 * Usage :  php tests/php/categories_tableau_test.php
 *         node scripts/wasm_run.mjs tests/php/categories_tableau_test.php   (sandbox)
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

/** Rend la vue Admin > Catégories, retourne le HTML. @param list<array<string,mixed>> $categories @param list<string> $erreurs */
function rendreCategories(array $categories, array $erreurs = []): string
{
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/admin/categories.php';

    return (string) ob_get_clean();
}

/** Deux catégories : une description longue, pour éprouver le repli des contenus. @return list<array<string,mixed>> */
function categoriesFixture(): array
{
    return [
        [
            'id_categorie' => 2,
            'nom' => 'Audio',
            'slug' => 'audio',
            'description' => 'Casques, enceintes et microphones',
            'nb_produits' => 3,
        ],
        [
            'id_categorie' => 4,
            'nom' => 'Objets connectés',
            'slug' => 'objets-connectes',
            'description' => 'Montres connectées, bracelets d\'activité et stations météo domestiques',
            'nb_produits' => 2,
        ],
    ];
}

/** Extrait le bloc <table …>…</table> du HTML rendu (null si absent). */
function extraireTableau(string $html): ?string
{
    if (preg_match('/<table class="tableau-categories">.*?<\/table>/s', $html, $m) === 1) {
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

echo "MiniShop — Admin > Catégories : page tenue en page, boutons entiers\n\n";

/* ── 1. Balisage rendu : structure, édition, actions ──────────────── */
echo "== 1 · Vue catégories : les cinq colonnes annoncées, actions en entier ==\n";
$html = rendreCategories(categoriesFixture());
$tableau = extraireTableau($html);
$check('le tableau porte la classe tableau-categories', $tableau !== null);
$check(
    'le colgroup fixe les cinq colonnes dans l\'ordre (edition, slug, produits, description, actions)',
    $tableau !== null
        && str_contains($tableau, '<col class="col-edition"><col class="col-slug"><col class="col-produits">')
        && str_contains($tableau, '<col class="col-description"><col class="col-actions">')
);
$entetes = [];
if ($tableau !== null && preg_match_all('/<th scope="col">([^<]+)<\/th>/', $tableau, $m) === 5) {
    $entetes = $m[1];
}
$check(
    'les cinq en-têtes sont là, Actions en dernière colonne (à droite)',
    $entetes === ['Nom', 'Slug', 'Produits visibles', 'Description', 'Actions'],
    implode('|', $entetes)
);
$check(
    'chaque ligne garde son formulaire d\'édition complet (nom, slug, description, « Enregistrer »)',
    $tableau !== null
        && substr_count($tableau, '<form method="post" action="/admin/categories/') >= 2
        && str_contains($tableau, 'action="/admin/categories/2/modification"')
        && substr_count($tableau, '>Enregistrer</button>') === 2
        && str_contains($tableau, 'value="Objets connectés"')
);
$check(
    'chaque ligne garde son bouton « Supprimer » (bouton-danger) vers la confirmation',
    $tableau !== null
        && substr_count($tableau, '<button type="submit" class="bouton-danger">Supprimer</button>') === 2
        && str_contains($tableau, 'action="/admin/categories/2/suppression/confirmation"'),
    (string) $tableau
);
$check(
    'slugs, nombres de produits et descriptions restent affichés',
    $tableau !== null
        && str_contains($tableau, '<td>audio</td>')
        && str_contains($tableau, '<td>3</td>')
        && str_contains($tableau, 'stations météo domestiques')
);
$check(
    'non-régression : le formulaire de création est intact (nom, slug, description, « Créer »)',
    str_contains($html, '<form method="post" class="formulaire-creation-categorie" action="/admin/categories">')
        && str_contains($html, '<input id="nom" name="nom"')
        && str_contains($html, '>Créer</button>')
);
$htmlErreurs = rendreCategories(categoriesFixture(), ['Une catégorie porte déjà ce nom.']);
$check(
    'non-régression : une erreur métier reste affichée en alerte',
    str_contains($htmlErreurs, 'role="alert"') && str_contains($htmlErreurs, 'Une catégorie porte déjà ce nom.')
);

/* ── 2. CSS : les règles qui tiennent la page ──────────────────────── */
echo "\n== 2 · Feuille du site : répartition fixe, champs tenus, boutons entiers ==\n";
$css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/minishop.css');
$blocTableau = blocCss($css, '.tableau-categories');
$check(
    '.tableau-categories : largeur 100 %, colonnes à répartition fixe, bordures fusionnées',
    $blocTableau !== null && str_contains($blocTableau, 'width: 100%')
        && str_contains($blocTableau, 'table-layout: fixed') && str_contains($blocTableau, 'border-collapse: collapse'),
    (string) $blocTableau
);
$largeurs = [];
foreach (['col-edition', 'col-slug', 'col-produits', 'col-description', 'col-actions'] as $colonne) {
    $bloc = blocCss($css, '.tableau-categories .' . $colonne);
    $largeur = null;
    if ($bloc !== null && preg_match('/width:\s*(\d+)%/', $bloc, $m) === 1) {
        $largeur = (int) $m[1];
    }
    $largeurs[$colonne] = $largeur;
}
$check(
    'chaque colonne a sa largeur explicite (edition 32, slug 15, produits 11, description 26, actions 16)',
    $largeurs === ['col-edition' => 32, 'col-slug' => 15, 'col-produits' => 11, 'col-description' => 26, 'col-actions' => 16],
    (string) json_encode($largeurs)
);
$totalLargeurs = 0;
foreach ($largeurs as $largeur) {
    if ($largeur !== null) {
        $totalLargeurs += $largeur;
    }
}
$check('les largeurs totalisent 100 % : la page tient dans la fenêtre, sans défilement', $totalLargeurs === 100, (string) $totalLargeurs);
$blocCellules = blocCss($css, ".tableau-categories th,\n.tableau-categories td");
if ($blocCellules === null) {
    $blocCellules = blocCss($css, '.tableau-categories th, .tableau-categories td');
}
$check(
    'en-têtes et cellules alignés à gauche, contenus longs repliés (overflow-wrap)',
    $blocCellules !== null && str_contains($blocCellules, 'text-align: left') && str_contains($blocCellules, 'overflow-wrap: break-word'),
    (string) $blocCellules
);
$blocForm = blocCss($css, '.tableau-categories form');
$check(
    'les formulaires du tableau sont empilés en grille (plus de champs côte à côte qui étalent)',
    $blocForm !== null && str_contains($blocForm, 'display: grid'),
    (string) $blocForm
);
$blocInput = blocCss($css, '.tableau-categories input');
$check(
    'les champs d\'édition tiennent dans leur colonne (largeur 100 %, box-sizing)',
    $blocInput !== null && str_contains($blocInput, 'width: 100%') && str_contains($blocInput, 'box-sizing: border-box'),
    (string) $blocInput
);
$blocDanger = blocCss($css, '.tableau-categories .bouton-danger');
$check(
    'le bouton « Supprimer » reste en une pièce dans sa colonne (nowrap, zone resserrée)',
    $blocDanger !== null && str_contains($blocDanger, 'white-space: nowrap') && str_contains($blocDanger, 'padding: 0.6rem 0.75rem'),
    (string) $blocDanger
);
$check(
    'l\'en-tête de colonnes a le même gris que les autres tableaux du site (#f5f5f4)',
    str_contains($css, '.tableau-categories thead th') && str_contains($css, 'background-color: #f5f5f4')
);

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Catégories admin (page tenue en page) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
