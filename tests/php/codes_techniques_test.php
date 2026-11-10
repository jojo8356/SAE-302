<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Codes techniques visibles par le client »
 * (EF-VIS-03, EF-ADM-00…10, art. L221-5).
 *
 * Signalement : des codes techniques s'affichaient partout —
 * PORT_OFFERT_A_PARTIR_DE_80.00 et LIVRAISON_STANDARD (panier,
 * récapitulatif, validation), « Tracé par trg_history_statut » et
 * « Création tracée par trg_history_creation » dans l'historique
 * des commandes, « v_etat_stock » (stocks), et les références RB-xx
 * un peu partout (RB-06, RB-11, RB-18/20, RB-16, RB-19, RB-03,
 * RB-12, RB-14…) jusque dans les CGV et les mentions légales. Deux
 * numéros affichés étaient en outre faux (suppression de catégorie
 * notée RB-14 alors que sql/02 la rattache à RB-07 ; seuil d'alerte
 * noté RB-03, règle du stock non négatif). « Varier de » (stocks)
 * avait déjà été remplacé par « Ajouter / retirer » (signalement
 * n° 8) — on le verrouille ici aussi.
 *
 * Correctif verrouillé ici : aucun code technique dans les textes
 * AFFICHÉS — vues cliente et back-office, messages d'erreur métier
 * (BusinessError), motifs de port (ParametreRepository) et
 * commentaires stockés dans l'historique des statuts (Triggers).
 * Les codes restent où ils sont utiles : commentaires de code,
 * journaux et tests — jamais à l'écran. Les intitulés humains
 * disent la même chose en français (« frais de port offerts dès
 * 80,00 € de marchandises », « Création de la commande »…).
 *
 * Toutes les vues concernées sont rendues pour de vrai (fixtures)
 * et le HTML rendu est passé au crible des motifs interdits.
 *
 * Usage :  php tests/php/codes_techniques_test.php
 *         node scripts/wasm_run.mjs tests/php/codes_techniques_test.php   (sandbox)
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Model\Data\BusinessError;
use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use App\Repository\ParametreRepository;

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

/** Motifs techniques interdits dans tout texte affiché. */
const MOTIFS_INTERDITS = '/(RB-\d|EF-[A-Z]|ENF-\d|UC-\d|trg_[a-z]|v_etat_stock|v_catalogue|PORT_OFFERT|LIVRAISON_STANDARD|Varier de|SQLSTATE|sp_[a-z]+_)/';

/** Rend une vue avec ses variables, retourne le HTML. @param array<string,mixed> $vars */
function rendre(string $vue, array $vars = []): string
{
    extract($vars);
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/' . $vue . '.php';

    return (string) ob_get_clean();
}

/** Recap panier d'un article. @return array<string,mixed> */
function recapFixture(): array
{
    return [
        'articles' => [[
            'id_produit' => 5, 'slug' => 'casque-audio-sans-fil-aura', 'nom' => 'Casque audio sans fil Aura',
            'reference' => 'CASQ-005', 'stock_disponible' => 16, 'prix_unitaire' => 154.80,
            'quantite' => 1, 'total_ligne' => 154.80, 'plafonne' => false,
        ]],
        'sous_total' => 154.80, 'frais_port' => 4.90, 'total' => 159.70,
        'port_motif' => 'livraison standard (4,90 €)',
    ];
}

/** Commande de démonstration complète pour les détails client/admin. @return array<string,mixed> */
function commandeFixture(): array
{
    return [
        'id_commande' => 7, 'numero' => 'CMD2026-000007', 'statut' => 'PAYEE',
        'date_commande' => '2026-09-28 10:15:00', 'adresse_livraison' => '12 rue de France, 06000 Nice',
        'montant_total' => 159.70, 'frais_port' => 4.90,
    ];
}

/** Historique de statuts tel que les déclencheurs l'écrivent désormais. @return list<array<string,mixed>> */
function historiqueFixture(): array
{
    return [
        ['changed_at' => '2026-09-28 10:15:00', 'new_status' => 'BROUILLON', 'changed_by_role' => 'CLIENT', 'changed_by' => 1, 'commentaire' => 'Création de la commande'],
        ['changed_at' => '2026-09-28 10:16:00', 'new_status' => 'PAYEE', 'changed_by_role' => 'CLIENT', 'changed_by' => 1, 'commentaire' => 'Changement de statut le 2026-09-28 10:16:00'],
    ];
}

/** Lignes de la commande fixture. @return list<array<string,mixed>> */
function lignesFixture(): array
{
    return [[
        'produit_nom' => 'Casque audio sans fil Aura', 'produit_reference' => 'CASQ-005',
        'prix_unitaire' => 154.80, 'quantite' => 1, 'total_ligne' => 154.80,
    ]];
}

/**
 * Passe un HTML rendu au crible : aucun motif technique, et retourne
 * le premier motif trouvé pour le diagnostic ('' si propre).
 */
function premierMotifInterdit(string $html): string
{
    if (preg_match(MOTIFS_INTERDITS, $html, $m) === 1) {
        return $m[1];
    }

    return '';
}

echo "MiniShop — Codes techniques : plus rien de technique à l'écran\n\n";

/* ── 1. Vues cliente et visiteur ───────────────────────────────────── */
echo "== 1 · Espace client et visiteur : HTML rendu sans code technique ==\n";
$client = ['nom' => 'Dupont', 'prenom' => 'Alice', 'email' => 'alice@example.com', 'telephone' => '0612345678',
    'adresse_livraison' => '12 rue de France', 'code_postal' => '06000', 'ville' => 'Nice'];
$commande = commandeFixture();
$vuesClient = [
    'panier (totaux et motif de port)' => ['panier/index', ['recap' => recapFixture()]],
    'validation de commande (récapitulatif)' => ['commande/valider', ['recap' => recapFixture(), 'client' => $client, 'erreurs' => [], 'adresse_livraison' => '12 rue de France, 06000 Nice']],
    'mes commandes' => ['commande/mes', ['commandes' => [[
        'id_commande' => 7, 'numero' => 'CMD2026-000007', 'date_commande' => '2026-09-28 10:15:00',
        'statut' => 'PAYEE', 'nb_lignes' => 1, 'montant_total' => 159.70,
    ]]]],
    'détail de commande (historique des statuts)' => ['commande/detail', ['commande' => $commande, 'lignes' => lignesFixture(), 'historique' => historiqueFixture()]],
    'confirmation d\'annulation' => ['commande/confirmer_annulation', ['commande' => $commande]],
    'fiche produit' => ['catalogue/produit', [
        'produit' => ['id_produit' => 5, 'nom' => 'Casque audio sans fil Aura', 'reference' => 'CASQ-005',
            'prix_ht' => 129.00, 'tva' => 20.00, 'prix_ttc' => 154.80, 'stock' => 16,
            'description' => 'Réduction de bruit active.', 'image_url' => 'img/aura.svg'],
        'categorie' => ['id_categorie' => 2, 'nom' => 'Audio'], 'etat_stock' => 'DISPONIBLE',
    ]],
    'mon compte' => ['compte/index', ['client' => $client, 'erreurs' => []]],
    'inscription' => ['auth/inscription', ['erreurs' => [], 'valeurs' => []]],
    'connexion admin' => ['auth/admin_connexion', ['erreurs' => [], 'email' => '']],
    'catégories (visiteur)' => ['home/categories', ['categories' => [['id_categorie' => 2, 'nom' => 'Audio', 'slug' => 'audio', 'description' => 'Casques et enceintes', 'nb_produits' => 3]]]],
];
foreach ($vuesClient as $libelle => [$vue, $vars]) {
    $html = rendre($vue, $vars);
    $motif = premierMotifInterdit($html);
    $check("vue {$libelle} : aucun code technique à l'écran", $motif === '', "motif trouvé : {$motif}");
}
foreach (['home/cgv', 'home/mentions_legales', 'errors/403', 'errors/500'] as $vueStatique) {
    $html = rendre($vueStatique);
    $motif = premierMotifInterdit($html);
    $check("vue {$vueStatique} : aucun code technique à l'écran", $motif === '', "motif trouvé : {$motif}");
}
$panierHtml = rendre('panier/index', ['recap' => recapFixture()]);
$check(
    'panier : le motif de port affiché est en français (« livraison standard (4,90 €) »)',
    str_contains($panierHtml, 'livraison standard (4,90 €)')
);
$detailHtml = rendre('commande/detail', ['commande' => $commande, 'lignes' => lignesFixture(), 'historique' => historiqueFixture()]);
$check(
    'détail : l\'historique parle français (« Création de la commande », « Historique des statuts »)',
    str_contains($detailHtml, 'Création de la commande') && str_contains($detailHtml, '<h2>Historique des statuts</h2>')
);

/* ── 2. Vues back-office ───────────────────────────────────────────── */
echo "\n== 2 · Back-office : HTML rendu sans code technique non plus ==\n";
$vuesAdmin = [
    'stocks' => ['admin/stocks', [
        'etats' => [['id_produit' => 5, 'reference' => 'CASQ-005', 'nom' => 'Casque audio sans fil Aura', 'stock' => 16, 'seuil_alerte' => 5, 'etat' => 'DISPONIBLE']],
        'valeur_inventaire' => 2451.60, 'erreurs' => [],
    ]],
    'produits (liste)' => ['admin/produits', [
        'resultat' => ['lignes' => [[
            'id_produit' => 5, 'reference' => 'CASQ-005', 'nom' => 'Casque audio sans fil Aura', 'categorie' => 'Audio',
            'prix_ht' => 129.00, 'tva' => 20.0, 'prix_ttc' => 154.80, 'stock' => 16, 'etat_stock' => 'DISPONIBLE', 'visible' => 1,
        ]], 'page' => 1, 'pages' => 1, 'total' => 1],
        'categories' => [['id_categorie' => 2, 'nom' => 'Audio']], 'q' => '', 'cat' => null,
    ]],
    'formulaire produit' => ['admin/produit_form', [
        'produit' => null, 'categories' => [['id_categorie' => 2, 'nom' => 'Audio']],
        'erreurs' => [], 'valeurs' => [], 'tva_defaut' => 20.0,
    ]],
    'commandes (liste)' => ['admin/commandes', [
        'commandes' => [[
            'id_commande' => 7, 'numero' => 'CMD2026-000007', 'date_commande' => '2026-09-28 10:15:00',
            'client' => 'Alice Dupont', 'statut' => 'PAYEE', 'montant_total' => 159.70,
        ]],
        'statuts' => ['BROUILLON', 'PAYEE'], 'filtres' => ['statut' => '', 'client' => '', 'du' => '', 'au' => '', 'montant_min' => null],
    ]],
    'détail commande admin' => ['admin/commande_detail', [
        'commande' => $commande, 'client' => ['prenom' => 'Alice', 'nom' => 'Dupont', 'email' => 'alice@example.com'],
        'lignes' => lignesFixture(), 'historique' => historiqueFixture(), 'transitions' => ['EXPEDIEE'],
    ]],
    'catégories admin' => ['admin/categories', [
        'categories' => [['id_categorie' => 2, 'nom' => 'Audio', 'slug' => 'audio', 'description' => 'Casques', 'nb_produits' => 3]],
        'erreurs' => [],
    ]],
    'tableau de bord' => ['admin/dashboard', [
        'indicateurs' => ['commandes' => 1, 'en_preparation' => 0, 'clients' => 3, 'produits' => 12, 'ca_total' => 159.70],
        'rapport' => ['par_statut' => [['statut' => 'PAYEE', 'nb_commandes' => 1, 'montant_total' => 159.70, 'montant_moyen' => 159.70]],
            'top_produits' => [['nom' => 'Casque audio sans fil Aura', 'reference' => 'CASQ-005', 'unites' => 1, 'ca' => 154.80]]],
        'alertes' => [['reference' => 'ENCH-006', 'nom' => 'Enceinte portable Boom', 'stock' => 1, 'seuil_alerte' => 3, 'etat' => 'TRES_BAS']],
    ]],
    'équipe' => ['admin/equipe', [
        'admins' => [['prenom' => 'Ada', 'nom' => 'Admin', 'email' => 'admin@minishop.fr', 'role' => 'SUPER', 'actif' => 1, 'derniere_connexion' => null, 'id_admin' => 1]],
        'erreurs' => [],
    ]],
    'confirmation suppression catégorie' => ['admin/confirmer_suppression_categorie', ['categorie' => ['id_categorie' => 2, 'nom' => 'Audio']]],
    'confirmation suppression produit' => ['admin/confirmer_suppression_produit', ['produit' => ['id_produit' => 5, 'nom' => 'Casque audio sans fil Aura', 'reference' => 'CASQ-005']]],
];
foreach ($vuesAdmin as $libelle => [$vue, $vars]) {
    $html = rendre($vue, $vars);
    $motif = premierMotifInterdit($html);
    $check("vue {$libelle} : aucun code technique à l'écran", $motif === '', "motif trouvé : {$motif}");
}
$stocksHtml = rendre('admin/stocks', $vuesAdmin['stocks'][1]);
$check(
    'stocks : le mode s\'appelle « Ajouter / retirer » (jamais « Varier de »)',
    str_contains($stocksHtml, '>Ajouter / retirer</option>') && !str_contains($stocksHtml, 'Varier de')
);

/* ── 3. Messages du moteur : erreurs métier, motifs, historique ────── */
echo "\n== 3 · Messages affichés par le moteur : français uniquement ==\n";
$messagesTechniques = [];
foreach (BusinessError::MESSAGES as $code => $message) {
    if (preg_match(MOTIFS_INTERDITS, $message) === 1) {
        $messagesTechniques[] = $code;
    }
}
$check(
    'aucun message d\'erreur métier ne contient de code technique',
    $messagesTechniques === [],
    implode(',', $messagesTechniques)
);
$check(
    'les messages gardent leur sens en français (« Le prix doit être strictement positif. »…)',
    (BusinessError::MESSAGES['RB02_PRIX_DOIT_ETRE_STRICTEMENT_POSITIF'] ?? '') === 'Le prix doit être strictement positif.'
        && (BusinessError::MESSAGES['CATEGORIE_NON_VIDE'] ?? '') === 'Cette catégorie contient des produits : réassignez-les avant suppression.'
);
$dossierCodes = sys_get_temp_dir() . '/minishop_codes_' . getmypid() . '_' . bin2hex(random_bytes(4));
$store = JsonStore::open($dossierCodes);
try {
    Seed::force($store);
    $repo = new ParametreRepository($store);
    $motifStandard = $repo->computeShipping(10.00);
    $check(
        'port standard : motif affiché en français, montant correct',
        $motifStandard['port'] === 4.90 && $motifStandard['motif'] === 'livraison standard (4,90 €)',
        (string) json_encode($motifStandard)
    );
    $motifOffert = $repo->computeShipping(100.00);
    $check(
        'port offert : motif affiché en français (« frais de port offerts dès 80,00 € de marchandises »)',
        $motifOffert['port'] === 0.0 && $motifOffert['motif'] === 'frais de port offerts dès 80,00 € de marchandises',
        (string) json_encode($motifOffert)
    );
} finally {
    $fichiersCodes = scandir($dossierCodes);
    if ($fichiersCodes === false) {
        $fichiersCodes = [];
    }
    foreach ($fichiersCodes as $fichierCode) {
        if ($fichierCode !== '.' && $fichierCode !== '..') {
            @unlink($dossierCodes . '/' . $fichierCode);
        }
    }
    @rmdir($dossierCodes);
}
$declencheurs = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Model/Data/Triggers.php');
$check(
    'les commentaires stockés dans l\'historique sont en français (création, changement de statut)',
    str_contains($declencheurs, "'commentaire' => 'Création de la commande'")
        && str_contains($declencheurs, '$commentaireTrace = \'Changement de statut le \';')
        && !str_contains($declencheurs, "'Création tracée par trg_history_creation'")
        && !str_contains($declencheurs, "'Tracé par trg_history_statut le '")
);

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Codes techniques (vues, messages, motifs, historique) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
