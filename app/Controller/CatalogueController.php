<?php

declare(strict_types=1);

/**
 * MiniShop — catalogue, recherche et fiche produit (UC-01/02/03, EF-VIS-01…04).
 *
 * Tous les filtres arrivent par GET, sont validés côté serveur (jamais de
 * trust sur le navigateur), se cumulent et se conservent dans l'URL (EF-VIS-03) ;
 * le tri vient d'une liste blanche (ProduitRepository::TRIS, D4-L8-01).
 */

namespace App\Controller;

use App\Config\Database;
use App\Repository\CategorieRepository;
use App\Repository\ProduitRepository;

final class CatalogueController extends Controller
{
    private const TRIS_LIBELLES = [
        'nom' => 'Nom (A→Z)',
        'prix_asc' => 'Prix croissant',
        'prix_desc' => 'Prix décroissant',
        'nouveaute' => 'Nouveautés',
        'stock' => 'Stock',
    ];

    public function catalogue(): void
    {
        $this->rendreRecherche('catalogue/index', 'Catalogue');
    }

    public function recherche(): void
    {
        $this->rendreRecherche('catalogue/index', 'Recherche', true);
    }

    private function rendreRecherche(string $vue, string $titre, bool $depuisRecherche = false): void
    {
        $store = Database::store();
        $produits = new ProduitRepository($store);
        $categories = (new CategorieRepository($store))->listerAvecCompteur();

        $criteres = [
            'mot_cle' => $this->str('q', 120),
            'id_categorie' => $this->int('cat') ?: null,
            'prix_min' => $this->float('prix_min'),
            'prix_max' => $this->float('prix_max'),
            'en_stock' => $this->bool('stock'),
            'tri' => $this->str('tri', 20),
            'page' => max(1, $this->int('page') ?? 1),
            'par_page' => 12,
        ];

        $resultat = $produits->searchProducts($criteres);

        // les filtres se conservent dans l'URL (EF-VIS-03)
        $parametresUrl = array_filter([
            'q' => $criteres['mot_cle'] !== '' ? $criteres['mot_cle'] : null,
            'cat' => $criteres['id_categorie'],
            'prix_min' => $criteres['prix_min'],
            'prix_max' => $criteres['prix_max'],
            'stock' => $criteres['en_stock'] ? '1' : null,
            'tri' => $criteres['tri'] !== '' && $criteres['tri'] !== 'nom' ? $criteres['tri'] : null,
        ], static fn (mixed $v) => $v !== null && $v !== '');

        $this->render($vue, [
            'resultat' => $resultat,
            'criteres' => $criteres,
            'categories' => $categories,
            'tris' => self::TRIS_LIBELLES,
            'parametres_url' => $parametresUrl,
            'est_recherche' => $depuisRecherche,
        ], $titre);
    }

    /** UC-03 — fiche produit : prix TTC/TVA, disponibilité, 404 si masquée (RB-19). */
    public function produit(string $slug): void
    {
        $store = Database::store();
        $produit = (new ProduitRepository($store))->findVisibleBySlug($slug);
        if ($produit === null) {
            http_response_code(404);
            (new HomeController())->page404();

            return;
        }
        $categorie = (new CategorieRepository($store))->find((int) $produit['id_categorie']);

        $this->render('catalogue/produit', [
            'produit' => $produit,
            'categorie' => $categorie,
            'etat_stock' => \App\Model\Data\Views::etatStock($produit),
        ], $produit['nom'] . ' — MiniShop');
    }
}
