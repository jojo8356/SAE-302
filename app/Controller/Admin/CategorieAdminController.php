<?php

declare(strict_types=1);

/**
 * MiniShop — gestion des catégories (UC-11, EF-ADM-03, RB-07/08/14).
 */

namespace App\Controller\Admin;

use App\Config\Database;
use App\Controller\Controller;
use App\Model\Data\BusinessError;
use App\Repository\CategorieRepository;
use App\Security\Auth;

final class CategorieAdminController extends Controller
{
    public function index(): void
    {
        Auth::exigeAdmin();
        $store = Database::store();
        $repo = new CategorieRepository($store);

        $this->render('admin/categories', [
            'categories' => $repo->listerAvecCompteur(),
            'erreurs' => [],
        ], 'Back-office — catégories');
    }

    public function creer(): void
    {
        $this->verifierCsrf();
        Auth::exigeAdmin();
        $repo = new CategorieRepository(Database::store());
        try {
            $slug = $this->str('slug', 120);
            if (!$slug) {
                $slug = null;
            }
            $description = $this->str('description', 500);
            if (!$description) {
                $description = null;
            }
            $repo->saveCategory(
                null,
                $this->str('nom', 100),
                $slug,
                $description,
            );
            $this->flashSucces('Catégorie enregistrée.');
        } catch (BusinessError $e) {
            $this->flashErreur($e->messageHumain());
        }
        $this->rediriger('/admin/categories');
    }

    public function modifier(string $id): void
    {
        $this->verifierCsrf();
        Auth::exigeAdmin();
        $repo = new CategorieRepository(Database::store());
        try {
            $slug = $this->str('slug', 120);
            if (!$slug) {
                $slug = null;
            }
            $description = $this->str('description', 500);
            if (!$description) {
                $description = null;
            }
            $repo->saveCategory(
                (int) $id,
                $this->str('nom', 100),
                $slug,
                $description,
            );
            $this->flashSucces('Catégorie mise à jour.');
        } catch (BusinessError $e) {
            $this->flashErreur($e->messageHumain());
        }
        $this->rediriger('/admin/categories');
    }

    /**
     * Étape de confirmation avant suppression : action définitive, un
     * simple clic ne doit pas suffire (page « Êtes-vous sûr ? », GET
     * sans effet de bord ; seul le POST supprimer() exécute).
     */
    public function confirmerSuppression(string $id): void
    {
        Auth::exigeAdmin();
        $categorie = (new CategorieRepository(Database::store()))->find((int) $id);
        if ($categorie === null) {
            $this->flashErreur('Catégorie introuvable.');
            $this->rediriger('/admin/categories');
        }

        $this->render('admin/confirmer_suppression_categorie', [
            'categorie' => $categorie,
        ], 'Supprimer la catégorie ' . $categorie['nom'] . ' ?');
    }

    /** RB-14 : suppression refusée si la catégorie est occupée. */
    public function supprimer(string $id): void
    {
        $this->verifierCsrf();
        Auth::exigeAdmin();
        $repo = new CategorieRepository(Database::store());
        try {
            $repo->deleteCategory((int) $id);
            $this->flashSucces('Catégorie supprimée.');
        } catch (BusinessError $e) {
            $this->flashErreur($e->messageHumain());
        }
        $this->rediriger('/admin/categories');
    }
}
