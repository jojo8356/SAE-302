<?php

declare(strict_types=1);

namespace MiniShop\Tests;

use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use PHPUnit\Framework\TestCase;

/**
 * RB-02 / RB-16 / ENF-14 : le prix TTC est une valeur calculée par le moteur,
 * jamais saisie. Ce test vérifie l'invariant sur TOUT le jeu de démonstration
 * (et non sur un seul produit) : c'est le genre de contrôle qu'on garde en CI
 * pour détecter un trigger cassé.
 */
final class PrixTest extends TestCase
{
    private JsonStore $store;

    protected function setUp(): void
    {
        $this->store = JsonStore::open(MINISHOP_TESTS_TMP . '/prix_' . bin2hex(random_bytes(4)));
        Seed::force($this->store);
    }

    public function testPrixTtcEgalPrixHtFoisTva(): void
    {
        $produits = $this->store->all('produit');
        self::assertNotEmpty($produits, 'le jeu de démonstration est vide');

        foreach ($produits as $produit) {
            $attendu = round((float) $produit['prix_ht'] * (1 + (float) $produit['tva'] / 100), 2);
            self::assertEqualsWithDelta(
                $attendu,
                (float) $produit['prix_ttc'],
                0.005,
                'prix TTC incohérent pour ' . $produit['reference'],
            );
        }
    }

    public function testTousLesPrixSontStrictementPositifs(): void
    {
        foreach ($this->store->all('produit') as $produit) {
            self::assertGreaterThan(0.0, (float) $produit['prix_ht'], 'RB-02 violé sur ' . $produit['reference']);
        }
    }

    public function testAucunStockNegatif(): void
    {
        foreach ($this->store->all('produit') as $produit) {
            self::assertGreaterThanOrEqual(0, (int) $produit['stock'], 'RB-03 violé sur ' . $produit['reference']);
        }
    }

    public function testLesMontantsDeCommandeSontCoherents(): void
    {
        $lignesParCommande = [];
        foreach ($this->store->all('ligne_commande') as $ligne) {
            $id = (int) $ligne['id_commande'];
            $lignesParCommande[$id] = ($lignesParCommande[$id] ?? 0.0) + (float) $ligne['total_ligne'];
            self::assertEqualsWithDelta(
                round((float) $ligne['prix_unitaire'] * (int) $ligne['quantite'], 2),
                (float) $ligne['total_ligne'],
                0.005,
                'total de ligne incohérent',
            );
        }

        foreach ($this->store->all('commande') as $commande) {
            if ($commande['statut'] === 'BROUILLON') {
                continue;
            }
            $attendu = round(($lignesParCommande[(int) $commande['id_commande']] ?? 0.0) + (float) $commande['frais_port'], 2);
            self::assertEqualsWithDelta($attendu, (float) $commande['montant_total'], 0.005, 'RB-15 violé sur ' . $commande['numero']);
        }
    }
}
