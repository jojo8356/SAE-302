<?php

declare(strict_types=1);

/** Contrats des cartes produit : rendu isolé, sans écriture de données. */
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$tests = 0;
$echecs = 0;
$check = static function (string $label, bool $ok) use (&$tests, &$echecs): void {
    ++$tests;
    if ($ok) {
        echo '  ✅ ' . $label . "\n";
    } else {
        ++$echecs;
        echo '  ❌ ' . $label . "\n";
    }
};

$produits = [
    ['image_url' => 'img/produits/zen14.jpg', 'nom' => 'Bracelet test', 'slug' => 'bracelet-test', 'categorie' => 'Objets', 'reference' => 'BRAC-TEST', 'prix_ttc' => 12, 'stock' => 5, 'date_creation' => '2026-10-28 14:05:32'],
    ['nom' => 'Écran "test" <script>', 'slug' => 'ecran-test', 'categorie' => 'Informatique', 'reference' => 'ECRA-TEST', 'prix_ttc' => 99, 'stock' => 0, 'date_creation' => '2026-10-27 14:05:32'],
];
$categories = [];
$nouveautes = $produits;
ob_start();
require dirname(__DIR__, 2) . '/app/View/home/index.php';
$accueil = (string) ob_get_clean();

$resultat = ['lignes' => $produits, 'total' => 2, 'page' => 1, 'pages' => 1];
$criteres = ['mot_cle' => '', 'id_categorie' => null, 'prix_min' => null, 'prix_max' => null, 'en_stock' => false, 'tri' => 'nom'];
$tris = ['nom' => 'Nom'];
$parametres_url = [];
$est_recherche = false;
ob_start();
require dirname(__DIR__, 2) . '/app/View/catalogue/index.php';
$catalogue = (string) ob_get_clean();

foreach (['accueil' => $accueil, 'catalogue' => $catalogue] as $page => $html) {
    preg_match_all('/<article class="carte carte-produit">(.*?)<\\/article>/s', $html, $trouves);
    $cartes = $trouves[1];
    $check($page . ' : les deux produits sont rendus', count($cartes) === count($produits));
    $check($page . ' : visuel de la carte décoratif', str_contains($cartes[0] ?? '', 'alt=""'));
    foreach ($produits as $index => $produit) {
        $carte = $cartes[$index] ?? '';
        preg_match_all('/<a\\b[^>]*>/', $carte, $liens);
        $check($page . ' : un seul lien de fiche pour le produit ' . ($index + 1),
            count($liens[0]) === 1 && str_contains($carte, 'href="/produit/' . e($produit['slug']) . '"'));
        $check($page . ' : bouton explicite et nom accessible échappé ' . ($index + 1),
            str_contains($carte, 'aria-label="Voir la fiche de ' . e($produit['nom']) . '"')
            && str_contains($carte, '>Voir la fiche</a>'));
        $niveau = 'h2';
        if ($page === 'accueil') {
            $niveau = 'h3';
        }
        $check($page . ' : nom du produit en vrai titre ' . ($index + 1),
            preg_match('/<' . $niveau . ' class="carte-produit__nom">\\s*' . preg_quote(e($produit['nom']), '/') . '\\s*<\\/' . $niveau . '>/', $carte) === 1);
        $check($page . ' : texte et attributs échappés ' . ($index + 1),
            !str_contains($carte, '<script>') && !str_contains($carte, 'aria-label="Voir la fiche de Écran "test"'));
    }
}

echo 'CARTES ACCESSIBLES : ' . $tests . ' vérifications, ' . $echecs . " échec(s).\n";
if ($echecs !== 0) {
    exit(1);
}
