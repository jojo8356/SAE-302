<?php

declare(strict_types=1);

/**
 * MiniShop — tests unitaires (couche « pure » : helpers, filtres, panier, matrices).
 *
 * Complète la pyramide de tests du projet :
 *   - tests/php/engine_smoke.php  : briques du moteur de données ;
 *   - tests/php/run_tests.php     : règles de gestion T-01…T-29 (base court-circuitée) ;
 *   - tests/php/unit_tests.php    : CE FICHIER — unitaires du document de tests §6 ;
 *   - tests/php/features_test.php : chaque cas d'utilisation UC-01…UC-14 via les repositories ;
 *   - scripts/wasm-e2e.mjs        : parcours HTTP complets (cookies + CSRF).
 *
 * Usage :  php tests/php/unit_tests.php
 *         node scripts/wasm_run.mjs tests/php/unit_tests.php   (sandbox)
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

// Sortie bufferisée : les tests affichent au fur et à mesure, mais la session
// (ouverte par PanierSession/Auth) doit pouvoir émettre ses en-têtes sans cri
// « headers already sent » — purement cosmétique pour un harnais CLI.
ob_start();

use App\Model\Data\BusinessError;
use App\Model\Data\Filter;
use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use App\Model\Data\StoreInterface;
use App\Model\Data\Text;
use App\Model\Data\Triggers;
use App\Model\Data\Views;
use App\Model\PanierSession;
use App\Repository\ParametreRepository;
use App\Repository\ProduitRepository;
use App\Security\Csrf;

// en CLI, pas d'en-têtes de cookie de session (tests silencieux)
if (PHP_SAPI !== 'wasm' && !headers_sent()) {
    ini_set('session.use_cookies', '0');
}
\App\Security\Auth::demarrer();

$echecs = 0;
$tests = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$echecs, &$tests): void {
    ++$tests;
    echo ($ok ? '  ✅ ' : '  ❌ ') . $label . ($ok || $detail === '' ? '' : " — {$detail}") . "\n";
    if (!$ok) {
        ++$echecs;
    }
};
$attenduCode = static function (string $code, callable $travail) use (&$echecs, &$tests): bool {
    ++$tests;
    try {
        $travail();
        echo "  ❌ {$code} non levé\n";
        ++$echecs;

        return false;
    } catch (BusinessError $e) {
        $ok = $e->businessCode === $code;
        echo ($ok ? '  ✅ ' : '  ❌ ') . "refus {$code}" . ($ok ? '' : ' (obtenu ' . $e->businessCode . ')') . "\n";
        if (!$ok) {
            ++$echecs;
        }

        return $ok;
    }
};

/** Magasin neuf (seed complet) pour les tests needing data. */
function magasin(): StoreInterface
{
    static $n = 0;
    $dossier = sys_get_temp_dir() . '/minishop_u' . getmypid() . '_' . (++$n);
    if (is_dir($dossier)) {
        array_map('unlink', glob($dossier . '/*.json') ?: []);
        @rmdir($dossier);
    }
    $store = JsonStore::open($dossier);
    Seed::force($store);

    return $store;
}

echo "MiniShop — tests unitaires (document de tests §6)\n\n";

// ============================================================ 1. Text::fold
echo "1. Text — pliage collation (utf8mb4_unicode_ci)\n";
{
    $check('fold(null) / fold("") → ""', Text::fold(null) === '' && Text::fold('') === '');
    $check('fold insensible casse', Text::fold('ÉCRAN') === Text::fold('écran'));
    $check('fold plie les accents latins', Text::fold('ÀÇÉÈÊËÎÏÔÖÙÜÿ') === 'aceeeeiioouuy');
    $check('fold plie les ligatures', Text::fold('œuvre Æther straße') === 'oeuvre aether strasse');
    $check('fold idempotent', Text::fold(Text::fold('Çà et Là Œuf')) === Text::fold('Çà et Là Œuf'));
    $check('fold préserve les chiffres/ponctuation', Text::fold('Cable USB-C 2 m (renforcé)') === 'cable usb-c 2 m (renforce)');
}

// ============================================================ 2. Text::slug
echo "\n2. Text::slug (trigger trg_categorie_slug)\n";
{
    $check('slug accents + espaces', Text::slug('Écran 27 pouces') === 'ecran-27-pouces');
    $check('slug ponctuation → tiret unique', Text::slug('Objets connectés: santé & maison !') === 'objets-connectes-sante-maison');
    $check('slug bornes propres', Text::slug('  --Casque (sans fil)--  ') === 'casque-sans-fil');
    $check('slug vide', Text::slug('???') === '');
    $check('slug lettres réservées', Text::slug('PC Portable Zen-14 "Turbo"') === 'pc-portable-zen-14-turbo');
}

// ============================================================ 3. Text::compare
echo "\n3. Text::compare (tri collation)\n";
{
    $check('null avant tout', Text::compare(null, 'a') < 0 && Text::compare('a', null) > 0 && Text::compare(null, null) === 0);
    $check('écran == ECRAN', Text::compare('écran', 'ECRAN') === 0);
    $check('café < czar', Text::compare('café', 'czar') < 0 && Text::compare('czar', 'café') > 0);
    $check('Bravo > alpha (insensible casse)', Text::compare('Bravo', 'alpha') === 1);
}

// ============================================================ 4. helpers vues
echo "\n4. Helpers d'affichage (SEC-02/S-02)\n";
{
    $check('e() échappe les balises', e('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;');
    $check('e() échappe apostrophes ET guillemets (ENT_QUOTES)', e("l'a \"g\"") === 'l&#039;a &quot;g&quot;');
    $check('e(null) → chaîne vide', e(null) === '' && e(0) === '0');
    $check('e() préserve l\'UTF-8', e('Écran — 27″') === 'Écran — 27″');
    $check('euros() virgule + espace milliers', euros(154.8) === '154,80 €' && euros(1234.5) === '1 234,50 €' && euros(0) === '0,00 €');
    $check('date_fr null → —', date_fr(null) === '—');
    $check('date_fr format court', preg_match('#^\d{2}/\d{2}/\d{4}$#', date_fr('2026-09-21 12:19:03')) === 1);
    $check('date_fr avec heure', date_fr('2026-09-21 12:19:03', true) === '21/09/2026 12h19');
    foreach (['BROUILLON' => 'Brouillon', 'EN_PREPARATION' => 'En préparation', 'PAYEE' => 'Payée', 'EXPEDIEE' => 'Expédiée', 'LIVREE' => 'Livrée', 'ANNULEE' => 'Annulée'] as $statut => $libelle) {
        $check("statut_libelle({$statut})", statut_libelle($statut) === $libelle);
    }
    foreach (['DISPONIBLE' => 'Disponible', 'TRES_BAS' => 'Très bas', 'RUPTURE' => 'Rupture'] as $etat => $libelle) {
        $check("etat_libelle({$etat})", etat_libelle($etat) === $libelle);
    }
}

// ============================================================ 5. CSRF
echo "\n5. Csrf (SEC-05)\n";
{
    $jeton = Csrf::jeton();
    $check('jeton stable dans la session', Csrf::jeton() === $jeton);
    $check('jeton 64 hexa (32 octets aléatoires)', preg_match('#^[a-f0-9]{64}$#', $jeton) === 1);
    $champ = Csrf::champ();
    $check('champ() porte le jeton', $champ === '<input type="hidden" name="_csrf" value="' . $jeton . '">');
}

// ============================================================ 6. matrice RB-11
echo "\n6. Matrice RB-11 — les 36 couples (§6 du document de tests)\n";
{
    $attendue = [
        'BROUILLON' => ['EN_PREPARATION', 'PAYEE', 'ANNULEE'],
        'EN_PREPARATION' => ['PAYEE', 'EXPEDIEE', 'ANNULEE'],
        'PAYEE' => ['EXPEDIEE', 'ANNULEE'],
        'EXPEDIEE' => ['LIVREE', 'ANNULEE'],
        'LIVREE' => [],
        'ANNULEE' => [],
    ];
    $check('STATUTS = les 6 états du CDC', Triggers::STATUTS === array_keys($attendue));
    $autorisees = 0;
    foreach ($attendue as $depuis => $vers) {
        $check("TRANSITIONS[{$depuis}] = " . implode(',', $vers ?: ['∅']), (Triggers::TRANSITIONS[$depuis] ?? null) === $vers);
        $autorisees += count($vers);
    }
    $check('10 transitions autorisées au total', $autorisees === 10);
    // la matrice est appliquée pour de vrai par le déclencheur (couverture croisée
    // avec T-12/T-13) : on vérifie ici la cohérence de la constante seule.
}

// ============================================================ 7. v_etat_stock
echo "\n7. Views::etatStock — formule dérivée\n";
{
    $f = static fn (int $stock, int $seuil) => Views::etatStock(['stock' => $stock, 'seuil_alerte' => $seuil]);
    $check('stock 0 → RUPTURE', $f(0, 5) === 'RUPTURE');
    $check('stock = seuil → TRES_BAS', $f(2, 2) === 'TRES_BAS');
    $check('stock < seuil → TRES_BAS', $f(1, 2) === 'TRES_BAS');
    $check('stock > seuil → DISPONIBLE', $f(3, 2) === 'DISPONIBLE');
}

// ============================================================ 8. Filter + select
echo "\n8. Filtres et spécifications de sélection\n";
{
    $store = JsonStore::open(sys_get_temp_dir() . '/minishop_filters_' . getmypid());
    // mini-table de référence : noms choisis pour tester casse/accents/tri
    $store->replaceAll('produit', [
        ['id_produit' => 1, 'nom' => 'Écran', 'prix_ht' => 179.90, 'stock' => 25, 'visible' => 1],
        ['id_produit' => 2, 'nom' => 'ecran tactile', 'prix_ht' => 99.0, 'stock' => 0, 'visible' => 1],
        ['id_produit' => 3, 'nom' => 'Clavier', 'prix_ht' => 79.0, 'stock' => 40, 'visible' => 0],
        ['id_produit' => 4, 'nom' => 'Casque', 'prix_ht' => 129.0, 'stock' => 18, 'visible' => 1],
        ['id_produit' => 5, 'nom' => 'Câble', 'prix_ht' => 12.5, 'stock' => 150, 'visible' => 1],
    ]);

    $ids = static fn (array $lignes) => array_column($lignes, 'id_produit');
    $check('eq entier', $ids($store->select('produit', ['where' => [Filter::eq('id_produit', 3)]])) === [3]);
    $check('neq', $ids($store->select('produit', ['where' => [Filter::neq('id_produit', 3)]])) === [1, 2, 4, 5]);
    $check('gt / gte / lt / lte numériques',
        $ids($store->select('produit', ['where' => [Filter::gt('stock', 25)]])) === [3, 5]
        && $ids($store->select('produit', ['where' => [Filter::gte('stock', 25)]])) === [1, 3, 5]
        && $ids($store->select('produit', ['where' => [Filter::lt('prix_ht', 79.0)]])) === [5]
        && $ids($store->select('produit', ['where' => [Filter::lte('prix_ht', 79.0)]])) === [3, 5]);
    $check('eq insensible casse/accent (collation)', $ids($store->select('produit', ['where' => [Filter::eq('nom', 'ÉCRAN')]])) === [1]);
    $check('like %…% plié', $ids($store->select('produit', ['where' => [Filter::like('nom', '%cran%')]])) === [1, 2]);
    $check('like préfixe', $ids($store->select('produit', ['where' => [Filter::like('nom', 'ca%')]])) === [4, 5]);
    $check('notLike', $ids($store->select('produit', ['where' => [Filter::notLike('nom', '%cran%')]])) === [3, 4, 5]);
    $check('in', $ids($store->select('produit', ['where' => [Filter::in('id_produit', [2, 4, 9])]])) === [2, 4]);
    $check('notIn', $ids($store->select('produit', ['where' => [Filter::notIn('id_produit', [1, 2, 3])]])) === [4, 5]);
    $check('between bornes incluses', $ids($store->select('produit', ['where' => [Filter::between('prix_ht', 79.0, 129.0)]])) === [2, 3, 4]); // 99,0 est dedans aussi
    $check('or', $ids($store->select('produit', ['where' => [Filter::or([Filter::eq('id_produit', 2), Filter::eq('id_produit', 5)])]])) === [2, 5]);
    $check('and imbriqué', $ids($store->select('produit', ['where' => [Filter::and([Filter::eq('visible', 1), Filter::gt('prix_ht', 100.0)])]])) === [1, 4]);
    $check('ordre multi-clés (prix desc puis id)', $ids($store->select('produit', ['order' => [Filter::sort('prix_ht', 'desc'), Filter::sort('id_produit')]])) === [1, 4, 2, 3, 5]);
    $check('ordre « collation » (Écran trié comme ecran)', $ids($store->select('produit', ['order' => [Filter::sort('nom')]])) === [5, 4, 3, 1, 2]); // câble, casque, clavier, écran, ecran tactile
    $check('limit + offset (page 2 de 2)', $ids($store->select('produit', ['order' => [Filter::sort('id_produit')], 'limit' => 2, 'offset' => 2])) === [3, 4]);
    $check('projection fields', $store->select('produit', ['where' => [Filter::eq('id_produit', 1)], 'fields' => ['nom']])[0] === ['nom' => 'Écran']);
    $check('count + sum + exists + all', $store->count('produit') === 5
        && abs($store->sum('produit', 'prix_ht') - 499.40) < 0.001
        && $store->exists('produit', [Filter::eq('nom', 'clavier')])
        && count($store->all('produit')) === 5);
    $check('findOne / find par clé primaire', $store->find('produit', 4)['nom'] === 'Casque' && $store->findOne('produit', [Filter::eq('nom', 'Câble')])['id_produit'] === 5);
}

// ============================================================ 9. PanierSession
echo "\n9. PanierSession (UC-06, EF-CLI-03…06, RB-18/19)\n";
{
    $store = magasin();
    $panier = new PanierSession($store, new ProduitRepository($store), new ParametreRepository($store));
    $micro = (int) $store->findOne('produit', [Filter::eq('reference', 'MICRO-007')])['id_produit']; // stock 2
    $casque = (int) $store->findOne('produit', [Filter::eq('reference', 'CASQ-005')])['id_produit'];  // stock 18
    $power = (int) $store->findOne('produit', [Filter::eq('reference', 'POWER-010')])['id_produit'];  // stock 0
    $bracelet = (int) $store->findOne('produit', [Filter::eq('reference', 'BRAC-012')])['id_produit']; // masqué
    $cable = (int) $store->findOne('produit', [Filter::eq('reference', 'CABLE-009')])['id_produit']; // 15,00 € TTC

    $check('panier vide au départ', $panier->estVide() && $panier->nombreArticles() === 0);
    $attenduCode('RB05_QUANTITE_DOIT_ETRE_SUPERIEURE_A_ZERO', static fn () => $panier->ajouter($casque, 0));
    $attenduCode('PRODUIT_SUPPRIME_DU_CATALOGUE', static fn () => $panier->ajouter(999999, 1));
    $attenduCode('PRODUIT_SUPPRIME_DU_CATALOGUE', static fn () => $panier->ajouter($bracelet, 1)); // masqué (RB-19)
    $check('rupture : ajout plafonné à 0 (RB-18)', $panier->ajouter($power, 3) === 0 && $panier->estVide());
    $check('plafond serveur : 5 demandés, 2 retenus (RB-18)', $panier->ajouter($micro, 5) === 2);
    // le stock baisse après l'ajout (un autre client achète) : le récap doit
    // re-plafonner et signaler la ligne
    $store->update('produit', [Filter::eq('id_produit', $micro)], ['stock' => 1]);
    $recapPlafonne = $panier->recapitulatif();
    $check('ligne re-plafonnée après baisse de stock (RB-18)', ($recapPlafonne['articles'][0]['plafonne'] ?? null) === true
        && ($recapPlafonne['articles'][0]['quantite_demandee'] ?? 0) === 2 && ($recapPlafonne['articles'][0]['quantite'] ?? 0) === 1);
    $store->update('produit', [Filter::eq('id_produit', $micro)], ['stock' => 2]); // remise en état
    $check('fusion du même produit : +1 → toujours 2', $panier->ajouter($micro, 1) === 2);
    $check('badge = somme des quantités', $panier->nombreArticles() === 2);

    $panier->ajouter($casque, 2);
    $recap = $panier->recapitulatif();
    $check('récap : 2 lignes jointes au produit', count($recap['articles']) === 2);
    $check('prix TTC serveur (jamais client)', (float) $recap['articles'][1]['prix_unitaire'] === 154.80);
    $check('sous-total = 2×118,80 + 2×154,80', abs($recap['sous_total'] - 547.20) < 0.001); // micro 99 HT → 118,80
    $check('port offert ≥ 80 €', $recap['frais_port'] === 0.0);
    $check('pourCommande : format compact serveur', $panier->pourCommande() === [
        ['id_produit' => $micro, 'quantite' => 2],
        ['id_produit' => $casque, 'quantite' => 2],
    ]);

    // petit panier < 80 € : port facturé (câble 15,00 € TTC)
    $panier->vider();
    $panier->ajouter($cable, 1);
    $recapPetit = $panier->recapitulatif();
    $check('port standard 4,90 sous 80 €', abs($recapPetit['frais_port'] - 4.90) < 0.001
        && abs($recapPetit['sous_total'] - 15.00) < 0.001 && abs($recapPetit['total'] - 19.90) < 0.001);

    $panier->vider();
    $panier->ajouter($casque, 1);
    $panier->modifierQuantite($casque, 99);
    $check('modifierQuantite plafonne au stock', $panier->articlesBruts()[$casque] === 18);
    $panier->modifierQuantite($casque, 0);
    $check('modifierQuantite 0 retire la ligne', !isset($panier->articlesBruts()[$casque]));
    $panier->retirer(424242);
    $check('retirer un absent : sans erreur (idempotence, F-14)', true);

    // produit masqué entre-temps → purgé du récap (RB-17/19)
    $panier->vider();
    $panier->ajouter($casque, 1);
    $store->update('produit', [Filter::eq('id_produit', $casque)], ['visible' => 0]);
    $recapMasque = $panier->recapitulatif();
    $check('produit masqué pendant la visite : purgé du récap (RB-19)', $recapMasque['articles'] === []);
    $panier->vider();
    $check('vider() remet à zéro', $panier->estVide());
}

// ============================================================ 10. ports & paramètres
echo "\n10. ParametreRepository — frais de port (EF-VIS-09, ENF-14)\n";
{
    $store = magasin();
    $parametres = new ParametreRepository($store);
    $check('fnParam valeur connue', $parametres->fnParam('frais_port', '9.99') === '4.90');
    $check('fnParam valeur absente → défaut', $parametres->fnParam('inexistant', 'défaut') === 'défaut');
    $check('computeShipping < 80 € → 4,90', $parametres->computeShipping(79.99)['port'] === 4.90);
    $check('computeShipping ≥ 80 € → 0 (franchise)', $parametres->computeShipping(80.00)['port'] === 0.0);
    $check('computeShipping 0 € de marchandises → 4,90', $parametres->computeShipping(0.0)['port'] === 4.90);
    $check('TVA par défaut = 20', $parametres->tvaDefaut() === 20.0);
    $check('tous() expose les 4 paramètres', count($parametres->tous()) === 4);
}

// ============================================================ 11. prix TTC (§6)
echo "\n11. Prix TTC dérivés — arrondis (RB-16)\n";
{
    $store = magasin();
    $produits = new ProduitRepository($store);
    $id = $produits->saveProduct(null, 'TST-A', 'A', null, null, 129.00, 20.0, 1, 1, 1, true, null);
    $check('129,00 € @ 20 % → 154,80 €', (float) $store->find('produit', $id)['prix_ttc'] === 154.80);
    $id = $produits->saveProduct(null, 'TST-B', 'B', null, null, 10.00, 5.5, 1, 1, 1, true, null);
    $check('10,00 € @ 5,5 % → 10,55 €', (float) $store->find('produit', $id)['prix_ttc'] === 10.55);
    $id = $produits->saveProduct(null, 'TST-C', 'C', null, null, 3.333, 20.0, 1, 1, 1, true, null);
    $check('arrondi 2 décimales (3,333 → 4,00)', (float) $store->find('produit', $id)['prix_ttc'] === 4.00);
}

// ============================================================ 12. stockage : journal
echo "\n12. Journal d'audit (EF-GEN-04)\n";
{
    $store = magasin();
    $lignesAvant = is_file($store->journalPath()) ? count(file($store->journalPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
    $store->setActor(['id' => 7, 'role' => 'ADMIN', 'nom' => 'Testeur']);
    $store->update('produit', [Filter::eq('reference', 'CASQ-005')], ['stock' => 17]);
    $lignes = file($store->journalPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $check('chaque écriture est journalisée (+1 ligne)', count($lignes) === $lignesAvant + 1);
    $derniere = json_decode($lignes[count($lignes) - 1] ?? 'null', true);
    $check('le journal porte l\'auteur (traçabilité)', ($derniere['actor']['id'] ?? null) === 7 && ($derniere['actor']['role'] ?? '') === 'ADMIN');
    $check('le journal porte l\'opération, la table et les états', ($derniere['op'] ?? '') === 'update'
        && ($derniere['table'] ?? '') === 'produit'
        && ($derniere['old']['stock'] ?? null) === 18 && ($derniere['new']['stock'] ?? null) === 17);
}

// ============================================================ synthèse
echo "\n" . str_repeat('=', 72) . "\n";
echo sprintf("%d vérifications — %d OK, %d échecs\n", $tests, $tests - $echecs, $echecs);
if ($echecs > 0) {
    echo "UNITAIRES : SUITE EN ÉCHEC ❌\n";
    exit(1);
}
echo "UNITAIRES : TOUT EST VERT ✅\n";
ob_end_flush();
exit(0);
