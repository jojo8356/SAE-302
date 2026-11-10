<?php

declare(strict_types=1);

/** Rendu isolé des dates affichées sur les cartes « Derniers ajouts ». */
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$tests = 0;
$echecs = 0;
$check = static function (string $label, bool $ok) use (&$tests, &$echecs): void {
    ++$tests;
    $symbole = '✅';
    if (!$ok) {
        ++$echecs;
        $symbole = '❌';
    }
    echo '  ' . $symbole . ' ' . $label . "\n";
};

$fuseauInitial = getenv('FUSEAU_AFFICHAGE');
putenv('FUSEAU_AFFICHAGE=UTC+2');

$base = ['nom' => 'Produit test', 'slug' => 'produit-test', 'categorie' => 'Test', 'prix_ttc' => 12, 'stock' => 5];
$categories = [];
$nouveautes = [
    array_merge($base, ['date_creation' => '2026-10-28 14:05:32']),
    array_merge($base, ['date_creation' => '2024-02-29 00:00:00']),
    array_merge($base, ['date_creation' => '2026-02-31 09:30:00']),
    array_merge($base, ['date_creation' => '2026-10-28 14:05:32"><script>alert(1)</script>']),
    array_merge($base, ['date_creation' => '']),
    $base,
];
ob_start();
require dirname(__DIR__, 2) . '/app/View/home/index.php';
$html = (string) ob_get_clean();
preg_match_all('/<article class="carte carte-produit">(.*?)<\/article>/s', $html, $trouves);
$cartes = $trouves[1];
$check('une date par carte', count($cartes) === 6 && substr_count($html, 'class="carte-produit__date"') === 6);
$check('date lisible et heure exacte', str_contains($cartes[0] ?? '', '28/10/2026 à 16h05'));
$check('date machine avec secondes conservées', str_contains($cartes[0] ?? '', 'datetime="2026-10-28T14:05:32Z"'));
$check('zéros et année bissextile', str_contains($cartes[1] ?? '', '29/02/2024 à 02h00'));
$check('date impossible : repli sans date inventée', str_contains($cartes[2] ?? '', 'Date d’ajout indisponible') && !str_contains($cartes[2] ?? '', '<time'));
$check('injection : repli sans balise exécutable', str_contains($cartes[3] ?? '', 'Date d’ajout indisponible') && !str_contains($cartes[3] ?? '', '<script'));
$check('date vide : repli sans élément time', str_contains($cartes[4] ?? '', 'Date d’ajout indisponible') && !str_contains($cartes[4] ?? '', '<time'));
$check('date absente : repli sans élément time', str_contains($cartes[5] ?? '', 'Date d’ajout indisponible') && !str_contains($cartes[5] ?? '', '<time'));
$check('métadonnée sous le bouton', strpos($cartes[0] ?? '', 'Voir la fiche') < strpos($cartes[0] ?? '', 'carte-produit__date'));
$css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/css/app.css');
preg_match('/\.carte-produit__date\s*\{([^}]*)\}/s', $css, $style);
$regles = $style[1] ?? '';
$check('date centrée et séparée discrètement', str_contains($regles, 'text-align: center') && str_contains($regles, 'border-top:'));
$check('texte adaptable et sans largeur fixe', str_contains($regles, 'overflow-wrap: anywhere') && str_contains($regles, 'width: 100%'));
$check('texte en couleur lisible de la charte', str_contains($regles, 'color: var(--encre-douce)') && str_contains($css, '--encre-douce: #475569'));
$check('pas de date ajoutée aux cartes du catalogue', !str_contains((string) file_get_contents(dirname(__DIR__, 2) . '/app/View/catalogue/index.php'), 'carte-produit__date'));

if ($fuseauInitial === false) { putenv('FUSEAU_AFFICHAGE'); } else { putenv('FUSEAU_AFFICHAGE=' . $fuseauInitial); }

echo 'DATES DERNIERS AJOUTS : ' . $tests . ' vérifications, ' . $echecs . " échec(s).\n";
if ($echecs !== 0) {
    exit(1);
}
