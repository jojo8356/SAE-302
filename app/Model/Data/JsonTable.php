<?php

declare(strict_types=1);

/**
 * MiniShop — une table = un fichier JSON (pilote StorageDriver::JSON).
 *
 * Format du fichier data/minishop/<table>.json :
 * {
 *     "table": "produit",
 *     "auto_increment": 13,
 *     "rows": [ {...}, {...} ]
 * }
 *
 * - Chargement paresseux (le fichier n'est lu qu'à la première utilisation).
 * - Écriture ATOMIQUE : sérialisation dans un fichier temporaire puis
 *   rename() — un crash ne peut jamais laisser une table à moitié écrite
 *   (équivalent du journal InnoDB, adapté au stockage fichier).
 * - Écriture différée : la table marquée « dirty » n'est écrite qu'au
 *   commit()/flush() du store — une transaction multi-tables coûte une seule
 *   écriture par table réellement modifiée.
 */

namespace App\Model\Data;

final class JsonTable
{
    /** @var list<array<string,mixed>>|null lignes en mémoire (null = non chargé) */
    private ?array $rows = null;

    private ?int $autoIncrement = null;

    private bool $dirty = false;

    public function __construct(
        private readonly string $name,
        private readonly string $filePath,
        private readonly bool $autoIncrementPk,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    /** Est-ce que la table a des modifications non écrites ? */
    public function isDirty(): bool
    {
        return $this->dirty;
    }

    /** Charge le fichier si nécessaire (table absente = table vide à créer). */
    public function load(): void
    {
        if ($this->rows !== null) {
            return;
        }
        if (!is_file($this->filePath)) {
            $this->rows = [];
            $this->autoIncrement = 1;

            return;
        }
        $raw = file_get_contents($this->filePath);
        if ($raw === false) {
            throw new \RuntimeException("Impossible de lire la table {$this->name} ({$this->filePath})");
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['rows']) || !is_array($decoded['rows'])) {
            throw new \RuntimeException("Table {$this->name} corrompue (JSON invalide) : {$this->filePath}");
        }
        $this->rows = array_values($decoded['rows']);
        $this->autoIncrement = isset($decoded['auto_increment']) ? (int) $decoded['auto_increment'] : 1;
    }

    /** @return list<array<string,mixed>> */
    public function rows(): array
    {
        $this->load();

        return $this->rows ?? [];
    }

    /** Remplace les lignes (usage interne : transactions, seed). */
    public function setRows(array $rows, ?int $autoIncrement = null): void
    {
        $this->rows = array_values($rows);
        if ($autoIncrement !== null) {
            $this->autoIncrement = $autoIncrement;
        }
        $this->dirty = true;
    }

    /** Ajoute une ligne (l'id auto doit déjà être affecté par le store). */
    public function append(array $row): void
    {
        $this->load();
        $this->rows[] = $row;
        $this->dirty = true;
    }

    /** Remplace la ligne à l'index donné. */
    public function replace(int $index, array $row): void
    {
        $this->load();
        $this->rows[$index] = $row;
        $this->dirty = true;
    }

    /** Supprime la ligne à l'index donné. */
    public function remove(int $index): void
    {
        $this->load();
        array_splice($this->rows, $index, 1);
        $this->dirty = true;
    }

    /** Consomme et retourne la prochaine valeur d'auto-incrément. */
    public function takeNextId(): int
    {
        $this->load();
        $id = $this->autoIncrement ?? 1;
        $this->autoIncrement = $id + 1;
        $this->dirty = true;

        return $id;
    }

    /** Prochaine valeur SANS consommer le compteur (numéro lisible). */
    public function peekNextId(): int
    {
        $this->load();

        return $this->autoIncrement ?? 1;
    }

    /** Force le compteur au-delà d'un id explicite (seed / reprise de données). */
    public function bumpBeyond(int $id): void
    {
        $this->load();
        if ($id >= ($this->autoIncrement ?? 1)) {
            $this->autoIncrement = $id + 1;
            $this->dirty = true;
        }
    }

    /** Écrit la table sur disque si elle est sale (atomique). */
    public function flush(): void
    {
        if (!$this->dirty || $this->rows === null) {
            return;
        }
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $payload = json_encode(
            ['table' => $this->name, 'auto_increment' => $this->autoIncrement, 'rows' => $this->rows],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($payload === false) {
            throw new \RuntimeException("Impossible de sérialiser la table {$this->name}");
        }
        $tmp = $this->filePath . '.' . getmypid() . '.tmp';
        if (file_put_contents($tmp, $payload, LOCK_EX) === false) {
            throw new \RuntimeException("Impossible d'écrire la table {$this->name} ({$tmp})");
        }
        if (!@rename($tmp, $this->filePath)) {
            @unlink($tmp);
            throw new \RuntimeException("Écriture atomique impossible pour la table {$this->name}");
        }
        $this->dirty = false;
    }

    /** Snapshot profond (transactions : rollback). */
    public function snapshot(): array
    {
        $this->load();

        return ['rows' => $this->rows, 'auto_increment' => $this->autoIncrement];
    }

    /** Restaure un snapshot (transactions : rollback). */
    public function restore(array $snapshot): void
    {
        $this->rows = $snapshot['rows'];
        $this->autoIncrement = $snapshot['auto_increment'];
        $this->dirty = true;
    }
}
