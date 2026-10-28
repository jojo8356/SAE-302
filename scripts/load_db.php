<?php

declare(strict_types=1);

/**
 * MiniShop — chargement de la base MySQL/MariaDB (enveloppe historique).
 *
 * Conservé pour les scripts et la documentation existants
 * (`./scripts/load_db.sh`, CI, campagnes de mesure). Il délègue tout à
 * App\Tools\DbInstaller : même comportement que `php scripts/db_init.php`,
 * mais piloté par variables d'environnement seulement (pratique en CI).
 *
 * Usage :
 *   php scripts/load_db.php                       # base « minishop » + démo
 *   DB=minishop_test php scripts/load_db.php      # base de test
 *   SKIP_DEMO=1 php scripts/load_db.php           # sans sql/04 (démo)
 *   KEEP=1 php scripts/load_db.php                # ne recrée pas si déjà valide
 *
 * Variables : DB / DB_NAME, DB_HOST, DB_PORT, DB_USER, DB_PASS,
 *             SKIP_DEMO (0|1), KEEP (0|1).
 *
 * Code retour : 0 succès, 1 échec de connexion ou de chargement, 2 nom invalide.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Tools\DbInstaller;

/**
 * Écrit sur la sortie d'erreur.
 *
 * La constante STDERR n'existe que sous le SAPI CLI : le runtime PHP-WASM
 * utilisé pour les tests de la sandbox ne la définit pas. On retombe alors sur
 * la sortie standard — le message reste affiché, seul le canal change.
 */
function erreur(string $texte): void
{
    if (defined('STDERR')) {
        fwrite(STDERR, $texte);

        return;
    }

    echo $texte;
}

$installateur = DbInstaller::depuisVariables();
$valeurDemo = getenv('SKIP_DEMO');
$sansDemo = $valeurDemo === '1';
$valeurKeep = getenv('KEEP');
$conserver = $valeurKeep === '1';

if (!$installateur->baseValide()) {
    erreur('✘ nom de base invalide : ' . $installateur->base() . "\n");
    exit(2);
}

echo '== MiniShop : chargement de la base « ' . $installateur->base() . " » sur "
    . $installateur->hote() . ':' . $installateur->port() . " ==\n";

try {
    if ($conserver && $installateur->baseExiste()) {
        $controles = $installateur->verifier($installateur->inventaire(), !$sansDemo);
        $incomplet = false;
        foreach ($controles as $controle) {
            if (!$controle['ok']) {
                $incomplet = true;
            }
        }
        if (!$incomplet) {
            echo "✔ base déjà conforme (KEEP=1) — rien à faire.\n";
            exit(0);
        }
        erreur("→ base incomplète : reconstruction (KEEP=1 ignoré).\n");
    }

    if ($installateur->baseExiste()) {
        $installateur->supprimerBase();
    }
    $installateur->creerBase();

    $comptes = $installateur->charger(
        DbInstaller::scripts(!$sansDemo),
        static function (string $fichier, int $nombre): void {
            printf("   %-32s %3d instruction(s)\n", $fichier, $nombre);
        },
    );

    $inventaire = $installateur->inventaire();
    printf(
        "   tables %d · vues %d · procédures %d · fonctions %d · triggers %d\n",
        $inventaire['tables'],
        $inventaire['vues'],
        $inventaire['procedures'],
        $inventaire['fonctions'],
        $inventaire['triggers'],
    );
    printf(
        "   produits %d · clients %d · commandes %d\n",
        $inventaire['produits'],
        $inventaire['clients'],
        $inventaire['commandes'],
    );

    $conformes = true;
    foreach ($installateur->verifier($inventaire, !$sansDemo) as $controle) {
        if (!$controle['ok']) {
            $conformes = false;
            erreur('   ❌ ' . $controle['controle'] . ' — ' . $controle['detail'] . "\n");
        }
    }

    if (!$conformes) {
        erreur("✘ base chargée mais incomplète : voir les contrôles ci-dessus.\n");
        exit(1);
    }

    $total = array_sum($comptes);
    echo '✔ base « ' . $installateur->base() . " » prête ($total instructions exécutées).\n";
    exit(0);
} catch (Throwable $erreur) {
    erreur('✘ ' . $erreur->getMessage() . "\n");
    exit(1);
}
