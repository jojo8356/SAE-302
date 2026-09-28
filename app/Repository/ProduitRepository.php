<?php

declare(strict_types=1);

/**
 * MiniShop — produits & recherche catalogue (portage de sp_search_products,
 * sp_save_product, sp_delete_product, sp_adjust_stock).
 *
 * La recherche applique EF-VIS-02/03 : mot-clé insensible casse/accents
 * (nom, description, référence), filtres catégorie / prix min-max / stock,
 * cumulables, tri en LISTE BLANCHE, pagination bornée (12 par défaut, max 60).
 */

namespace App\Repository;

use App\Model\Data\BusinessError;
use App\Model\Data\Filter;
use App\Model\Data\StoreInterface;
use App\Model\Data\Text;
use App\Model\Data\Views;

final class ProduitRepository
{
    /** Liste blanche des tris (D4-L8-01 : jamais de tri sorti du navigateur). */
    public const TRIS = ['nom', 'prix_asc', 'prix_desc', 'nouveaute', 'stock'];

    /** Spécification de tri évaluée à l'exécution (une constante de classe ne peut pas appeler de méthode). */
    public static function specTri(string $tri): array
    {
        return match ($tri) {
            'prix_asc' => [Filter::sort('prix_ttc'), Filter::sort('id_produit')],
            'prix_desc' => [Filter::sort('prix_ttc', 'desc'), Filter::sort('id_produit')],
            'nouveaute' => [Filter::sort('date_creation', 'desc'), Filter::sort('id_produit')],
            'stock' => [Filter::sort('stock', 'desc'), Filter::sort('id_produit')],
            default => [Filter::sort('nom'), Filter::sort('id_produit')],
        };
    }

    public function __construct(private readonly StoreInterface $store)
    {
    }

    public function find(int $idProduit): ?array
    {
        return $this->store->find('produit', $idProduit);
    }

    /** Fiche produit par slug — 404 côté contrôleur si absent ou masqué (RB-19). */
    public function findVisibleBySlug(string $slug): ?array
    {
        return $this->store->findOne('produit', [Filter::eq('slug', $slug), Filter::eq('visible', 1)]);
    }

    /** Disponibilité instantanée d'un produit (contrôle serveur avant tout ajout panier). */
    public function disponible(int $idProduit): int
    {
        $produit = $this->store->find('produit', $idProduit);

        return $produit === null ? 0 : max(0, (int) $produit['stock']);
    }

    /**
     * sp_search_products — recherche + filtres + tri + pagination.
     *
     * @param array{mot_cle?: ?string, id_categorie?: ?int, prix_min?: ?float, prix_max?: ?float,
     *               en_stock?: bool, tri?: ?string, page?: int, par_page?: int, admin?: bool} $criteres
     * @return array{lignes: list<array<string,mixed>>, total: int, page: int, par_page: int, pages: int}
     */
    public function searchProducts(array $criteres = []): array
    {
        $where = [];
        $admin = (bool) ($criteres['admin'] ?? false);

        if (!$admin) {
            $where[] = Filter::eq('visible', 1); // RB-19
        }

        $motCle = trim((string) ($criteres['mot_cle'] ?? ''));
        if ($motCle !== '') {
            $motif = '%' . $motCle . '%';
            $where[] = Filter::or([
                Filter::like('nom', $motif),
                Filter::like('description', $motif),
                Filter::like('reference', $motif),
            ]);
        }

        if (!empty($criteres['id_categorie'])) {
            $where[] = Filter::eq('id_categorie', (int) $criteres['id_categorie']);
        }

        $where[] = Filter::gte('prix_ttc', max(0.0, (float) ($criteres['prix_min'] ?? 0)));
        $where[] = Filter::lte('prix_ttc', (float) ($criteres['prix_max'] ?? 99999999.99));

        if (!empty($criteres['en_stock'])) {
            $where[] = Filter::gt('stock', 0);
        }

        $tri = $criteres['tri'] ?? 'nom';
        if (!in_array($tri, self::TRIS, true)) { // liste blanche (D4-L8-01)
            $tri = 'nom';
        }
        $order = self::specTri($tri);

        $parPage = min(max((int) ($criteres['par_page'] ?? 12), 1), 60); // borne EF-VIS-03
        $page = max((int) ($criteres['page'] ?? 1), 1);
        $offset = ($page - 1) * $parPage;

        $total = $this->store->count('produit', $where);

        // enrichissement façon v_catalogue : catégorie jointe + état de stock dérivé
        $categories = $this->store->all('categorie');
        $lignes = [];
        foreach ($this->store->select('produit', ['where' => $where, 'order' => $order, 'limit' => $parPage, 'offset' => $offset]) as $p) {
            $categorie = $categories[(int) $p['id_categorie']] ?? null;
            $p['id_categorie'] = (int) $p['id_categorie'];
            $p['categorie'] = $categorie['nom'] ?? '—';
            $p['categorie_slug'] = $categorie['slug'] ?? null;
            $p['etat_stock'] = Views::etatStock($p);
            $lignes[] = $p;
        }

        return [
            'lignes' => $lignes,
            'total' => $total,
            'page' => $page,
            'par_page' => $parPage,
            'pages' => max(1, (int) ceil($total / $parPage)),
        ];
    }

    /**
     * sp_save_product — création / modification (EF-ADM-01/02).
     * p_id_produit null => création. Le prix TTC est DÉRIVÉ par le
     * déclencheur trg_produit_ttc (RB-16), jamais saisi.
     *
     * @return int id du produit
     */
    public function saveProduct(
        ?int $idProduit,
        string $reference,
        string $nom,
        ?string $slug,
        ?string $description,
        float $prixHt,
        ?float $tva,
        int $stock,
        ?int $seuilAlerte,
        int $idCategorie,
        ?bool $visible,
        ?string $imageUrl,
    ): int {
        if ($prixHt <= 0) {
            throw new BusinessError('RB02_PRIX_DOIT_ETRE_STRICTEMENT_POSITIF');
        }
        if ($stock < 0) {
            throw new BusinessError('RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF');
        }
        if (trim($nom) === '') {
            throw new BusinessError('NOM_REQUIS');
        }
        if ($this->store->find('categorie', $idCategorie) === null) {
            throw new BusinessError('CATEGORIE_INTROUVABLE');
        }

        $slug = ($slug !== null && trim($slug) !== '') ? Text::slug($slug) : Text::slug($nom);
        $reference = trim($reference);

        $exclusion = $idProduit ?? 0;
        if ($this->store->exists('produit', [Filter::eq('reference', $reference), Filter::neq('id_produit', $exclusion)])) {
            throw new BusinessError('REFERENCE_DEJA_UTILISEE');
        }
        if ($this->store->exists('produit', [Filter::eq('slug', $slug), Filter::neq('id_produit', $exclusion)])) {
            throw new BusinessError('SLUG_DEJA_UTILISE');
        }

        $donnees = [
            'reference' => $reference,
            'nom' => trim($nom),
            'slug' => $slug,
            'description' => $description,
            'prix_ht' => $prixHt,
            'tva' => $tva ?? 20.00,
            'stock' => $stock,
            'seuil_alerte' => $seuilAlerte ?? 3,
            'id_categorie' => $idCategorie,
            'image_url' => $imageUrl,
        ];

        if ($idProduit === null) {
            $donnees['visible'] = $visible ?? true;

            return $this->store->insert('produit', $donnees);
        }

        if ($visible !== null) {
            $donnees['visible'] = $visible;
        }
        $this->store->update('produit', [Filter::eq('id_produit', $idProduit)], $donnees);

        return $idProduit;
    }

    /**
     * sp_delete_product — suppression protégée (EF-ADM-02, RB-14/RB-17) :
     * un produit déjà commandé est MASQUÉ (visible=0) afin de conserver
     * l'historique des lignes de commande ; sinon suppression réelle.
     */
    public function deleteProduct(int $idProduit): void
    {
        if ($this->store->find('produit', $idProduit) === null) {
            throw new BusinessError('PRODUIT_INTROUVABLE');
        }
        if ($this->store->exists('ligne_commande', [Filter::eq('id_produit', $idProduit)])) {
            $this->store->update('produit', [Filter::eq('id_produit', $idProduit)], ['visible' => false]);

            return;
        }
        $this->store->delete('produit', [Filter::eq('id_produit', $idProduit)]);
    }

    /**
     * sp_adjust_stock — réappro / inventaire (EF-ADM-04, RB-03).
     * mode 'SET' (valeur absolue) ou 'DELTA' (mouvement ±). Toute écriture
     * de stock porte un MOTIF (règle non fonctionnelle §6.3, EF-ADM-04).
     */
    public function adjustStock(int $idProduit, string $mode, int $quantite, string $motif): int
    {
        if (trim($motif) === '') {
            throw new BusinessError('MOTIF_STOCK_REQUIS');
        }
        $produit = $this->store->find('produit', $idProduit);
        if ($produit === null) {
            throw new BusinessError('PRODUIT_INTROUVABLE');
        }

        $nouveau = $mode === 'DELTA' ? (int) $produit['stock'] + $quantite : $quantite;
        if ($nouveau < 0) {
            throw new BusinessError('RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF');
        }

        $this->store->update('produit', [Filter::eq('id_produit', $idProduit)], ['stock' => $nouveau]);

        return $nouveau;
    }

    /** État des stocks enrichi (vue v_etat_stock) pour le back-office. */
    public function etatStocks(): array
    {
        return (new Views($this->store))->etatStocks();
    }

    /** Produits en alerte de stock (EF-ADM-05 : seuil atteint ou rupture). */
    public function alertesStock(): array
    {
        return array_values(array_filter(
            $this->etatStocks(),
            static fn (array $ligne): bool => $ligne['etat'] !== 'DISPONIBLE'
        ));
    }
}
