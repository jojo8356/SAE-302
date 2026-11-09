<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Panier, bouton ‹ Valider ma commande › »
 * (UC-06 → UC-07, EF-CLI-06).
 *
 * Signalement : l'action principale du parcours d'achat s'affichait en
 * texte bleu souligné sur un rectangle orange, presque illisible, à
 * ~1000 px comme à 768 px — un lien ordinaire
 * (`<a href="/commande/valider"><strong>…</strong></a>`), sans aucun
 * style de bouton, alors que toutes les autres actions du site sont
 * de vrais boutons.
 *
 * Correctif verrouillé ici, en deux couches :
 *   1. balisage : un vrai <button type="submit" class="bouton-principal">
 *      dans un formulaire GET vers /commande/valider — même sans CSS,
 *      le rendu natif d'un bouton est lisible à toute largeur ;
 *   2. style : public/css/app.css (seule feuille du site, chargée par
 *      le gabarit, autorisée par la CSP en style-src 'self' — jamais
 *      de style en ligne) donne au bouton son rang d'action principale :
 *      fond orange foncé, texte blanc (contraste 7,3:1, WCAG AA),
 *      pas de soulignement, focus clavier visible.
 * L'action secondaire « Continuer mes achats » reste, elle, un lien :
 * c'est la hiérarchie visuelle attendue.
 *
 * La vue est rendue pour de vrai (fixture de recap), pas relue comme
 * du texte mort ; le CSS et la CSP sont vérifiés comme des contrats.
 *
 * Usage :  php tests/php/panier_cta_test.php
 *         node scripts/wasm_run.mjs tests/php/panier_cta_test.php   (sandbox)
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
    $suffixe = '';
    if (!$ok && $detail !== '') {
        $suffixe = " — {$detail}";
    }
    echo $icone . $label . $suffixe . "\n";
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
            'id_produit' => 1,
            'slug' => 'pc-portable-14',
            'nom' => 'PC Portable 14"',
            'reference' => 'PC-PORT-001',
            'stock_disponible' => 8,
            'prix_unitaire' => 838.80,
            'quantite' => 1,
            'total_ligne' => 838.80,
            'plafonne' => false,
        ]],
        'sous_total' => 838.80,
        'frais_port' => 0.0,
        'total' => 838.80,
        'port_motif' => 'Offerts dès 500 €',
    ];
}

echo "MiniShop — régression bouton « Valider ma commande » (panier)\n";

// ============================================== 1. Panier plein : le CTA est un bouton
echo "\n1. Panier plein : balisage du CTA\n";
{
    $html = rendrePanier(recapPlein());

    $check(
        'le CTA est un <button type="submit"> portant la classe bouton-principal',
        preg_match('#<button[^>]*type="submit"[^>]*class="bouton-principal"[^>]*>\s*Valider ma commande →\s*</button>#', $html) === 1
    );
    $check(
        'le bouton soumet un GET vers /commande/valider',
        preg_match('#<form[^>]*method="get"[^>]*action="/commande/valider"[^>]*>#', $html) === 1
    );
    $check(
        'plus aucun lien « Valider ma commande » (l\'ancien rendu illisible)',
        preg_match('#<a[^>]*href="/commande/valider"[^>]*>#', $html) !== 1
    );
    $check('aucun style en ligne sur le CTA (CSP sans inline)', !str_contains($html, 'bouton-principal" style='));
    $check(
        'l\'action secondaire « Continuer mes achats » reste un lien',
        preg_match('#<a[^>]*href="/catalogue"[^>]*>← Continuer mes achats</a>#', $html) === 1
    );
    $check('le total du panier reste affiché', str_contains($html, 'Total TTC'));
}

// ============================================== 2. Panier vide : pas de CTA
echo "\n2. Panier vide : aucun bouton de validation\n";
{
    $html = rendrePanier(['articles' => [], 'sous_total' => 0.0, 'frais_port' => 0.0, 'total' => 0.0, 'port_motif' => '']);
    $check('message « Votre panier est vide. »', str_contains($html, 'Votre panier est vide.'));
    $check('aucun formulaire vers /commande/valider', !str_contains($html, 'action="/commande/valider"'));
    $check('aucun bouton-principal', !str_contains($html, 'bouton-principal'));
}

// ============================================== 3. La feuille de style (contrat visuel)
echo "\n3. public/css/app.css : le style de l'action principale\n";
{
    $css = file_get_contents(dirname(__DIR__, 2) . '/public/css/app.css');
    if (!is_string($css)) {
        $css = '';
    }
    $check('la feuille existe et définit .bouton-principal', str_contains($css, '.bouton-principal'));
    $check('fond orange foncé #9a3412', str_contains($css, '--accent-fonce: #9a3412'));
    $check('texte blanc #ffffff (contraste 7,3:1 sur ce fond)', str_contains($css, 'color: #fff'));
    $check('aucun soulignement', str_contains($css, 'text-decoration: none'));
    $check('zone cliquable généreuse (padding)', str_contains($css, 'padding: .75rem 1.5rem'));
    $check('focus clavier visible (:focus-visible)', str_contains($css, '.bouton-principal:focus-visible'));
    $check('aucune ressource externe (url/import)', !str_contains($css, '@import'));
}

// ============================================== 4. Gabarit + CSP : la feuille est servie et autorisée
echo "\n4. Gabarit et CSP\n";
{
    $gabarit = file_get_contents(dirname(__DIR__, 2) . '/app/View/layout.php');
    if (!is_string($gabarit)) {
        $gabarit = '';
    }
    $check(
        'le gabarit charge /css/app.css',
        str_contains($gabarit, '<link rel="stylesheet" href="/css/app.css">')
    );

    $frontal = file_get_contents(dirname(__DIR__, 2) . '/public/index.php');
    if (!is_string($frontal)) {
        $frontal = '';
    }
    $check("la CSP autorise style-src 'self' (notre feuille uniquement)", str_contains($frontal, "style-src 'self'"));
    $check('la CSP garde default-src \'none\'', str_contains($frontal, "default-src 'none'"));
    $check("aucun 'unsafe-inline' introduit", !str_contains($frontal, 'unsafe-inline'));
}

// ------------------------------------------------------------ bilan
echo "\n============================================================\n";
if ($echecs > 0) {
    echo "bouton panier : {$echecs}/{$tests} vérification(s) en échec ❌\n";
    exit(1);
}
echo "bouton panier : {$tests}/{$tests} vérifications vertes ✅\n";
exit(0);
