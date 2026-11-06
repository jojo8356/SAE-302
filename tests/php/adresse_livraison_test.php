<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Adresse de livraison incomplète »
 * (page « Valider ma commande », UC-07, EF-CLI-07).
 *
 * Signalement : le champ « Adresse de livraison complète » était
 * prérempli avec « 12 rue de France » seulement — la simple rue du
 * profil — sans son code postal ni sa ville (06000 Nice), et la
 * commande enregistrait cette adresse incomplète telle quelle.
 *
 * Cause : le profil client stocke rue, code postal et ville dans
 * trois colonnes séparées (EF-CLI-01), la commande n'enregistre
 * qu'une seule chaîne (commande.adresse_livraison), et la vue ne
 * préremplissait que la première colonne.
 *
 * Correctif verrouillé ici : ClientRepository::adresseLivraisonComplete()
 * compose « rue, CP Ville » depuis le profil ; CommandeController passe
 * cette adresse complète à la vue, qui la préremplit. L'internaute
 * peut toujours la modifier avant de confirmer, mais ce qui part par
 * défaut — et donc ce que la commande enregistre — est l'adresse
 * complète du profil.
 *
 * Suite volontairement statique (données du seed, magasin temporaire) :
 * pas d'E2E navigateur pour ce signalement.
 *
 * Usage :  php tests/php/adresse_livraison_test.php
 *         node scripts/wasm_run.mjs tests/php/adresse_livraison_test.php   (sandbox)
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Model\Data\Filter;
use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use App\Model\Data\StoreInterface;
use App\Repository\ClientRepository;
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

/** Magasin temporaire seedé (jamais data/minishop livré). */
function magasin(): StoreInterface
{
    static $n = 0;
    $dossier = sys_get_temp_dir() . '/minishop_adresse' . getmypid() . '_' . (++$n);
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

function pid(StoreInterface $store, string $reference): int
{
    return (int) $store->findOne('produit', [Filter::eq('reference', $reference)])['id_produit'];
}

/**
 * Rend la vue « Valider ma commande » comme CommandeController::formulaire :
 * récapitulatif figé en fixture, client du seed, adresse composée du profil.
 *
 * @param array<string,mixed> $client
 */
function rendreValider(array $client, string $adresse_livraison): string
{
    $recap = [
        'articles' => [
            ['nom' => 'Casque audio', 'reference' => 'CASQ-005', 'prix_unitaire' => 154.80, 'quantite' => 1, 'total_ligne' => 154.80],
        ],
        'sous_total' => 154.80,
        'frais_port' => 0.0,
        'total' => 154.80,
        'port_motif' => 'offerts dès 80 €',
    ];
    $erreurs = [];

    ob_start();
    include dirname(__DIR__, 2) . '/app/View/commande/valider.php';

    return (string) ob_get_clean();
}

/** Valeur de l'input #adresse dans le HTML rendu (null si absent). */
function valeurChampAdresse(string $html): ?string
{
    $balise = null;
    if (preg_match('/<input[^>]*id="adresse"[^>]*>/', $html, $m) === 1) {
        $balise = $m[0];
    }
    if ($balise === null) {
        return null;
    }
    if (preg_match('/value="([^"]*)"/', $balise, $m) === 1) {
        return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }

    return null;
}

echo "MiniShop — Adresse de livraison complète (préremplissage commande)\n\n";

/* ── 1. Composition depuis le profil : le cas signalé ─────────────── */
echo "== 1 · Composition « rue, CP Ville » (cas signalé : 12 rue de France, 06000 Nice) ==\n";
$check(
    'profil complet → rue, code postal, ville',
    ClientRepository::adresseLivraisonComplete([
        'adresse_livraison' => '12 rue de France', 'code_postal' => '06000', 'ville' => 'Nice',
    ]) === '12 rue de France, 06000 Nice',
    ClientRepository::adresseLivraisonComplete(['adresse_livraison' => '12 rue de France', 'code_postal' => '06000', 'ville' => 'Nice'])
);
$check(
    'code postal seul avec la ville (sans rue) → « CP Ville »',
    ClientRepository::adresseLivraisonComplete([
        'adresse_livraison' => null, 'code_postal' => '75011', 'ville' => 'Paris',
    ]) === '75011 Paris'
);
$check(
    'rue seule → rue, sans virgule orpheline',
    ClientRepository::adresseLivraisonComplete([
        'adresse_livraison' => '3 impasse des Oliviers', 'code_postal' => null, 'ville' => null,
    ]) === '3 impasse des Oliviers'
);
$check(
    'ville seule → ville, sans espace de tête',
    ClientRepository::adresseLivraisonComplete([
        'adresse_livraison' => '', 'code_postal' => '', 'ville' => 'Lyon',
    ]) === 'Lyon'
);
$check(
    'profil sans adresse (champs null) → chaîne vide',
    ClientRepository::adresseLivraisonComplete([
        'adresse_livraison' => null, 'code_postal' => null, 'ville' => null,
    ]) === ''
);
$check(
    'profil sans adresse (clés absentes) → chaîne vide',
    ClientRepository::adresseLivraisonComplete([]) === ''
);
$check(
    'espaces superflus nettoyés, pas de double espace ni de virgule finale',
    ClientRepository::adresseLivraisonComplete([
        'adresse_livraison' => '  12 rue de France  ', 'code_postal' => ' 06000 ', 'ville' => ' Nice ',
    ]) === '12 rue de France, 06000 Nice',
    ClientRepository::adresseLivraisonComplete(['adresse_livraison' => '  12 rue de France  ', 'code_postal' => ' 06000 ', 'ville' => ' Nice '])
);

/* ── 2. Données du seed : le profil qui a déclenché le signalement ── */
echo "\n== 2 · Seed : le profil d'Alice (12 rue de France / 06000 / Nice) ==\n";
$store = magasin();
$clients = new ClientRepository($store);
$alice = $clients->find(cid($store, 'alice@example.com'));
$check('Alice retrouvée dans le seed', $alice !== null && ($alice['adresse_livraison'] ?? null) === '12 rue de France');
$composeeAlice = ClientRepository::adresseLivraisonComplete($alice ?? []);
$check(
    'adresse composée d\'Alice = « 12 rue de France, 06000 Nice »',
    $composeeAlice === '12 rue de France, 06000 Nice',
    $composeeAlice
);
$bruno = $clients->find(cid($store, 'bruno@example.com'));
$check(
    'adresse composée de Bruno = « 5 avenue Jean Médecin, 06000 Nice »',
    ClientRepository::adresseLivraisonComplete($bruno ?? []) === '5 avenue Jean Médecin, 06000 Nice',
    ClientRepository::adresseLivraisonComplete($bruno ?? [])
);
$carla = $clients->find(cid($store, 'carla@example.com'));
$check(
    'adresse composée de Carla = « 8 rue Papin, 06300 Nice »',
    ClientRepository::adresseLivraisonComplete($carla ?? []) === '8 rue Papin, 06300 Nice',
    ClientRepository::adresseLivraisonComplete($carla ?? [])
);

/* ── 3. Rendu réel de la page « Valider ma commande » ─────────────── */
echo "\n== 3 · Vue commande/valider : le champ est prérempli avec l'adresse complète ==\n";
$html = rendreValider($alice ?? [], $composeeAlice);
$valeur = valeurChampAdresse($html);
$check('le champ #adresse existe dans le formulaire', $valeur !== null);
$check(
    'prérempli avec « 12 rue de France, 06000 Nice »',
    $valeur === '12 rue de France, 06000 Nice',
    (string) $valeur
);
$check(
    'pas le seul « 12 rue de France » (ancien comportement)',
    $valeur !== '12 rue de France'
);
$check(
    'le champ garde son libellé « Adresse de livraison complète »',
    str_contains($html, 'Adresse de livraison complète')
);
$check(
    'le champ reste modifiable et requis (name=adresse, required)',
    str_contains($html, 'name="adresse"') && str_contains($html, 'required')
);
$htmlVide = rendreValider([], '');
$check(
    'profil sans adresse → champ vide (placeholder, pas de texte inventé)',
    valeurChampAdresse($htmlVide) === '',
    (string) valeurChampAdresse($htmlVide)
);

/* ── 4. La commande enregistrée garde l'adresse complète ──────────── */
echo "\n== 4 · Commande créée avec l'adresse préremplie : c'est elle qui est stockée ==\n";
$store2 = magasin();
$repoCommandes = CommandeRepository::for($store2);
$idAlice = cid($store2, 'alice@example.com');
$profilAlice = (new ClientRepository($store2))->find($idAlice);
$adresseSoumise = ClientRepository::adresseLivraisonComplete($profilAlice ?? []);
$creation = $repoCommandes->createOrderFromBasket($idAlice, $adresseSoumise, [
    ['id_produit' => pid($store2, 'CASQ-005'), 'quantite' => 1],
]);
$commande = $repoCommandes->find((int) $creation['id_commande']);
$check('la commande est créée', $commande !== null);
$check(
    'adresse enregistrée = « 12 rue de France, 06000 Nice »',
    ($commande['adresse_livraison'] ?? null) === '12 rue de France, 06000 Nice',
    (string) ($commande['adresse_livraison'] ?? 'absente')
);
$check(
    'l\'adresse enregistrée contient le code postal et la ville',
    str_contains((string) ($commande['adresse_livraison'] ?? ''), '06000')
        && str_contains((string) ($commande['adresse_livraison'] ?? ''), 'Nice')
);

/* ── 5. Branchements (contrat statique) ────────────────────────────── */
echo "\n== 5 · Branchements : contrôleur → vue ==\n";
$racine = dirname(__DIR__, 2);
$controleur = (string) file_get_contents($racine . '/app/Controller/CommandeController.php');
$vue = (string) file_get_contents($racine . '/app/View/commande/valider.php');
$check(
    'CommandeController compose l\'adresse complète du profil',
    str_contains($controleur, 'ClientRepository::adresseLivraisonComplete(')
);
$check(
    'CommandeController la transmet à la vue (adresse_livraison)',
    str_contains($controleur, "'adresse_livraison' => ClientRepository::adresseLivraisonComplete(")
);
$check(
    'la vue préremplit avec l\'adresse complète transmise',
    str_contains($vue, 'e($adresse_livraison ?? \'\')')
);
$check(
    'la vue ne préremplit plus avec la seule rue du profil',
    !str_contains($vue, "e(\$client['adresse_livraison'] ?? '')")
);
$check(
    'le profil garde ses trois champs distincts (compte, inchangé)',
    str_contains((string) file_get_contents($racine . '/app/View/compte/index.php'), 'name="code_postal"')
        && str_contains((string) file_get_contents($racine . '/app/View/compte/index.php'), 'name="ville"')
);

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Adresse de livraison : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
