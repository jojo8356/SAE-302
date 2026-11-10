<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Heures décalées d'une heure » (historique,
 * détail et listes de commandes ; « Ajouté le » des produits).
 *
 * Signalement : commandes passées à 19h15 et 19h47 (heure de Paris),
 * affichées 18h15 et 18h47. Cause : bootstrap forçait UTC, les
 * horodatages naissaient par `date()` en UTC et `date_fr()` les
 * réaffichait tels quels — aucun affichage ne convertissait vers le
 * fuseau du visiteur. (En été, l'écart aurait été de deux heures.)
 *
 * Correctif verrouillé ici : un gestionnaire unique, App\Tools\Heure —
 * stockage en UTC (format « Y-m-d H:i:s » inchangé), affichage converti
 * en Europe/Paris par DateTimeImmutable + DateTimeZone (règles IANA :
 * CET = UTC+1 l'hiver, CEST = UTC+2 l'été, transitions les derniers
 * dimanches de mars et d'octobre). `date_fr()`, point d'affichage
 * unique des vues, délègue au gestionnaire ; tous les sites de
 * génération d'horodatage passent par lui.
 *
 * Sources des règles vérifiées :
 *   - Manuel PHP DateTimeImmutable / DateTimeZone (php.net) ;
 *   - IANA tzdb, zonenow.tab : « Europe/Paris  CET/CEST  +01/+02 » et
 *     fichier europe (transitions derniers dimanches de mars/octobre).
 *
 * Usage :  php tests/php/heure_test.php
 *         node scripts/wasm_run.mjs tests/php/heure_test.php   (sandbox)
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Tools\Heure;

ob_start();

$variableOrigine = getenv('FUSEAU_AFFICHAGE');
putenv('FUSEAU_AFFICHAGE=Europe/Paris');

$echecs = 0;
$tests = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$echecs, &$tests): void {
    ++$tests;
    $icone = '  ❌ ';
    if ($ok) {
        $icone = '  ✅ ';
    }
    echo $icone, $label;
    if (!$ok && $detail !== '') {
        echo " — ", $detail;
    }
    echo "\n";
    if (!$ok) {
        ++$echecs;
    }
};

echo "MiniShop — Heure : UTC en base, Europe/Paris à l'écran\n\n";

/* ── 1. Le signalement, tel quel : hiver, UTC+1 ─────────────────────
 * Une commande passée à 19h15 à Paris en janvier est stockée 18h15 UTC
 * (son instant) ; l'affichage doit restituer 19h15. Idem 19h47. */
$check('signalement : 18h15 UTC (janv.) s\'affiche 19h15', date_fr('2026-01-15 18:15:00', true) === '15/01/2026 19h15', date_fr('2026-01-15 18:15:00', true));
$check('signalement : 18h47 UTC (janv.) s\'affiche 19h47', date_fr('2026-01-15 18:47:00', true) === '15/01/2026 19h47', date_fr('2026-01-15 18:47:00', true));
$check('même jour : 17h15 UTC (juillet, UTC+2) s\'affiche 19h15', date_fr('2026-07-15 17:15:00', true) === '15/07/2026 19h15', date_fr('2026-07-15 17:15:00', true));
$check('date seule : pas d\'heure inventée, jour converti', date_fr('2026-01-15 18:15:00') === '15/01/2026');
$check('minuit UTC bascule au lendemain à Paris (été)', date_fr('2026-07-01 22:30:00', true) === '02/07/2026 00h30', date_fr('2026-07-01 22:30:00', true));

/* ── 2. Contrat historique de date_fr() préservé ─────────────────── */
$check('date_fr(null) → —', date_fr(null) === '—');
$check('date_fr(\'\') → —', date_fr('') === '—');
$check('date_fr(illisible) → —', date_fr('pas une date') === '—');
$check('date_fr format court JJ/MM/AAAA', preg_match('#^\d{2}/\d{2}/\d{4}$#', date_fr('2026-09-21 12:19:03')) === 1);

/* ── 3. Décalage UTC (CET/CEST) et transitions IANA ──────────────── */
$hiver = new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC'));
$ete = new DateTimeImmutable('2026-07-15 12:00:00', new DateTimeZone('UTC'));
$check('hiver : décalage UTC+1 (CET)', Heure::decalage($hiver) === 'UTC+1', Heure::decalage($hiver));
$check('été : décalage UTC+2 (CEST)', Heure::decalage($ete) === 'UTC+2', Heure::decalage($ete));
$check('hiver : pas d\'heure avancée', Heure::estHeureAvancee($hiver) === false);
$check('été : heure avancée active', Heure::estHeureAvancee($ete) === true);
$check('libellé fuseau d\'été', Heure::libelleFuseau($ete) === 'Europe/Paris (UTC+2, heure d\'été)', Heure::libelleFuseau($ete));
$check('libellé fuseau d\'hiver', Heure::libelleFuseau($hiver) === 'Europe/Paris (UTC+1, heure d\'hiver)', Heure::libelleFuseau($hiver));

/* Dernier dimanche de mars 2026 (29 mars) : à 01:00 UTC, Paris passe
 * de 02h00 CET à 03h00 CEST — l'heure 02hxx n'existe pas ce jour-là. */
$check('veille du passage été : 00h30 UTC → 01h30 Paris', date_fr('2026-03-29 00:30:00', true) === '29/03/2026 01h30', date_fr('2026-03-29 00:30:00', true));
$check('passage été : 01h30 UTC → 03h30 Paris (02hxx sautée)', date_fr('2026-03-29 01:30:00', true) === '29/03/2026 03h30', date_fr('2026-03-29 01:30:00', true));

/* Dernier dimanche d'octobre 2026 (25 oct.) : à 01:00 UTC, Paris passe
 * de 03h00 CEST à 02h00 CET — l'heure 02hxx est vécue deux fois. Le
 * stockage UTC lève toute ambiguïté : deux instants distincts. */
$premierPassage = Heure::instantDepuisStockage('2026-10-25 00:30:00');
$secondPassage = Heure::instantDepuisStockage('2026-10-25 01:30:00');
$check('passage hiver : 00h30 UTC → 02h30 Paris (CEST)', date_fr('2026-10-25 00:30:00', true) === '25/10/2026 02h30', date_fr('2026-10-25 00:30:00', true));
$check('passage hiver : 01h30 UTC → 02h30 Paris (CET)', date_fr('2026-10-25 01:30:00', true) === '25/10/2026 02h30', date_fr('2026-10-25 01:30:00', true));
$check('heure répétée : deux instants UTC distincts d\'une heure', $premierPassage !== null && $secondPassage !== null && ($secondPassage->getTimestamp() - $premierPassage->getTimestamp()) === 3600);
$check('heure répétée : décalages CEST puis CET', $premierPassage !== null && $secondPassage !== null && Heure::decalage($premierPassage) === 'UTC+2' && Heure::decalage($secondPassage) === 'UTC+1');

/* ── 4. Aller-retour stockage ↔ affichage ────────────────────────── */
$instant = new DateTimeImmutable('2026-01-15 18:15:00', new DateTimeZone('UTC'));
$stocke = Heure::horodatageStockage($instant);
$check('horodatageStockage conserve la représentation UTC', $stocke === '2026-01-15 18:15:00', $stocke);
$relu = Heure::instantDepuisStockage($stocke);
$check('relecture : même instant (timestamp identique)', $relu !== null && $relu->getTimestamp() === $instant->getTimestamp());
$check('relecture : exprimé dans le fuseau d\'affichage', $relu !== null && $relu->getTimezone()->getName() === 'Europe/Paris');
$check('formaterInstant = formater(stocké)', $relu !== null && Heure::formaterInstant($relu, true) === date_fr($stocke, true));

/* Une valeur ISO portant son fuseau est lue comme un instant. */
$check('ISO avec +02:00 converti à Paris', Heure::formater('2026-07-15T19:15:00+02:00', true) === '15/07/2026 19h15', Heure::formater('2026-07-15T19:15:00+02:00', true));
$check('ISO Zulu converti à Paris (hiver)', Heure::formater('2026-01-15T18:15:00Z', true) === '15/01/2026 19h15');
$check('formater(null) → —', Heure::formater(null) === '—');
$check('formater(illisible) → —', Heure::formater('nimporte quoi') === '—');
$check('instantDepuisStockage(null) → null', Heure::instantDepuisStockage(null) === null);
$check('instantDepuisStockage(vide) → null', Heure::instantDepuisStockage('  ') === null);

/* ── 5. Insensibilité au fuseau par défaut du serveur ──────────────
 * Quel que soit date.timezone (php.ini) ou un date_default_timezone_set
 * tiers, génération et affichage restent UTC/Paris. */
$avant = date_default_timezone_get();
date_default_timezone_set('America/New_York');
$check('fuseau serveur hostile : stockage toujours UTC', Heure::horodatageStockage($instant) === '2026-01-15 18:15:00');
$check('fuseau serveur hostile : affichage toujours Paris', date_fr('2026-01-15 18:15:00', true) === '15/01/2026 19h15');
date_default_timezone_set($avant);
$check('bootstrap impose UTC comme garde-fou par défaut', $avant === 'UTC', $avant);

/* Horodatage généré « maintenant » : plausible et UTC. */
$maintenant = Heure::horodatageStockage();
$check('horodatageStockage() ≈ maintenant UTC (±5 s)', abs(time() - strtotime($maintenant . ' UTC')) <= 5, $maintenant);
$check('horodatageStockage() au format Y-m-d H:i:s', preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $maintenant) === 1);
$check('maintenant() est à Paris, écart cohérent', Heure::maintenant()->getTimezone()->getName() === 'Europe/Paris');
$check('anneeCivile = année de Paris', Heure::anneeCivile() === (int) Heure::maintenant()->format('Y'));
$check('constantes du gestionnaire', Heure::FUSEAU_STOCKAGE === 'UTC' && Heure::FUSEAU_AFFICHAGE === 'Europe/Paris');

/* ── 6. Branchements : plus aucun date() nu, date_fr délègue ─────── */
$racine = dirname(__DIR__, 2);
$sources = '';
foreach (['app/Model/Data/JsonStore.php', 'app/Model/Data/Triggers.php', 'app/Model/Data/Seed.php', 'app/Model/Data/Journal.php', 'app/Repository/ClientRepository.php', 'app/Repository/CommandeRepository.php'] as $fichier) {
    $sources .= file_get_contents($racine . '/' . $fichier);
}
$check('les générateurs passent par Heure (≥ 8 appels)', substr_count($sources, 'Heure::') >= 8, (string) substr_count($sources, 'Heure::'));
$check('aucun date(\'Y-m-d H:i:s\') brut dans les générateurs', !str_contains($sources, "date('Y-m-d H:i:s')"));
$check('numéro de commande : année civile de Paris', str_contains(file_get_contents($racine . '/app/Repository/CommandeRepository.php'), 'Heure::anneeCivile()'));
$xss = file_get_contents($racine . '/app/Security/xss.php');
$check('date_fr() délègue au gestionnaire', str_contains($xss, 'Heure::formater('));
$check('date_fr() ne lit plus l\'horloge serveur (strtotime/date)', !str_contains($xss, 'strtotime'));
$check('le gestionnaire existe à l\'emplacement PSR-4 App\\Tools', is_file($racine . '/app/Tools/Heure.php'));

/* Décalages d'affichage configurables : écriture UTC et année métier inchangées. */
putenv('FUSEAU_AFFICHAGE=UTC+2');
$check('UTC+2 : affichage fixe hiver', date_fr('2026-01-15 18:15:00', true) === '15/01/2026 20h15');
$check('UTC+2 : affichage fixe été', date_fr('2026-07-15 17:15:00', true) === '15/07/2026 19h15');
$check('UTC+2 : borne du jour local en UTC', Heure::borneJourUtc('2026-01-15') === '2026-01-14 22:00:00' && Heure::borneJourUtc('2026-01-15', true) === '2026-01-15 22:00:00');
$check('UTC+2 : stockage inchangé', Heure::horodatageStockage($instant) === '2026-01-15 18:15:00');
$check('UTC+2 : libellé sans fausse heure d’été', str_contains(Heure::libelleFuseau($ete), 'décalage fixe') && !Heure::estHeureAvancee($ete));
putenv('FUSEAU_AFFICHAGE=UTC-5');
$check('UTC-5 : jour précédent', date_fr('2026-01-15 02:15:00', true) === '14/01/2026 21h15');
$check('UTC-5 : filtre local converti', Heure::borneJourUtc('2026-01-15') === '2026-01-15 05:00:00');
putenv('FUSEAU_AFFICHAGE=UTC+5:30');
$check('UTC+5:30 : minutes et décalage affichés', date_fr('2026-01-15 18:15:00', true) === '15/01/2026 23h45' && Heure::decalage($hiver) === 'UTC+5:30');
putenv('FUSEAU_AFFICHAGE=UTC');
$check('UTC : aucun décalage', date_fr('2026-01-15 18:15:00', true) === '15/01/2026 18h15');
foreach (['UTC+15', 'UTC-13', 'UTC+14:01', 'UTC+1:60', 'UTC+1xyz', 'Mars/Phobos'] as $incorrect) {
    putenv('FUSEAU_AFFICHAGE=' . $incorrect);
    $refuseInvalide = false;
    try { Heure::fuseauAffichage(); } catch (InvalidArgumentException) { $refuseInvalide = true; }
    $check('fuseau invalide refusé : ' . $incorrect, $refuseInvalide);
}
// Lecture réelle du fichier .env et priorité de l'environnement du processus.
$ancienFichier = getenv('MINISHOP_ENV_FILE');
$envTemp = sys_get_temp_dir() . '/minishop-fuseau-' . bin2hex(random_bytes(8)) . '.env';
file_put_contents($envTemp, "FUSEAU_AFFICHAGE=UTC-3\n");
putenv('MINISHOP_ENV_FILE=' . $envTemp);
putenv('FUSEAU_AFFICHAGE');
\App\Config\Env::reinitialiser();
$check('valeur depuis .env', date_fr('2026-01-15 18:15:00', true) === '15/01/2026 15h15');
putenv('FUSEAU_AFFICHAGE=UTC+1');
$check('environnement prioritaire sur .env', date_fr('2026-01-15 18:15:00', true) === '15/01/2026 19h15');
@unlink($envTemp);
if ($ancienFichier === false) { putenv('MINISHOP_ENV_FILE'); } else { putenv('MINISHOP_ENV_FILE=' . $ancienFichier); }
\App\Config\Env::reinitialiser();
putenv('FUSEAU_AFFICHAGE=Europe/Paris');
$check('bornes locales Paris au changement d’heure', Heure::borneJourUtc('2026-03-29') === '2026-03-28 23:00:00' && Heure::borneJourUtc('2026-03-29', true) === '2026-03-29 22:00:00');
$check('jour invalide refusé', Heure::borneJourUtc('2026-02-31') === null && Heure::borneJourUtc('x') === null);
if ($variableOrigine === false) { putenv('FUSEAU_AFFICHAGE'); } else { putenv('FUSEAU_AFFICHAGE=' . $variableOrigine); }

/* La connexion échoue fermée même si PDO est en mode silencieux. */
$pdoSilencieux = new class extends PDO {
    public int $appels = 0;
    public function __construct() {}
    public function exec(string $statement): int|false
    {
        ++$this->appels;
        return false;
    }
};
$refus = 0;
$storeSql = new \App\Model\Data\SqlStore($pdoSilencieux);
for ($i = 0; $i < 2; ++$i) {
    try { $storeSql->pdo(); } catch (PDOException) { ++$refus; }
}
$check('session SQL non initialisable : échec fermé et aucun cache de connexion', $refus === 2 && $pdoSilencieux->appels === 2);

ob_end_clean();

$symbole = '❌';
$code = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $code = 0;
}
echo $symbole, " Heure : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($code);
