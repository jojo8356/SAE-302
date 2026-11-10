<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Panier : mise en page du tableau »
 * (UC-06, EF-CLI-06).
 *
 * Signalement : le titre « Panier — 1 article(s) » était coupé sur
 * trois lignes ; dans la ligne produit, libellés et valeurs étaient
 * alignés à droite alors que la quantité et les boutons restaient à
 * gauche ; les lignes Sous-total / Frais de port avaient des fonds
 * gris décalés et « Sous-total (marchandises TTC) » tenait sur trois
 * lignes. Cause : le tableau du panier n'avait AUCUNE règle dédiée —
 * tout reposait sur les alignements par défaut du navigateur.
 *
 * Correctif verrouillé ici : une mise en page explicite et cohérente
 * (public/minishop.css, classe .tableau-panier) — légende sur une
 * seule ligne, tout aligné à gauche sauf les montants (en-têtes et
 * valeurs ensemble) alignés à droite, pied de tableau (sous-total,
 * port, total) avec un fond uniforme sur toute la largeur et des
 * libellés qui tiennent sur une ligne.
 *
 * La vue est rendue pour de vrai (fixture de recap), comme pour la
 * régression du bouton « Valider ma commande » (panier_cta_test).
 *
 * Usage :  php tests/php/panier_tableau_test.php
 *         node scripts/wasm_run.mjs tests/php/panier_tableau_test.php   (sandbox)
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

/** Rend la vue panier avec un récapitulatif donné, retourne le HTML. @param array<string,mixed> $recap */
function rendrePanier(array $recap): string
{
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/panier/index.php';

    return (string) ob_get_clean();
}

/** Recap d'un panier contenant un article (même forme que PanierSession::recapitulatif). @return array<string,mixed> */
function recapPlein(): array
{
    return [
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
}

/** Extrait le bloc <table …>…</table> du HTML rendu (null si absent). */
function extraireTableau(string $html): ?string
{
    if (preg_match('/<table class="tableau-panier tableau-nombres">.*?<\/table>/s', $html, $m) === 1) {
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

echo "MiniShop — Panier : mise en page du tableau (titre, alignements, totaux)\n\n";

/* ── 1. Balisage rendu : légende, alignements annoncés, totaux ─────── */
echo "== 1 · Vue panier : le tableau annonce sa mise en page ==\n";
$html = rendrePanier(recapPlein());
$tableau = extraireTableau($html);
$check('le tableau porte la classe tableau-panier', $tableau !== null);
$check(
    'la légende « Panier — 1 article(s) » est le titre du tableau',
    $tableau !== null && str_contains($tableau, '<caption>Panier — 1 article(s)</caption>')
);
$check(
    'les en-têtes de montants (Prix unitaire, Total) portent la classe montant',
    $tableau !== null
        && str_contains($tableau, '<th scope="col" class="montant">Prix unitaire TTC</th>')
        && str_contains($tableau, '<th scope="col" class="montant">Total TTC</th>')
);
$check(
    'les autres en-têtes (Produit, Quantité, Actions) restent sans classe montant',
    $tableau !== null
        && str_contains($tableau, '<th scope="col" class="produit">Produit</th>')
        && str_contains($tableau, '<th scope="col">Quantité</th>')
        && str_contains($tableau, '<th scope="col">Actions</th>')
);
$check(
    'la ligne produit : prix unitaire et total en cellules montant',
    $tableau !== null
        && str_contains($tableau, '<td class="montant" data-label="Prix unitaire TTC">154,80 €</td>')
        && substr_count($tableau, '<td class="montant" data-label=') >= 2
);
$check(
    'la ligne produit garde son lien, sa quantité modifiable et son bouton Retirer',
    $tableau !== null
        && str_contains($tableau, 'href="/produit/casque-audio-sans-fil-aura"')
        && str_contains($tableau, 'name="quantite"')
        && str_contains($tableau, '>Retirer</button>')
);
$check('récapitulatif présent hors du tableau avec libellés et montants',
    str_contains($html, '<div class="recap">') && str_contains($html, '<dt>Sous-total (marchandises TTC)</dt>')
        && str_contains($html, '<dt>Frais de port</dt>') && str_contains($html, '<dt><strong>Total TTC</strong></dt>')
        && str_contains($html, '154,80 €') && str_contains($html, '4,90 €') && str_contains($html, '159,70 €'));
$check('motif du port affiché dans le récapitulatif', str_contains($html, 'offerts dès 80,00 € de marchandises'));
$check(
    'non-régression : le CTA « Valider ma commande » reste le bouton principal',
    str_contains($html, '<button type="submit" class="bouton-principal">Valider ma commande →</button>')
);
$htmlVide = rendrePanier(['articles' => [], 'sous_total' => 0.0, 'frais_port' => 0.0, 'total' => 0.0, 'port_motif' => '']);
$check(
    'panier vide : pas de tableau, message et lien catalogue',
    !str_contains($htmlVide, 'tableau-panier') && str_contains($htmlVide, 'Votre panier est vide.')
);

/* ── 2. Feuille effectivement chargée ─────────────────────────────── */
echo "\n== 2 · Feuille du site : tableau, récapitulatif et mobile ==\n";
$css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/css/app.css');
$layout = (string) file_get_contents(dirname(__DIR__, 2) . '/app/View/layout.php');
$check('le gabarit charge la feuille examinée', str_contains($layout, 'href="/css/app.css"'));
$check('montants alignés à droite', str_contains((string) blocCss($css, '.tableau-panier .montant'), 'text-align: right'));
$check('quantité bornée par les règles de formulaire', str_contains($css, 'input[data-largeur-courte]') && str_contains($css, 'max-width: 5.5rem'));
$check('récapitulatif en grille, total occupant toute sa largeur', str_contains((string) blocCss($css, '.recap dl'), 'display: grid') && str_contains((string) blocCss($css, '.recap .total'), 'grid-column: 1 / -1'));
$check('panier transformé en cartes étiquetées sur mobile', str_contains($css, '.tableau-panier td::before') && str_contains($css, 'content: attr(data-label)') && str_contains($html, 'data-label="Quantité"'));

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Panier (mise en page du tableau) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
