<?php

declare(strict_types=1);

/** Contrats de contenu et de câblage ; ne remplace pas un audit juridique. */
$racine = dirname(__DIR__, 2);
$tests = 0;
$echecs = 0;
$check = static function (string $label, bool $ok) use (&$tests, &$echecs): void {
    ++$tests;
    if (!$ok) {
        ++$echecs;
    }
    $symbole = '✅';
    if (!$ok) {
        $symbole = '❌';
    }
    echo '  ' . $symbole . ' ' . $label . "\n";
};
$lire = static function (string $nom) use ($racine): string {
    $contenu = file_get_contents($racine . '/' . $nom);
    if ($contenu === false) {
        return '';
    }

    return $contenu;
};
$routes = $lire('public/index.php');
$pied = $lire('app/View/layout.php');
$controleur = $lire('app/Controller/HomeController.php');
$pages = [
    'mentions-legales' => 'mentions_legales',
    'cgv' => 'cgv',
    'cgu' => 'cgu',
    'confidentialite' => 'confidentialite',
    'cookies' => 'cookies',
    'livraison' => 'livraison',
    'retractation' => 'retractation',
    'garanties' => 'garanties',
];
foreach ($pages as $route => $vue) {
    $check($route . ' : route publique déclarée', str_contains($routes, "get('/" . $route . "'"));
    $check($route . ' : lien dans le pied', str_contains($pied, 'href="/' . $route . '"'));
    $check($route . ' : vue et titre', str_contains($lire('app/View/home/' . $vue . '.php'), '<h1>'));
}
$retractation = $lire('app/View/home/retractation.php');
$check('rétractation : 14 jours dès la réception', str_contains($retractation, '14 jours à compter de la réception'));
$check('rétractation : aucune déclaration prétendument transmise', str_contains($retractation, 'ne transmettent aucune déclaration'));
$check('rétractation : annulation clairement distincte', str_contains($retractation, 'ne constitue pas une rétractation légale'));
$check('rétractation : modèle et signature papier', str_contains($retractation, 'Signature du (des) consommateur(s)') && str_contains($retractation, 'uniquement en cas de notification'));
$cgv = $lire('app/View/home/cgv.php');
$encadre = $lire('app/View/home/garanties_encadre.php');
$check('CGV : encadré légal inclus réellement', str_contains($cgv, "require __DIR__ . '/garanties_encadre.php'"));
$check('garanties : délais, remèdes et vices cachés', str_contains($encadre, 'délai de trente jours') && str_contains($encadre, 'six mois') && str_contains($encadre, '1641 à 1649'));
$privacy = $lire('app/View/home/confidentialite.php');
$check('données : contact, journaux et anciennes valeurs expliqués', str_contains($privacy, 'contact.jsonl') && str_contains($privacy, 'anciennes et nouvelles valeurs'));
$check('données : activation et limites de la tâche précisées', str_contains($privacy, 'seulement si l’exploitant installe et active') && str_contains($privacy, 'comptes liés à des commandes'));
$check('droits et contact incomplet signalés', str_contains($privacy, 'CNIL') && str_contains($privacy, 'à renseigner'));
$cookies = $lire('app/View/home/cookies.php');
$auth = $lire('app/Security/Auth.php');
$check('cookie de session correctement identifié', str_contains($cookies, 'MINISHOPSESS') && str_contains($auth, "session_name('MINISHOPSESS')"));
$check('cookie outil dev et Secure décrit', str_contains($cookies, 'MINISHOPSESSID') && str_contains($cookies, 'sans attribut <code>Secure</code>'));
$check('consentement futur et refus mentionnés', str_contains($cookies, 'consentement préalable') && str_contains($cookies, 'refus aussi simple'));
$javascript = '';
$fichiersJs = glob($racine . '/public/js/*.js');
if ($fichiersJs === false) {
    $fichiersJs = [];
}
foreach ($fichiersJs as $fichierJs) {
    $contenuJs = file_get_contents($fichierJs);
    if ($contenuJs !== false) {
        $javascript .= $contenuJs;
    }
}
$check('stockage local limité aux préférences et expliqué', str_contains($javascript, 'minishop.preferences.v1') && !str_contains($javascript, 'sessionStorage') && !str_contains($javascript, 'document.cookie') && str_contains($lire('app/View/home/cookies.php'), 'localStorage'));
$check('préférences : retrait permission navigateur distingué de la réinitialisation', str_contains($lire('app/View/home/preferences.php'), 'paramètres du navigateur'));
$check('erreur 500 : aucune fausse promesse d’absence de modification', !str_contains($lire('app/View/errors/500.php'), 'aucune donnée n\'a été modifiée') && !str_contains($lire('app/View/errors/500.php'), 'aucune donnée n’a été modifiée'));
$check('contrôleur : ressources et connexions limitées à l’origine', str_contains($routes, "script-src 'self'") && str_contains($routes, "connect-src 'self'"));
$ecritureContact = $lire('app/Service/NettoyageJournaux.php');
$check('contact : succès dépend de l’écriture', str_contains($controleur, 'if (!$this->journaliserContact') && str_contains($controleur, 'return NettoyageJournaux::ajouter(') && str_contains($ecritureContact, '$n === strlen($ligne)'));
$check('contact : aucun engagement de réponse fictif', !str_contains($controleur, 'répondons sous 2 jours'));
$check('pied : aucune fausse absence de conservation', !str_contains($pied, 'aucune donnée personnelle conservée'));
$css = $lire('public/css/app.css');
$check('design : largeur adaptative et centrage', str_contains($css, 'width: min(100%, 76ch)') && str_contains($css, 'margin-inline: auto'));
$check('formulaires : lien confidentialité', str_contains($lire('app/View/auth/inscription.php'), 'href="/confidentialite"') && str_contains($lire('app/View/home/contact.php'), 'href="/confidentialite"'));
$check('inscription : confirmation alignée avec le contrôleur', str_contains($lire('app/View/auth/inscription.php'), 'name="confirmation"'));
$check('détail de commande : annulation distincte de la rétractation', str_contains($lire('app/View/commande/detail.php'), 'distincte du') && !str_contains($lire('app/View/commande/detail.php'), 'Conformément au droit de rétractation'));
$check('commande : liens précontractuels', str_contains($lire('app/View/commande/valider.php'), 'href="/retractation"') && str_contains($lire('app/View/commande/valider.php'), 'href="/garanties"'));

echo "\nInformation juridique : " . $tests . ' vérifications, ' . $echecs . " échec(s).\n";
if ($echecs > 0) {
    exit(1);
}
