<?php

declare(strict_types=1);

/**
 * Horodatages en UTC à l'écriture ; repli serveur Europe/Paris.
 * Le navigateur affiche les dates selon les préférences personnelles.
 * Aucun changement de configuration ne modifie les dates stockées. Les anciennes
 * dates SQL de provenance inconnue doivent être examinées avant conversion.
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

    /** Instant courant, dans le fuseau d’affichage. */
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
     *     l'instant est celui décrit, puis converti dans le fuseau d’affichage.
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
     * « 15/01/2026 » ou, avec l'heure, « 15/01/2026 19h15 » (heure
     * configurée). Valeur absente ou illisible → « — » (contrat historique
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

    /** Formate un instant quelconque dans le fuseau d’affichage. */
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

    /** Minuit du jour affiché, ou du lendemain, converti en UTC pour les filtres. */
    public static function borneJourUtc(string $jour, bool $lendemain = false): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $jour) !== 1 || (int) substr($jour, 0, 4) < 1001 || (int) substr($jour, 0, 4) > 9998) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $jour, self::fuseauAffichage());
        if ($date === false || $date->format('Y-m-d') !== $jour) { return null; }
        if ($lendemain) { $date = $date->modify('+1 day'); }
        return self::horodatageStockage($date);
    }

    /** Borne calculée par le navigateur, contrôlée sans enregistrer son fuseau. */
    public static function borneTransmise(string $jour, string $borne, bool $lendemain = false): ?string
    {
        if (self::borneJourUtc($jour) === null || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $borne) !== 1) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $borne, self::fuseauStockage());
        $milieu = DateTimeImmutable::createFromFormat('!Y-m-d', $jour, self::fuseauStockage());
        if ($date === false || $milieu === false || $date->format('Y-m-d H:i:s') !== $borne) { return null; }
        if ($lendemain) { $milieu = $milieu->modify('+1 day'); }
        if (abs($date->getTimestamp() - $milieu->getTimestamp()) > 36 * 3600) { return null; }
        return $borne;
    }

    /** Année civile à Paris (numérotation des commandes CMD{année}-…). */
    public static function anneeCivile(): int
    {
        // L'identifiant métier conserve l'année civile de Paris indépendamment
        // du réglage purement visuel du fuseau.
        return (int) self::maintenantUtc()->setTimezone(new DateTimeZone(self::FUSEAU_AFFICHAGE))->format('Y');
    }
}
