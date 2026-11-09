<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Admin > Catégories : un doublon écrase la
 * catégorie existante » (EF-ADM-03, uk_categorie_nom / uk_categorie_slug).
 *
 * Scénario du signalement, rejoué au niveau Repository :
 *   créer une catégorie nommée « Audio » avec la description « test »
 *   → attendu : refus NOM_DEJA_UTILISE (« Ce nom de catégorie existe
 *     déjà. ») et catégorie Audio d'origine strictement inchangée ;
 *   → obtenu avant correctif : « Catégorie enregistrée » et description
 *     de la vraie Audio remplacée par « test » (fusion silencieuse,
 *     ancien portage « ON DUPLICATE KEY UPDATE » de saveCategory).
 *
 * La suite verrouille tout le périmètre de l'unicité, pas seulement le
 * scénario nominal : variantes casse/accents/espaces (la collation du
 * magasin est insensible aux trois), slug explicite ou généré en
 * collision, renommage sur le nom/slug d'une autre catégorie, et les
 * comportements voisins qui ne doivent pas bouger (création valide,
 * modification légitime de la même catégorie, suppression RB-14).
 * Après CHAQUE refus, l'état du magasin est comparé à l'état initial :
 * un refus qui écrit quand même est un échec.
 *
 * Usage :  php tests/php/categorie_doublon_test.php
 *         node scripts/wasm_run.mjs tests/php/categorie_doublon_test.php   (sandbox)
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

ob_start();

use App\Model\Data\BusinessError;
use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use App\Model\Data\StoreInterface;
use App\Repository\CategorieRepository;

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
/** Capture un code métier attendu (et seulement lui). */
$attendu = static function (string $code, callable $travail) use (&$echecs, &$tests): void {
    ++$tests;
    try {
        $travail();
        echo "  ❌ {$code} non levé\n";
        ++$echecs;
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
    }
};

function magasin(): StoreInterface
{
    static $n = 0;
    $dossier = sys_get_temp_dir() . '/minishop_cat' . getmypid() . '_' . (++$n);
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

/** Photographie complète de la table categorie (état avant/après refus). @return list<array<string,mixed>> */
function photoCategories(StoreInterface $store): array
{
    return $store->select('categorie', ['order' => [['id_categorie', 'asc']]]);
}

echo "MiniShop — régression doublon de catégorie (Admin > Catégories)\n";

// ============================================== 1. Scénario exact du signalement
echo "\n1. Scénario signalé : créer « Audio » / description « test »\n";
{
    $store = magasin();
    $repo = new CategorieRepository($store);
    $avant = photoCategories($store);
    $audio = $store->find('categorie', 2);
    $check('fixture : Audio existe (id 2)', $audio !== null && $audio['nom'] === 'Audio');
    $check(
        'fixture : description d\'origine',
        $audio !== null && $audio['description'] === 'Casques, enceintes et microphones'
    );

    $attendu('NOM_DEJA_UTILISE', fn () => $repo->saveCategory(null, 'Audio', null, 'test'));
    $check('aucune écriture après le refus (état identique)', photoCategories($store) === $avant);
    $apres = $store->find('categorie', 2);
    $check(
        'la vraie Audio est intacte (nom, slug, description)',
        $apres !== null
            && $apres['nom'] === 'Audio'
            && $apres['slug'] === 'audio'
            && $apres['description'] === 'Casques, enceintes et microphones'
    );
    $check('toujours 4 catégories, aucune « test » créée', $store->count('categorie') === 4);
}

// ============================================== 2. Variantes du même nom (collation)
echo "\n2. Même nom, autre casse / accents / espaces : même refus\n";
{
    $store = magasin();
    $repo = new CategorieRepository($store);
    $avant = photoCategories($store);
    foreach (['audio', 'AUDIO', ' Audio ', 'Àudio'] as $variante) {
        $attendu('NOM_DEJA_UTILISE', fn () => $repo->saveCategory(null, $variante, null, 'test'));
    }
    $check('aucune écriture après les 4 refus', photoCategories($store) === $avant);
}

// ============================================== 3. Collisions de slug en création
echo "\n3. Slug déjà utilisé en création (explicite ou généré)\n";
{
    $store = magasin();
    $repo = new CategorieRepository($store);
    $avant = photoCategories($store);

    // nom libre, mais slug explicite déjà porté par Audio
    $attendu('SLUG_CATEGORIE_DEJA_UTILISE', fn () => $repo->saveCategory(null, 'Multimédia', 'audio', 'test'));
    // nom libre dont le slug GÉNÉRÉ heurte celui d'une catégorie créée juste avant
    $id = $repo->saveCategory(null, 'High-Tech', null, 'Neuf');
    $check('création témoin High-Tech', $store->find('categorie', $id)['slug'] === 'high-tech');
    $attendu('SLUG_CATEGORIE_DEJA_UTILISE', fn () => $repo->saveCategory(null, 'High Tech', null, 'test'));
    $check(
        'seule la création témoin a été ajoutée',
        $store->count('categorie') === 5 && $store->find('categorie', 2) === $avant[1]
    );
}

// ============================================== 4. Renommage sur une autre catégorie
echo "\n4. Renommage vers le nom ou le slug d'une autre catégorie\n";
{
    $store = magasin();
    $repo = new CategorieRepository($store);
    $avant = photoCategories($store);

    $attendu('NOM_DEJA_UTILISE', fn () => $repo->saveCategory(3, 'Audio', null, 'test'));
    $attendu('SLUG_CATEGORIE_DEJA_UTILISE', fn () => $repo->saveCategory(3, 'Accessoires HD', 'audio', 'test'));
    $check('aucune écriture après les refus de renommage', photoCategories($store) === $avant);
    $accessoires = $store->find('categorie', 3);
    $check(
        'Accessoires intacte',
        $accessoires !== null && $accessoires['nom'] === 'Accessoires'
            && $accessoires['description'] === 'Sacs, câbles, supports et batteries'
    );
}

// ============================================== 5. Comportements légitimes préservés
echo "\n5. Création et modifications légitimes (non-régression)\n";
{
    $store = magasin();
    $repo = new CategorieRepository($store);

    $id = $repo->saveCategory(null, 'Stockage', null, 'SSD, disques');
    $creee = $store->find('categorie', $id);
    $check('création valide : slug auto', $creee !== null && $creee['slug'] === 'stockage');
    $check('création valide : 5 catégories', $store->count('categorie') === 5);

    // même catégorie, même nom, nouvelle description : modification, pas un doublon
    $repo->saveCategory($id, 'Stockage', null, 'SSD, disques, clés USB');
    $check(
        'modification de SA description (même nom) acceptée',
        $store->find('categorie', $id)['description'] === 'SSD, disques, clés USB'
    );

    // renommage vers un nom libre
    $repo->saveCategory($id, 'Stockage & sauvegarde', null, 'SSD, disques, clés USB');
    $renommee = $store->find('categorie', $id);
    $check(
        'renommage libre accepté, slug régénéré',
        $renommee !== null && $renommee['nom'] === 'Stockage & sauvegarde'
            && $renommee['slug'] === 'stockage-sauvegarde'
    );

    $attendu('CATEGORIE_INTROUVABLE', fn () => $repo->saveCategory(999, 'Fantôme', null, 'test'));
    $attendu('NOM_REQUIS', fn () => $repo->saveCategory(null, '   ', null, 'test'));
    $check('toujours 5 catégories après refus divers', $store->count('categorie') === 5);
}

// ============================================== 6. Suppression (voisinage RB-14)
echo "\n6. Suppression : occupée refusée, vide acceptée\n";
{
    $store = magasin();
    $repo = new CategorieRepository($store);
    $attendu('CATEGORIE_NON_VIDE', fn () => $repo->deleteCategory(2)); // Audio a des produits
    $check('Audio toujours présente après refus RB-14', $store->find('categorie', 2) !== null);
    $id = $repo->saveCategory(null, 'Temporaire', null, 'À supprimer');
    $repo->deleteCategory($id);
    $check('catégorie vide supprimée', $store->find('categorie', $id) === null && $store->count('categorie') === 4);
}

// ------------------------------------------------------------ bilan
echo "\n============================================================\n";
if ($echecs > 0) {
    echo "doublon catégorie : {$echecs}/{$tests} vérification(s) en échec ❌\n";
    exit(1);
}
echo "doublon catégorie : {$tests}/{$tests} vérifications vertes ✅\n";
exit(0);
