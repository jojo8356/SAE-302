<?php

declare(strict_types=1);

namespace MiniShop\Tests;

use App\Model\Data\Filter;
use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use App\Repository\CommandeRepository;
use App\Service\NettoyageJournaux;
use App\Service\NettoyageSauvegardes;
use App\Service\RetentionComptes;
use App\Tools\Heure;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests unitaires PHPUnit couvrant les services d'horodatage UTC/Europe-Paris,
 * les bascules d'heure d'été/hiver, la conservation conservatrice des comptes,
 * ainsi que la rotation des journaux JSONL et des sauvegardes locales.
 */
final class ConservationEtFuseauTest extends TestCase
{
    private string $dossier = '';

    protected function setUp(): void
    {
        $this->dossier = sys_get_temp_dir() . '/minishop_pu_cons_' . getmypid() . '_' . bin2hex(random_bytes(4));
        mkdir($this->dossier, 0o700, true);
    }

    protected function tearDown(): void
    {
        $this->supprimerArbre($this->dossier);
    }

    private function supprimerArbre(string $racine): void
    {
        if ($racine === '' || !file_exists($racine)) {
            return;
        }
        if (is_link($racine) || is_file($racine)) {
            @unlink($racine);
            return;
        }
        $entrees = scandir($racine);
        if ($entrees === false) {
            $entrees = [];
        }
        foreach ($entrees as $entree) {
            if ($entree === '.' || $entree === '..') {
                continue;
            }
            $this->supprimerArbre($racine . '/' . $entree);
        }
        @rmdir($racine);
    }

    public function testHeureStockageUtcEtAffichageEuropeParis(): void
    {
        $utc = Heure::maintenantUtc();
        self::assertSame('UTC', $utc->getTimezone()->getName());
        self::assertSame('Europe/Paris', Heure::maintenant()->getTimezone()->getName());

        $hiver = new DateTimeImmutable('2026-01-15 18:15:00', new DateTimeZone('UTC'));
        self::assertSame('2026-01-15 18:15:00', Heure::horodatageStockage($hiver));
        self::assertFalse(Heure::estHeureAvancee($hiver));
        self::assertSame('UTC+1', Heure::decalage($hiver));
        self::assertSame("Europe/Paris (UTC+1, heure d'hiver)", Heure::libelleFuseau($hiver));
        self::assertSame('15/01/2026 19h15', Heure::formater('2026-01-15 18:15:00', true));

        $ete = new DateTimeImmutable('2026-07-15 18:15:00', new DateTimeZone('UTC'));
        self::assertTrue(Heure::estHeureAvancee($ete));
        self::assertSame('UTC+2', Heure::decalage($ete));
        self::assertSame("Europe/Paris (UTC+2, heure d'été)", Heure::libelleFuseau($ete));
        self::assertSame('15/07/2026 20h15', Heure::formater('2026-07-15 18:15:00', true));

        $depuisIso = Heure::instantDepuisStockage('2026-07-15T20:15:00+02:00');
        self::assertNotNull($depuisIso);
        self::assertSame('2026-07-15 18:15:00', Heure::horodatageStockage($depuisIso));

        self::assertNull(Heure::instantDepuisStockage(null));
        self::assertNull(Heure::instantDepuisStockage(''));
        self::assertSame('—', Heure::formater('date-invalide', true));
        self::assertGreaterThanOrEqual(2026, Heure::anneeCivile());
    }

    public function testBornesJourUtcAuxBasculesHeureEteHiverEtToleranceNavigateur(): void
    {
        // Bascule printemps Europe/Paris (2026-03-29 : journée locale de 23 heures).
        $debutPrintemps = Heure::borneJourUtc('2026-03-29', false);
        $finPrintemps = Heure::borneJourUtc('2026-03-29', true);
        self::assertSame('2026-03-28 23:00:00', $debutPrintemps);
        self::assertSame('2026-03-29 22:00:00', $finPrintemps);
        $tDebutP = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $debutPrintemps, new DateTimeZone('UTC'));
        $tFinP = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $finPrintemps, new DateTimeZone('UTC'));
        self::assertTrue($tDebutP instanceof DateTimeImmutable);
        self::assertTrue($tFinP instanceof DateTimeImmutable);
        if ($tDebutP instanceof DateTimeImmutable && $tFinP instanceof DateTimeImmutable) {
            self::assertSame(23 * 3600, $tFinP->getTimestamp() - $tDebutP->getTimestamp());
        }

        // Bascule automne Europe/Paris (2026-10-25 : journée locale de 25 heures).
        $debutAutomne = Heure::borneJourUtc('2026-10-25', false);
        $finAutomne = Heure::borneJourUtc('2026-10-25', true);
        self::assertSame('2026-10-24 22:00:00', $debutAutomne);
        self::assertSame('2026-10-25 23:00:00', $finAutomne);
        $tDebutA = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $debutAutomne, new DateTimeZone('UTC'));
        $tFinA = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $finAutomne, new DateTimeZone('UTC'));
        self::assertTrue($tDebutA instanceof DateTimeImmutable);
        self::assertTrue($tFinA instanceof DateTimeImmutable);
        if ($tDebutA instanceof DateTimeImmutable && $tFinA instanceof DateTimeImmutable) {
            self::assertSame(25 * 3600, $tFinA->getTimestamp() - $tDebutA->getTimestamp());
        }

        self::assertNull(Heure::borneJourUtc('2026-02-30', false));
        self::assertNull(Heure::borneJourUtc('0999-01-01', false));

        // Borne transmise par un navigateur America/New_York (UTC-5 en hiver) : acceptée car <= 36 h.
        self::assertSame(
            '2026-01-15 05:00:00',
            Heure::borneTransmise('2026-01-15', '2026-01-15 05:00:00', false)
        );
        // Borne hors tolérance (> 36 h d'écart) : rejetée.
        self::assertNull(Heure::borneTransmise('2026-01-15', '2026-01-18 12:00:00', false));
        self::assertNull(Heure::borneTransmise('2026-01-15', 'invalide', false));
    }

    public function testRetentionComptesSimulationExclusionCommandesEtPurgeReelle(): void
    {
        $storeDir = $this->dossier . '/store';
        $store = JsonStore::open($storeDir);
        Seed::force($store);
        $store->setActor(['id' => 1, 'role' => 'ADMIN', 'nom' => 'Retention Test']);

        // Fixer explicitement conservation_compte à 24 mois.
        if ($store->find('parametre', 'conservation_compte') !== null) {
            $store->update('parametre', [Filter::eq('cle', 'conservation_compte')], ['valeur' => '24']);
        } else {
            $store->insert('parametre', [
                'cle' => 'conservation_compte',
                'valeur' => '24',
                'description' => 'Durée de conservation en mois',
            ]);
        }

        $maintenant = new DateTimeImmutable('2026-10-23 12:00:00', new DateTimeZone('UTC'));
        $hash = password_hash('MotDePasse2026!', PASSWORD_BCRYPT, ['cost' => 4]);

        // 1. Compte inactif depuis > 24 mois + 2 jours sans commande -> éligible puis supprimé.
        $idEligible = $store->insert('client', [
            'nom' => 'Ancien',
            'prenom' => 'SansCommande',
            'email' => 'ancien.sanscmd@example.test',
            'mot_de_passe_hash' => $hash,
            'adresse_livraison' => '1 rue Calme',
            'code_postal' => '06000',
            'ville' => 'Nice',
            'actif' => 1,
        ]);
        $store->update('client', [Filter::eq('id_client', $idEligible)], [
            'date_creation' => '2023-01-10 10:00:00',
            'derniere_connexion' => '2024-01-10 10:00:00',
        ]);

        // 2. Compte ancien mais ayant une commande -> protégé (compteur commandes).
        $idAvecCommande = $store->insert('client', [
            'nom' => 'Ancien',
            'prenom' => 'AvecCommande',
            'email' => 'ancien.aveccmd@example.test',
            'mot_de_passe_hash' => $hash,
            'adresse_livraison' => '2 rue Active',
            'code_postal' => '06000',
            'ville' => 'Nice',
            'actif' => 1,
        ]);
        $depot = CommandeRepository::for($store);
        $depot->createOrder($idAvecCommande, '2 rue Active, 06000 Nice');
        $store->update('client', [Filter::eq('id_client', $idAvecCommande)], [
            'date_creation' => '2023-01-10 10:00:00',
            'derniere_connexion' => '2023-06-10 10:00:00',
        ]);

        // 3. Compte avec date future -> classé dates_invalides et jamais supprimé.
        $idDateFuture = $store->insert('client', [
            'nom' => 'Futur',
            'prenom' => 'Horloge',
            'email' => 'futur.horloge@example.test',
            'mot_de_passe_hash' => $hash,
            'adresse_livraison' => '3 rue Future',
            'code_postal' => '06000',
            'ville' => 'Nice',
            'actif' => 1,
        ]);
        $store->update('client', [Filter::eq('id_client', $idDateFuture)], [
            'date_creation' => '2028-01-01 00:00:00',
        ]);

        $service = new RetentionComptes($store);

        // Mode simulation : aucune suppression effective.
        $simulation = $service->traiter($maintenant, false);
        self::assertSame(0, $simulation['supprimés']);
        self::assertGreaterThanOrEqual(1, $simulation['éligibles']);
        self::assertGreaterThanOrEqual(1, $simulation['commandes']);
        self::assertGreaterThanOrEqual(1, $simulation['dates_invalides']);
        self::assertNotNull($store->find('client', $idEligible));

        // Mode exécution : seul le compte éligible sans commande est supprimé.
        $execution = $service->traiter($maintenant, true);
        self::assertSame($simulation['éligibles'], $execution['supprimés']);
        self::assertNull($store->find('client', $idEligible));
        self::assertNotNull($store->find('client', $idAvecCommande));
        self::assertNotNull($store->find('client', $idDateFuture));
    }

    public function testNettoyageJournauxEtSauvegardes(): void
    {
        $maintenant = new DateTimeImmutable('2026-10-23T12:00:00+00:00');
        $journal = $this->dossier . '/contact.jsonl';

        self::assertTrue(NettoyageJournaux::ajouter($journal, "{\"date\":\"2024-01-15T10:00:00+00:00\",\"msg\":\"ancien\"}\n"));
        self::assertTrue(NettoyageJournaux::ajouter($journal, "{\"date\":\"2026-09-15T10:00:00+00:00\",\"msg\":\"recent\"}\n"));
        self::assertTrue(NettoyageJournaux::ajouter($journal, "{corrompu}\n"));

        $simJournal = NettoyageJournaux::traiter($journal, 'date', 12, $maintenant, false);
        self::assertSame(1, $simJournal['eligible']);
        self::assertSame(0, $simJournal['supprime']);
        self::assertSame(1, $simJournal['conserve_invalide']);

        if (function_exists('posix_geteuid')) {
            $execJournal = NettoyageJournaux::traiter($journal, 'date', 12, $maintenant, true);
            self::assertSame(1, $execJournal['supprime']);
            $contenu = (string) file_get_contents($journal);
            self::assertStringNotContainsString('ancien', $contenu);
            self::assertStringContainsString('recent', $contenu);
            self::assertStringContainsString('{corrompu}', $contenu);
        }

        // Ligne > 1 Mio refusée sans altérer le journal.
        $journalTropLong = $this->dossier . '/trop_long.jsonl';
        file_put_contents($journalTropLong, '{"date":"2024-01-01T00:00:00+00:00","x":"' . str_repeat('a', 1050000) . "\"}\n");
        $erreurLongueur = false;
        try {
            NettoyageJournaux::traiter($journalTropLong, 'date', 6, $maintenant, false);
        } catch (RuntimeException) {
            $erreurLongueur = true;
        }
        self::assertTrue($erreurLongueur, 'ligne > 1 Mio doit lever RuntimeException');

        if (!function_exists('posix_geteuid')) {
            return;
        }

        // Sauvegardes : marqueur obligatoire + conservation minimale des 2 plus récentes même anciennes.
        $dirSauvegardes = $this->dossier . '/backups';
        mkdir($dirSauvegardes, 0o700, true);
        chmod($dirSauvegardes, 0o700);

        $sansMarqueur = false;
        try {
            NettoyageSauvegardes::traiter($dirSauvegardes, $maintenant, false);
        } catch (RuntimeException) {
            $sansMarqueur = true;
        }
        self::assertTrue($sansMarqueur, 'absence du marqueur .minishop-sauvegardes doit être refusée');

        file_put_contents($dirSauvegardes . '/.minishop-sauvegardes', "MINISHOP_BACKUPS_V1\n");
        $ancienMtime = $maintenant->modify('-60 days')->getTimestamp();
        $noms = [
            'minishop-20260701T120000Z-' . str_repeat('1', 32) . '.backup',
            'minishop-20260715T120000Z-' . str_repeat('2', 32) . '.backup',
            'minishop-20260801T120000Z-' . str_repeat('3', 32) . '.backup',
        ];
        foreach ($noms as $nom) {
            $chemin = $dirSauvegardes . '/' . $nom;
            file_put_contents($chemin, 'backup-data');
            touch($chemin, $ancienMtime);
        }

        $bilanSauv = NettoyageSauvegardes::traiter($dirSauvegardes, $maintenant, true);
        self::assertSame(1, $bilanSauv['eligible']);
        self::assertSame(1, $bilanSauv['supprime']);
        self::assertSame(2, $bilanSauv['conserve']);
        self::assertFalse(file_exists($dirSauvegardes . '/' . $noms[0]));
        self::assertTrue(file_exists($dirSauvegardes . '/' . $noms[1]));
        self::assertTrue(file_exists($dirSauvegardes . '/' . $noms[2]));
    }
}
