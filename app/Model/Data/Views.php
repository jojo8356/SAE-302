<?php

declare(strict_types=1);

/**
 * MiniShop — les 3 vues du schéma (sql/01) portées en opérations JSON.
 *
 *   v_etat_stock        : produit + état dérivé (RUPTURE / TRES_BAS / DISPONIBLE)
 *   v_catalogue         : produits visibles + catégorie + état (RB-19)
 *   v_commandes_client  : commandes + client + nombre de lignes
 *
 * Une seule définition des formules partagées par le front-office, le
 * back-office et les procédures (évite la divergence de calcul, cf. RB-16 /
 * RB-19 / ENF-13). Le jour du basculement StorageDriver::SQL, ces méthodes
 * deviennent des CREATE VIEW identiques à sql/01 — les contrôleurs n'ont
 * aucune modification à faire.
 *
 * Techniquement : jointures par table de hachage (index pk du store), zéro
 * requête, zéro SQL — uniquement des opérations de tableaux natifs.
 */

namespace App\Model\Data;

final class Views
{
    public function __construct(private readonly StoreInterface $store)
    {
    }

    /** État de stock dérivé d'un produit (règle de la vue v_etat_stock). */
    public static function etatStock(array $produit): string
    {
        $stock = (int) ($produit['stock'] ?? 0);
        if ($stock === 0) {
            return 'RUPTURE';
        }
        if ($stock <= (int) ($produit['seuil_alerte'] ?? 0)) {
            return 'TRES_BAS';
        }

        return 'DISPONIBLE';
    }

    /**
     * v_etat_stock — l'état du stock de tous les produits.
     *
     * @return list<array<string,mixed>>
     */
    public function etatStocks(): array
    {
        $rows = [];
        foreach ($this->store->select('produit', ['order' => [Filter::sort('nom')]]) as $p) {
            $rows[] = [
                'id_produit' => (int) $p['id_produit'],
                'reference' => $p['reference'],
                'nom' => $p['nom'],
                'stock' => (int) $p['stock'],
                'seuil_alerte' => (int) $p['seuil_alerte'],
                'etat' => self::etatStock($p),
            ];
        }

        return $rows;
    }

    /**
     * v_catalogue — produits visibles (RB-19) enrichis de leur catégorie.
     *
     * @param array{where?: array, order?: array, limit?: int, offset?: int} $spec
     * @return list<array<string,mixed>>
     */
    public function catalogue(array $spec = []): array
    {
        $categories = $this->store->all('categorie');
        $where = array_merge(
            [['visible', '=', 1]],
            $spec['where'] ?? []
        );

        $rows = [];
        foreach ($this->store->select('produit', ['where' => $where, 'order' => $spec['order'] ?? [], 'limit' => $spec['limit'] ?? null, 'offset' => $spec['offset'] ?? 0]) as $p) {
            $categorie = $categories[(int) $p['id_categorie']] ?? null;
            $rows[] = [
                'id_produit' => (int) $p['id_produit'],
                'reference' => $p['reference'],
                'slug' => $p['slug'],
                'nom' => $p['nom'],
                'description' => $p['description'],
                'prix_ht' => (float) $p['prix_ht'],
                'tva' => (float) $p['tva'],
                'prix_ttc' => (float) $p['prix_ttc'],
                'stock' => (int) $p['stock'],
                'image_url' => $p['image_url'],
                'id_categorie' => (int) $p['id_categorie'],
                'categorie' => $categorie['nom'] ?? '—',
                'categorie_slug' => $categorie['slug'] ?? null,
                'etat' => self::etatStock($p),
            ];
        }

        return $rows;
    }

    /**
     * v_commandes_client — commandes enrichies (client, nombre de lignes).
     *
     * @param array{where?: array, order?: array, limit?: int, offset?: int} $spec
     * @return list<array<string,mixed>>
     */
    public function commandesClient(array $spec = []): array
    {
        $clients = $this->store->all('client');
        $rows = [];
        foreach ($this->store->select('commande', ['where' => $spec['where'] ?? [], 'order' => $spec['order'] ?? [Filter::sort('date_commande', 'desc')], 'limit' => $spec['limit'] ?? null, 'offset' => $spec['offset'] ?? 0]) as $o) {
            $client = $clients[(int) $o['id_client']] ?? null;
            $rows[] = [
                'id_commande' => (int) $o['id_commande'],
                'numero' => $o['numero'],
                'statut' => $o['statut'],
                'montant_total' => (float) $o['montant_total'],
                'frais_port' => (float) $o['frais_port'],
                'adresse_livraison' => $o['adresse_livraison'],
                'date_commande' => $o['date_commande'],
                'id_client' => (int) $o['id_client'],
                'client' => $client !== null ? $client['prenom'] . ' ' . $client['nom'] : '—',
                'client_email' => $client['email'] ?? null,
                'nb_lignes' => $this->store->count('ligne_commande', [['id_commande', '=', (int) $o['id_commande']]]),
            ];
        }

        return $rows;
    }
}
