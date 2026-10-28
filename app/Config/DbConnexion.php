<?php

declare(strict_types=1);

/**
 * MiniShop — ouverture d'une connexion PDO vers MySQL / MariaDB.
 *
 * Point d'entrée UNIQUE : `SqlStore` (pilote applicatif) et `DbInstaller`
 * (création de la base) passent tous les deux par ici, ce qui supprime le
 * DSN dupliqué qui vivait dans chaque classe.
 *
 * Le point délicat est l'authentification de `root` sous Debian/Ubuntu :
 * MariaDB et MySQL y créent `root@localhost` avec le greffon `unix_socket`,
 * qui **n'accepte pas** de connexion TCP. PHP, lui, se connecte en TCP
 * (`127.0.0.1:3306`), et le serveur répond alors :
 *
 *   SQLSTATE[HY000] [1698] Access denied for user 'root'@'localhost'
 *
 * `ouvrir()` traite ce cas précis : si le code d'erreur est 1698 (ou 1045 sur
 * un compte `root` sans mot de passe) et qu'une socket Unix locale existe, la
 * connexion est **réessayée par la socket** — sans rien changer au `.env`.
 * Le répertoire de socket peut aussi être imposé explicitement :
 *
 *   DB_SOCKET=/run/mysqld/mysqld.sock
 *
 * C'est le comportement attendu d'un outil de développement : le cas le plus
 * fréquent (poste Debian fraîchement installé) fonctionne sans création de
 * compte applicatif, et `DB_SOCKET` reste la porte de sortie explicite.
 */

namespace App\Config;

use PDO;
use PDOException;

final class DbConnexion
{
    /** Codes d'erreur signalant un compte qui refuse l'authentification TCP. */
    private const CODES_AUTH_SOCKET = [1698, 1045];

    /** Chemins de socket Unix rencontrés sur les installations locales. */
    public const SOCKETS_CONNUS = [
        '/run/mysqld/mysqld.sock',        // Debian / Ubuntu (paquet mariadb-server)
        '/var/run/mysqld/mysqld.sock',    // idem, ancien chemin
        '/tmp/mysql.sock',               // MySQL générique, MAMP
        '/var/lib/mysql/mysql.sock',      // RHEL / Fedora
        '/opt/homebrew/var/mysql/mysql.sock',  // Homebrew Apple Silicon
        '/usr/local/var/mysql/mysql.sock',     // Homebrew Intel, macOS
    ];

    /** Socket réellement utilisée par la dernière connexion réussie. */
    private static ?string $socketUtilisee = null;

    /** DSN effectivement retenu par la dernière connexion réussie. */
    private static ?string $dsnUtilise = null;

    /**
     * Collation de session imposée à chaque connexion.
     *
     * `sql/01`…`sql/03` créent tables, vues et routines sous
     * `SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci`. Si la connexion garde le
     * défaut du serveur, sur MariaDB ≥ 11.4 (`utf8mb4_uca1400_ai_ci`), comparer
     * un littéral ou un paramètre à une colonne de VUE lève :
     *
     *     ERROR 1267 Illegal mix of collations (utf8mb4_unicode_ci,COERCIBLE)
     *     and (utf8mb4_uca1400_ai_ci,COERCIBLE) for operation '='
     *
     * Une colonne de table a une collation « implicite », qui gagne contre le
     * littéral ; une colonne de vue n'en a pas — les deux opérandes sont alors
     * coercibles et MariaDB refuse de choisir. Aligner la session sur le schéma
     * supprime la classe entière de défaut, au lieu de corriger un cas.
     */
    public const COLLATION = 'utf8mb4_unicode_ci';

    /** Options communes à toutes les connexions applicatives. */
    public static function options(): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // La constante n'existe que si pdo_mysql est chargée (le repli JSON
        // fonctionne sans elle).
        if (defined('Pdo\\Mysql::ATTR_INIT_COMMAND')) {
            $options[constant('Pdo\\Mysql::ATTR_INIT_COMMAND')] = 'SET NAMES utf8mb4 COLLATE ' . self::COLLATION;
        } elseif (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            // PHP 8.2 et 8.3 : l'ancienne constante n'y est pas dépréciée.
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE ' . self::COLLATION;
        }

        return $options;
    }

    /**
     * Ouvre une connexion : `$base = null` pour l'administration du serveur
     * (création / suppression de la base, `information_schema`).
     *
     * @throws PDOException la dernière erreur rencontrée (message exploitable
     *                      par `expliquer()`)
     */
    public static function ouvrir(?string $base = null, ?array $config = null): PDO
    {
        $config ??= Database::config();
        $utilisateur = (string) ($config['db_user'] ?? 'root');
        $motDePasse = (string) ($config['db_pass'] ?? '');
        $dsns = self::dsns($base, $config);
        $nombre = count($dsns);
        $derniere = null;

        for ($index = 0; $index < $nombre; ++$index) {
            $dsn = $dsns[$index];
            try {
                $pdo = new PDO($dsn, $utilisateur, $motDePasse, self::options());
                self::$socketUtilisee = self::socketDuDsn($dsn);
                self::$dsnUtilise = $dsn;

                return $pdo;
            } catch (PDOException $erreur) {
                $derniere = $erreur;
                $reste = $index + 1 < $nombre;
                if (!$reste || !self::authParSocket($erreur)) {
                    break;
                }
                // Compte en unix_socket : on retente par la socket locale.
            }
        }

        if ($derniere instanceof PDOException) {
            throw $derniere;
        }

        throw new PDOException('Connexion MySQL impossible : aucun DSN n’a pu être construit.');
    }

    /**
     * DSN à essayer, dans l'ordre : socket imposée, TCP, puis socket locale
     * (repli). Le repli n'est tenté que sur une erreur d'authentification.
     *
     * @return list<string>
     */
    public static function dsns(?string $base = null, ?array $config = null): array
    {
        $config ??= Database::config();
        $dsns = [];

        $imposee = self::socketConfiguree($config);
        if ($imposee !== null) {
            $dsns[] = self::dsnSocket($imposee, $base);
        }

        $dsns[] = self::dsnTcp($config, $base);

        $locale = self::socketLocale($config);
        if ($imposee === null && $locale !== null) {
            $dsns[] = self::dsnSocket($locale, $base);
        }

        return $dsns;
    }

    /** DSN TCP (`mysql:host=…;port=…;dbname=…;charset=utf8mb4`). */
    public static function dsnTcp(array $config, ?string $base = null): string
    {
        $morceaux = [
            'host=' . (string) ($config['db_host'] ?? '127.0.0.1'),
            'port=' . (string) ($config['db_port'] ?? '3306'),
        ];

        return self::composer($morceaux, $base);
    }

    /** DSN par socket Unix (`mysql:unix_socket=…;dbname=…;charset=utf8mb4`). */
    public static function dsnSocket(string $socket, ?string $base = null): string
    {
        return self::composer(['unix_socket=' . $socket], $base);
    }

    /** Socket explicitement demandée par `DB_SOCKET`, si elle existe vraiment. */
    public static function socketConfiguree(?array $config = null): ?string
    {
        $config ??= Database::config();
        $chemin = trim((string) ($config['db_socket'] ?? ''));
        if ($chemin === '' || !file_exists($chemin)) {
            return null;
        }

        return $chemin;
    }

    /** Socket locale détectée (repli `unix_socket`), `null` si serveur distant. */
    public static function socketLocale(?array $config = null): ?string
    {
        $config ??= Database::config();
        if (!self::hoteLocal($config)) {
            return null;
        }

        foreach (self::SOCKETS_CONNUS as $chemin) {
            if (file_exists($chemin)) {
                return $chemin;
            }
        }

        return null;
    }

    /** Le serveur visé tourne-t-il sur cette machine ? */
    public static function hoteLocal(array $config): bool
    {
        $hote = strtolower(trim((string) ($config['db_host'] ?? '127.0.0.1')));

        return in_array($hote, ['', 'localhost', '127.0.0.1', '::1'], true);
    }

    /** Socket utilisée par la dernière connexion réussie (`null` = TCP). */
    public static function socketUtilisee(): ?string
    {
        return self::$socketUtilisee;
    }

    /** DSN retenu par la dernière connexion réussie (diagnostic). */
    public static function dsnUtilise(): ?string
    {
        return self::$dsnUtilise;
    }

    /** Réinitialise la trace (tests, bascule de configuration). */
    public static function reinitialiser(): void
    {
        self::$socketUtilisee = null;
        self::$dsnUtilise = null;
    }

    /**
     * Traduit une erreur PDO en conseil actionnable.
     *
     * `$hote` / `$port` sont fournis par l'appelant car le DSN peut avoir
     * basculé sur une socket entre-temps.
     */
    public static function expliquer(PDOException $erreur, string $hote, string $port): string
    {
        $code = (int) ($erreur->errorInfo[1] ?? 0);
        $message = $erreur->getMessage();
        $paquet = 'php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

        if (str_contains($message, 'could not find driver')) {
            return "L'extension PHP « pdo_mysql » n'est pas chargée : PDO ne sait pas parler à MySQL.\n"
                . '    Debian/Ubuntu : sudo apt install ' . $paquet . "-mysql\n"
                . '    puis vérifier : php -m | grep pdo_mysql';
        }

        if ($code === 1698) {
            // unix_socket compare l'utilisateur du SYSTÈME, pas un mot de passe :
            // aucune socket, aussi bien détectée soit-elle, ne débloque le cas
            // si PHP ne tourne pas sous le même utilisateur que le compte.
            $utilisateur = self::utilisateurCourant();
            $systeme = self::utilisateurSysteme();
            $socket = self::socketLocale();
            $ligneSocket = '';
            if ($socket !== null) {
                $ligneSocket = '    socket détectée : ' . $socket . "\n";
            }

            return "Le compte « " . $utilisateur . " » n'accepte que l'authentification par socket Unix "
                . "(greffon unix_socket).\n"
                . "    Ce greffon compare l'utilisateur du SYSTÈME : ici PHP tourne sous « "
                . $systeme . " », le compte est « " . $utilisateur . " » → refus, quel que soit le mot de passe.\n"
                . $ligneSocket
                . "    Remèdes :\n"
                . "      1. créer le compte applicatif automatiquement : php scripts/db_init.php --compte --yes\n"
                . "         (ou ./run.sh --db, qui le fait tout seul)\n"
                . '      2. ou exécuter la commande en root : sudo php scripts/db_init.php' . "\n"
                . '    Détail : ' . $message;
        }

        if ($code === 1045) {
            return "Identifiants refusés pour $hote:$port (utilisateur ou mot de passe incorrect).\n"
                . '    Vérifiez DB_USER / DB_PASSWORD dans .env ; sous Debian/Ubuntu, « root » '
                . "s'authentifie par socket Unix et ne peut pas servir PHP en TCP.\n"
                . '    Détail : ' . $message;
        }

        if ($code === 1049) {
            return "La base demandée n'existe pas encore : lancez « php scripts/db_init.php ».";
        }

        $injoignable = in_array($code, [2002, 2006, 2013], true)
            || str_contains($message, 'Connection refused')
            || str_contains($message, 'gone away');
        if ($injoignable) {
            return "Aucun serveur MySQL n'écoute sur $hote:$port. Démarrez-le :\n"
                . "    Linux  : sudo systemctl start mariadb   (ou mysql)\n"
                . "    macOS  : brew services start mariadb\n"
                . '    Windows: net start MySQL80            (services.msc)';
        }

        return "Connexion MySQL impossible sur $hote:$port : " . $message;
    }

    /** Utilisateur du SYSTÈME sous lequel tourne PHP (comparé par unix_socket). */
    public static function utilisateurSysteme(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $identite = posix_getpwuid(posix_geteuid());
            if (is_array($identite) && isset($identite['name']) && is_string($identite['name'])) {
                return $identite['name'];
            }
        }

        // `getenv('USER')` évite un fork : sous PHP-WASM (sandbox), exec() ne
        // peut pas créer de processus et émet un avertissement.
        $variable = getenv('USER');
        if (is_string($variable) && $variable !== '') {
            return $variable;
        }

        $lignes = [];
        $code = 1;
        @exec('id -un 2>/dev/null', $lignes, $code);
        if ($code === 0 && $lignes !== []) {
            return trim($lignes[0]);
        }

        return get_current_user();
    }

    /** Utilisateur configuré, pour les messages d'erreur. */
    private static function utilisateurCourant(): string
    {
        $config = Database::config();

        return (string) ($config['db_user'] ?? 'root');
    }

    /** Assemble le DSN final. */
    private static function composer(array $morceaux, ?string $base): string
    {
        if ($base !== null && $base !== '') {
            $morceaux[] = 'dbname=' . $base;
        }
        $morceaux[] = 'charset=utf8mb4';

        return 'mysql:' . implode(';', $morceaux);
    }

    /** L'erreur indique-t-elle un compte restreint à l'authentification socket ? */
    private static function authParSocket(PDOException $erreur): bool
    {
        $code = (int) ($erreur->errorInfo[1] ?? 0);
        if (!in_array($code, self::CODES_AUTH_SOCKET, true)) {
            return false;
        }

        return self::socketLocale() !== null;
    }

    /** Extrait la socket d'un DSN (`null` si le DSN est en TCP). */
    private static function socketDuDsn(string $dsn): ?string
    {
        if (preg_match('/unix_socket=([^;]+)/', $dsn, $trouve) !== 1) {
            return null;
        }

        return $trouve[1];
    }
}
