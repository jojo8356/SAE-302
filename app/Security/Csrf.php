<?php

declare(strict_types=1);

/**
 * MiniShop — jetons CSRF (SEC-05, test S-05).
 *
 * Un jeton par session, comparé en comparaison constante pour TOUTES les
 * requêtes POST (aucune route d'écriture n'y échappe — contrôlé par le
 * contrôleur de base). Le champ caché est généré par Csrf::champ().
 */

namespace App\Security;

final class Csrf
{
    private const CLE = 'csrf_token';

    /** Jeton de la session (généré au besoin). */
    public static function jeton(): string
    {
        Auth::demarrer();
        if (empty($_SESSION[self::CLE])) {
            $_SESSION[self::CLE] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::CLE];
    }

    /** Champ caché à inclure dans chaque formulaire. */
    public static function champ(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::jeton() . '">';
    }

    /** Vérifie le jeton d'une requête POST ; interrompt si invalide. */
    public static function verifier(): void
    {
        Auth::demarrer();
        $recu = (string) ($_POST['_csrf'] ?? '');
        if ($recu === '' || !hash_equals(self::jeton(), $recu)) {
            http_response_code(403);
            $_SESSION['flash_erreur'] = 'Jeton de sécurité invalide ou expiré (CSRF). Rechargez la page et réessayez.';
            Auth::rediriger('/');
        }
    }
}
