<?php

declare(strict_types=1);

namespace App\Model\Data;

/**
 * Capacité optionnelle d'un pilote à exécuter une procédure stockée.
 *
 * Elle est séparée de StoreInterface : le pilote JSON n'a pas à simuler une
 * API SQL qu'il ne peut pas implémenter. Les repositories dépendent de ce
 * contrat, jamais de la classe concrète SqlStore.
 */
interface StoredProcedureExecutor
{
    /**
     * Exécute une procédure et retourne ses paramètres OUT sous la clé `out`.
     *
     * @param list<mixed>  $inputs
     * @param list<string> $outputs noms exacts des paramètres OUT SQL
     * @return array{out: array<string,mixed>}
     */
    public function callProcedure(string $procedure, array $inputs = [], array $outputs = []): array;
}
