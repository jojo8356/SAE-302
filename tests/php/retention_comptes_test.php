<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Model\Data\Filter;
use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use App\Repository\ClientRepository;
use App\Repository\CommandeRepository;
use App\Repository\ParametreRepository;
use App\Service\RetentionComptes;

$dir = sys_get_temp_dir() . '/minishop-retention-' . bin2hex(random_bytes(8));
$store = JsonStore::open($dir);
Seed::force($store);
foreach ([1, 2, 3] as $idSeed) {
    $store->update('client', [Filter::eq('id_client', $idSeed)], ['date_creation' => '2026-09-01 00:00:00', 'derniere_connexion' => '2026-09-01 00:00:00']);
}
$clients = new ClientRepository($store);
$repo = CommandeRepository::for($store);
$parametres = new ParametreRepository($store);
$maintenant = new DateTimeImmutable('2026-10-15 12:00:00', new DateTimeZone('UTC'));
$echecs = 0;
$checks = 0;
$check = static function (string $description, bool $ok) use (&$checks, &$echecs): void {
    ++$checks;
    if (!$ok) { ++$echecs; echo "❌ $description\n"; }
};
$ids = [];
foreach (['ancien', 'recent', 'commande', 'date', 'limite', 'bord'] as $nom) {
    $id = $clients->createAccount($nom, 'Fixture', $nom . '@test.invalid', password_hash('motdepasseTest', PASSWORD_BCRYPT));
    $store->update('client', [Filter::eq('id_client', $id)], ['date_creation' => '2020-01-01 00:00:00', 'derniere_connexion' => '2020-01-01 00:00:00']);
    $ids[$nom] = $id;
}
$store->update('client', [Filter::eq('id_client', $ids['recent'])], ['derniere_connexion' => '2026-09-01 00:00:00']);
$store->update('client', [Filter::eq('id_client', $ids['limite'])], ['derniere_connexion' => '2024-10-13 12:00:00']);
$store->update('client', [Filter::eq('id_client', $ids['bord'])], ['derniere_connexion' => '2024-10-13 12:00:01']);
$store->update('client', [Filter::eq('id_client', $ids['date'])], ['date_creation' => null]);
$repo->createOrder($ids['commande'], 'Adresse à conserver');
$service = new RetentionComptes($store);
$simulation = $service->traiter($maintenant, false);
$check('simulation sans suppression', $simulation['éligibles'] === 2 && $store->find('client', $ids['ancien']) !== null);
$check('commande exclue et date absente exclue', $simulation['commandes'] === 1 && $simulation['dates_invalides'] === 1);
$applique = $service->traiter($maintenant, true);
$check('compte ancien sans commande supprimé', $applique['supprimés'] === 2 && $store->find('client', $ids['ancien']) === null);
$check('échéance atteinte supprimée et seconde suivante conservée', $store->find('client', $ids['limite']) === null && $store->find('client', $ids['bord']) !== null);
$check('compte récent conservé', $store->find('client', $ids['recent']) !== null);
$check('compte ayant commande conservé', $store->find('client', $ids['commande']) !== null && $store->count('commande', [Filter::eq('id_client', $ids['commande'])]) === 1);
$check('date manquante conservée', $store->find('client', $ids['date']) !== null);
$check('traitement répétable', $service->traiter($maintenant, true)['supprimés'] === 0);
$clientActif = $clients->clientActif($ids['recent']);
$check('session authentifiée conserve et actualise la date', $clientActif !== null && $store->find('client', $ids['recent'])['derniere_connexion'] !== '2026-09-01 00:00:00');
$check('date impossible rejetée', RetentionComptes::dateUtc('2025-02-31 00:00:00') === null);
$check('date absente rejetée', RetentionComptes::dateUtc(null) === null);
$erreur = false;
try { $parametres->enregistrer('conservation_compte', '0'); } catch (Throwable $e) { $erreur = true; }
$check('délai nul refusé', $erreur);
$store->update('parametre', [Filter::eq('cle', 'conservation_compte')], ['valeur' => 'abc']);
$erreur = false;
try { $service->traiter($maintenant, true); } catch (RuntimeException $e) { $erreur = true; }
$check('paramètre corrompu : refus sans suppression', $erreur && $store->find('client', $ids['recent']) !== null);
$journal = (string) file_get_contents($store->journalPath());
$check('nouvelles traces client sans snapshots personnels', !str_contains($journal, 'ancien@test.invalid') && !str_contains($journal, 'mot_de_passe_hash'));
$fichiers = glob($dir . '/*');
if (is_array($fichiers)) { foreach ($fichiers as $fichier) { @unlink($fichier); } }
@unlink($dir . '/.minishop.lock');
@rmdir($dir);
if ($echecs > 0) { echo "❌ Rétention : $echecs échec(s) sur $checks\n"; exit(1); }
echo "✅ Rétention des comptes : $checks vérifications réussies\n";
