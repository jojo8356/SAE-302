<?php

declare(strict_types=1);

/** Scénarios partagés JSON / MariaDB, à exécuter uniquement sur une fixture isolée. */

use App\Model\Data\Filter;
use App\Model\Data\StoreInterface;
use App\Model\Data\Text;
use App\Repository\ProduitRepository;

/**
 * Vérifie les pages individuellement contre une sélection filtrée hors repository.
 * Les jeux incluent des requêtes par nom, référence, description, casse/accents,
 * catégorie, bornes de prix et stock, ainsi que les frontières de pagination.
 *
 * @return array{tests:int, echecs:int}
 */
function verifierCatalogueStock(StoreInterface $store): array
{
    $repo = new ProduitRepository($store);
    $tests = 0;
    $echecs = 0;
    $cas = [
        [], ['mot_cle' => 'batterie'], ['mot_cle' => 'POWER'],
        ['mot_cle' => '65 W'], ['mot_cle' => 'ÉCRAN'],
        ['mot_cle' => 'CASQUE'], ['mot_cle' => 'POWER-010'],
        ['mot_cle' => 'introuvable-999'], ['id_categorie' => 3],
        ['mot_cle' => 'batterie', 'id_categorie' => 3, 'prix_min' => 20, 'prix_max' => 90],
        ['mot_cle' => 'POWER', 'en_stock' => true],
        ['id_categorie' => 3, 'prix_max' => 60],
        ['prix_min' => 999999, 'prix_max' => 1000000],
    ];
    // Chaque nom complet et chaque référence du jeu sont aussi recherchés,
    // y compris les produits masqués : on ne fige pas la taille du catalogue.
    foreach ($store->select('produit') as $produit) {
        foreach (['reference', 'nom'] as $champ) {
            $motCle = (string) ($produit[$champ] ?? '');
            // Ces cas utilisent la recherche littérale, pas les jokers SQL LIKE.
            if ($motCle !== '' && !str_contains($motCle, '%') && !str_contains($motCle, '_')) {
                $cas[] = ['mot_cle' => $motCle];
            }
        }
    }
    // Ordres explicités ici, sans passer par searchProducts() ni specTri().
    $ordres = [
        'nom' => [Filter::sort('nom'), Filter::sort('id_produit')],
        'prix_asc' => [Filter::sort('prix_ttc'), Filter::sort('id_produit')],
        'prix_desc' => [Filter::sort('prix_ttc', 'desc'), Filter::sort('id_produit')],
        'nouveaute' => [Filter::sort('date_creation', 'desc'), Filter::sort('id_produit', 'desc')],
        'stock' => [Filter::sort('stock', 'desc'), Filter::sort('id_produit')],
        'inconnu' => [Filter::sort('nom'), Filter::sort('id_produit')],
    ];
    foreach ($ordres as $tri => $ordre) {
        $source = $store->select('produit', ['order' => $ordre]);
        foreach ($cas as $criteres) {
            // Oracle : filtre manuellement la table complète, puis partitionne
            // sans pagination ; aucune méthode du repository n'est réutilisée.
            $selection = array_values(array_filter($source, static function (array $p) use ($criteres): bool {
                if ((int) $p['visible'] !== 1) {
                    return false;
                }
                if (isset($criteres['id_categorie']) && (int) $p['id_categorie'] !== $criteres['id_categorie']) {
                    return false;
                }
                if ((float) $p['prix_ttc'] < (float) ($criteres['prix_min'] ?? 0)
                    || (float) $p['prix_ttc'] > (float) ($criteres['prix_max'] ?? 99999999.99)) {
                    return false;
                }
                if (!empty($criteres['en_stock']) && (int) $p['stock'] <= 0) {
                    return false;
                }
                $mot = Text::fold((string) ($criteres['mot_cle'] ?? ''));
                if ($mot === '') {
                    return true;
                }
                foreach (['nom', 'description', 'reference'] as $champ) {
                    if (str_contains(Text::fold((string) ($p[$champ] ?? '')), $mot)) {
                        return true;
                    }
                }
                return false;
            }));
            $attendus = array_merge(
                array_values(array_filter($selection, static fn (array $p): bool => (int) $p['stock'] > 0)),
                array_values(array_filter($selection, static fn (array $p): bool => (int) $p['stock'] <= 0)),
            );
            $idsAttendus = array_map('intval', array_column($attendus, 'id_produit'));
            foreach ([1, 3, 5, 12, 60] as $taille) {
                ++$tests;
                $nbPages = max(1, (int) ceil(count($attendus) / $taille));
                $idsObtenus = [];
                $erreur = '';
                for ($page = 1; $page <= $nbPages; ++$page) {
                    $reponse = $repo->searchProducts(array_merge($criteres, [
                        'ruptures_fin' => true, 'tri' => $tri, 'par_page' => $taille, 'page' => $page,
                    ]));
                    $idsPage = array_map('intval', array_column($reponse['lignes'], 'id_produit'));
                    $idsObtenus = array_merge($idsObtenus, $idsPage);
                    if ($reponse['total'] !== count($attendus) || $reponse['pages'] !== $nbPages
                        || $reponse['page'] !== $page || $reponse['par_page'] !== $taille
                        || $idsPage !== array_slice($idsAttendus, ($page - 1) * $taille, $taille)) {
                        $erreur = 'contenu ou métadonnées page ' . $page;
                    }
                }
                if ($idsObtenus !== $idsAttendus || count($idsObtenus) !== count(array_unique($idsObtenus))) {
                    $erreur = 'ordre global, doublon ou résultat manquant';
                }
                $horsBornes = $repo->searchProducts(array_merge($criteres, [
                    'ruptures_fin' => true, 'tri' => $tri, 'par_page' => $taille, 'page' => $nbPages + 1,
                ]));
                if ($horsBornes['lignes'] !== [] || $horsBornes['total'] !== count($attendus)) {
                    $erreur = 'page hors limites';
                }
                if ($erreur !== '') {
                    ++$echecs;
                    echo '  ❌ tri=' . $tri . ', taille=' . $taille . ', filtres=' . json_encode($criteres)
                        . ' : ' . $erreur . "\n";
                }
            }
        }
    }

    return ['tests' => $tests, 'echecs' => $echecs];
}
