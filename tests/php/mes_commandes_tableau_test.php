<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Mes commandes (375 px) : montant et
 * Détail cachés à droite, légende coupée » (EF-CLI-08, UC-08).
 *
 * Signalement : sur « Mes commandes » à 375 px, le tableau
 * défilait et le montant comme le lien « Détail » restaient
 * cachés à droite ; la légende « Historique de vos commandes
 * (prix figés à l'achat) » était coupée au lieu de passer à la
 * ligne. (Le « — RB-06 » cité de mémoire a disparu au
 * signalement n° 18.) Cause : ce tableau était le dernier du
 * parcours SANS aucune règle dédiée — pas même une classe :
 * dimensionné à son contenu (numéro, date complète avec
 * l'heure), il repoussait les colonnes de droite hors écran,
 * et sa légende, aussi large que le tableau, filait hors champ.
 *
 * Correctif verrouillé ici : une mise en page tenue en page
 * (public/minishop.css, classe .tableau-mes-commandes) —
 * largeur 100 %, répartition fixe des colonnes via <colgroup>,
 * montant (en-tête et valeurs) à droite sur une ligne, légende
 * alignée à gauche et libre de passer à la ligne dans la page.
 * Aucun défilement horizontal : les six colonnes tiennent dans
 * la page, montant et « Détail » compris.
 *
 * La vue est rendue pour de vrai (fixture de commandes), comme
 * pour les régressions commandes admin et validation.
 *
 * Usage :  php tests/php/mes_commandes_tableau_test.php
 *         node scripts/wasm_run.mjs tests/php/mes_commandes_tableau_test.php   (sandbox)
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

/** Rend la vue « Mes commandes » avec une liste donnée, retourne le HTML. @param list<array<string,mixed>> $commandes */
function rendreMesCommandes(array $commandes): string
{
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/commande/mes.php';

    return (string) ob_get_clean();
}

/** Deux commandes : le statut le plus long et un montant à milliers. @return list<array<string,mixed>> */
function commandesFixture(): array
{
    return [
        [
            'id_commande' => 7,
            'numero' => 'CMD2026-000007',
            'date_commande' => '2026-01-15 10:15:00',
            'statut' => 'EN_PREPARATION',
            'nb_lignes' => 3,
            'montant_total' => 1234.50,
        ],
        [
            'id_commande' => 8,
            'numero' => 'CMD2026-000008',
            'date_commande' => '2026-02-02 09:02:00',
            'statut' => 'PAYEE',
            'nb_lignes' => 1,
            'montant_total' => 19.90,
        ],
    ];
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

echo "MiniShop — Mes commandes à 375 px : montant et Détail en page, légende qui se replie\n\n";

/* ── 1. Balisage rendu : structure et contenus ─────────────────────── */
echo "== 1 · Vue « Mes commandes » : les six colonnes, montant et Détail présents ==\n";
$html = rendreMesCommandes(commandesFixture());
$check(
    "le tableau porte la classe tableau-mes-commandes (et non un tableau nu)",
    str_contains($html, '<table class="tableau-mes-commandes">')
);
$check(
    "le colgroup fige les six colonnes dans l'ordre (numero, date, statut, articles, montant, detail)",
    str_contains($html, '<col class="col-numero"><col class="col-date"><col class="col-statut">')
        && str_contains($html, '<col class="col-articles"><col class="col-montant"><col class="col-detail">')
);
$check(
    "la légende est là, en entier et sans code technique",
    str_contains($html, "<caption>Historique de vos commandes (prix figés à l'achat)</caption>")
        && !str_contains($html, 'RB-06')
);
$check(
    "l'en-tête « Montant TTC » porte la classe montant (aligné avec ses valeurs)",
    str_contains($html, '<th scope="col" class="montant">Montant TTC</th>')
);
$check(
    "chaque montant est en cellule montant, formaté (1 234,50 € et 19,90 €)",
    str_contains($html, '<td class="montant">1 234,50 €</td>')
        && str_contains($html, '<td class="montant">19,90 €</td>')
);
$check(
    "le lien « Détail → » de chaque commande est présent (dernière colonne)",
    str_contains($html, '<a href="/mes-commandes/7">Détail →</a>')
        && str_contains($html, '<a href="/mes-commandes/8">Détail →</a>')
);
$check(
    "numéro, date, statut long et nombre d'articles restent affichés (ils se replieront dans leur colonne)",
    str_contains($html, 'CMD2026-000007')
        && str_contains($html, '15/01/2026')
        && str_contains($html, 'En préparation')
        && str_contains($html, '<td>3</td>')
);
$htmlVide = rendreMesCommandes([]);
$check(
    "sans commande : le message d'accueil remplace le tableau, aucun tableau fantôme",
    str_contains($htmlVide, "Vous n'avez pas encore de commande.")
        && !str_contains($htmlVide, '<table')
);

/* ── 2. CSS : les règles qui tiennent la page ──────────────────────── */
echo "\n== 2 · Feuille du site : 100 % fixe, légende qui passe à la ligne ==\n";
$css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/minishop.css');
$blocTableau = blocCss($css, '.tableau-mes-commandes');
$check(
    "le tableau fait 100 % de large, en répartition fixe des colonnes",
    $blocTableau !== null
        && str_contains($blocTableau, 'width: 100%')
        && str_contains($blocTableau, 'table-layout: fixed')
);
$somme = 0;
foreach (['col-numero', 'col-date', 'col-statut', 'col-articles', 'col-montant', 'col-detail'] as $colonne) {
    $blocColonne = blocCss($css, '.tableau-mes-commandes .' . $colonne);
    if ($blocColonne !== null && preg_match('/width:\s*(\d+)%/', $blocColonne, $m) === 1) {
        $somme += (int) $m[1];
    }
}
$check(
    "les six largeurs de colonnes totalisent 100 % (aucune colonne hors écran)",
    $somme === 100,
    'somme=' . $somme
);
$blocLegende = blocCss($css, '.tableau-mes-commandes caption');
$check(
    "la légende est alignée à gauche et peut passer à la ligne (jamais de texte sur une seule ligne forcée)",
    $blocLegende !== null
        && str_contains($blocLegende, 'text-align: left')
        && !str_contains($blocLegende, 'nowrap')
);
$blocMontant = blocCss($css, '.tableau-mes-commandes .montant');
$check(
    "les montants sont à droite, sur une ligne (jamais coupés au milieu)",
    $blocMontant !== null
        && str_contains($blocMontant, 'text-align: right')
        && str_contains($blocMontant, 'white-space: nowrap')
);
$check(
    "les cellules peuvent replier leur contenu long dans leur colonne (overflow-wrap)",
    str_contains($css, '.tableau-mes-commandes th,')
        && str_contains($css, 'overflow-wrap: break-word;')
);
$check(
    "aucune règle générique « table » n'a été introduite (les autres tableaux gardent leurs classes)",
    preg_match('/(^|[}\n])\s*table\s*\{/', $css) === 0
);

ob_end_clean();

$symbole = '❌';
$codeSortie = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $codeSortie = 0;
}
echo $symbole, " Mes commandes (tableau à 375 px, légende) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($codeSortie);
