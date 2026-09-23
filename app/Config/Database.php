<?php
declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;

/**
 * MiniShop — connexion PDO centralisée (cf. annexe 17.7 §1).
 * - Requêtes préparées réelles (EMU false) — SEC-01
 * - ERRMODE EXCEPTION — jamais de warning muet
 * - utf8mb4 / utf8mb4_unicode_ci identique MySQL et MariaDB
 */
final class Database
{
    private static ?PDO $pdo = null;

    /**
     * Retourne le singleton PDO configuré via app/Config/env.php ou variables d'environnement.
     * @param string|null $dbName Surcharge ponctuelle (volumétrie : minishop_perf)
     */
    public static function pdo(?string $dbName = null): PDO
    {
        if (self::$pdo instanceof PDO && $dbName === null) {
            return self::$pdo;
        }

        $cfg = self::loadConfig();

        $host = $cfg['db_host'] ?? '127.0.0.1';
        $port = (int) ($cfg['db_port'] ?? 3306);
        $name = $dbName ?? $cfg['db_name'] ?? $cfg['DB'] ?? 'minishop';
        $user = $cfg['db_user'] ?? 'root';
        $pass = $cfg['db_pass'] ?? '';

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);

        if ($dbName === null) {
            self::$pdo = $pdo;
        }

        return $pdo;
    }

    /** Crée un PDO vers une base précise (sans polluer le singleton). */
    public static function pdoForDatabase(string $dbName): PDO
    {
        return self::pdo($dbName);
    }

    /** Réinitialise le singleton (tests). */
    public static function reset(): void
    {
        self::$pdo = null;
    }

    /** @return array<string,mixed> */
    private static function loadConfig(): array
    {
        $candidates = [
            __DIR__ . '/env.php',
            __DIR__ . '/../../config/env.php',
            __DIR__ . '/../../.env',
        ];
        foreach ($candidates as $f) {
            if (is_file($f)) {
                $cfg = @include $f;
                if (is_array($cfg)) {
                    return $cfg;
                }
                // .env simple KEY=VALUE
                if (is_string($cfg) || $cfg === 1) {
                    $parsed = @parse_ini_file($f);
                    if (is_array($parsed)) {
                        return $parsed;
                    }
                }
            }
        }
        // Fallback : variables d'environnement seules
        return [
            'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
            'db_port' => getenv('DB_PORT') ?: '3306',
            'db_name' => getenv('DB') ?: getenv('DB_NAME') ?: 'minishop',
            'db_user' => getenv('DB_USER') ?: 'root',
            'db_pass' => getenv('DB_PASS') ?: '',
            'env'     => getenv('APP_ENV') ?: 'dev',
        ];
    }
}
