<?php

declare(strict_types=1);

/**
 * MiniShop — génère le procès-verbal de recette à partir d'une campagne réelle.
 *
 *   php scripts/generer_pv.php                 # rejoue tout, archive le log, génère le PV
 *   php scripts/generer_pv.php --sans-run      # génère le PV sans rejouer (log existant le plus récent)
 *   php scripts/generer_pv.php --sortie=docs/pv-recette.md
 *
 * Le PV (`docs/pv-recette.md`, trame du §8 de `docs/02-document-tests-validation.md`)
 * est généré depuis l'exécution réelle : date, commit/SHA, branche,
 * environnement (PHP, système, version de la base), résumé de la campagne et
 * anomalies détectées dans le log. Le log complet est archivé dans
 * `logs/pv-AAAA-MM-JJ-HHMMSS.log`.
 *
 * **Aucune signature n'est apposée par ce script** : les champs auteur et
 * signature restent à compléter par un humain après relecture du PV. La
 * présence du fichier ne vaut ni approbation ni validation.
 */

$racine = dirname(__DIR__);
chdir($racine);

$sortie = $racine . '/docs/pv-recette.md';
$sansRun = false;
foreach (array_slice($_SERVER['argv'] ?? [], 1) as $argument) {
    if (str_starts_with($argument, '--sortie=')) {
        $sortie = $argument;
        $sortie = substr($argument, strlen('--sortie='));
        if (!str_starts_with($sortie, '/')) {
            $sortie = $racine . '/' . $sortie;
        }
    }
    if ($argument === '--sans-run') {
        $sansRun = true;
    }
}

$horodatage = date('Y-m-d-His');
$logChemin = $racine . '/logs/pv-' . $horodatage . '.log';
if (!is_dir($racine . '/logs')) {
    mkdir($racine . '/logs', 0777, true);
}

// ------------------------------------------------------- informations
$date = date('Y-m-d H:i:s');
$sha = 'non disponible';
$branche = 'non disponible';
$sortieGit = [];
$codeGit = 1;
@exec('git rev-parse HEAD 2>/dev/null', $sortieGit, $codeGit);
if ($codeGit === 0 && isset($sortieGit[0]) && $sortieGit[0] !== '') {
    $sha = trim($sortieGit[0]);
}
$sortieBranche = [];
$codeBranche = 1;
@exec('git branch --show-current 2>/dev/null', $sortieBranche, $codeBranche);
if ($codeBranche === 0 && isset($sortieBranche[0]) && $sortieBranche[0] !== '') {
    $branche = trim($sortieBranche[0]);
}
$versionPhp = PHP_VERSION . ' (' . PHP_SAPI . ')';
$systeme = PHP_OS_FAMILY;
$sortieUname = [];
$codeUname = 1;
@exec('uname -a 2>/dev/null', $sortieUname, $codeUname);
if ($codeUname === 0 && isset($sortieUname[0]) && $sortieUname[0] !== '') {
    $systeme .= ' — ' . trim($sortieUname[0]);
}
$versionBase = 'non disponible (serveur injoignable ou pdo_mysql absent)';
if (extension_loaded('pdo_mysql')) {
    try {
        require_once $racine . '/app/bootstrap.php';
        $config = \App\Config\Database::config();
        $pdo = \App\Config\DbConnexion::ouvrir((string) ($config['db_name'] ?? 'minishop'), $config);
        $versionBase = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    } catch (Throwable $erreur) {
        $versionBase = 'non disponible (' . $erreur->getMessage() . ')';
    }
}

// ------------------------------------------------------- campagne
$codeCampagne = 0;
$logContenu = '';
if ($sansRun) {
    $logs = glob($racine . '/logs/pv-*.log');
    if (is_array($logs) && $logs !== []) {
        sort($logs);
        $dernier = $logs[count($logs) - 1];
        $lu = file_get_contents($dernier);
        if (is_string($lu)) {
            $logContenu = $lu;
        }
        $logChemin = $dernier;
    }
    echo "PV généré sans rejouer la campagne (log réutilisé : " . basename($logChemin) . ")\n";
} else {
    echo "Exécution de la campagne complète (php scripts/run-all-tests.php)…\n";
    $sortieCampagne = [];
    exec(
        escapeshellarg(PHP_BINARY) . ' scripts/run-all-tests.php 2>&1',
        $sortieCampagne,
        $codeCampagne
    );
    $logContenu = implode("\n", $sortieCampagne) . "\n";
    file_put_contents($logChemin, $logContenu);
    echo "Log archivé : logs/" . basename($logChemin) . " (code de sortie {$codeCampagne})\n";
}

// ------------------------------------------------------- résumé et anomalies
$resume = 'non disponible';
$anomalies = [];
foreach (explode("\n", $logContenu) as $ligne) {
    if (str_starts_with(trim($ligne), 'SUITES :')) {
        $resume = trim($ligne);
    }
    if (str_contains($ligne, '❌') || str_contains($ligne, 'ÉCHEC')) {
        $anomalies[] = trim($ligne);
    }
}
if ($anomalies === []) {
    $anomalies[] = 'Aucune ligne d’échec détectée dans le log de la campagne.';
}

// ------------------------------------------------------- PV
$pv = "# Procès-verbal de recette — MiniShop\n\n";
$pv .= "Document généré automatiquement par `scripts/generer_pv.php` à partir d'une campagne\n";
$pv .= "réelle. **Ce document n'est ni signé ni approuvé** : les champs auteur et signature\n";
$pv .= "sont à compléter par un humain après relecture. Trame : `docs/02-document-tests-validation.md` §8.\n\n";
$pv .= "## Identification\n\n";
$pv .= "| Élément | Valeur |\n|---|---|\n";
$pv .= "| Date de la campagne | {$date} |\n";
$pv .= "| Commit / SHA | `{$sha}` |\n";
$pv .= "| Branche | `{$branche}` |\n";
$pv .= "| PHP | {$versionPhp} |\n";
$pv .= "| Système | {$systeme} |\n";
$pv .= "| Base de données | {$versionBase} |\n";
$pv .= "| Log complet | `logs/" . basename($logChemin) . "` |\n";
$pv .= "| Code de sortie de la campagne | {$codeCampagne} |\n\n";
$pv .= "## Résumé de la campagne\n\n";
$pv .= "{$resume}\n\n";
$pv .= "## Anomalies relevées dans le log\n\n";
foreach ($anomalies as $anomalie) {
    $pv .= "- {$anomalie}\n";
}
$pv .= "\n## Décision de recette\n\n";
$pv .= "- Critère : 0 anomalie bloquante et 0 anomalie majeure (cf. `docs/02` §1).\n";
$pv .= "- Décision : **à compléter après relecture humaine**.\n\n";
$pv .= "## Auteur et signature\n\n";
$pv .= "- Auteur de la campagne : *(à compléter)*\n";
$pv .= "- Signature : *(à compléter — aucune signature automatique n'est apposée)*\n";

$dossierSortie = dirname($sortie);
if (!is_dir($dossierSortie)) {
    mkdir($dossierSortie, 0777, true);
}
file_put_contents($sortie, $pv);
$relatif = $sortie;
if (str_starts_with($sortie, $racine . '/')) {
    $relatif = substr($sortie, strlen($racine . '/'));
}
echo "PV généré : {$relatif}\n";
echo "Rappel : auteur et signature restent à compléter par un humain.\n";
exit(0);
