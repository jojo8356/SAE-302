<?php

declare(strict_types=1);

/**
 * MiniShop — utilitaires texte du pilote JSON.
 *
 * La collation cible du projet est utf8mb4_unicode_ci : insensible à la casse
 * ET aux accents. Pour que le pilote JSON se comporte à l'identique (recherche
 * EF-VIS-02 « insensible à la casse et aux accents », comparaisons de tri),
 * les comparaisons passent par ce pliage (folding).
 *
 * Stratégie « modules natifs d'abord, repli maison ensuite » (env-agnostic) :
 *   1. ext/intl (ICU)  : Transliterator « Any-Latin; Latin-ASCII » —
 *      dé-accentuation Unicode COMPLÈTE (grec, cyrillique, ligatures…) et
 *      Collator (force PRIMARY) pour les comparaisons — l'équivalent direct
 *      d'une collation utf8mb4_unicode_ci. Utilisé dès que l'extension est
 *      chargée (serveur LAMP normal).
 *   2. ext/mbstring    : mb_convert_case(…, MB_CASE_FOLD) — pliage de casse
 *      Unicode complet (ß → ss, Œ → œ…), complété par la table latine maison
 *      pour les accents. C'est le chemin actif dans le bac à sable PHP-WASM
 *      (intl n'y est pas compilé).
 *   3. repli minimal   : table latine + strtolower (environnement sans
 *      mbstring — les clés majuscules de la table prennent alors le relais).
 *
 * Quel que soit le chemin, le résultat est identique pour l'alphabet latin :
 * le jeu de tests (tests/php/run_tests.php, T-25) verrouille ce contrat.
 */

namespace App\Model\Data;

final class Text
{
    /**
     * @var array<string,string> correspondances accents latins -> ASCII
     * (repli lorsque ext/intl est absente ; les clés majuscules ne servent
     * que si mbstring manque aussi, strtolower étant alors byte à byte)
     */
    private const FOLD_MAP = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ñ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
        'Ç' => 'C',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ñ' => 'N',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ý' => 'Y',
        'œ' => 'oe', 'Œ' => 'OE', 'æ' => 'ae', 'Æ' => 'AE', 'ß' => 'ss',
    ];

    /** @var Transliterator|false|null instance ICU mise en cache (false = absente) */
    private static mixed $transliterator = null;

    /** @var Collator|false|null collateur ICU PRIMARY mis en cache (false = absent) */
    private static mixed $collator = null;

    /** Pliage insensible casse + accents (clé de comparaison « collation »). */
    public static function fold(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // 1) casse : pliage Unicode complet (mbstring) — ß→ss, Œ→œ…
        if (function_exists('mb_convert_case')) {
            $value = mb_convert_case($value, MB_CASE_FOLD, 'UTF-8');
        } else {
            $value = strtolower($value); // repli byte à byte : la table ci-dessous complète
        }

        // 2) accents : ext/intl (ICU) si présente — couverture Unicode totale
        $transliterator = self::transliterator();
        if ($transliterator !== false) {
            return $transliterator->transliterate($value) ?? $value;
        }

        // 3) repli : table latine maison
        return strtr($value, self::FOLD_MAP);
    }

    /**
     * Génère un slug URL : minuscules ASCII, accents pliés, séparateurs '-'.
     * (Équivalent du trigger trg_categorie_slug + usage produit du CDC.)
     */
    public static function slug(string $value): string
    {
        $folded = self::fold(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $folded) ?? '';

        return trim($slug, '-');
    }

    /**
     * Comparaison de tri « collation » : insensible casse/accent, null d'abord.
     * Utilise le Collator ICU (force PRIMARY ≈ utf8mb4_unicode_ci) quand
     * ext/intl est disponible, sinon le pliage ASCII.
     *
     * @return int -1, 0 ou 1 (contrat strcmp)
     */
    public static function compare(?string $a, ?string $b): int
    {
        if ($a === null && $b === null) {
            return 0;
        }
        if ($a === null) {
            return -1;
        }
        if ($b === null) {
            return 1;
        }

        $collator = self::collator();
        if ($collator !== false) {
            $verdict = $collator->compare($a, $b);
            if ($verdict !== false) {
                return $verdict < 0 ? -1 : ($verdict > 0 ? 1 : 0);
            }
        }

        return strcmp(self::fold($a), self::fold($b));
    }

    /** @return Transliterator|false l'instance « Any-Latin; Latin-ASCII », ou false si intl est absent */
    private static function transliterator(): mixed
    {
        if (self::$transliterator === null) {
            self::$transliterator = class_exists('Transliterator')
                ? (Transliterator::create('Any-Latin; Latin-ASCII') ?: false)
                : false;
        }

        return self::$transliterator;
    }

    /** @return Collator|false un collateur français PRIMARY, ou false si intl est absent */
    private static function collator(): mixed
    {
        if (self::$collator === null) {
            if (class_exists('Collator')) {
                $collator = new Collator('fr_FR');
                $collator->setStrength(Collator::PRIMARY); // insensible casse ET accents
                self::$collator = $collator;
            } else {
                self::$collator = false;
            }
        }

        return self::$collator;
    }
}
