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

// ------------------------------------------------------------------ verdict
echo "\n" . str_repeat('=', 60) . "\n";
if ($echecs === 0) {
    echo "DESIGN : TOUT EST VERT ✅ ({$tests} vérifications)\n";
    exit(0);
}
echo "DESIGN : {$echecs} échec(s) sur {$tests} vérifications ❌\n";
exit(1);
