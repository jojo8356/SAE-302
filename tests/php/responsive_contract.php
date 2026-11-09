<?php

declare(strict_types=1);

/**
 * MiniShop — contrat de robustesse responsive (docs/DA.md §14).
 *
 * Objectif : rendre la mise en page **défendable** devant un jury ou un
 * audit externe. Ces vérifications ne disent pas « c'est joli » — elles
 * disent « aucune des causes classiques de casse n'est présente » :
 *   - pas de largeur fixe qui déborde à 320 px ;
 *   - pas de tableau rendu illisible ou débordant hors cadre ;
 *   - une navigation utilisable au doigt, sans dépendre du JavaScript ;
 *   - des cibles ≥ 44 px et des champs à 16 px (pas de zoom iOS) ;
 *   - une typographie fluide (clamp) plutôt que des tailles figées ;
 *   - encoches, barre d'outils mobile, paysage, impression pris en compte.
 *
 * Usage : php tests/php/responsive_contract.php
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

$echecs = 0;
$tests = 0;

$check = static function (string $label, bool $ok, string $detail = '') use (&$echecs, &$tests): void {
    ++$tests;
    $icone = '  ✅ ';
    if (!$ok) {
        $icone = '  ❌ ';
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

$racine = dirname(__DIR__, 2);
$css = (string) file_get_contents($racine . '/public/css/app.css');
$layout = (string) file_get_contents($racine . '/app/View/layout.php');
$js = (string) file_get_contents($racine . '/public/js/ui.js');

$vues = [];
$iterateur = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine . '/app/View'));
foreach ($iterateur as $fichier) {
    if ($fichier instanceof SplFileInfo && $fichier->getExtension() === 'php') {
        $vues[] = (string) file_get_contents($fichier->getPathname());
    }
}
$htmlVues = implode("\n", $vues);

echo "MiniShop — contrat responsive (docs/DA.md §14)\n\n";

// --------------------------------------------------------------- 1. meta
echo "1. Contrat de fenêtre (viewport)\n";
$check('meta viewport présent', str_contains($layout, 'name="viewport"'));
$check('largeur = largeur de l\'appareil', str_contains($layout, 'width=device-width'));
$check('échelle initiale = 1 (pas de zoom bloqué)', str_contains($layout, 'initial-scale=1'));
$check('viewport-fit=cover pour les encoches', str_contains($layout, 'viewport-fit=cover'));
$check('aucun maximum-scale / user-scalable=no (2.5.4)', !str_contains($layout, 'maximum-scale')
    && !str_contains($layout, 'user-scalable=no'));

// ---------------------------------------------------------- 2. navigation
echo "\n2. Navigation adaptative\n";
$check('menu repliable déclaré (data-menu)', str_contains($layout, 'data-menu'));
$check('bouton <summary> focusable', str_contains($layout, '<summary'));
$check('menu ouvert sans JavaScript (attribut open)', str_contains($layout, '<details class="nav-principal__details" data-menu open>'));
$check('état synchronisé par ui.js (matchMedia 64em)', str_contains($js, "matchMedia('(max-width: 64em)')"));
$check('Échap referme le menu et rend le focus', str_contains($js, "'Escape'") && str_contains($js, 'focus()'));
$check('classe du bouton stylée dans app.css', str_contains($css, '.nav-principal__bouton'));
$check('liste du menu stylée (positionnement absolu)', str_contains($css, '.nav-principal .nav-principal__liste'));

// ------------------------------------------------------------- 3. tableaux
echo "\n3. Tableaux sur petit écran\n";
$nbTableaux = substr_count($htmlVues, '<table');
$nbRegions = substr_count($htmlVues, 'tableau-defilant');
$check('chaque tableau est dans une région défilante (' . $nbTableaux . ')', $nbTableaux > 0 && $nbRegions >= $nbTableaux, "{$nbRegions} région(s) pour {$nbTableaux} tableau(x)");
$check('régions accessibles au clavier (tabindex="0")', substr_count($htmlVues, 'tabindex="0"') >= $nbTableaux);
$check('régions nommées (role="region" + aria-label)', substr_count($htmlVues, 'role="region"') >= $nbTableaux);
$check('défilement tactile fluide (-webkit-overflow-scrolling)', str_contains($css, '-webkit-overflow-scrolling: touch'));
$check('défilement confiné (overscroll-behavior-x)', str_contains($css, 'overscroll-behavior-x: contain'));
$check('largeur minimale du tableau bornée', preg_match('/\.tableau-defilant table \{[^}]*min-width: \d+px/s', $css) === 1);
$check('taille de police des tableaux ≥ 14 px sur mobile', preg_match('/@media \(max-width: 40em\)[\s\S]*?table \{[^}]*font-size: var\(--t-sm\)/', $css) === 1);
$check('panier en cartes sous 560 px (reflow 1.4.10)', str_contains($css, '.tableau-defilant--carte') && str_contains($css, 'attr(data-label)'));
$check('cellules du panier étiquetées (data-label)', substr_count($htmlVues, 'data-label=') >= 5);
$commandeMes = (string) file_get_contents($racine . '/app/View/commande/mes.php');
$commandeValider = (string) file_get_contents($racine . '/app/View/commande/valider.php');
$check('historique et récapitulatif deviennent des cartes étiquetées sur mobile',
    str_contains($commandeMes, 'tableau-defilant--client')
    && str_contains($commandeValider, 'tableau-defilant--client')
    && str_contains($css, '.tableau-defilant--client tbody td::before')
    && str_contains($css, 'content: attr(data-label)'));
$check('montant, statut et détail de l’historique restent identifiés',
    str_contains($commandeMes, 'data-label="Montant TTC"')
    && str_contains($commandeMes, 'data-label="Statut"')
    && str_contains($commandeMes, 'data-label="Détail"'));
$check('récapitulatif mobile : produit, quantité, prix et total identifiés',
    str_contains($commandeValider, 'data-label="Produit"')
    && str_contains($commandeValider, 'data-label="Quantité"')
    && str_contains($commandeValider, 'data-label="Prix TTC"')
    && str_contains($commandeValider, 'data-label="Total TTC"'));
$check('totaux du récapitulatif restent ensemble sur mobile',
    str_contains($css, '.tableau-recapitulatif tfoot tr')
    && str_contains($css, '.tableau-recapitulatif tfoot td')
    && str_contains($commandeValider, 'Confirmer et payer : <?= euros($recap[\'total\']) ?>'));


// ------------------------------------------------------------ 4. typographie
echo "\n4. Typographie fluide et lisibilité\n";
$clamps = substr_count($css, 'clamp(');
$check('échelle typographique fluide (' . $clamps . ' clamp)', $clamps >= 8);
$check('titres : text-wrap balance', str_contains($css, 'text-wrap: balance'));
$check('paragraphes : text-wrap pretty', str_contains($css, 'text-wrap: pretty'));
$check('mots longs coupés (overflow-wrap)', str_contains($css, 'overflow-wrap: break-word'));
$check('longueur de ligne bornée (--mesure)', str_contains($css, '--mesure: 68ch'));
$check('aucune taille de police inférieure à 12 px', preg_match('/font-size:\s*(0?\.\d+rem|[0-9]px|1[01]px)/', $css) === 0);
$check('champs à 16 px minimum (pas de zoom iOS)', str_contains($css, 'font-size: max(16px, 1rem)'));

// --------------------------------------------------------- 5. points de rupture
echo "\n5. Points de rupture et compositions\n";
$paliers = ['25em', '35em', '40em', '45em', '64em', '87.5em'];
$manquants = [];
foreach ($paliers as $palier) {
    if (!str_contains($css, '@media (max-width: ' . $palier . ')') && !str_contains($css, '@media (min-width: ' . $palier . ')')) {
        $manquants[] = $palier;
    }
}
$check('paliers 25 / 35 / 40 / 45 / 64 / 87.5 em déclarés', $manquants === [], implode(', ', $manquants));
$check('paysage mobile traité (orientation: landscape)', str_contains($css, '(orientation: landscape)'));
$check('cibles tactiles élargies (pointer: coarse)', str_contains($css, '(pointer: coarse)'));
$check('grilles à deux colonnes empilées à 64em', preg_match('/@media \(max-width: 64em\)[\s\S]*?\.catalogue[\s\S]*?grid-template-columns: minmax\(0, 1fr\)/', $css) === 1);
$check('paliers en em, pas en px (zoom du texte respecté)', preg_match('/@media \((max|min)-width: \d+px\)/', $css) !== 1);
$check('filtres placés avant les résultats sous 45em', preg_match('/\.catalogue > \.panneau-filtres\s*\{[^}]*order:\s*0/s', $css) === 1);
$check('grille de produits sans débordement (minmax(0, 1fr))', substr_count($css, 'minmax(0, 1fr)') >= 3);

// ------------------------------------------------------ 6. adaptation continue
echo "\n6. Adaptation continue (sans dépendre du viewport seul)\n";
$check('requêtes de conteneur (@container)', str_contains($css, '@container'));
$check('garde de compatibilité (@supports container-type)', str_contains($css, '@supports (container-type: inline-size)'));
$check('gouttière fluide (--gouttiere)', str_contains($css, '--gouttiere: clamp(') && substr_count($css, 'var(--gouttiere)') >= 3);
$check('hauteur dynamique (100dvh)', str_contains($css, '100dvh'));
$check('encoches respectées (env(safe-area-inset))', substr_count($css, 'env(safe-area-inset') >= 2);
$check('ancres non masquées par l\'en-tête collant', str_contains($css, 'scroll-padding-top'));
$check('aucune largeur fixée en 100vw (source de scroll horizontal)',
    preg_match('/(^|[;{\s])(width|min-width):\s*100vw/m', $css) !== 1);
$check('débordement horizontal neutralisé sur html', preg_match('/html \{[^}]*overflow-x: hidden/s', $css) === 1);
$check('enfants de grille protégés (min-width: 0)', str_contains($css, 'min-width: 0'));

// Les formulaires « auth » partagent la même carte que le contact : +25 %
// sur grand écran, jamais plus large que son conteneur sur mobile.
$check('cartes auth/contact : largeur majorée de 25 % et bornée sur mobile',
    preg_match('/\.auth\s*\{[^}]*width:\s*100%;[^}]*max-width:\s*575px;[^}]*min-width:\s*0;/s', $css) === 1);
$check('formulaires auth/contact : champs textuels remplissent la carte, cases inchangées',
    str_contains($css, '.auth input:not([type="checkbox"]):not([type="radio"])')
    && preg_match('/\.auth textarea\s*\{[^}]*max-width:\s*none;/s', $css) === 1
    && preg_match('/\.auth fieldset\s*\{[^}]*min-width:\s*0;/s', $css) === 1);

// ------------------------------------------------------------ 7. impression
echo "\n7. Impression et autres médias\n";
$impression = (string) file_get_contents($racine . '/public/css/print.css');
$check('feuille d\'impression dédiée', str_contains($layout, 'media="print"'));
$check('marges de page définies (@page)', str_contains($impression, '@page'));
$check('menu et navigation masqués à l\'impression', str_contains($impression, 'nav') || str_contains($css, '@media print'));
$check('coupures de page maîtrisées', str_contains($impression, 'break-inside') || str_contains($css, 'break-inside'));

// ------------------------------------------------------------------ verdict
echo "\n" . str_repeat('=', 62) . "\n";
if ($echecs === 0) {
    echo "RESPONSIVE : TOUT EST VERT ✅ ({$tests} vérifications)\n";
    exit(0);
}
echo "RESPONSIVE : {$echecs} échec(s) sur {$tests} vérifications ❌\n";
exit(1);
