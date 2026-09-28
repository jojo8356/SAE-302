<?php

declare(strict_types=1);

/**
 * MiniShop — authentification & sessions durcies (§8.4, SEC-04/05, RB-12/13).
 *
 * Deux espaces STRICTEMENT séparés (RB-13) :
 *   - client   : $_SESSION['client_id']  (front-office, espace client)
 *   - admin    : $_SESSION['admin_id']   (back-office, rôle SUPER|GESTIONNAIRE)
 * Un client ne voit jamais le back-office ; un admin ne gère jamais un compte
 * client. Les deux sessions peuvent coexister dans le même navigateur.
 *
 * Durcissements (SEC-04) : cookie HttpOnly + SameSite=Lax, régénération de
 * l'identifiant de session à chaque élévation de privilège (anti-fixation,
 * test S-04), messages d'erreur volontairement identiques pour « email
 * inconnu » et « mot de passe erroné » (SEC-05).
 *
 * NOTE RUNTIME DE DÉVELOPPEMENT — le bac à sable exécute l'application sous
 * PHP-WASM, dont le module de session est défaillant : il verrouille le
 * PREMIER identifiant de session du processus et l'ignore ensuite (tous les
 * visiteurs partageraient une même session). Sur ce SAPI uniquement
 * (PHP_SAPI === 'wasm'), demarrer() bascule sur un pool de sessions géré en
 * mémoire du processus, indexé par le cookie — comportement strictement
 * équivalent pour l'application. Sur tout serveur PHP normal (FPM, Apache,
 * php -S, CLI), c'est l'API session native qui est utilisée.
 */

namespace App\Security;

final class Auth
{
    // -------------------------------------------------- bac à sable WASM
    /** Dossier du pool de sessions (SAPI wasm uniquement). */
    private const WASM_SESS_DIR = '/tmp/minishop_sess';

    private static ?string $wasmSid = null;

    private static ?string $derniereRequete = null;

    public static function demarrer(): void
    {
        if (PHP_SAPI === 'wasm') {
            self::demarrerWasm();

            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? 'off') !== 'off'),
        ]);
        session_name('MINISHOPSESS');
        ini_set('session.use_strict_mode', '1'); // identifiant inconnu ⇒ nouvelle session
        session_start();
    }

    /**
     * Sessions maison du bac à sable (cf. note d'en-tête de classe) : un
     * fichier par session dans le système de fichiers du runtime WASM (seul
     * espace qui persiste d'une requête à l'autre — les statiques PHP y sont
     * réinitialisés entre requêtes).
     *
     * Le runtime n'expose pas non plus $_COOKIE (l'en-tête « cookie » est
     * avalé par son SAPI) : le serveur de développement le transporte sous
     * « x-ms-cookie » et on l'analyse ici.
     */
    private static function demarrerWasm(): void
    {
        $cookiesRecus = self::cookiesWasmRecus();
        $identite = ($_SERVER['REQUEST_METHOD'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '')
            . '|' . ($cookiesRecus['MINISHOPSESS'] ?? '')
            . '|' . ($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
        if (self::$derniereRequete === $identite) {
            return; // déjà démarrée pour cette requête
        }
        self::$derniereRequete = $identite;

        $sid = (string) ($cookiesRecus['MINISHOPSESS'] ?? '');
        $fichier = self::WASM_SESS_DIR . '/' . $sid . '.json';
        if (preg_match('#^[A-Za-z0-9,-]{20,128}$#', $sid) === 1 && is_file($fichier)) {
            $_SESSION = (array) json_decode((string) file_get_contents($fichier), true); // session connue
        } else {
            $sid = bin2hex(random_bytes(20)); // session neuve (cookie inconnu ⇒ anti-réutilisation)
            self::cookieWasm($sid);
            $_SESSION = [];
        }
        self::$wasmSid = $sid;

        // persister l'état final de $_SESSION à la fin de CETTE requête
        register_shutdown_function(static function (): void {
            if (self::$wasmSid !== null) {
                @mkdir(self::WASM_SESS_DIR, 0777, true);
                file_put_contents(self::WASM_SESS_DIR . '/' . self::$wasmSid . '.json', json_encode($_SESSION ?? [], JSON_UNESCAPED_UNICODE));
            }
        });
    }

    /**
     * Cookies du navigateur (transport dev WASM).
     *
     * On lit EXCLUSIVEMENT « x-ms-cookie » : le SAPI du runtime joue au
     * navigateur avec un bocal unique (les Set-Cookie émis par une réponse
     * sont réinjectés dans HTTP_COOKIE de TOUTES les requêtes suivantes, y
     * compris celles d'autres visiteurs) — son HTTP_COOKIE n'est donc pas
     * fiable pour distinguer deux visiteurs.
     *
     * @return array<string, string>
     */
    private static function cookiesWasmRecus(): array
    {
        $brut = (string) ($_SERVER['HTTP_X_MS_COOKIE'] ?? '');
        $cookies = [];
        foreach (explode(';', $brut) as $paire) {
            $paire = trim((string) $paire);
            if ($paire === '' || !str_contains($paire, '=')) {
                continue;
            }
            [$cle, $valeur] = explode('=', $paire, 2);
            $cookies[urldecode($cle)] = urldecode($valeur);
        }

        return $cookies;
    }

    private static function cookieWasm(string $sid): void
    {
        setcookie('MINISHOPSESS', $sid, ['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    }

    /** Régénère l'identifiant de session (anti-fixation, SEC-04) — les deux runtimes. */
    private static function regenererId(): void
    {
        if (PHP_SAPI === 'wasm') {
            if (self::$wasmSid !== null) {
                @unlink(self::WASM_SESS_DIR . '/' . self::$wasmSid . '.json'); // l'ancien identifiant devient inutilisable
            }
            $nouveau = bin2hex(random_bytes(20));
            self::$wasmSid = $nouveau;
            self::cookieWasm($nouveau);

            return;
        }
        session_regenerate_id(true);
    }

    // ------------------------------------------------------------- client

    /** @return array{id: int, nom: string, prenom: string, email: string}|null */
    public static function client(): ?array
    {
        $id = $_SESSION['client_id'] ?? null;
        if ($id === null) {
            return null;
        }
        $client = \App\Config\Database::store()->find('client', (int) $id);

        if ($client === null || !$client['actif']) {
            return null;
        }

        return [
            'id' => (int) $client['id_client'],
            'nom' => (string) $client['nom'],
            'prenom' => (string) $client['prenom'],
            'email' => (string) $client['email'],
        ];
    }

    /** Connecte un client (après password_verify côté contrôleur). */
    public static function connecterClient(int $idClient): void
    {
        self::regenererId(); // anti-fixation (SEC-04, S-04)
        $_SESSION['client_id'] = $idClient;
    }

    public static function deconnecterClient(): void
    {
        unset($_SESSION['client_id']);
        self::regenererId();
    }

    /** Garde de route : exiger un client connecté (redirige vers /connexion). */
    public static function exigeClient(): array
    {
        $client = self::client();
        if ($client === null) {
            $_SESSION['flash_erreur'] = 'Connectez-vous pour accéder à votre espace client.';
            self::rediriger('/connexion');
        }

        return $client;
    }

    // -------------------------------------------------------------- admin

    /** @return array{id: int, nom: string, prenom: string, email: string, role: string}|null */
    public static function admin(): ?array
    {
        $id = $_SESSION['admin_id'] ?? null;
        if ($id === null) {
            return null;
        }
        $admin = \App\Config\Database::store()->find('administrateur', (int) $id);

        if ($admin === null || !$admin['actif']) {
            return null;
        }

        return [
            'id' => (int) $admin['id_admin'],
            'nom' => (string) $admin['nom'],
            'prenom' => (string) $admin['prenom'],
            'email' => (string) $admin['email'],
            'role' => (string) $admin['role'],
        ];
    }

    public static function connecterAdmin(int $idAdmin): void
    {
        self::regenererId();
        $_SESSION['admin_id'] = $idAdmin;
    }

    public static function deconnecterAdmin(): void
    {
        unset($_SESSION['admin_id']);
        self::regenererId();
    }

    /** Garde de route : exiger un administrateur connecté (RB-13). */
    public static function exigeAdmin(): array
    {
        $admin = self::admin();
        if ($admin === null) {
            self::rediriger('/admin/connexion');
        }

        return $admin;
    }

    /** Garde de route : exiger le rôle SUPER (gestion des comptes admin, RB-13). */
    public static function exigeSuper(): array
    {
        $admin = self::exigeAdmin();
        if ($admin['role'] !== 'SUPER') {
            \http_response_code(403);
            $_SESSION['flash_erreur'] = 'Réservé au rôle SUPER.';
            self::rediriger('/admin');
        }

        return $admin;
    }

    /** Vérifie les identifiants admin (comparaison à temps constant, RB-12). */
    public static function verifierAdmin(string $email, string $motDePasse): ?array
    {
        $store = \App\Config\Database::store();
        $admin = $store->findOne('administrateur', [\App\Model\Data\Filter::eq('email', strtolower(trim($email)))]);
        if ($admin === null || !$admin['actif'] || !password_verify($motDePasse, (string) $admin['mot_de_passe_hash'])) {
            return null; // message identique côté contrôleur (SEC-05)
        }

        return $admin;
    }

    // ------------------------------------------------------------ divers

    public static function rediriger(string $chemin): never
    {
        header('Location: ' . $chemin, true, 302);
        exit;
    }
}
