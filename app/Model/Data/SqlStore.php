<?php

declare(strict_types=1);

/**
 * MiniShop — pilote SQL (MySQL / MariaDB via PDO).
 *
 * Implémente EXACTEMENT le contrat StoreInterface : les contrôleurs, les
 * dépôts, les vues et les tests fonctionnels sont inchangés. Le pilote est
 * choisi par la configuration (`storage_driver = 'sql'`, cf. StorageDriver) :
 *
 *   « JSON » → JsonStore  : moteur natif, règles appliquées en PHP
 *   « SQL »  → SqlStore   : MySQL, règles appliquées par la base
 *                           (CHECK, clés étrangères, 16 déclencheurs,
 *                           1 fonction + 17 procédures de sql/01→03)
 *
 * Principes de sécurité (SEC-01) :
 *   - AUCUNE valeur n'est concaténée dans le SQL : tout passe par des
 *     marqueurs `?` et des requêtes préparées natives
 *     (PDO::ATTR_EMULATE_PREPARES = false) ;
 *   - les noms de tables et de colonnes sont validés contre le SCHÉMA
 *     (liste blanche Schema::tables()), jamais contre une entrée utilisateur ;
 *   - les opérateurs sont limités à une table de correspondance fermée.
 *
 * Transactions : `beginTransaction/commit/rollback` sont délégués à PDO.
 * L'acteur courant est mémorisé en PHP : il sert à appeler les procédures
 * qui tracent l'auteur (historique de statut, mouvements de stock).
 */

namespace App\Model\Data;

use App\Config\Database;
use PDO;
use PDOException;
use RuntimeException;

final class SqlStore implements StoreInterface
{
    /** Correspondance opérateur déclaratif → fragment SQL (liste fermée). */
    private const OPERATEURS = [
        '=' => '=',
        '!=' => '<>',
        '<>' => '<>',
        '<' => '<',
        '<=' => '<=',
        '>' => '>',
        '>=' => '>=',
        'like' => 'LIKE',
        'not-like' => 'NOT LIKE',
        'in' => 'IN',
        'not-in' => 'NOT IN',
        'between' => 'BETWEEN',
        'null' => 'IS NULL',
        'not-null' => 'IS NOT NULL',
    ];

    private ?PDO $pdo = null;

    /** @var array{id?: int|null, role?: string, nom?: string|null} */
    private array $acteur = ['id' => null, 'role' => 'SYSTEME', 'nom' => null];

    private int $profondeurTransaction = 0;

    public function __construct(private readonly ?PDO $connexionFournie = null)
    {
    }

    // ------------------------------------------------------------ connexion

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        if ($this->connexionFournie instanceof PDO) {
            $this->pdo = $this->connexionFournie;

            return $this->pdo;
        }

        $config = Database::config();
        $hote = (string) ($config['db_host'] ?? '127.0.0.1');
        $port = (string) ($config['db_port'] ?? 3306);
        $base = (string) ($config['db_name'] ?? 'minishop');
        $utilisateur = (string) ($config['db_user'] ?? 'root');
        $motDePasse = (string) ($config['db_pass'] ?? '');

        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $hote, $port, $base);
            $this->pdo = new PDO($dsn, $utilisateur, $motDePasse, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Connexion MySQL impossible (' . $hote . ':' . $port . '/' . $base . ') : ' . $e->getMessage()
                . ' — lancez ./scripts/load_db.sh ou repassez en pilote JSON.',
                0,
                $e,
            );
        }

        return $this->pdo;
    }

    // -------------------------------------------------------------- lecture

    /** @param array{where?: array, order?: array, limit?: int, offset?: int, fields?: array} $spec */
    public function select(string $table, array $spec = []): array
    {
        $def = $this->definition($table);
        $colonnes = $this->colonnes($table, $spec['fields'] ?? null);

        $sql = 'SELECT ' . implode(', ', array_map($this->identifiant(...), $colonnes))
            . ' FROM ' . $this->identifiant($table);
        $parametres = [];

        $where = $spec['where'] ?? [];
        if ($where !== []) {
            $sql .= ' WHERE ' . $this->clauseWhere((array) $where, $def, $parametres);
        }

        $ordre = $spec['order'] ?? [];
        if ($ordre !== []) {
            $sql .= ' ORDER BY ' . $this->clauseOrder((array) $ordre, $def);
        }

        if (isset($spec['limit'])) {
            $sql .= ' LIMIT ' . max(0, (int) $spec['limit']);
        }
        if (isset($spec['offset']) && (int) $spec['offset'] > 0) {
            if (!isset($spec['limit'])) {
                $sql .= ' LIMIT 18446744073709551615';
            }
            $sql .= ' OFFSET ' . max(0, (int) $spec['offset']);
        }

        $statement = $this->executer($sql, $parametres);
        $lignes = $statement->fetchAll();
        if ($lignes === false) {
            return [];
        }

        return array_map(fn (array $ligne): array => $this->normaliser($table, $ligne), $lignes);
    }

    public function findOne(string $table, array $where = []): ?array
    {
        $lignes = $this->select($table, ['where' => $where, 'limit' => 1]);

        return $lignes[0] ?? null;
    }

    public function find(string $table, int|string $pk): ?array
    {
        return $this->findOne($table, [[$this->clePrimaire($table), '=', $pk]]);
    }

    public function count(string $table, array $where = []): int
    {
        $def = $this->definition($table);
        $parametres = [];
        $sql = 'SELECT COUNT(*) AS n FROM ' . $this->identifiant($table);
        if ($where !== []) {
            $sql .= ' WHERE ' . $this->clauseWhere($where, $def, $parametres);
        }

        $valeur = $this->executer($sql, $parametres)->fetchColumn();

        return (int) $valeur;
    }

    public function sum(string $table, string $column, array $where = []): float
    {
        $def = $this->definition($table);
        $this->verifierColonne($column, $def, $table);
        $parametres = [];
        $sql = 'SELECT COALESCE(SUM(' . $this->identifiant($column) . '), 0) AS s FROM ' . $this->identifiant($table);
        if ($where !== []) {
            $sql .= ' WHERE ' . $this->clauseWhere($where, $def, $parametres);
        }

        return (float) $this->executer($sql, $parametres)->fetchColumn();
    }

    public function exists(string $table, array $where = []): bool
    {
        $parametres = [];
        $sql = 'SELECT 1 FROM ' . $this->identifiant($table);
        if ($where !== []) {
            $sql .= ' WHERE ' . $this->clauseWhere($where, $this->definition($table), $parametres);
        }
        $sql .= ' LIMIT 1';

        return $this->executer($sql, $parametres)->fetchColumn() !== false;
    }

    /** @return array<int|string, array<string,mixed>> */
    public function all(string $table): array
    {
        $indexe = [];
        foreach ($this->select($table) as $ligne) {
            $indexe[$ligne[$this->clePrimaire($table)]] = $ligne;
        }

        return $indexe;
    }

    // ------------------------------------------------------------- écriture

    /** @param array<string,mixed> $row */
    public function insert(string $table, array $row): int
    {
        $def = $this->definition($table);
        $colonnes = [];
        $marqueurs = [];
        $valeurs = [];

        foreach ($row as $colonne => $valeur) {
            $this->verifierColonne((string) $colonne, $def, $table);
            if ($colonne === ($def['pk'] ?? null) && ($valeur === null || (int) $valeur === 0)) {
                continue; // laisser l'AUTO_INCREMENT faire son travail
            }
            $colonnes[] = $this->identifiant((string) $colonne);
            $marqueurs[] = '?';
            $valeurs[] = $this->versSql($valeur);
        }

        if ($colonnes === []) {
            throw new RuntimeException('insertion vide refusée sur ' . $table);
        }

        $sql = 'INSERT INTO ' . $this->identifiant($table)
            . ' (' . implode(', ', $colonnes) . ') VALUES (' . implode(', ', $marqueurs) . ')';

        $this->executer($sql, $valeurs);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $changes */
    public function update(string $table, array $where, array $changes): int
    {
        $def = $this->definition($table);
        if ($changes === []) {
            return 0;
        }
        if ($where === []) {
            throw new RuntimeException('UPDATE sans condition refusé sur ' . $table);
        }

        $affectations = [];
        $valeurs = [];
        foreach ($changes as $colonne => $valeur) {
            $this->verifierColonne((string) $colonne, $def, $table);
            if ($colonne === ($def['pk'] ?? null)) {
                throw new RuntimeException('la clé primaire de ' . $table . ' ne se modifie pas');
            }
            $affectations[] = $this->identifiant((string) $colonne) . ' = ?';
            $valeurs[] = $this->versSql($valeur);
        }

        $parametresWhere = [];
        $clause = $this->clauseWhere($where, $def, $parametresWhere);

        $sql = 'UPDATE ' . $this->identifiant($table) . ' SET ' . implode(', ', $affectations) . ' WHERE ' . $clause;

        return $this->executer($sql, array_merge($valeurs, $parametresWhere))->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        if ($where === []) {
            throw new RuntimeException('DELETE sans condition refusé sur ' . $table);
        }

        $parametres = [];
        $clause = $this->clauseWhere($where, $this->definition($table), $parametres);
        $sql = 'DELETE FROM ' . $this->identifiant($table) . ' WHERE ' . $clause;

        return $this->executer($sql, $parametres)->rowCount();
    }

    public function nextId(string $table): int
    {
        $pk = $this->clePrimaire($table);
        $sql = 'SELECT COALESCE(MAX(' . $this->identifiant($pk) . '), 0) + 1 AS suivant FROM ' . $this->identifiant($table);

        return (int) $this->executer($sql, [])->fetchColumn();
    }

    // --------------------------------------------------- transactions / io

    public function beginTransaction(): void
    {
        if ($this->profondeurTransaction === 0) {
            $this->pdo()->beginTransaction();
        }
        ++$this->profondeurTransaction;
    }

    public function commit(): void
    {
        if ($this->profondeurTransaction <= 0) {
            return;
        }
        --$this->profondeurTransaction;
        if ($this->profondeurTransaction === 0 && $this->pdo()->inTransaction()) {
            $this->pdo()->commit();
        }
    }

    public function rollback(): void
    {
        if ($this->profondeurTransaction <= 0) {
            return;
        }
        $this->profondeurTransaction = 0;
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->rollBack();
        }
    }

    public function inTransaction(): bool
    {
        return $this->profondeurTransaction > 0;
    }

    public function transactional(callable $work): mixed
    {
        $this->beginTransaction();
        try {
            $resultat = $work($this);
            $this->commit();

            return $resultat;
        } catch (\Throwable $erreur) {
            $this->rollback();
            throw $erreur;
        }
    }

    /** @param array{id?: int|null, role?: 'CLIENT'|'ADMIN'|'SYSTEME', nom?: string|null} $actor */
    public function setActor(array $actor): void
    {
        $identifiant = null;
        if (isset($actor['id'])) {
            $identifiant = (int) $actor['id'];
        }
        $nom = null;
        if (isset($actor['nom'])) {
            $nom = (string) $actor['nom'];
        }
        $this->acteur = [
            'id' => $identifiant,
            'role' => (string) ($actor['role'] ?? 'SYSTEME'),
            'nom' => $nom,
        ];
    }

    /** @return array{id?: int|null, role?: string, nom?: string|null} */
    public function actor(): array
    {
        return $this->acteur;
    }

    // ------------------------------------------------- procédures stockées

    /**
     * Appelle une procédure stockée de sql/02 et retourne ses paramètres OUT.
     *
     * Les procédures sont la couche métier de la base (RB-04, RB-05, RB-06,
     * RB-11, RB-15, RB-18, RB-20) : les écritures critiques passent par elles
     * quand ce pilote est actif (voir CommandeRepository / ProduitRepository).
     *
     *   $store->appeler('sp_cancel_order', [$idCommande, $idClient], ['p_code_retour']);
     *
     * @param list<mixed>  $entrees valeurs des paramètres IN, dans l'ordre
     * @param list<string> $sorties noms des paramètres OUT à récupérer
     * @return array<string,mixed>
     */
    public function appeler(string $procedure, array $entrees = [], array $sorties = []): array
    {
        if (preg_match('/^sp_[a-z0-9_]+$/', $procedure) !== 1) {
            throw new RuntimeException('nom de procédure invalide : ' . $procedure);
        }

        $marqueurs = array_fill(0, count($entrees), '?');
        $variables = [];
        $select = [];
        foreach ($sorties as $indice => $nom) {
            if (preg_match('/^p_[a-z0-9_]+$/', $nom) !== 1) {
                throw new RuntimeException('paramètre de sortie invalide : ' . $nom);
            }
            $variable = 'out' . $indice;
            $marqueurs[] = '@' . $variable;
            $variables[$nom] = '@' . $variable;
            $select[] = '@' . $variable . ' AS ' . $this->identifiant($nom);
        }

        $sql = 'CALL ' . $procedure . '(' . implode(', ', $marqueurs) . ')';
        $this->executer($sql, $entrees);

        if ($select === []) {
            return [];
        }

        $requeteSorties = 'SELECT ' . implode(', ', $select);
        $ligne = $this->pdo()->query($requeteSorties)->fetch();

        if (is_array($ligne)) {
            return $ligne;
        }

        return [];
    }

    // ------------------------------------------------------------- interne

    /** @return array<string,mixed> */
    private function definition(string $table): array
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $table) !== 1) {
            throw new RuntimeException('nom de table invalide : ' . $table);
        }

        return Schema::table($table);
    }

    private function clePrimaire(string $table): string
    {
        return Schema::primaryKey($table);
    }

    private function identifiant(string $nom): string
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $nom) !== 1) {
            throw new RuntimeException('identifiant SQL invalide : ' . $nom);
        }

        return '`' . $nom . '`';
    }

    /** @param array<string,mixed> $def */
    private function verifierColonne(string $colonne, array $def, string $table): void
    {
        $colonnes = (array) ($def['columns'] ?? []);
        if (!array_key_exists($colonne, $colonnes)) {
            throw new RuntimeException('colonne inconnue ' . $table . '.' . $colonne);
        }
    }

    /**
     * @param array<string,mixed>|null $fields
     * @return list<string>
     */
    private function colonnes(string $table, ?array $fields): array
    {
        $def = $this->definition($table);
        if ($fields === null || $fields === []) {
            return array_keys((array) $def['columns']);
        }

        $retenues = [];
        foreach ($fields as $colonne) {
            $this->verifierColonne((string) $colonne, $def, $table);
            if (preg_match('/^[a-z][a-z0-9_]*$/', (string) $colonne) === 1) {
                $retenues[] = (string) $colonne;
            }
        }

        if ($retenues === []) {
            throw new RuntimeException('projection vide refusée sur ' . $table);
        }

        return $retenues;
    }

    /**
     * @param array<int,mixed> $where
     * @param array<string,mixed> $def
     * @param list<mixed> $parametres (accumulateur)
     */
    private function clauseWhere(array $where, array $def, array &$parametres): string
    {
        $fragments = [];

        foreach ($where as $condition) {
            if (!is_array($condition) || $condition === []) {
                continue;
            }

            $premier = strtolower((string) ($condition[0] ?? ''));

            // Groupe explicite : ['and', [c1, c2]] ou ['or', [c1, c2]].
            if (($premier === 'and' || $premier === 'or') && isset($condition[1]) && is_array($condition[1])) {
                $interne = [];
                foreach ($condition[1] as $sousCondition) {
                    if (is_array($sousCondition)) {
                        $interne[] = $this->clauseWhere([$sousCondition], $def, $parametres);
                    }
                }
                if ($interne !== []) {
                    $fragments[] = '(' . implode(' ' . strtoupper($premier) . ' ', $interne) . ')';
                }
                continue;
            }

            $colonne = (string) ($condition[0] ?? '');
            $operateur = strtolower((string) ($condition[1] ?? '='));
            $valeur = $condition[2] ?? null;

            if ($colonne === 'or' || $colonne === 'and') {
                continue;
            }

            $this->verifierColonne($colonne, $def, 'where');

            if (!array_key_exists($operateur, self::OPERATEURS)) {
                throw new RuntimeException('opérateur non autorisé : ' . $operateur);
            }

            $identifiant = $this->identifiant($colonne);

            if ($operateur === 'null') {
                $fragments[] = $identifiant . ' IS NULL';
                continue;
            }
            if ($operateur === 'not-null') {
                $fragments[] = $identifiant . ' IS NOT NULL';
                continue;
            }
            if ($operateur === 'in' || $operateur === 'not-in') {
                $valeurs = [$valeur];
                if (is_array($valeur)) {
                    $valeurs = $valeur;
                }
                if ($valeurs === []) {
                    $fragments[] = '1 = 0';
                    continue;
                }
                $marqueurs = [];
                foreach ($valeurs as $item) {
                    $marqueurs[] = '?';
                    $parametres[] = $this->versSql($item);
                }
                $fragments[] = $identifiant . ' ' . self::OPERATEURS[$operateur] . ' (' . implode(', ', $marqueurs) . ')';
                continue;
            }
            if ($operateur === 'between') {
                $bornes = [];
                if (is_array($valeur)) {
                    $bornes = array_values($valeur);
                }
                $min = $bornes[0] ?? null;
                $max = $bornes[1] ?? null;
                $fragments[] = $identifiant . ' BETWEEN ? AND ?';
                $parametres[] = $this->versSql($min);
                $parametres[] = $this->versSql($max);
                continue;
            }

            $fragments[] = $identifiant . ' ' . self::OPERATEURS[$operateur] . ' ?';
            $parametres[] = $this->versSql($valeur);
        }

        if ($fragments === []) {
            return '1 = 1';
        }

        return implode(' AND ', $fragments);
    }

    /**
     * @param array<int,mixed> $ordre
     * @param array<string,mixed> $def
     */
    private function clauseOrder(array $ordre, array $def): string
    {
        $fragments = [];
        foreach ($ordre as $entree) {
            if (!is_array($entree) || $entree === []) {
                continue;
            }
            $colonne = (string) ($entree[0] ?? '');
            $this->verifierColonne($colonne, $def, 'order');
            $sens = 'ASC';
            if (strtolower((string) ($entree[1] ?? 'asc')) === 'desc') {
                $sens = 'DESC';
            }
            $fragments[] = $this->identifiant($colonne) . ' ' . $sens;
        }

        if ($fragments === []) {
            return '1';
        }

        return implode(', ', $fragments);
    }

    /**
     * Exécute une requête préparée et TRADUIT les erreurs MySQL en erreurs
     * applicatives : les déclencheurs de sql/03 émettent
     * SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '<CODE>' ; on les remonte
     * en BusinessError(<CODE>) — exactement ce que fait le pilote JSON.
     * Les violations de contrainte (23000) deviennent ConstraintError.
     *
     * @param list<mixed> $parametres
     */
    private function executer(string $sql, array $parametres): \PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);

        try {
            $statement->execute($parametres);
        } catch (PDOException $erreur) {
            throw $this->traduire($erreur);
        }

        return $statement;
    }

    /** Traduit une PDOException en erreur de la couche de données. */
    private function traduire(PDOException $erreur): \Throwable
    {
        $sqlState = (string) ($erreur->errorInfo[0] ?? $erreur->getCode());
        $code = (string) ($erreur->errorInfo[1] ?? '');
        $message = (string) $erreur->getMessage();

        // 45000 = SIGNAL déclenché par une procédure ou un déclencheur.
        if ($sqlState === '45000') {
            $codeMetier = trim($message);
            if (str_contains($message, 'MESSAGE_TEXT')) {
                $morceaux = explode('MESSAGE_TEXT', $message, 2);
                $codeMetier = trim($morceaux[1], " ='\"");
            }
            if (preg_match('/^[A-Z][A-Z0-9_]+$/', $codeMetier) === 1) {
                return new BusinessError($codeMetier);
            }

            return new BusinessError('ERREUR_METIER');
        }

        // 23000 = violation d'intégrité (UNIQUE / FK / CHECK / ENUM).
        if ($sqlState === '23000' || $code === '1062' || $code === '1452' || $code === '3819') {
            return new ConstraintError('Contrainte d\'intégrité violée : ' . $erreur->getMessage(), 0, $erreur);
        }

        return new RuntimeException('Erreur SQL (' . $sqlState . ') : ' . $message, 0, $erreur);
    }

    private function versSql(mixed $valeur): mixed
    {
        if (is_bool($valeur)) {
            if ($valeur) {
                return 1;
            }

            return 0;
        }
        if ($valeur instanceof \DateTimeInterface) {
            return $valeur->format('Y-m-d H:i:s');
        }

        return $valeur;
    }

    /**
     * Aligne les types PHP sur le schéma (MySQL renvoie des chaînes).
     *
     * @param array<string,mixed> $ligne
     * @return array<string,mixed>
     */
    private function normaliser(string $table, array $ligne): array
    {
        $colonnes = (array) (Schema::table($table)['columns'] ?? []);

        foreach ($ligne as $colonne => $valeur) {
            $type = (string) ($colonnes[$colonne]['type'] ?? 'string');
            if ($valeur === null) {
                continue;
            }

            $ligne[$colonne] = match ($type) {
                'int' => (int) $valeur,
                'decimal' => (float) $valeur,
                'bool' => (bool) (int) $valeur,
                default => $valeur,
            };
        }

        return $ligne;
    }
}
