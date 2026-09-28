<?php

declare(strict_types=1);

/**
 * MiniShop — point d'accès unique à la couche de données (annexe 17.7 §1).
 *
 * L'application ne parle PAS à un SGBD : Database::store() retourne le pilote
 * choisi par l'ÉNUMÉRATION App\Model\Data\StorageDriver, lu dans la
 * configuration (app/Config/env.php — jamais commité — ou variables
 * d'environnement) :
 *
 *   'storage_driver' => 'json'  → JsonStore (moteur JSON natif, actif)
 *   'storage_driver' => 'sql'   → SqlStore  (migration finale, lots L14+)
 *
 * Les contrôleurs et les repositories ne connaissent QUE StoreInterface :
 * basculer d'un pilote à l'autre ne change aucune autre ligne du projet.
 */

namespace App\Config;

use App\Model\Data\StorageDriver;
use App\Model\Data\StoreInterface;

final class Database
{
    private static ?StoreInterface $store = null;

    /** @var array<string,mixed>|null configuration chargée */
    private static ?array $config = null;

    /** Retourne le store applicatif (singleton par requête). */
    public static function store(): StoreInterface
    {
        if (self::$store === null) {
            $config = self::loadConfig();
            $driver = StorageDriver::fromConfig($config['storage_driver'] ?? null);
            self::$store = $driver === StorageDriver::JSON
                ? \App\Model\Data\JsonStore::open(self::dataPath($config))
                : $driver->createStore();
        }

        return self::$store;
    }

    /** Chemin des données JSON (surchargeable : tests, volumétrie). */
    public static function dataPath(array $config): string
    {
        $configured = $config['data_path'] ?? null;

        return $configured !== null && $configured !== ''
            ? rtrim((string) $configured, '/')
            : StorageDriver::defaultDataPath();
    }

    /** Configuration brute (dev/debug). @return array<string,mixed> */
    public static function config(): array
    {
        return self::$config ??= self::loadConfig();
    }

    /** Réinitialise le singleton (tests). */
    public static function reset(): void
    {
        self::$store = null;
        self::$config = null;
    }

    /** @return array<string,mixed> */
    private static function loadConfig(): array
    {
        $candidates = [
            __DIR__ . '/env.php',
            dirname(__DIR__, 2) . '/config/env.php',
            dirname(__DIR__, 2) . '/.env.php',
        ];
        foreach ($candidates as $fichier) {
            if (is_file($fichier)) {
                $config = include $fichier;
                if (is_array($config)) {
                    return $config;
                }
            }
        }

        // Fallback : variables d'environnement seules
        return [
            'storage_driver' => getenv('STORAGE_DRIVER') ?: 'json',
            'data_path' => getenv('DATA_PATH') ?: null,
            'env' => getenv('APP_ENV') ?: 'dev',
            'debug' => true,
        ];
    }
}
