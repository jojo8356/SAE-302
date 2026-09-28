<?php

declare(strict_types=1);

/**
 * MiniShop — pilote SQL (coquille de la migration finale).
 *
 * CE PILOTE N'EST PAS ENCORE ACTIF : il matérialise l'architecture cible du
 * projet. Aujourd'hui toute l'application tourne sur StorageDriver::JSON
 * (JsonStore, opérations JSON natives). La bascule vers ce pilote se fera
 * lors du merge final vers MySQL (lots L14+ du WBS) en réutilisant les
 * scripts déjà livrés dans sql/ :
 *
 *   - data/minishop/*.json  → tables MySQL chargées par sql/01 (DDL + seed) ;
 *   - les conditions déclaratives de StoreInterface deviennent des clauses
 *     WHERE préparées (opérateurs identiques : =, <, >=, LIKE, IN, BETWEEN…) ;
 *   - App\Model\Data\Triggers devient les 16 déclencheurs de sql/03 ;
 *   - les Repository conservent leurs signatures : seules les implémentations
 *     du présent pilote changent, aucun contrôleur ni vue à modifier.
 *
 * Tant que la migration n'a pas lieu, chaque méthode lève une erreur
 * explicite — le pilote ne peut pas être activé par accident.
 */

namespace App\Model\Data;

final class SqlStore implements StoreInterface
{
    private const MESSAGE = 'Pilote SQL prévu pour la migration finale (merge vers MySQL, sql/01→04) : '
        . 'l’application tourne aujourd’hui sur StorageDriver::JSON.';

    public function select(string $table, array $spec = []): array
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    public function findOne(string $table, array $where = []): ?array
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    public function find(string $table, int|string $pk): ?array
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    public function count(string $table, array $where = []): int
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    public function sum(string $table, string $column, array $where = []): float
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    public function exists(string $table, array $where = []): bool
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    public function all(string $table): array
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    public function insert(string $table, array $row): int
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    public function update(string $table, array $where, array $changes): int
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    public function delete(string $table, array $where): int
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    public function nextId(string $table): int
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    public function beginTransaction(): void
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    public function commit(): void
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    public function rollback(): void
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    public function inTransaction(): bool
    {
        return false;
    }

    public function setActor(array $actor): void
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    public function actor(): array
    {
        throw new \RuntimeException(self::MESSAGE);
    }
}
