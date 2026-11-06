<?php

declare(strict_types=1);

/**
 * MiniShop — régression « Créer un compte : erreur = tout retaper »
 * (EF-VIS-06, RB-01).
 *
 * Signalement : après une erreur d'inscription (confirmation de
 * mot de passe différente), tous les champs étaient vidés et la
 * case CGV décochée — il fallait tout retaper ; et le site
 * n'affichait qu'une erreur à la fois (l'email déjà utilisé
 * n'était signalé qu'à l'envoi suivant). Vérifié en vrai POST :
 * nom, prénom et email repartaient bien, mais la case CGV perdait
 * son état, et l'unicité de l'email n'était contrôlée qu'à la
 * création du compte — donc jamais en même temps qu'une autre
 * erreur. Les étoiles des champs obligatoires, elles, existaient
 * déjà (même convention que « Mon compte »), sauf sur la case
 * d'acceptation des CGV, pourtant obligatoire.
 *
 * Correctif verrouillé ici : le contrôleur signale TOUTES les
 * erreurs en une fois — le doublon d'email est contrôlé dès la
 * validation via ClientRepository::emailExiste (même
 * normalisation que la connexion), avec le même message que la
 * création — et le formulaire repart avec nom, prénom, email et
 * case CGV dans l'état saisi ; seuls les mots de passe sont
 * vidés. La case CGV porte l'étoile des champs obligatoires.
 *
 * Usage :  php tests/php/inscription_erreurs_test.php
 *         node scripts/wasm_run.mjs tests/php/inscription_erreurs_test.php   (sandbox)
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Model\Data\BusinessError;
use App\Model\Data\JsonStore;
use App\Model\Data\Seed;
use App\Model\Data\StoreInterface;
use App\Repository\ClientRepository;

ob_start();

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

/** Rend le formulaire d'inscription avec erreurs et valeurs données. */
function rendreInscription(array $erreurs, array $valeurs): string
{
    ob_start();
    include dirname(__DIR__, 2) . '/app/View/auth/inscription.php';

    return (string) ob_get_clean();
}

/** Magasin temporaire peuplé comme la démonstration. */
function magasin(): StoreInterface
{
    $store = JsonStore::open(sys_get_temp_dir() . '/minishop_inscr' . getmypid());
    Seed::force($store);
    $store->setActor(['id' => 1, 'role' => 'ADMIN', 'nom' => 'Harnais']);

    return $store;
}

echo "MiniShop — Inscription : toutes les erreurs d'un coup, le formulaire ne se vide pas\n\n";

/* ── 1. Vue : le formulaire repart avec la saisie (sauf mots de passe)  */
echo "== 1 · Formulaire réaffiché : valeurs conservées, case CGV, étoiles ==\n";
$html = rendreInscription(
    ['La confirmation du mot de passe ne correspond pas.', 'Cette adresse email est déjà utilisée.'],
    ['nom' => 'Durand', 'prenom' => 'Zoe', 'email' => 'alice@example.com', 'cgv' => true]
);
$check(
    "nom, prénom et email repartent avec la saisie (pas tout à retaper)",
    str_contains($html, 'id="nom" name="nom" maxlength="100" required value="Durand"')
        && str_contains($html, 'id="prenom" name="prenom" maxlength="100" required value="Zoe"')
        && str_contains($html, 'id="email" name="email" maxlength="190" required value="alice@example.com"')
);
$check(
    "la case CGV reste cochée après l'erreur",
    preg_match('/<input type="checkbox" name="cgv" value="1" required checked>/', $html) === 1
);
$htmlDecochee = rendreInscription([], ['nom' => '', 'prenom' => '', 'email' => '']);
$check(
    "sans saisie, la case part décochée (aucun cochage forcé)",
    preg_match('/<input type="checkbox" name="cgv" value="1" required>/', $htmlDecochee) === 1
);
$check(
    "les mots de passe ne repartent jamais (aucune valeur réaffichée)",
    !str_contains($html, 'id="mot_de_passe" name="mot_de_passe" minlength="8" required value=')
        && !str_contains($html, 'id="confirmation" name="confirmation" minlength="8" required value=')
);
$check(
    "toutes les erreurs sont listées ensemble dans l'alerte",
    str_contains($html, '<li>✘ La confirmation du mot de passe ne correspond pas.</li>')
        && str_contains($html, '<li>✘ Cette adresse email est déjà utilisée.</li>')
);
$check(
    "chaque champ obligatoire porte son étoile, case CGV comprise",
    str_contains($html, '<label for="nom">Nom *</label>')
        && str_contains($html, '<label for="prenom">Prénom *</label>')
        && str_contains($html, '<label for="email">Email *</label>')
        && str_contains($html, '<label for="mot_de_passe">Mot de passe *')
        && str_contains($html, '<label for="confirmation">Confirmation *</label>')
        && str_contains($html, 'conditions générales de vente</a> *</label>')
);

/* ── 2. Repository : le doublon est détectable dès la validation ──── */
echo "\n== 2 · ClientRepository::emailExiste — le contrôle d'unicité en amont ==\n";
$store = magasin();
$repo = new ClientRepository($store);
// method_exists : sans le correctif, ce sont des échecs comptés,
// pas un crash de la suite.
$detecte = static function (string $email) use ($repo): ?bool {
    if (!method_exists($repo, 'emailExiste')) {
        return null;
    }

    return $repo->emailExiste($email);
};
$check(
    "l'email de démonstration est détecté comme déjà utilisé",
    $detecte('alice@example.com') === true
);
$check(
    "la détection suit la même normalisation que la connexion (casse et espaces)",
    $detecte(' Alice@Example.com ') === true
);
$check(
    "une adresse libre n'est pas signalée",
    $detecte('nouvelle@example.com') === false
);
$code = '';
try {
    $repo->createAccount('Durand', 'Zoe', 'alice@example.com', password_hash('MotDePasse1!', PASSWORD_BCRYPT));
} catch (BusinessError $e) {
    $code = $e->businessCode;
}
$check(
    "le garde-fou à la création reste armé (EMAIL_DEJA_UTILISE si le pré-contrôle est contourné)",
    $code === 'EMAIL_DEJA_UTILISE',
    $code
);

/* ── 3. Contrôleur : câblage du pré-contrôle et des valeurs ────────── */
echo "\n== 3 · Contrôleur : toutes les erreurs en une fois, état réinjecté ==\n";
$controleur = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controller/AuthController.php');
$check(
    "l'unicité est contrôlée PENDANT la validation (emailExiste), pas seulement à la création",
    str_contains($controleur, '$repo->emailExiste($email)')
        && str_contains($controleur, "BusinessError::MESSAGES['EMAIL_DEJA_UTILISE']")
);
$check(
    "l'état du formulaire (dont la case CGV) est réinjecté dans la vue",
    str_contains($controleur, "'email' => \$email, 'cgv' => \$cgv")
);

ob_end_clean();

$symbole = '❌';
$codeSortie = 1;
if ($echecs === 0) {
    $symbole = '✅';
    $codeSortie = 0;
}
echo $symbole, " Inscription (erreurs simultanées, saisie conservée) : ", ($tests - $echecs), "/", $tests, " vérifications réussies\n";
exit($codeSortie);
