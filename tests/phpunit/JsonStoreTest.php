<?php

declare(strict_types=1);

namespace MiniShop\Tests;

use App\Model\Data\BusinessError;
use App\Model\Data\ConstraintError;
use App\Repository\CommandeRepository;
use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use PHPUnit\Framework\TestCase;

/**
 * Intégration du moteur : contraintes, triggers, transactions.
 * Ces cas prouvent que les règles tiennent même quand on contourne
 * l'application (écritures directes), comme les tests SQL T-01…T-29.
 */
final class JsonStoreTest extends TestCase
{
    private JsonStore $store;

    protected function setUp(): void
    {
        $this->store = JsonStore::open(MINISHOP_TESTS_TMP . '/store_' . bin2hex(random_bytes(4)));
        Seed::force($this->store);
    }

    public function testInsertionProduitValide(): void
    {
        $id = $this->store->insert('produit', [
            'reference' => 'TEST-PU-001',
            'nom' => 'Produit de test unitaire',
            'slug' => 'produit-de-test-unitaire',
            'description' => 'Test',
            'prix_ht' => 10.00,
            'tva' => 20.00,
            'stock' => 5,
            'seuil_alerte' => 1,
            'id_categorie' => 1,
            'visible' => 1,
        ]);

        self::assertGreaterThan(0, $id);
        $produit = $this->store->find('produit', $id);
        self::assertNotNull($produit);
        self::assertEqualsWithDelta(12.00, (float) $produit['prix_ttc'], 0.005, 'prix TTC non calculé par le trigger');
    }

    public function testPrixZeroRefuse(): void
    {
        // La contrainte CHECK est la première barrière (≈ CHECK ck_produit_prix).
        $this->expectException(ConstraintError::class);
        $this->store->insert('produit', [
            'reference' => 'TEST-PU-002',
            'nom' => 'Prix nul',
            'slug' => 'prix-nul',
            'description' => '',
            'prix_ht' => 0.0,
            'tva' => 20.00,
            'stock' => 1,
            'seuil_alerte' => 1,
            'id_categorie' => 1,
            'visible' => 1,
        ]);
    }

    public function testStockNegatifRefuse(): void
    {
        $this->expectException(ConstraintError::class);
        $this->store->insert('produit', [
            'reference' => 'TEST-PU-003',
            'nom' => 'Stock négatif',
            'slug' => 'stock-negatif',
            'description' => '',
            'prix_ht' => 10.0,
            'tva' => 20.00,
            'stock' => -1,
            'seuil_alerte' => 1,
            'id_categorie' => 1,
            'visible' => 1,
        ]);
    }

    public function testEmailClientUnique(): void
    {
        $this->expectException(ConstraintError::class);
        $this->store->insert('client', [
            'nom' => 'Doublon',
            'prenom' => 'Test',
            'email' => 'alice@example.com',
            'mot_de_passe_hash' => password_hash('MotDePasse2026!', PASSWORD_BCRYPT, ['cost' => 4]),
            'adresse_livraison' => '1 rue du Test',
            'code_postal' => '06000',
            'ville' => 'Nice',
            'actif' => 1,
        ]);
    }

    public function testSuppressionCategorieNonVideRefusee(): void
    {
        $this->expectException(BusinessError::class);
        $this->store->delete('categorie', [['id_categorie', '=', 1]]);
    }

    public function testTransactionAnnuleeNeLaissePasDeTrace(): void
    {
        $avant = $this->store->count('produit');

        try {
            $this->store->beginTransaction();
            $this->store->insert('produit', [
                'reference' => 'TEST-PU-004',
                'nom' => 'Produit annulé',
                'slug' => 'produit-annule',
                'description' => '',
                'prix_ht' => 10.0,
                'tva' => 20.00,
                'stock' => 3,
                'seuil_alerte' => 1,
                'id_categorie' => 1,
                'visible' => 1,
            ]);
            $this->store->rollback();
        } catch (BusinessError) {
            self::fail('insertion valide refusée');
        }

        self::assertSame($avant, $this->store->count('produit'), 'rollback incomplet');
    }

    public function testTransitionDeStatutInterdite(): void
    {
        $idClient = $this->store->insert('client', [
            'nom' => 'Test',
            'prenom' => 'Unitaire',
            'email' => 'unitaire.' . bin2hex(random_bytes(4)) . '@example.test',
            'mot_de_passe_hash' => password_hash('MotDePasse2026!', PASSWORD_BCRYPT, ['cost' => 4]),
            'adresse_livraison' => '1 rue du Test',
            'code_postal' => '06000',
            'ville' => 'Nice',
            'actif' => 1,
        ]);
        $client = $this->store->find('client', $idClient);
        self::assertNotNull($client, 'client de test non créé');

        $produit = null;
        foreach ($this->store->all('produit') as $candidat) {
            if ((int) $candidat['visible'] === 1 && (int) $candidat['stock'] > 0) {
                $produit = $candidat;
                break;
            }
        }
        self::assertNotNull($produit, 'produit disponible absent');

        $this->store->setActor(['id' => 1, 'role' => 'ADMIN', 'nom' => 'Test Unitaire']);
        $depot = CommandeRepository::for($this->store);

        $commande = $depot->createOrder((int) $client['id_client'], '1 rue du Test');
        $depot->addOrderLine((int) $commande['id_commande'], (int) $produit['id_produit'], 1);
        $depot->confirmOrder((int) $commande['id_commande'], (int) $client['id_client'], true);

        $this->expectException(BusinessError::class);
        $depot->updateOrderStatus((int) $commande['id_commande'], 'BROUILLON', 'retour en arrière interdit', 1, 'ADMIN');
    }
}
