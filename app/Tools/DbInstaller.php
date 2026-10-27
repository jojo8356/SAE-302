<?php

declare(strict_types=1);

/**
 * MiniShop — installation locale de la base MySQL/MariaDB.
 *
 * Regroupe en une seule classe ce qu'un développeur doit faire à la main :
 * créer la base si elle manque, y charger `sql/01 → 04` (schéma, 17 procédures,
 * 16 déclencheurs, jeu de démonstration), puis **vérifier** que les objets
 * attendus existent réellement. Utilisé par `scripts/db_init.php` et
 * `scripts/load_db.php` (qui reste l'enveloppe historique).
 *
 * La configuration vient de `Database::config()` — donc du fichier `.env`
 * (modèle `.env.example`) :
 *
 *   DB_HOST="127.0.0.1"
 *   DB_PORT="3306"
 *   DB_NAME="minishop"
 *   DB_USER="minishop"
 *   DB_PASSWORD="…"
 *   STORAGE_DRIVER="sql"
 *
 * Aucune valeur n'est concaténée dans le SQL (SEC-01) ; le nom de la base est
 * validé par une liste blanche (`[A-Za-z0-9_]+`) et n'est jamais utilisé comme
 * paramètre lié (MySQL refuse les marqueurs sur les identifiants).
 */

namespace App\Tools;

use App\Config\Database;
use PDO;
use PDOException;
use RuntimeException;

final class DbInstaller
{
    /** Objets attendus après chargement de `sql/01 → 03` (CDC §16.2). */
    public const TABLES_ATTENDUES = 8;
    public const VUES_ATTENDUES = 3;
    public const PROCEDURES_ATTENDUES = 17;
    public const FONCTIONS_ATTENDUES = 1;

    /** Minimum de déclencheurs : le compte exact dépend des versions (16 attendus). */
    public const TRIGGERS_ATTENDUS = 5;

    /** Volumétrie du jeu de démonstration (`sql/04_minishop_demo.sql`). */
    public const PRODUITS_DEMO = 12;
    public const CLIENTS_DEMO = 3;

    public function __construct(
        private readonly string $hote,
        private readonly string $port,
        private readonly string $base,
        private readonly string $utilisateur,
        private readonly string $motDePasse,
    ) {
    }

    /** Construit l'installateur depuis `.env` / l'environnement. */
    public static function depuisConfiguration(): self
    {
        $config = Database::config();

        return new self(
            (string) ($config['db_host'] ?? '127.0.0.1'),
            (string) ($config['db_port'] ?? '3306'),
            (string) ($config['db_name'] ?? 'minishop'),
            (string) ($config['db_user'] ?? 'root'),
            (string) ($config['db_pass'] ?? ''),
        );
    }

    /** Depuis des variables d'environnement explicites (compatibilité load_db.php). */
    public static function depuisVariables(): self
    {
        $hote = getenv('DB_HOST');
        $port = getenv('DB_PORT');
        $base = getenv('DB_NAME');
        $utilisateur = getenv('DB_USER');
        $motDePasse = getenv('DB_PASS');

        if ($base === false || $base === '') {
            $base = getenv('DB');
        }
        if ($hote === false || $hote === '') {
            $hote = '127.0.0.1';
        }
        if ($port === false || $port === '') {
            $port = '3306';
        }
        if ($utilisateur === false || $utilisateur === '') {
            $utilisateur = 'root';
        }
        if ($motDePasse === false) {
            $motDePasse = '';
        }
        if ($base === false || $base === '') {
            $base = 'minishop';
        }

        return new self((string) $hote, (string) $port, (string) $base, (string) $utilisateur, (string) $motDePasse);
    }

    public function base(): string
    {
        return $this->base;
    }

    public function hote(): string
    {
        return $this->hote;
    }

    public function port(): string
    {
        return $this->port;
    }

    public function utilisateur(): string
    {
        return $this->utilisateur;
    }

    /** Cible lisible : `minishop@127.0.0.1:3306`. */
    public function cible(): string
    {
        return sprintf('%s@%s:%s', $this->base, $this->hote, $this->port);
    }

    /** Le nom de base respecte-t-il la liste blanche ? */
    public function baseValide(): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $this->base) === 1;
    }

    /**
     * Connexion PDO. `$avecBase = false` pour l'administration du serveur
     * (création / suppression de la base).
     */
    public function connexion(bool $avecBase = true): PDO
    {
        if (!$this->baseValide()) {
            throw new RuntimeException(
                'Nom de base invalide : « ' . $this->base . ' » (lettres, chiffres et _ uniquement).',
            );
        }

        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $this->hote, $this->port);
        if ($avecBase) {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $this->hote, $this->port, $this->base);
        }

        try {
            return new PDO($dsn, $this->utilisateur, $this->motDePasse, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $erreur) {
            throw new RuntimeException(self::expliquer($erreur, $this->hote, $this->port), 0, $erreur);
        }
    }

    /** Message d'aide à partir du code d'erreur MySQL. */
    public static function expliquer(PDOException $erreur, string $hote, string $port): string
    {
        $code = (int) ($erreur->errorInfo[1] ?? 0);
        $message = $erreur->getMessage();

        if ($code === 1045) {
            return "Identifiants refusés pour $hote:$port (mot de passe ou utilisateur incorrect). "
                . 'Vérifiez DB_USER / DB_PASSWORD dans .env.';
        }
        if ($code === 1049) {
            return "La base demandée n'existe pas encore : lancez « php scripts/db_init.php ».";
        }
        $injoignable = in_array($code, [2002, 2006, 2013], true)
            || str_contains($message, 'Connection refused')
            || str_contains($message, 'gone away');
        if ($injoignable) {
            return "Aucun serveur MySQL n'écoute sur $hote:$port. Démarrez-le :\n"
                . "    Linux  : sudo systemctl start mysql   (ou mariadb)\n"
                . "    macOS  : brew services start mysql\n"
                . "    Windows: net start MySQL80            (services.msc)";
        }

        return "Connexion MySQL impossible sur $hote:$port : " . $message;
    }

    /** La base existe-t-elle sur le serveur ? */
    public function baseExiste(): bool
    {
        $pdo = $this->connexion(false);
        $requete = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $requete->execute([$this->base]);

        return (int) $requete->fetchColumn() > 0;
    }

    /** Crée la base en utf8mb4 (accents et emoji sûrs). */
    public function creerBase(): void
    {
        $pdo = $this->connexion(false);
        $pdo->exec(
            'CREATE DATABASE IF NOT EXISTS `' . $this->base . '` '
            . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        );
    }

    /** Supprime la base (mode `--reset`, irréversible). */
    public function supprimerBase(): void
    {
        $pdo = $this->connexion(false);
        $pdo->exec('DROP DATABASE IF EXISTS `' . $this->base . '`');
    }

    /** Le serveur répond-il ? (diagnostic, sans échouer) */
    public function serveurJoignable(): bool
    {
        try {
            $this->connexion(false);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Charge les scripts SQL dans l'ordre.
     *
     * @param list<string> $fichiers chemins absolus
     * @param callable(string,int):void|null $journal reçoit (fichier, nb instructions)
     * @return array<string,int> fichier => instructions exécutées
     */
    public function charger(array $fichiers, ?callable $journal = null): array
    {
        $pdo = $this->connexion(true);
        $chargeur = new SqlLoader($pdo);

        $resultats = [];
        foreach ($fichiers as $fichier) {
            if (!is_file($fichier)) {
                throw new RuntimeException('script SQL introuvable : ' . $fichier);
            }
            $nombre = $chargeur->chargerFichier($fichier, true);
            $resultats[basename($fichier)] = $nombre;
            if ($journal !== null) {
                $journal(basename($fichier), $nombre);
            }
        }

        return $resultats;
    }

    /** Chemin des scripts dans l'ordre de chargement. */
    public static function scripts(bool $avecDemo = true): array
    {
        $racine = dirname(__DIR__, 2) . '/sql';
        $fichiers = [
            $racine . '/01_minishop_schema.sql',
            $racine . '/02_minishop_procedures.sql',
            $racine . '/03_minishop_triggers.sql',
        ];
        if ($avecDemo) {
            $fichiers[] = $racine . '/04_minishop_demo.sql';
        }

        return $fichiers;
    }

    /**
     * Inventaire de la base (compte réel des objets et des lignes).
     *
     * @return array<string,int> `-1` = information indisponible
     */
    public function inventaire(): array
    {
        $inventaire = [
            'tables' => 0,
            'vues' => 0,
            'procedures' => 0,
            'fonctions' => 0,
            'triggers' => 0,
            'produits' => 0,
            'clients' => 0,
            'commandes' => 0,
        ];

        try {
            $pdo = $this->connexion(true);
            $objets = (new SqlLoader($pdo))->compteObjets();
            $inventaire['tables'] = (int) $objets['tables'];
            $inventaire['vues'] = (int) $objets['vues'];
            $inventaire['procedures'] = (int) $objets['procedures'];
            $inventaire['fonctions'] = (int) $objets['fonctions'];
            $inventaire['triggers'] = (int) $objets['triggers'];

            foreach (['produits' => 'produit', 'clients' => 'client', 'commandes' => 'commande'] as $cle => $table) {
                $requete = $pdo->query('SELECT COUNT(*) FROM `' . $table . '`');
                if ($requete !== false) {
                    $inventaire[$cle] = (int) $requete->fetchColumn();
                }
            }
        } catch (RuntimeException | PDOException) {
            foreach (array_keys($inventaire) as $cle) {
                $inventaire[$cle] = -1;
            }
        }

        return $inventaire;
    }

    /** Version annoncée par le serveur (`10.11.6-MariaDB`, `8.0.36`, …). */
    public function versionServeur(): string
    {
        try {
            $pdo = $this->connexion(true);
            $valeur = $pdo->query('SELECT VERSION()')->fetchColumn();
            if (!is_string($valeur)) {
                return '';
            }

            return trim($valeur);
        } catch (RuntimeException | PDOException) {
            return '';
        }
    }

    /**
     * Le serveur applique-t-il réellement les contraintes `CHECK` ?
     *
     * MySQL ne les applique qu'à partir de 8.0.16 (avant, elles sont parsées
     * puis ignorées : RB-02/RB-03 passeraient silencieusement) ; MariaDB les
     * applique depuis 10.2, mais le projet exige 10.6+ pour les autres objets.
     */
    public static function versionCompatible(string $version): bool
    {
        $version = trim($version);
        if ($version === '') {
            return false;
        }

        if (preg_match('/(\d+)\.(\d+)\.(\d+)/', $version, $morceaux) !== 1) {
            return false;
        }

        $normalisee = $morceaux[1] . '.' . $morceaux[2] . '.' . $morceaux[3];
        $estMariaDb = stripos($version, 'mariadb') !== false;
        if ($estMariaDb) {
            return version_compare($normalisee, '10.6.0', '>=');
        }

        return version_compare($normalisee, '8.0.16', '>=');
    }

    /** Jeu de caractères de la connexion (doit être utf8mb4). */
    public function jeuCaracteres(): string
    {
        try {
            $pdo = $this->connexion(true);
            $valeur = $pdo->query('SELECT @@character_set_connection')->fetchColumn();
            if (!is_string($valeur)) {
                return '';
            }

            return $valeur;
        } catch (RuntimeException | PDOException) {
            return '';
        }
    }

    /**
     * Contrôles de conformité après chargement.
     *
     * @param array<string,int> $inventaire résultat de inventaire()
     * @return list<array{controle:string, ok:bool, detail:string}>
     */
    public function verifier(array $inventaire, bool $avecDemo = true): array
    {
        $controles = [];

        $accessible = $inventaire['tables'] >= 0;
        $detailBase = 'inaccessible';
        if ($accessible) {
            $detailBase = 'accessible';
        }
        $controles[] = [
            'controle' => 'base « ' . $this->base . ' »',
            'ok' => $accessible,
            'detail' => $detailBase,
        ];

        $version = $this->versionServeur();
        $detailVersion = $version;
        if ($version === '') {
            $detailVersion = 'indisponible';
        }
        $controles[] = [
            'controle' => 'version du serveur',
            'ok' => self::versionCompatible($version),
            'detail' => $detailVersion . ' (MySQL ≥ 8.0.16 ou MariaDB ≥ 10.6)',
        ];

        $attendu = [
            'tables' => self::TABLES_ATTENDUES,
            'vues' => self::VUES_ATTENDUES,
            'procedures' => self::PROCEDURES_ATTENDUES,
            'fonctions' => self::FONCTIONS_ATTENDUES,
            'triggers' => self::TRIGGERS_ATTENDUS,
        ];

        foreach ($attendu as $cle => $minimum) {
            $valeur = $inventaire[$cle] ?? -1;
            $controles[] = [
                'controle' => $cle,
                'ok' => $valeur >= $minimum,
                'detail' => $valeur . ' (attendu ≥ ' . $minimum . ')',
            ];
        }

        $caracteres = $this->jeuCaracteres();
        $detailCaracteres = $caracteres;
        if ($caracteres === '') {
            $detailCaracteres = 'indisponible';
        }
        $controles[] = [
            'controle' => 'jeu de caractères',
            'ok' => $caracteres === 'utf8mb4',
            'detail' => $detailCaracteres,
        ];

        if ($avecDemo) {
            $controles[] = [
                'controle' => 'produits de démonstration',
                'ok' => ($inventaire['produits'] ?? 0) >= self::PRODUITS_DEMO,
                'detail' => ($inventaire['produits'] ?? -1) . ' (attendu ≥ ' . self::PRODUITS_DEMO . ')',
            ];
            $controles[] = [
                'controle' => 'clients de démonstration',
                'ok' => ($inventaire['clients'] ?? 0) >= self::CLIENTS_DEMO,
                'detail' => ($inventaire['clients'] ?? -1) . ' (attendu ≥ ' . self::CLIENTS_DEMO . ')',
            ];
        }

        return $controles;
    }

    /**
     * Vérification complète : la base est-elle utilisable par l'application ?
     *
     * @return list<array{controle:string, ok:bool, detail:string}>
     */
    public function diagnostiquer(bool $avecBase = true): array
    {
        $controles = [];
        $joignable = $this->serveurJoignable();
        $detailServeur = 'injoignable';
        if ($joignable) {
            $detailServeur = $this->hote . ':' . $this->port;
        }
        $controles[] = [
            'controle' => 'serveur MySQL',
            'ok' => $joignable,
            'detail' => $detailServeur,
        ];

        if (!$joignable) {
            return $controles;
        }

        $version = $this->versionServeur();
        if ($version !== '') {
            $controles[] = [
                'controle' => 'version du serveur',
                'ok' => self::versionCompatible($version),
                'detail' => $version . ' (MySQL ≥ 8.0.16 ou MariaDB ≥ 10.6)',
            ];
        }

        $existe = $this->baseExiste();
        $detailBase = 'absente — lancer php scripts/db_init.php';
        if ($existe) {
            $detailBase = 'présente';
        }
        $controles[] = [
            'controle' => 'base « ' . $this->base . ' »',
            'ok' => $existe,
            'detail' => $detailBase,
        ];

        if (!$existe || !$avecBase) {
            return $controles;
        }

        return array_merge($controles, $this->verifier($this->inventaire()));
    }
}
