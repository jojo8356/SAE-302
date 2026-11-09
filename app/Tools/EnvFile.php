<?php

declare(strict_types=1);

/**
 * MiniShop — écriture maîtrisée du fichier `.env` (outil de développement).
 *
 * Utilisé par `scripts/db_init.php --switch` et `run.sh --db` pour basculer
 * l'application sur MySQL sans demander au développeur d'éditer un fichier à
 * la main. Le fichier est modifié **ligne à ligne** : les commentaires, l'ordre
 * et les autres clés sont conservés ; une clé absente est ajoutée à la fin.
 *
 * Le fichier cible suit App\Config\Env (donc `MINISHOP_ENV_FILE` en test) et
 * n'est jamais versionné (`.gitignore`).
 */

namespace App\Tools;

use App\Config\Env;

final class EnvFile
{
    /** Chemin effectif (celui que lit l'application). */
    public static function chemin(): string
    {
        return Env::chemin();
    }

    /**
     * Crée le fichier depuis `.env.example` s'il manque.
     *
     * @return bool vrai si un fichier a été créé
     */
    public static function initialiser(): bool
    {
        $cible = self::chemin();
        if (is_file($cible)) {
            return false;
        }

        $modele = Env::racine() . '/.env.example';
        $contenu = '';
        if (is_file($modele)) {
            $lu = file_get_contents($modele);
            if ($lu !== false) {
                $contenu = $lu;
            }
        }

        $dossier = dirname($cible);
        if (!is_dir($dossier)) {
            @mkdir($dossier, 0775, true);
        }

        return file_put_contents($cible, $contenu) !== false;
    }

    /**
     * Définit (ou ajoute) une clé. Retourne vrai si le fichier a changé.
     */
    public static function definir(string $cle, string $valeur): bool
    {
        $cible = self::chemin();
        $contenu = '';
        if (is_file($cible)) {
            $lu = file_get_contents($cible);
            if ($lu !== false) {
                $contenu = $lu;
            }
        }

        $ligne = $cle . '=' . self::formater($valeur);
        $motif = '/^\s*(?:export\s+)?' . preg_quote($cle, '/') . '\s*=.*$/m';

        if (preg_match($motif, $contenu) === 1) {
            $nouveau = preg_replace($motif, $ligne, $contenu, 1) ?? $contenu;
        } else {
            if ($contenu !== '' && !str_ends_with($contenu, "\n")) {
                $contenu .= "\n";
            }
            $nouveau = $contenu . $ligne . "\n";
        }

        if ($nouveau === $contenu) {
            Env::reinitialiser();

            return false;
        }

        $dossier = dirname($cible);
        if (!is_dir($dossier)) {
            @mkdir($dossier, 0775, true);
        }
        $ecrit = file_put_contents($cible, $nouveau) !== false;
        Env::reinitialiser();

        return $ecrit;
    }

    /** Valeur actuelle d'une clé dans le fichier (sans les variables d'environnement). */
    public static function valeur(string $cle): ?string
    {
        $valeurs = Env::analyser(self::chemin());

        return $valeurs[$cle] ?? null;
    }

    /**
     * Applique plusieurs paires clé → valeur d'un coup.
     *
     * @param array<string,string> $paires
     * @return list<string> clés réellement modifiées
     */
    public static function definirPlusieurs(array $paires): array
    {
        $modifiees = [];
        foreach ($paires as $cle => $valeur) {
            if (self::definir($cle, $valeur)) {
                $modifiees[] = $cle;
            }
        }

        return $modifiees;
    }

    /** Guillemet la valeur si elle contient un espace, un `#` ou un guillemet. */
    private static function formater(string $valeur): string
    {
        if ($valeur === '') {
            return '';
        }
        if (preg_match('/[\s#"\'\\\\]/', $valeur) === 1) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $valeur) . '"';
        }

        return $valeur;
    }
}
