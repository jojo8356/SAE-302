<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Valider ma commande à 375 px »
 * (EF-CLI-07, art. L221-5).
 *
 * Signalement « important » : dans le récapitulatif de validation,
 * on ne voyait jamais en même temps le libellé et le montant — sans
 * défilement « Sous-total », « Frais de port » et « Total à payer »
 * sans les montants ; en faisant défiler le tableau, les montants
 * sans les libellés. Le client pouvait donc confirmer et payer sans
 * voir clairement le total. Cause : ce tableau était le seul du
 * parcours sans classe ni règle CSS — il se dimensionnait à son
 * contenu (noms de produits longs, quatre colonnes) et débordait
 * d'un écran de 375 px, la colonne des montants hors champ.
 * (Le texte sous le bouton, lui, ne contient plus RB-18/RB-06/RB-20
 * depuis le signalement n° 18 ; on le reverrouille ici.)
 *
 * Correctif verrouillé ici : le tableau devient
 * .tableau-recapitulatif — largeur 100 %, répartition fixe des
 * colonnes par <colgroup> (produit 38, prix 22, quantité 13,
 * total 27 : total 100 %), montants à droite sur une ligne,
 * libellés du pied libres de passer à la ligne dans leur zone —
 * donc libellé ET montant côte à côte à toute largeur, sans
 * défilement horizontal. Et le bouton de confirmation affiche le
 * total à payer (« Confirmer et payer : 159,70 € (simulé) »), pour
 * que le montant soit sous les yeux au moment de décider.
 *
 * Usage :  php tests/php/valider_recap_test.php
 *         node scripts/wasm_run.mjs tests/php/valider_recap_test.php   (sandbox)
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

/** Rend la page de validation avec un récapitulatif d'un article. */
function rendreValider(): string
{
    $recap = [
        'articles' => [[
            'id_produit' => 9, 'nom' => 'Câble USB-C tressé 1 m', 'reference' => 'CABL-009',
            'prix_unitaire' => 12.00, 'quantite' => 1, 'total_ligne' => 12.00,
        ]],
        'sous_total' => 12.00, 'frais_port' => 4.90, 'total' => 16.90,
        'port_motif' => 'livraison standard (4,90 €)',
    ];
    $client = ['nom' => 'Dupont', 'prenom' => 'Alice', 'email' => 'alice@example.com'];
    $erreurs = [];
    $adresse_livraison = '12 rue de France, 06000 Nice';
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/commande/valider.php';

    return (string) ob_get_clean();
}

/** Extrait le bloc de déclarations d'un sélecteur CSS, seul ou groupé (null si absent). */
function blocCss(string $css, string $selecteur): ?string
{
    $debut = strpos($css, $selecteur);
    if ($debut === false) {
        return null;
    }
    $apres = $css[$debut + strlen($selecteur)] ?? '';
    if ($apres !== ' ' && $apres !== ',' && $apres !== '{') {
        return null;
    }
    $accolade = strpos($css, '{', $debut);
    $fin = strpos($css, '}', $debut);
    if ($accolade === false || $fin === false) {
        return null;
    }

    return substr($css, $accolade, $fin - $accolade + 1);
}

/** Extrait le premier tableau du HTML rendu (null si absent). */
function extraireTableau(string $html): ?string
{
    $debut = strpos($html, '<table');
    $fin = strpos($html, '</table>');
    if ($debut === false || $fin === false) {
        return null;
    }

    return substr($html, $debut, $fin - $debut + 8);
}

echo "MiniShop — Valider ma commande : libellés et montants ensemble, même à 375 px\n\n";

/* ── 1. Structure : un tableau dédié, colonnes figées ──────────────── */
echo "== 1 · Page rendue : tableau récapitulatif structuré pour tenir en page ==\n";
$html = rendreValider();
$tableau = extraireTableau($html);
$check(
    "le récapitulatif porte sa classe dédiée (.tableau-recapitulatif)",
    $tableau !== null && str_contains($tableau, '<table class="tableau-recapitulatif">')
);
$check(
    "le colgroup fixe les quatre colonnes dans l'ordre (produit, prix, qté, total)",
    $tableau !== null
        && str_contains($tableau, '<colgroup><col class="col-produit"><col class="col-prix"><col class="col-qte"><col class="col-total"></colgroup>')
);
$paires = [
    'Sous-total TTC' => '12,00',
    'Frais de port' => '4,90',
    'Total à payer' => '16,90',
];
foreach ($paires as $libelle => $montant) {
    $trouve = false;
    if ($tableau !== null && preg_match_all('#<tr>(.*?)</tr>#s', $tableau, $lignes) !== false) {
        foreach ($lignes[1] as $ligne) {
            if (str_contains($ligne, $libelle) && str_contains($ligne, $montant)) {
                $trouve = true;
            }
        }
    }
    $check("la ligne « {$libelle} » porte son montant dans la même ligne", $trouve);
}
$check(
    "chaque montant du pied est dans une cellule .montant (à droite, une ligne)",
    $tableau !== null && substr_count($tableau, '<td class="montant">') >= 4
        && str_contains($tableau, '<th scope="row" colspan="3">Total à payer</th><td class="montant"><strong>')
);
$check(
    "le bouton de confirmation affiche le total (« Confirmer et payer : 16,90 € (simulé) »)",
    str_contains($html, 'Confirmer et payer : ' . euros(16.90) . ' (simulé)')
);
$check(
    "les libellés de totaux restent uniques sur la page (aucun doublon réintroduit)",
    substr_count($html, 'Sous-total') === 1 && substr_count($html, 'Total à payer') === 1
        && substr_count($html, 'Récapitulatif de votre panier') === 1
);
$check(
    "aucun code technique sous le bouton (RB-18/RB-06/RB-20 ont disparu au signalement n° 18)",
    preg_match('/(RB-\\d|EF-[A-Z]|ENF-\\d|UC-\\d)/', $html) === 0
);

/* ── 2. CSS : les règles qui tiennent la page à 375 px ─────────────── */
echo "\n== 2 · Feuille du site : 100 % fixe, libellé et montant côte à côte ==\n";
$css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/minishop.css');
$bloc = blocCss($css, '.tableau-recapitulatif');
$check(
    ".tableau-recapitulatif : largeur 100 %, colonnes à répartition fixe, bordures fusionnées",
    $bloc !== null && str_contains($bloc, 'width: 100%')
        && str_contains($bloc, 'table-layout: fixed') && str_contains($bloc, 'border-collapse: collapse'),
    (string) $bloc
);
$largeurs = [];
foreach (['col-produit', 'col-prix', 'col-qte', 'col-total'] as $colonne) {
    $blocColonne = blocCss($css, '.tableau-recapitulatif .' . $colonne);
    $largeur = null;
    if ($blocColonne !== null && preg_match('/width:\\s*(\\d+)%/', $blocColonne, $m) === 1) {
        $largeur = (int) $m[1];
    }
    $largeurs[$colonne] = $largeur;
}
$check(
    "chaque colonne a sa largeur explicite (produit 38, prix 22, qté 13, total 27)",
    $largeurs === ['col-produit' => 38, 'col-prix' => 22, 'col-qte' => 13, 'col-total' => 27],
    (string) json_encode($largeurs)
);
$totalLargeurs = 0;
foreach ($largeurs as $largeur) {
    if ($largeur !== null) {
        $totalLargeurs += $largeur;
    }
}
$check(
    "les largeurs totalisent exactement 100 % (rien ne déborde, rien à faire défiler)",
    $totalLargeurs === 100,
    (string) $totalLargeurs
);
$blocMontant = blocCss($css, '.tableau-recapitulatif .montant');
$check(
    "les montants sont alignés à droite et tenus sur une ligne (nowrap)",
    $blocMontant !== null && str_contains($blocMontant, 'text-align: right')
        && str_contains($blocMontant, 'white-space: nowrap')
);
$blocPied = blocCss($css, '.tableau-recapitulatif tfoot th');
$blocCellules = blocCss($css, '.tableau-recapitulatif th');
$check(
    "les libellés du pied peuvent passer à la ligne dans leur zone (pas de nowrap qui expulserait le montant)",
    $blocCellules !== null && str_contains($blocCellules, 'overflow-wrap: break-word')
        && ($blocPied === null || !str_contains($blocPied, 'nowrap')),
    (string) $blocPied
);
$check(
    "aucun défilement horizontal n'est organisé pour ce tableau (pas d'overflow-x dans la feuille)",
    !str_contains($css, 'overflow-x')
);

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Valider ma commande (récapitulatif à 375 px, total visible) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
