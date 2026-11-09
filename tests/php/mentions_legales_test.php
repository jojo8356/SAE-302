<?php

declare(strict_types=1);

/** Vérifie les mentions légales sans inventer d’éditeur ou d’hébergement. */

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

/** Rend une vue et retourne son HTML brut. @param array<string,mixed> $vars */
function rendreHtml(string $vue, array $vars = []): string
{
    extract($vars);
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/' . $vue . '.php';

    return (string) ob_get_clean();
}

/** Texte visible d'un HTML : balises retirées, espaces normalisés. */
function texteVisible(string $html): string
{
    $texte = strip_tags($html);
    $texte = html_entity_decode($texte, ENT_QUOTES, 'UTF-8');

    return (string) preg_replace('/\s+/u', ' ', $texte);
}

echo "MiniShop — Mentions légales : informations connues et inconnues\n\n";
$html = rendreHtml('home/mentions_legales');
$texte = texteVisible($html);
$check('éditeur et directeur de publication à renseigner', str_contains($texte, 'Éditeur et responsable de publication') && str_contains($texte, 'Directeur de publication') && str_contains($texte, 'à renseigner'));
$check('aucun vendeur identifié sans vérification', str_contains($texte, 'Aucun vendeur n’est identifié'));
$check('hébergeur à renseigner pour le déploiement réel', str_contains($texte, 'Hébergement') && str_contains($texte, 'Nom ou dénomination de l’hébergeur, adresse et téléphone') && !str_contains($texte, 'plateforme LineServ'));
$check('le contact est lié mais ne remplace pas des coordonnées professionnelles', str_contains($html, 'href="/contact"') && str_contains($texte, 'ne remplace pas des coordonnées professionnelles fonctionnelles'));
$check('la page renvoie à la politique de confidentialité', str_contains($html, 'href="/confidentialite"'));
$check('cadre pédagogique et non-production signalés', str_contains($texte, 'Projet pédagogique') && str_contains($texte, 'pas une boutique en production'));
$contact = texteVisible(rendreHtml('home/contact'));
$check('contact : stockage local sans mail ni réponse garantie', str_contains($contact, 'enregistré localement') && str_contains($contact, 'sans envoi d’e-mail ni réponse garantie'));
$pages = ['home/aide', 'home/livraison', 'home/confidentialite', 'home/retractation', 'home/cgv', 'home/mentions_legales', 'home/contact'];
$pagesAvecLicence3 = [];
foreach ($pages as $vue) {
    if (str_contains(texteVisible(rendreHtml($vue)), 'Licence 3')) {
        $pagesAvecLicence3[] = $vue;
    }
}
$check('aucun niveau d’études faux sur les pages statiques', $pagesAvecLicence3 === [], implode(',', $pagesAvecLicence3));
$apropos = texteVisible(rendreHtml('home/apropos', [
    'statistiques' => ['visibles' => 11, 'categories' => 4],
    'parametres' => [],
]));
$check('page À propos : cadre étudiant et paiement simulé', str_contains($apropos, 'BUT Informatique') && str_contains($apropos, "Université Côte d'Azur") && str_contains($apropos, 'simulés') && !str_contains($apropos, 'Licence 3'));

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Mentions légales (identité, contact et hébergement à vérifier) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
