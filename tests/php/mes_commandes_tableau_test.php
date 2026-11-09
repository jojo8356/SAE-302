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
    "le tableau porte la classe dédiée au parcours client",
    str_contains($html, '<table class="tableau-commandes-client">')
);
$check(
    "les six colonnes sont présentes et étiquetées sur mobile",
    str_contains($html, 'data-label="Numéro"') && str_contains($html, 'data-label="Date"')
        && str_contains($html, 'data-label="Statut"') && str_contains($html, 'data-label="Articles"')
        && str_contains($html, 'data-label="Montant TTC"') && str_contains($html, 'data-label="Détail"')
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
    str_contains($html, '<td class="montant" data-label="Montant TTC">1 234,50 €</td>')
        && str_contains($html, '<td class="montant" data-label="Montant TTC">19,90 €</td>')
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
        && str_contains($html, '<td data-label="Articles">3</td>')
);
$htmlVide = rendreMesCommandes([]);
$check(
    "sans commande : le message d'accueil remplace le tableau, aucun tableau fantôme",
    str_contains($htmlVide, "Vous n'avez pas encore de commande.")
        && !str_contains($htmlVide, '<table')
);

/* ── 2. CSS réellement chargée : cartes sur mobile ─────────────────── */
echo "\n== 2 · Feuille chargée : cartes mobiles identifiées ==\n";
$racine = dirname(__DIR__, 2);
$css = (string) file_get_contents($racine . '/public/css/app.css');
$layout = (string) file_get_contents($racine . '/app/View/layout.php');
$check('le gabarit charge la feuille vérifiée', str_contains($layout, 'href="/css/app.css"'));
$check('les règles mobiles sont ciblées sous 35em', str_contains($css, '@media (max-width: 35em)') && str_contains($css, '.tableau-defilant--client'));
$blocTableau = blocCss($css, '.tableau-defilant--client table');
$check('tableau limité à 100 % sans largeur minimale', $blocTableau !== null && str_contains($blocTableau, 'width: 100%') && str_contains($blocTableau, 'min-width: 0'));
$blocLigne = blocCss($css, '.tableau-defilant--client tbody tr');
$check('une carte distincte par commande', $blocLigne !== null && str_contains($blocLigne, 'border:') && str_contains($blocLigne, 'margin-bottom:'));
$blocCellule = blocCss($css, '.tableau-defilant--client tbody td');
$check('valeur et libellé sont affichés ensemble et les mots longs se replient', $blocCellule !== null && str_contains($blocCellule, 'grid-template-columns') && str_contains($blocCellule, 'overflow-wrap: anywhere'));
$blocLabel = blocCss($css, '.tableau-defilant--client tbody td::before');
$check('les libellés des colonnes sont repris dans les cartes', $blocLabel !== null && str_contains($blocLabel, 'content: attr(data-label)'));

ob_end_clean();

$symbole = '❌';
$codeSortie = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $codeSortie = 0;
}
echo $symbole, " Mes commandes (tableau à 375 px, légende) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($codeSortie);
