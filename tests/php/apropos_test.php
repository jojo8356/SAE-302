<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Page À propos » (EF-GEN-05).
 *
 * Trois griefs du signalement, trois familles de vérifications :
 *   1. la page disait que la boutique « ne fonctionne ni avec MySQL,
 *      ni avec aucune requête SQL » — le sujet est PHP/MySQL/PDO, le
 *      jury peut le lire comme un hors-sujet, et la phrase deviendra
 *      fausse une fois le site sur l'hébergement final en mode SQL.
 *      → la page doit présenter la cible MySQL/PDO honnêtement, en la
 *      distinguant de l'étape de démonstration, sans négation absolue ;
 *   2. elle s'adressait aux développeurs (chemins de fichiers, noms de
 *      classes, codes RB-/ENF-/EF-, jargon SGBD) au lieu de présenter
 *      la boutique aux visiteurs ;
 *   3. « 4 paramètres métier » n'en citait que 3 — il manquait la
 *      conservation des données de compte (36 mois). Les paramètres
 *      sont désormais rendus depuis la base : le nombre annoncé et le
 *      nombre affiché sont le même, par construction.
 *
 * La vue est rendue pour de vrai, alimentée par les paramètres réels
 * d'un magasin seedé (ParametreRepository::tous()).
 *
 * Usage :  php tests/php/apropos_test.php
 *         node scripts/wasm_run.mjs tests/php/apropos_test.php   (sandbox)
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

ob_start();

use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use App\Model\Data\StoreInterface;
use App\Repository\ParametreRepository;

$echecs = 0;
$tests = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$echecs, &$tests): void {
    ++$tests;
    $icone = '  ❌ ';
    if ($ok) {
        $icone = '  ✅ ';
    }
    $suffixe = '';
    if (!$ok && $detail !== '') {
        $suffixe = " — {$detail}";
    }
    echo $icone . $label . $suffixe . "\n";
    if (!$ok) {
        ++$echecs;
    }
};

function magasin(): StoreInterface
{
    static $n = 0;
    $dossier = sys_get_temp_dir() . '/minishop_apropos' . getmypid() . '_' . (++$n);
    if (is_dir($dossier)) {
        $anciens = glob($dossier . '/*.json');
        if (is_array($anciens)) {
            array_map('unlink', $anciens);
        }
        @rmdir($dossier);
    }
    $store = JsonStore::open($dossier);
    Seed::force($store);
    $store->setActor(['id' => 1, 'role' => 'ADMIN', 'nom' => 'Harnais']);

    return $store;
}

/**
 * Rend la vue À propos comme le fait HomeController::aPropos.
 *
 * @param array<string,int> $statistiques
 * @param list<array<string,mixed>> $parametres
 */
function rendreApropos(array $statistiques, array $parametres): string
{
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/home/apropos.php';

    return (string) ob_get_clean();
}

/** Extrait le HTML entre deux marqueurs (section des règles chiffrées). */
function section(string $html, string $debut, string $fin): string
{
    $posDebut = strpos($html, $debut);
    if ($posDebut === false) {
        return '';
    }
    $posFin = strpos($html, $fin, $posDebut);
    if ($posFin === false) {
        return substr($html, $posDebut);
    }

    return substr($html, $posDebut, $posFin - $posDebut);
}

echo "MiniShop — régression page À propos\n";

$store = magasin();
$parametres = (new ParametreRepository($store))->tous();
$html = rendreApropos(
    ['produits' => 12, 'visibles' => 11, 'categories' => 4],
    $parametres
);

// ============================================== 1. Une page pour les visiteurs
echo "\n1. Présentation de la boutique (et non du moteur)\n";
{
    $check('titre « À propos de MiniShop »', str_contains($html, '<h1>À propos de MiniShop</h1>'));
    $check('la boutique est présentée (matériel informatique, objets connectés)',
        str_contains($html, 'matériel informatique') && str_contains($html, 'objets connectés'));
    $check('prix TTC annoncés', str_contains($html, 'TTC'));
    $check('paiement simulé dit clairement (démonstration)', str_contains($html, 'paiement') && str_contains($html, 'simulé'));
    $check('chiffres du catalogue rendus (11 produits, 4 catégories)',
        str_contains($html, '11 produits') && str_contains($html, '4 catégories'));
    $check('lien vers le catalogue', str_contains($html, 'href="/catalogue"'));
}

// ============================================== 2. Formulation technique honnête
echo "\n2. Technique : cible MySQL/PDO, démo distinguée, aucune négation absolue\n";
{
    $check('MySQL nommé comme cible du modèle de données', str_contains($html, 'MySQL'));
    $check('PDO nommé pour l\'accès à la base', str_contains($html, 'PDO'));
    $check('PHP nommé', str_contains($html, 'PHP'));
    $check('l\'étape de démonstration est distinguée de la cible',
        str_contains($html, 'démonstration') && str_contains($html, 'cible'));
    $check('plus jamais « ni avec MySQL, ni avec aucune requête SQL »',
        !str_contains($html, 'ni avec MySQL') && !str_contains($html, 'aucune requête SQL'));
}

// ============================================== 3. Aucun jargon développeur
echo "\n3. Aucun jargon ni détail d'implémentation\n";
{
    $interdits = [
        'data/minishop', '.json', 'StorageDriver', 'moteur maison',
        'RB-', 'ENF-', 'EF-', 'UC-',
        'NOT NULL', 'ENUM', 'CHECK', 'RESTRICT/CASCADE', 'déclencheur',
        'rollback', 'snapshot', 'Schéma & contraintes',
        'À propos du moteur de données', 'Contenu actuel de la base',
        'fichier temporaire puis renommage', 'journal d\'audit',
    ];
    foreach ($interdits as $motif) {
        $check("absent : « {$motif} »", !str_contains($html, $motif));
    }
}

// ============================================== 4. Les paramètres : annoncés = affichés = en base
echo "\n4. Paramètres métier : le compte annoncé est le compte affiché\n";
{
    $check('la base seedée porte bien 4 paramètres', count($parametres) === 4, count($parametres) . ' en base');
    $regles = section($html, 'règles chiffrées', 'Qui sommes-nous');
    $check('le titre annonce le vrai nombre (Nos 4 règles chiffrées)', str_contains($html, 'Nos 4 règles chiffrées'));
    $check(
        'exactement 4 règles listées',
        substr_count($regles, '<li>') === 4,
        substr_count($regles, '<li>') . ' item(s)'
    );
    $check('frais de port cités : 4,90 €', str_contains($regles, 'Livraison standard') && str_contains($regles, '4,90 €'));
    $check('franchise citée : offerte dès 80,00 €', str_contains($regles, 'offerte') && str_contains($regles, '80,00 €'));
    $check('TVA citée : 20 %', str_contains($regles, 'TVA') && str_contains($regles, '20 %'));
    $check(
        'conservation des données citée : 36 mois (le 4e paramètre, celui qui manquait)',
        str_contains($regles, 'Conservation') && str_contains($regles, '36 mois')
    );
}

// ============================================== 5. Contrôleur : alimentation de la page
echo "\n5. HomeController::aPropos alimente la page en données réelles\n";
{
    $controleur = file_get_contents(dirname(__DIR__, 2) . '/app/Controller/HomeController.php');
    if (!is_string($controleur)) {
        $controleur = '';
    }
    $check('les paramètres viennent de ParametreRepository::tous()', str_contains($controleur, 'ParametreRepository'));
    $check('le nombre de catégories est compté pour la page', str_contains($controleur, "'categories' => \$store->count('categorie')"));
    $check('titre de page visiteur (plus « moteur de données »)', str_contains($controleur, "'À propos de MiniShop'"));
}

// ------------------------------------------------------------ bilan
echo "\n============================================================\n";
if ($echecs > 0) {
    echo "page À propos : {$echecs}/{$tests} vérification(s) en échec ❌\n";
    exit(1);
}
echo "page À propos : {$tests}/{$tests} vérifications vertes ✅\n";
exit(0);
