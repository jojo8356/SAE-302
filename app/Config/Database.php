<?php

declare(strict_types=1);

/**
 * MiniShop — point d'accès unique à la couche de données (annexe 17.7 §1).
 *
 * L'application ne parle PAS à un SGBD : Database::store() retourne le pilote
 * choisi par l'ÉNUMÉRATION App\Model\Data\StorageDriver :
 *
 *   'storage_driver' => 'json'  → JsonStore (moteur JSON natif, aucun service)
 *   'storage_driver' => 'sql'   → SqlStore  (MySQL/MariaDB via PDO)
 *
 * La configuration est lue dans cet ordre (le dernier gagne) :
 *
 *   1. valeurs par défaut ci-dessous ;
 *   2. `app/Config/env.php` (compatibilité avec l'ancien modèle) ;
 *   3. fichier **`.env`** à la racine, modèle versionné `.env.example` —
 *      c'est le mode recommandé : copier, éditer, ne jamais committer ;
 *   4. variables d'environnement du processus (CI, conteneur, test ponctuel).
 *
 * Basculer en MySQL ne demande donc AUCUNE modification de code :
 *
 *   cp .env.example .env
 *   # .env : STORAGE_DRIVER="sql"  DB_NAME="minishop"  DB_USER="minishop"  DB_PASSWORD="…"
 *   #        DB_SOCKET="" (vide = TCP 127.0.0.1:3306, repli automatique sur la
 *   #        socket Unix si le compte n'accepte que ce mode — cas de root sur Debian)
 *   php scripts/db_init.php        # crée la base, les 8 tables, 17 procédures, 16 triggers
 *
 * Les contrôleurs et les repositories ne connaissent QUE StoreInterface.
 */

namespace App\Config;

use App\Model\Data\JsonStore;
use App\Model\Data\StorageDriver;
use App\Model\Data\StoreInterface;

final class Database
{
    /** Valeurs de repli : une application fraîchement clonée démarre sans rien configurer. */
    private const DEFAUTS = [
        'env' => 'dev',
        'APP_ENV' => 'dev',
        'debug' => true,
        'storage_driver' => 'json',
        'data_path' => null,
        'db_host' => '127.0.0.1',
        'db_port' => 3306,
        'db_name' => 'minishop',
        'db_user' => 'root',
        'db_pass' => '',
        'db_socket' => '',
    ];

    /** Variable d'environnement → clé de configuration. */
    private const CORRESPONDANCES = [
        'APP_ENV' => 'env',
        'APP_DEBUG' => 'debug',
        'STORAGE_DRIVER' => 'storage_driver',
        'DATA_PATH' => 'data_path',
        'DB_HOST' => 'db_host',
        'DB_PORT' => 'db_port',
        'DB_NAME' => 'db_name',
        'DB_USER' => 'db_user',
        'DB_PASSWORD' => 'db_pass',
        'DB_SOCKET' => 'db_socket',
    ];

    /** Alias historiques acceptés (scripts existants, habitudes). */
    private const ALIAS = [
        'DB_PASS' => 'db_pass',
        'DB' => 'db_name',
    ];

    private static ?StoreInterface $store = null;

    /** @var array<string,mixed>|null configuration chargée */
    private static ?array $config = null;

    /** Retourne le store applicatif (singleton par requête). */
    public static function store(): StoreInterface
    {
        if (self::$store === null) {
            $config = self::loadConfig();
            $driver = StorageDriver::fromConfig($config['storage_driver'] ?? null);
            if ($driver === StorageDriver::JSON) {
                self::$store = JsonStore::open(self::dataPath($config));
            } else {
                self::$store = $driver->createStore();
            }
        }

        return self::$store;
    }

    /** Chemin des données JSON (surchargeable : tests, volumétrie). */
    public static function dataPath(array $config): string
    {
        $configured = $config['data_path'] ?? null;

        if ($configured !== null && $configured !== '') {
            return rtrim((string) $configured, '/');
        }

        return StorageDriver::defaultDataPath();
    }

    /** Configuration brute (dev/debug). @return array<string,mixed> */
    public static function config(): array
    {
        return self::$config ??= self::loadConfig();
    }

    /** Pilote actif ('json' ou 'sql'), tel que configuré. */
    public static function driver(): string
    {
        $config = self::config();

        return (string) ($config['storage_driver'] ?? 'json');
    }

    /** Fichier `app/Config/env.php` réellement présent (sinon `null`). */
    public static function fichierConfig(): ?string
    {
        foreach (self::fichiersConfig() as $fichier) {
            if (is_file($fichier) && !self::estClasseEnv($fichier)) {
                return $fichier;
            }
        }

        return null;
    }

    /** Sous Windows, env.php peut désigner le fichier de classe Env.php. */
    private static function estClasseEnv(string $fichier): bool
    {
        $candidat = realpath($fichier);
        $classe = realpath(__DIR__ . '/Env.php');
        if ($candidat === false || $classe === false) {
            return false;
        }

        return PHP_OS_FAMILY === 'Windows'
            ? strcasecmp($candidat, $classe) === 0
            : $candidat === $classe;
    }

    /** Sources lues par la dernière configuration (diagnostic, `db_init.php --check`). */
    public static function sources(): array
    {
        $sources = [];
        $fichier = self::fichierConfig();
        if ($fichier !== null) {
            $sources[] = $fichier;
        }
        $env = Env::cheminLu();
        if ($env !== null) {
            $sources[] = $env;
        }

        return $sources;
    }

    /** Réinitialise le singleton (tests, bascule de configuration à chaud). */
    public static function reset(): void
    {
        self::$store = null;
        self::$config = null;
        Env::reinitialiser();
    }

    /** @return list<string> */
    private static function fichiersConfig(): array
    {
        return [
            __DIR__ . '/env.php',
            dirname(__DIR__, 2) . '/config/env.php',
            dirname(__DIR__, 2) . '/.env.php',
        ];
    }

    /** @return array<string,mixed> */
    private static function loadConfig(): array
    {
        $config = self::DEFAUTS;

        // 2. ancien fichier PHP (s'il existe encore)
        $fichier = self::fichierConfig();
        if ($fichier !== null) {
            $contenu = include $fichier;
            if (is_array($contenu)) {
                $config = array_merge($config, $contenu);
            }
        }

        // 3 et 4. `.env` puis variables d'environnement réelles (prioritaires)
        foreach (self::CORRESPONDANCES as $variable => $cle) {
            $valeur = Env::get($variable);
            if ($valeur === null) {
                continue;
            }
            $config[$cle] = self::typer($cle, $valeur);
        }
        foreach (self::ALIAS as $variable => $cle) {
            $valeur = Env::get($variable);
            if ($valeur === null || $valeur === '') {
                continue;
            }
            $config[$cle] = self::typer($cle, $valeur);
        }

        $config['APP_ENV'] = $config['env'];

        return $config;
    }

    /** Convertit une valeur textuelle vers le type attendu par la clé. */
    private static function typer(string $cle, string $valeur): mixed
    {
        if ($cle === 'debug') {
            return Env::booleen('APP_DEBUG', true);
        }
        if ($cle === 'db_port') {
            return Env::entier('DB_PORT', 3306);
        }
        if ($cle === 'data_path' && trim($valeur) === '') {
            return null;
        }

        return $valeur;
    }
}
