<?php

declare(strict_types=1);

/**
 * MiniShop — empreinte de la révision du code affichée par les scripts.
 *
 * Pourquoi : `scripts/db_init.php` et `run.sh` peuvent être exécutés depuis une
 * copie partiellement mise à jour (fichiers récupérés un par un, `git checkout`
 * incomplet). Le symptôme est trompeur — un message d'erreur d'une révision
 * antérieure ressemble à un bug de la révision courante. Afficher l'empreinte
 * rend la confusion immédiatement visible : il suffit de comparer avec
 * `git log -1 --oneline`.
 *
 * Aucune dépendance : `git` est appelé en silence, et l'absence de dépôt (copie
 * dézippée, sandbox PHP-WASM) donne « inconnue » plutôt qu'une erreur.
 */

namespace App\Tools;

final class Revision
{
    /** Valeur mise en cache (`null` = pas encore calculée). */
    private static ?string $cache = null;

    /**
     * Empreinte lisible : `6a7dde5 · 2026-10-27`, ou `inconnue` hors dépôt Git.
     */
    public static function courante(): string
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $racine = dirname(__DIR__, 2);
        if (!is_dir($racine . '/.git') && !is_file($racine . '/.git')) {
            self::$cache = 'inconnue (aucun dépôt Git)';

            return self::$cache;
        }

        $sha = self::git(['rev-parse', '--short', 'HEAD']);
        if ($sha === null) {
            self::$cache = 'inconnue (git indisponible)';

            return self::$cache;
        }

        $date = self::git(['log', '-1', '--format=%cs']);
        if ($date === null || $date === '') {
            self::$cache = $sha;

            return self::$cache;
        }

        self::$cache = $sha . ' · ' . $date;

        return self::$cache;
    }

    /**
     * Révision sur laquelle la copie a été synchronisée (`.git/minishop-synchro`),
     * écrite par `scripts/maj-depuis-branche.sh` — `null` si aucune synchro n'a
     * eu lieu.
     *
     * Une synchro de fichiers ne déplace NOTRE tête de branche : afficher
     * seulement `HEAD` laisserait croire que la copie est périmée alors qu'elle
     * vient d'être mise à jour.
     */
    public static function synchroniseSur(): ?string
    {
        $racine = dirname(__DIR__, 2);
        $fichier = $racine . '/.git/minishop-synchro';
        if (!is_file($fichier)) {
            return null;
        }
        $lignes = file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lignes) || $lignes === []) {
            return null;
        }
        $sha = trim((string) $lignes[0]);
        if (preg_match('/^[0-9a-f]{7,40}$/', $sha) !== 1) {
            return null;
        }

        return substr($sha, 0, 7);
    }

    /** Le code local est-il modifié par rapport au dernier commit ? */
    public static function modifie(): bool
    {
        $etat = self::git(['status', '--porcelain', '--untracked-files=no']);

        return $etat !== null && $etat !== '';
    }

    /** Réinitialise le cache (tests). */
    public static function reinitialiser(): void
    {
        self::$cache = null;
    }

    /**
     * Sortie nettoyée d'une commande git, ou `null` si git ne peut pas répondre.
     *
     * @param list<string> $arguments
     */
    private static function git(array $arguments): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }

        $commande = 'git -C ' . escapeshellarg(dirname(__DIR__, 2))
            . ' ' . implode(' ', array_map(escapeshellarg(...), $arguments)) . ' 2>/dev/null';
        $lignes = [];
        $code = 1;
        // @ : sous PHP-WASM, exec() ne peut pas créer de processus ; l'absence
        // de réponse est déjà traitée par le code retour.
        @exec($commande, $lignes, $code);
        if ($code !== 0) {
            return null;
        }

        return trim(implode("\n", $lignes));
    }
}
