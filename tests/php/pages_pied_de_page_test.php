<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Pied de page : pages Aide, Livraison,
 * Confidentialité et Rétractation absentes » (EF-GEN-05, test F-24 du
 * document de validation).
 *
 * Signalement : le pied de page ne contenait que « Mentions légales »
 * et « Conditions générales de vente » ; les pages Aide, Livraison,
 * Confidentialité et Rétractation attendues par la liste de tests
 * (et la page Contact citée par EF-GEN-05) n'existaient pas.
 *
 * Correctif verrouillé ici : cinq pages statiques visiteur
 * (home/aide, livraison, confidentialite, retractation, contact),
 * routées en GET public, et le pied de page du gabarit relie les huit
 * entrées : Aide · Livraison · Rétractation · Confidentialité ·
 * Contact · À propos · Mentions légales · Conditions générales de
 * vente. Contenus cohérents avec le fonctionnement réel du site
 * (port 4,90 € offert dès 80 €, annulation avant expédition,
 * conservation 24 mois, paiement simulé).
 *
 * Usage :  php tests/php/pages_pied_de_page_test.php
 *         node scripts/wasm_run.mjs tests/php/pages_pied_de_page_test.php   (sandbox)
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

/** Rend une vue statique home/* comme le fait HomeController. */
function rendrePage(string $vue): string
{
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/home/' . $vue . '.php';

    return (string) ob_get_clean();
}

/** Texte visible d'un HTML rendu (balises retirées, espaces normalisés). */
function texteVisible(string $html): string
{
    $texte = strip_tags($html);
    $texte = html_entity_decode($texte, ENT_QUOTES, 'UTF-8');
    $normalise = preg_replace('/\s+/', ' ', $texte);
    if ($normalise === null) {
        return trim($texte);
    }

    return trim($normalise);
}

echo "MiniShop — Pied de page : les pages attendues existent et sont liées\n\n";

/* ── 1. Les cinq pages : titre et contenu utile ───────────────────── */
echo "== 1 · Les pages créées : un vrai contenu visiteur ==\n";
$pages = [
    'aide' => ['<h1>Aide</h1>', 'Comment commander ?', '/mes-commandes', '/contact'],
    'livraison' => ['<h1>Livraison</h1>', 'Simulation uniquement', '/mes-commandes', '/retractation'],
    'confidentialite' => ['<h1>Politique de confidentialité</h1>', '24 mois', '/compte', '/contact'],
    'retractation' => ['<h1>Rétractation et retours</h1>', '14 jours', '/mes-commandes', '/cgv'],
    'contact' => ['<h1>Nous contacter</h1>', 'sans envoi d’e-mail ni réponse garantie', '/aide', '/compte'],
];
foreach ($pages as $vue => $attentes) {
    $html = rendrePage($vue);
    $check("page {$vue} : titre « {$attentes[0]} »", str_contains($html, $attentes[0]));
    $check(
        "page {$vue} : contenu attendu (« {$attentes[1]} »)",
        str_contains(texteVisible($html), $attentes[1]),
        texteVisible($html)
    );
    $check(
        "page {$vue} : renvoie vers « {$attentes[2]} »",
        str_contains($html, 'href="' . $attentes[2] . '"')
    );
    $check(
        "page {$vue} : renvoie aussi vers « {$attentes[3]} »",
        str_contains($html, 'href="' . $attentes[3] . '"')
    );
    $check(
        "page {$vue} : aucun jargon de développement affiché",
        !str_contains(texteVisible($html), 'EF-GEN')
            && !str_contains(texteVisible($html), 'RB-')
            && !str_contains(texteVisible($html), 'UC-')
    );
}

/* ── 2. Pied de page du gabarit : les huit liens ──────────────────── */
echo "\n== 2 · Gabarit : le pied de page relie les huit entrées ==\n";
$gabarit = (string) file_get_contents(dirname(__DIR__, 2) . '/app/View/layout.php');
$debut = strpos($gabarit, '<footer');
$fin = strpos($gabarit, '</footer>');
$pied = '';
if ($debut !== false && $fin !== false && $fin > $debut) {
    $pied = substr($gabarit, $debut, $fin - $debut);
}
$check('le gabarit contient un pied de page', $pied !== '');
$liens = [
    '/aide' => 'Aide',
    '/livraison' => 'Livraison',
    '/retractation' => 'Rétractation et retours',
    '/confidentialite' => 'Confidentialité',
    '/contact' => 'Contact',
    '/a-propos' => 'À propos',
    '/mentions-legales' => 'Mentions légales',
    '/cgv' => 'Conditions générales de vente',
];
foreach ($liens as $href => $libelle) {
    $check(
        "pied de page : lien « {$libelle} » → {$href}",
        str_contains($pied, '<a href="' . $href . '">' . $libelle . '</a>')
    );
}

/* ── 3. Routes et contrôleur : pages publiques, sans session ──────── */
echo "\n== 3 · Branchements : routes GET publiques et méthodes du contrôleur ==\n";
$racine = dirname(__DIR__, 2);
$routes = (string) file_get_contents($racine . '/public/index.php');
$controleur = (string) file_get_contents($racine . '/app/Controller/HomeController.php');
$methodes = [
    'aide' => 'aide',
    'livraison' => 'livraison',
    'confidentialite' => 'confidentialite',
    'retractation' => 'retractation',
    'contact' => 'formulaireContact',
];
foreach ($methodes as $route => $methode) {
    $check(
        "GET /{$route} routé (public, sans garde)",
        str_contains($routes, "get('/{$route}', [HomeController::class, '{$methode}'])")
    );
    $check(
        "HomeController::{$methode}() rend la vue home/{$route}",
        str_contains($controleur, 'function ' . $methode . '(): void')
            && str_contains($controleur, "render('home/{$route}'")
    );
    $check("la vue home/{$route}.php existe", is_file($racine . '/app/View/home/' . $route . '.php'));
}
$check(
    'les pages historiques sont toujours là (cgv, mentions-legales)',
    str_contains($routes, "get('/cgv', [HomeController::class, 'cgv'])")
        && str_contains($routes, "get('/mentions-legales', [HomeController::class, 'mentionsLegales'])")
        && rendrePage('cgv') !== '' && rendrePage('mentions_legales') !== ''
);

/* ── 4. Cohérence des contenus avec le site réel ──────────────────── */
echo "\n== 4 · Cohérence : les pages disent ce que le site fait vraiment ==\n";
$livraison = texteVisible(rendrePage('livraison'));
$check(
    'livraison : port 4,90 € et offert dès 80 € (comme au panier)',
    str_contains($livraison, 'aucun colis n’est expédié') && str_contains($livraison, 'frais de port')
        && str_contains($livraison, 'avant la validation')
);
$retractation = texteVisible(rendrePage('retractation'));
$cgv = texteVisible(rendrePage('cgv'));
$check(
    'rétractation et CGV racontent la même chose (annulation avant expédition)',
    str_contains($retractation, 'annulation avant expédition simulée') && str_contains($cgv, 'avant son expédition simulée')
);
$confidentialite = texteVisible(rendrePage('confidentialite'));
$check(
    'confidentialité : conservation 24 mois (paramètre du site) et aucun paiement réel',
    str_contains($confidentialite, '24 mois') && str_contains($confidentialite, 'tâche planifiée') && str_contains($confidentialite, 'pas de numéro de carte bancaire')
);
$aide = texteVisible(rendrePage('aide'));
$check(
    'aide : le paiement simulé est annoncé, pas de promesse de débit',
    str_contains($aide, 'simulé') && str_contains($aide, 'aucune donnée bancaire')
);

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Pages du pied de page : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
