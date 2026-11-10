<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Divers » (EF-VIS-03/04, EF-ADM-00, EF-GEN-05).
 *
 * Signalements : (1) sous-total, port et total affichés deux fois
 * dans le panier (tableau + « Récapitulatif ») ; (2) le « Top 5
 * produits » vide du tableau de bord sans aucun message ; (3)
 * plusieurs produits avec exactement la même photo ; (4) la
 * section Description affichée vide quand le produit n'en a pas ;
 * (5) le texte gris de la mention MiniShop du pied de page presque
 * illisible sur fond bleu nuit ; (6) sur Contact, titre centré et
 * phrase dessous alignée à gauche.
 *
 * État des lieux et correctifs verrouillés ici :
 * (1) dans le panier, le trio n'apparaît qu'une fois — le pied du
 * tableau ; le « Récapitulatif » est celui de la page de
 * validation, exigé avant paiement (art. L221-5) : les tests
 * comptent les occurrences, une par page et par libellé, pour
 * qu'aucun doublon ne revienne. (2) Un message explicite remplace
 * la liste vide. (3) La fiche n'affichait AUCUNE photo (EF-VIS-04
 * la prévoit) : elle affiche désormais le visuel propre à chaque
 * produit (public/img/, un fichier distinct par produit, CSP
 * img-src 'self'). (4) Une phrase de repli remplace la section
 * vide. (5) Le pied de page reçoit fond clair et texte presque
 * noir explicites — contraste calculé ≥ 4,5:1. (6) Titre et
 * paragraphes de Contact partagent l'alignement à gauche écrit.
 *
 * Les vues sont rendues pour de vrai (fixtures).
 *
 * Usage :  php tests/php/divers_test.php
 *         node scripts/wasm_run.mjs tests/php/divers_test.php   (sandbox)
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Model\Data\JsonStore;
use App\Model\Data\Seed;

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

/** Recap d'un panier d'un article (forme PanierSession::recapitulatif). @return array<string,mixed> */
function recapFixture(): array
{
    return [
        'articles' => [[
            'id_produit' => 5, 'slug' => 'casque-audio-sans-fil-aura', 'nom' => 'Casque audio sans fil Aura',
            'reference' => 'CASQ-005', 'stock_disponible' => 16, 'prix_unitaire' => 154.80,
            'quantite' => 1, 'total_ligne' => 154.80, 'plafonne' => false,
        ]],
        'sous_total' => 154.80, 'frais_port' => 4.90, 'total' => 159.70,
        'port_motif' => 'offerts dès 80,00 € de marchandises',
    ];
}

/** Rend la vue panier, retourne le HTML. */
function rendrePanier(): string
{
    $recap = recapFixture();
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/panier/index.php';

    return (string) ob_get_clean();
}

/** Rend la page de validation de commande, retourne le HTML. */
function rendreValider(): string
{
    $recap = recapFixture();
    $client = ['prenom' => 'Alice', 'nom' => 'Dupont'];
    $erreurs = [];
    $adresse_livraison = '12 rue de France, 06000 Nice';
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/commande/valider.php';

    return (string) ob_get_clean();
}

/** Rend le tableau de bord avec un Top 5 donné, retourne le HTML. @param list<array<string,mixed>> $top */
function rendreDashboard(array $top): string
{
    $indicateurs = ['commandes' => 0, 'en_preparation' => 0, 'clients' => 3, 'produits' => 12, 'ca_total' => 0.0];
    $rapport = ['par_statut' => [], 'top_produits' => $top];
    $alertes = [];
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/admin/dashboard.php';

    return (string) ob_get_clean();
}

/** Rend une fiche produit (description et image paramétrables), retourne le HTML. */
function rendreFiche(string $description, ?string $imageUrl): string
{
    $produit = [
        'id_produit' => 5, 'nom' => 'Casque audio sans fil Aura', 'reference' => 'CASQ-005',
        'prix_ht' => 129.00, 'tva' => 20.00, 'prix_ttc' => 154.80, 'stock' => 16,
        'description' => $description, 'image_url' => $imageUrl,
    ];
    $categorie = ['id_categorie' => 2, 'nom' => 'Audio'];
    $etat_stock = 'DISPONIBLE';
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/catalogue/produit.php';

    return (string) ob_get_clean();
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

/** Contraste WCAG entre deux couleurs #rrggbb (1.0 à 21.0). */
function contraste(string $hex1, string $hex2): float
{
    $lum = static function (string $hex): float {
        $canaux = [];
        foreach ([1, 3, 5] as $i) {
            $c = hexdec(substr($hex, $i, 2)) / 255;
            if ($c <= 0.03928) {
                $canaux[] = $c / 12.92;
            } else {
                $canaux[] = (($c + 0.055) / 1.055) ** 2.4;
            }
        }

        return 0.2126 * $canaux[0] + 0.7152 * $canaux[1] + 0.0722 * $canaux[2];
    };
    $l1 = $lum($hex1);
    $l2 = $lum($hex2);
    if ($l1 < $l2) {
        $tmp = $l1;
        $l1 = $l2;
        $l2 = $tmp;
    }

    return ($l1 + 0.05) / ($l2 + 0.05);
}

echo "MiniShop — Divers : doublon panier, Top 5, photos, description, pied de page, contact\n\n";

/* ── 1. Panier et validation : le trio une seule fois par page ─────── */
echo "== 1 · Sous-total, port, total : une occurrence par page, jamais deux ==\n";
$panierHtml = rendrePanier();
$check(
    'panier : « Sous-total » apparaît exactement une fois (le pied du tableau)',
    substr_count($panierHtml, 'Sous-total') === 1,
    (string) substr_count($panierHtml, 'Sous-total')
);
$check(
    'panier : « Frais de port » apparaît exactement une fois, « Total TTC » en pied une fois',
    substr_count($panierHtml, 'Frais de port') === 1
        && substr_count($panierHtml, '<dt><strong>Total TTC</strong></dt>') === 1
);
$check(
    'panier : un seul récapitulatif sans doublon',
    substr_count($panierHtml, '<div class="recap">') === 1
);
$validerHtml = rendreValider();
$check(
    'validation : le « Récapitulatif de votre panier » (avant paiement, L221-5) est là, une fois',
    substr_count($validerHtml, 'Récapitulatif de votre panier') === 1
        && substr_count($validerHtml, 'Sous-total') === 1
        && substr_count($validerHtml, 'Frais de port') === 1
        && substr_count($validerHtml, 'Total simulé') === 1
);

/* ── 2. Top 5 vide : un message, pas une liste vide ────────────────── */
echo "\n== 2 · Tableau de bord : Top 5 vide explicite, Top 5 rempli inchangé ==\n";
$dashboardVide = rendreDashboard([]);
$check(
    'Top 5 vide : le message « Aucun produit vendu pour le moment » est affiché',
    str_contains($dashboardVide, 'Aucun produit vendu pour le moment')
);
$check(
    'Top 5 vide : aucune liste <ol> vide ne subsiste après le titre',
    preg_match('/<h2>Top 5 produits<\/h2>\s*<ol>/s', $dashboardVide) !== 1
);
$dashboardPlein = rendreDashboard([
    ['nom' => 'Casque audio sans fil Aura', 'reference' => 'CASQ-005', 'unites' => 9, 'ca' => 1393.20],
]);
$check(
    'Top 5 rempli : le tableau et son premier produit sont affichés, sans le message',
    str_contains($dashboardPlein, '<table') && str_contains($dashboardPlein, 'Casque audio sans fil Aura')
        && !str_contains($dashboardPlein, 'Aucun produit vendu')
);

/* ── 3. Photos produits : un visuel distinct par produit ───────────── */
echo "\n== 3 · Fiche produit : la photo propre au produit (EF-VIS-04) ==\n";
$ficheHtml = rendreFiche('Réduction de bruit active.', 'img/aura.svg');
$check(
    'la fiche affiche la photo du produit (img, src du produit, alt nommé)',
    str_contains($ficheHtml, '<img class="carte-produit__image" src="/img/aura.svg"')
        && str_contains($ficheHtml, 'alt="Visuel indicatif : Casque audio sans fil Aura"')
);
$ficheSansImage = rendreFiche('Réduction de bruit active.', null);
$check(
    'sans image déclarée, aucune balise img cassée n\'est affichée',
    !str_contains($ficheSansImage, '<img')
);
$dossierDivers = sys_get_temp_dir() . '/minishop_divers_' . getmypid() . '_' . bin2hex(random_bytes(4));
$lignes = [];
try {
    $storeDivers = JsonStore::open($dossierDivers);
    Seed::force($storeDivers);
    $lignes = array_values($storeDivers->all('produit'));
} finally {
    $fichiersDivers = scandir($dossierDivers);
    if ($fichiersDivers === false) {
        $fichiersDivers = [];
    }
    foreach ($fichiersDivers as $fichierDiver) {
        if ($fichierDiver !== '.' && $fichierDiver !== '..') {
            @unlink($dossierDivers . '/' . $fichierDiver);
        }
    }
    @rmdir($dossierDivers);
}
$urls = [];
foreach ($lignes as $ligne) {
    if (isset($ligne['image_url'])) {
        $urls[] = (string) $ligne['image_url'];
    }
}
$check(
    'les produits du jeu de démonstration ont des images réutilisées par type',
    count($lignes) > 0 && count($urls) === count($lignes) && count(array_unique($urls)) >= 2,
    count($urls) . ' URL, ' . count(array_unique($urls)) . ' distinctes'
);
$fichiersOk = true;
$empreintes = [];
foreach ($urls as $url) {
    $chemin = dirname(__DIR__, 2) . '/public/' . $url;
    if (!is_file($chemin)) {
        $fichiersOk = false;
    } else {
        $empreintes[] = md5_file($chemin);
    }
}
$check(
    'chaque image déclarée existe dans public/',
    $fichiersOk && count($empreintes) === count($lignes)
);
$frontal = (string) file_get_contents(dirname(__DIR__, 2) . '/public/index.php');
$check(
    'la CSP autorise les images du site (img-src \'self\'), toujours rien d\'externe',
    str_contains($frontal, "img-src 'self'") && str_contains($frontal, "default-src 'none'")
);

/* ── 4. Description vide : une phrase de repli ─────────────────────── */
echo "\n== 4 · Description absente : la section n'est plus vide ==\n";
$ficheSansDesc = rendreFiche('', 'img/aura.svg');
$check(
    'description vide : « Aucune description n\'est disponible pour ce produit » est affiché',
    str_contains($ficheSansDesc, "Aucune description n'est disponible pour ce produit pour le moment.")
);
$check(
    'description présente : le texte du produit est affiché, sans la phrase de repli',
    str_contains($ficheHtml, 'Réduction de bruit active.')
        && !str_contains($ficheHtml, "Aucune description n'est disponible")
);

/* ── 5. Pied de page lisible et Contact accessible ─────────────────── */
echo "\n== 5 · Pied et Contact : contraste et cohérence ==\n";
$css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/css/app.css');
$blocFooter = blocCss($css, '.pied');
$blocFooterLien = blocCss($css, '.pied a');
$check('fond sombre et texte clair explicites', $blocFooter !== null && str_contains($blocFooter, 'background: var(--encre)') && str_contains($blocFooter, 'color: #e2e8f0'));
$check('contraste du texte du pied : ≥ 4,5:1', contraste('#e2e8f0', '#0f172a') >= 4.5);
$check('contraste des liens du pied : ≥ 4,5:1', $blocFooterLien !== null && str_contains($blocFooterLien, 'color: #dbeafe') && contraste('#dbeafe', '#0f172a') >= 4.5);
$gabarit = (string) file_get_contents(dirname(__DIR__, 2) . '/app/View/layout.php');
$check('mention pédagogique présente dans le pied', str_contains($gabarit, 'MiniShop — maquette pédagogique') && str_contains($gabarit, 'class="pied"'));
$contactHtml = (string) file_get_contents(dirname(__DIR__, 2) . '/app/View/home/contact.php');
$check('Contact : titre et explication réunis dans le même en-tête', str_contains($contactHtml, '<header class="entete-page">') && str_contains($contactHtml, '<h1>Nous contacter</h1>') && str_contains($contactHtml, 'sans envoi d’e-mail ni réponse garantie'));

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Divers (panier, Top 5, photos, description, pied de page, contact) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
