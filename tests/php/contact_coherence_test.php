<?php

declare(strict_types=1);

/**
 * Le formulaire de démonstration est conservé : stockage local, pas d'e-mail
 * et pas de réponse garantie. Vérifie aussi la cohérence des pages légales.
 * Usage : php tests/php/contact_coherence_test.php
 */
require dirname(__DIR__, 2) . '/app/bootstrap.php';
ob_start();
$tests = 0;
$echecs = 0;
$check = static function (string $label, bool $ok) use (&$tests, &$echecs): void {
    ++$tests;
    if (!$ok) {
        ++$echecs;
        echo "❌ ", $label, "\n";
    }
};
function rendreContactVue(string $vue): string
{
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/' . $vue . '.php';
    return (string) ob_get_clean();
}
function texteContactVisible(string $html): string
{
    return (string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
}

$html = rendreContactVue('home/contact');
$contact = texteContactVisible($html);
$check('formulaire POST de contact', str_contains($html, '<form method="post" action="/contact">'));
$check('protection CSRF', str_contains($html, 'name="_csrf"'));
$check('nom, email et message demandés', str_contains($html, 'name="nom"') && str_contains($html, 'name="email"') && str_contains($html, 'name="message"'));
$check('enregistrement local annoncé', str_contains($contact, 'enregistré localement'));
$check('aucun envoi de mail ni réponse garantie', str_contains($contact, 'sans envoi d’e-mail ni réponse garantie'));
$check('aucun délai de réponse promis', !str_contains($contact, 'jours ouvrés') && !str_contains($contact, '48 heures'));
$check('conseil contre les données sensibles et lien confidentialité', str_contains($contact, 'données sensibles') && str_contains($html, 'href="/confidentialite"'));

$htmlConf = rendreContactVue('home/confidentialite');
$conf = texteContactVisible($htmlConf);
$check('données et journal local indiqués', str_contains($conf, 'nom, e-mail, message et date') && str_contains($conf, 'var/log/contact.jsonl'));
$check('pas de mail ni de garantie de réponse dans la politique', str_contains($conf, 'Aucun e-mail n’est envoyé') && str_contains($conf, 'aucune réponse n’est garantie'));
$check('pas de purge ou durée effective inventée', str_contains($conf, 'Aucune durée effective générale ni purge automatique') && str_contains($conf, 'ne déclenche pas à lui seul de suppression'));
$check('exercice des droits non garanti par le formulaire', str_contains($conf, 'ne garantit pas le traitement d’une demande de droits'));
$mentions = texteContactVisible(rendreContactVue('home/mentions_legales'));
$check('le formulaire ne remplace pas des coordonnées professionnelles', str_contains($mentions, 'ne remplace pas des coordonnées professionnelles fonctionnelles'));
$check('pas de fausse affirmation sur les données fictives', !str_contains($mentions, 'contiennent des données fictives'));
$layout = (string) file_get_contents(dirname(__DIR__, 2) . '/app/View/layout.php');
$debut = strpos($layout, '<footer');
$fin = strpos($layout, '</footer>');
$pied = '';
if ($debut !== false && $fin !== false) {
    $pied = texteContactVisible(substr($layout, $debut, $fin - $debut));
}
$check('pied de page présent sans négation de conservation des données', $pied !== '' && !str_contains($pied, 'aucune donnée personnelle conservée') && !str_contains($pied, 'aucune donnée n’est conservée'));

$details = (string) ob_get_clean();
if ($echecs !== 0) {
    echo $details;
}
$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, ' Contact : ', ($tests - $echecs), '/', $tests, " vérifications réussies\n";
exit($code);
