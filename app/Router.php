<?php

declare(strict_types=1);

/**
 * MiniShop — routeur à liste blanche (§9.3, D4-L8-01).
 *
 * AUCUNE route dynamique : chaque URL exécutable est déclarée ici, avec sa
 * méthode HTTP et sa garde d'accès ('public' | 'client' | 'admin' | 'super',
 * RB-13). Toute URL inconnue répond 404 — il n'existe pas de « catch-all »
 * qui exposerait une action par accident.
 */

namespace App;

use App\Security\Auth;

final class Router
{
    /** @var array<string, array<string, array{action: array{0: string, 1: string}, garde: string}>> routes[methode][motif] */
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $motif, array $action, string $garde = 'public'): void
    {
        $this->routes['GET'][$motif] = ['action' => $action, 'garde' => $garde];
    }

    public function post(string $motif, array $action, string $garde = 'public'): void
    {
        $this->routes['POST'][$motif] = ['action' => $action, 'garde' => $garde];
    }

    /**
     * Dispatch une requête ; interrompt (404) si la route est inconnue.
     *
     * @return array{action: array{0: string, 1: string}, garde: string, params: array<string, string>}
     */
    public function resoudre(string $methode, string $uri): array
    {
        $chemin = parse_url($uri, PHP_URL_PATH);
        if (!$chemin) {
            $chemin = '/';
        }
        $chemin = rtrim($chemin, '/');
        if ($chemin === '') {
            $chemin = '/';
        }
        $routes = $this->routes[$methode] ?? [];

        // 1) correspondance exacte
        if (isset($routes[$chemin])) {
            return ['action' => $routes[$chemin]['action'], 'garde' => $routes[$chemin]['garde'], 'params' => []];
        }

        // 2) motifs avec paramètres {nom}
        foreach ($routes as $motif => $route) {
            $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $motif) . '$#u';
            if (preg_match($regex, $chemin, $m) === 1) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

                return ['action' => $route['action'], 'garde' => $route['garde'], 'params' => $params];
            }
        }

        http_response_code(404);
        (new \App\Controller\HomeController())->page404();
        exit;
    }

    /** Applique la garde d'accès d'une route (RB-13) puis exécute l'action. */
    public function executer(array $route): void
    {
        [$controleur, $action] = $route['action'];
        match ($route['garde']) {
            'client' => Auth::exigeClient(),
            'admin' => Auth::exigeAdmin(),
            'super' => Auth::exigeSuper(),
            default => null,
        };

        // les routes déclarent soit un nom court ('HomeController'), soit un
        // FQCN (HomeController::class) — normalisation ici
        $classe = 'App\\Controller\\' . $controleur;
        if (str_contains($controleur, '\\')) {
            $classe = $controleur;
        }
        $instance = new $classe();
        $instance->{$action}(...array_values($route['params']));
    }
}
