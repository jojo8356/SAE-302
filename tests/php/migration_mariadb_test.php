<?php

declare(strict_types=1);

/** Migration réelle sur MariaDB : une base aléatoire créée et détruite ici seulement. */
require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Tools\SqlLoader;

if (getenv('MINISHOP_MARIADB_INSTANCE_JETABLE') !== '1') {
    fwrite(STDERR, "NON JOUÉ : MINISHOP_MARIADB_INSTANCE_JETABLE=1 requis sur serveur jetable.\n");
    exit(2);
}
if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "NON JOUÉ : extension pdo_mysql absente.\n");
    exit(2);
}
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$user = getenv('DB_USER');
if (!is_string($host) || $host === '') { $host = '127.0.0.1'; }
if (!is_string($port) || $port === '') { $port = '3306'; }
if (!is_string($user) || $user === '') { $user = 'root'; }
$password = getenv('DB_PASSWORD');
if (!is_string($password)) {
    $password = '';
}
$pdo = new PDO('mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4', $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
if (stripos($version, 'mariadb') === false) {
    throw new RuntimeException('Ce test doit viser MariaDB, pas un autre SGBD : ' . $version);
}
$base = 'minishop_migration_' . bin2hex(random_bytes(8));
$cree = false;
try {
    // CREATE sans IF NOT EXISTS : ne jamais utiliser ni supprimer une base préexistante.
    $pdo->exec('CREATE DATABASE `' . $base . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $cree = true;
    $pdo->exec('USE `' . $base . '`');
    $racine = dirname(__DIR__, 2);
    $schema = (string) file_get_contents($racine . '/sql/01_minishop_schema.sql');
    $ancien = preg_replace('/^\s*montant_avant_annulation\s+DECIMAL\(10,2\).*?,\s*$/m', '', $schema, 1, $remplacements);
    if (!is_string($ancien) || $remplacements !== 1) {
        throw new RuntimeException('Impossible de construire le schéma antérieur de la fixture.');
    }
    foreach (SqlLoader::decouper($ancien) as $instruction) {
        if (preg_match('/^(DROP|CREATE)\s+DATABASE\b/i', $instruction) === 1 || preg_match('/^USE\s+/i', $instruction) === 1) {
            continue;
        }
        $pdo->exec($instruction);
    }
    // Données représentatives : commande encore active et commande historique
    // déjà annulée. Le montant d'origine de cette dernière est inconnu.
    $pdo->exec("INSERT INTO commande (numero,id_client,adresse_livraison,statut,montant_total,frais_port) VALUES
        ('TEST-MIG-1',1,'Adresse test','PAYEE',63.70,4.90),('TEST-MIG-2',1,'Adresse ancienne','ANNULEE',0,0)");
    $pdo->exec('INSERT INTO ligne_commande (id_commande,id_produit,quantite,prix_unitaire,total_ligne) VALUES (1,8,1,58.80,58.80),(2,8,1,58.80,58.80)');
    $pdo->exec("INSERT INTO order_status_history (order_id,old_status,new_status,changed_by_role) VALUES (2,'PAYEE','ANNULEE','CLIENT')");
    $pdo->exec('UPDATE produit SET stock=21 WHERE id_produit=8');
    $requeteCommande = 'SELECT id_commande,numero,id_client,adresse_livraison,statut,montant_total,frais_port,commentaire,date_commande,date_modification FROM commande ORDER BY id_commande';
    $requetes = [$requeteCommande, 'SELECT * FROM ligne_commande ORDER BY id_ligne', 'SELECT * FROM order_status_history ORDER BY id', 'SELECT * FROM produit ORDER BY id_produit', 'SELECT * FROM client ORDER BY id_client'];
    $avant = [];
    foreach ($requetes as $requete) {
        $avant[] = $pdo->query($requete)->fetchAll(PDO::FETCH_ASSOC);
    }
    $loader = new SqlLoader($pdo);
    $migration = $racine . '/sql/migrations/001_montant_avant_annulation_mariadb.sql';
    $loader->chargerFichier($migration);
    $loader->chargerFichier($migration); // répétition sans destruction
    foreach ($requetes as $i => $requete) {
        if ($avant[$i] !== $pdo->query($requete)->fetchAll(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Données modifiées par la migration : ' . $requete);
        }
    }
    $colonne = $pdo->query("SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='commande' AND COLUMN_NAME='montant_avant_annulation'")->fetch(PDO::FETCH_ASSOC);
    if ($colonne === false || strtolower((string) $colonne['COLUMN_TYPE']) !== 'decimal(10,2)' || $colonne['IS_NULLABLE'] !== 'YES') {
        throw new RuntimeException('La définition de la colonne migrée est incorrecte.');
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM commande WHERE montant_avant_annulation IS NOT NULL')->fetchColumn() !== 0) {
        throw new RuntimeException('Un montant historique a été inventé.');
    }
    echo "✅ Migration répétée, schéma et données existantes préservés ($version)\n";

    // Sur la base jetable uniquement, tester la version courante des routines.
    $loader->chargerFichiers([$racine . '/sql/02_minishop_procedures.sql', $racine . '/sql/03_minishop_triggers.sql']);
    try {
        $pdo->exec('CALL sp_cancel_order(1, 9999, @code)');
        throw new RuntimeException('Une annulation non autorisée a été acceptée.');
    } catch (PDOException $erreur) {
        if (!str_contains($erreur->getMessage(), 'ACCES_NON_AUTORISE')) {
            throw $erreur;
        }
    }
    if ((int) $pdo->query('SELECT stock FROM produit WHERE id_produit=8')->fetchColumn() !== 21 || $pdo->query('SELECT statut FROM commande WHERE id_commande=1')->fetchColumn() !== 'PAYEE') {
        throw new RuntimeException('La tentative refusée a modifié les données.');
    }
    $pdo->exec('CALL sp_cancel_order(1, 1, @code)');
    $commande = $pdo->query('SELECT statut,montant_total,frais_port,montant_avant_annulation FROM commande WHERE id_commande=1')->fetch(PDO::FETCH_ASSOC);
    if ($commande['statut'] !== 'ANNULEE' || (float) $commande['montant_total'] !== 0.0 || (float) $commande['frais_port'] !== 0.0 || (float) $commande['montant_avant_annulation'] !== 63.70 || (int) $pdo->query('SELECT stock FROM produit WHERE id_produit=8')->fetchColumn() !== 22 || (int) $pdo->query('SELECT COUNT(*) FROM ligne_commande WHERE id_commande=1')->fetchColumn() !== 1 || (int) $pdo->query("SELECT COUNT(*) FROM order_status_history WHERE order_id=1 AND new_status='ANNULEE'")->fetchColumn() !== 1) {
        throw new RuntimeException('L’annulation après migration ne respecte pas les invariants.');
    }
    try {
        $pdo->exec('CALL sp_cancel_order(1, 1, @code)');
        throw new RuntimeException('Une seconde annulation a été acceptée.');
    } catch (PDOException $erreur) {
        if (!str_contains($erreur->getMessage(), 'COMMANDE_NON_ANNULABLE')) {
            throw $erreur;
        }
    }
    if ((int) $pdo->query('SELECT stock FROM produit WHERE id_produit=8')->fetchColumn() !== 22) {
        throw new RuntimeException('Le stock a été restitué deux fois.');
    }
    echo "✅ Annulation autorisée, refus, rollback et historique vérifiés sur MariaDB\n";
} finally {
    if ($cree) {
        $pdo->exec('DROP DATABASE `' . $base . '`');
    }
}
