<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Mentions légales incomplètes » (EF-GEN-05).
 *
 * Signalement : la page Mentions légales ne donnait ni le nom ou un
 * contact de l'éditeur, ni l'hébergeur (l'université, une fois le
 * site en ligne sur LineServ) ; et elle annonçait « BUT Informatique
 * / Licence 3 » alors que l'équipe est en BUT2. La même erreur de
 * niveau figurait sur la page Contact.
 *
 * Correctif verrouillé ici : la page nomme l'éditeur (l'équipe
 * projet MiniShop, BUT2, Université Côte d'Azur) et un moyen de le
 * joindre (lien vers la page Contact du site — aucune donnée
 * personnelle inventée pour une maquette), elle nomme l'hébergeur
 * (l'Université Côte d'Azur, plateforme LineServ), et le niveau
 * affiché est le bon (BUT2) sur Mentions légales, Contact et le
 * README du projet ; plus aucune page visible ne parle de
 * « Licence 3 ».
 *
 * Usage :  php tests/php/mentions_legales_test.php
 *         node scripts/wasm_run.mjs tests/php/mentions_legales_test.php   (sandbox)
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

echo "MiniShop — Mentions légales : éditeur, contact, hébergeur, et le bon niveau (BUT2)\n\n";

/* ── 1. Mentions légales complètes ─────────────────────────────────── */
echo "== 1 · Mentions légales : éditeur nommé et joignable, hébergeur nommé ==\n";
$html = rendreHtml('home/mentions_legales');
$texte = texteVisible($html);
$check(
    "l'éditeur est nommé (« l'équipe projet MiniShop »)",
    str_contains($texte, "Éditeur : l'équipe projet MiniShop")
);
$check(
    "un moyen de joindre l'éditeur est donné (lien vers la page Contact)",
    str_contains($html, '<a href="/contact">Contact</a>') && str_contains($texte, "Pour joindre l'équipe")
);
$positionHebergeur = strpos($texte, 'Hébergeur');
$apresHebergeur = '';
if ($positionHebergeur !== false) {
    $apresHebergeur = substr($texte, $positionHebergeur);
}
$check(
    "une section « Hébergeur » existe et nomme l'Université Côte d'Azur et LineServ",
    $positionHebergeur !== false && str_contains($apresHebergeur, "l'Université Côte d'Azur")
        && str_contains($apresHebergeur, 'LineServ')
);
$check(
    "la page dit le bon cadre (SAE, BUT Informatique — BUT2, Université Côte d'Azur)",
    str_contains($texte, "BUT Informatique — BUT2, Université Côte d'Azur")
);

/* ── 2. Le niveau affiché est le bon, partout ──────────────────────── */
echo "\n== 2 · BUT2 partout, « Licence 3 » nulle part à l'écran ==\n";
$check(
    "Mentions légales : « Licence 3 » a disparu, « BUT2 » est affiché",
    !str_contains($texte, 'Licence 3') && str_contains($texte, 'BUT2')
);
$contact = texteVisible(rendreHtml('home/contact'));
$check(
    "page Contact : même correction (BUT2 affiché, « Licence 3 » disparu)",
    str_contains($contact, 'BUT2') && !str_contains($contact, 'Licence 3')
);
$pagesStatiques = ['home/aide', 'home/livraison', 'home/confidentialite', 'home/retractation', 'home/cgv', 'home/mentions_legales', 'home/contact', 'home/categories'];
$pagesAvecLicence3 = [];
foreach ($pagesStatiques as $vue) {
    $vars = [];
    if ($vue === 'home/categories') {
        $vars = ['categories' => [['id_categorie' => 2, 'nom' => 'Audio', 'slug' => 'audio', 'description' => 'Casques', 'nb_produits' => 3]]];
    }
    if (str_contains(texteVisible(rendreHtml($vue, $vars)), 'Licence 3')) {
        $pagesAvecLicence3[] = $vue;
    }
}
$check(
    "aucune page statique visible ne parle encore de « Licence 3 »",
    $pagesAvecLicence3 === [],
    implode(',', $pagesAvecLicence3)
);
$apropos = texteVisible(rendreHtml('home/apropos', [
    'statistiques' => ['visibles' => 11, 'categories' => 4],
    'parametres' => [
        ['cle' => 'frais_port', 'valeur' => '4.90', 'unite' => 'EUR TTC'],
        ['cle' => 'franchise_port', 'valeur' => '80.00', 'unite' => 'EUR TTC'],
    ],
]));
$check(
    "la page À propos reste cohérente (BUT Informatique, Université Côte d'Azur, sans niveau faux)",
    str_contains($apropos, "Université Côte d'Azur") && !str_contains($apropos, 'Licence 3')
);
$check(
    "le caractère pédagogique reste dit (maquette pédagogique, paiement simulé)",
    str_contains($texte, 'maquette pédagogique') && str_contains($texte, 'le paiement est simulé')
);

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Mentions légales (éditeur, contact, hébergeur, BUT2) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
