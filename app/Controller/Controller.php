<?php

declare(strict_types=1);

/**
 * MiniShop — contrôleur de base (§9.2) : rendu de vues, redirections,
 * messages flash, lecture contrôlée des entrées POST/GET.
 *
 * Les vues sont du HTML sémantique SANS CSS (choix d'architecture du lot
 * courant) ; l'échappement est systématique via App\Security\e().
 */

namespace App\Controller;

use App\Security\Auth;
use App\Security\Csrf;

abstract class Controller
{
    /** Chemin des vues. */
    private const VUES = MINISHOP_ROOT . '/app/View';

    /**
     * Rend une vue dans le gabarit commun.
     *
     * @param array<string, mixed> $donnees variables exposées à la vue
     */
    protected function render(string $vue, array $donnees = [], string $titre = 'MiniShop'): void
    {
        Auth::demarrer();
        extract($donnees, EXTR_SKIP);

        ob_start();
        include self::VUES . '/' . $vue . '.php';
        $contenu = ob_get_clean();

        // variables communes au gabarit
        $client = Auth::client();
        $admin = Auth::admin();
        $titrePage = $titre;
        $flashSucces = $_SESSION['flash_succes'] ?? null;
        $flashErreur = $_SESSION['flash_erreur'] ?? null;
        unset($_SESSION['flash_succes'], $_SESSION['flash_erreur']);

        header('Content-Type: text/html; charset=utf-8');
        include self::VUES . '/layout.php';
    }

    protected function rediriger(string $chemin): never
    {
        Auth::rediriger($chemin);
    }

    protected function flashSucces(string $message): void
    {
        Auth::demarrer();
        $_SESSION['flash_succes'] = $message;
    }

    protected function flashErreur(string $message): void
    {
        Auth::demarrer();
        $_SESSION['flash_erreur'] = $message;
    }

    /** Vérifie le jeton CSRF de toute requête POST (SEC-05). */
    protected function verifierCsrf(): void
    {
        Csrf::verifier();
    }

    // ------------------------------------------------- lecture des entrées

    protected function str(string $cle, int $max = 0): string
    {
        $valeur = trim((string) ($_POST[$cle] ?? ($_GET[$cle] ?? '')));

        if ($max > 0) {
            return mb_substr($valeur, 0, $max);
        }

        return $valeur;
    }

    protected function int(string $cle, ?int $defaut = null): ?int
    {
        $brut = $_POST[$cle] ?? ($_GET[$cle] ?? null);
        if ($brut === null || $brut === '') {
            return $defaut;
        }

        if (is_numeric($brut)) {
            return (int) $brut;
        }

        return $defaut;
    }

    protected function float(string $cle, ?float $defaut = null): ?float
    {
        $brut = $_POST[$cle] ?? ($_GET[$cle] ?? null);
        if ($brut === null || $brut === '') {
            return $defaut;
        }

        if (is_numeric(str_replace(',', '.', (string) $brut))) {
            return (float) str_replace(',', '.', (string) $brut);
        }

        return $defaut;
    }

    protected function bool(string $cle): bool
    {
        $brut = $_POST[$cle] ?? ($_GET[$cle] ?? false);

        return in_array($brut, [true, 1, '1', 'on', 'true', 'oui'], true);
    }
}
