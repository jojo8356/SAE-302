<?php

declare(strict_types=1);

/**
 * MiniShop : repère des candidats au code inutilisé, sans exécuter l'application.
 * php scripts/code_mort.php             compare au référentiel existant
 * php scripts/code_mort.php --rapport   liste tous les candidats à examiner
 * php scripts/code_mort.php --json      exporte les résultats en JSON
 */
require_once __DIR__ . '/code_mort_lib.php';

$racine = dirname(__DIR__);
$arguments = array_slice($_SERVER['argv'] ?? [], 1);
if (count($arguments) > 1 || ($arguments !== [] && !in_array($arguments[0], ['--rapport', '--json'], true))) {
    fwrite(STDERR, "Usage : php scripts/code_mort.php [--rapport|--json]\n");
    exit(2);
}
$mode = $arguments[0] ?? '--check';
$sources = [];
$cibles = [];
foreach (['app', 'public', 'scripts', 'tests/php', 'tests/phpunit'] as $dossier) {
    $chemin = $racine . '/' . $dossier;
    if (!is_dir($chemin)) {
        fwrite(STDERR, "❌ Répertoire manquant : {$dossier}\n");
        exit(1);
    }
    $fichiers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($chemin, FilesystemIterator::SKIP_DOTS));
    foreach ($fichiers as $objet) {
        if (!$objet->isFile() || $objet->isLink() || strtolower($objet->getExtension()) !== 'php') {
            continue;
        }
        $relatif = str_replace('\\', '/', substr($objet->getPathname(), strlen($racine) + 1));
        $source = file_get_contents($objet->getPathname());
        if ($source === false) {
            fwrite(STDERR, "❌ Lecture impossible : {$relatif}\n");
            exit(1);
        }
        $sources[$relatif] = $source;
        if ($dossier === 'app' || $dossier === 'public') {
            $cibles[] = $relatif;
        }
    }
}
ksort($sources);
sort($cibles);
$alertes = cm_analyser($sources, $cibles);
if ($mode === '--json') {
    echo json_encode($alertes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    exit(0);
}
if ($mode === '--rapport') {
    foreach ($alertes as $alerte) {
        echo $alerte['fichier'] . ':' . $alerte['ligne'] . ' ' . $alerte['fonction'] . ' — ' . $alerte['genre'] . ' : ' . $alerte['nom'] . ' (' . $alerte['details'] . ")\n";
    }
    echo count($alertes) . " candidat(s) ; aucune suppression automatique.\n";
    exit(0);
}
$referentielPath = __DIR__ . '/code_mort_reference.json';
$brut = @file_get_contents($referentielPath);
$reference = null;
if (is_string($brut)) {
    $reference = json_decode($brut, true);
}
if (!is_array($reference) || !isset($reference['candidats']) || !is_array($reference['candidats'])) {
    fwrite(STDERR, "❌ Référentiel de code mort absent ou invalide.\n");
    exit(1);
}
foreach ($reference['candidats'] as $cle) {
    if (!is_string($cle)) {
        fwrite(STDERR, "❌ Référentiel de code mort invalide : clé non textuelle.\n");
        exit(1);
    }
}
$nouvelles = cm_nouvelles($alertes, $reference['candidats']);
if ($nouvelles !== []) {
    foreach ($nouvelles as $alerte) {
        echo '❌ Candidat nouveau : ' . $alerte['fichier'] . ':' . $alerte['ligne'] . ' ' . $alerte['fonction'] . ' — ' . $alerte['genre'] . ' : ' . $alerte['nom'] . "\n";
    }
    echo "Examiner chaque cas : un appel indirect ou un contrat peut le justifier.\n";
    exit(1);
}
$clesActuelles = array_column($alertes, 'cle');
$disparues = array_diff($reference['candidats'], $clesActuelles);
if ($disparues !== []) {
    foreach ($disparues as $cle) {
        echo '❌ Candidat résolu ou modifié : retirer du référentiel après vérification : ' . $cle . "\n";
    }
    exit(1);
}
echo '✅ CODE POTENTIELLEMENT INUTILISÉ : aucun nouveau candidat ; ' . count($alertes) . " candidat(s) existant(s) à examiner (--rapport).\n";
echo "Limites : analyse lexicale partielle, appels dynamiques et externes non prouvés, pas d'analyse JS/CSS.\n";
