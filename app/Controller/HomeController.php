<?php

declare(strict_types=1);

/**
 * MiniShop — pages d'accueil, catégories et pages légales statiques
 * (UC-01 « Consulter les catégories », EF-GEN-05).
 */

namespace App\Controller;

use App\Config\Database;
use App\Model\Data\Views;
use App\Repository\CategorieRepository;
use App\Repository\ProduitRepository;

final class HomeController extends Controller
{
    public function index(): void
    {
        $store = Database::store();
        $categories = (new CategorieRepository($store))->listerAvecCompteur();
        $nouveautes = (new ProduitRepository($store))->searchProducts(['tri' => 'nouveaute', 'par_page' => 4]);

        $this->render('home/index', ['categories' => $categories, 'nouveautes' => $nouveautes['lignes']], 'MiniShop — matériel informatique & objets connectés');
    }

    public function categories(): void
    {
        $store = Database::store();
        $categories = (new CategorieRepository($store))->listerAvecCompteur();

        $this->render('home/categories', ['categories' => $categories], 'Nos catégories');
    }

    public function mentionsLegales(): void
    {
        $this->render('home/mentions_legales', [], 'Mentions légales');
    }

    public function cgv(): void
    {
        $this->render('home/cgv', [], 'Conditions générales de vente');
    }

    /** Vue de synthèse du moteur de données (page « à propos » du projet). */
    public function aPropos(): void
    {
        $store = Database::store();
        $statistiques = [
            'produits' => $store->count('produit'),
            'visibles' => $store->count('produit', [['visible', '=', 1]]),
            'clients' => $store->count('client'),
            'commandes' => $store->count('commande'),
            'parametres' => $store->count('parametre'),
        ];

        $this->render('home/apropos', ['statistiques' => $statistiques], 'À propos du moteur de données');
    }

    public function page404(): void
    {
        $this->render('errors/404', [], 'Page introuvable');
    }

    public function page403(): void
    {
        $this->render('errors/403', [], 'Accès refusé');
    }
}
