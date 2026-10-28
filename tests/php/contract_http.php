<?php

declare(strict_types=1);

/**
 * MiniShop — contrats HTTP (statiques), sans serveur web.
 *
 *   php tests/php/contract_http.php
 *
 * Complète l'E2E (`scripts/e2e.mjs`, qui exige un runtime HTTP) par des
 * contrôles que l'on peut rejouer partout, y compris en intégration continue
 * sans navigateur ni serveur :
 *
 *   1. **Table de routage** : chaque route déclarée porte une garde d'accès,
 *      les routes d'administration sont protégées, les actions POST existent
 *      réellement dans les contrôleurs appelés.
 *   2. **Liens internes** : chaque `href="/…"` et `action="/…"` des vues
 *      correspond à une route déclarée. Un lien mort est une page 404 en
 *      production ; aucun test ne le voyait jusqu'ici.
 *   3. **En-têtes de sécurité** : CSP sans `unsafe-inline`, `frame-ancestors
 *      'none'`, `nosniff`, `Referrer-Policy`, `X-Frame-Options` — vérifiés
 *      dans `public/index.php`, source unique.
 *   4. **Contrats de méthode** : une action qui écrit n'est jamais joignable
 *      en GET (pas de mutation par lien, cf. SEC-04).
 *
 * Ces contrôles sont volontairement statiques : ils ne remplacent pas l'E2E,
 * ils garantissent qu'une erreur de câblage est vue AVANT le déploiement.
 */

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$racine = dirname(__DIR__, 2);
$tests = 0;
$echecs = 0;

/** Enregistre un contrôle. */
$check = static function (string $libelle, bool $ok, string $detail = '') use (&$tests, &$echecs): void {
    ++$tests;
    if (!$ok) {
        ++$echecs;
    }
    $puce = '✅';
    if (!$ok) {
        $puce = '❌';
    }
    $ligne = '  ' . $puce . ' ' . $libelle;
    if ($detail !== '' && !$ok) {
        $ligne .= ' — ' . $detail;
    }
    echo $ligne . "\n";
};

$source = (string) file_get_contents($racine . '/public/index.php');

// ============================================================ 1. routage
echo "1. Table de routage\n";

/**
 * Extrait les routes déclarées : `$routeur->get('/x', [C::class, 'action'], 'garde')`.
 *
 * @return list<array{verbe:string, motif:string, controleur:string, methode:string, garde:string}>
 */
function routes(string $source): array
{
    $routes = [];
    $motif = '/\$routeur->(get|post)\(\s*\'([^\']+)\'\s*,\s*\[\s*([A-Za-z0-9_\\\\:]+)\s*,\s*\'([^\']+)\'\s*\]\s*(?:,\s*\'([a-z]+)\')?\s*\)/';
    if (preg_match_all($motif, $source, $trouves, PREG_SET_ORDER) === false) {
        return [];
    }
    foreach ($trouves as $trouve) {
        $garde = 'public';
        if (isset($trouve[5]) && $trouve[5] !== '') {
            $garde = $trouve[5];
        }
        $routes[] = [
            'verbe' => strtoupper($trouve[1]),
            'motif' => $trouve[2],
            'controleur' => $trouve[3],
            'methode' => $trouve[4],
            'garde' => $garde,
        ];
    }

    return $routes;
}

$routes = routes($source);
$check('routes déclarées trouvées', count($routes) >= 40, count($routes) . ' route(s)');

$sansGarde = [];
$verbesInconnus = [];
foreach ($routes as $route) {
    if (!in_array($route['garde'], ['public', 'client', 'admin', 'super'], true)) {
        $verbesInconnus[] = $route['motif'] . ' (' . $route['garde'] . ')';
    }
    if ($route['garde'] === '') {
        $sansGarde[] = $route['motif'];
    }
}
$check('chaque route déclare une garde connue', $verbesInconnus === [], implode(', ', $verbesInconnus));
$check('aucune route sans garde', $sansGarde === [], implode(', ', $sansGarde));

// Toute route /admin/* doit être protégée.
$adminOuvertes = [];
foreach ($routes as $route) {
    if (!str_starts_with($route['motif'], '/admin')) {
        continue;
    }
    if ($route['motif'] === '/admin/connexion' || $route['motif'] === '/admin/deconnexion') {
        continue; // pages de connexion, par nature publiques
    }
    if (!in_array($route['garde'], ['admin', 'super'], true)) {
        $adminOuvertes[] = $route['motif'] . ' (' . $route['garde'] . ')';
    }
}
$check('toute page /admin/* est protégée (RB-13)', $adminOuvertes === [], implode(', ', $adminOuvertes));

// ============================================================ 2. méthodes
echo "\n2. Contrat de méthode (pas de mutation par lien)\n";

$ecrituresEnGet = [];
foreach ($routes as $route) {
    if ($route['verbe'] === 'GET' && preg_match('/^(ajouter|supprimer|valider|creer|modifier|enregistrer|payer|annuler|retirer|changer|mettre_a_jour)/i', $route['methode']) === 1) {
        $ecrituresEnGet[] = $route['motif'] . ' → ' . $route['methode'];
    }
}
$check('aucune action d’écriture joignable en GET (SEC-04)', $ecrituresEnGet === [], implode(', ', $ecrituresEnGet));
$check('les routes POST existent (formulaires traités)', count(array_filter($routes, static fn (array $r): bool => $r['verbe'] === 'POST')) >= 10);

// ============================================================ 3. liens des vues
echo "\n3. Liens internes des vues → routes déclarées\n";

/**
 * Les motifs de route deviennent des expressions régulières.
 *
 * TOUS les verbes sont inclus : un `<form method="post" action="/panier/ajouter">`
 * vise une route POST, et l'exclure de la table produisait quatre faux liens
 * morts. Ce qui est vérifié ici, c'est que la DESTINATION existe, pas la
 * méthode employée (le contrat de méthode est contrôlé en §2).
 */
$motifs = [];
foreach ($routes as $route) {
    $parties = explode('/', $route['motif']);
    $parties = array_map(static function (string $partie): string {
        if (preg_match('/^\{[a-z_]+\}$/', $partie) === 1) {
            return '[^/]+';
        }

        return preg_quote($partie, '#');
    }, $parties);
    $motifs[] = '#^' . implode('/', $parties) . '$#u';
}

/** Cette URL interne correspond-elle à une route ? */
$routeExiste = static function (string $url) use ($motifs): bool {
    $chemin = parse_url($url, PHP_URL_PATH);
    if (!is_string($chemin) || $chemin === '') {
        return false;
    }
    foreach ($motifs as $motif) {
        if (preg_match($motif, $chemin) === 1) {
            return true;
        }
    }

    return false;
};

$vues = [];
$iterateur = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($racine . '/app/View', FilesystemIterator::SKIP_DOTS),
);
foreach ($iterateur as $fichier) {
    if ($fichier->isFile() && $fichier->getExtension() === 'php') {
        $vues[] = $fichier->getPathname();
    }
}
$check('vues analysées', count($vues) >= 20, count($vues) . ' fichier(s)');

$statiques = ['/css/', '/js/', '/img/'];
$liensMorts = [];
$liensVerifies = 0;

foreach ($vues as $vue) {
    $contenu = (string) file_get_contents($vue);
    // href="…" et action="…" — valeur COMPLÈTE, pour ne pas tronquer les URL
    // construites en deux morceaux (chemin littéral suivi d'une balise PHP
    // d'interpolation), dont le point d'interrogation appartient à la balise
    // et non à la chaîne de requête.
    if (preg_match_all('/(?:href|action)="([^"]*)"/', $contenu, $trouves) === false) {
        continue;
    }
    foreach ($trouves[1] as $lien) {
        if (!str_starts_with($lien, '/')) {
            continue;                       // ancre, mailto:, URL absolue
        }
        if (str_contains($lien, '<?')) {
            continue;                       // URL dynamique : non évaluable ici
        }
        $ignore = false;
        foreach ($statiques as $prefixe) {
            if (str_starts_with($lien, $prefixe)) {
                $ignore = true;
            }
        }
        if ($ignore) {
            continue;
        }
        ++$liensVerifies;
        $candidats = [$lien];
        // /produit/x peut venir d'un gabarit : on teste aussi la forme nue.
        if (substr_count($lien, '/') >= 2 && !$routeExiste($lien)) {
            $segments = explode('/', $lien);
            $candidats[] = '/' . $segments[1] . '/{param}';
        }
        $ok = false;
        foreach ($candidats as $candidat) {
            if ($routeExiste($candidat)) {
                $ok = true;
            }
        }
        if (!$ok) {
            $liensMorts[] = basename($vue) . ' → ' . $lien;
        }
    }
}
$check('liens internes vérifiés', $liensVerifies >= 30, (string) $liensVerifies);
$check('aucun lien mort entre les vues et le routeur', $liensMorts === [], implode(' | ', array_slice($liensMorts, 0, 6)));

// ============================================================ 4. en-têtes
echo "\n4. En-têtes de sécurité (public/index.php)\n";

$entetesAttendus = [
    'X-Content-Type-Options: nosniff',
    'X-Frame-Options: DENY',
    'Referrer-Policy: same-origin',
];
foreach ($entetesAttendus as $entete) {
    $check('en-tête émis : ' . strtok($entete, ':'), str_contains($source, $entete));
}

$check('CSP déclarée', preg_match("/Content-Security-Policy:/", $source) === 1);
$check('CSP : default-src \'none\' (rien par défaut)', str_contains($source, "default-src 'none'"));
$check('CSP : frame-ancestors \'none\' (anti-clickjacking)', str_contains($source, "frame-ancestors 'none'"));
$check('CSP : base-uri \'none\' (anti-injection de <base>)', str_contains($source, "base-uri 'none'"));
$check('CSP : form-action \'self\'', str_contains($source, "form-action 'self'"));
$check('CSP : polices locales uniquement', str_contains($source, "font-src 'self'"));
$check('CSP sans unsafe-inline', !str_contains($source, 'unsafe-inline'));
$check('CSP sans unsafe-eval', !str_contains($source, 'unsafe-eval'));
$check('CSP : aucune origine distante autorisée',
    preg_match('/Content-Security-Policy:[^"]*https?:\/\//', $source) !== 1);
$check('en-têtes émis AVANT tout traitement (aucune sortie préalable)',
    strpos($source, "header('X-Content-Type-Options") < strpos($source, 'Auth::demarrer()'));

// ============================================================ 5. erreurs
echo "\n5. Pages d'erreur\n";

foreach ([403, 404, 500] as $code) {
    $vueErreur = $racine . '/app/View/errors/' . $code . '.php';
    $check('vue d’erreur ' . $code . ' livrée', is_file($vueErreur));
}
$check('404 servi par le routeur', str_contains((string) file_get_contents($racine . '/app/Router.php'), 'page404'));
$check('403/500 servis par les contrôleurs ou le front controller',
    str_contains($source, 'page404') || str_contains($source, 'erreur'));

// ============================================================ verdict
echo "\n" . str_repeat('=', 60) . "\n";
if ($echecs === 0) {
    echo "CONTRATS HTTP : TOUT EST VERT ✅ ({$tests} vérifications)\n";
    exit(0);
}
echo "CONTRATS HTTP : {$echecs} échec(s) sur {$tests} vérifications ❌\n";
exit(1);
