<?php

declare(strict_types=1);

/**
 * MiniShop — création du compte MySQL applicatif (outil de développement).
 *
 * Pourquoi cette classe existe : sous Debian/Ubuntu, `mariadb-server` crée
 * `root@localhost` avec le greffon `unix_socket`. Ce greffon ne compare PAS un
 * mot de passe : il autorise la connexion quand le **processus qui se connecte
 * tourne sous le même utilisateur du système** que le compte. Conséquences :
 *
 *   sudo mariadb        ✅ (le shell est root)
 *   php scripts/…       ❌ (PHP tourne sous « jojokes ») → [1698] Access denied
 *
 * Aucun repli côté PHP ne peut contourner cela — c'est une décision du SGBD.
 * La seule solution propre est donc un compte dédié, créé une fois, par un
 * `sudo mariadb` ponctuel. C'est exactement ce que fait cette classe :
 *
 *   1. elle génère un mot de passe aléatoire (hexadécimal : rien à échapper) ;
 *   2. elle écrit les CREATE USER / GRANT dans un fichier temporaire (0600) ;
 *   3. elle l'exécute avec `sudo mariadb < fichier` ;
 *   4. `scripts/db_init.php --compte` reporte les identifiants dans `.env`.
 *
 * Le compte est restreint à la base du projet (`GRANT … ON base.*`), jamais
 * global. Deux hôtes sont créés (`localhost` et `127.0.0.1`) car MariaDB les
 * distingue : PHP en TCP arrive en `127.0.0.1`, mais `localhost` reste utile
 * pour les outils en socket.
 */

namespace App\Tools;

use App\Config\Database;

final class DbProvision
{
    /** Hôtes créés : MariaDB traite `localhost` (socket) et `127.0.0.1` (TCP) séparément. */
    public const HOTES = ['localhost', '127.0.0.1'];

    public function __construct(
        private readonly string $base,
        private readonly string $utilisateur,
    ) {
    }

    /** Depuis `.env` / l'environnement. */
    public static function depuisConfiguration(): self
    {
        $config = Database::config();
        $utilisateur = (string) ($config['db_user'] ?? 'minishop');
        // Un compte nommé « root » ne doit jamais être renforcé à l'aveugle :
        // on retombe sur le compte applicatif standard.
        if (strtolower($utilisateur) === 'root' || $utilisateur === '') {
            $utilisateur = 'minishop';
        }

        return new self((string) ($config['db_name'] ?? 'minishop'), $utilisateur);
    }

    /**
     * Mot de passe aléatoire en hexadécimal.
     *
     * Aucun caractère à échapper (`'`, `\`) : la valeur peut être insérée dans
     * une chaîne SQL ou dans `.env` sans transformation, donc sans risque de
     * corruption ni d'injection.
     */
    public static function motDePasse(int $octets = 16): string
    {
        return bin2hex(random_bytes($octets));
    }

    public function base(): string
    {
        return $this->base;
    }

    public function utilisateur(): string
    {
        return $this->utilisateur;
    }

    /** Le nom de base / d'utilisateur ne contient que des caractères sûrs. */
    public function valide(): bool
    {
        return preg_match('/^[A-Za-z0-9_]{1,32}$/', $this->base) === 1
            && preg_match('/^[A-Za-z0-9_]{1,32}$/', $this->utilisateur) === 1;
    }

    /**
     * Instructions SQL de création du compte.
     *
     * @return list<string>
     */
    public function instructions(string $motDePasse): array
    {
        $instructions = [
            'CREATE DATABASE IF NOT EXISTS `' . $this->base . '` '
                . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        ];

        foreach (self::HOTES as $hote) {
            $compte = "'" . $this->utilisateur . "'@'" . $hote . "'";
            $instructions[] = 'CREATE USER IF NOT EXISTS ' . $compte
                . " IDENTIFIED BY '" . $motDePasse . "'";
            // Un compte préexistant peut avoir un autre mot de passe : on le
            // réaligne au lieu d'échouer.
            $instructions[] = 'ALTER USER ' . $compte
                . " IDENTIFIED BY '" . $motDePasse . "'";
            $instructions[] = 'GRANT ALL PRIVILEGES ON `' . $this->base . '`.* TO ' . $compte;
        }

        $instructions[] = 'FLUSH PRIVILEGES';

        return $instructions;
    }

    /**
     * Crée le compte en passant par `sudo mariadb`.
     *
     * @return array{ok:bool, sortie:string, methode:string}
     */
    public function creerCompte(string $motDePasse): array
    {
        if (!$this->valide()) {
            return [
                'ok' => false,
                'sortie' => 'nom de base ou d’utilisateur invalide (lettres, chiffres et _ uniquement)',
                'methode' => 'aucune',
            ];
        }

        return $this->executerEnRoot($this->instructions($motDePasse));
    }

    /**
     * Exécute des instructions SQL en root via `sudo <client> < fichier`.
     *
     * Le SQL passe par un fichier (0600) et non par stdin : stdin reste libre
     * pour la demande de mot de passe sudo, et une instruction contenant des
     * guillemets n'a rien à échapper dans une ligne de commande.
     *
     * @param list<string> $instructions
     * @return array{ok:bool, sortie:string, methode:string}
     */
    private function executerEnRoot(array $instructions): array
    {
        $client = self::clientSql();
        if ($client === null) {
            return [
                'ok' => false,
                'sortie' => "client « mariadb » (ou « mysql ») introuvable dans le PATH",
                'methode' => 'aucune',
            ];
        }

        $fichier = tempnam(sys_get_temp_dir(), 'minishop-compte-');
        if ($fichier === false) {
            return [
                'ok' => false,
                'sortie' => 'fichier temporaire impossible à créer',
                'methode' => 'aucune',
            ];
        }

        @chmod($fichier, 0600);
        file_put_contents($fichier, implode(";\n", $instructions) . ";\n");

        // Le SQL est passé par un fichier : stdin reste libre pour la demande
        // de mot de passe sudo (elle se fait sur /dev/tty de toute façon).
        $commande = 'sudo ' . $client . ' < ' . escapeshellarg($fichier) . ' 2>&1';
        $lignes = [];
        $code = 0;
        // `@` : sous PHP-WASM (sandbox), exec() ne peut pas créer de processus ;
        // l'échec est déjà traité par le code retour et le message qui suit.
        @exec($commande, $lignes, $code);
        @unlink($fichier);

        return [
            'ok' => $code === 0,
            'sortie' => trim(implode("\n", $lignes)),
            'methode' => 'sudo ' . $client,
        ];
    }

    /**
     * Instructions pour une base ANNEXE (tests d'intégration : `minishop_test`).
     *
     * Elles créent la base et accordent les droits au compte applicatif, sans
     * jamais toucher à son mot de passe ni élargir ses droits sur la base de
     * production. Le moindre privilège reste la règle : `minishop` n'a des
     * droits que sur `minishop` et sur la base de test explicitement nommée.
     *
     * @return list<string>
     */
    public function instructionsBaseAnnexe(string $baseAnnexe): array
    {
        $instructions = [
            'CREATE DATABASE IF NOT EXISTS `' . $baseAnnexe . '` '
                . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        ];
        foreach (self::HOTES as $hote) {
            $compte = "'" . $this->utilisateur . "'@'" . $hote . "'";
            $instructions[] = 'GRANT ALL PRIVILEGES ON `' . $baseAnnexe . '`.* TO ' . $compte;
        }
        $instructions[] = 'FLUSH PRIVILEGES';

        return $instructions;
    }

    /** Le nom d'une base annexe est sûr (pas d'injection possible). */
    public static function baseValide(string $nom): bool
    {
        return preg_match('/^[A-Za-z0-9_]{1,32}$/', $nom) === 1;
    }

    /**
     * Crée une base annexe et ses droits, en passant par `sudo mariadb`.
     *
     * @return array{ok:bool, sortie:string, methode:string}
     */
    public function ajouterBaseAnnexe(string $baseAnnexe): array
    {
        if (!self::baseValide($baseAnnexe)) {
            return [
                'ok' => false,
                'sortie' => 'nom de base invalide (lettres, chiffres et _ uniquement)',
                'methode' => 'aucune',
            ];
        }

        return $this->executerEnRoot($this->instructionsBaseAnnexe($baseAnnexe));
    }

    /** Commande SQL exécutable en root, prête à copier-coller. */
    public function script(string $motDePasse): string
    {
        return implode(";\n", $this->instructions($motDePasse)) . ";\n";
    }

    /** Client en ligne de commande disponible (`mariadb`, `mysql`, ou `null`). */
    public static function clientSql(): ?string
    {
        foreach (['mariadb', 'mysql'] as $client) {
            $lignes = [];
            $code = 1;
            @exec('command -v ' . $client . ' 2>/dev/null', $lignes, $code);
            if ($code === 0 && $lignes !== []) {
                return $client;
            }
        }

        return null;
    }

    /** `sudo` est-il installé ? */
    public static function sudoDisponible(): bool
    {
        $lignes = [];
        $code = 1;
        @exec('command -v sudo 2>/dev/null', $lignes, $code);

        return $code === 0 && $lignes !== [];
    }
}
