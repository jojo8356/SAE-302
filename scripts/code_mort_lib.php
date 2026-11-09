<?php

declare(strict_types=1);

/** Analyse conservatrice de candidats, pas preuve d'inaccessibilité. */
function cm_tokens(string $source): array
{
    $resultat = [];
    $ligne = 1;
    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            [$id, $texte, $ligne] = $token;
            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
                $ligne += substr_count($texte, "\n");
                continue;
            }
        } else {
            $id = 0;
            $texte = $token;
        }
        $resultat[] = ['id' => $id, 'texte' => $texte, 'ligne' => $ligne];
        $ligne += substr_count($texte, "\n");
    }

    return $resultat;
}

/** Fonctions et méthodes nommées avec corps ; interfaces et prototypes exclus. */
function cm_declarations(array $tokens): array
{
    $fonctions = [];
    $n = count($tokens);
    for ($i = 0; $i < $n; ++$i) {
        if ($tokens[$i]['id'] !== T_FUNCTION) {
            continue;
        }
        $j = $i + 1;
        if (($tokens[$j]['texte'] ?? '') === '&' || in_array($tokens[$j]['id'] ?? 0, [T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG, T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG], true)) {
            ++$j;
        }
        if (($tokens[$j]['id'] ?? 0) !== T_STRING || ($tokens[$j + 1]['texte'] ?? '') !== '(') {
            continue; // fermeture anonyme
        }
        $nom = $tokens[$j]['texte'];
        $ouverture = $j + 1;
        $profondeur = 1;
        $parametres = [];
        $attributs = [];
        for ($k = $ouverture + 1; $k < $n; ++$k) {
            $texte = $tokens[$k]['texte'];
            if ($texte === '(' || $texte === '[' || $texte === '{' || ($tokens[$k]['id'] ?? 0) === T_ATTRIBUTE) {
                ++$profondeur;
            } elseif ($texte === ')' || $texte === ']' || $texte === '}') {
                --$profondeur;
                if ($profondeur === 0) {
                    break;
                }
            }
            if ($profondeur === 1 && $texte === ',') {
                $attributs = [];
            } elseif ($profondeur === 1 && in_array($tokens[$k]['id'], [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
                $attributs[] = $tokens[$k]['id'];
            } elseif ($profondeur === 1 && $tokens[$k]['id'] === T_VARIABLE) {
                $parametres[$tokens[$k]['texte']] = ['ligne' => $tokens[$k]['ligne'], 'promu' => $attributs !== []];
            }
        }
        if ($k >= $n) {
            continue;
        }
        $debut = $k + 1;
        while ($debut < $n && !in_array($tokens[$debut]['texte'], ['{', ';'], true)) {
            ++$debut;
        }
        if (($tokens[$debut]['texte'] ?? '') !== '{') {
            continue;
        }
        $niveau = 1;
        for ($fin = $debut + 1; $fin < $n; ++$fin) {
            $texte = $tokens[$fin]['texte'];
            if ($texte === '{' || $tokens[$fin]['id'] === T_DOLLAR_OPEN_CURLY_BRACES) {
                ++$niveau;
            } elseif ($texte === '}') {
                --$niveau;
                if ($niveau === 0) {
                    break;
                }
            }
        }
        if ($fin >= $n) {
            continue;
        }
        $visibilite = 'publique ou fonction';
        for ($v = $i - 1; $v >= 0 && !in_array($tokens[$v]['texte'], [';', '{', '}'], true); --$v) {
            if ($tokens[$v]['id'] === T_PRIVATE) {
                $visibilite = 'privée';
                break;
            }
            if ($tokens[$v]['id'] === T_PROTECTED) {
                $visibilite = 'protégée';
                break;
            }
        }
        $fonctions[] = ['nom' => $nom, 'ligne' => $tokens[$j]['ligne'], 'visibilite' => $visibilite, 'parametres' => $parametres, 'corps' => array_slice($tokens, $debut + 1, $fin - $debut - 1)];
    }

    return $fonctions;
}

/**
 * Cherche les paramètres jamais cités, les écritures uniques simples et les
 * noms de fonctions/méthodes jamais cités ailleurs dans le périmètre fourni.
 * Les utilisations indirectes restent possibles ; une alerte n'est pas une preuve.
 * @param array<string,string> $sources chemin => source PHP
 * @param list<string> $cibles fichiers dont les déclarations sont inspectées
 * @return list<array{cle:string,genre:string,fichier:string,ligne:int,fonction:string,nom:string,details:string}>
 */
function cm_analyser(array $sources, array $cibles): array
{
    $tokensParFichier = [];
    $mentions = [];
    $declarations = [];
    foreach ($sources as $fichier => $source) {
        $tokens = cm_tokens($source);
        $tokensParFichier[$fichier] = $tokens;
        $declarations[$fichier] = cm_declarations($tokens);
        foreach ($tokens as $token) {
            $mot = '';
            if ($token['id'] === T_STRING) {
                $mot = strtolower($token['texte']);
            } elseif ($token['id'] === T_CONSTANT_ENCAPSED_STRING) {
                $mot = strtolower(substr($token['texte'], 1, -1));
            }
            if ($mot !== '') {
                $mentions[$mot] = ($mentions[$mot] ?? 0) + 1;
            }
        }
    }
    $nombreDeclarations = [];
    foreach ($declarations as $liste) {
        foreach ($liste as $fonction) {
            $nom = strtolower($fonction['nom']);
            $nombreDeclarations[$nom] = ($nombreDeclarations[$nom] ?? 0) + 1;
        }
    }
    $alertes = [];
    foreach ($cibles as $fichier) {
        foreach ($declarations[$fichier] ?? [] as $fonction) {
            $nom = $fonction['nom'];
            $nomMinuscule = strtolower($nom);
            if (!str_starts_with($nom, '__') && ($mentions[$nomMinuscule] ?? 0) <= ($nombreDeclarations[$nomMinuscule] ?? 0)) {
                cm_ajouter($alertes, 'fonction ou méthode sans référence textuelle', $fichier, $fonction['ligne'], $nom, $nom, $fonction['visibilite'] . ' ; appels dynamiques et API externe possibles');
            }
            $occurrences = [];
            $premiers = [];
            $dynamique = false;
            foreach ($fonction['corps'] as $index => $token) {
                if ($token['id'] === T_STRING && in_array(strtolower($token['texte']), ['compact', 'extract', 'get_defined_vars'], true)) {
                    $dynamique = true;
                }
                if (in_array($token['id'], [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE], true)) {
                    $dynamique = true;
                }
                if ($token['id'] === T_DOLLAR_OPEN_CURLY_BRACES || $token['texte'] === '$') {
                    $dynamique = true;
                }
                if ($token['id'] === T_VARIABLE) {
                    $variable = $token['texte'];
                    $occurrences[$variable] = ($occurrences[$variable] ?? 0) + 1;
                    $premiers[$variable] ??= $index;
                }
            }
            // Les variables peuvent être référencées indirectement : ne pas
            // déduire leur inutilité dans ces fonctions.
            if ($dynamique) {
                continue;
            }
            foreach ($fonction['parametres'] as $variable => $parametre) {
                if (!$parametre['promu'] && !isset($occurrences[$variable])) {
                    cm_ajouter($alertes, 'paramètre non cité dans le corps', $fichier, $parametre['ligne'], $nom, $variable, 'signature et contrats à vérifier avant modification');
                }
            }
            foreach ($occurrences as $variable => $total) {
                $index = $premiers[$variable];
                $suivant = $fonction['corps'][$index + 1]['texte'] ?? '';
                $precedent = $fonction['corps'][$index - 1]['id'] ?? 0;
                $estPropriete = in_array($precedent, [T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true);
                if ($total === 1 && $suivant === '=' && !$estPropriete && !isset($fonction['parametres'][$variable]) && $variable !== '$this') {
                    cm_ajouter($alertes, 'variable écrite une seule fois', $fichier, $fonction['corps'][$index]['ligne'], $nom, $variable, 'vérifier les effets de bord de la valeur et la portée');
                }
            }
        }
    }
    usort($alertes, static function (array $a, array $b): int {
        return strcmp($a['cle'], $b['cle']);
    });

    return $alertes;
}

function cm_ajouter(array &$alertes, string $genre, string $fichier, int $ligne, string $fonction, string $nom, string $details): void
{
    $cle = $fichier . '|' . strtolower($fonction) . '|' . $genre . '|' . strtolower($nom);
    $alertes[$cle] = ['cle' => $cle, 'genre' => $genre, 'fichier' => $fichier, 'ligne' => $ligne, 'fonction' => $fonction, 'nom' => $nom, 'details' => $details];
}

/** Les alertes nouvelles seules rendent le contrôle bloquant. */
function cm_nouvelles(array $alertes, array $reference): array
{
    $connues = array_fill_keys($reference, true);
    $nouvelles = [];
    foreach ($alertes as $alerte) {
        if (!isset($connues[$alerte['cle']])) {
            $nouvelles[] = $alerte;
        }
    }

    return $nouvelles;
}
