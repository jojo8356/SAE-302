<?php

declare(strict_types=1);

/** Copies chiffrées à usage manuel : ne retire ni ne modifie jamais la source. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI uniquement');
}

const RACINE_PROJET = __DIR__ . '/..';
const LIMITE_SOURCE = 8 * 1024 * 1024;
const LIMITE_ARCHIVE = 12 * 1024 * 1024;

/** Répertoire privé, hors dépôt ; le contrôle des ACL et du webroot reste opérationnel. */
function repertoirePrive(string $chemin): string
{
    if (!function_exists('posix_geteuid') || $chemin === '' || $chemin[0] !== '/' || is_link($chemin)) {
        throw new RuntimeException('Répertoire absolu, non symbolique et POSIX requis.');
    }
    $reel = realpath($chemin);
    $projet = realpath(RACINE_PROJET);
    if ($reel === false || !is_dir($reel) || $projet === false || $reel === $projet || str_starts_with($reel, $projet . '/')) {
        throw new RuntimeException('Répertoire existant hors du dépôt requis.');
    }
    clearstatcache(true, $reel);
    if (fileowner($reel) !== posix_geteuid() || (fileperms($reel) & 0077) !== 0) {
        throw new RuntimeException('Répertoire réservé au propriétaire requis (0700, sans accès groupe/autres).');
    }
    return $reel;
}

/** Lecture bornée de fichier régulier, sans suivre de lien final. */
function lireFichier(string $chemin, int $limite): string
{
    if ($chemin === '' || $chemin[0] !== '/' || is_link($chemin) || !is_file($chemin) || !is_readable($chemin)) {
        throw new RuntimeException('Fichier absolu régulier, lisible et non symbolique requis.');
    }
    $f = fopen($chemin, 'rb');
    if ($f === false) { throw new RuntimeException('Lecture impossible.'); }
    try {
        $contenu = stream_get_contents($f, $limite + 1);
        if ($contenu === false || strlen($contenu) > $limite) { throw new RuntimeException('Fichier trop volumineux ou lecture impossible.'); }
        return $contenu;
    } finally { fclose($f); }
}

/** Création exclusive : pas d'écrasement, mode 0600, et nettoyage sur erreur. */
function ecrireNouveau(string $chemin, string $contenu): void
{
    $f = @fopen($chemin, 'xb');
    if ($f === false) { throw new RuntimeException('Création exclusive impossible.'); }
    try {
        if (!chmod($chemin, 0600)) { throw new RuntimeException('Protection du fichier impossible.'); }
        $position = 0;
        while ($position < strlen($contenu)) {
            $n = fwrite($f, substr($contenu, $position));
            if ($n === false || $n === 0) { throw new RuntimeException('Écriture incomplète.'); }
            $position += $n;
        }
        if (!fflush($f) || (function_exists('fsync') && !fsync($f))) { throw new RuntimeException('Synchronisation impossible.'); }
    } catch (Throwable $e) {
        fclose($f);
        @unlink($chemin);
        throw $e;
    }
    fclose($f);
}

/** Journal local obligatoire avant toute restitution ; non inviolable face au propriétaire. */
function tracerAcces(string $repertoire, string $identifiant, string $motif): void
{
    $chemin = $repertoire . '/acces.jsonl';
    if (is_link($chemin) || (file_exists($chemin) && (!is_file($chemin) || fileowner($chemin) !== posix_geteuid() || (fileperms($chemin) & 0077) !== 0))) {
        throw new RuntimeException('Journal de consultation non sûr.');
    }
    $f = @fopen($chemin, 'ab');
    if ($f === false) { throw new RuntimeException('Journal de consultation indisponible.'); }
    try {
        if (!chmod($chemin, 0600) || !flock($f, LOCK_EX)) { throw new RuntimeException('Protection ou verrou du journal impossible.'); }
        $ligne = json_encode(['date_utc' => gmdate('c'), 'uid' => posix_geteuid(), 'archive_sha256' => $identifiant,
            'motif' => $motif, 'evenement' => 'restitution_demandee'], JSON_THROW_ON_ERROR) . "\n";
        if (fwrite($f, $ligne) !== strlen($ligne) || !fflush($f) || (function_exists('fsync') && !fsync($f))) {
            throw new RuntimeException('Journalisation incomplète : aucune restitution.');
        }
        flock($f, LOCK_UN);
    } finally { fclose($f); }
}

/** Paramètres explicites, uniques, aucune option non documentée. */
function optionsCli(array $args, array $attendus, array $facultatifs = []): array
{
    $options = [];
    while ($args !== []) {
        $nom = array_shift($args);
        if (!is_string($nom) || !str_starts_with($nom, '--') || isset($options[$nom]) || !in_array($nom, array_merge($attendus, $facultatifs), true)) {
            throw new RuntimeException('Option inconnue ou répétée.');
        }
        if ($nom === '--executer') { $options[$nom] = true; continue; }
        $valeur = array_shift($args);
        if (!is_string($valeur) || $valeur === '' || str_starts_with($valeur, '--')) { throw new RuntimeException('Valeur d’option manquante.'); }
        $options[$nom] = $valeur;
    }
    foreach ($attendus as $nom) { if (!isset($options[$nom])) { throw new RuntimeException('Option obligatoire absente : ' . $nom); } }
    return $options;
}

function cleDecodee(string $chemin, int $taille): string
{
    $contenu = trim(lireFichier($chemin, 256));
    $cle = base64_decode($contenu, true);
    if ($cle === false || strlen($cle) !== $taille || base64_encode($cle) !== $contenu) { throw new RuntimeException('Clé invalide.'); }
    return $cle;
}

try {
    if (!extension_loaded('sodium') || !function_exists('posix_geteuid')) { throw new RuntimeException('Extensions PHP sodium et posix requises.'); }
    umask(0077);
    $action = $argv[1] ?? '';
    $args = array_slice($argv, 2);
    if ($action === 'generer-cles') {
        $o = optionsCli($args, ['--dossier']);
        $dir = repertoirePrive($o['--dossier']);
        $privee = $dir . '/cle-privee.txt';
        $publique = $dir . '/cle-publique.txt';
        if (file_exists($privee) || is_link($privee) || file_exists($publique) || is_link($publique)) { throw new RuntimeException('Clés déjà présentes : aucun écrasement.'); }
        $paire = sodium_crypto_box_keypair();
        ecrireNouveau($privee, base64_encode(sodium_crypto_box_secretkey($paire)) . "\n");
        try { ecrireNouveau($publique, base64_encode(sodium_crypto_box_publickey($paire)) . "\n"); }
        catch (Throwable $e) { @unlink($privee); throw $e; }
        sodium_memzero($paire);
        echo "Clés créées en mode 0600. Garder la clé privée hors du serveur applicatif.\n";
    } elseif ($action === 'sceller') {
        $o = optionsCli($args, ['--source', '--dossier', '--cle-publique', '--categorie', '--echeance', '--justification'], ['--executer']);
        $dir = repertoirePrive($o['--dossier']);
        if (!in_array($o['--categorie'], ['commandes', 'contact', 'journaux', 'sauvegarde'], true)) { throw new RuntimeException('Catégorie invalide.'); }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $o['--echeance'], new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $o['--echeance'] || $date <= new DateTimeImmutable('today', new DateTimeZone('UTC'))) { throw new RuntimeException('Échéance future valide requise (AAAA-MM-JJ).'); }
        if (!in_array($o['--justification'], ['obligation_legale', 'contentieux', 'droits', 'restauration'], true)) { throw new RuntimeException('Justification invalide.'); }
        $cle = cleDecodee($o['--cle-publique'], SODIUM_CRYPTO_BOX_PUBLICKEYBYTES);
        $source = lireFichier($o['--source'], LIMITE_SOURCE);
        if (!isset($o['--executer'])) {
            echo "SIMULATION : source lisible, aucun fichier créé et aucune source modifiée.\n";
        } else {
            $enveloppe = json_encode(['format' => 1, 'creation_utc' => gmdate('c'), 'categorie' => $o['--categorie'],
                'echeance' => $o['--echeance'], 'justification' => $o['--justification'], 'contenu_base64' => base64_encode($source)], JSON_THROW_ON_ERROR);
            $chiffre = "MSA1\n" . sodium_crypto_box_seal($enveloppe, $cle);
            $nom = bin2hex(random_bytes(16)) . '.msa';
            ecrireNouveau($dir . '/' . $nom, $chiffre);
            sodium_memzero($enveloppe);
            echo "Copie chiffrée créée : " . $nom . ". Source inchangée ; ce n’est pas un transfert.\n";
        }
        sodium_memzero($source);
    } elseif ($action === 'consulter') {
        $o = optionsCli($args, ['--archive', '--cle-privee', '--dossier-sortie', '--motif']);
        if (!in_array($o['--motif'], ['droits', 'contentieux', 'controle', 'restauration'], true)) { throw new RuntimeException('Motif de consultation invalide.'); }
        $dir = repertoirePrive($o['--dossier-sortie']);
        $archiveDir = repertoirePrive(dirname($o['--archive']));
        $cleDir = repertoirePrive(dirname($o['--cle-privee']));
        if (is_link($o['--archive']) || is_link($o['--cle-privee']) || !str_starts_with($o['--archive'], '/') || !str_starts_with($o['--cle-privee'], '/')) { throw new RuntimeException('Chemins absolus sans lien requis.'); }
        $priveePath = $cleDir . '/' . basename($o['--cle-privee']);
        clearstatcache(true, $priveePath);
        if (fileowner($priveePath) !== posix_geteuid() || (fileperms($priveePath) & 0077) !== 0) { throw new RuntimeException('Clé privée non protégée (0600 requis).'); }
        $secret = cleDecodee($priveePath, SODIUM_CRYPTO_BOX_SECRETKEYBYTES);
        $paire = sodium_crypto_box_keypair_from_secretkey_and_publickey($secret, sodium_crypto_box_publickey_from_secretkey($secret));
        $chiffre = lireFichier($archiveDir . '/' . basename($o['--archive']), LIMITE_ARCHIVE);
        if (!str_starts_with($chiffre, "MSA1\n")) { throw new RuntimeException('Format d’archive invalide.'); }
        $texte = sodium_crypto_box_seal_open(substr($chiffre, 5), $paire);
        sodium_memzero($secret); sodium_memzero($paire);
        if ($texte === false) { throw new RuntimeException('Déchiffrement ou intégrité impossible.'); }
        $document = json_decode($texte, true, 10, JSON_THROW_ON_ERROR);
        if (!is_array($document) || ($document['format'] ?? null) !== 1 || !is_string($document['contenu_base64'] ?? null)
            || !in_array($document['categorie'] ?? null, ['commandes', 'contact', 'journaux', 'sauvegarde'], true)
            || !in_array($document['justification'] ?? null, ['obligation_legale', 'contentieux', 'droits', 'restauration'], true)
            || !is_string($document['echeance'] ?? null) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $document['echeance']) !== 1) {
            throw new RuntimeException('Contenu invalide.');
        }
        $contenu = base64_decode($document['contenu_base64'], true);
        if ($contenu === false || strlen($contenu) > LIMITE_SOURCE) { throw new RuntimeException('Contenu invalide ou trop volumineux.'); }
        tracerAcces($dir, hash('sha256', $chiffre), $o['--motif']);
        $nom = 'restitution-' . bin2hex(random_bytes(16)) . '.bin';
        ecrireNouveau($dir . '/' . $nom, $contenu);
        sodium_memzero($contenu); sodium_memzero($texte);
        echo "Restitution EN CLAIR créée : " . $nom . " ; journal local : acces.jsonl.\n";
        echo 'Catégorie : ' . ($document['categorie'] ?? '?') . ' ; échéance : ' . ($document['echeance'] ?? '?') . ' ; justification : ' . ($document['justification'] ?? '?') . "\n";
    } else {
        throw new RuntimeException('Usage : archive_restreinte.php generer-cles|sceller|consulter ; voir docs/18-politique-conservation.md.');
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Échec : ' . $e->getMessage() . "\n");
    exit(1);
}
