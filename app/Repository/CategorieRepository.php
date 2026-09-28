<?php

declare(strict_types=1);

/**
 * MiniShop — catégories (portage de sp_save_category, sp_delete_category).
 * RB-08 : une catégorie peut contenir 0..N produits (catégorie vide autorisée).
 */

namespace App\Repository;

use App\Model\Data\BusinessError;
use App\Model\Data\Filter;
use App\Model\Data\StoreInterface;
use App\Model\Data\Text;

final class CategorieRepository
{
    public function __construct(private readonly StoreInterface $store)
    {
    }

    /** Catégorie par id. */
    public function find(int $idCategorie): ?array
    {
        return $this->store->find('categorie', $idCategorie);
    }

    /** Catégorie par slug (routes /catalogue?cat=…, fiche produit). */
    public function findBySlug(string $slug): ?array
    {
        return $this->store->findOne('categorie', [Filter::eq('slug', $slug)]);
    }

    /**
     * Toutes les catégories avec le nombre de produits VISIBLES
     * (EF-VIS-01 « consulter les catégories »).
     */
    public function listerAvecCompteur(): array
    {
        $categories = $this->store->select('categorie', ['order' => [Filter::sort('nom')]]);
        $visibles = [];
        foreach ($this->store->select('produit', ['where' => [Filter::eq('visible', 1)]]) as $produit) {
            $visibles[(int) $produit['id_categorie']] = ($visibles[(int) $produit['id_categorie']] ?? 0) + 1;
        }

        return array_map(static function (array $categorie) use ($visibles): array {
            $categorie['nb_produits'] = $visibles[(int) $categorie['id_categorie']] ?? 0;

            return $categorie;
        }, $categories);
    }

    /**
     * sp_save_category — création / renommage (EF-ADM-03).
     * p_id_categorie null => création. Le slug vide est normalisé
     * (portage du trigger trg_categorie_slug, ici en amont pour l'app).
     */
    public function saveCategory(?int $idCategorie, string $nom, ?string $slug, ?string $description): int
    {
        $nom = trim($nom);
        if ($nom === '') {
            throw new BusinessError('NOM_REQUIS');
        }
        $slug = $slug !== null && trim($slug) !== '' ? Text::slug($slug) : Text::slug($nom);

        if ($idCategorie === null) {
            $existant = $this->store->findOne('categorie', [Filter::eq('nom', $nom)]);
            if ($existant !== null) {
                // équivalent du ON DUPLICATE KEY : on met à jour au lieu d'insérer
                $this->store->update('categorie', [Filter::eq('id_categorie', $existant['id_categorie'])], [
                    'slug' => $slug,
                    'description' => $description,
                ]);

                return (int) $existant['id_categorie'];
            }

            return $this->store->insert('categorie', ['nom' => $nom, 'slug' => $slug, 'description' => $description]);
        }

        if ($this->store->find('categorie', $idCategorie) === null) {
            throw new BusinessError('CATEGORIE_INTROUVABLE');
        }

        return (int) $this->store->update('categorie', [Filter::eq('id_categorie', $idCategorie)], [
            'nom' => $nom,
            'slug' => $slug,
            'description' => $description,
        ]) > 0 ? $idCategorie : throw new BusinessError('AUCUNE_MODIFICATION');
    }

    /**
     * sp_delete_category — RB-14 : on ne supprime pas une catégorie occupée.
     */
    public function deleteCategory(int $idCategorie): void
    {
        if ($this->store->find('categorie', $idCategorie) === null) {
            throw new BusinessError('CATEGORIE_INTROUVABLE');
        }
        $this->store->delete('categorie', [Filter::eq('id_categorie', $idCategorie)]);
        // le déclencheur trg_categorie_delete (CATEGORIE_NON_VIDE) reste la
        // dernière barrière si un produit a été ajouté entre-temps
    }
}
