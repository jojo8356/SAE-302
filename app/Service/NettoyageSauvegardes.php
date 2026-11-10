<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Rotation manuelle d'un dossier dédié de fichiers achevés et identifiés. */
final class NettoyageSauvegardes
{
    /** @return array{eligible:int,supprime:int,conserve:int} */
    public static function traiter(string $dossier, DateTimeImmutable $maintenant, bool $executer): array
    {
        if (!function_exists('posix_geteuid') || $dossier === '' || $dossier[0] !== '/' || is_link($dossier)) {
            throw new RuntimeException('Dossier absolu et POSIX requis.');
        }
        $dir = realpath($dossier);
        $racine = realpath(dirname(__DIR__, 2));
        if ($dir === false || !is_dir($dir) || $racine === false || $dir === $racine || str_starts_with($dir, $racine . '/')) {
            throw new RuntimeException('Dossier existant hors du dépôt requis.');
        }
        clearstatcache(true, $dir);
        if (fileowner($dir) !== posix_geteuid() || (fileperms($dir) & 0077) !== 0) { throw new RuntimeException('Dossier privé appartenant au compte courant requis (0700).'); }
        $marqueur = $dir . '/.minishop-sauvegardes';
        if (is_link($marqueur) || !is_file($marqueur) || file_get_contents($marqueur) !== "MINISHOP_BACKUPS_V1\n") {
            throw new RuntimeException('Marqueur de dossier dédié absent ou invalide.');
        }
        $verrouPath = $dir . '/.minishop-retention.lock';
        if (is_link($verrouPath)) { throw new RuntimeException('Verrou symbolique refusé.'); }
        $masque = umask(0o077);
        try { $verrou = @fopen($verrouPath, 'c+b'); } finally { umask($masque); }
        if ($verrou === false) { throw new RuntimeException('Verrou inaccessible.'); }
        try {
            if (!flock($verrou, LOCK_EX)) { throw new RuntimeException('Verrouillage impossible.'); }
            $liste = scandir($dir);
            if ($liste === false) { throw new RuntimeException('Lecture du dossier impossible.'); }
            $fichiers = [];
            foreach ($liste as $nom) {
                if (preg_match('/^minishop-(\d{8}T\d{6}Z)-[a-f0-9]{32}\.backup$/D', $nom, $m) !== 1) { continue; }
                $chemin = $dir . '/' . $nom;
                $stat = lstat($chemin);
                $date = DateTimeImmutable::createFromFormat('!Ymd\THis\Z', $m[1], new DateTimeZone('UTC'));
                if ($stat === false || is_link($chemin) || !is_file($chemin) || ($stat['nlink'] ?? 0) !== 1 || $date === false || $date->format('Ymd\THis\Z') !== $m[1]) { continue; }
                $fichiers[] = ['nom' => $nom, 'date' => $date, 'mtime' => (int) $stat['mtime'], 'ino' => $stat['ino'], 'dev' => $stat['dev']];
            }
            usort($fichiers, static fn (array $a, array $b): int => strcmp($b['nom'], $a['nom']));
            $bilan = ['eligible' => 0, 'supprime' => 0, 'conserve' => 0];
            foreach ($fichiers as $index => $f) {
                // Préserver au moins deux sauvegardes reconnues même anciennes.
                $eligible = $index >= 2 && $f['date']->modify('+30 days') <= $maintenant
                    && $f['mtime'] > 0 && $f['mtime'] <= $maintenant->modify('-30 days')->getTimestamp();
                if (!$eligible) { ++$bilan['conserve']; continue; }
                ++$bilan['eligible'];
                if ($executer) {
                    $path = $dir . '/' . $f['nom'];
                    clearstatcache(true, $path);
                    $stat = lstat($path);
                    if ($stat === false || is_link($path) || !is_file($path) || $stat['ino'] !== $f['ino'] || $stat['dev'] !== $f['dev'] || $stat['nlink'] !== 1 || (int) $stat['mtime'] !== $f['mtime']) {
                        throw new RuntimeException('Sauvegarde modifiée pendant le traitement : arrêt.');
                    }
                    if (!unlink($path)) { throw new RuntimeException('Suppression de sauvegarde impossible : arrêt.'); }
                    ++$bilan['supprime'];
                }
            }
            return $bilan;
        } finally { flock($verrou, LOCK_UN); fclose($verrou); }
    }
}
