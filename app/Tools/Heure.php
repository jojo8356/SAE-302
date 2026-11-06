<?php

declare(strict_types=1);

/**
 * MiniShop — gestionnaire d'heure (fuseaux, stock, affichage français).
 *
 * Bug d'origine : `app/bootstrap.php` forçait
 * `date_default_timezone_set('UTC')`, les horodatages étaient produits
 * par `date()` (donc en UTC) puis réaffichés tels quels par `date_fr()`
 * — une commande passée à 19h15 à Paris s'affichait 18h15 dans
 * l'historique, le détail et les listes (et 17h15 en été).
 *
 * Règles du gestionnaire (convention unique, aucun calcul manuel
 * « +1 » ou « +2 » nulle part dans le code) :
 *
 *   1. STOCKAGE EN UTC, TOUJOURS. Tout instant écrit en base
 *      (date_commande, date_creation, changed_at, derniere_connexion…)
 *      est la représentation UTC de l'instant, au format existant
 *      « Y-m-d H:i:s » (compatibilité schéma SQL DATETIME inchangée).
 *      L'UTC ne connaît pas d'heure d'été : un instant stocké est sans
 *      ambiguïté, y compris pendant l'heure répétée d'octobre.
 *      → Heure::horodatageStockage()
 *
 *   2. AFFICHAGE EN Europe/Paris, TOUJOURS. Toute date montrée à un
 *      visiteur est convertie UTC → Europe/Paris par DateTimeImmutable
 *      + DateTimeZone, qui appliquent les règles IANA de la zone
 *      (CET = UTC+1 l'hiver, CEST = UTC+2 l'été, transitions le dernier
 *      dimanche de mars et d'octobre). → Heure::formater(), consommé
 *      par le helper global date_fr() de toutes les vues.
 *
 *   3. AUCUN APPEL date() « nu » pour un horodatage métier : la
 *      génération passe par ce gestionnaire, insensible au réglage
 *      date.timezone du php.ini comme au fuseau par défaut du script.
 *      (bootstrap maintient date_default_timezone_set('UTC') comme
 *      garde-fou : un oubli éventuel reste en UTC, jamais dans un
 *      fuseau serveur arbitraire.)
 *
 * Sources :
 *   - Manuel PHP, DateTimeImmutable (setTimezone, createFromFormat) :
 *     https://www.php.net/manual/fr/class.datetimeimmutable.php
 *   - Manuel PHP, DateTimeZone : https://www.php.net/manual/fr/class.datetimezone.php
 *   - IANA Time Zone Database (tzdb), zone Europe/Paris (CET/CEST) :
 *     https://data.iana.org/time-zones/data/zonenow.tab
 *     (« +01/+02 - CET/CEST · Europe/Paris ») et fichier europe de la tzdb.
 *
 * Données anciennes : les horodatages déjà stockés l'ont été en UTC
 * (c'était le fuseau forcé de bootstrap) ; la convention ci-dessus les
 * affiche donc correctement dès l'installation du correctif, sans
 * migration. Les lignes du jeu de démonstration SQL (dates figées) sont,
 * elles, lues comme des instants UTC — écart assumé et borné au seed.
 */

namespace App\Tools;

use DateTimeImmutable;
use DateTimeZone;

final class Heure
{
    /** Fuseau de stockage : UTC, sans heure d'été, sans ambiguïté. */
    public const FUSEAU_STOCKAGE = 'UTC';

    /** Fuseau d'affichage : France métropolitaine (CET/CEST, base IANA). */
    public const FUSEAU_AFFICHAGE = 'Europe/Paris';

    /** Format d'un horodatage stocké (schéma DATETIME, inchangé). */
    public const FORMAT_STOCKAGE = 'Y-m-d H:i:s';

    public static function fuseauStockage(): DateTimeZone
    {
        return new DateTimeZone(self::FUSEAU_STOCKAGE);
    }

    public static function fuseauAffichage(): DateTimeZone
    {
        return new DateTimeZone(self::FUSEAU_AFFICHAGE);
    }

    /** Instant courant, en UTC. */
    public static function maintenantUtc(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::fuseauStockage());
    }

    /** Instant courant, vu de Paris. */
    public static function maintenant(): DateTimeImmutable
    {
        return self::maintenantUtc()->setTimezone(self::fuseauAffichage());
    }

    /**
     * Horodatage à ÉCRIRE en base pour un instant donné (maintenant par
     * défaut) : sa représentation UTC au format de stockage.
     */
    public static function horodatageStockage(?DateTimeImmutable $instant = null): string
    {
        $reference = $instant;
        if ($reference === null) {
            $reference = self::maintenantUtc();
        }

        return $reference->setTimezone(self::fuseauStockage())->format(self::FORMAT_STOCKAGE);
    }

    /**
     * Lit un horodatage stocké et retourne l'instant correspondant,
     * exprimé dans le fuseau d'affichage.
     *
     * Formes acceptées :
     *   - « Y-m-d H:i:s » ou « Y-m-d » nus : lus comme de l'UTC
     *     (convention de stockage n° 1) ;
     *   - forme ISO 8601 portant son fuseau (« …T…+02:00 », « …Z ») :
     *     l'instant est celui décrit, puis converti à Paris.
     * Retourne null si la valeur est vide ou illisible.
     */
    public static function instantDepuisStockage(?string $valeur): ?DateTimeImmutable
    {
        if ($valeur === null) {
            return null;
        }
        $valeur = trim($valeur);
        if ($valeur === '') {
            return null;
        }

        if (str_contains($valeur, 'T') || str_ends_with($valeur, 'Z') || preg_match('/[+-]\d{2}:\d{2}$/', $valeur) === 1) {
            try {
                $instant = new DateTimeImmutable($valeur);
            } catch (\Throwable) {
                return null;
            }

            return $instant->setTimezone(self::fuseauAffichage());
        }

        $instant = DateTimeImmutable::createFromFormat(self::FORMAT_STOCKAGE, $valeur, self::fuseauStockage());
        if ($instant === false) {
            $instant = DateTimeImmutable::createFromFormat('Y-m-d', $valeur, self::fuseauStockage());
        }
        if ($instant === false) {
            return null;
        }

        return $instant->setTimezone(self::fuseauAffichage());
    }

    /**
     * Formate un horodatage stocké pour l'affichage français :
     * « 15/01/2026 » ou, avec l'heure, « 15/01/2026 19h15 » (heure de
     * Paris). Valeur absente ou illisible → « — » (contrat historique
     * du helper date_fr()).
     */
    public static function formater(?string $valeur, bool $avecHeure = false): string
    {
        $instant = self::instantDepuisStockage($valeur);
        if ($instant === null) {
            return '—';
        }

        return self::formaterInstant($instant, $avecHeure);
    }

    /** Formate un instant quelconque, converti à l'heure de Paris. */
    public static function formaterInstant(DateTimeImmutable $instant, bool $avecHeure = false): string
    {
        $paris = $instant->setTimezone(self::fuseauAffichage());
        $format = 'd/m/Y';
        if ($avecHeure) {
            $format = 'd/m/Y H\\hi';
        }

        return $paris->format($format);
    }

    /**
     * Décalage de Paris par rapport à UTC pour un instant donné
     * (maintenant par défaut) : « UTC+1 » l'hiver, « UTC+2 » l'été.
     */
    public static function decalage(?DateTimeImmutable $instant = null): string
    {
        $reference = $instant;
        if ($reference === null) {
            $reference = self::maintenantUtc();
        }
        $paris = $reference->setTimezone(self::fuseauAffichage());
        $brut = $paris->format('P'); // « +02:00 »
        $signe = substr($brut, 0, 1);
        $heures = (int) substr($brut, 1, 2);
        $minutes = substr($brut, 4, 2);
        $texte = 'UTC' . $signe . $heures;
        if ($minutes !== '00') {
            $texte .= ':' . $minutes;
        }

        return $texte;
    }

    /** Paris observe-t-il l'heure avancée (CEST) à cet instant ? */
    public static function estHeureAvancee(?DateTimeImmutable $instant = null): bool
    {
        $reference = $instant;
        if ($reference === null) {
            $reference = self::maintenantUtc();
        }

        return $reference->setTimezone(self::fuseauAffichage())->format('I') === '1';
    }

    /**
     * Libellé du fuseau d'affichage pour un instant, ex. :
     * « Europe/Paris (UTC+2, heure d'été) » / « (UTC+1, heure d'hiver) ».
     */
    public static function libelleFuseau(?DateTimeImmutable $instant = null): string
    {
        $saison = 'heure d\'hiver';
        if (self::estHeureAvancee($instant)) {
            $saison = 'heure d\'été';
        }

        return self::FUSEAU_AFFICHAGE . ' (' . self::decalage($instant) . ', ' . $saison . ')';
    }

    /** Année civile à Paris (numérotation des commandes CMD{année}-…). */
    public static function anneeCivile(): int
    {
        return (int) self::maintenant()->format('Y');
    }
}
