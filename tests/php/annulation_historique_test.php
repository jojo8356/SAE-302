<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Annulée : 0 article, 0,00 € »
 * (EF-CLI-10, RB-18, RB-20, RB-15).
 *
 * Signalement : après une annulation, la commande affichait
 * 0 article et 0,00 €. Le 0,00 € est conforme à RB-20 (une commande
 * annulée n'a plus rien à facturer), mais le client ne voyait plus
 * ce qu'il avait commandé : l'annulation SUPPRIMAIT les lignes —
 * c'était le mécanisme de restitution du stock, et la formule
 * RB-15 (montant = somme des lignes + port, vérifiée à chaque
 * écriture) imposait alors le zéro partout.
 *
 * Correctif verrouillé ici : les lignes sont CONSERVÉES comme
 * historique (noms, prix figés, quantités), le stock est restitué
 * explicitement ligne à ligne (RB-18), les montants de facturation
 * sont soldés à 0 (RB-20, inchangé) et le total d'origine (port
 * compris) est mémorisé dans montant_avant_annulation. La formule
 * RB-15 admet cet état soldé (ANNULEE et montant 0) comme seul
 * écart ; elle continue de refuser tout autre montant incohérent,
 * y compris sur une commande annulée. Côté affichage, le détail
 * (client et admin) montre les articles, le « Total de la commande
 * avant annulation » puis le « Montant soldé (annulée — rien à
 * payer) », et « Mes commandes » recompte les articles.
 *
 * Usage :  php tests/php/annulation_historique_test.php
 *         node scripts/wasm_run.mjs tests/php/annulation_historique_test.php   (sandbox)
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

/** Magasin temporaire peuplé comme la démonstration. */
function magasin(): StoreInterface
{
    $dossier = sys_get_temp_dir() . '/minishop_annul' . getmypid();
    $store = JsonStore::open($dossier);
    Seed::force($store);
    $store->setActor(['id' => 1, 'role' => 'ADMIN', 'nom' => 'Harnais']);

    return $store;
}

/** id d'un produit par sa référence. */
function pid(StoreInterface $store, string $reference): int
{
    return (int) $store->findOne('produit', [Filter::eq('reference', $reference)])['id_produit'];
}

/** id d'un client par son e-mail. */
function cid(StoreInterface $store, string $email): int
{
    return (int) $store->findOne('client', [Filter::eq('email', $email)])['id_client'];
}

/** Rend une vue et retourne son texte visible. @param array<string,mixed> $vars */
function texteRendu(string $vue, array $vars = []): string
{
    extract($vars);
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/' . $vue . '.php';
    $texte = strip_tags((string) ob_get_clean());
    $texte = html_entity_decode($texte, ENT_QUOTES, 'UTF-8');

    return (string) preg_replace('/\s+/u', ' ', $texte);
}

echo "MiniShop — Annulation : les articles restent visibles, seul le montant est soldé\n\n";

/* ── 1. Moteur : lignes conservées, stock restitué, montants soldés ── */
echo "== 1 · Annulation : historique conservé, stock exact, soldes à zéro ==\n";
$store = magasin();
$repo = CommandeRepository::for($store);
$idCarla = cid($store, 'carla@example.com');
$stockSacAvant = (int) $store->find('produit', pid($store, 'SAC-008'))['stock'];
$stockEnceinteAvant = (int) $store->find('produit', pid($store, 'ENCH-006'))['stock'];
$creation = $repo->createOrderFromBasket($idCarla, '12 rue de France, 06000 Nice', [
    ['id_produit' => pid($store, 'SAC-008'), 'quantite' => 2],
    ['id_produit' => pid($store, 'ENCH-006'), 'quantite' => 1],
], true);
$idCommande = (int) $creation['id_commande'];
$montantOrigine = (float) $store->find('commande', $idCommande)['montant_total'];
$resultat = $repo->cancelOrder($idCommande, $idCarla);
$apres = $store->find('commande', $idCommande);
$lignesApres = $repo->lignes($idCommande);
$check(
    "les 2 lignes sont conservées (noms et prix figés lisibles)",
    count($lignesApres) === 2 && $lignesApres[0]['produit_nom'] !== '' && (float) $lignesApres[0]['total_ligne'] > 0.0,
    (string) count($lignesApres)
);
$check(
    "le stock est restitué exactement, ligne à ligne (RB-18)",
    (int) $store->find('produit', pid($store, 'SAC-008'))['stock'] === $stockSacAvant
        && (int) $store->find('produit', pid($store, 'ENCH-006'))['stock'] === $stockEnceinteAvant
        && $resultat['unites_rendues'] === 3
);
$check(
    "les montants de facturation sont soldés à 0 (RB-20, inchangé)",
    $apres['statut'] === 'ANNULEE' && (float) $apres['montant_total'] === 0.0 && (float) $apres['frais_port'] === 0.0
);
$check(
    "le total d'origine (port compris) est mémorisé pour l'affichage",
    (float) ($apres['montant_avant_annulation'] ?? 0.0) === $montantOrigine
        && (float) $resultat['montant_rembourse'] === $montantOrigine,
    (string) ($apres['montant_avant_annulation'] ?? 'null')
);
$refus = '';
try {
    $repo->cancelOrder($idCommande, $idCarla);
} catch (BusinessError $e) {
    $refus = $e->businessCode;
}
$check("une seconde annulation reste refusée (COMMANDE_NON_ANNULABLE)", $refus === 'COMMANDE_NON_ANNULABLE', $refus);

/* ── 2. RB-15 tient toujours — seul l'état soldé est admis ─────────── */
echo "\n== 2 · La formule RB-15 continue de refuser les montants incohérents ==\n";
$creation2 = $repo->createOrderFromBasket($idCarla, '12 rue de France, 06000 Nice', [
    ['id_produit' => pid($store, 'CABLE-009'), 'quantite' => 1],
], true);
$codeRb15 = '';
try {
    $store->update('commande', [Filter::eq('id_commande', (int) $creation2['id_commande'])], ['montant_total' => 999.99]);
} catch (BusinessError $e) {
    $codeRb15 = $e->businessCode;
}
$check(
    "commande vivante : un montant qui ne vaut pas lignes + port est refusé (RB-15)",
    $codeRb15 === 'RB15_MONTANT_CALCULE_INTERDIT',
    $codeRb15
);
$codeRb15Annulee = '';
try {
    $store->update('commande', [Filter::eq('id_commande', $idCommande)], ['montant_total' => 50.0]);
} catch (BusinessError $e) {
    $codeRb15Annulee = $e->businessCode;
}
$check(
    "commande annulée : relever le montant soldé est refusé aussi (le 0 est verrouillé)",
    $codeRb15Annulee === 'RB15_MONTANT_CALCULE_INTERDIT',
    $codeRb15Annulee
);

/* ── 3. Affichages : le client revoit ce qu'il avait commandé ──────── */
echo "\n== 3 · Détail, Mes commandes, back-office : l'historique est lisible ==\n";
$nomLigne1 = $lignesApres[0]['produit_nom'] ?? '';
$nomLigne2 = $lignesApres[1]['produit_nom'] ?? '';
$detailClient = texteRendu('commande/detail', [
    'commande' => $apres,
    'lignes' => $lignesApres,
    'historique' => $repo->historique($idCommande),
]);
$check(
    "détail client : les articles commandés sont de nouveau là (noms, quantités, prix figés)",
    str_contains($detailClient, $nomLigne1) && $nomLigne1 !== '' && str_contains($detailClient, $nomLigne2) && $nomLigne2 !== ''
);
$check(
    "détail client : « Total de la commande avant annulation » puis « Montant soldé (annulée — rien à payer) »",
    str_contains($detailClient, 'Total de la commande avant annulation')
        && str_contains($detailClient, 'Montant soldé (annulée — rien à payer)')
        && str_contains($detailClient, euros($montantOrigine))
);
$ligneMes = $apres;
$ligneMes['nb_lignes'] = count($lignesApres);
$mesCommandes = texteRendu('commande/mes', ['commandes' => [$ligneMes]]);
$check(
    "Mes commandes : la commande annulée recompte ses articles (plus de « 0 article »)",
    preg_match('/Annulée\\s+' . count($lignesApres) . '\\s/u', $mesCommandes) === 1,
    substr($mesCommandes, 0, 200)
);
$client = $store->find('client', $idCarla);
$detailAdmin = texteRendu('admin/commande_detail', [
    'commande' => $apres,
    'client' => ['prenom' => (string) $client['prenom'], 'nom' => (string) $client['nom'], 'email' => (string) $client['email']],
    'lignes' => $lignesApres,
    'historique' => $repo->historique($idCommande),
    'transitions' => [],
]);
$check(
    "détail admin : même lecture (montant soldé, total avant annulation, lignes présentes)",
    str_contains($detailAdmin, 'Montant soldé') && str_contains($detailAdmin, 'Total avant annulation')
        && $nomLigne1 !== '' && str_contains($detailAdmin, $nomLigne1)
);
$vivante = $store->find('commande', (int) $creation2['id_commande']);
$detailVivante = texteRendu('commande/detail', [
    'commande' => $vivante,
    'lignes' => $repo->lignes((int) $creation2['id_commande']),
    'historique' => $repo->historique((int) $creation2['id_commande']),
]);
$check(
    "commande non annulée : l'affichage classique ne bouge pas (Frais de port, Montant total TTC)",
    str_contains($detailVivante, 'Frais de port') && str_contains($detailVivante, 'Montant total TTC')
        && !str_contains($detailVivante, 'Montant soldé')
);

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Annulation (historique conservé, montant soldé) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
