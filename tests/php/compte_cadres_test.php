<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Mon compte (375 px) : les titres des
 * cadres chevauchent la bordure » (EF-CLI-01/02).
 *
 * Signalement : sur « Mon compte » à 375 px, les titres des deux
 * cadres (« État civil et coordonnées (l'email reste unique) »,
 * « L'ancien mot de passe est exigé ; le nouveau est re-haché »)
 * chevauchaient la bordure du cadre. (Les codes « RB-01/RB-12 »
 * cités de mémoire ont disparu de ces titres au signalement
 * n° 18.) Cause : deux <fieldset> nus, légendes longues posées
 * telles quelles sur la bordure, et AUCUNE règle fieldset/legend
 * applicable dans la feuille — le rendu reposait entièrement
 * sur le comportement par défaut du navigateur, qui ne replie
 * pas proprement un titre de cadre trop long.
 *
 * Correctif verrouillé ici, sur le précédent des catégories
 * (signalement n° 16 : « une légende longue débordait du
 * fieldset ») : légende courte (« État civil et coordonnées »,
 * « Mot de passe »), la précision devient un paragraphe d'aide
 * DANS le cadre, en langage clair (« chiffré », plus « re-haché ») ;
 * et la feuille donne aux légendes de Mon compte un peu d'air
 * (padding, comme les filtres du catalogue), une largeur bornée
 * et le passage à la ligne autorisé.
 *
 * Usage :  php tests/php/compte_cadres_test.php
 *         node scripts/wasm_run.mjs tests/php/compte_cadres_test.php   (sandbox)
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

/** Rend la page « Mon compte » pour un client donné. */
function rendreCompte(array $client): string
{
    $erreurs = [];
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/compte/index.php';

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

echo "MiniShop — Mon compte à 375 px : les titres des cadres restent dans le cadre\n\n";

/* ── 1. Page rendue : légendes courtes, aides dans le cadre ────────── */
echo "== 1 · Vue « Mon compte » : titres courts, précisions en aide, champs inchangés ==\n";
$html = rendreCompte(['nom' => 'Durand', 'prenom' => 'Zoe', 'email' => 'alice@example.com']);
preg_match_all('/<legend>([^<]+)<\/legend>/', $html, $m);
$legendes = $m[1] ?? [];
$check(
    "les deux cadres portent des titres courts, sans parenthèse ni code technique",
    $legendes === ['État civil et coordonnées', 'Mot de passe'],
    implode('|', $legendes)
);
$tropLongue = false;
foreach ($legendes as $legende) {
    if (strlen($legende) > 30) {
        $tropLongue = true;
    }
}
$check(
    "aucun titre ne peut déborder de la bordure à 375 px (30 caractères maximum)",
    !$tropLongue && count($legendes) === 2
);
$check(
    "la précision « email unique » est devenue une aide DANS le premier cadre",
    str_contains($html, '<p class="aide">L\'adresse email reste unique pour chaque compte.</p>')
);
$check(
    "l'exigence de l'ancien mot de passe est une aide dans le second cadre, en langage clair",
    str_contains($html, "L'ancien mot de passe est exigé pour valider le changement")
        && str_contains($html, "le nouveau est chiffré avant d'être enregistré")
        && !str_contains($html, 're-haché')
);
$check(
    "aucun code technique nulle part sur la page rendue",
    preg_match('/RB-\d|EF-[A-Z]|UC-\d/', $html) === 0
);
$check(
    "les deux formulaires portent la classe formulaire-compte (cible des règles dédiées)",
    str_contains($html, '<form method="post" action="/compte" class="formulaire-compte">')
        && str_contains($html, '<form method="post" action="/compte/mot-de-passe" class="formulaire-compte">')
);
$check(
    "non-régression : champs étoilés et boutons inchangés (Nom *, Email *, Enregistrer, Mettre à jour)",
    str_contains($html, '<label for="nom">Nom *</label>')
        && str_contains($html, '<label for="email">Email *</label>')
        && str_contains($html, '<button type="submit">Enregistrer</button>')
        && str_contains($html, '<button type="submit">Mettre à jour</button>')
        && str_contains($html, 'value="alice@example.com"')
);

/* ── 2. CSS : les règles qui gardent le titre dans le cadre ────────── */
echo "\n== 2 · Feuille du site : légende bornée, aérée, qui peut passer à la ligne ==\n";
$css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/minishop.css');
$blocLegend = blocCss($css, '.formulaire-compte legend');
$check(
    "la légende a de l'air autour du texte et ne dépasse jamais la largeur du cadre",
    $blocLegend !== null
        && str_contains($blocLegend, 'padding: 0 0.5rem')
        && str_contains($blocLegend, 'max-width: 100%')
);
$check(
    "le passage à la ligne du titre est autorisé (jamais de texte forcé sur une ligne)",
    $blocLegend !== null && str_contains($blocLegend, 'white-space: normal')
);
$blocCadre = blocCss($css, '.formulaire-compte fieldset');
$check(
    "le cadre peut se replier à la largeur de l'écran (min-width 0)",
    $blocCadre !== null && str_contains($blocCadre, 'min-width: 0')
);
$check(
    "l'aide a son style dans le cadre (marge basse, mots longs repliés)",
    blocCss($css, '.formulaire-compte .aide') !== null
);
$check(
    "aucune règle générique « fieldset » ou « legend » n'a été introduite (les autres cadres gardent leurs règles)",
    preg_match('/(^|[}\n])\s*fieldset\s*\{/', $css) === 0
        && preg_match('/(^|[}\n])\s*legend\s*\{/', $css) === 0
);

ob_end_clean();

$symbole = '❌';
$codeSortie = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $codeSortie = 0;
}
echo $symbole, " Mon compte (titres des cadres à 375 px) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($codeSortie);
