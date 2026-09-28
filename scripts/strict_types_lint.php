<?php

declare(strict_types=1);

/**
 * MiniShop — linter « strict types » (outil de développement).
 *
 * Rejoue, avec le seul tokenizer PHP, les vérifications des outils de
 * l'écosystème Packagist (ininstallables dans la sandbox : le proxy n'autorise
 * ni packagist.org ni les assets GitHub ; sur machine normale, utiliser les
 * outils réels — composer.json fourni à la racine) :
 *
 *   1. règle `declare_strict_types` de friendsofphp/php-cs-fixer :
 *      declare(strict_types=1) présent et PREMIÈRE instruction du fichier ;
 *   2. exigences natives de phpstan/phpstan --level=9 (cf. sa doc « level 9
 *      requires native type hints everywhere ») :
 *      a. tout paramètre de fonction/méthode/closure porte un type natif ;
 *      b. toute fonction/méthode/closure déclare son type de retour
 *         (exceptions : __construct/__destruct, où PHP l'interdit) ;
 *      c. toute propriété de classe/trait est typée (les propriétés
 *         promues de constructeur sont couvertes par 2a).
 *   3. RÈGLE MAISON : l'opérateur ternaire (`cond ? a : b` et le court
 *      `cond ?: b`) est INTERDIT — c'est moche, illisible en réunion et
 *      obscurci les conditions imbriquées : if/else, match ou variable
 *      intermédiaire. La coalescence `??` (opérateur binaire distinct)
 *      reste autorisée, de même que les types nullables `?int`.
 *
 * Usage :
 *   php scripts/strict_types_lint.php [chemin1 chemin2…]
 *     (défaut : app public scripts/seed.php tests/php — tout le livrable PHP)
 *   node scripts/wasm_run.mjs scripts/strict_types_lint.php
 *
 * Sortie : une ligne par violation (fichier:ligne — problème), code 1 si
 * au moins une violation, 0 sinon.
 */

const MODIFIEURS = [T_PUBLIC, T_PRIVATE, T_PROTECTED, T_STATIC, T_VAR, T_READONLY, T_ABSTRACT, T_FINAL];

/** Toute la cible : fichiers PHP énumérés récursivement. @return list<string> */
function collecter(string $chemin): array
{
    if (is_file($chemin)) {
        $reel = realpath($chemin);
        if ($reel !== false) {
            return [$reel];
        }

        return [$chemin];
    }
    $fichiers = [];
    $ite = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($chemin, FilesystemIterator::SKIP_DOTS));
    foreach ($ite as $f) {
        if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
            $fichiers[] = $f->getPathname();
        }
    }
    sort($fichiers);

    return $fichiers;
}

/** Token normalisé : [id (0 = ponctuation), texte, ligne]. */
function t(array|string $t): array
{
    if (is_array($t)) {
        return [(int) $t[0], (string) $t[1], (int) $t[2]];
    }

    return [0, $t, -1];
}

/** Indice du prochain token significatif (espaces/commentaires ignorés). */
function sig(array $tokens, int $i): int
{
    while ($i < count($tokens)) {
        $id = $tokens[$i][0];
        if ($id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
            return $i;
        }
        ++$i;
    }

    return $i;
}

/** Texte des tokens significatifs entre $debut et $fin (exclus). */
function texte(array $tokens, int $debut, int $fin): string
{
    $out = '';
    for ($i = $debut; $i < $fin && $i < count($tokens); ++$i) {
        if ($tokens[$i][0] !== T_WHITESPACE) {
            $out .= $tokens[$i][1];
        }
    }

    return $out;
}

/**
 * Règle maison : détecte les opérateurs ternaires (`? … :` et `?:`).
 *
 * Heuristique token-par-token : un `?` est un ternaire si l'on rencontre un
 * `:` au MÊME niveau de profondeur avant toute fin d'instruction (`;`) ou
 * descente sous le niveau du `?` (fermeture de parenthèse/crochet/accolade).
 * Les types nullables (`?int $x` … puis `)` qui referme la signature) ne
 * matchent donc jamais, ni `??` (token T_COALESCE), ni `?->`
 * (T_NULLSAFE_OBJECT_OPERATOR), ni le `:` des types de retour ou des
 * arguments nommés (toujours après une fermeture de niveau).
 *
 * @param list<array> $tokens
 * @return list<string>
 */
function detecterTernaires(string $fichier, array $tokens): array
{
    $violations = [];
    $nb = count($tokens);
    $ouvrants = ['(', '[', '{'];
    $fermants = [')' => -1, ']' => -1, '}' => -1];
    for ($i = 0; $i < $nb; ++$i) {
        if ($tokens[$i][0] !== 0 || $tokens[$i][1] !== '?') {
            continue;
        }
        // type nullable ? « ?Type $var » ou « ): ?Type { » — jamais un ternaire :
        // un nom de type suivi d'une variable, d'une ouverture de corps, d'un
        // point-virgule (abstrait/interface), d'une union « | » ou de « use »
        $u = sig($tokens, $i + 1);
        $v = sig($tokens, $u + 1);
        if (in_array($tokens[$u][0], [T_STRING, T_ARRAY, T_CALLABLE, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            $apres = '';
            if ($tokens[$v][0] === 0) {
                $apres = $tokens[$v][1];
            }
            if ($tokens[$v][0] === T_VARIABLE || $tokens[$v][0] === T_USE || $apres === '{' || $apres === ';' || $apres === '|') {
                continue;
            }
        }
        $profondeur = 0;
        for ($j = $i + 1; $j < $nb; ++$j) {
            $id = $tokens[$j][0];
            $txt = $tokens[$j][1];
            if ($id === 0 && in_array($txt, $ouvrants, true)) {
                ++$profondeur;
                continue;
            }
            if ($id === 0 && isset($fermants[$txt])) {
                if ($profondeur === 0) {
                    break; // on descend sous le niveau du « ? » : pas un ternaire
                }
                --$profondeur;
                continue;
            }
            if ($profondeur === 0) {
                if ($id === 0 && ($txt === ';' || $txt === '}')) {
                    break; // fin d'instruction sans « : » : pas un ternaire
                }
                if ($id === 0 && $txt === ':') {
                    $violations[] = sprintf('%s:%d — opérateur ternaire interdit (règle maison : c\'est moche → if/else, match ou variable intermédiaire)', $fichier, (int) $tokens[$i][2]);
                    break;
                }
            }
        }
    }

    return $violations;
}

/**
 * Analyse un fichier et retourne les violations.
 *
 * @param list<array> $tokens
 * @return list<string>
 */
function analyser(string $fichier, array $tokens): array
{
    $violations = detecterTernaires($fichier, $tokens);
    $nb = count($tokens);

    // ---- 1. declare(strict_types=1) = première instruction -----------------
    $attenduDeclare = true;
    $vuDeclare = false;
    for ($i = 0; $i < $nb; ++$i) {
        [$id, $texte] = $tokens[$i];
        if ($id === T_OPEN_TAG || $id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
            continue;
        }
        if ($attenduDeclare && $id !== T_DECLARE) {
            $violations[] = sprintf('%s:%d — declare(strict_types=1) absent (première instruction : « %s »)', $fichier, $tokens[$i][2], substr($texte, 0, 20));
            $attenduDeclare = false;
        }
        if ($id === T_DECLARE) {
            $corps = texte($tokens, $i, min($nb, $i + 12));
            if (!str_contains($corps, 'strict_types') || !str_contains($corps, '1')) {
                $violations[] = sprintf('%s:%d — declare() trouvé sans strict_types=1', $fichier, $tokens[$i][2]);
            }
            $vuDeclare = true;
            break;
        }
        if ($id !== T_OPEN_TAG) {
            break;
        }
    }
    if ($attenduDeclare && !$vuDeclare && $nb > 0) {
        // fichier uniquement <?php sans code : rien à signaler
    }

    // ---- 2/3. signatures et propriétés ------------------------------------
    $profondeur = 0;            // accolades
    $profClasse = null;         // profondeur du corps de classe en cours
    $signatureEnCours = null;   // infos de la signature en cours d'extraction

    for ($i = 0; $i < $nb; ++$i) {
        [$id, $texte, $ligne] = $tokens[$i];

        // accolades : suivi de profondeur + fin de signature
        if ($id === 0 && $texte === '{') {
            ++$profondeur;
            if ($signatureEnCours !== null) {
                $signatureEnCours['fin'] = $i; // le corps commence
                $violations = [...$violations, ...verifierSignature($fichier, $tokens, $signatureEnCours)];
                $signatureEnCours = null;
            }
            continue;
        }
        if ($id === 0 && $texte === '}') {
            if ($profClasse !== null && $profondeur === $profClasse) {
                $profClasse = null; // fin de la classe
            }
            --$profondeur;
            continue;
        }

        // corps de classe : T_CLASS / T_TRAIT / T_ENUM (hors `new class` anonyme après `::class`)
        if ($id === T_CLASS || $id === T_TRAIT || $id === T_ENUM) {
            $precTexte = '';
            $iPrec = sig($tokens, $i - 1);
            if ($iPrec >= 0 && isset($tokens[$iPrec]) && is_array($tokens[$iPrec])) {
                $precTexte = (string) $tokens[$iPrec][1];
            }
            $apresNew = $precTexte === 'new' || $precTexte === '::';
            if (!$apresNew) {
                // la prochaine '{' ouvre le corps de cette classe
                $j = $i;
                while ($j < $nb && !($tokens[$j][0] === 0 && $tokens[$j][1] === '{') && $tokens[$j][0] !== T_EXTENDS && $tokens[$j][0] !== T_IMPLEMENTS) {
                    ++$j;
                }
                while ($j < $nb && !($tokens[$j][0] === 0 && $tokens[$j][1] === '{')) {
                    ++$j; // sauter extends/implements jusqu'à l'accolade
                }
                if ($j < $nb) {
                    $profClasse = $profondeur + 1; // profondeur APRÈS ouverture
                }
            }
            continue;
        }

        // fonction / closure / arrow fn : début d'extraction de signature
        if ($id === T_FUNCTION || $id === T_FN) {
            $signatureEnCours = ['debut' => $i, 'ligne' => $ligne, 'flechee' => $id === T_FN];
            continue;
        }

        // signature abstraite / d'interface : se termine par ';' au lieu de '{'
        if ($signatureEnCours !== null && $id === 0 && $texte === ';') {
            $signatureEnCours['fin'] = $i;
            $violations = [...$violations, ...verifierSignature($fichier, $tokens, $signatureEnCours)];
            $signatureEnCours = null;
            continue;
        }

        // propriété de classe (au niveau du corps de classe uniquement)
        if ($id === T_PUBLIC || $id === T_PRIVATE || $id === T_PROTECTED || $id === T_VAR) {
            if ($profClasse !== null && $profondeur === $profClasse - 1 + 1 && $signatureEnCours === null) {
                // niveau = corps de classe : le modificateur ouvre-t-il une propriété ?
                $j = sig($tokens, $i + 1);
                $k = $j;
                // sauter static/readonly éventuels
                while ($k < $nb && ($tokens[$k][0] === T_STATIC || $tokens[$k][0] === T_READONLY)) {
                    $k = sig($tokens, $k + 1);
                }
                if ($k < $nb && $tokens[$k][0] === T_VARIABLE) {
                    $dernier = $tokens[sig($tokens, $k - 1)];
                    if (in_array($dernier[0], MODIFIEURS, true)) {
                        $violations[] = sprintf('%s:%d — propriété %s sans type natif (phpstan level 9)', $fichier, $ligne, $tokens[$k][1]);
                    }
                }
            }
            continue;
        }
    }

    return $violations;
}

/**
 * Vérifie la signature collectée : paramètres typés + retour déclaré.
 *
 * @param list<array> $tokens
 * @param array{debut: int, ligne: int, flechee: bool, fin: int} $sig
 * @return list<string>
 */
function verifierSignature(string $fichier, array $tokens, array $sig): array
{
    $violations = [];
    $nb = count($tokens);
    $i = $sig['debut'];

    // nom éventuel (fonction nommée) puis '(' des paramètres
    $j = sig($tokens, $i + 1);
    $nom = '';
    if ($tokens[$j][0] === T_STRING) {
        $nom = $tokens[$j][1];
        $j = sig($tokens, $j + 1);
    }
    if ($tokens[$j][1] !== '(') {
        return []; // pas une signature attendue (ex. usage du mot-clé « function » seul)
    }

    // parenthèse fermante des paramètres (niveau 0)
    $k = $j + 1;
    $niveau = 1;
    while ($k < $nb && $niveau > 0) {
        if ($tokens[$k][0] === 0 && $tokens[$k][1] === '(') {
            ++$niveau;
        }
        if ($tokens[$k][0] === 0 && $tokens[$k][1] === ')') {
            --$niveau;
        }
        ++$k;
    }
    $finParams = $k - 1; // indice de ')'

    // ---- paramètres : découpage au niveau 0 par ','
    $params = [];
    $courant = '';
    $prof = 0;
    for ($p = $j + 1; $p < $finParams; ++$p) {
        [$pid, $ptxt] = $tokens[$p];
        if ($pid === T_WHITESPACE) {
            continue;
        }
        if ($pid === 0 && $ptxt === '(') {
            ++$prof;
        }
        if ($pid === 0 && $ptxt === ')') {
            --$prof;
        }
        if ($pid === 0 && $ptxt === ',' && $prof === 0) {
            $params[] = $courant;
            $courant = '';
            continue;
        }
        $courant .= $ptxt;
    }
    if (trim($courant) !== '') {
        $params[] = $courant;
    }

    $magiques = ['__construct', '__destruct'];
    foreach ($params as $p) {
        if ($p === '') {
            continue;
        }
        $m = [];
        if (preg_match('/^(?:\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*\|?)*(?:\??[A-Za-z_][A-Za-z0-9_\\\\]*)?\s*(\.\.\.)?\s*&?\s*\$([A-Za-z_][A-Za-z0-9_]*)\s*(?:=|$)/u', $p, $m) === 1) {
            $avant = trim(preg_replace('/\.\.\.|&|\$[A-Za-z_][A-Za-z0-9_]*.*$/u', '', $p));
            if ($avant === '') {
                $portee = 'closure';
                if ($nom !== '') {
                    $portee = $nom;
                }
                $violations[] = sprintf('%s:%d — paramètre $%s sans type natif (phpstan level 9) [%s]', $fichier, $sig['ligne'], $m[2] ?? '?', $portee);
            }
        }
    }

    // ---- type de retour : ':' après ')' (et après un éventuel use (...))
    $u = sig($tokens, $finParams + 1);
    if ($tokens[$u][1] === 'use' || $tokens[$u][0] === T_USE) {
        // sauter use ( ... )
        $v = sig($tokens, $u + 1);
        if ($tokens[$v][1] === '(') {
            $prof2 = 1;
            $w = $v + 1;
            while ($w < $nb && $prof2 > 0) {
                if ($tokens[$w][0] === 0 && $tokens[$w][1] === '(') {
                    ++$prof2;
                }
                if ($tokens[$w][0] === 0 && $tokens[$w][1] === ')') {
                    --$prof2;
                }
                ++$w;
            }
            $u = sig($tokens, $w);
        }
    }
    $estMagique = in_array($nom, $magiques, true);
    if (!$estMagique && $tokens[$u][1] !== ':' && !$sig['flechee']) {
        $nomAffiche = $nom;
        if ($nomAffiche === '') {
            $nomAffiche = 'closure';
        }
        $violations[] = sprintf('%s:%d — type de retour manquant (phpstan level 9) [%s]', $fichier, $sig['ligne'], $nomAffiche);
    }

    return $violations;
}

/** Propage une ligne aux tokens « char » (sans ligne) depuis le dernier token horodaté. */
function completerLignes(array $tokens): array
{
    $ligne = 1;
    foreach ($tokens as $i => $t) {
        if ($t[2] > 0) {
            $ligne = $t[2];
        } elseif ($t[2] < 0) {
            $tokens[$i][2] = $ligne;
        }
    }

    return $tokens;
}

// ------------------------------------------------------------------ exécution
$racines = array_slice($_SERVER['argv'] ?? [], 1);
if ($racines === []) {
    // tout le livrable PHP du dépôt
    $racines = ['app', 'public', 'scripts/seed.php', 'tests/php'];
}
$racineDepot = dirname(__DIR__); // fiable sur les deux runtimes (wasm : /repo)
$resolues = [];
foreach ($racines as $r) {
    if (str_starts_with($r, '/')) {
        $resolues[] = $r;
    } else {
        $resolues[] = $racineDepot . '/' . $r;
    }
}
$racines = $resolues;

$fichiers = [];
foreach ($racines as $r) {
    if (!file_exists($r)) {
        echo "chemin inconnu : {$r}\n";
        exit(2);
    }
    $fichiers = [...$fichiers, ...collecter($r)];
}

$violations = [];
foreach ($fichiers as $f) {
    $tokensBruts = token_get_all((string) file_get_contents($f));
    $tokens = completerLignes(array_map(t(...), $tokensBruts));
    $violations = [...$violations, ...analyser($f, $tokens)];
}

$violations = array_values(array_unique($violations)); // un signalement par ligne
printf("Linter strict types — %d fichiers analysés (declare_strict_types de php-cs-fixer, types natifs de phpstan level 9, règle maison : pas de ternaire)\n", count($fichiers));
foreach ($violations as $v) {
    echo '  ❌ ', $v, "\n";
}
printf("%d violation(s)\n", count($violations));
if ($violations === []) {
    exit(0);
}
exit(1);
