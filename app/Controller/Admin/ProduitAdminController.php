<?php

declare(strict_types=1);

/**
 * MiniShop — gestion des produits (UC-10, EF-ADM-01/02, RB-02/07/14/16/17).
 */

namespace App\Controller\Admin;

use App\Config\Database;
use App\Controller\Controller;
use App\Model\Data\BusinessError;
use App\Repository\CategorieRepository;
use App\Repository\ProduitRepository;
use App\Security\Auth;

final class ProduitAdminController extends Controller
{
    public function index(): void
    {
        Auth::exigeAdmin();
        $store = Database::store();
        $repo = new ProduitRepository($store);

        $categorie = $this->int('cat');
        if (!$categorie) {
            $categorie = null;
        }

        $resultat = $repo->searchProducts([
            'mot_cle' => $this->str('q', 120),
            'id_categorie' => $categorie,
            'tri' => $this->str('tri', 20),
            'page' => max(1, $this->int('page') ?? 1),
            'par_page' => 20,
            'admin' => true, // RB-19 : le back-office voit aussi les produits masqués
        ]);

        $this->render('admin/produits', [
            'resultat' => $resultat,
            'categories' => (new CategorieRepository($store))->listerAvecCompteur(),
            'q' => $this->str('q', 120),
            'cat' => $this->int('cat'),
        ], 'Back-office — produits');
    }

    public function formulaireNouveau(): void
    {
        Auth::exigeAdmin();
        $this->rendreFormulaire(null);
    }

    public function creer(): void
    {
        $this->enregistrer(null);
    }

    public function formulaireModifier(string $id): void
    {
        Auth::exigeAdmin();
        $produit = (new ProduitRepository(Database::store()))->find((int) $id);
        if ($produit === null) {
            $this->flashErreur('Produit introuvable.');
            $this->rediriger('/admin/produits');
        }
        $this->rendreFormulaire($produit);
    }

    public function enregistrerModification(string $id): void
    {
        $this->enregistrer((int) $id);
    }

    private function enregistrer(?int $idProduit): void
    {
        $this->verifierCsrf();
        Auth::exigeAdmin();
        $store = Database::store();

        $nom = $this->str('nom', 150);
        $slug = $this->str('slug', 180);
        $reference = $this->str('reference', 30);

        $produitInitial = null;
        if ($idProduit !== null) {
            $produitInitial = (new ProduitRepository($store))->find($idProduit);
        }

        $description = $this->str('description');
        if (!$description) {
            $description = null;
        }
        $imageUrl = $this->str('image_url', 255);
        if (!$imageUrl) {
            $imageUrl = null;
        }

        try {
            (new ProduitRepository($store))->saveProduct(
                $idProduit,
                $reference,
                $nom,
                $slug,
                $description,
                $this->float('prix_ht') ?? 0.0,
                $this->float('tva') ?? 20.0,
                $this->int('stock') ?? 0,
                $this->int('seuil_alerte') ?? 3,
                $this->int('id_categorie') ?? 0,
                $this->bool('visible'),
                $imageUrl,
            );
            $message = 'Produit mis à jour.';
            if ($idProduit === null) {
                $message = 'Produit créé.';
            }
            $this->flashSucces($message);
            $this->rediriger('/admin/produits');
        } catch (BusinessError $e) {
            $erreurs = [$e->messageHumain()];
            $this->rendreFormulaire(
                $produitInitial ?? [],
                $erreurs,
                ['nom' => $nom, 'slug' => $slug, 'reference' => $reference,
                 'description' => $this->str('description'), 'prix_ht' => $this->float('prix_ht'),
                 'tva' => $this->float('tva'), 'stock' => $this->int('stock'),
                 'seuil_alerte' => $this->int('seuil_alerte'), 'id_categorie' => $this->int('id_categorie'),
                 'visible' => $this->bool('visible'), 'image_url' => $this->str('image_url', 255)],
            );
        }
    }

    /**
     * EF-ADM-02 — suppression protégée : masquage si déjà commandé
     * (RB-14/RB-17), suppression réelle sinon.
     */
    public function supprimer(string $id): void
    {
        $this->verifierCsrf();
        Auth::exigeAdmin();
        try {
            (new ProduitRepository(Database::store()))->deleteProduct((int) $id);
            $this->flashSucces('Produit supprimé ou masqué (déjà commandé).');
        } catch (BusinessError $e) {
            $this->flashErreur($e->messageHumain());
        }
        $this->rediriger('/admin/produits');
    }

    /** @param array<string, mixed>|null $produit */
    private function rendreFormulaire(?array $produit, array $erreurs = [], array $valeurs = []): void
    {
        $store = Database::store();

        $titre = 'Nouveau produit';
        if ($produit !== null) {
            $titre = 'Modifier « ' . ($produit['nom'] ?? '') . ' »';
        }

        $this->render('admin/produit_form', [
            'produit' => $produit,
            'categories' => (new CategorieRepository($store))->listerAvecCompteur(),
            'erreurs' => $erreurs,
            'valeurs' => $valeurs,
            'tva_defaut' => (new \App\Repository\ParametreRepository($store))->tvaDefaut(),
        ], $titre);
    }
}
