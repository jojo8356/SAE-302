<?php

declare(strict_types=1);

/**
 * MiniShop — lecture du fichier `.env` (configuration locale).
 *
 * La configuration de l'application vient de trois sources, dans cet ordre
 * de priorité (la dernière gagne) :
 *
 *   1. valeurs par défaut du code (pilote JSON, 127.0.0.1:3306, base minishop) ;
 *   2. `.env` à la racine — fichier LOCAL, jamais commité (`.env.example` est
 *      le modèle versionné) ;
 *   3. variables d'environnement réelles du processus (`DB_USER=… ./run.sh`),
 *      pratiques en CI, en conteneur et pour un test ponctuel.
 *
 * Le format accepté est volontairement minimal et strict :
 *
 *   # commentaire
 *   CLE=valeur
 *   export CLE="valeur avec espaces"     # préfixe `export` toléré
 *   CLE='sans interprétation'
 *
 * Aucune expansion de variable, aucune exécution : une ligne non reconnue est
 * ignorée (le fichier reste un simple fichier de données). Les clés sont
 * validées (`[A-Za-z_][A-Za-z0-9_]*`) pour qu'une faute de frappe ne puisse
 * pas polluer l'environnement.
 *
 * Emplacement : `<racine>/.env`, surchargeable par `MINISHOP_ENV_FILE`
 * (utilisé par les tests pour ne jamais toucher au fichier du développeur).
 */

namespace App\Config;

final class Env
{
    /** Nom du fichier par défaut, à la racine du projet. */
    public const FICHIER = '.env';

    /** Variable d'environnement qui déplace le fichier lu. */
    public const VARIABLE_FICHIER = 'MINISHOP_ENV_FILE';

    /** @var array<string,string>|null valeurs du fichier, `null` = pas encore lu */
    private static ?array $valeurs = null;

    /** @var string|null chemin réellement lu (diagnostic) */
    private static ?string $cheminLu = null;

    /** Racine du projet (le fichier `.env` y est cherché par défaut). */
    public static function racine(): string
    {
        return dirname(__DIR__, 2);
    }

    /** Chemin du fichier de configuration à lire. */
    public static function chemin(): string
    {
        $force = getenv(self::VARIABLE_FICHIER);

        if (is_string($force) && $force !== '') {
            return $force;
        }

        return self::racine() . '/' . self::FICHIER;
    }

    /** Chemin effectivement lu au dernier chargement (`null` si absent). */
    public static function cheminLu(): ?string
    {
        self::toutes();

        return self::$cheminLu;
    }

    /** Toutes les valeurs du fichier (lecture unique, mise en cache). */
    public static function toutes(): array
    {
        if (self::$valeurs !== null) {
            return self::$valeurs;
        }

        $chemin = self::chemin();
        self::$valeurs = [];
        if (is_file($chemin)) {
            self::$valeurs = self::analyser($chemin);
            self::$cheminLu = $chemin;
        }

        return self::$valeurs;
    }

    /**
     * Valeur d'une clé : variable d'environnement réelle, puis `.env`, puis
     * le défaut fourni.
     */
    public static function get(string $cle, ?string $defaut = null): ?string
    {
        $environnement = getenv($cle);
        if (is_string($environnement) && $environnement !== '') {
            return $environnement;
        }

        $valeurs = self::toutes();
        if (array_key_exists($cle, $valeurs)) {
            return $valeurs[$cle];
        }

        return $defaut;
    }

    /** Valeur entière, avec repli si la valeur n'est pas numérique. */
    public static function entier(string $cle, int $defaut): int
    {
        $brut = self::get($cle);
        if ($brut === null || preg_match('/^-?\d+$/', trim($brut)) !== 1) {
            return $defaut;
        }

        return (int) trim($brut);
    }

    /** Valeur booléenne : 1/true/oui/yes/on (casse ignorée). */
    public static function booleen(string $cle, bool $defaut): bool
    {
        $brut = self::get($cle);
        if ($brut === null || trim($brut) === '') {
            return $defaut;
        }

        $normalisee = strtolower(trim($brut));

        return in_array($normalisee, ['1', 'true', 'oui', 'yes', 'on'], true);
    }

    /** Oublie le cache (tests, bascule de fichier à chaud). */
    public static function reinitialiser(): void
    {
        self::$valeurs = null;
        self::$cheminLu = null;
    }

    /**
     * Analyse d'un fichier `.env`.
     *
     * @return array<string,string>
     */
    public static function analyser(string $chemin): array
    {
        $contenu = @file_get_contents($chemin);
        if ($contenu === false) {
            return [];
        }

        // BOM éventuel (fichier édité sous Windows) puis fins de ligne Windows
        $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu) ?? $contenu;
        $lignes = preg_split('/\R/', $contenu);
        if ($lignes === false) {
            $lignes = [];
        }

        $valeurs = [];
        foreach ($lignes as $ligne) {
            $valeur = self::analyserLigne($ligne);
            if ($valeur !== null) {
                $valeurs[$valeur[0]] = $valeur[1];
            }
        }

        return $valeurs;
    }

    /**
     * Une ligne → [clé, valeur], ou `null` si la ligne ne porte pas d'affectation.
     *
     * @return array{0:string,1:string}|null
     */
    private static function analyserLigne(string $ligne): ?array
    {
        $ligne = trim($ligne);
        if ($ligne === '' || str_starts_with($ligne, '#')) {
            return null;
        }

        if (str_starts_with($ligne, 'export ')) {
            $ligne = trim(substr($ligne, 7));
        }

        $position = strpos($ligne, '=');
        if ($position === false) {
            return null;
        }

        $cle = trim(substr($ligne, 0, $position));
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $cle) !== 1) {
            return null;
        }

        $valeur = trim(substr($ligne, $position + 1));

        return [$cle, self::nettoyerValeur($valeur)];
    }

    /** Retire les guillemets et les commentaires de fin de ligne. */
    private static function nettoyerValeur(string $valeur): string
    {
        if ($valeur !== '' && ($valeur[0] === '"' || $valeur[0] === "'")) {
            $guillemet = $valeur[0];
            $fin = strrpos($valeur, $guillemet);
            if ($fin !== false && $fin > 0) {
                $interieur = substr($valeur, 1, $fin - 1);
                if ($guillemet === '"') {
                    return str_replace(['\\"', '\\n', '\\t', '\\\\'], ['"', "\n", "\t", '\\'], $interieur);
                }

                return $interieur;
            }
        }

        $commentaire = strpos($valeur, ' #');
        if ($commentaire !== false) {
            $valeur = substr($valeur, 0, $commentaire);
        }

        return trim($valeur);
    }
}
