<?php

declare(strict_types=1);

/**
 * MiniShop — régression « CGV article 4 : annulation ≠ rétractation »
 * (EF-GEN-05, EF-CLI-10, art. L221-18 du code de la consommation).
 *
 * Signalement : l'article 4 des CGV s'intitulait « Droit de
 * rétractation » et disait « Conformément au droit de rétractation,
 * le client peut annuler sa commande tant qu'elle n'a pas été
 * expédiée ». C'est juridiquement faux : pour un achat à distance,
 * le droit de rétractation (art. L221-18) court 14 jours à compter
 * de la RÉCEPTION du bien. Ce que MiniShop propose avant l'expédition
 * est une annulation avant expédition — une facilité de la boutique,
 * pas le droit légal. La même phrase fautive figurait sous le bouton
 * d'annulation du détail de commande, et la page Rétractation
 * juxtaposait les deux sans les distinguer nettement.
 *
 * Correctif verrouillé ici (rédactionnel, la règle métier ne
 * change pas) :
 *  - CGV, article 4 renommé « Annulation avant expédition », qui
 *    décrit le mécanisme réel (avant expédition, stock restitué,
 *    port compris soldé), puis distingue explicitement le droit de
 *    rétractation légal (14 jours à compter de la réception,
 *    art. L221-18) et rappelle qu'aucune rétractation ni aucun
 *    retour n'est mis en œuvre sur cette maquette pédagogique ;
 *  - détail de commande : « annulation avant expédition, CGV art. 4 » ;
 *  - page Rétractation : le droit légal d'abord (14 jours après
 *    réception), l'annulation avant expédition ensuite, présentée
 *    comme une facilité qui « ne se confond pas » avec ce droit.
 * Et la règle décrite reste la règle appliquée : annulable tant que
 * ce n'est pas expédié, refusée ensuite (COMMANDE_NON_ANNULABLE).
 *
 * Usage :  php tests/php/cgv_retractation_test.php
 *         node scripts/wasm_run.mjs tests/php/cgv_retractation_test.php   (sandbox)
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Model\Data\BusinessError;
use App\Model\Data\Filter;
use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
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

/** Rend une vue avec ses variables, retourne le texte visible. @param array<string,mixed> $vars */
function texteRendu(string $vue, array $vars = []): string
{
    extract($vars);
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/' . $vue . '.php';
    $html = (string) ob_get_clean();
    $texte = strip_tags($html);
    $texte = html_entity_decode($texte, ENT_QUOTES, 'UTF-8');

    return (string) preg_replace('/\s+/u', ' ', $texte);
}

echo "MiniShop — CGV article 4 : l'annulation avant expédition ne s'appelle plus rétractation\n\n";

/* ── 1. CGV : article 4 renommé et droit rappelé ───────────────────── */
echo "== 1 · CGV : « Annulation avant expédition », droit de rétractation distingué ==\n";
$cgv = texteRendu('home/cgv');
$check(
    "l'article 4 s'intitule « Annulation avant expédition »",
    str_contains($cgv, 'Article 4 — Annulation avant expédition')
);
$check(
    "l'ancien titre « Article 4 — Droit de rétractation » a disparu",
    !str_contains($cgv, 'Article 4 — Droit de rétractation')
);
$check(
    "la phrase fautive « Conformément au droit de rétractation, le client peut annuler » a disparu",
    !str_contains($cgv, 'Conformément au droit de rétractation')
);
$check(
    "l'article 4 décrit le mécanisme réel (pas encore expédiée, stock restitué, port compris soldé)",
    str_contains($cgv, "n'a pas été expédiée") && str_contains($cgv, 'stock est restitué')
        && str_contains($cgv, 'montant total (port compris) est soldé')
);
$check(
    "la distinction est écrite noir sur blanc (« distincte du droit de rétractation »)",
    str_contains($cgv, 'est distincte du droit de rétractation')
);
$check(
    "le vrai droit est rappelé avec son point de départ légal (14 jours à compter de la réception, L221-18)",
    str_contains($cgv, '14 jours à compter de la réception') && str_contains($cgv, 'L221-18')
);
$check(
    "l'article renvoie à la page Rétractation et assume le statut de maquette (aucun retour traité)",
    str_contains($cgv, "aucune procédure de rétractation ni de retour de marchandise n'est mise en œuvre")
);

/* ── 2. Page Rétractation : le droit d'abord, la facilité ensuite ──── */
echo "\n== 2 · Page Rétractation : droit légal et annulation ne se confondent plus ==\n";
$retractation = texteRendu('home/retractation');
$check(
    "le droit légal est énoncé avec son délai et son point de départ (14 jours, réception, L221-18)",
    str_contains($retractation, '14 jours') && str_contains($retractation, 'à compter de la réception')
        && str_contains($retractation, 'L221-18')
);
$check(
    "la facilité du site porte son vrai nom (« annulation avant expédition »)",
    str_contains($retractation, "Concrètement, sur MiniShop : l'annulation avant expédition")
);
$check(
    "la page dit explicitement que l'annulation « ne se confond pas avec le droit de rétractation »",
    str_contains($retractation, 'ne se confond pas avec le droit de rétractation')
);
$check(
    "après expédition, la page rattache le délai de 14 jours à la réception (pas à l'annulation)",
    str_contains($retractation, "droit de rétractation de 14 jours à compter de la réception qui s'appliquerait")
);
$check(
    "la page renvoie toujours aux CGV (article 4) et à Mes commandes",
    str_contains($retractation, "l'article 4 des Conditions générales de vente")
);

/* ── 3. Détail de commande et aide : même vocabulaire partout ──────── */
echo "\n== 3 · Détail de commande, aide : le bouton d'annulation dit la même chose ==\n";
$commande = [
    'id_commande' => 7, 'numero' => 'CMD2026-000007', 'statut' => 'PAYEE',
    'date_commande' => '2026-09-28 10:15:00', 'adresse_livraison' => '12 rue de France, 06000 Nice',
    'montant_total' => 159.70, 'frais_port' => 4.90,
];
$lignes = [[
    'produit_nom' => 'Casque audio sans fil Aura', 'produit_reference' => 'CASQ-005',
    'prix_unitaire' => 154.80, 'quantite' => 1, 'total_ligne' => 154.80,
]];
$detail = texteRendu('commande/detail', ['commande' => $commande, 'lignes' => $lignes, 'historique' => []]);
$check(
    "sous le bouton d'annulation : « annulation avant expédition, CGV art. 4 »",
    str_contains($detail, "annulation avant expédition, CGV art. 4")
);
$check(
    "la phrase fautive « Conformément au droit de rétractation » a disparu du détail",
    !str_contains($detail, 'Conformément au droit de rétractation')
);
$aide = texteRendu('home/aide');
$check(
    "l'aide raconte la même règle (annuler tant que ce n'est pas expédié, renvoi Rétractation)",
    str_contains($aide, "Tant qu'elle n'est pas expédiée, vous pouvez l'annuler")
        && !str_contains($aide, 'Conformément au droit de rétractation')
);
$livraison = texteRendu('home/livraison');
$check(
    "la page Livraison reste cohérente (annulation possible tant que ce n'est pas expédié)",
    str_contains($livraison, "Tant que la commande n'est pas expédiée, vous pouvez encore")
);

/* ── 4. La règle décrite est la règle appliquée ────────────────────── */
echo "\n== 4 · Comportement inchangé : annulable avant expédition, refusé après ==\n";
$dossier = sys_get_temp_dir() . '/minishop_cgv' . getmypid();
$store = JsonStore::open($dossier);
Seed::force($store);
$store->setActor(['id' => 1, 'role' => 'ADMIN', 'nom' => 'Harnais']);
$repo = new CommandeRepository($store);
$idAlice = (int) $store->findOne('client', [Filter::eq('email', 'alice@example.com')])['id_client'];
$idCable = (int) $store->findOne('produit', [Filter::eq('reference', 'CABLE-009')])['id_produit'];
$stockAvant = (int) $store->find('produit', $idCable)['stock'];

$commandeOk = $repo->createOrderFromBasket($idAlice, '12 rue de France, 06000 Nice', [['id_produit' => $idCable, 'quantite' => 2]], true);
$resultat = $repo->cancelOrder((int) $commandeOk['id_commande'], $idAlice);
$stockApres = (int) $store->find('produit', $idCable)['stock'];
$check(
    "commande non expédiée : annulation acceptée, stock restitué, montant soldé",
    $resultat['unites_rendues'] === 2 && $resultat['montant_rembourse'] > 0.0 && $stockApres === $stockAvant,
    (string) json_encode($resultat)
);

$commandeExpediee = $repo->createOrderFromBasket($idAlice, '12 rue de France, 06000 Nice', [['id_produit' => $idCable, 'quantite' => 1]], true);
$repo->updateOrderStatus((int) $commandeExpediee['id_commande'], 'EXPEDIEE', null, 1, 'ADMIN');
$refus = '';
try {
    $repo->cancelOrder((int) $commandeExpediee['id_commande'], $idAlice);
} catch (BusinessError $e) {
    $refus = $e->businessCode;
}
$check(
    "commande expédiée : annulation refusée (COMMANDE_NON_ANNULABLE) — l'article 4 ne promet rien de plus",
    $refus === 'COMMANDE_NON_ANNULABLE',
    $refus
);
$check(
    "le message du refus parle d'expédition, pas de rétractation",
    (BusinessError::MESSAGES['COMMANDE_NON_ANNULABLE'] ?? '') === 'Cette commande ne peut plus être annulée (déjà expédiée/livrée).'
);

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " CGV article 4 (annulation avant expédition, pas rétractation) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
