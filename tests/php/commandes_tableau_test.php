<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Admin > Commandes : colonne Statut coupée »
 * (UC-13, EF-ADM-06).
 *
 * Signalement : dans Admin > Commandes, le tableau défilait
 * horizontalement et la colonne Statut — la plus utile pour piloter
 * les commandes — était coupée à droite. Cause : le tableau n'avait
 * AUCUNE règle dédiée — il s'étalait selon son contenu (dates
 * complètes, noms de clients) et repoussait les colonnes de droite
 * hors de la page.
 *
 * Correctif verrouillé ici : une mise en page tenue en page
 * (public/minishop.css, classe .tableau-commandes) — largeur 100 %,
 * répartition fixe des colonnes via <colgroup>, colonne Statut avec
 * sa largeur propre et un libellé qui ne se coupe jamais ; ce sont
 * les contenus longs (client) qui se replient. Aucun défilement
 * horizontal, les six colonnes tiennent dans la page.
 *
 * La vue est rendue pour de vrai (fixture de commandes), comme pour
 * les régressions fiche produit et panier.
 *
 * Usage :  php tests/php/commandes_tableau_test.php
 *         node scripts/wasm_run.mjs tests/php/commandes_tableau_test.php   (sandbox)
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

/** Rend la vue Admin > Commandes avec une liste donnée, retourne le HTML. @param list<array<string,mixed>> $commandes */
function rendreCommandes(array $commandes): string
{
    $statuts = ['BROUILLON', 'EN_PREPARATION', 'PAYEE', 'EXPEDIEE', 'LIVREE', 'ANNULEE'];
    $filtres = ['statut' => '', 'client' => '', 'du' => '', 'au' => '', 'montant_min' => null];
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/admin/commandes.php';

    return (string) ob_get_clean();
}

/** Deux commandes de pilotage : le statut le plus long et un client au nom long. @return list<array<string,mixed>> */
function commandesFixture(): array
{
    return [
        [
            'id_commande' => 7,
            'numero' => 'CMD2026-000007',
            'date_commande' => '2026-09-28 10:15:00',
            'client' => 'Alexandra de Montmorency-Beauregard',
            'statut' => 'EN_PREPARATION',
            'montant_total' => 159.70,
        ],
        [
            'id_commande' => 8,
            'numero' => 'CMD2026-000008',
            'date_commande' => '2026-09-28 11:02:00',
            'client' => 'Karim Benali',
            'statut' => 'PAYEE',
            'montant_total' => 94.80,
        ],
    ];
}

/** Extrait le bloc <table …>…</table> du HTML rendu (null si absent). */
function extraireTableau(string $html): ?string
{
    if (preg_match('/<table class="tableau-commandes">.*?<\/table>/s', $html, $m) === 1) {
        return $m[0];
    }

    return null;
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

echo "MiniShop — Admin > Commandes : colonne Statut tenue en page\n\n";

/* ── 1. Balisage rendu : structure, colonne Statut, non-régression ── */
echo "== 1 · Vue commandes : les six colonnes annoncées, Statut en entier ==\n";
$html = rendreCommandes(commandesFixture());
$tableau = extraireTableau($html);
$check('le tableau porte la classe tableau-commandes', $tableau !== null);
$check(
    'le colgroup fixe les six colonnes dans l\'ordre (numero, date, client, statut, montant, detail)',
    $tableau !== null
        && str_contains($tableau, '<col class="col-numero"><col class="col-date"><col class="col-client">')
        && str_contains($tableau, '<col class="col-statut"><col class="col-montant"><col class="col-detail">')
);
$entetes = [];
if ($tableau !== null && preg_match_all('/<th scope="col"[^>]*>([^<]+)<\/th>/', $tableau, $m) === 6) {
    $entetes = $m[1];
}
$check(
    'les six en-têtes sont là, Statut en quatrième position (avant Montant et Détail)',
    $entetes === ['Numéro', 'Date', 'Client', 'Statut', 'Montant TTC', 'Détail'],
    implode('|', $entetes)
);
$check(
    'l\'en-tête Montant TTC porte la classe montant (aligné avec ses valeurs)',
    $tableau !== null && str_contains($tableau, '<th scope="col" class="montant">Montant TTC</th>')
);
$check(
    'chaque statut est rendu en entier dans une cellule statut (« En préparation », « Payée »)',
    $tableau !== null
        && str_contains($tableau, '<td class="statut">En préparation</td>')
        && str_contains($tableau, '<td class="statut">Payée</td>')
);
$check(
    'les montants sont en cellules montant (159,70 € et 94,80 €)',
    $tableau !== null
        && str_contains($tableau, '<td class="montant">159,70 €</td>')
        && str_contains($tableau, '<td class="montant">94,80 €</td>')
);
$check(
    'numéros, dates et clients restent affichés (le contenu long se repliera, pas le statut)',
    $tableau !== null
        && str_contains($tableau, 'CMD2026-000007')
        && str_contains($tableau, 'Alexandra de Montmorency-Beauregard')
        && str_contains($tableau, 'Karim Benali')
);
$check(
    'non-régression : le lien « Ouvrir → » de chaque commande est intact',
    $tableau !== null
        && str_contains($tableau, '<a href="/admin/commandes/7">Ouvrir →</a>')
        && str_contains($tableau, '<a href="/admin/commandes/8">Ouvrir →</a>')
);
$check(
    'non-régression : filtres et compteur toujours là (« 2 commande(s) affichée(s) », formulaire GET)',
    str_contains($html, '<form method="get" action="/admin/commandes">')
        && str_contains($html, '2 commande(s) affichée(s)')
        && str_contains($html, '<select id="statut" name="statut">')
);
$htmlVide = rendreCommandes([]);
$tableauVide = extraireTableau($htmlVide);
$check(
    'liste vide : le tableau et ses en-têtes restent rendus, sans ligne de commande',
    $tableauVide !== null && str_contains($tableauVide, '<th scope="col">Statut</th>')
        && !str_contains($tableauVide, 'Ouvrir')
);

/* ── 2. CSS : les règles qui tiennent la page ──────────────────────── */
echo "\n== 2 · Feuille du site : répartition fixe, Statut jamais coupé ==\n";
$css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/minishop.css');
$blocTableau = blocCss($css, '.tableau-commandes');
$check(
    '.tableau-commandes : largeur 100 %, colonnes à répartition fixe, bordures fusionnées',
    $blocTableau !== null && str_contains($blocTableau, 'width: 100%')
        && str_contains($blocTableau, 'table-layout: fixed') && str_contains($blocTableau, 'border-collapse: collapse'),
    (string) $blocTableau
);
$largeurs = [];
foreach (['col-numero', 'col-date', 'col-client', 'col-statut', 'col-montant', 'col-detail'] as $colonne) {
    $bloc = blocCss($css, '.tableau-commandes .' . $colonne);
    $largeur = null;
    if ($bloc !== null && preg_match('/width:\s*(\d+)%/', $bloc, $m) === 1) {
        $largeur = (int) $m[1];
    }
    $largeurs[$colonne] = $largeur;
}
$check(
    'chaque colonne a sa largeur explicite (numero 15, date 19, client 23, statut 17, montant 14, detail 12)',
    $largeurs === ['col-numero' => 15, 'col-date' => 19, 'col-client' => 23, 'col-statut' => 17, 'col-montant' => 14, 'col-detail' => 12],
    (string) json_encode($largeurs)
);
$totalLargeurs = 0;
foreach ($largeurs as $largeur) {
    if ($largeur !== null) {
        $totalLargeurs += $largeur;
    }
}
$check('les largeurs totalisent 100 % : tout tient dans la page, sans défilement', $totalLargeurs === 100, (string) $totalLargeurs);
$blocCellules = blocCss($css, ".tableau-commandes th,\n.tableau-commandes td");
if ($blocCellules === null) {
    $blocCellules = blocCss($css, '.tableau-commandes th, .tableau-commandes td');
}
$check(
    'en-têtes et cellules alignés à gauche, contenus longs repliés (overflow-wrap)',
    $blocCellules !== null && str_contains($blocCellules, 'text-align: left') && str_contains($blocCellules, 'overflow-wrap: break-word'),
    (string) $blocCellules
);
$blocStatut = blocCss($css, '.tableau-commandes .statut');
$check(
    'la colonne Statut : libellé jamais coupé (nowrap) et mis en avant (gras)',
    $blocStatut !== null && str_contains($blocStatut, 'white-space: nowrap') && str_contains($blocStatut, 'font-weight: 700'),
    (string) $blocStatut
);
$blocMontant = blocCss($css, '.tableau-commandes .montant');
$check(
    'les montants (et eux seuls) sont alignés à droite, sans retour à la ligne',
    $blocMontant !== null && str_contains($blocMontant, 'text-align: right') && str_contains($blocMontant, 'white-space: nowrap')
);
$check(
    'l\'en-tête de colonnes a le même gris que les autres tableaux du site (#f5f5f4)',
    str_contains($css, '.tableau-commandes thead th') && str_contains($css, 'background-color: #f5f5f4')
);

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Commandes admin (colonne Statut) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
