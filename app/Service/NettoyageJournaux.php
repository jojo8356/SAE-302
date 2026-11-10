<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Rotation ciblée de JSONL ; écrivains et nettoyage utilisent le même verrou stable. */
final class NettoyageJournaux
{
    /** @return resource */
    private static function verrou(string $chemin): mixed
    {
        $nom = $chemin . '.lock';
        if (is_link($nom)) { throw new RuntimeException('Verrou symbolique refusé.'); }
        $masque = umask(0o077);
        try { $f = @fopen($nom, 'c+b'); } finally { umask($masque); }
        if ($f === false) { throw new RuntimeException('Verrou inaccessible.'); }
        if (!flock($f, LOCK_EX)) { fclose($f); throw new RuntimeException('Verrouillage impossible.'); }
        return $f;
    }

    /** Ajout avec le même verrou que la rotation, sans suivre le lien final. */
    public static function ajouter(string $chemin, string $ligne): bool
    {
        try { $verrou = self::verrou($chemin); } catch (RuntimeException) { return false; }
        try {
            if (is_link($chemin) || (file_exists($chemin) && !is_file($chemin))) { return false; }
            $masque = umask(0o077);
            try { $n = @file_put_contents($chemin, $ligne, FILE_APPEND | LOCK_EX); } finally { umask($masque); }
            if ($n === strlen($ligne)) { @chmod($chemin, 0o600); return true; }
            return false;
        } finally { flock($verrou, LOCK_UN); fclose($verrou); }
    }

    /**
     * @return array{eligible:int,supprime:int,conserve_invalide:int}
     * Les lignes corrompues ou aux dates inconnues restent conservées.
     */
    public static function traiter(string $chemin, string $champ, int $mois, DateTimeImmutable $maintenant, bool $executer): array
    {
        if (!in_array($champ, ['date', 'at'], true) || $mois < 1 || $mois > 120) {
            throw new RuntimeException('Paramètres de rétention invalides.');
        }
        $bilan = ['eligible' => 0, 'supprime' => 0, 'conserve_invalide' => 0];
        if (!is_dir(dirname($chemin)) || (!file_exists($chemin) && !is_link($chemin))) { return $bilan; }
        // Éviter de créer un verrou inaccessible au serveur sous un autre UID.
        if (is_link($chemin) || (function_exists('posix_geteuid') && fileowner($chemin) !== posix_geteuid())) {
            throw new RuntimeException('Journal symbolique ou compte propriétaire différent.');
        }
        $verrou = self::verrou($chemin);
        $temp = null;
        try {
            if (is_link($chemin)) { throw new RuntimeException('Journal symbolique refusé.'); }
            if (!file_exists($chemin)) { return $bilan; }
            if (!is_file($chemin) || !is_readable($chemin)) { throw new RuntimeException('Journal illisible.'); }
            // La rotation doit s'exécuter avec le compte propriétaire du fichier
            // pour ne pas empêcher le serveur de continuer à écrire après rename.
            if ($executer && (!function_exists('posix_geteuid') || fileowner($chemin) !== posix_geteuid())) {
                throw new RuntimeException('Utiliser le compte propriétaire du journal.');
            }
            $source = @fopen($chemin, 'rb');
            if ($source === false) { throw new RuntimeException('Lecture du journal impossible.'); }
            $destination = null;
            try {
                if ($executer) {
                    $temp = dirname($chemin) . '/.rotation-' . bin2hex(random_bytes(16));
                    $masque = umask(0o077);
                    try { $destination = @fopen($temp, 'xb'); } finally { umask($masque); }
                    if ($destination === false) { throw new RuntimeException('Fichier temporaire inaccessible.'); }
                    if (!chmod($temp, 0o600)) { throw new RuntimeException('Permissions du fichier temporaire impossibles.'); }
                }
                while (($ligne = fgets($source, 1048578)) !== false) {
                    if (strlen($ligne) > 1048576 || (!str_ends_with($ligne, "\n") && !feof($source))) {
                        throw new RuntimeException('Ligne trop longue : journal inchangé.');
                    }
                    $entree = json_decode($ligne, true);
                    $texte = null;
                    if (is_array($entree)) { $texte = $entree[$champ] ?? null; }
                    $date = self::date($texte);
                    $eligible = $date !== null && $date <= $maintenant && $date->modify('+' . $mois . ' months') <= $maintenant;
                    if ($eligible) { ++$bilan['eligible']; }
                    elseif ($date === null || $date > $maintenant) { ++$bilan['conserve_invalide']; }
                    if ($destination !== null && !$eligible && fwrite($destination, $ligne) !== strlen($ligne)) {
                        throw new RuntimeException('Écriture incomplète : journal inchangé.');
                    }
                }
                if (!feof($source)) { throw new RuntimeException('Lecture incomplète : journal inchangé.'); }
                if ($destination !== null && (!fflush($destination) || (function_exists('fsync') && !fsync($destination)))) {
                    throw new RuntimeException('Synchronisation impossible : journal inchangé.');
                }
            } finally {
                fclose($source);
                if (is_resource($destination)) { fclose($destination); }
            }
            if ($executer && $bilan['eligible'] > 0) {
                if (!rename($temp, $chemin)) { throw new RuntimeException('Rotation impossible : journal inchangé.'); }
                $temp = null;
                $bilan['supprime'] = $bilan['eligible'];
            }
            return $bilan;
        } finally {
            if ($temp !== null && file_exists($temp)) { @unlink($temp); }
            flock($verrou, LOCK_UN);
            fclose($verrou);
        }
    }

    private static function date(mixed $valeur): ?DateTimeImmutable
    {
        if (!is_string($valeur) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/D', $valeur) !== 1) { return null; }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $valeur, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d\TH:i:sP') !== $valeur) { return null; }
        return $date;
    }
}
