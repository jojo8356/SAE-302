<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Service\NettoyageJournaux;
use App\Service\NettoyageSauvegardes;

$dir = sys_get_temp_dir() . '/minishop-nettoyage-' . bin2hex(random_bytes(8));
mkdir($dir, 0700);
$maintenant = new DateTimeImmutable('2026-10-15T12:00:00+00:00');
$checks = 0; $echecs = 0;
$check = static function (string $nom, bool $ok) use (&$checks, &$echecs): void {
    ++$checks;
    if (!$ok) { ++$echecs; echo "❌ $nom\n"; }
};
$journal = $dir . '/contact.jsonl';
$ancien = '{"date":"2024-01-01T00:00:00+00:00","message":"ancien"}' . "\n";
$recent = '{"date":"2026-09-01T00:00:00+00:00","message":"récent"}' . "\n";
$invalide = '{"date":"2024-02-31T00:00:00+00:00","message":"à examiner"}' . "\n";
$futur = '{"date":"2099-01-01T00:00:00+00:00"}' . "\n";
$corrompu = "{invalide}\n";
$original = $ancien . $recent . $invalide . $futur . $corrompu;
file_put_contents($journal, $original);
$simu = NettoyageJournaux::traiter($journal, 'date', 12, $maintenant, false);
$check('simulation et dates invalides', $simu === ['eligible' => 1, 'supprime' => 0, 'conserve_invalide' => 3]);
$check('simulation conserve les octets', file_get_contents($journal) === $original);
if (function_exists('posix_geteuid')) {
    $bilan = NettoyageJournaux::traiter($journal, 'date', 12, $maintenant, true);
    $check('une seule ancienne entrée supprimée', $bilan['supprime'] === 1 && file_get_contents($journal) === $recent . $invalide . $futur . $corrompu);
    $check('rotation idempotente', NettoyageJournaux::traiter($journal, 'date', 12, $maintenant, true)['supprime'] === 0);
}
$check('écriture via le verrou stable', NettoyageJournaux::ajouter($journal, $recent) && str_ends_with((string) file_get_contents($journal), $recent));
$long = $dir . '/long.jsonl';
$gros = str_repeat('x', 1048577) . "\n";
file_put_contents($long, $gros);
$refuse = false;
try { NettoyageJournaux::traiter($long, 'date', 12, $maintenant, function_exists('posix_geteuid')); } catch (RuntimeException) { $refuse = true; }
$check('ligne trop longue : refus sans modification', $refuse && file_get_contents($long) === $gros);
$link = $dir . '/lien';
if (function_exists('symlink') && @symlink($journal, $link)) {
    $refuse = false;
    try { NettoyageJournaux::traiter($link, 'date', 12, $maintenant, false); } catch (RuntimeException) { $refuse = true; }
    $check('lien symbolique refusé', $refuse);
}
if (function_exists('posix_geteuid')) {
    file_put_contents($dir . '/.minishop-sauvegardes', "MINISHOP_BACKUPS_V1\n");
    $noms = [];
    foreach (['20200101T000000Z', '20210101T000000Z', '20220101T000000Z'] as $date) {
        $nom = 'minishop-' . $date . '-' . str_repeat('a', 32) . '.backup';
        $noms[] = $nom;
        file_put_contents($dir . '/' . $nom, 'backup');
        touch($dir . '/' . $nom, strtotime('2022-01-01 UTC'));
    }
    $s = NettoyageSauvegardes::traiter($dir, $maintenant, false);
    $check('simulation sauvegardes, deux préservées', $s['eligible'] === 1 && $s['supprime'] === 0 && file_exists($dir . '/' . $noms[0]));
    $s = NettoyageSauvegardes::traiter($dir, $maintenant, true);
    $check('plus ancienne sauvegarde supprimée', $s['supprime'] === 1 && !file_exists($dir . '/' . $noms[0]));
    $check('deux sauvegardes préservées', file_exists($dir . '/' . $noms[1]) && file_exists($dir . '/' . $noms[2]));
    $check('fichiers non reconnus ignorés', file_exists($journal));
    chmod($dir, 0755);
    $refuse = false;
    try { NettoyageSauvegardes::traiter($dir, $maintenant, true); } catch (RuntimeException) { $refuse = true; }
    $check('dossier non privé refusé', $refuse);
    chmod($dir, 0700);
}
$fichiers = scandir($dir);
if ($fichiers === false) { $fichiers = []; }
foreach ($fichiers as $fichier) {
    if ($fichier !== '.' && $fichier !== '..') { @unlink($dir . '/' . $fichier); }
}
@rmdir($dir);
if ($echecs > 0) { echo "❌ Nettoyage : $echecs échec(s) sur $checks\n"; exit(1); }
echo "✅ Nettoyage : $checks vérifications réussies";
if (!function_exists('posix_geteuid')) { echo ' ; exécution POSIX non jouée'; }
echo "\n";
