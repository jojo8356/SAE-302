<?php

declare(strict_types=1);

/**
 * MiniShop — piste d'audit des écritures (EF-GEN-04 : « un rejet de règle par
 * la base est toujours journalisé » — ici : chaque écriture du moteur).
 *
 * Journal append-only au format JSON Lines : var/journal/db.jsonl
 *   {"at":"2026-09-28T10:12:33+02:00","op":"insert","table":"ligne_commande",
 *    "pk":"42","actor":{"id":7,"role":"CLIENT","nom":"Dupont"},"old":null,
 *    "new":{…}}
 *
 * Équivalent du binlog MySQL en beaucoup plus simple : rejouable, greppable,
 * et consultable depuis le back-office pour justifier une traçabilité.
 * Si le dossier var/ n'est pas inscriptible, le moteur continue de fonctionner
 * (le journal est une exigence de traçabilité, pas une dépendance forte).
 */

namespace App\Model\Data;

final class Journal
{
    private function __construct(private readonly string $filePath)
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    /** Crée le journal ; désactivé (null) si le FS n'est pas inscriptible. */
    public static function create(string $filePath): ?self
    {
        $journal = new self($filePath);
        if (!is_dir(dirname($filePath)) || !is_writable(dirname($filePath))) {
            return null;
        }

        return $journal;
    }

    /**
     * Enregistre une écriture.
     *
     * @param array{id?: int|null, role?: string, nom?: string|null} $actor
     */
    public function log(string $op, string $table, int|string $pk, ?array $old, ?array $new, array $actor): void
    {
        $entry = [
            'at' => date('c'),
            'op' => $op,
            'table' => $table,
            'pk' => $pk,
            'actor' => ['id' => $actor['id'] ?? null, 'role' => $actor['role'] ?? 'SYSTEME', 'nom' => $actor['nom'] ?? null],
            'old' => $old,
            'new' => $new,
        ];
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line !== false) {
            @file_put_contents($this->filePath, $line . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    /** @return list<array<string,mixed>> les N dernières écritures (audit BO). */
    public function tail(int $limit = 50): array
    {
        if (!is_file($this->filePath)) {
            return [];
        }
        $lines = @file($this->filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }
        $entries = [];
        foreach (array_slice($lines, -$limit) as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }

        return $entries;
    }
}
