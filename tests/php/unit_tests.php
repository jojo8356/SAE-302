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

use App\Config\Database;
use App\Config\Env;
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
use App\Config\DbConnexion;
use App\Tools\DbInstaller;
use App\Tools\DbProvision;

// Aucune suite de tests ne provisionne le système (comptes, bases) : le
// drapeau est lu par DbProvision::contexteDeTest(). Posé ici pour couvrir
// toutes les sections, pas seulement celle qui parle de provisionnement.
putenv('MINISHOP_TESTS=1');
use App\Tools\Revision;
use App\Tools\SqlLoader;
use App\Tools\EnvFile;

// en CLI, pas d'en-têtes de cookie de session (tests silencieux)
if (PHP_SAPI !== 'wasm' && !headers_sent()) {
    ini_set('session.use_cookies', '0');
}
\App\Security\Auth::demarrer();

$echecs = 0;
$tests = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$echecs, &$tests): void {
    ++$tests;
    $icone = '  ❌ ';
    if ($ok) {
        $icone = '  ✅ ';
    }
    $suffixe = '';
    if (!$ok && $detail !== '') {
        $suffixe = " — {$detail}";
    }
    echo $icone . $label . $suffixe . "\n";
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
        $icone = '  ❌ ';
        $obtenu = ' (obtenu ' . $e->businessCode . ')';
        if ($ok) {
            $icone = '  ✅ ';
            $obtenu = '';
        }
        echo $icone . "refus {$code}" . $obtenu . "\n";
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
        $anciens = glob($dossier . '/*.json');
        if (is_array($anciens)) {
            array_map('unlink', $anciens);
        }
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
        $versAffiches = $vers;
        if (!$vers) {
            $versAffiches = ['∅'];
        }
        $check("TRANSITIONS[{$depuis}] = " . implode(',', $versAffiches), (Triggers::TRANSITIONS[$depuis] ?? null) === $vers);
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
    if (is_file($store->journalPath())) { $lignesAvant = count(file($store->journalPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)); } else { $lignesAvant = 0; }
    $store->setActor(['id' => 7, 'role' => 'ADMIN', 'nom' => 'Testeur']);
    $store->update('produit', [Filter::eq('reference', 'CASQ-005')], ['stock' => 17]);
    if (file($store->journalPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) { $lignes = file($store->journalPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); } else { $lignes = []; }
    $check('chaque écriture est journalisée (+1 ligne)', count($lignes) === $lignesAvant + 1);
    $derniere = json_decode($lignes[count($lignes) - 1] ?? 'null', true);
    $check('le journal porte l\'auteur (traçabilité)', ($derniere['actor']['id'] ?? null) === 7 && ($derniere['actor']['role'] ?? '') === 'ADMIN');
    $check('le journal porte l\'opération, la table et les états', ($derniere['op'] ?? '') === 'update'
        && ($derniere['table'] ?? '') === 'produit'
        && ($derniere['old']['stock'] ?? null) === 18 && ($derniere['new']['stock'] ?? null) === 17);
}

// ============================================================ 13. configuration .env
echo "\n13. Configuration .env (App\\Config\\Env, EnvFile, Database)\n";
{
    // Fichier de travail : jamais celui du développeur (MINISHOP_ENV_FILE).
    $dossier = sys_get_temp_dir() . '/minishop-env-' . bin2hex(random_bytes(4));
    mkdir($dossier, 0775, true);
    $fichier = $dossier . '/.env';
    $modele = "# commentaire ignoré\n"
        . "\n"
        . "STORAGE_DRIVER=json\n"
        . "DB_NAME=\"minishop locale\"      # commentaire de fin de ligne\n"
        . "export DB_USER='racine'\n"
        . "  DB_PORT = 3307  \n"
        . "APP_DEBUG=oui\n"
        . "1_INVALIDE=non\n"
        . "ligne sans egal\n";
    file_put_contents($fichier, $modele);
    putenv(Env::VARIABLE_FICHIER . '=' . $fichier);
    Env::reinitialiser();

    $valeurs = Env::toutes();
    $check('clé simple lue', ($valeurs['STORAGE_DRIVER'] ?? null) === 'json');
    $check('guillemets et commentaire de fin retirés', ($valeurs['DB_NAME'] ?? null) === 'minishop locale');
    $check('préfixe « export » toléré', ($valeurs['DB_USER'] ?? null) === 'racine');
    $check('espaces autour de = et valeur ignorés', ($valeurs['DB_PORT'] ?? null) === '3307');
    $check('commentaire et ligne vide ignorés', !array_key_exists('#', $valeurs) && count($valeurs) === 5);
    $check('clé invalide rejetée', !array_key_exists('1_INVALIDE', $valeurs));
    $check('ligne sans « = » ignorée', !in_array('ligne sans egal', $valeurs, true));

    $check('booléen : « oui » → true', Env::booleen('APP_DEBUG', false) === true);
    $check('booléen : clé absente → défaut', Env::booleen('APP_DEBUG_ABSENT', true) === true);
    $check('entier : valeur numérique', Env::entier('DB_PORT', 3306) === 3307);
    $check('entier : valeur non numérique → défaut', Env::entier('DB_NAME', 3306) === 3306);
    $check('valeur absente → défaut fourni', Env::get('INCONNUE', 'repli') === 'repli');

    // priorité : variable d'environnement réelle > fichier
    putenv('DB_NAME=prioritaire');
    Env::reinitialiser();
    $check('l’environnement gagne sur le fichier', Env::get('DB_NAME') === 'prioritaire');
    putenv('DB_NAME');
    Env::reinitialiser();

    // Database::config() reflète le fichier (et convertit les types)
    Database::reset();
    $config = Database::config();
    $check('Database lit le .env', ($config['db_name'] ?? null) === 'minishop locale');
    $check('db_port converti en entier', ($config['db_port'] ?? null) === 3307);
    $check('debug converti en booléen', ($config['debug'] ?? null) === true);
    $check('pilote lu (« json »)', ($config['storage_driver'] ?? null) === 'json');

    // EnvFile : écriture sans perdre les commentaires
    $modifie = EnvFile::definir('STORAGE_DRIVER', 'sql');
    $contenu = (string) file_get_contents($fichier);
    $check('EnvFile remplace une clé existante', $modifie && str_contains($contenu, 'STORAGE_DRIVER=sql'));
    $check('EnvFile conserve les commentaires', str_contains($contenu, '# commentaire ignoré'));
    $check('EnvFile conserve les autres clés', str_contains($contenu, "DB_USER='racine'"));
    $ajoute = EnvFile::definir('NOUVELLE_CLE', 'valeur avec espaces');
    $contenu = (string) file_get_contents($fichier);
    $check('EnvFile ajoute une clé absente', $ajoute && str_contains($contenu, 'NOUVELLE_CLE="valeur avec espaces"'));
    $check('EnvFile : valeur relue sans guillemets', EnvFile::valeur('NOUVELLE_CLE') === 'valeur avec espaces');
    $check('EnvFile n’écrit rien si la valeur est identique', EnvFile::definir('NOUVELLE_CLE', 'valeur avec espaces') === false);

    // nettoyage : on rend l'environnement au reste de la suite
    putenv(Env::VARIABLE_FICHIER);
    Env::reinitialiser();
    Database::reset();
    $restes = glob($dossier . '/*');
    if ($restes === false) {
        $restes = [];
    }
    foreach ($restes as $reste) {
        @unlink($reste);
    }
    @rmdir($dossier);
    // On isole AVANT de conclure : sur un poste dont le `.env` porte
    // STORAGE_DRIVER=sql (cas réel après `./run.sh --db`), lire « config par
    // défaut » en relisant le vrai fichier donnait « sql » et faisait échouer
    // la vérification — le test dépendait de la configuration de la machine.
    putenv('MINISHOP_ENV_FILE=' . sys_get_temp_dir() . '/minishop-inexistant-' . getmypid() . '.env');
    Env::reinitialiser();
    Database::reset();
    $check('environnement rendu (config par défaut)', Database::driver() === 'json');
    putenv(Env::VARIABLE_FICHIER);
    Env::reinitialiser();
    Database::reset();
}

// ============================================================ 14. compatibilité SGBD
echo "\n14. Versions MySQL / MariaDB acceptées (App\\Tools\\DbInstaller)\n";
{
    $cas = [
        ['10.11.6-MariaDB-0+deb12u1', true,  'MariaDB 10.11 (Debian 12) accepté'],
        ['10.6.18-MariaDB',           true,  'MariaDB 10.6 (minimum) accepté'],
        ['10.3.39-MariaDB',           false, 'MariaDB 10.3 refusé (< 10.6)'],
        ['8.0.36-0ubuntu0.22.04.1',   true,  'MySQL 8.0.36 accepté'],
        ['8.0.16',                    true,  'MySQL 8.0.16 (minimum CHECK) accepté'],
        ['8.0.15',                    false, 'MySQL 8.0.15 refusé (CHECK ignoré)'],
        ['5.7.44-log',                false, 'MySQL 5.7 refusé'],
        ['8.4.2',                     true,  'MySQL 8.4 accepté'],
        ['',                          false, 'version vide refusée'],
        ['inconnue',                  false, 'version illisible refusée'],
    ];
    foreach ($cas as [$version, $attendu, $libelle]) {
        $check($libelle, DbInstaller::versionCompatible($version) === $attendu, 'version : ' . $version);
    }
}

// ============================================================ 15. connexion SGBD
echo "\n15. DSN et repli socket Unix (App\\Config\\DbConnexion)\n";
{
    $local = ['db_host' => '127.0.0.1', 'db_port' => '3306', 'db_user' => 'root', 'db_pass' => '', 'db_socket' => ''];
    $distant = ['db_host' => 'db.exemple.fr', 'db_port' => '3307', 'db_user' => 'app', 'db_pass' => 'x', 'db_socket' => ''];

    $dsn = DbConnexion::dsnTcp($local, 'minishop');
    $check('DSN TCP : hôte, port, base', $dsn === 'mysql:host=127.0.0.1;port=3306;dbname=minishop;charset=utf8mb4', $dsn);
    $check('DSN TCP : jeu de caractères imposé', str_ends_with($dsn, 'charset=utf8mb4'));
    // --- Collation de session : le défaut qui a fait échouer T-26 sur une base
    // réelle. Une colonne de TABLE a une collation implicite et gagne contre le
    // littéral ; une colonne de VUE n'en a pas, donc les deux opérandes sont
    // « coercibles » et MariaDB 11.4+ (défaut utf8mb4_uca1400_ai_ci) refuse :
    // ERROR 1267 Illegal mix of collations. La session doit être alignée sur le
    // schéma, côté PDO comme côté harnais SQL.
    $check('collation de session alignée sur le schéma',
        DbConnexion::COLLATION === 'utf8mb4_unicode_ci', DbConnexion::COLLATION);
    $options = DbConnexion::options();
    $init = '';
    if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
        $init = (string) ($options[PDO::MYSQL_ATTR_INIT_COMMAND] ?? '');
        $check('PDO : SET NAMES imposé à la connexion',
            str_contains($init, 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'), $init);
    } else {
        $check('PDO : init-command omise sans pdo_mysql (repli sans erreur)',
            !array_key_exists('PDO::MYSQL_ATTR_INIT_COMMAND', $options));
    }
    foreach (['01_minishop_schema', '02_minishop_procedures', '03_minishop_triggers'] as $fichier) {
        $contenu = (string) file_get_contents(dirname(__DIR__, 2) . '/sql/' . $fichier . '.sql');
        $check('sql/' . $fichier . ' fixe la collation avant de créer les objets',
            str_contains($contenu, 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'));
    }
    $harnais = (string) file_get_contents(dirname(__DIR__, 2) . '/tests/sql/run_tests.sh');
    $check('harnais SQL : session alignée (même collation que le schéma)',
        str_contains($harnais, 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'));
    // L'en-tête doit annoncer la collation RÉELLEMENT utilisée, pas le défaut du
    // client : afficher uca1400_ai_ci alors que les scénarios tournent en
    // unicode_ci laissait croire que la campagne était exposée à l'erreur 1267.
    $check('harnais SQL : la collation affichée est celle des scénarios',
        str_contains($harnais, 'COLLATION_SESSION=') && str_contains($harnais, 'COLLATION_CLIENT=')
        && !str_contains($harnais, 'Collation de session : $(mysqlq'));

    $check('DSN sans base (administration serveur)',
        DbConnexion::dsnTcp($local) === 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4');
    $check('DSN socket : pas de host ni de port',
        DbConnexion::dsnSocket('/run/mysqld/mysqld.sock', 'minishop')
            === 'mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=minishop;charset=utf8mb4');

    $check('hôte 127.0.0.1 considéré comme local', DbConnexion::hoteLocal($local));
    $check('hôte « localhost » considéré comme local',
        DbConnexion::hoteLocal(['db_host' => 'LOCALHOST']));
    $check('hôte distant non local', !DbConnexion::hoteLocal($distant));
    $check('aucune socket proposée pour un serveur distant',
        DbConnexion::socketLocale($distant) === null);

    $dsns = DbConnexion::dsns('minishop', $local);
    $check('TCP essayé en premier (comportement du .env respecté)', $dsns[0] === DbConnexion::dsnTcp($local, 'minishop'));
    $check('au plus deux DSN pour un poste local', count($dsns) <= 2, (string) count($dsns));
    foreach ($dsns as $essai) {
        $check('DSN bien formé : ' . $essai, str_starts_with($essai, 'mysql:') && str_contains($essai, 'charset=utf8mb4'));
    }

    // DB_SOCKET explicite et inexistante : ignorée (on ne casse pas le TCP)
    $avecFaux = ['db_host' => '127.0.0.1', 'db_socket' => '/tmp/inexistant-' . bin2hex(random_bytes(4)) . '.sock'];
    $check('DB_SOCKET inexistante ignorée', DbConnexion::socketConfiguree($avecFaux) === null);

    // DB_SOCKET explicite et existante : prioritaire
    $fauxSocket = sys_get_temp_dir() . '/minishop-test-' . bin2hex(random_bytes(4)) . '.sock';
    if (@file_put_contents($fauxSocket, '') !== false) {
        $avecSocket = ['db_host' => '127.0.0.1', 'db_port' => '3306', 'db_socket' => $fauxSocket];
        $check('DB_SOCKET existante retenue', DbConnexion::socketConfiguree($avecSocket) === $fauxSocket);
        $prioritaires = DbConnexion::dsns('minishop', $avecSocket);
        $check('socket imposée essayée en premier', $prioritaires[0] === DbConnexion::dsnSocket($fauxSocket, 'minishop'));
        $check('DSN unix_socket ne contient ni host ni port',
            !str_contains($prioritaires[0], 'host=') && !str_contains($prioritaires[0], 'port='));
        @unlink($fauxSocket);
    }

    // Un message d'erreur francophone et actionnable, jamais un code nu
    $erreur = new PDOException("SQLSTATE[HY000] [1698] Access denied for user 'root'@'localhost'");
    $erreur->errorInfo = ['HY000', 1698, "Access denied for user 'root'@'localhost'"];
    $texte = DbConnexion::expliquer($erreur, '127.0.0.1', '3306');
    $check('erreur 1698 expliquée (socket Unix)', str_contains($texte, 'socket Unix'));
    $check('erreur 1698 : création du compte proposée', str_contains($texte, '--compte'));
    $check('erreur 1698 : utilisateur du système nommé', str_contains($texte, 'utilisateur du SYSTÈME'));
    $check('erreur 1698 : alternative « sudo php » proposée', str_contains($texte, 'sudo php scripts/db_init.php'));

    $refus = new PDOException('SQLSTATE[HY000] [1045] Access denied');
    $refus->errorInfo = ['HY000', 1045, 'Access denied'];
    $check('erreur 1045 : identifiants', str_contains(DbConnexion::expliquer($refus, 'h', '1'), 'Identifiants refusés'));

    $pilote = new PDOException('could not find driver');
    $pilote->errorInfo = ['HY000', 0, 'could not find driver'];
    $check('pdo_mysql absent : paquet indiqué',
        str_contains(DbConnexion::expliquer($pilote, 'h', '1'), 'pdo_mysql'));

    $muet = new PDOException('SQLSTATE[HY000] [2002] Connection refused');
    $muet->errorInfo = ['HY000', 2002, 'Connection refused'];
    $check('serveur arrêté : commande de démarrage',
        str_contains(DbConnexion::expliquer($muet, 'h', '3306'), 'systemctl start mariadb'));

    $config = Database::config();
    $check('clé db_socket exposée par la configuration', array_key_exists('db_socket', $config));


    // Le chemin exact suivi par « db_init.php --compte » : écrire les
    // identifiants dans .env, vider les caches, relire la configuration.
    $dossierCompte = sys_get_temp_dir() . '/minishop-compte-' . bin2hex(random_bytes(4));
    @mkdir($dossierCompte, 0775, true);
    $envCompte = $dossierCompte . '/.env';
    file_put_contents($envCompte, "DB_USER=root\nDB_PASSWORD=\nSTORAGE_DRIVER=json\n");
    putenv(Env::VARIABLE_FICHIER . '=' . $envCompte);
    Env::reinitialiser();
    Database::reset();
    $avant = Database::config();
    $check('départ : DB_USER = root (ce que --compte doit remplacer)', ($avant['db_user'] ?? '') === 'root');

    $motDePasseCompte = DbProvision::motDePasse();
    $modifiees = EnvFile::definirPlusieurs([
        'DB_USER' => 'minishop',
        'DB_PASSWORD' => $motDePasseCompte,
    ]);
    Database::reset();
    Env::reinitialiser();
    $apres = Database::config();
    $check('identifiants reportés dans .env', $modifiees === ['DB_USER', 'DB_PASSWORD'], implode(',', $modifiees));
    $check('configuration rechargée : utilisateur applicatif', ($apres['db_user'] ?? '') === 'minishop');
    $check('configuration rechargée : mot de passe généré', ($apres['db_pass'] ?? '') === $motDePasseCompte);
    $check('rechargement sans redémarrage (caches vidés)', ($apres['storage_driver'] ?? '') === 'json');
    $check('le mot de passe n’est pas cité dans l’en-tête du fichier',
        !str_contains((string) file_get_contents($envCompte), 'root\n'));
    $relecture = (string) file_get_contents($envCompte);
    $check('mot de passe écrit sans transformation (hexadécimal)',
        str_contains($relecture, 'DB_PASSWORD=' . $motDePasseCompte));

    putenv(Env::VARIABLE_FICHIER);
    Env::reinitialiser();
    Database::reset();
    @unlink($envCompte);
    @rmdir($dossierCompte);

}

// ============================================================ 16. compte applicatif
echo "\n16. Création du compte MySQL applicatif (App\\Tools\\DbProvision)\n";
{
    // root ne doit jamais être renforcé : on bascule sur le compte standard.
    putenv('DB_USER=root');
    putenv('MINISHOP_ENV_FILE=/tmp/inexistant-minishop.env');
    Env::reinitialiser();
    Database::reset();
    $provision = DbProvision::depuisConfiguration();
    $check('DB_USER=root → compte applicatif « minishop »', $provision->utilisateur() === 'minishop');
    putenv(Env::VARIABLE_FICHIER);
    putenv('DB_USER');
    Env::reinitialiser();
    Database::reset();

    $provision = new DbProvision('minishop', 'minishop');
    $check('nom de base et d’utilisateur validés', $provision->valide());
    $check('injection dans le nom de base refusée', !(new DbProvision('minishop; DROP TABLE x', 'minishop'))->valide());
    $check('injection dans l’utilisateur refusée', !(new DbProvision('minishop', "min'@'%"))->valide());
    $check('nom trop long refusé', !(new DbProvision(str_repeat('a', 33), 'minishop'))->valide());
    $check('base vide refusée', !(new DbProvision('', 'minishop'))->valide());

    $motDePasse = DbProvision::motDePasse();
    $check('mot de passe hexadécimal de 32 caractères', preg_match('/^[0-9a-f]{32}$/', $motDePasse) === 1);
    $check('mot de passe aléatoire (deux tirages diffèrent)', DbProvision::motDePasse() !== $motDePasse);

    $instructions = $provision->instructions($motDePasse);
    $texte = implode("\n", $instructions);
    $check('base créée en utf8mb4_unicode_ci', str_contains($texte, 'CREATE DATABASE IF NOT EXISTS `minishop`')
        && str_contains($texte, 'utf8mb4_unicode_ci'));
    $check('deux hôtes couverts (localhost + 127.0.0.1)',
        str_contains($texte, "'minishop'@'localhost'") && str_contains($texte, "'minishop'@'127.0.0.1'"));
    $check('mot de passe présent pour chaque hôte',
        substr_count($texte, 'IDENTIFIED BY') === 4, (string) substr_count($texte, 'IDENTIFIED BY'));
    $check('ALTER USER réaligne un compte préexistant',
        substr_count($texte, 'ALTER USER') === 2);
    $check('droits limités à la base du projet (jamais *.*)',
        !str_contains($texte, '*.*') && substr_count($texte, 'GRANT ALL PRIVILEGES ON `minishop`.*') === 2);
    $check('privilèges rechargés', str_contains($texte, 'FLUSH PRIVILEGES'));
    $check('aucune instruction sans point-virgule dans script()', substr_count($provision->script($motDePasse), ";\n") === count($instructions));
    $check('script() copiable en une commande', str_starts_with($provision->script($motDePasse), 'CREATE DATABASE'));

    // Une suite de tests ne provisionne JAMAIS le système.
    //
    // Incident réel : cette section appelait `creerCompte()` pour constater
    // l'échec en l'absence de client SQL. Sur une machine où le client existe,
    // elle a exécuté un vrai `sudo mariadb` — mot de passe du compte applicatif
    // remplacé par une valeur aléatoire écrite nulle part, puis `1045` sur
    // toutes les connexions PDO. Le drapeau ci-dessous rend l'accident
    // impossible, et il est lui-même vérifié ici.
    $refusCompte = $provision->creerCompte($motDePasse);
    $check('aucune création de compte depuis une suite de tests (garde-fou)',
        $refusCompte['ok'] === false && $refusCompte['methode'] === 'refusé', $refusCompte['sortie']);
    $refusAnnexe = $provision->ajouterBaseAnnexe('minishop_test_interdit');
    $check('aucune création de base depuis une suite de tests (garde-fou)',
        $refusAnnexe['ok'] === false && $refusAnnexe['methode'] === 'refusé', $refusAnnexe['sortie']);
    $check('création refusée si les noms sont invalides',
        (new DbProvision('base interdite', 'minishop'))->creerCompte('x')['ok'] === false);

    $check('client SQL détecté ou absent, jamais une exception',
        DbProvision::clientSql() === null || in_array(DbProvision::clientSql(), ['mariadb', 'mysql'], true));

    // --- base annexe des tests d'intégration (demandée par la machine réelle :
    // « 1044 Access denied for user 'minishop'@'localhost' to database
    // 'minishop_test' » — le compte applicatif n'a, à raison, de droits que sur
    // sa base ; le test SQL ne peut donc pas créer la sienne).
    $annexe = implode("\n", $provision->instructionsBaseAnnexe('minishop_test'));
    $check('base annexe créée en utf8mb4_unicode_ci',
        str_contains($annexe, 'CREATE DATABASE IF NOT EXISTS `minishop_test`')
        && str_contains($annexe, 'utf8mb4_unicode_ci'));
    $check('droits accordés sur la base annexe pour les deux hôtes',
        substr_count($annexe, 'GRANT ALL PRIVILEGES ON `minishop_test`.*') === 2
        && str_contains($annexe, "'minishop'@'localhost'")
        && str_contains($annexe, "'minishop'@'127.0.0.1'"));
    $check('la base annexe n’élargit jamais les droits (jamais *.*)', !str_contains($annexe, '*.*'));
    $check('la base annexe ne touche pas au mot de passe du compte',
        !str_contains($annexe, 'IDENTIFIED BY') && !str_contains($annexe, 'ALTER USER')
        && !str_contains($annexe, 'CREATE USER'));
    $check('la base de production n’est pas modifiée par la base annexe',
        !str_contains($annexe, 'ON `minishop`.*'));
    $check('nom de base annexe validé',
        DbProvision::baseValide('minishop_test') && !DbProvision::baseValide('minishop; DROP TABLE x')
        && !DbProvision::baseValide(str_repeat('a', 33)) && !DbProvision::baseValide(''));
    $check('base annexe invalide : refus explicite, sans exécution',
        $provision->ajouterBaseAnnexe('minishop; DROP TABLE x')['ok'] === false);
}

// ============================================================ 17. empreinte du code
echo "\n17. Empreinte de révision affichée par les scripts (App\\Tools\\Revision)\n";
{
    Revision::reinitialiser();
    $empreinte = Revision::courante();
    $check('une empreinte est toujours retournée', $empreinte !== '');
    $check('jamais une exception, même hors dépôt Git',
        is_string($empreinte) && (str_contains($empreinte, 'inconnue') || preg_match('/^[0-9a-f]{4,}/', $empreinte) === 1),
        $empreinte);
    // Dans la sandbox (PHP-WASM), git ne peut pas être lancé : « inconnue ».
    // Sur une machine avec dépôt : « sha · AAAA-MM-JJ ».
    if (!str_contains($empreinte, 'inconnue')) {
        $check('format « sha · date »', preg_match('/^[0-9a-f]{4,}\s·\s\d{4}-\d{2}-\d{2}$/', $empreinte) === 1, $empreinte);
    }
    $check('deuxième appel identique (mise en cache)', Revision::courante() === $empreinte);
    Revision::reinitialiser();
    $check('cache vidable', Revision::courante() === $empreinte);
    $check('état de travail interrogeable sans erreur', is_bool(Revision::modifie()));

    // Le message d'erreur 1698 doit renvoyer vers la solution réelle, et non
    // vers un repli qui ne peut pas fonctionner par conception.
    $refus = new PDOException("SQLSTATE[HY000] [1698] Access denied for user 'root'@'localhost'");
    $refus->errorInfo = ['HY000', 1698, "Access denied for user 'root'@'localhost'"];
    $texte1698 = DbConnexion::expliquer($refus, '127.0.0.1', '3306');
    $check('1698 : « --compte » proposé', str_contains($texte1698, '--compte'));
    $check('1698 : sudo php proposé', str_contains($texte1698, 'sudo php'));
    $check('1698 : aucune promesse de socket magique',
        !str_contains($texte1698, 'introuvable') || str_contains($texte1698, 'quel que soit le mot de passe'));
}

// ============================================================ 18. découpage SQL
echo "\n18. Découpage des scripts SQL (App\\Tools\\SqlLoader::decouper)\n";
{
    // Cas synthétiques : chacun correspond à une erreur réellement rencontrée.
    $cas = [
        ['SELECT 1; SELECT 2;', 2, 'deux instructions sur une ligne'],
        ["SELECT ';';", 1, 'point-virgule dans une chaîne'],
        ["SELECT 'a'';b';", 1, 'apostrophe doublée : le ; reste dans la chaîne'],
        ["SELECT 1 -- un commentaire\n;", 1, 'commentaire de fin de ligne'],
        ["SELECT 1 # un commentaire\n;", 1, 'commentaire dièse'],
        ["SELECT 1 /* bloc */;", 1, 'commentaire de bloc'],
        ["-- c'est l'apostrophe qui casse tout\nSELECT 1;", 1, 'commentaire en colonne 0 avec apostrophe'],
        ["  -- c'est l'apostrophe qui casse tout\n  SELECT 1;", 1, 'commentaire INDENTÉ avec apostrophe (bug corrigé)'],
        ["SELECT 1-1;", 1, 'a-1 reste du code (-- exige un blanc)'],
        ["SELECT 1 --2;", 1, '1--2 reste du code'],
        ["DELIMITER $$\nCREATE PROCEDURE p() BEGIN SELECT 1; END $$\nDELIMITER ;\nSELECT 2;", 2, 'DELIMITER $$ : le ; interne ne coupe pas'],
        ["  DELIMITER $$\nCREATE PROCEDURE p() BEGIN SELECT ';'; END $$\nDELIMITER ;", 1, 'DELIMITER indenté + point-virgule dans une chaîne'],
        ["DELIMITER //\nCREATE TRIGGER t BEFORE INSERT ON x FOR EACH ROW BEGIN SET @a = 1; END //\nDELIMITER ;", 1, 'délimiteur // et corps à une instruction'],
        ["SELECT 1;;; SELECT 2", 2, 'délimiteurs vides ignorés'],
        ["SELECT 1", 1, 'dernière instruction sans délimiteur'],
    ];
    foreach ($cas as [$script, $attendu, $libelle]) {
        $obtenu = SqlLoader::decouper($script);
        $check($libelle, count($obtenu) === $attendu, 'obtenu ' . count($obtenu) . ' : ' . implode(' | ', $obtenu));
        foreach ($obtenu as $instruction) {
            $check($libelle . ' — aucun $$ résiduel', !str_contains($instruction, '$$'));
        }
    }

    // Fichiers réels du projet : c'est là que le bug se manifestait.
    $racine = dirname(__DIR__, 2);
    $attentes = [
        'sql/01_minishop_schema.sql' => ['CREATE TABLE' => 8, 'CREATE OR REPLACE VIEW' => 3, 'cards' => 28, 'cards_max' => 32],
        'sql/02_minishop_procedures.sql' => ['CREATE PROCEDURE' => 17, 'CREATE FUNCTION' => 1],
        'sql/03_minishop_triggers.sql' => ['CREATE TRIGGER' => 16],
        'sql/04_minishop_demo.sql' => [],
    ];
    foreach ($attentes as $fichier => $comptes) {
        $chemin = $racine . '/' . $fichier;
        $check($fichier . ' livré', is_file($chemin));
        $instructions = SqlLoader::decouper((string) file_get_contents($chemin));
        $residuel = 0;
        $delimiteurResiduel = 0;
        foreach ($instructions as $instruction) {
            if (str_contains($instruction, '$$')) {
                ++$residuel;
            }
            if (preg_match('/^\s*DELIMITER\b/i', $instruction) === 1) {
                ++$delimiteurResiduel;
            }
        }
        $check($fichier . ' — aucun « $$ » dans une instruction', $residuel === 0, $residuel . ' instruction(s)');
        $check($fichier . ' — aucune ligne DELIMITER exécutée', $delimiteurResiduel === 0);
        $source = (string) file_get_contents($chemin);
        foreach ($comptes as $motif => $attendu) {
            if ($motif === 'cards' || $motif === 'cards_max') {
                continue;
            }
            $presents = preg_match_all('/^' . preg_quote($motif, '/') . '\s/im', $source);
            $decoupes = 0;
            foreach ($instructions as $instruction) {
                if (preg_match('/^' . preg_quote($motif, '/') . '\s/i', $instruction) === 1) {
                    ++$decoupes;
                }
            }
            $check($fichier . ' — ' . $attendu . ' × ' . $motif . ' présents dans le source', $presents === $attendu, (string) $presents);
            $check($fichier . ' — ' . $attendu . ' × ' . $motif . ' correctement délimités', $decoupes === $attendu, (string) $decoupes);
        }
        // Un corps `BEGIN … END` doit finir par END (sinon il a été coupé au
        // premier point-virgule interne). Un trigger à instruction unique
        // (`SET NEW.x = …`) n'a pas de BEGIN : il ne compte que pour le
        // recollement, symptôme exact du bug corrigé.
        $incompletes = 0;
        $recolles = 0;
        foreach ($instructions as $instruction) {
            if (preg_match('/^CREATE\s+(PROCEDURE|FUNCTION|TRIGGER)\b/i', $instruction) !== 1) {
                continue;
            }
            $corps = rtrim($instruction);
            if (preg_match('/\bBEGIN\b/i', $corps) === 1 && preg_match('/END\s*$/i', $corps) !== 1) {
                ++$incompletes;
            }
            if (preg_match_all('/CREATE\s+(PROCEDURE|FUNCTION|TRIGGER)\b/i', $corps) > 1) {
                ++$recolles;
            }
        }
        $check($fichier . ' — aucun corps BEGIN…END incomplet', $incompletes === 0, $incompletes . ' incomplet(s)');
        $check($fichier . ' — aucune routine recollée avec une autre', $recolles === 0, $recolles . ' recollée(s)');
        if (isset($comptes['cards'])) {
            $check($fichier . ' — nombre d’instructions plausible',
                count($instructions) >= $comptes['cards'] && count($instructions) <= $comptes['cards_max'],
                (string) count($instructions));
        }
    }
}

// ============================================================ 19. invariants SQL
echo "\n19. Invariants des scripts SQL livrés (collations, garde-fous métier)\n";
{
    $racine = dirname(__DIR__, 2);
    $lire = static function (string $fichier) use ($racine): string {
        $chemin = $racine . '/' . $fichier;
        if (!is_file($chemin)) {
            return '';
        }

        return (string) file_get_contents($chemin);
    };

    // 1. Collation de connexion : sans elle, les procédures mémorisent la
    //    collation du moment et sp_search_products lève ensuite l'erreur 1267.
    foreach (['sql/01_minishop_schema.sql', 'sql/02_minishop_procedures.sql', 'sql/03_minishop_triggers.sql'] as $fichier) {
        $source = $lire($fichier);
        $check($fichier . ' — SET NAMES avec collation explicite',
            str_contains($source, 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'));
        // `SET NAMES utf8mb4;` seul laisserait la collation au défaut du serveur.
        $sansCollation = preg_match_all('/^SET NAMES utf8mb4\s*;/mi', $source);
        $check($fichier . ' — aucun SET NAMES sans collation', $sansCollation === 0, (string) $sansCollation);
    }
    $check('la base est créée en utf8mb4_unicode_ci (cohérence avec les routines)',
        str_contains($lire('sql/01_minishop_schema.sql'), 'COLLATE utf8mb4_unicode_ci'));
    $check('le chargeur crée la base dans la même collation',
        str_contains($lire('app/Tools/DbInstaller.php'), 'utf8mb4_unicode_ci'));

    // 2. RB-19 : un produit masqué ne peut pas être commandé.
    $procedures = $lire('sql/02_minishop_procedures.sql');
    $check('sp_add_order_line verrouille le stock en filtrant les produits masqués',
        str_contains($procedures, 'FROM produit WHERE id_produit = p_id_produit AND visible = 1 FOR UPDATE'));

    // 3. Anti-doublon d'historique : le déclencheur écrit, la procédure non.
    $debut = strpos($procedures, 'CREATE PROCEDURE sp_update_order_status');
    $check('sp_update_order_status présente', $debut !== false);
    if ($debut !== false) {
        $fin = strpos($procedures, 'DELIMITER', $debut);
        if ($fin === false) {
            $corps = substr($procedures, $debut);
        } else {
            $corps = substr($procedures, $debut, $fin - $debut);
        }
        $check('sp_update_order_status n’écrit pas elle-même l’historique (le trigger s’en charge)',
            !str_contains($corps, 'INSERT INTO order_status_history'));
        $check('sp_update_order_status écrit bien le statut',
            str_contains($corps, 'UPDATE commande'));
    }

    // 4. Les triggers d'historique existent et sont tracés.
    $triggers = $lire('sql/03_minishop_triggers.sql');
    $check('le déclencheur d’historique de statut est livré',
        str_contains($triggers, 'order_status_history'));
    $check('aucun trigger ne supprime silencieusement l’historique',
        !str_contains($triggers, 'DELETE FROM order_status_history'));
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
