<?php

declare(strict_types=1);

/** Tests partagés du tri des nouveautés ; à utiliser uniquement sur une fixture isolée. */

use App\Model\Data\Filter;
use App\Model\Data\StoreInterface;
use App\Repository\ProduitRepository;

/** @return array{tests:int,echecs:int} */
function verifierNouveautes(StoreInterface $store): array
{
    $repo = new ProduitRepository($store);
    $tests = 0;
    $echecs = 0;
    $check = static function (string $nom, bool $ok) use (&$tests, &$echecs): void {
        ++$tests;
        if ($ok) {
            echo '  ✅ ' . $nom . "\n";
        } else {
            ++$echecs;
            echo '  ❌ ' . $nom . "\n";
        }
    };
    $ids = static fn (array $lignes): array => array_map('intval', array_column($lignes, 'id_produit'));
    $produits = $store->select('produit');
    usort($produits, static fn (array $a, array $b): int => (int) $a['id_produit'] <=> (int) $b['id_produit']);
    if (count($produits) < 7) {
        $check('fixture : au moins sept produits', false);
        return ['tests' => $tests, 'echecs' => $echecs];
    }
    $visibles = array_values(array_filter($produits, static fn (array $p): bool => (int) $p['visible'] === 1));
    usort($visibles, static function (array $a, array $b): int {
        $date = strcmp((string) $b['date_creation'], (string) $a['date_creation']);
        if ($date !== 0) {
            return $date;
        }
        return (int) $b['id_produit'] <=> (int) $a['id_produit'];
    });
    $accueil = $repo->searchProducts(['tri' => 'nouveaute', 'par_page' => 4]);
    $check('jeu initial : les quatre premiers correspondent aux dates et identifiants décroissants',
        $ids($accueil['lignes']) === array_slice($ids($visibles), 0, 4)
        && $accueil['total'] === count($visibles));

    // Chaque écriture ci-dessous est annulée, y compris sur MariaDB.
    $store->beginTransaction();
    try {
        $store->update('produit', [], ['visible' => false]);
        $vide = $repo->searchProducts(['tri' => 'nouveaute', 'par_page' => 4]);
        $check('aucun produit visible : liste vide', $vide['lignes'] === [] && $vide['total'] === 0);

        $p = array_map('intval', array_column(array_slice($produits, 0, 7), 'id_produit'));
        $dates = [
            ['2040-01-01 10:00:00', true, 4],  // petit identifiant mais date récente
            ['2030-01-01 10:00:00', true, 4],
            ['2025-01-01 10:00:00', true, 4],
            ['2010-01-01 10:00:00', true, 4],
            ['2030-01-01 10:00:00', true, 4],  // égalité : id plus grand d'abord
            ['2099-01-01 10:00:00', false, 4], // masqué malgré sa date
            ['2050-01-01 10:00:00', true, 0],  // visible mais en rupture
        ];
        foreach ($dates as $index => [$date, $visible, $stock]) {
            $store->update('produit', [Filter::eq('id_produit', $p[$index])], [
                'date_creation' => $date, 'visible' => $visible, 'stock' => $stock,
            ]);
        }
        $top = $repo->searchProducts(['tri' => 'nouveaute', 'par_page' => 4]);
        $ordre = [$p[6], $p[0], $p[4], $p[1], $p[2], $p[3]];
        $check('date de création prioritaire sur identifiant, modification et stock',
            $ids($top['lignes']) === array_slice($ordre, 0, 4));
        $check('masqué exclu et nombre total correct', $top['total'] === 6 && !in_array($p[5], $ids($top['lignes']), true));
        $admin = $repo->searchProducts(['tri' => 'nouveaute', 'par_page' => 4, 'admin' => true]);
        $check('administration : les produits masqués restent visibles et triés par date',
            (int) $admin['lignes'][0]['id_produit'] === $p[5]);
        $check('rupture visible : retenue si elle fait partie des derniers ajouts',
            (int) $top['lignes'][0]['id_produit'] === $p[6] && (int) $top['lignes'][0]['stock'] === 0);
        $check('même seconde : identifiant le plus récent en premier',
            (int) $top['lignes'][2]['id_produit'] === $p[4] && (int) $top['lignes'][3]['id_produit'] === $p[1]);
        $page2 = $repo->searchProducts(['tri' => 'nouveaute', 'par_page' => 4, 'page' => 2]);
        $check('pagination : aucun doublon ni omission à la frontière',
            $ids($page2['lignes']) === array_slice($ordre, 4) && $page2['pages'] === 2);
        $filtre = $repo->searchProducts(['tri' => 'nouveaute', 'par_page' => 4, 'en_stock' => true]);
        $check('filtre en stock : ordre chronologique conservé', $ids($filtre['lignes']) === [$p[0], $p[4], $p[1], $p[2]]);
        $catalogue = $repo->searchProducts(['tri' => 'nouveaute', 'par_page' => 4, 'ruptures_fin' => true]);
        $check('catalogue : ruptures en dernier tout en triant les groupes par nouveauté',
            $ids($catalogue['lignes']) === [$p[0], $p[4], $p[1], $p[2]]);
        $store->update('produit', [Filter::eq('id_produit', $p[3])], ['description' => 'Modification de test']);
        $check('modifier un ancien produit ne le transforme pas en nouvel ajout',
            $ids($repo->searchProducts(['tri' => 'nouveaute', 'par_page' => 4])['lignes']) === array_slice($ordre, 0, 4));
        foreach (array_slice($ordre, 2) as $id) {
            $store->update('produit', [Filter::eq('id_produit', $id)], ['visible' => false]);
        }
        $peu = $repo->searchProducts(['tri' => 'nouveaute', 'par_page' => 4]);
        $check('moins de quatre visibles : seulement les deux produits restants',
            $ids($peu['lignes']) === [$p[6], $p[0]] && $peu['total'] === 2);

        // Créations réelles par l'API du repository, dans la même fixture isolée.
        $store->update('produit', [], ['visible' => false]);
        $suffixe = bin2hex(random_bytes(4));
        $categorie = (int) $produits[0]['id_categorie'];
        $un = $repo->saveProduct(null, 'NOUV-A-' . $suffixe, 'Nouveauté A ' . $suffixe, null, null, 5.0, 20.0, 1, 1, $categorie, true, null);
        $deux = $repo->saveProduct(null, 'NOUV-B-' . $suffixe, 'Nouveauté B ' . $suffixe, null, null, 5.0, 20.0, 1, 1, $categorie, true, null);
        $dateUn = (string) $store->find('produit', $un)['date_creation'];
        $dateDeux = (string) $store->find('produit', $deux)['date_creation'];
        $check('créations : dates de création renseignées', $dateUn !== '' && $dateDeux !== '' && $dateDeux >= $dateUn);
        $check('deux créations successives : la plus récente en premier',
            $ids($repo->searchProducts(['tri' => 'nouveaute', 'par_page' => 4])['lignes']) === [$deux, $un]);
        $store->update('produit', [Filter::eq('id_produit', $un)], ['date_creation' => '2026-01-01 12:00:00']);
        $store->update('produit', [Filter::eq('id_produit', $deux)], ['date_creation' => '2026-01-01 12:00:00']);
        $check('créations à la même seconde : départage déterministe par id décroissant',
            $ids($repo->searchProducts(['tri' => 'nouveaute', 'par_page' => 4])['lignes']) === [$deux, $un]);
        $store->update('produit', [Filter::eq('id_produit', $deux)], ['visible' => false]);
        $check('masquage du plus récent : il disparaît immédiatement',
            $ids($repo->searchProducts(['tri' => 'nouveaute', 'par_page' => 4])['lignes']) === [$un]);
    } finally {
        $store->rollback();
    }
    $check('transaction annulée : fixture restaurée', $ids($repo->searchProducts(['tri' => 'nouveaute', 'par_page' => 4])['lignes']) === $ids($accueil['lignes']));

    return ['tests' => $tests, 'echecs' => $echecs];
}
