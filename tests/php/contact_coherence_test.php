<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Contact : promesse de réponse et données »
 * (EF-GEN-05).
 *
 * Signalement : « le site promet une réponse "sous 2 jours ouvrés"
 * sur une maquette, et le pied de page dit "aucune donnée
 * personnelle conservée" alors que le formulaire demande nom et
 * e-mail ». Vérifié dans l'application : aucune de ces deux phrases
 * n'existe — il n'y a ni délai de réponse promis, ni mention de
 * données dans le pied de page, ni même de formulaire de contact.
 * Les vrais problèmes de cohérence, voisins, étaient ceux-ci :
 *  - la page Contact n'écartait la réponse qu'en petits caractères
 *    et au conditionnel (« Aucun message envoyé à un éventuel canal
 *    de contact ne serait traité »), pour un canal qui n'existe pas ;
 *  - la page Confidentialité envoyait « pour toute demande
 *    concernant vos données, passez par la page Contact » — une page
 *    d'où rien ne peut être envoyé ;
 *  - les Mentions légales affirmaient « les comptes contiennent des
 *    données fictives » alors que l'inscription enregistre nom et
 *    e-mail (conservés 36 mois, dit la page Confidentialité).
 *
 * Correctif verrouillé ici : la page Contact dit clairement et en
 * pleine page qu'il n'y a ni formulaire ni service client, qu'aucun
 * message ne peut être envoyé et qu'aucune réponse ne sera donc
 * apportée ; Confidentialité désigne « Mon compte » comme la seule
 * voie de demande de la maquette ; les Mentions légales conseillent
 * des informations fictives au lieu d'affirmer qu'elles le sont, et
 * renvoient à Confidentialité. La transparence ne bouge pas : ce qui
 * est enregistré (nom, prénom, e-mail, adresse) et conservé 36 mois
 * reste dit, et le pied de page continue de ne promettre qu'une
 * chose vraie (aucun paiement réel).
 *
 * Usage :  php tests/php/contact_coherence_test.php
 *         node scripts/wasm_run.mjs tests/php/contact_coherence_test.php   (sandbox)
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

echo "MiniShop — Contact : pas de réponse promise, des données dites honnêtement\n\n";

/* ── 1. Contact : l'absence de réponse est dite clairement ─────────── */
echo "== 1 · Page Contact : ni formulaire, ni réponse — et c'est écrit ==\n";
$htmlContact = rendreHtml('home/contact');
$contact = texteVisible($htmlContact);
$check(
    "l'absence de canal est dite en pleine page (ni formulaire ni service client, aucun message, aucune réponse)",
    str_contains($contact, 'ni formulaire de contact ni service client')
        && str_contains($contact, 'aucun message ne peut être envoyé')
        && str_contains($contact, 'aucune réponse ne sera donc apportée')
);
$check(
    "l'ancienne réserve au conditionnel (« éventuel canal… ne serait traité ») a disparu",
    !str_contains($contact, 'éventuel canal') && !str_contains($contact, 'ne serait traité')
);
$check(
    "aucun délai de réponse n'est promis (« jours ouvrés », « 2 jours »…)",
    !str_contains($contact, 'jours ouvrés') && !str_contains($contact, '2 jours')
        && !str_contains($contact, '48 heures')
);
$check(
    "les renvois utiles restent là (Aide, Mentions légales, Mon compte, Confidentialité)",
    str_contains($htmlContact, '<a href="/aide">Aide</a>')
        && str_contains($htmlContact, '<a href="/mentions-legales">Mentions légales</a>')
        && str_contains($htmlContact, '<a href="/compte">Mon compte</a>')
        && str_contains($htmlContact, '<a href="/confidentialite">Confidentialité</a>')
);

/* ── 2. Confidentialité : la voie de demande existe vraiment ───────── */
echo "\n== 2 · Confidentialité : « Mon compte » est la voie de demande, pas un canal fantôme ==\n";
$htmlConfidentialite = rendreHtml('home/confidentialite');
$confidentialite = texteVisible($htmlConfidentialite);
$check(
    "la transparence ne bouge pas (nom, prénom et e-mail enregistrés, conservés 36 mois)",
    str_contains($confidentialite, 'vos nom, prénom et e-mail') && str_contains($confidentialite, 'conservées 36 mois')
);
$check(
    "les droits désignent la voie réelle (« Mon compte », seule voie de demande de la maquette)",
    str_contains($confidentialite, "c'est la seule voie de demande")
        && str_contains($confidentialite, 'ni formulaire de contact ni service client')
);
$check(
    "l'ancien renvoi trompeur (« passez par la page Contact ») a disparu",
    !str_contains($confidentialite, 'passez par la page')
);
$check(
    "les liens Mon compte et Contact (pour le cadre, pas pour écrire) restent présents",
    str_contains($htmlConfidentialite, '<a href="/compte">Mon compte</a>')
        && str_contains($htmlConfidentialite, '<a href="/contact">Contact</a>')
);

/* ── 3. Mentions légales : conseiller le fictif, pas l'affirmer ────── */
echo "\n== 3 · Mentions légales : les données saisies sont enregistrées, et c'est dit ==\n";
$htmlMentions = rendreHtml('home/mentions_legales');
$mentions = texteVisible($htmlMentions);
$check(
    "le conseil remplace l'affirmation (« Utilisez des informations fictives : ce que vous saisissez… est enregistré »)",
    str_contains($mentions, 'Utilisez des informations fictives : ce que vous saisissez')
        && str_contains($mentions, 'est enregistré pour faire fonctionner la boutique')
);
$check(
    "l'ancienne affirmation (« les comptes contiennent des données fictives ») a disparu",
    !str_contains($mentions, 'contiennent des données fictives')
);
$check(
    "la section renvoie au détail de la page Confidentialité",
    str_contains($htmlMentions, '<a href="/confidentialite">Confidentialité</a>')
);

/* ── 4. Vue d'ensemble : aucune page ne promet ni ne nie ───────────── */
echo "\n== 4 · Pied de page et pages statiques : rien de promis, rien de nié ==\n";
$layout = (string) file_get_contents(dirname(__DIR__, 2) . '/app/View/layout.php');
$debutPied = strpos($layout, '<footer>');
$finPied = strpos($layout, '</footer>');
$pied = '';
if ($debutPied !== false && $finPied !== false) {
    $pied = texteVisible(substr($layout, $debutPied, $finPied - $debutPied));
}
$check(
    "le pied de page ne parle que de paiement (« aucun paiement réel »), jamais de données « non conservées »",
    str_contains($pied, 'aucun paiement réel') && !str_contains($pied, 'donnée')
);
$pagesStatiques = ['home/aide', 'home/livraison', 'home/confidentialite', 'home/retractation', 'home/cgv', 'home/mentions_legales', 'home/contact', 'home/categories'];
$promesses = [];
foreach ($pagesStatiques as $vue) {
    $vars = [];
    if ($vue === 'home/categories') {
        $vars = ['categories' => [['id_categorie' => 2, 'nom' => 'Audio', 'slug' => 'audio', 'description' => 'Casques', 'nb_produits' => 3]]];
    }
    $texte = texteVisible(rendreHtml($vue, $vars));
    if (str_contains($texte, 'jours ouvrés') || str_contains($texte, 'aucune donnée personnelle conservée') || str_contains($texte, 'aucune donnée n\'est conservée')) {
        $promesses[] = $vue;
    }
}
$check(
    "aucune page statique ne promet de délai de réponse ni ne nie la conservation des données",
    $promesses === [],
    implode(',', $promesses)
);

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Contact (réponse non promise, données cohérentes) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
