<?php

declare(strict_types=1);

/**
 * MiniShop — intégration SQL : rejoue les 29 scénarios sur une base RÉELLE.
 *
 *   php tests/php/sql_integration.php            # crée minishop_test, joue, supprime
 *   php tests/php/sql_integration.php --garder   # laisse la base pour inspection
 *   php tests/php/sql_integration.php --reinitialiser-base-existante
 *       # DESTRUCTIF : autorise explicitement la suppression d’une base de test existante
 *
 * Les autres suites tournent sur le moteur JSON : elles valident la logique,
 * jamais le SQL. Ce test est le seul qui exécute réellement les 17 procédures,
 * la fonction, les 16 déclencheurs et les 3 vues — c'est-à-dire la partie que
 * le correcteur SQL du PDO ne voit pas.
 *
 * **Isolation.** Le harnais `tests/sql/run_tests.sh` réinitialise l'état via
 * `fixture.sql` avant chaque scénario : le lancer sur la base de développement
 * détruirait les données de démonstration. Cette enveloppe crée donc une base
 * dédiée (`minishop_test` par défaut), y charge `sql/01 → 04`, joue la
 * campagne, puis la supprime. Une base déjà existante est refusée par défaut.
 * Le nom configuré pour l’application est toujours refusé, même avec l’option
 * de réinitialisation. Utiliser exclusivement un serveur de test isolé : une
 * autre application peut utiliser une base dont le nom diffère de DB_NAME.
 *
 * **Dégradation.** Sans serveur joignable (ou sans client `mysql`/`mariadb`),
 * le test annonce NON JOUÉ et sort avec le code 2 : `scripts/run-all-tests.php`
 * le distingue d'un échec.
 */

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Config\Database;
use App\Config\DbConnexion;
use App\Tools\DbInstaller;

$racine = dirname(__DIR__, 2);
$garder = in_array('--garder', $_SERVER['argv'] ?? [], true);
$reinitialiser = in_array('--reinitialiser-base-existante', $_SERVER['argv'] ?? [], true);
$config = Database::config();
$baseDemandee = getenv('MINISHOP_TEST_DB');
$base = 'minishop_test';
if (is_string($baseDemandee) && $baseDemandee !== '') {
    $base = $baseDemandee;
}

echo "MiniShop — intégration SQL (base dédiée « " . $base . " »)\n";
echo str_repeat('-', 60) . "\n";

// Ne jamais supprimer la base de l'application, même en mode explicite.
// Comparaison insensible à la casse : prudence sur les serveurs où les noms
// de bases sont eux-mêmes insensibles à la casse.
$baseApplication = (string) ($config['db_name'] ?? 'minishop');
if (strcasecmp($base, $baseApplication) === 0) {
    echo "❌ Refus : la base de test correspond à DB_NAME ; aucune action SQL.\n";
    exit(1);
}

// ------------------------------------------------------------- prérequis
if (!extension_loaded('pdo_mysql')) {
    echo "⏭  NON JOUÉ : l'extension pdo_mysql n'est pas chargée.\n";
    echo "    Debian/Ubuntu : sudo apt install php" . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . "-mysql\n";
    exit(2);
}

$client = null;
foreach (['mariadb', 'mysql'] as $candidat) {
    $sortie = [];
    $code = 1;
    @exec('command -v ' . $candidat . ' 2>/dev/null', $sortie, $code);
    if ($code === 0 && $sortie !== []) {
        $client = $candidat;
        break;
    }
}
if ($client === null) {
    echo "⏭  NON JOUÉ : aucun client « mariadb » ou « mysql » dans le PATH\n";
    echo "    (le harnais tests/sql/run_tests.sh s'appuie sur le client en ligne de commande).\n";
    exit(2);
}

$installateur = new DbInstaller(
    (string) ($config['db_host'] ?? '127.0.0.1'),
    (string) ($config['db_port'] ?? '3306'),
    $base,
    (string) ($config['db_user'] ?? 'root'),
    (string) ($config['db_pass'] ?? ''),
    (string) ($config['db_socket'] ?? ''),
);

if (!$installateur->serveurJoignable()) {
    echo "⏭  NON JOUÉ : serveur MySQL injoignable — " . $installateur->derniereErreur() . "\n";
    exit(2);
}

// ------------------------------------------------------- base dédiée
echo "1. Base de test isolée\n";
try {
    if ($reinitialiser) {
        echo "   ⚠ Réinitialisation explicitement demandée : suppression de « " . $base . " » si elle existe.\n";
        $installateur->supprimerBase();
    }
    // Sans IF NOT EXISTS : si une base existe ou apparaît entre-temps, le
    // serveur échoue au lieu de la charger et de la supprimer par accident.
    $installateur->creerBaseNouvelle();
    echo "   ✓ « " . $base . " » créée (utf8mb4_unicode_ci)\n";
} catch (Throwable $erreur) {
    $message = $erreur->getMessage();
    echo "   ❌ création impossible : " . $message . "\n";
    echo "   Si la base existe déjà, elle n'a pas été supprimée par défaut.\n";
    echo "   Sur un serveur isolé uniquement, après vérification des données,\n";
    echo "   --reinitialiser-base-existante autorise explicitement sa suppression.\n";

    // 1044 : pas le droit de créer la base ; 1142 : pas le droit d'y écrire.
    // Ce n'est pas un bug du test : le compte applicatif n'a volontairement de
    // droits que sur sa base. Le remède est une commande, pas un contournement.
    $refus = str_contains($message, '1044')
        || str_contains($message, '1142')
        || str_contains($message, 'Access denied');
    if ($refus) {
        echo "\n   Le compte « " . (string) ($config['db_user'] ?? '?') . " » n'a pas de droits sur"
            . " « " . $base . " » — c'est voulu (moindre privilège).\n";
        echo "   Créez la base de test une fois pour toutes, puis relancez :\n\n";
        echo "       php scripts/db_init.php --test-db\n\n";
        echo "   Attention : cette commande crée une base existante ; le test refuse\n";
        echo "   ensuite de la réinitialiser sans option explicite destructive.\n";
        echo "   Ne poursuivre que sur un serveur de test jetable, après vérification.\n";
    }
    exit(1);
}

echo "\n2. Chargement du schéma, des routines et du jeu de démonstration\n";
try {
    $resultats = $installateur->charger(DbInstaller::scripts(true), static function (string $fichier, int $nombre): void {
        printf("   %-32s %3d instruction(s)\n", $fichier, $nombre);
    });
    if ($resultats === []) {
        echo "   ❌ aucun script chargé\n";
        exit(1);
    }
} catch (Throwable $erreur) {
    echo "   ❌ chargement impossible : " . $erreur->getMessage() . "\n";
    exit(1);
}

// Vérification sur table TEMPORAIRE et session dédiée : aucun changement global
// du serveur, aucune donnée applicative modifiée. Jouée sur MySQL et MariaDB.
echo "\n2 bis. Fuseau de session SQL et dates automatiques\n";
$echecsFuseau = 0;
try {
    $pdoFuseau = DbConnexion::ouvrir($base, $config);
    if ($pdoFuseau->query('SELECT @@session.time_zone')->fetchColumn() !== '+00:00') {
        throw new RuntimeException('La connexion centrale ne fixe pas UTC.');
    }
    // Simuler une connexion extérieure non UTC avant de l'injecter dans SqlStore.
    $pdoFuseau->exec("SET SESSION time_zone = '+05:30'");
    $temoin = $pdoFuseau->query('SELECT @@session.time_zone, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), CURRENT_TIMESTAMP())')->fetch(PDO::FETCH_NUM);
    if ($temoin === false || $temoin[0] !== '+05:30' || (int) $temoin[1] !== 19800) {
        throw new RuntimeException('Le fuseau hostile de test ne fonctionne pas.');
    }
    $storeFuseau = new \App\Model\Data\SqlStore($pdoFuseau);
    $storeFuseau->pdo();
    if ($pdoFuseau->query('SELECT @@session.time_zone')->fetchColumn() !== '+00:00') {
        throw new RuntimeException('La connexion injectée ne revient pas en UTC.');
    }
    $pdoFuseau->exec('CREATE TEMPORARY TABLE test_fuseau_utc (id INT NOT NULL PRIMARY KEY, cree DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, modifie DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)');
    $cas = [
        ['2026-01-15 18:15:00', '15/01/2026 19h15'],
        ['2026-07-15 17:15:00', '15/07/2026 19h15'],
    ];
    foreach ($cas as $i => [$utc, $paris]) {
        $instant = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        $pdoFuseau->exec('SET timestamp = ' . $instant->getTimestamp());
        if ($i === 0) {
            $pdoFuseau->exec('INSERT INTO test_fuseau_utc (id) VALUES (1)');
            $colonne = 'cree';
        } else {
            $pdoFuseau->exec('UPDATE test_fuseau_utc SET id = 2 WHERE id = 1');
            $colonne = 'modifie';
        }
        $stocke = $pdoFuseau->query('SELECT ' . $colonne . ' FROM test_fuseau_utc')->fetchColumn();
        if ($stocke !== $utc || date_fr($stocke, true) !== $paris) {
            throw new RuntimeException('Date SQL ou affichage Paris incorrect pour le cas ' . $i);
        }
    }
    $pdoFuseau->exec('SET timestamp = 0');
    $pdoFuseau->exec('DROP TEMPORARY TABLE test_fuseau_utc');
    echo "   ✅ session UTC, connexion injectée hostile, INSERT et UPDATE en hiver et été\n";
} catch (Throwable $erreur) {
    $echecsFuseau = 1;
    echo '   ❌ fuseau SQL : ' . $erreur->getMessage() . "\n";
}

// Contrat de pagination du repository, sur la même base dédiée et en lecture seule.
// Jamais sur la base applicative. Les scénarios sont partagés avec le pilote JSON.
echo "\n3. Catalogue : ruptures en dernier (pilote SQL)\n";
$echecsCatalogue = 0;
try {
    require_once __DIR__ . '/catalogue_stock_cases.php';
    $stockSql = new \App\Model\Data\SqlStore(DbConnexion::ouvrir($base, $config));
    $resultatCatalogue = verifierCatalogueStock($stockSql);
    $echecsCatalogue = $resultatCatalogue['echecs'];
    if ($echecsCatalogue === 0) {
        echo '   ✅ ' . $resultatCatalogue['tests'] . " scénarios de catalogue SQL\n";
    } else {
        echo '   ❌ catalogue SQL : ' . $echecsCatalogue . " échec(s)\n";
    }
} catch (Throwable $erreur) {
    $echecsCatalogue = 1;
    echo '   ❌ catalogue SQL : ' . $erreur->getMessage() . "\n";
}

// Même contrat sur MariaDB : transactions annulées et données uniquement sur
// la base de test déjà créée ; vérifier aussi la procédure SQL indépendante.
$echecsNouveautes = 0;
echo "\n4. Derniers ajouts (pilote SQL et procédure)\n";
try {
    require_once __DIR__ . '/nouveautes_cases.php';
    $pdoNouveautes = DbConnexion::ouvrir($base, $config);
    $resultatNouveautes = verifierNouveautes(new \App\Model\Data\SqlStore($pdoNouveautes));
    $echecsNouveautes = $resultatNouveautes['echecs'];
    $attenduNouveautes = $pdoNouveautes->query(
        'SELECT id_produit FROM produit WHERE visible = 1 ORDER BY date_creation DESC, id_produit DESC LIMIT 4'
    )->fetchAll(PDO::FETCH_COLUMN);
    $appel = $pdoNouveautes->query("CALL sp_search_products('', NULL, NULL, NULL, 0, 'nouveaute', 1, 4, 0)");
    $obtenuNouveautes = array_column($appel->fetchAll(PDO::FETCH_ASSOC), 'id_produit');
    while ($appel->nextRowset()) {
        if ($appel->columnCount() > 0) {
            $appel->fetchAll();
        }
    }
    $appel->closeCursor();
    if (array_map('intval', $attenduNouveautes) !== array_map('intval', $obtenuNouveautes)) {
        ++$echecsNouveautes;
        echo "   ❌ procédure SQL : ordre des nouveautés incorrect\n";
    } else {
        echo "   ✅ procédure SQL : ordre des nouveautés\n";
    }
    if ($echecsNouveautes === 0) {
        echo '   ✅ ' . $resultatNouveautes['tests'] . " vérifications du dépôt SQL\n";
    } else {
        echo '   ❌ nouveautés SQL : ' . $echecsNouveautes . " échec(s)\n";
    }
} catch (Throwable $erreur) {
    $echecsNouveautes = 1;
    echo '   ❌ nouveautés SQL : ' . $erreur->getMessage() . "\n";
}

// ------------------------------------------------------- campagne SQL
echo "\n5. Campagne de scénarios SQL (tests/sql/run_tests.sh)\n";
$harnais = $racine . '/tests/sql/run_tests.sh';
if (!is_file($harnais)) {
    echo "   ❌ harnais absent : " . $harnais . "\n";
    exit(1);
}

$variables = [
    'DB_TEST' => $base,
    'DB_HOST' => (string) ($config['db_host'] ?? '127.0.0.1'),
    'DB_PORT' => (string) ($config['db_port'] ?? '3306'),
    'DB_USER' => (string) ($config['db_user'] ?? 'root'),
    'DB_PASS' => (string) ($config['db_pass'] ?? ''),
];
$prefixe = '';
foreach ($variables as $cle => $valeur) {
    $prefixe .= $cle . '=' . escapeshellarg($valeur) . ' ';
}

$sortie = [];
$code = 0;
@exec($prefixe . 'bash ' . escapeshellarg($harnais) . ' 2>&1', $sortie, $code);
echo implode("\n", $sortie) . "\n";

// Étape 6 placée APRÈS run_tests.sh pour ne jamais altérer le jeu d'essai initial attendu par l'étape 5.
echo "\n6. Annulation (client/admin/expédiée), bornes UTC et rétention des comptes (pilote SQL)\n";
$echecsParcoursSql = 0;
try {
    $pdoParcours = DbConnexion::ouvrir($base, $config);
    $storeParcours = new \App\Model\Data\SqlStore($pdoParcours);
    $repoCmd = \App\Repository\CommandeRepository::for($storeParcours);

    $ligneProduit = $pdoParcours->query('SELECT id_produit, stock FROM produit WHERE visible = 1 AND stock >= 6 ORDER BY id_produit ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!is_array($ligneProduit)) {
        throw new RuntimeException('Aucun produit avec stock >= 6 sur la base de test.');
    }
    $idProdTest = (int) $ligneProduit['id_produit'];
    $stockReference = (int) $ligneProduit['stock'];

    // 6a. sp_cancel_order (client) restitue le stock et solde les montants.
    $cmdClient = $repoCmd->createOrderFromBasket(1, '10 rue de la République, 06000 Nice', [['id_produit' => $idProdTest, 'quantite' => 2]], true);
    $idCmdClient = (int) $cmdClient['id_commande'];
    $pdoParcours->exec("CALL sp_cancel_order($idCmdClient, 1, @code_cancel_sql)");
    $codeCancelSql = (string) $pdoParcours->query('SELECT @code_cancel_sql')->fetchColumn();
    $etatCmdClient = $repoCmd->find($idCmdClient);
    $stockApresClient = (int) $pdoParcours->query('SELECT stock FROM produit WHERE id_produit = ' . $idProdTest)->fetchColumn();
    if ($codeCancelSql !== 'OK' || $etatCmdClient === null || $etatCmdClient['statut'] !== 'ANNULEE'
        || (float) $etatCmdClient['montant_total'] !== 0.0 || $stockApresClient !== $stockReference) {
        throw new RuntimeException('sp_cancel_order incorrect : code=' . $codeCancelSql . ', stock=' . $stockApresClient);
    }

    // 6b. Garde ANNULATION_VIA_PARCOURS_DEDIE sur sp_update_order_status + annulation admin PAYEE (stock restitué).
    $cmdAdminPayee = $repoCmd->createOrderFromBasket(1, '12 avenue Jean Médecin, 06000 Nice', [['id_produit' => $idProdTest, 'quantite' => 1]], true);
    $idCmdPayee = (int) $cmdAdminPayee['id_commande'];
    $gardeActive = false;
    try {
        $pdoParcours->exec("CALL sp_update_order_status($idCmdPayee, 'ANNULEE', 'direct interdit', 1, 'ADMIN', @code_interdit)");
    } catch (PDOException $erreurSql) {
        if (str_contains($erreurSql->getMessage(), 'ANNULATION_VIA_PARCOURS_DEDIE')) {
            $gardeActive = true;
        } else {
            throw $erreurSql;
        }
    }
    if (!$gardeActive) {
        throw new RuntimeException('sp_update_order_status a laissé passer ANNULEE en direct.');
    }
    $repoCmd->updateOrderStatus($idCmdPayee, 'ANNULEE', 'Rupture fournisseur', 1, 'ADMIN');
    $stockApresAdminPayee = (int) $pdoParcours->query('SELECT stock FROM produit WHERE id_produit = ' . $idProdTest)->fetchColumn();
    if ($stockApresAdminPayee !== $stockReference) {
        throw new RuntimeException('Annulation admin PAYEE : stock non restitué.');
    }

    // 6c. Annulation admin d'une commande EXPEDIEE : stock non restitué, client bloqué.
    $cmdExp = $repoCmd->createOrderFromBasket(1, '14 boulevard Victor Hugo, 06000 Nice', [['id_produit' => $idProdTest, 'quantite' => 2]], true);
    $idCmdExp = (int) $cmdExp['id_commande'];
    $repoCmd->updateOrderStatus($idCmdExp, 'EXPEDIEE', 'Colis expédié', 1, 'ADMIN');
    $codeRefusClient = '';
    try {
        $repoCmd->updateOrderStatus($idCmdExp, 'ANNULEE', 'Client trop tard', 1, 'CLIENT');
    } catch (\App\Model\Data\BusinessError $erreurMetier) {
        $codeRefusClient = $erreurMetier->businessCode;
    }
    if ($codeRefusClient !== 'ANNULATION_TROP_TARDIVE') {
        throw new RuntimeException('Refus client sur commande EXPEDIEE attendu (obtenu : ' . $codeRefusClient . ').');
    }
    $repoCmd->updateOrderStatus($idCmdExp, 'ANNULEE', 'Avarie transporteur', 1, 'ADMIN');
    $stockApresExp = (int) $pdoParcours->query('SELECT stock FROM produit WHERE id_produit = ' . $idProdTest)->fetchColumn();
    $etatExp = $repoCmd->find($idCmdExp);
    if ($stockApresExp !== $stockReference - 2 || $etatExp === null || $etatExp['statut'] !== 'ANNULEE' || (float) $etatExp['montant_total'] !== 0.0) {
        throw new RuntimeException('Annulation admin EXPEDIEE incorrecte (le stock ne devait pas revenir).');
    }

    // 6d. Filtrage UTC strict par bornes [debut, fin[ sur revenueReport.
    $jourUtc = \App\Tools\Heure::maintenantUtc()->format('Y-m-d');
    $debutJour = \App\Tools\Heure::borneJourUtc($jourUtc, false);
    $finJour = \App\Tools\Heure::borneJourUtc($jourUtc, true);
    if ($debutJour === null || $finJour === null) {
        throw new RuntimeException('Bornes UTC du jour courant introuvables.');
    }
    $cmdRapport = $repoCmd->createOrderFromBasket(1, '16 rue Pastorelli, 06000 Nice', [['id_produit' => $idProdTest, 'quantite' => 1]], true);
    $rapportJour = $repoCmd->revenueReport($debutJour, $finJour);
    $rapportVide = $repoCmd->revenueReport('1999-01-01 00:00:00', '1999-01-02 00:00:00');
    if ($rapportJour['par_statut'] === [] || $rapportVide['par_statut'] !== [] || (int) $cmdRapport['id_commande'] <= 0) {
        throw new RuntimeException('Filtrage par bornes UTC de revenueReport incohérent.');
    }

    // 6e. RetentionComptes sur SqlStore : simulation puis suppression sélective d'un compte inactif sans commande.
    if ($storeParcours->find('parametre', 'conservation_compte') === null) {
        $storeParcours->insert('parametre', [
            'cle' => 'conservation_compte',
            'valeur' => '24',
            'description' => 'Conservation comptes inactifs (mois)',
        ]);
    } else {
        $storeParcours->update('parametre', [\App\Model\Data\Filter::eq('cle', 'conservation_compte')], ['valeur' => '24']);
    }
    $repoClients = new \App\Repository\ClientRepository($storeParcours);
    $emailInactif = 'sql.inactif.' . bin2hex(random_bytes(4)) . '@example.test';
    $idInactif = $repoClients->createAccount('Inactif', 'Sql', $emailInactif, password_hash('MotDePasse2026!', PASSWORD_BCRYPT, ['cost' => 4]));
    $storeParcours->update('client', [\App\Model\Data\Filter::eq('id_client', $idInactif)], [
        'date_creation' => '2021-02-01 08:00:00',
        'derniere_connexion' => '2021-03-01 08:00:00',
    ]);
    $retentionSql = new \App\Service\RetentionComptes($storeParcours);
    $simSql = $retentionSql->traiter(\App\Tools\Heure::maintenantUtc(), false);
    if ($simSql['supprimés'] !== 0 || $simSql['éligibles'] < 1 || $storeParcours->find('client', $idInactif) === null) {
        throw new RuntimeException('Simulation RetentionComptes SQL invalide.');
    }
    $execSql = $retentionSql->traiter(\App\Tools\Heure::maintenantUtc(), true);
    if ($execSql['supprimés'] < 1 || $storeParcours->find('client', $idInactif) !== null || $storeParcours->find('client', 1) === null) {
        throw new RuntimeException('Exécution RetentionComptes SQL invalide.');
    }
    echo "   ✅ sp_cancel_order, garde sp_update_order_status, annulation EXPEDIEE sans retour stock, bornes UTC et RetentionComptes SQL\n";
} catch (Throwable $erreur) {
    $echecsParcoursSql = 1;
    echo '   ❌ parcours SQL étape 6 : ' . $erreur->getMessage() . "\n";
}

if (($echecsCatalogue !== 0 || $echecsNouveautes !== 0 || $echecsFuseau !== 0 || $echecsParcoursSql !== 0) && $code === 0) {
    $code = 1;
}

// ------------------------------------------------------------ ménage
if ($garder) {
    echo "\n(base « " . $base . " » conservée — --garder)\n";
} else {
    try {
        $installateur->supprimerBase();
        echo "\n(base « " . $base . " » supprimée : les données de développement sont intactes)\n";
    } catch (Throwable $erreur) {
        echo "\n⚠ la base « " . $base . " » n'a pas pu être supprimée : " . $erreur->getMessage() . "\n";
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
if ($code === 0) {
    echo "SQL : CAMPAGNE CONFORME ✅\n";
    exit(0);
}
echo "SQL : ÉCHECS DANS LA CAMPAGNE ❌ (code " . $code . ")\n";
exit(1);
