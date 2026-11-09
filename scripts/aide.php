<?php

declare(strict_types=1);

/** Aide locale en lecture seule : aucune création ni connexion à une base. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Model\Data\Seed;

$options = array_slice($argv, 1);
if ($options === [] || $options === ['--help'] || $options === ['-h']) {
    echo "Aide MiniShop (lecture seule)\n"
        . "Usage : php scripts/aide.php [--help | --comptes-demo]\n"
        . "  --help          Afficher cette aide.\n"
        . "  --comptes-demo  Afficher les identifiants initiaux du jeu de démonstration JSON.\n"
        . "Les comptes ont pu être modifiés ou ne pas exister sur votre installation.\n";
    exit(0);
}
if ($options !== ['--comptes-demo']) {
    fwrite(STDERR, "Option invalide. Usage : php scripts/aide.php [--help | --comptes-demo]\n");
    exit(2);
}

echo "Identifiants initiaux du jeu de démonstration JSON uniquement.\n"
    . "Ne pas utiliser ces comptes sur une instance accessible sans protection.\n"
    . "Ils peuvent avoir été modifiés ou être absents de votre installation.\n";
foreach (Seed::comptesDemo() as $email => $motDePasse) {
    echo $email . ' : ' . $motDePasse . "\n";
}
