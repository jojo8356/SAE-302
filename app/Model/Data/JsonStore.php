<?php

declare(strict_types=1);

/**
 * MiniShop — moteur de données JSON (pilote StorageDriver::JSON).
 *
 * « Moteur intelligent » : ce pilote reproduit, en opérations JSON natives,
 * tout ce que le schéma MySQL du projet attend d'un SGBD — sans UNE SEULE
 * requête SQL. En particulier :
 *
 *   - TABLES      : une table = un fichier data/minishop/<table>.json,
 *                   chargement paresseux, écriture atomique (tmp + rename) ;
 *   - SCHEMA      : valeurs par défaut, coercition de types, NOT NULL, ENUM,
 *                   UNIQUE, CHECK, FK RESTRICT/CASCADE (cf. Schema) ;
 *   - DÉCLENCHEURS: les 16 triggers métier de sql/03 portés en callbacks PHP
 *                   (cf. Triggers) — ils sont la dernière barrière, y compris
 *                   pour un code applicatif défaillant (défense en profondeur) ;
 *   - TRANSACTIONS: snapshot mémoire + rollback (ENF-16 : un refus ne laisse
 *                   JAMAIS de données incohérentes), commit = flush atomique ;
 *   - INDEX       : index PK en table de hachage (accès O(1) par clé) ;
 *   - JOURNAL     : piste d'audit append-only de chaque écriture
 *                   (var/journal/db.jsonl, exigence EF-GEN-04) ;
 *   - ACTEUR      : setActor() mémorise qui écrit (client/admin/système) pour
 *                   la piste d'audit automatique des statuts (RB-11).
 *
 * Les lectures (select/findOne/count/sum/exists) évaluent les conditions
 * déclaratives ligne à ligne : le volume cible (200 produits, 1 000 commandes,
 * cf. ENF-01/02) tient en mémoire et l'opération reste en O(n) natif —
 * le jour du basculement StorageDriver::SQL, les mêmes spécifications
 * deviennent des clauses WHERE indexées et ce fichier disparaît.
 *
 * Hors transaction, chaque écriture est immédiatement persistée ; en
 * transaction, les tables modifiées ne sont écrites qu'au commit().
 */

namespace App\Model\Data;

final class JsonStore implements StoreInterface
{
    /** @var array<string, JsonTable> tables chargées (paresseusement) */
    private array $tables = [];

    /** @var array<string, array<int|string, int>> index pk -> position dans les lignes */
    private array $pkIndex = [];

    /** @var array<string, bool> index invalidés à récalculer */
    private array $pkIndexStale = [];

    /** @var array{id?: int|null, role?: string, nom?: string|null} acteur courant */
    private array $actor = ['id' => null, 'role' => 'SYSTEME', 'nom' => null];

    private bool $transaction = false;

    /** @var array<string, array> snapshot des tables chargées au BEGIN */
    private array $snapshot = [];

    private ?Journal $journal;

    private string $journalPath;

    private function __construct(private readonly string $dataPath)
    {
        // Journal d'audit (EF-GEN-04) : le magasin applicatif journalise dans
        // var/journal/db.jsonl (emplacement documenté, hors dépôt) ; un bac à
        // sable (tests, volumétrie) journalise À CÔTÉ de ses données pour
        // rester hermétique. Le dossier est créé au besoin.
        $racine = dirname(__DIR__, 3);
        $chemin = rtrim($this->dataPath, '/') === $racine . '/data/minishop'
            ? $racine . '/var/journal/db.jsonl'
            : rtrim($this->dataPath, '/') . '/journal.jsonl';
        @mkdir(dirname($chemin), 0775, true);
        $this->journal = Journal::create($chemin);
        $this->journalPath = $chemin;
    }

    /** Chemin du journal d'audit de ce magasin (EF-GEN-04, tests, back-office). */
    public function journalPath(): string
    {
        return $this->journalPath;
    }

    /** Ouvre (et le cas échéant initialise) la base JSON d'un dossier. */
    public static function open(string $dataPath): self
    {
        $dataPath = rtrim($dataPath, '/');
        if (!is_dir($dataPath)) {
            @mkdir($dataPath, 0775, true);
        }

        return new self($dataPath);
    }

    public function dataPath(): string
    {
        return $this->dataPath;
    }

    // ============================================================= lecture

    public function select(string $table, array $spec = []): array
    {
        $rows = $this->rowsOf($table);
        $where = $spec['where'] ?? [];

        $result = [];
        foreach ($rows as $row) {
            if ($this->matches($row, $where)) {
                $result[] = $row;
            }
        }

        if (!empty($spec['order'])) {
            $result = $this->sortRows($result, $spec['order']);
        }

        $offset = max(0, (int) ($spec['offset'] ?? 0));
        if ($offset > 0) {
            $result = array_slice($result, $offset);
        }
        if (isset($spec['limit'])) {
            $limit = (int) $spec['limit'];
            if ($limit >= 0) {
                $result = array_slice($result, 0, $limit);
            }
        }

        if (!empty($spec['fields'])) {
            $result = $this->project($result, (array) $spec['fields']);
        }

        return $result;
    }

    public function findOne(string $table, array $where = []): ?array
    {
        $rows = $this->select($table, ['where' => $where, 'limit' => 1]);

        return $rows[0] ?? null;
    }

    public function find(string $table, int|string $pk): ?array
    {
        $def = Schema::table($table);
        $key = is_int($pk) ? $pk : (ctype_digit((string) $pk) ? (int) $pk : $pk);
        $index = $this->pkIndexOf($table);

        return isset($index[$key]) ? ($this->rowsOf($table)[$index[$key]] ?? null) : null;
    }

    public function count(string $table, array $where = []): int
    {
        return count($this->select($table, ['where' => $where]));
    }

    public function sum(string $table, string $column, array $where = []): float
    {
        $total = 0.0;
        foreach ($this->select($table, ['where' => $where]) as $row) {
            $value = $row[$column] ?? null;
            if (is_numeric($value)) {
                $total += (float) $value;
            }
        }

        return round($total, 2);
    }

    public function exists(string $table, array $where = []): bool
    {
        return $this->findOne($table, $where) !== null;
    }

    public function all(string $table): array
    {
        $def = Schema::table($table);
        $pk = $def['pk'];
        $all = [];
        foreach ($this->rowsOf($table) as $row) {
            $all[$row[$pk]] = $row;
        }

        return $all;
    }

    // ============================================================ écriture

    public function insert(string $table, array $row): int
    {
        $this->guardWritable($table);
        $def = Schema::table($table);
        $pk = $def['pk'];

        $new = $this->prepare($table, $row, $def);
        if ($def['autoIncrement']) {
            if (empty($new[$pk])) {
                $new[$pk] = $this->table($table)->takeNextId();
            } else {
                // id explicite (seed/reprise) : avance le compteur au-delà
                $this->table($table)->bumpBeyond((int) $new[$pk]);
            }
        } else {
            if (empty($new[$pk])) {
                throw new ConstraintError("{$table}.{$pk} : clé primaire requise (table sans auto-incrément)");
            }
        }

        // déclencheurs BEFORE INSERT (peuvent ajuster la ligne ou refuser)
        $new = Triggers::fire($this, $table, 'before', 'insert', null, $new);

        // contraintes sur l'état FINAL (comme un SGBD : CHECK après triggers)
        $this->assertConstraints($table, $new, null, $def);

        $this->table($table)->append($new);
        $this->invalidate($table);

        // journal + déclencheurs AFTER
        $this->journal?->log('insert', $table, $new[$pk], null, $new, $this->actor);
        Triggers::fire($this, $table, 'after', 'insert', null, $new);

        $this->autoFlush();

        return (int) $new[$pk];
    }

    public function update(string $table, array $where, array $changes): int
    {
        $this->guardWritable($table);
        $def = Schema::table($table);
        $pk = $def['pk'];

        $changed = 0;
        foreach ($this->matchingIndexes($table, $where) as $index) {
            $old = $this->rowsOf($table)[$index];
            $new = $this->prepareChanges($table, $old, $changes, $def);

            // déclencheurs BEFORE UPDATE (matrice RB-11, formule RB-15…)
            $new = Triggers::fire($this, $table, 'before', 'update', $old, $new);

            // horodatage automatique date_modification (ON UPDATE CURRENT_TIMESTAMP)
            if (isset($def['columns']['date_modification']) && !array_key_exists('date_modification', $changes)) {
                $new['date_modification'] = date('Y-m-d H:i:s');
            }

            $this->assertConstraints($table, $new, $old, $def);

            if ($this->rowEquals($old, $new)) {
                continue; // sémantique ROW_COUNT : seules les lignes modifiées comptent
            }
            $this->table($table)->replace($index, $new);
            $this->invalidate($table);
            ++$changed;

            $this->journal?->log('update', $table, $new[$pk], $old, $new, $this->actor);
            Triggers::fire($this, $table, 'after', 'update', $old, $new);
        }

        $this->autoFlush();

        return $changed;
    }

    public function delete(string $table, array $where): int
    {
        $this->guardWritable($table);
        $def = Schema::table($table);
        $pk = $def['pk'];

        $deleted = 0;
        // les index sont re-résolus à chaque tour : les cascades décalent les lignes
        while (true) {
            $indexes = $this->matchingIndexes($table, $where);
            if ($indexes === []) {
                break;
            }
            $index = $indexes[0];
            $old = $this->rowsOf($table)[$index];

            // déclencheurs BEFORE DELETE (RB-14 : produit référencé…)
            Triggers::fire($this, $table, 'before', 'delete', $old, null);

            // clés étrangères : RESTRICT refuse, CASCADE supprime en cascade
            $this->assertChildrenRemovable($table, $old[$pk]);

            $this->table($table)->remove($index);
            $this->invalidate($table);
            ++$deleted;

            $this->journal?->log('delete', $table, $old[$pk], $old, null, $this->actor);
            Triggers::fire($this, $table, 'after', 'delete', $old, null);

            if (empty($where)) {
                continue; // suppression complète demandée : boucle jusqu'à épuisement
            }
            if (!$this->exists($table, $where)) {
                break;
            }
        }

        $this->autoFlush();

        return $deleted;
    }

    public function nextId(string $table): int
    {
        Schema::table($table);

        return $this->table($table)->peekNextId();
    }

    // ======================================================== transactions

    public function beginTransaction(): void
    {
        if ($this->transaction) {
            throw new QueryError('Transaction déjà ouverte (imbrication interdite, comme un SGBD simple)');
        }
        $this->snapshot = [];
        foreach (array_keys($this->tables) as $name) {
            $this->snapshot[$name] = $this->table($name)->snapshot();
        }
        $this->transaction = true;
    }

    public function commit(): void
    {
        if (!$this->transaction) {
            throw new QueryError('Aucune transaction ouverte');
        }
        $this->transaction = false;
        $this->snapshot = [];
        $this->flush();
    }

    public function rollback(): void
    {
        if (!$this->transaction) {
            throw new QueryError('Aucune transaction ouverte');
        }
        foreach ($this->snapshot as $name => $snap) {
            $this->table($name)->restore($snap);
            $this->invalidate($name);
        }
        $this->transaction = false;
        $this->snapshot = [];
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    /** Exécute $work dans une transaction ; rollback automatique sur exception. */
    public function transactional(callable $work): mixed
    {
        $this->beginTransaction();
        try {
            $result = $work($this);
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($this->transaction) {
                $this->rollback();
            }
            throw $e;
        }
    }

    /** Écrit toutes les tables modifiées sur disque (atomique par table). */
    public function flush(): void
    {
        foreach ($this->tables as $table) {
            $table->flush();
        }
    }

    public function setActor(array $actor): void
    {
        $this->actor = [
            'id' => $actor['id'] ?? null,
            'role' => in_array($actor['role'] ?? '', ['CLIENT', 'ADMIN', 'SYSTEME'], true) ? $actor['role'] : 'SYSTEME',
            'nom' => $actor['nom'] ?? null,
        ];
    }

    public function actor(): array
    {
        return $this->actor;
    }

    /** Force l'état d'une table (seed, tests, réinstallation). */
    public function replaceAll(string $table, array $rows, ?int $autoIncrement = null): void
    {
        $def = Schema::table($table);
        $pk = $def['pk'];
        $prepared = [];
        $next = 1;
        foreach ($rows as $row) {
            $line = $this->prepare($table, $row, $def);
            if ($def['autoIncrement'] && empty($line[$pk])) {
                // un INSERT de masse affecte les PK manquantes, comme un SGBD
                $line[$pk] = $next;
            }
            $next = max($next, (int) $line[$pk] + 1);
            $prepared[] = $line;
        }
        $this->table($table)->setRows($prepared, $autoIncrement ?? $next);
        $this->invalidate($table);
        $this->autoFlush();
    }

    // ============================================================ interne

    private function table(string $name): JsonTable
    {
        if (!isset($this->tables[$name])) {
            Schema::table($name); // liste blanche
            $this->tables[$name] = new JsonTable($name, $this->dataPath . '/' . $name . '.json', Schema::table($name)['autoIncrement']);
        }

        return $this->tables[$name];
    }

    /** @return list<array<string,mixed>> */
    private function rowsOf(string $table): array
    {
        return $this->table($table)->rows();
    }

    private function guardWritable(string $table): void
    {
        Schema::table($table);
    }

    /**
     * Prépare une ligne pour insertion : defaults, coercition, NOT NULL, ENUM.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function prepare(string $table, array $row, array $def): array
    {
        $unknown = array_diff(array_keys($row), array_keys($def['columns']));
        if ($unknown !== []) {
            throw new QueryError("{$table} : colonne(s) inconnue(s) — " . implode(', ', $unknown));
        }

        $prepared = [];
        foreach ($def['columns'] as $col => $meta) {
            if (array_key_exists($col, $row)) {
                $prepared[$col] = $this->coerce($table, $col, $row[$col], $meta);
                continue;
            }
            if (array_key_exists('default', $meta)) {
                $prepared[$col] = $meta['default'];
                continue;
            }
            if ($meta['type'] === 'datetime') {
                $prepared[$col] = date('Y-m-d H:i:s'); // CURRENT_TIMESTAMP
                continue;
            }
            $prepared[$col] = null; // nullable sans valeur
        }

        // NB : le contrôle NOT NULL est fait dans assertConstraints, APRÈS les
        // déclencheurs BEFORE (comme un SGBD : un trigger peut remplir la colonne,
        // c'est le cas de ligne_commande.prix_unitaire rempli par le snapshot RB-06).

        return $prepared;
    }

    /**
     * Applique des changements sur une ligne existante (semi-préparation).
     *
     * @return array<string,mixed>
     */
    private function prepareChanges(string $table, array $old, array $changes, array $def): array
    {
        $unknown = array_diff(array_keys($changes), array_keys($def['columns']));
        if ($unknown !== []) {
            throw new QueryError("{$table} : colonne(s) inconnue(s) — " . implode(', ', $unknown));
        }
        $new = $old;
        foreach ($changes as $col => $value) {
            $new[$col] = $this->coerce($table, $col, $value, $def['columns'][$col]);
        }

        return $new;
    }

    /** Coercition de type + ENUM + longueur maximale (VARCHAR). */
    private function coerce(string $table, string $col, mixed $value, array $meta): mixed
    {
        if ($value === null) {
            return null;
        }
        switch ($meta['type']) {
            case 'int':
                if (is_bool($value)) {
                    return $value ? 1 : 0;
                }
                if (!is_numeric($value)) {
                    throw new ConstraintError("{$table}.{$col} : entier attendu, reçu « " . get_debug_type($value) . ' »');
                }

                return (int) $value;
            case 'decimal':
                if (!is_numeric($value)) {
                    throw new ConstraintError("{$table}.{$col} : nombre décimal attendu, reçu « " . get_debug_type($value) . ' »');
                }

                return round((float) $value, 2);
            case 'bool':
                if (is_string($value)) {
                    return !in_array(strtolower($value), ['', '0', 'false', 'non'], true);
                }

                return (bool) $value;
            case 'datetime':
                $value = (string) $value;

                return $value === '' ? null : $value;
            case 'string':
            case 'text':
            default:
                $value = (string) $value;
                if (isset($meta['max']) && strlen($value) > $meta['max']) {
                    throw new ConstraintError("{$table}.{$col} : " . strlen($value) . ' caractères > maximum ' . $meta['max']);
                }

                return $value;
        }
    }

    /**
     * Contraintes d'intégrité sur l'état final d'une ligne.
     *
     * @param array<string,mixed>|null $old ligne avant modification (exclusion UNIQUE)
     */
    private function assertConstraints(string $table, array $new, ?array $old, array $def): void
    {
        $pk = $def['pk'];

        // NOT NULL — après les déclencheurs BEFORE, comme un SGBD (un trigger
        // peut remplir la colonne : ligne_commande.prix_unitaire, RB-06)
        foreach ($def['columns'] as $col => $meta) {
            $isAutoPk = $col === $pk && $def['autoIncrement'];
            if (($meta['required'] ?? false) === true && $new[$col] === null && !$isAutoPk) {
                throw new ConstraintError("{$table}.{$col} : valeur obligatoire (NOT NULL)");
            }
        }

        // ENUM
        foreach ($def['columns'] as $col => $meta) {
            if (isset($meta['enum']) && $new[$col] !== null && !in_array($new[$col], $meta['enum'], true)) {
                throw new ConstraintError("{$table}.{$col} : valeur « {$new[$col]} » hors de l'ENUM (" . implode(', ', $meta['enum']) . ')');
            }
        }

        // CHECK (grammaire déclarative des filtres)
        foreach ($def['checks'] as $name => $conditions) {
            if (!$this->matches($new, (array) $conditions)) {
                throw new ConstraintError("{$table} : contrainte CHECK {$name} violée");
            }
        }

        // UNIQUE
        foreach ($def['unique'] as $name => $cols) {
            foreach ($this->rowsOf($table) as $row) {
                if ($old !== null && ($row[$pk] === $old[$pk])) {
                    continue; // la ligne elle-même ne compte pas
                }
                $same = true;
                foreach ($cols as $col) {
                    if (($row[$col] ?? null) !== ($new[$col] ?? null)) {
                        $same = false;
                        break;
                    }
                }
                if ($same) {
                    $shown = implode(', ', array_map(
                        static fn (string $c): string => $c . '=' . var_export($new[$c], true),
                        $cols
                    ));
                    throw new ConstraintError("{$table} : contrainte UNIQUE {$name} violée ({$shown})");
                }
            }
        }

        // FOREIGN KEY (valeur non nulle -> le parent doit exister)
        foreach ($def['foreignKeys'] as $col => $fk) {
            $value = $new[$col] ?? null;
            if ($value !== null && $this->find($fk['table'], (int) $value) === null) {
                throw new ConstraintError("{$table}.{$col} = {$value} : aucune ligne « {$fk['table']}.{$fk['column']} » correspondante (FK {$fk['onDelete']})");
            }
        }
    }

    /** FK onDelete RESTRICT/CASCADE au moment de supprimer une ligne parente. */
    private function assertChildrenRemovable(string $table, int|string $pkValue): void
    {
        foreach (Schema::childrenOf($table) as $child) {
            // where correct (spec select + condition positionnelle) : voir Filter::eq
            $childRows = $this->select($child['table'], ['where' => [[$child['column'], '=', $pkValue]]]);
            if ($childRows === []) {
                continue;
            }
            if ($child['onDelete'] === 'RESTRICT') {
                throw new ConstraintError("Impossible de supprimer {$table} #{$pkValue} : {$child['table']}.{$child['column']} référence encore cette valeur (RESTRICT)");
            }
            // CASCADE : suppression récursive (les triggers du fils s'exécutent aussi)
            $this->delete($child['table'], [[$child['column'], '=', $pkValue]]);
        }
    }

    /**
     * Évalue une spécification « where » contre une ligne (opérations natives).
     *
     * @param array<string,mixed> $row
     * @param list<mixed> $where
     */
    private function matches(array $row, array $where): bool
    {
        foreach ($where as $condition) {
            if (!is_array($condition) || $condition === []) {
                throw new QueryError('Condition mal formée : ' . var_export($condition, true));
            }
            if (!array_is_list($condition)) {
                throw new QueryError('Condition mal formée (liste [colonne, opérateur, valeur] attendue) : ' . var_export($condition, true));
            }
            $head = $condition[0];
            if (($head === 'or' || $head === 'and') && isset($condition[1]) && is_array($condition[1])) {
                $ok = $head === 'or' ? false : true;
                foreach ($condition[1] as $sub) {
                    $subOk = $this->matches($row, [$sub]);
                    if ($head === 'or' && $subOk) {
                        $ok = true;
                        break;
                    }
                    if ($head === 'and' && !$subOk) {
                        $ok = false;
                        break;
                    }
                }
                if (!$ok) {
                    return false;
                }
                continue;
            }
            if (!$this->matchesOne($row, $condition)) {
                return false;
            }
        }

        return true;
    }

    /** @param list{string,string,mixed} $condition */
    private function matchesOne(array $row, array $condition): bool
    {
        [$col, $op, $value] = [$condition[0], strtolower($condition[1]), $condition[2] ?? null];
        $cell = $row[$col] ?? null;

        switch ($op) {
            case '=':
                return $this->looseEquals($cell, $value);
            case '!=':
            case '<>':
                return !$this->looseEquals($cell, $value);
            case '<':
                return $this->comparable($cell, $value) && $cell < $value;
            case '<=':
                return $this->comparable($cell, $value) && $cell <= $value;
            case '>':
                return $this->comparable($cell, $value) && $cell > $value;
            case '>=':
                return $this->comparable($cell, $value) && $cell >= $value;
            case 'like':
                return $this->like($cell, (string) $value);
            case 'not-like':
                return $cell === null || !$this->like($cell, (string) $value);
            case 'in':
                foreach ((array) $value as $candidate) {
                    if ($this->looseEquals($cell, $candidate)) {
                        return true;
                    }
                }

                return false;
            case 'not-in':
                foreach ((array) $value as $candidate) {
                    if ($this->looseEquals($cell, $candidate)) {
                        return false;
                    }
                }

                return true;
            case 'between':
                [$min, $max] = (array) $value;

                return $this->comparable($cell, $min) && $cell >= $min && $cell <= $max;
            case 'null':
                return $cell === null;
            case 'not-null':
                return $cell !== null;
            default:
                throw new QueryError("Opérateur de filtre inconnu : « {$op} »");
        }
    }

    /** Égalité « collation » : numérique si les deux le sont, sinon pliée. */
    private function looseEquals(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }
        if (is_bool($a) || is_bool($b)) {
            return ((int) (bool) $a) === ((int) (bool) $b);
        }
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return Text::fold((string) $a) === Text::fold((string) $b);
    }

    /** Les comparaisons ordonnées exigent deux nombres ou deux chaînes. */
    private function comparable(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }
        $bothNumeric = is_numeric($a) && is_numeric($b);
        $bothString = is_string($a) && is_string($b);

        return $bothNumeric || $bothString;
    }

    /** LIKE façon utf8mb4_unicode_ci : jokers % et _, casse/accents pliés. */
    private function like(mixed $cell, string $pattern): bool
    {
        if ($cell === null) {
            return false;
        }
        $quoted = preg_quote(Text::fold($pattern), '/'); // % et _ ne sont pas des méta-caractères regex
        $regex = '/^' . str_replace(['%', '_'], ['.*', '.'], $quoted) . '$/u';

        return (bool) preg_match($regex, Text::fold((string) $cell));
    }

    /** Tri multi-clés stable (nombres natifs, chaînes « collation »). */
    private function sortRows(array $rows, array $order): array
    {
        usort($rows, function (array $a, array $b) use ($order): int {
            foreach ($order as [$col, $direction]) {
                $va = $a[$col] ?? null;
                $vb = $b[$col] ?? null;
                if ($va === null && $vb === null) {
                    continue;
                }
                if ($va === null) {
                    return -1; // NULL d'abord (asc)
                }
                if ($vb === null) {
                    return 1;
                }
                if (is_numeric($va) && is_numeric($vb)) {
                    $cmp = ((float) $va <=> (float) $vb) <=> 0 ?: ((float) $va <=> (float) $vb);
                } else {
                    $cmp = Text::compare((string) $va, (string) $vb);
                }
                if ($cmp !== 0) {
                    return strtolower($direction) === 'desc' ? -$cmp : $cmp;
                }
            }

            return 0;
        });

        return $rows;
    }

    /** Projection (SELECT de colonnes). */
    private function project(array $rows, array $fields): array
    {
        return array_map(static function (array $row) use ($fields): array {
            $out = [];
            foreach ($fields as $field) {
                if (!array_key_exists($field, $row)) {
                    throw new QueryError("Colonne de projection inconnue : « {$field} »");
                }
                $out[$field] = $row[$field];
            }

            return $out;
        }, $rows);
    }

    /** Index des positions des lignes vérifiant le where. @return list<int> */
    private function matchingIndexes(string $table, array $where): array
    {
        $indexes = [];
        foreach ($this->rowsOf($table) as $i => $row) {
            if ($this->matches($row, $where)) {
                $indexes[] = $i;
            }
        }

        return $indexes;
    }

    /** Index haché pk -> position (reconstruction paresseuse). @return array<int|string,int> */
    private function pkIndexOf(string $table): array
    {
        if (!isset($this->pkIndex[$table]) || ($this->pkIndexStale[$table] ?? false)) {
            $index = [];
            foreach ($this->rowsOf($table) as $i => $row) {
                $index[$row[Schema::primaryKey($table)]] = $i;
            }
            $this->pkIndex[$table] = $index;
            $this->pkIndexStale[$table] = false;
        }

        return $this->pkIndex[$table];
    }

    private function invalidate(string $table): void
    {
        $this->pkIndexStale[$table] = true;
    }

    /** Deux lignes sont-elles identiques valeur par valeur ? */
    private function rowEquals(array $a, array $b): bool
    {
        if (array_keys($a) !== array_keys($b)) {
            return false;
        }
        foreach ($a as $key => $value) {
            $other = $b[$key] ?? null;
            if (is_numeric($value) && is_numeric($other)) {
                if ((float) $value !== (float) $other) {
                    return false;
                }
                continue;
            }
            if ($value !== $other) {
                return false;
            }
        }

        return true;
    }

    private function maxPk(array $rows, string $pk): int
    {
        $max = 0;
        foreach ($rows as $row) {
            if (is_numeric($row[$pk] ?? null) && (int) $row[$pk] > $max) {
                $max = (int) $row[$pk];
            }
        }

        return $max;
    }

    /** Hors transaction : persistance immédiate de chaque écriture. */
    private function autoFlush(): void
    {
        if (!$this->transaction) {
            $this->flush();
        }
    }
}
