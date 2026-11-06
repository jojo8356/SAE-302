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
     *
     * Unicité (uk_categorie_nom / uk_categorie_slug) : un nom — ou un
     * slug — déjà porté par une AUTRE catégorie est REFUSÉ avant toute
     * écriture (NOM_DEJA_UTILISE / SLUG_CATEGORIE_DEJA_UTILISE), en
     * création comme en renommage. La comparaison passe par le magasin
     * (Filter::eq = insensible casse/accents, comme la collation SQL).
     * Divergence assumée avec l'ancien portage « ON DUPLICATE KEY
     * UPDATE » : fusionner en silence écrasait la description de la
     * catégorie existante (bug « Admin > Catégories : un doublon écrase
     * la catégorie existante »), alors que l'attendu métier est un refus.
     */
    public function saveCategory(?int $idCategorie, string $nom, ?string $slug, ?string $description): int
    {
        $nom = trim($nom);
        if ($nom === '') {
            throw new BusinessError('NOM_REQUIS');
        }
        if ($slug !== null && trim($slug) !== '') {
            $slug = Text::slug($slug);
        } else {
            $slug = Text::slug($nom);
        }

        if ($idCategorie !== null && $this->store->find('categorie', $idCategorie) === null) {
            throw new BusinessError('CATEGORIE_INTROUVABLE');
        }

        $doublonNom = $this->store->findOne('categorie', [Filter::eq('nom', $nom)]);
        if ($doublonNom !== null && (int) $doublonNom['id_categorie'] !== $idCategorie) {
            throw new BusinessError('NOM_DEJA_UTILISE');
        }
        $doublonSlug = $this->store->findOne('categorie', [Filter::eq('slug', $slug)]);
        if ($doublonSlug !== null && (int) $doublonSlug['id_categorie'] !== $idCategorie) {
            throw new BusinessError('SLUG_CATEGORIE_DEJA_UTILISE');
        }

        if ($idCategorie === null) {
            return $this->store->insert('categorie', ['nom' => $nom, 'slug' => $slug, 'description' => $description]);
        }

        $modifiees = (int) $this->store->update('categorie', [Filter::eq('id_categorie', $idCategorie)], [
            'nom' => $nom,
            'slug' => $slug,
            'description' => $description,
        ]);
        if ($modifiees > 0) {
            return $idCategorie;
        }

        throw new BusinessError('AUCUNE_MODIFICATION');
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
