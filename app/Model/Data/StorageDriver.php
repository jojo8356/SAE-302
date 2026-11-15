<?php

declare(strict_types=1);

/**
 * MiniShop — énumération du pilote de gestion des données.
 *
 * Le pilote concret est choisi par cette énumération (clé
 * « storage_driver » de la configuration). Le mode SQL est le parcours
 * applicatif principal ; JSON reste disponible explicitement pour les tests
 * portables, les démonstrations sans serveur et les installations isolées.
 *
 *   - StorageDriver::SQL → App\Model\Data\SqlStore : MySQL/MariaDB via PDO,
 *     procédures, transactions et déclencheurs définis dans sql/01→04.
 *   - StorageDriver::JSON → App\Model\Data\JsonStore : tables sous forme de
 *     fichiers dans data/minishop ; aucune procédure SQL n'est simulée.
 *
 * Les deux pilotes partagent StoreInterface. Les capacités SQL spécialisées
 * (procédures, fonctions stockées, verrouillage de lignes) sont exposées par
 * des interfaces optionnelles, sans imposer une fausse API SQL au pilote JSON.
 */

namespace App\Model\Data;

enum StorageDriver: string
{
    /** Stockage fichier JSON — mode secondaire sans serveur SQL. */
    case JSON = 'json';

    /** Stockage relationnel MySQL/MariaDB par PDO — mode applicatif principal. */
    case SQL = 'sql';

    /** Pilote par défaut si la configuration ne dit rien. */
    public const DEFAULT = self::SQL;

    /** Instancie le pilote correspondant à la valeur de configuration. */
    public function createStore(): StoreInterface
    {
        return match ($this) {
            self::JSON => JsonStore::open(self::defaultDataPath()),
            self::SQL  => new SqlStore(),
        };
    }

    /** Chemin par défaut des fichiers de données JSON (racine du projet). */
    public static function defaultDataPath(): string
    {
        return dirname(__DIR__, 3) . '/data/minishop';
    }

    /** Depuis une chaîne de configuration ('json' / 'sql'), avec repli SQL. */
    public static function fromConfig(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? self::DEFAULT;
    }
}
