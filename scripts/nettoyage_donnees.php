<?php

declare(strict_types=1);

/** Point d'entrée uniquement via db_init.php --nettoyage ; ne recrée jamais de base. */
if (PHP_SAPI !== 'cli' || !isset($argumentsBruts) || ($argumentsBruts[0] ?? '') !== '--nettoyage') {
    http_response_code(403);
    exit('Maintenance CLI uniquement via db_init.php --nettoyage');
}

use App\Config\Database;
use App\Config\Env;
use App\Service\NettoyageJournaux;
use App\Service\NettoyageSauvegardes;
use App\Service\RetentionComptes;
use App\Tools\Heure;

$args = array_slice($argumentsBruts, 1);
if ($args === ['--help']) {
    echo <<<'TXT'
Maintenance séparée, sans réinitialisation de la base :
  php scripts/db_init.php --nettoyage comptes|contact|journaux|sauvegardes
      [--executer --validation-absence-gel]
Simulation par défaut ; l'exécution exige une validation explicite du responsable.
Comptes avec commandes : non supprimés (règles métier et obligations à traiter).
Contact : 12 mois après réception ; journaux JSON : 6 mois après événement.
Sauvegardes : dossier privé dédié configuré dans NETTOYAGE_SAUVEGARDES,
30 jours minimum et au moins deux fichiers conservés. Voir docs/18-politique-conservation.md.
TXT;
    echo "\n";
    return;
}
try {
    $categorie = array_shift($args);
    if (!in_array($categorie, ['comptes', 'contact', 'journaux', 'sauvegardes'], true)) { throw new RuntimeException('Catégorie invalide : voir --nettoyage --help.'); }
    $executer = false;
    $validation = false;
    foreach ($args as $arg) {
        if ($arg === '--executer' && !$executer) { $executer = true; continue; }
        if ($arg === '--validation-absence-gel' && !$validation) { $validation = true; continue; }
        throw new RuntimeException('Option inconnue ou répétée : voir --nettoyage --help.');
    }
    if ($executer !== $validation) { throw new RuntimeException('Exécution : fournir ensemble --executer et --validation-absence-gel.'); }
    $maintenant = Heure::maintenantUtc();
    if ($categorie === 'comptes') {
        $bilan = (new RetentionComptes(Database::store()))->traiter($maintenant, $executer);
        echo "Comptes avec commandes non supprimés : règles métier et validation par dossier non implémentées.\n";
    } elseif ($categorie === 'contact') {
        $bilan = NettoyageJournaux::traiter(MINISHOP_ROOT . '/var/log/contact.jsonl', 'date', 12, $maintenant, $executer);
    } elseif ($categorie === 'journaux') {
        $config = Database::config();
        $data = rtrim(Database::dataPath($config), '/');
        $journal = $data . '/journal.jsonl';
        if ($data === MINISHOP_ROOT . '/data/minishop') { $journal = MINISHOP_ROOT . '/var/journal/db.jsonl'; }
        $bilan = NettoyageJournaux::traiter($journal, 'at', 6, $maintenant, $executer);
    } else {
        $bilan = NettoyageSauvegardes::traiter((string) Env::get('NETTOYAGE_SAUVEGARDES', ''), $maintenant, $executer);
    }
    if ($executer) { echo 'EXÉCUTION'; } else { echo 'SIMULATION'; }
    foreach ($bilan as $cle => $valeur) { echo ' ' . $cle . '=' . $valeur; }
    echo "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Échec du nettoyage : ' . $e->getMessage() . "\n");
    exit(1);
}
