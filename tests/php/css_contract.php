<?php

declare(strict_types=1);

/**
 * MiniShop — contrats du design system (docs/DA.md).
 *
 * Pourquoi des tests sur du CSS ? Parce qu'une direction artistique se
 * DÉGRADE silencieusement : un token renommé, une couleur passée sous le
 * seuil AA, une classe utilisée dans une vue mais jamais définie. Aucun de
 * ces défauts ne casse l'application — donc aucun test fonctionnel ne les
 * voit. Ces contrôles sont l'équivalent de « design tokens lint » chez les
 * grandes équipes front.
 *
 * Usage : php tests/php/css_contract.php
 *         node scripts/wasm_run.mjs tests/php/css_contract.php
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

$echecs = 0;
$tests = 0;

$check = static function (string $label, bool $ok, string $detail = '') use (&$echecs, &$tests): void {
    ++$tests;
    $icone = '  ✅ ';
    if (!$ok) {
        $icone = '  ❌ ';
    }
    $suffixe = '';
    if (!$ok && $detail !== '') {
        $suffixe = " — {$detail}";
    }
    echo $icone . $label . $suffixe . "\n";
    if (!$ok) {
        ++$echecs;
    }
};

$racine = dirname(__DIR__, 2);
$css = (string) file_get_contents($racine . '/public/css/app.css');
$print = (string) file_get_contents($racine . '/public/css/print.css');
$layout = (string) file_get_contents($racine . '/app/View/layout.php');

/** Luminance relative WCAG 2.1. */
$luminance = static function (string $hex): float {
    $hex = ltrim($hex, '#');
    if (preg_match('/^[0-9a-f]{6}$/i', $hex) !== 1) {
        return 0.0;
    }
    $composantes = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    $lineaire = array_map(static function (int $valeur): float {
        $c = $valeur / 255;
        if ($c <= 0.03928) {
            return $c / 12.92;
        }

        return (($c + 0.055) / 1.055) ** 2.4;
    }, $composantes);

    return 0.2126 * $lineaire[0] + 0.7152 * $lineaire[1] + 0.0722 * $lineaire[2];
};

$contraste = static function (string $a, string $b) use ($luminance): float {
    if (preg_match('/^#[0-9a-f]{6}$/i', $a) !== 1 || preg_match('/^#[0-9a-f]{6}$/i', $b) !== 1) {
        return 0.0;
    }
    $la = $luminance($a);
    $lb = $luminance($b);
    $clair = max($la, $lb);
    $sombre = min($la, $lb);

    return ($clair + 0.05) / ($sombre + 0.05);
};

echo "MiniShop — contrats du design system (docs/DA.md)\n\n";

// ---------------------------------------------------------------- 1. tokens
echo "1. Tokens (DA §5 et §6)\n";
// On ne lit QUE le bloc :root (les sélecteurs .btn--danger:hover contiennent
// « --danger: » et fausseraient une extraction globale).
$blocRacine = '';
if (preg_match('/:root\s*\{(.*?)\n\}/s', $css, $racineTrouvee) === 1) {
    $blocRacine = $racineTrouvee[1];
}
preg_match_all('/^\s*--([a-z0-9-]+)\s*:\s*([^;]+);/im', $blocRacine, $trouves, PREG_SET_ORDER);
$tokens = [];
foreach ($trouves as $trouve) {
    $tokens['--' . $trouve[1]] = trim($trouve[2]);
}

$obligatoires = [
    '--papier', '--surface', '--encre', '--encre-douce', '--bordure',
    '--primaire', '--primaire-fonce', '--accent', '--accent-fonce',
    '--succes', '--succes-fond', '--danger', '--danger-fond', '--info', '--info-fond',
    '--police', '--t-xs', '--t-sm', '--t-md', '--t-lg', '--t-xl', '--t-2xl', '--t-3xl',
    '--e-1', '--e-2', '--e-3', '--e-4', '--e-5', '--e-6',
    '--r-sm', '--r-md', '--r-pilule', '--ombre-1', '--ombre-2', '--largeur',
];
$manquants = [];
foreach ($obligatoires as $token) {
    if (!isset($tokens[$token])) {
        $manquants[] = $token;
    }
}
$check('les ' . count($obligatoires) . ' tokens de la DA sont définis', $manquants === [], implode(', ', $manquants));

// ------------------------------------------------------------ 2. contrastes
echo "\n2. Contrastes WCAG 2.1 AA (DA §9)\n";
$paires = [
    ['encre sur papier', $tokens['--encre'] ?? '', $tokens['--papier'] ?? '', 4.5],
    ['encre douce sur papier', $tokens['--encre-douce'] ?? '', $tokens['--papier'] ?? '', 4.5],
    ['blanc sur primaire', '#ffffff', $tokens['--primaire'] ?? '', 4.5],
    ['blanc sur accent', '#ffffff', $tokens['--accent'] ?? '', 4.5],
    ['succes sur succes-fond', $tokens['--succes'] ?? '', $tokens['--succes-fond'] ?? '', 4.5],
    ['danger sur danger-fond', $tokens['--danger'] ?? '', $tokens['--danger-fond'] ?? '', 4.5],
    ['info sur info-fond', $tokens['--info'] ?? '', $tokens['--info-fond'] ?? '', 4.5],
];
foreach ($paires as [$nom, $texte, $fond, $seuil]) {
    $ratio = $contraste($texte, $fond);
    $check(
        sprintf('%s : %.2f:1 (seuil %.1f)', $nom, $ratio, $seuil),
        $ratio >= $seuil,
        'contraste insuffisant',
    );
}

// Le texte combine .texte-aide et .pied__texte. Une règle plus tardive
// .texte-aide peut écraser .pied__texte à spécificité égale : exiger une
// règle contextuelle plus spécifique et calculer ses couleurs déclarées.
$blocPied = '';
$blocTextePied = '';
preg_match('/(?:^|\n)\.pied\s*\{([^}]*)\}/', $css, $reglePied);
preg_match('/(?:^|\n)\.pied\s+\.pied__texte\s*\{([^}]*)\}/', $css, $regleTextePied);
if (isset($reglePied[1])) {
    $blocPied = $reglePied[1];
}
if (isset($regleTextePied[1])) {
    $blocTextePied = $regleTextePied[1];
}
preg_match('/(?:^|[;\s])background\s*:\s*([^;]+);/', $blocPied, $fondPiedTrouve);
preg_match('/(?:^|[;\s])color\s*:\s*([^;]+);/', $blocTextePied, $couleurPiedTrouvee);
$fondPied = trim($fondPiedTrouve[1] ?? '');
$couleurPied = trim($couleurPiedTrouvee[1] ?? '');
if (preg_match('/^var\((--[a-z0-9-]+)\)$/i', $fondPied, $variablePied) === 1) {
    $fondPied = $tokens[$variablePied[1]] ?? '';
}
if (preg_match('/^var\((--[a-z0-9-]+)\)$/i', $couleurPied, $variableTextePied) === 1) {
    $couleurPied = $tokens[$variableTextePied[1]] ?? '';
}
$check('texte d’aide du pied : sélecteur contextuel plus spécifique',
    $blocTextePied !== '' && preg_match('/class="(?:texte-aide pied__texte|pied__texte texte-aide)"/', $layout) === 1);
$ratioPied = $contraste($couleurPied, $fondPied);
$check(sprintf('texte d’aide du pied : %.2f:1 (seuil 4.5)', $ratioPied), $ratioPied >= 4.5,
    'couleurs absentes, invalides ou contraste insuffisant');

// -------------------------------------------------------------- 3. classes
echo "\n3. Classes utilisées dans les vues et définies dans le CSS\n";
$vues = [];
$iterateur = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine . '/app/View'));
foreach ($iterateur as $fichier) {
    if ($fichier instanceof SplFileInfo && $fichier->getExtension() === 'php') {
        $vues[] = $fichier->getPathname();
    }
}
$vues[] = $racine . '/app/Security/xss.php';

$utilisees = [];
foreach ($vues as $vue) {
    $contenu = (string) file_get_contents($vue);
    if (preg_match_all('/class="([^"]+)"/', $contenu, $trouvesClasses) === 0) {
        continue;
    }
    foreach ($trouvesClasses[1] as $liste) {
        $nettoyee = preg_replace('/<\?.*?\?>/s', ' ', $liste);
        if ($nettoyee === null) {
            $nettoyee = $liste;
        }
        $morceaux = preg_split('/\s+/', $nettoyee);
        if ($morceaux === false) {
            $morceaux = [];
        }
        foreach ($morceaux as $classe) {
            $classe = trim($classe, " '\"");
            if ($classe !== '' && preg_match('/^[a-z][a-z0-9_-]*$/i', $classe) === 1) {
                $utilisees[$classe] = true;
            }
        }
    }
}

$exceptions = [
    // classes ajoutées par le JavaScript ou conditionnelles (documentées ici)
    'badge--neutre', 'badge--ok', 'badge--alerte', 'badge--rupture', 'badge--info',
    'message--succes', 'message--erreur', 'message--info', 'pastille--active',
];
$nonDefinies = [];
foreach (array_keys($utilisees) as $classe) {
    if (in_array($classe, $exceptions, true)) {
        continue;
    }
    if (preg_match('/(^|[^a-z0-9_-])\.' . preg_quote((string) $classe, '/') . '([^a-z0-9_-]|$)/i', $css) !== 1) {
        $nonDefinies[] = $classe;
    }
}
$check(
    count($utilisees) . ' classes repérées dans les vues sont toutes définies',
    $nonDefinies === [],
    implode(', ', $nonDefinies),
);
$check('diagnostic : styles des cinq états définis',
    str_contains($css, '.diagnostic__etat--ok')
    && str_contains($css, '.diagnostic__etat--attention')
    && str_contains($css, '.diagnostic__etat--bloque')
    && str_contains($css, '.diagnostic__etat--manuel')
    && str_contains($css, '.diagnostic__etat--info'));

// --------------------------------------------------------- 4. accessibilité
echo "\n4. Accessibilité et robustesse (DA §9)\n";
$check('focus visible défini (:focus-visible)', str_contains($css, ':focus-visible'));
$check('lien d\'évitement stylé (.lien-evitement)', str_contains($css, '.lien-evitement'));
$check('mouvement réduit géré (prefers-reduced-motion)', str_contains($css, 'prefers-reduced-motion'));
$check('contraste renforcé géré (prefers-contrast)', str_contains($css, 'prefers-contrast'));
$check('cibles tactiles ≥ 44 px déclarées', substr_count($css, 'min-height: 44px') >= 3);
$check('feuille d\'impression livrée', $print !== '' && str_contains($print, '@page'));
$check('aucune police distante (offline, ENF-20)', !str_contains($css, '@import') && !str_contains($css, 'fonts.googleapis'));
$check('aucune image distante', !preg_match('/url\(\s*https?:/i', $css . $print));

// ---------------------------------------------------------- 5. intégration
echo "\n5. Intégration dans le gabarit\n";
$check('feuille de style liée', str_contains($layout, '/css/app.css'));
$check('feuille d\'impression liée avec media="print"', str_contains($layout, 'media="print"'));
$check('JavaScript du panier chargé en defer', str_contains($layout, '/js/panier.js') && str_contains($layout, 'defer'));
$check('lien d\'évitement présent dans le gabarit', str_contains($layout, 'lien-evitement'));
$check('zone d\'annonces aria-live présente', str_contains($layout, 'aria-live="polite"'));
$check('langue déclarée (<html lang="fr">)', str_contains($layout, '<html lang="fr">'));
$check('un seul <main> identifié (#contenu)', substr_count($layout, '<main') === 1 && str_contains($layout, 'id="contenu"'));

foreach (['logo.svg', 'logo-mark.svg', 'favicon.svg'] as $image) {
    $chemin = $racine . '/public/img/' . $image;
    $ok = is_file($chemin) && str_contains((string) file_get_contents($chemin), '<svg');
    $check('image ' . $image . ' livrée (SVG)', $ok);
}

// --- Illustration du hero : une VRAIE image, pas un dessin vectoriel --------
$illustration = $racine . '/public/img/illustration-achat.jpg';
$check('illustration du hero livrée (JPEG)', is_file($illustration));
if (is_file($illustration)) {
    $octets = (int) filesize($illustration);
    $entete = (string) file_get_contents($illustration, false, null, 0, 3);
    // Signature JPEG : FF D8 FF. Un fichier simplement renommé .jpg ne passe pas.
    $check('illustration : signature JPEG réelle', bin2hex($entete) === 'ffd8ff', bin2hex($entete));
    $dimensions = getimagesize($illustration);
    $check('illustration : dimensions lisibles', is_array($dimensions) && $dimensions[0] > 0 && $dimensions[1] > 0);
    if (is_array($dimensions)) {
        $check('illustration : au moins 900 px de large', $dimensions[0] >= 900, $dimensions[0] . '×' . $dimensions[1]);
        $check('illustration : rapport 960/524 respecté',
            abs(($dimensions[0] / $dimensions[1]) - (960 / 524)) < 0.02,
            (string) round($dimensions[0] / $dimensions[1], 3));
    }
    $check('illustration : poids ≤ 250 Ko', $octets > 5000 && $octets <= 250 * 1024, $octets . ' octets');
}

$accueil = (string) file_get_contents($racine . '/app/View/home/index.php');
$check('le hero utilise la vraie image', str_contains($accueil, 'src="/img/illustration-achat.jpg"'));
$check('le hero ne référence plus le dessin vectoriel', !str_contains($accueil, 'illustration-achat.svg'));
$check('illustration : width et height déclarés (aucun décalage au chargement)',
    preg_match('/illustration-achat\.jpg[^>]*width="\d+"[^>]*height="\d+"/s', $accueil) === 1);
$check('illustration : texte alternatif descriptif',
    preg_match('/illustration-achat\.jpg[^>]*alt="[^"]{20,}"/s', $accueil) === 1);
$check('illustration : au-dessus de la ligne de flottaison (loading="eager")',
    preg_match('/illustration-achat\.jpg[^>]*loading="eager"/s', $accueil) === 1);
$check('illustration : servie localement, aucune requête distante',
    preg_match('#<img[^>]*illustration-achat[^>]*src="https?://#s', $accueil) !== 1);
$check('illustration : feuille de style bornant l’affichage (object-fit)',
    str_contains((string) file_get_contents($racine . '/public/css/app.css'), 'object-fit: cover'));


// --- Visuels produits : vraies photos, repli propre, aucune image cassée -----
$dossierVisuels = $racine . '/public/img/produits';
$check('dossier des visuels produits livré', is_dir($dossierVisuels));
$attendus = [
    'zen14.jpg', 'turbox.jpg', 'qhd27.jpg', 'k87.jpg', 'aura.jpg', 'boom.jpg',
    'procast.jpg', 'sac25.jpg', 'usbc.jpg', 'power20.jpg', 'fit2.jpg', 'band.jpg',
];
$manquants = [];
$nonJpeg = [];
$tropLourds = [];
$mauvaisFormat = [];
foreach ($attendus as $fichier) {
    $chemin = $dossierVisuels . '/' . $fichier;
    if (!is_file($chemin)) {
        $manquants[] = $fichier;
        continue;
    }
    $entete = (string) file_get_contents($chemin, false, null, 0, 3);
    if (bin2hex($entete) !== 'ffd8ff') {
        $nonJpeg[] = $fichier;
    }
    if ((int) filesize($chemin) > 120 * 1024) {
        $tropLourds[] = $fichier;
    }
    $dimensions = getimagesize($chemin);
    if (!is_array($dimensions) || $dimensions[0] < 400 || $dimensions[1] < 300) {
        $mauvaisFormat[] = $fichier;
    }
}
$check('12 visuels produits livrés', $manquants === [], implode(', ', $manquants));
$check('visuels produits : vraies images JPEG', $nonJpeg === [], implode(', ', $nonJpeg));
$check('visuels produits : poids ≤ 120 Ko', $tropLourds === [], implode(', ', $tropLourds));
$check('visuels produits : au moins 400 × 300 px', $mauvaisFormat === [], implode(', ', $mauvaisFormat));

// Les URL du jeu de démonstration doivent toutes pointer sur un fichier réel :
// une référence orpheline produirait exactement l'image cassée qu'on veut éviter.
$donnees = $racine . '/data/minishop/produit.json';
if (is_file($donnees)) {
    $contenu = json_decode((string) file_get_contents($donnees), true);
    $lignes = [];
    if (is_array($contenu) && isset($contenu['rows']) && is_array($contenu['rows'])) {
        $lignes = $contenu['rows'];
    }
    $orphelines = [];
    foreach ($lignes as $ligne) {
        $url = (string) ($ligne['image_url'] ?? '');
        if ($url === '') {
            continue;
        }
        if (!is_file($racine . '/public/' . $url)) {
            $orphelines[] = $url;
        }
    }
    $check('jeu de démonstration : chaque image_url existe vraiment',
        $orphelines === [], implode(', ', $orphelines));
}

// Le repli doit exister dans le CSS et dans la vue : pas seulement la photo.
$css = (string) file_get_contents($racine . '/public/css/app.css');
$check('CSS : le visuel remplit son cadre sans déformation (object-fit)',
    preg_match('/\.carte-produit__image\s*\{[^}]*object-fit:\s*cover/s', $css) === 1);
$check('CSS : repli dédié (dégradé + initiale)',
    str_contains($css, '.carte-produit__media--repli'));
$catalogueVue = (string) file_get_contents($racine . '/app/View/catalogue/index.php');
$check('vue catalogue : visuel utilisé quand il existe',
    str_contains($catalogueVue, 'visuel_produit('));
$check('vue catalogue : repli quand il manque',
    str_contains($catalogueVue, 'initiale_produit('));
$check('visuel : image décorative (alt vide, le nom est déjà dans le titre)',
    str_contains($catalogueVue, 'AlternativeDecorative') && str_contains((string) visuel_produit('img/produits/zen14.jpg', new \App\View\Image\AlternativeDecorative()), 'alt=""'));
$check('helpers : une URL distante est refusée',
    (static function () use ($racine): bool {
        require_once $racine . '/app/Security/xss.php';
        return image_produit('https://exemple.fr/p.jpg') === null
            && image_produit('//exemple.fr/p.jpg') === null
            && image_produit('../secret.jpg') === null
            && image_produit('') === null;
    })());
$check('helpers : un fichier absent ne produit pas de balise cassée',
    (static function () use ($racine): bool {
        require_once $racine . '/app/Security/xss.php';
        return image_produit('img/produits/inexistant.jpg') === null
            && visuel_produit('img/produits/inexistant.jpg', new \App\View\Image\AlternativeDecorative()) === null;
    })());
$check('helpers : un visuel existant produit une balise dimensionnée',
    (static function () use ($racine): bool {
        require_once $racine . '/app/Security/xss.php';
        $balise = visuel_produit('img/produits/zen14.jpg', new \App\View\Image\AlternativeDecorative());
        return is_string($balise) && str_contains($balise, 'width="600"')
            && str_contains($balise, 'height="450"') && str_contains($balise, 'loading="lazy"');
    })());
$check('helpers : initiale accentuée correctement pliée',
    (static function () use ($racine): bool {
        require_once $racine . '/app/Security/xss.php';
        return initiale_produit('Écran 27 pouces') === 'É';
    })());

// ---- Icônes de catégorie (Lucide, inlinées : zéro requête réseau) ----------
$check('icônes : les 4 familles ont un tracé distinct', (static function () use ($racine): bool {
    require_once $racine . '/app/Security/xss.php';
    $slugs = ['informatique', 'audio', 'accessoires', 'objets-connectes'];
    $traces = [];
    foreach ($slugs as $slug) {
        $svg = icone_categorie($slug);
        if (!str_starts_with($svg, '<svg') || !str_contains($svg, 'viewBox="0 0 24 24"')) {
            return false;
        }
        $traces[] = $svg;
    }

    return count(array_unique($traces)) === 4;
})());
$check('icônes : tracé SVG pur, sans requête ni script', (static function () use ($racine): bool {
    require_once $racine . '/app/Security/xss.php';
    $svg = icone_categorie('audio', 28);

    return !str_contains($svg, 'http') && !str_contains($svg, '<script')
        && !str_contains($svg, 'href') && str_contains($svg, 'stroke="currentColor"')
        && str_contains($svg, 'width="28"') && str_contains($svg, 'aria-hidden="true"');
})());
$check('icônes : un slug inconnu retombe sur une étiquette, jamais une balise vide', (static function () use ($racine): bool {
    require_once $racine . '/app/Security/xss.php';

    return str_starts_with(icone_categorie('categorie-inventee'), '<svg')
        && icone_categorie('categorie-inventee') !== icone_categorie('audio');
})());
$check('icônes : un slug hostile ne peut pas injecter de balise', (static function () use ($racine): bool {
    require_once $racine . '/app/Security/xss.php';
    $svg = icone_categorie('"><script>alert(1)</script>');

    // la slug n'est jamais recopiée dans le balisage : seul le tracé de la table sort
    return !str_contains($svg, '<script') && !str_contains($svg, 'alert');
})());
$check('les deux vues de catégories affichent l icône', str_contains(
    (string) file_get_contents($racine . '/app/View/home/index.php'), 'icone_categorie('
) && str_contains(
    (string) file_get_contents($racine . '/app/View/home/categories.php'), 'icone_categorie('
));
$check('CSS : pastille d icône dimensionnée et contrastée', (static function () use ($racine): bool {
    $css = (string) file_get_contents($racine . '/public/css/app.css');

    return preg_match('/\.carte-categorie__icone\s*\{[^}]*width:\s*2\.75rem/s', $css) === 1
        && preg_match('/\.carte-categorie__icone\s*\{[^}]*background:\s*var\(--primaire-fond\)/s', $css) === 1
        && str_contains($css, '.carte-categorie:hover .carte-categorie__icone');
})());

// Boutons désactivés : aucun style interactif ne doit s'appliquer à leur survol.
$debutBoutons = strpos($css, '10. Boutons / badges');
$finBoutons = strpos($css, '.badge {', (int) $debutBoutons);
$cssBoutons = substr($css, (int) $debutBoutons, (int) $finBoutons - (int) $debutBoutons);
$check('boutons : survol et appui réservés aux boutons actifs', (static function () use ($cssBoutons): bool {
    if (preg_match_all('/(?:button|\.btn(?:--[a-z]+)?):(?:hover|active)[^{,]*/', $cssBoutons, $regles) < 6) {
        return false;
    }
    foreach ($regles[0] as $regle) {
        if (!str_contains($regle, ':not(:disabled)') || !str_contains($regle, ':not([disabled])')
            || !str_contains($regle, ':not([aria-disabled="true"])')) {
            return false;
        }
    }
    return true;
})());
$check('boutons désactivés : fond, texte, bordure et curseur conservés',
    str_contains($cssBoutons, 'button:disabled,')
    && str_contains($cssBoutons, '.btn[aria-disabled="true"]')
    && preg_match('/button:disabled,.*?\{[^}]*background:\s*var\(--surface-2\);[^}]*color:\s*#64748b;[^}]*border-color:\s*var\(--bordure\);[^}]*cursor:\s*not-allowed;/s', $cssBoutons) === 1);
$check('fiche produit : le bouton indisponible est nativement désactivé',
    str_contains((string) file_get_contents($racine . '/app/View/catalogue/produit.php'),
        '<button type="button" class="btn--acheter" disabled>Indisponible</button>'));

// Le symbole euro conserve le caractère Unicode € ; seule sa police change.
$check('euro : police locale limitée au seul caractère U+20AC',
    preg_match('/@font-face\s*\{[^}]*font-family:\s*"MiniShop Euro";[^}]*url\("\.\.\/fonts\/minishop-euro\.woff2"\)[^}]*unicode-range:\s*U\+20AC;/s', $css) === 1
    && str_contains($css, '--police: "MiniShop Euro", system-ui'));
$check('euro : fichier WOFF2 local présent et licence livrée',
    is_file($racine . '/public/fonts/minishop-euro.woff2')
    && substr((string) file_get_contents($racine . '/public/fonts/minishop-euro.woff2'), 0, 4) === 'wOF2'
    && is_file($racine . '/public/fonts/OFL.txt')
    && str_contains((string) file_get_contents($racine . '/public/fonts/OFL.txt'), 'SIL OPEN FONT LICENSE'));
$check('euro : serveur WASM autorise les polices statiques WOFF2',
    str_contains((string) file_get_contents($racine . '/scripts/wasm-serve.mjs'), "cheminUrl.startsWith('/fonts/')")
    && str_contains((string) file_get_contents($racine . '/scripts/wasm-serve.mjs'), "'.woff2': 'font/woff2'"));
$check('euro : montant reste du texte UTF-8, sans image ni balise',
    euros(1234.5) === '1 234,50 €' && !str_contains(euros(1234.5), '<'));

// Formulaires publics : le libellé du groupe reste sémantique et le titre
// visible est réellement dans la carte, pas incrusté sur sa bordure.
$check('cartes contact et authentification : titre visible centré et légende accessible',
    (static function () use ($racine, $css): bool {
        $vues = [
            'home/contact.php' => 'Votre message',
            'auth/connexion.php' => 'Espace client',
            'auth/inscription.php' => 'Vos informations',
            'auth/admin_connexion.php' => 'Administrateur',
        ];
        foreach ($vues as $vue => $titre) {
            $contenu = (string) file_get_contents($racine . '/app/View/' . $vue);
            if (!str_contains($contenu, '<legend class="visuellement-cache">' . $titre . '</legend>')
                || !str_contains($contenu, '<h2 class="auth__titre">' . $titre . '</h2>')
                || preg_match('/<fieldset>\s*<legend class="visuellement-cache">.*?<\/legend>\s*<h2 class="auth__titre">.*?<\/h2>/s', $contenu) !== 1) {
                return false;
            }
        }
        return preg_match('/\.auth__titre\s*\{[^}]*font-weight:\s*700;[^}]*text-align:\s*center;/s', $css) === 1;
    })());

// ------------------------------------------------------------------ verdict
echo "\n" . str_repeat('=', 60) . "\n";
if ($echecs === 0) {
    echo "DESIGN : TOUT EST VERT ✅ ({$tests} vérifications)\n";
    exit(0);
}
echo "DESIGN : {$echecs} échec(s) sur {$tests} vérifications ❌\n";
exit(1);
