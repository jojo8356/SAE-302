<?php

declare(strict_types=1);

/** Vérifie les mentions légales de l’édition étudiante non professionnelle. */

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

echo "MiniShop — Mentions légales : édition étudiante non professionnelle et réserves\n\n";
$html = rendreHtml('home/mentions_legales');
$texte = texteVisible($html);
$check(
    'éditeurs étudiants, co-direction de publication et emails universitaires renseignés',
    str_contains($texte, 'Éditeur et responsable de publication')
        && str_contains($texte, 'Johan Polsinelli')
        && str_contains($texte, 'Chahd Gouiaa')
        && str_contains($texte, 'Directeur de publication : co-responsabilité de l’équipe projet')
        && str_contains($texte, 'johan.polsinelli@etu.unice.fr')
        && str_contains($texte, 'chahd.gouiaa@etu.unice.fr')
        && str_contains($texte, 'chahd.gouiaa@etu.univ-cotedazur.fr')
        && !str_contains($texte, 'à renseigner')
);
$check(
    'cadre non professionnel LCEN art. 6, III, 2 sans divulgation de domicile ou téléphone personnel',
    str_contains($texte, 'à titre non professionnel')
        && str_contains($texte, 'article 6, III, 2')
        && str_contains($texte, 'adresses de domicile et numéros de téléphone personnels ne sont pas publiés')
);
$check('aucun vendeur identifié sans vérification', str_contains($texte, 'Aucun vendeur n’est identifié'));
$check(
    'hébergement pédagogique Université Côte d’Azur / IUT renseigné avec réserve d’exécution locale',
    str_contains($texte, 'Hébergement')
        && str_contains($texte, 'Université Côte d’Azur — IUT Nice Côte d’Azur')
        && str_contains($texte, 'linserv-info-01.campus.unice.fr')
        && str_contains($texte, '28 avenue Valrose, 06103 Nice Cedex 2')
        && str_contains($texte, '+33 (0)4 89 15 00 00')
        && str_contains($texte, 'hors de ce serveur universitaire')
);
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
echo $symbole, " Mentions légales (équipe étudiante, contact et hébergement pédagogique) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
