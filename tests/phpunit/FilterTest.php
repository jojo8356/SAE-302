<?php

declare(strict_types=1);

namespace MiniShop\Tests;

use App\Model\Data\Filter;
use App\Model\Data\JsonStore;
use PHPUnit\Framework\TestCase;

/**
 * Contrat déclaratif des filtres (StoreInterface) : ce que JsonStore applique
 * doit être exactement ce que SqlStore traduira en WHERE (mêmes opérateurs).
 */
final class FilterTest extends TestCase
{
    private JsonStore $store;
    private string $dossier;

    protected function setUp(): void
    {
        $this->dossier = MINISHOP_TESTS_TMP . '/filtres_' . bin2hex(random_bytes(4));
        $this->store = JsonStore::open($this->dossier);
        $this->store->replaceAll('produit', [
            ['id_produit' => 1, 'nom' => 'Écran', 'prix_ht' => 179.90, 'stock' => 25, 'visible' => 1],
            ['id_produit' => 2, 'nom' => 'ecran tactile', 'prix_ht' => 99.0, 'stock' => 0, 'visible' => 1],
            ['id_produit' => 3, 'nom' => 'Clavier', 'prix_ht' => 79.0, 'stock' => 40, 'visible' => 0],
            ['id_produit' => 4, 'nom' => 'Casque', 'prix_ht' => 129.0, 'stock' => 18, 'visible' => 1],
            ['id_produit' => 5, 'nom' => 'Câble', 'prix_ht' => 12.5, 'stock' => 150, 'visible' => 1],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dossier . '/*') ?: [] as $fichier) {
            @unlink($fichier);
        }
        @rmdir($this->dossier);
    }

    /** @return list<int> */
    private function ids(array $where): array
    {
        $lignes = $this->store->select('produit', ['where' => $where]);

        return array_map(static fn (array $l): int => (int) $l['id_produit'], $lignes);
    }

    public function testEgaliteEtInegalite(): void
    {
        self::assertSame([3], $this->ids([Filter::eq('id_produit', 3)]));
        self::assertSame([1, 2, 4, 5], $this->ids([Filter::neq('id_produit', 3)]));
    }

    public function testComparaisonsNumeriques(): void
    {
        self::assertSame([1, 4], $this->ids([Filter::gt('prix_ht', 100)]));
        self::assertSame([2, 3, 5], $this->ids([Filter::lt('prix_ht', 100)]));
        self::assertSame([2, 3], $this->ids([Filter::between('prix_ht', 78.0, 99.0)]));
    }

    public function testLikeIgnoreCasseEtAccents(): void
    {
        self::assertSame([1, 2], $this->ids([Filter::like('nom', '%ECRAN%')]));
    }

    public function testInEtNull(): void
    {
        self::assertSame([1, 3], $this->ids([Filter::in('id_produit', [1, 3])]));
        self::assertSame([], $this->ids([Filter::isNull('prix_ht')]));
    }

    public function testEtOuEtTri(): void
    {
        $lignes = $this->store->select('produit', [
            'where' => [Filter::eq('visible', 1), Filter::gt('stock', 0)],
            'order' => [Filter::sort('prix_ht', 'desc')],
        ]);

        self::assertSame([1, 4, 5], array_map(static fn (array $l): int => (int) $l['id_produit'], $lignes));
    }

    public function testCountEtSum(): void
    {
        self::assertSame(5, $this->store->count('produit'));
        self::assertSame(3, $this->store->count('produit', [Filter::eq('visible', 1), Filter::gt('stock', 0)]));
        self::assertEqualsWithDelta(499.4, $this->store->sum('produit', 'prix_ht'), 0.001);
    }
}
