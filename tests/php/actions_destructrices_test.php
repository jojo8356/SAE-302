<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Annulation sans confirmation » (UC-09, EF-CLI-10).
 *
 * Signalement : un seul clic sur « Annuler cette commande » annulait
 * aussitôt la commande, sans « Êtes-vous sûr ? », alors que l'action
 * est définitive — et ce bouton (comme « Supprimer » dans l'admin)
 * avait le même style sombre par défaut que les boutons de validation.
 *
 * Correctif verrouillé ici, pour les trois actions définitives
 * (annuler une commande, supprimer un produit, supprimer une
 * catégorie) :
 *   1. le bouton de la liste/du détail n'exécute plus rien : c'est un
 *      GET vers une page de confirmation « Êtes-vous sûr ? » (aucun
 *      confirm() JavaScript — CSP sans script —, page accessible et
 *      testable) ; seul le POST historique, protégé CSRF, exécute,
 *      depuis cette page ;
 *   2. les boutons d'action définitive portent la classe
 *      .bouton-danger (rouge, public/minishop.css), visiblement
 *      distincte du bouton principal et des boutons de validation.
 * Le retour « Non, … » ramène à la page d'origine sans rien changer.
 *
 * Usage :  php tests/php/actions_destructrices_test.php
 *         node scripts/wasm_run.mjs tests/php/actions_destructrices_test.php   (sandbox)
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Model\Data\BusinessError;
use App\Model\Data\Filter;
use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use App\Model\Data\StoreInterface;
use App\Repository\CommandeRepository;

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

function magasin(): StoreInterface
{
    static $n = 0;
    $dossier = sys_get_temp_dir() . '/minishop_destructif' . getmypid() . '_' . (++$n);
    if (is_dir($dossier)) {
        $anciens = glob($dossier . '/*.json');
        if (is_array($anciens)) {
            array_map('unlink', $anciens);
        }
        @rmdir($dossier);
    }
    $store = JsonStore::open($dossier);
    Seed::force($store);
    $store->setActor(['id' => 1, 'role' => 'ADMIN', 'nom' => 'Harnais']);

    return $store;
}

function cid(StoreInterface $store, string $email): int
{
    return (int) $store->findOne('client', [Filter::eq('email', $email)])['id_client'];
}

function rendre(string $vue, array $donnees): string
{
    extract($donnees, EXTR_SKIP);
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/' . $vue . '.php';

    return (string) ob_get_clean();
}

/** Contraste WCAG d'un texte blanc sur un fond #rrggbb. */
function contrasteSurBlanc(string $hex): float
{
    $canaux = [];
    foreach ([1, 3, 5] as $debut) {
        $c = hexdec(substr($hex, $debut, 2)) / 255;
        if ($c <= 0.04045) {
            $canaux[] = $c / 12.92;
        } else {
            $canaux[] = (($c + 0.055) / 1.055) ** 2.4;
        }
    }
    $luminance = 0.2126 * $canaux[0] + 0.7152 * $canaux[1] + 0.0722 * $canaux[2];

    return 1.05 / ($luminance + 0.05);
}

/** Fond déclaré pour une classe dans la feuille du site (null si absent). */
function fondClasse(string $css, string $classe): ?string
{
    if (preg_match('/\.' . $classe . '\s*\{[^}]*background-color:\s*(#[0-9a-fA-F]{6})/', $css, $m) === 1) {
        return strtolower($m[1]);
    }

    return null;
}

$commandeFixture = [
    'id_commande' => 42,
    'numero' => 'CMD2026-000042',
    'date_commande' => '2026-01-15 18:15:00',
    'statut' => 'PAYEE',
    'adresse_livraison' => '12 rue de France, 06000 Nice',
    'frais_port' => 0.0,
    'montant_total' => 154.80,
];

echo "MiniShop — Actions définitives : confirmation « Êtes-vous sûr ? » + style danger\n\n";

/* ── 1. Détail commande : le clic ne suffit plus ──────────────────── */
echo "== 1 · Détail d'une commande : « Annuler cette commande » mène à la confirmation ==\n";
$detail = rendre('commande/detail', [
    'commande' => $commandeFixture,
    'lignes' => [['produit_nom' => 'Casque audio', 'produit_reference' => 'CASQ-005', 'prix_unitaire' => 154.80, 'quantite' => 1, 'total_ligne' => 154.80]],
    'historique' => [],
]);
$check('le bouton d\'annulation existe', str_contains($detail, 'Annuler cette commande'));
$check(
    'c\'est un GET vers la page de confirmation (aucun effet de bord)',
    str_contains($detail, '<form method="get" action="/mes-commandes/42/annulation/confirmation">')
);
$check(
    'plus aucun POST direct vers /annulation depuis le détail',
    !str_contains($detail, '<form method="post" action="/mes-commandes/42/annulation">')
);
$check(
    'le bouton porte le style danger',
    str_contains($detail, '<button type="submit" class="bouton-danger">Annuler cette commande</button>')
);

/* ── 2. Page de confirmation d'annulation ─────────────────────────── */
echo "\n== 2 · Confirmation d'annulation : « Êtes-vous sûr ? » ==\n";
$confirmation = rendre('commande/confirmer_annulation', ['commande' => $commandeFixture]);
$check('titre « Annuler cette commande ? »', str_contains($confirmation, '<h1>Annuler cette commande ?</h1>'));
$check('la question « Êtes-vous sûr » est posée', str_contains($confirmation, 'Êtes-vous sûr de vouloir annuler la commande'));
$check('le caractère définitif est annoncé', str_contains($confirmation, 'Cette action est définitive.'));
$check('la commande concernée est identifiée (numéro)', str_contains($confirmation, 'CMD2026-000042'));
$check(
    'le POST d\'exécution (historique, CSRF) part de cette page',
    str_contains($confirmation, '<form method="post" action="/mes-commandes/42/annulation">')
        && str_contains($confirmation, 'name="_csrf"')
);
$check(
    'le bouton d\'exécution est un danger explicite',
    str_contains($confirmation, '<button type="submit" class="bouton-danger">Oui, annuler définitivement</button>')
);
$check(
    'porte de sortie « Non, revenir à ma commande »',
    str_contains($confirmation, '<a href="/mes-commandes/42">Non, revenir à ma commande</a>')
);

/* ── 3. Admin produits et catégories : même traitement ────────────── */
echo "\n== 3 · Admin : « Supprimer » exige la même confirmation, en style danger ==\n";
$listeProduits = rendre('admin/produits', [
    'resultat' => [
        'lignes' => [[
            'id_produit' => 7, 'reference' => 'CASQ-005', 'nom' => 'Casque audio', 'categorie' => 'Audio',
            'prix_ht' => 129.00, 'tva' => 20.00, 'prix_ttc' => 154.80, 'stock' => 16, 'etat_stock' => 'DISPONIBLE', 'visible' => 1,
        ]],
        'page' => 1, 'pages' => 1, 'total' => 1,
    ],
    'categories' => [['id_categorie' => 2, 'nom' => 'Audio']],
    'q' => '',
    'cat' => null,
]);
$check(
    'produits : « Supprimer » est un GET vers la confirmation, en danger',
    str_contains($listeProduits, '<form method="get" action="/admin/produits/7/suppression/confirmation">')
        && str_contains($listeProduits, '<button type="submit" class="bouton-danger">Supprimer</button>')
);
$check(
    'produits : plus de POST direct de suppression dans la liste',
    !str_contains($listeProduits, '<form method="post" action="/admin/produits/7/suppression">')
);
$confirmationProduit = rendre('admin/confirmer_suppression_produit', [
    'produit' => ['id_produit' => 7, 'reference' => 'CASQ-005', 'nom' => 'Casque audio'],
]);
$check(
    'confirmation produit : question + définitif + produit identifié',
    str_contains($confirmationProduit, 'Êtes-vous sûr de vouloir supprimer le produit')
        && str_contains($confirmationProduit, 'Cette action est définitive.')
        && str_contains($confirmationProduit, 'Casque audio')
);
$check(
    'confirmation produit : POST CSRF + bouton danger + retour',
    str_contains($confirmationProduit, '<form method="post" action="/admin/produits/7/suppression">')
        && str_contains($confirmationProduit, 'name="_csrf"')
        && str_contains($confirmationProduit, '<button type="submit" class="bouton-danger">Oui, supprimer définitivement</button>')
        && str_contains($confirmationProduit, '<a href="/admin/produits">Non, revenir à la liste des produits</a>')
);

$listeCategories = rendre('admin/categories', [
    'categories' => [['id_categorie' => 5, 'nom' => 'Multimédia', 'slug' => 'multimedia', 'nb_produits' => 0, 'description' => 'Image et son']],
    'erreurs' => [],
]);
$check(
    'catégories : « Supprimer » est un GET vers la confirmation, en danger',
    str_contains($listeCategories, '<form method="get" action="/admin/categories/5/suppression/confirmation">')
        && str_contains($listeCategories, '<button type="submit" class="bouton-danger">Supprimer</button>')
);
$check(
    'catégories : plus de POST direct de suppression dans la liste',
    !str_contains($listeCategories, '<form method="post" action="/admin/categories/5/suppression">')
);
$confirmationCategorie = rendre('admin/confirmer_suppression_categorie', [
    'categorie' => ['id_categorie' => 5, 'nom' => 'Multimédia'],
]);
$check(
    'confirmation catégorie : question + définitif + catégorie identifiée',
    str_contains($confirmationCategorie, 'Êtes-vous sûr de vouloir supprimer la catégorie')
        && str_contains($confirmationCategorie, 'Cette action est définitive.')
        && str_contains($confirmationCategorie, 'Multimédia')
);
$check(
    'confirmation catégorie : POST CSRF + bouton danger + retour',
    str_contains($confirmationCategorie, '<form method="post" action="/admin/categories/5/suppression">')
        && str_contains($confirmationCategorie, 'name="_csrf"')
        && str_contains($confirmationCategorie, '<button type="submit" class="bouton-danger">Oui, supprimer définitivement</button>')
        && str_contains($confirmationCategorie, '<a href="/admin/categories">Non, revenir à la liste des catégories</a>')
);

/* ── 4. Style danger : distinct, lisible (contraste AA) ───────────── */
echo "\n== 4 · Feuille de style : le danger se distingue des validations ==\n";
$css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/minishop.css');
$fondDanger = fondClasse($css, 'bouton-danger');
$fondPrincipal = fondClasse($css, 'bouton-principal');
$check('.bouton-danger existe dans l\'unique feuille du site', $fondDanger !== null);
$check('fond danger = rouge #b91c1c', $fondDanger === '#b91c1c', (string) $fondDanger);
$check('texte blanc sur le danger', preg_match('/\.bouton-danger\s*\{[^}]*color:\s*#ffffff/', $css) === 1);
$detailContraste = 'absent';
if ($fondDanger !== null) {
    $detailContraste = (string) round(contrasteSurBlanc($fondDanger), 2);
}
$check(
    'contraste blanc/fond danger ≥ 4,5:1 (WCAG AA)',
    $fondDanger !== null && contrasteSurBlanc($fondDanger) >= 4.5,
    $detailContraste
);
$check(
    'fond danger distinct du bouton principal (orange) et du noir par défaut',
    $fondDanger !== null && $fondPrincipal !== null && $fondDanger !== $fondPrincipal && $fondDanger !== '#000000',
    'danger=' . (string) $fondDanger . ' principal=' . (string) $fondPrincipal
);
$check(
    'états hover et focus-visible du danger (clavier)',
    str_contains($css, '.bouton-danger:hover') && str_contains($css, '.bouton-danger:focus-visible')
);
$check(
    'aucun confirm() JavaScript ni gestionnaire inline (CSP sans script)',
    !str_contains($confirmation, 'confirm(') && !str_contains($confirmation, 'onclick')
        && !str_contains($detail, 'onclick') && !str_contains($listeProduits, 'onclick')
);

/* ── 5. Branchements : routes et contrôleurs ──────────────────────── */
echo "\n== 5 · Branchements : les confirmations sont routées, les POST inchangés ==\n";
$racine = dirname(__DIR__, 2);
$routes = (string) file_get_contents($racine . '/public/index.php');
$check(
    'GET annulation/confirmation routé (client)',
    str_contains($routes, "get('/mes-commandes/{id}/annulation/confirmation', [CommandeController::class, 'confirmerAnnulation'], 'client')")
);
$check(
    'POST annulation d\'exécution conservé',
    str_contains($routes, "post('/mes-commandes/{id}/annulation', [CommandeController::class, 'annuler'], 'client')")
);
$check(
    'GET suppression/confirmation routés (produit et catégorie, admin)',
    str_contains($routes, "get('/admin/produits/{id}/suppression/confirmation', [ProduitAdminController::class, 'confirmerSuppression'], 'admin')")
        && str_contains($routes, "get('/admin/categories/{id}/suppression/confirmation', [CategorieAdminController::class, 'confirmerSuppression'], 'admin')")
);
$check(
    'POST de suppression d\'exécution conservés',
    str_contains($routes, "post('/admin/produits/{id}/suppression', [ProduitAdminController::class, 'supprimer'], 'admin')")
        && str_contains($routes, "post('/admin/categories/{id}/suppression', [CategorieAdminController::class, 'supprimer'], 'admin')")
);
$controleurCommande = (string) file_get_contents($racine . '/app/Controller/CommandeController.php');
$check(
    'CommandeController::confirmerAnnulation contrôle appartenance et annulabilité',
    str_contains($controleurCommande, 'function confirmerAnnulation')
        && str_contains($controleurCommande, 'commandeDuClient')
        && str_contains($controleurCommande, "in_array(\$commande['statut'], ['BROUILLON', 'EN_PREPARATION', 'PAYEE'], true)")
);

/* ── 6. L'exécution reste possible — une seule fois ───────────────── */
echo "\n== 6 · Métier inchangé : l'annulation exécutée reste définitive ==\n";
$store = magasin();
$repo = CommandeRepository::for($store);
$creation = $repo->createOrder(cid($store, 'alice@example.com'), '12 rue de France, 06000 Nice');
$repo->cancelOrder((int) $creation['id_commande'], cid($store, 'alice@example.com'));
$check(
    'après le POST confirmé : statut ANNULEE',
    $repo->find((int) $creation['id_commande'])['statut'] === 'ANNULEE'
);
$codeRefus = null;
try {
    $repo->cancelOrder((int) $creation['id_commande'], cid($store, 'alice@example.com'));
} catch (BusinessError $e) {
    $codeRefus = $e->businessCode;
}
$check(
    'seconde annulation refusée (COMMANDE_NON_ANNULABLE) : pas de retour possible',
    $codeRefus === 'COMMANDE_NON_ANNULABLE',
    (string) $codeRefus
);

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Actions définitives : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
