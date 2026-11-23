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
use App\Model\Data\StockMovementAuditor;
use App\Model\Data\StoredProcedureExecutor;
use App\Model\Data\StoredProcedureResultExecutor;
use App\Model\Data\StoreInterface;
use App\Model\Data\Text;
use App\Model\Data\Views;
use UnexpectedValueException;

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
            // À la même seconde, l'identifiant auto-incrémenté le plus élevé est le dernier inséré.
            'nouveaute' => [Filter::sort('date_creation', 'desc'), Filter::sort('id_produit', 'desc')],
            'stock' => [Filter::sort('stock', 'desc'), Filter::sort('id_produit')],
            default => [Filter::sort('nom'), Filter::sort('id_produit')],
        };
    }

    /** Injecte le store abstrait (SqlStore ou JsonStore ; voir ADR-0001). */
    public function __construct(private readonly StoreInterface $store)
    {
    }

    /** Retourne un produit par son identifiant primaire, qu'il soit visible ou masqué. */
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

        if ($produit === null) {
            return 0;
        }

        return max(0, (int) $produit['stock']);
    }

    /**
     * sp_search_products — recherche + filtres + tri + pagination.
     *
     * @param array{mot_cle?: ?string, id_categorie?: ?int, prix_min?: ?float, prix_max?: ?float,
     *               en_stock?: bool, tri?: ?string, page?: int, par_page?: int, admin?: bool, ruptures_fin?: bool} $criteres
     * @return array{lignes: list<array<string,mixed>>, total: int, page: int, par_page: int, pages: int}
     */
    public function searchProducts(array $criteres = []): array
    {
        if ($this->store instanceof StoredProcedureResultExecutor) {
            return $this->searchProductsSql($criteres);
        }

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

        if (!empty($criteres['ruptures_fin'])) {
            // Deux groupes consécutifs AVANT pagination. À l'intérieur de chacun,
            // le tri choisi et sa clé de départage restent inchangés.
            $disponibles = [...$where, Filter::gt('stock', 0)];
            $ruptures = [...$where, Filter::lte('stock', 0)];
            $nombreDisponibles = $this->store->count('produit', $disponibles);
            $produits = [];
            if ($offset < $nombreDisponibles) {
                $produits = $this->store->select('produit', [
                    'where' => $disponibles, 'order' => $order, 'limit' => $parPage, 'offset' => $offset,
                ]);
            }
            $placesRestantes = $parPage - count($produits);
            if ($placesRestantes > 0 && $offset + count($produits) < $total) {
                $produits = array_merge($produits, $this->store->select('produit', [
                    'where' => $ruptures, 'order' => $order, 'limit' => $placesRestantes,
                    'offset' => max(0, $offset - $nombreDisponibles),
                ]));
            }
        } else {
            $produits = $this->store->select('produit', [
                'where' => $where, 'order' => $order, 'limit' => $parPage, 'offset' => $offset,
            ]);
        }

        // enrichissement façon v_catalogue : catégorie jointe + état de stock dérivé
        $categories = $this->store->all('categorie');
        $lignes = [];
        foreach ($produits as $p) {
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

    /** Exécution SQL de la recherche via sp_search_products. */
    private function searchProductsSql(array $criteres): array
    {
        if (!$this->store instanceof StoredProcedureResultExecutor) {
            throw new UnexpectedValueException('Le pilote SQL ne fournit pas les résultats de procédures.');
        }

        $motCle = trim((string) ($criteres['mot_cle'] ?? ''));
        $idCategorie = null;
        if (!empty($criteres['id_categorie'])) {
            $idCategorie = (int) $criteres['id_categorie'];
        }
        $prixMin = max(0.0, (float) ($criteres['prix_min'] ?? 0));
        $prixMax = (float) ($criteres['prix_max'] ?? 99999999.99);
        $enStock = (int) !empty($criteres['en_stock']);
        $admin = (bool) ($criteres['admin'] ?? false);
        $tri = $criteres['tri'] ?? 'nom';
        if (!in_array($tri, self::TRIS, true)) {
            $tri = 'nom';
        }
        $parPage = min(max((int) ($criteres['par_page'] ?? 12), 1), 60);
        $pageMax = min(2_147_483_647, intdiv(2_147_483_647, $parPage) + 1);
        $page = min(max((int) ($criteres['page'] ?? 1), 1), $pageMax);
        $offset = ($page - 1) * $parPage;
        $rupturesFin = (bool) ($criteres['ruptures_fin'] ?? false);

        [$lignesSql, $total] = $this->rechercheSqlPage(
            $motCle, $idCategorie, $prixMin, $prixMax, $enStock, $tri, $page, $parPage, $admin,
        );

        if ($rupturesFin && $enStock === 0) {
            [, $nombreDisponibles] = $this->rechercheSqlPage(
                $motCle, $idCategorie, $prixMin, $prixMax, 1, $tri, 1, 1, $admin,
            );
            $lignesSql = [];
            if ($offset < $nombreDisponibles) {
                [$lignesDisponibles] = $this->rechercheSqlPage(
                    $motCle, $idCategorie, $prixMin, $prixMax, 1, $tri, $page, $parPage, $admin,
                );
                $lignesSql = $lignesDisponibles;
            }

            $placesRestantes = $parPage - count($lignesSql);
            $offsetRuptures = max(0, $offset + count($lignesSql) - $nombreDisponibles);
            if ($placesRestantes > 0 && $offset + count($lignesSql) < $total) {
                $tailleLot = 60;
                $pageRuptures = intdiv($offsetRuptures, $tailleLot) + 1;
                $decalageDansLot = $offsetRuptures % $tailleLot;
                [$lotRuptures] = $this->rechercheSqlPage(
                    $motCle, $idCategorie, $prixMin, $prixMax, 2, $tri, $pageRuptures, $tailleLot, $admin,
                );
                $lignesSql = array_merge($lignesSql, array_slice($lotRuptures, $decalageDansLot, $placesRestantes));
            }
        }

        $lignes = [];
        foreach ($lignesSql as $produit) {
            $produit['id_produit'] = (int) $produit['id_produit'];
            $produit['id_categorie'] = (int) $produit['id_categorie'];
            $produit['stock'] = (int) $produit['stock'];
            $produit['seuil_alerte'] = (int) $produit['seuil_alerte'];
            $produit['visible'] = (bool) $produit['visible'];
            $produit['prix_ht'] = (float) $produit['prix_ht'];
            $produit['tva'] = (float) $produit['tva'];
            $produit['prix_ttc'] = (float) $produit['prix_ttc'];
            $produit['categorie'] = (string) ($produit['categorie'] ?? '—');
            $produit['categorie_slug'] = $produit['categorie_slug'] ?? null;
            $produit['etat_stock'] = Views::etatStock($produit);
            $lignes[] = $produit;
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
     * @return array{0:list<array<string,mixed>>,1:int}
     */
    private function rechercheSqlPage(
        string $motCle,
        ?int $idCategorie,
        float $prixMin,
        float $prixMax,
        int $enStock,
        string $tri,
        int $page,
        int $parPage,
        bool $admin,
    ): array {
        if (!$this->store instanceof StoredProcedureResultExecutor) {
            throw new UnexpectedValueException('Le pilote SQL ne fournit pas les résultats de procédures.');
        }

        $resultat = $this->store->transactional(function () use (
            $motCle,
            $idCategorie,
            $prixMin,
            $prixMax,
            $enStock,
            $tri,
            $page,
            $parPage,
            $admin,
        ): array {
            if (!$this->store instanceof StoredProcedureResultExecutor) {
                throw new UnexpectedValueException('Le pilote SQL ne fournit pas les résultats de procédures.');
            }

            return $this->store->callProcedureWithResults('sp_search_products', [
                $motCle,
                $idCategorie,
                $prixMin,
                $prixMax,
                $enStock,
                $tri,
                $page,
                $parPage,
                (int) $admin,
            ]);
        });
        $ensembles = $resultat['results'] ?? [];
        if (count($ensembles) < 2 || !isset($ensembles[1][0]['total_trouves'])) {
            throw new UnexpectedValueException('sp_search_products n’a pas retourné ses deux jeux de résultats.');
        }

        return [$ensembles[0], (int) $ensembles[1][0]['total_trouves']];
    }

    /**
     * sp_save_product — création / modification (EF-ADM-01/02).
     * p_id_produit null => création. Le prix TTC est DÉRIVÉ par le
     * déclencheur trg_produit_ttc (RB-16), jamais saisi.
     *
     * @param int $stock stock initial à la création ; ignoré à la modification,
     *                    où adjustStock() exige un motif obligatoire
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
        if (!$this->store instanceof StoredProcedureExecutor
            && $this->store->find('categorie', $idCategorie) === null) {
            throw new BusinessError('CATEGORIE_INTROUVABLE');
        }

        if ($slug !== null && trim($slug) !== '') {
            $slug = Text::slug($slug);
        } else {
            $slug = Text::slug($nom);
        }
        $reference = trim($reference);

        if ($this->store instanceof StoredProcedureExecutor) {
            $visibleSql = $visible;
            if ($idProduit === null && $visibleSql === null) {
                $visibleSql = true;
            }

            return $this->saveProductSql(
                $idProduit,
                $reference,
                trim($nom),
                $slug,
                $description,
                $prixHt,
                $tva ?? 20.00,
                $stock,
                $seuilAlerte ?? 3,
                $idCategorie,
                $visibleSql,
                $imageUrl,
            );
        }

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
            'seuil_alerte' => $seuilAlerte ?? 3,
            'id_categorie' => $idCategorie,
            'image_url' => $imageUrl,
        ];

        if ($idProduit === null) {
            // Le stock initial est une propriété de création ; les ajustements
            // ultérieurs passent exclusivement par adjustStock() avec motif.
            $donnees['stock'] = $stock;
            $donnees['visible'] = $visible ?? true;

            return $this->store->insert('produit', $donnees);
        }

        if ($visible !== null) {
            $donnees['visible'] = $visible;
        }
        $this->store->update('produit', [Filter::eq('id_produit', $idProduit)], $donnees);

        return $idProduit;
    }

    private function saveProductSql(
        ?int $idProduit,
        string $reference,
        string $nom,
        string $slug,
        ?string $description,
        float $prixHt,
        float $tva,
        int $stock,
        int $seuilAlerte,
        int $idCategorie,
        ?bool $visible,
        ?string $imageUrl,
    ): int {
        if (!$this->store instanceof StoredProcedureExecutor) {
            throw new UnexpectedValueException('Le pilote SQL ne fournit pas l’exécution de procédures.');
        }

        return $this->store->transactional(function () use (
            $idProduit,
            $reference,
            $nom,
            $slug,
            $description,
            $prixHt,
            $tva,
            $stock,
            $seuilAlerte,
            $idCategorie,
            $visible,
            $imageUrl,
        ): int {
            if (!$this->store instanceof StoredProcedureExecutor) {
                throw new UnexpectedValueException('Le pilote SQL ne fournit pas l’exécution de procédures.');
            }
            if ($idProduit !== null && $this->store->find('produit', $idProduit) === null) {
                throw new BusinessError('PRODUIT_INTROUVABLE');
            }

            $resultat = $this->store->callProcedure(
                'sp_save_product',
                [$idProduit, $reference, $nom, $slug, $description, $prixHt, $tva, $stock, $seuilAlerte, $idCategorie, $visible, $imageUrl],
                ['p_id_resultat', 'p_code_retour'],
            );
            $sorties = $resultat['out'] ?? [];
            $code = $sorties['p_code_retour'] ?? null;
            if ($code !== 'OK' && $code !== 'AUCUNE_MODIFICATION') {
                if (is_string($code) && preg_match('/^[A-Z][A-Z0-9_]+$/', $code) === 1) {
                    throw new BusinessError($code);
                }
                throw new UnexpectedValueException('Réponse inattendue de sp_save_product.');
            }

            $idResultat = $sorties['p_id_resultat'] ?? null;
            if (!is_numeric($idResultat) || (int) $idResultat < 1) {
                throw new UnexpectedValueException('sp_save_product n’a pas retourné un identifiant produit valide.');
            }

            return (int) $idResultat;
        });
    }

    /**
     * sp_delete_product — suppression protégée (EF-ADM-02, RB-14/RB-17) :
     * un produit déjà commandé est MASQUÉ (visible=0) afin de conserver
     * l'historique des lignes de commande ; sinon suppression réelle.
     */
    public function deleteProduct(int $idProduit): void
    {
        if ($this->store instanceof StoredProcedureExecutor) {
            $this->store->transactional(function () use ($idProduit): void {
                if (!$this->store instanceof StoredProcedureExecutor) {
                    throw new UnexpectedValueException('Le pilote SQL ne fournit pas l’exécution de procédures.');
                }
                $this->store->callProcedure('sp_delete_product', [$idProduit]);
            });

            return;
        }

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
        $mode = strtoupper(trim($mode));
        $motif = trim($motif);
        if ($motif === '') {
            throw new BusinessError('MOTIF_STOCK_REQUIS');
        }
        if (!in_array($mode, ['SET', 'DELTA'], true)) {
            throw new BusinessError('MODE_STOCK_INVALIDE');
        }

        if ($this->store instanceof StoredProcedureExecutor) {
            // sp_adjust_stock possède sa propre transaction : mise à jour et
            // audit SQL sont validés/annulés ensemble, y compris en appel direct.
            $resultat = $this->store->callProcedure(
                'sp_adjust_stock',
                [$idProduit, $mode, $quantite, $motif],
                ['p_stock', 'p_code_retour'],
            );
            $sorties = $resultat['out'] ?? [];
            $code = $sorties['p_code_retour'] ?? null;
            if ($code !== 'OK') {
                if (is_string($code) && preg_match('/^[A-Z][A-Z0-9_]+$/', $code) === 1) {
                    throw new BusinessError($code);
                }
                throw new UnexpectedValueException('Réponse inattendue de sp_adjust_stock.');
            }
            $stock = $sorties['p_stock'] ?? null;
            if (!is_numeric($stock)) {
                throw new UnexpectedValueException('sp_adjust_stock n’a pas retourné un stock valide.');
            }

            return (int) $stock;
        }

        $acteur = $this->store->actor();
        [$stockAvant, $stockApres] = $this->store->transactional(function () use (
            $idProduit,
            $mode,
            $quantite,
            $motif,
            $acteur,
        ): array {
            $produit = $this->store->find('produit', $idProduit);
            if ($produit === null) {
                throw new BusinessError('PRODUIT_INTROUVABLE');
            }
            $stockAvant = (int) $produit['stock'];
            $nouveau = $quantite;
            if ($mode === 'DELTA') {
                $nouveau = $stockAvant + $quantite;
            }
            if ($nouveau < 0) {
                throw new BusinessError('RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF');
            }
            if ($nouveau !== $stockAvant) {
                $this->store->update('produit', [Filter::eq('id_produit', $idProduit)], ['stock' => $nouveau]);
                if ($this->store instanceof StockMovementAuditor) {
                    $this->store->logStockMovement(
                        $idProduit,
                        $stockAvant,
                        $nouveau,
                        $mode,
                        $quantite,
                        $motif,
                        $acteur,
                    );
                }
            }

            return [$stockAvant, $nouveau];
        });

        return $stockApres;
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
