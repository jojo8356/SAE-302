<?php

declare(strict_types=1);

/**
 * MiniShop — inscription, connexion, déconnexion (UC-04/05, EF-VIS-06/07).
 *
 * La comparaison du mot de passe se fait EXCLUSIVEMENT par
 * password_verify() (RB-12) ; l'échec « email inconnu » et « mot de passe
 * erroné » produisent le même message (SEC-05) ; l'identifiant de session
 * est régénéré à la connexion (anti-fixation, SEC-04).
 */

namespace App\Controller;

use App\Config\Database;
use App\Model\Data\BusinessError;
use App\Repository\ClientRepository;
use App\Security\Auth;

final class AuthController extends Controller
{
    // ------------------------------------------------------------ client

    public function formulaireInscription(): void
    {
        $this->render('auth/inscription', ['erreurs' => [], 'valeurs' => []], 'Créer un compte');
    }

    public function inscription(): void
    {
        $this->verifierCsrf();
        $nom = $this->str('nom', 100);
        $prenom = $this->str('prenom', 100);
        $email = $this->str('email', 190);
        $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');
        $confirmation = (string) ($_POST['confirmation'] ?? '');
        $cgv = $this->bool('cgv');

        $erreurs = [];
        if ($nom === '' || $prenom === '') {
            $erreurs[] = 'Nom et prénom sont obligatoires.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs[] = 'Adresse email invalide.';
        }
        if (strlen($motDePasse) < 8) {
            $erreurs[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }
        if ($motDePasse !== $confirmation) {
            $erreurs[] = 'La confirmation du mot de passe ne correspond pas.';
        }
        if (!$cgv) {
            $erreurs[] = 'Vous devez accepter les conditions générales de vente.';
        }

        if ($erreurs === []) {
            try {
                $repo = new ClientRepository(Database::store());
                $id = $repo->createAccount($nom, $prenom, $email, password_hash($motDePasse, PASSWORD_BCRYPT));
                Auth::connecterClient($id);
                $this->flashSucces('Bienvenue ' . $prenom . ' ! Votre compte est créé.');
                $this->rediriger('/compte');
            } catch (BusinessError $e) {
                $erreurs[] = $e->messageHumain();
            }
        }

        $this->render('auth/inscription', [
            'erreurs' => $erreurs,
            'valeurs' => ['nom' => $nom, 'prenom' => $prenom, 'email' => $email],
        ], 'Créer un compte');
    }

    public function formulaireConnexion(): void
    {
        $this->render('auth/connexion', ['erreurs' => [], 'email' => ''], 'Connexion');
    }

    public function connexion(): void
    {
        $this->verifierCsrf();
        $email = $this->str('email', 190);
        $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');

        $repo = new ClientRepository(Database::store());
        $identifiants = $repo->getCredentials($email);

        $succes = false;
        if ($identifiants['statut'] === 'OK' && password_verify($motDePasse, (string) $identifiants['hash'])) {
            $succes = true;
        }

        if ($succes) {
            Auth::connecterClient((int) $identifiants['id_client']);
            $repo->noteConnexion((int) $identifiants['id_client']);
            $this->flashSucces('Connexion réussie.');
            $cible = $_SESSION['cible_apres_connexion'] ?? '/compte';
            unset($_SESSION['cible_apres_connexion']);
            $this->rediriger($cible);
        }

        // message identique quel que soit le motif (SEC-05)
        $erreur = $identifiants['statut'] === 'COMPTE_BLOQUE'
            ? 'Ce compte est bloqué. Contactez le support.'
            : 'Email ou mot de passe erroné.';
        $this->render('auth/connexion', ['erreurs' => [$erreur], 'email' => $email], 'Connexion');
    }

    public function deconnexion(): void
    {
        Auth::deconnecterClient();
        $this->flashSucces('Vous êtes déconnecté.');
        $this->rediriger('/');
    }

    // ------------------------------------------------------------- admin

    public function formulaireConnexionAdmin(): void
    {
        $this->render('auth/admin_connexion', ['erreurs' => [], 'email' => ''], 'Back-office — connexion');
    }

    public function connexionAdmin(): void
    {
        $this->verifierCsrf();
        $email = $this->str('email', 190);
        $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');

        $admin = Auth::verifierAdmin($email, $motDePasse);
        if ($admin === null) {
            $this->render('auth/admin_connexion', ['erreurs' => ['Email ou mot de passe erroné.'], 'email' => $email], 'Back-office — connexion');

            return;
        }

        Auth::connecterAdmin((int) $admin['id_admin']);
        Database::store()->setActor(['id' => (int) $admin['id_admin'], 'role' => 'ADMIN', 'nom' => $admin['prenom'] . ' ' . $admin['nom']]);
        $this->flashSucces('Bienvenue dans le back-office, ' . $admin['prenom'] . '.');
        $this->rediriger('/admin');
    }

    public function deconnexionAdmin(): void
    {
        Auth::deconnecterAdmin();
        $this->rediriger('/');
    }
}
