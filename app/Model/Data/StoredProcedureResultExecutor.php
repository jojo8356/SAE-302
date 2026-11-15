<?php

declare(strict_types=1);

namespace App\Model\Data;

/**
 * Capacité optionnelle à lire les jeux de résultats renvoyés par une
 * procédure stockée (recherche catalogue, rapports, etc.).
 *
 * Séparée du contrat StoreInterface : le pilote JSON n'a pas de procédures
 * ni de jeux de résultats SQL à émuler.
 */
interface StoredProcedureResultExecutor extends StoredProcedureExecutor
{
    /**
     * Exécute une procédure, conserve chaque jeu de résultats et ses paramètres OUT.
     *
     * @param list<mixed> $inputs
     * @param list<string> $outputs noms exacts des paramètres OUT SQL
     * @return array{results: list<list<array<string,mixed>>>, out: array<string,mixed>}
     */
    public function callProcedureWithResults(string $procedure, array $inputs = [], array $outputs = []): array;
}
