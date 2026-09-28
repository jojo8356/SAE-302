<?php

declare(strict_types=1);

/**
 * MiniShop — espace client : informations personnelles (EF-CLI-01) et
 * changement de mot de passe (EF-CLI-02 : ancien mot de passe exigé, re-hash).
 */

namespace App\Controller;

use App\Config\Database;
use App\Model\Data\BusinessError;
use App\Repository\ClientRepository;
use App\Security\Auth;

final class CompteController extends Controller
{
    public function index(): void
    {
        $client = Auth::exigeClient();
        $donnees = (new ClientRepository(Database::store()))->find($client['id']);
        $this->render('compte/index', [
            'client' => $donnees,
            'erreurs' => [],
            'succes' => null,
        ], 'Mon compte');
    }

    /** EF-CLI-01 — modification de ses informations (email unique ré-contrôlé). */
    public function enregistrer(): void
    {
        $this->verifierCsrf();
        $session = Auth::exigeClient();
        $repo = new ClientRepository(Database::store());

        $nom = $this->str('nom', 100);
        $prenom = $this->str('prenom', 100);
        $email = $this->str('email', 190);
        $telephone = $this->str('telephone', 20);
        $adresse = $this->str('adresse', 255);
        $codePostal = $this->str('code_postal', 10);
        $ville = $this->str('ville', 100);

        $erreurs = [];
        if ($nom === '' || $prenom === '') {
            $erreurs[] = 'Nom et prénom sont obligatoires.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs[] = 'Adresse email invalide.';
        }

        if ($erreurs === []) {
            try {
                $repo->updateClient($session['id'], $nom, $prenom, $email, $telephone, $adresse, $codePostal, $ville);
                $this->flashSucces('Informations enregistrées.');
                $this->rediriger('/compte');
            } catch (BusinessError $e) {
                $erreurs[] = $e->messageHumain();
            }
        }

        $this->render('compte/index', [
            'client' => ['nom' => $nom, 'prenom' => $prenom, 'email' => $email,
                'telephone' => $telephone, 'adresse_livraison' => $adresse, 'code_postal' => $codePostal, 'ville' => $ville],
            'erreurs' => $erreurs,
            'succes' => null,
        ], 'Mon compte');
    }

    /** EF-CLI-02 — changement de mot de passe (ancien exigé, re-hash RB-12). */
    public function motDePasse(): void
    {
        $this->verifierCsrf();
        $session = Auth::exigeClient();
        $repo = new ClientRepository(Database::store());

        $ancien = (string) ($_POST['ancien_mot_de_passe'] ?? '');
        $nouveau = (string) ($_POST['nouveau_mot_de_passe'] ?? '');
        $confirmation = (string) ($_POST['confirmation'] ?? '');

        $identifiants = $repo->getCredentials($session['email']);
        $client = $repo->find($session['id']);

        $erreurs = [];
        if ($identifiants['statut'] !== 'OK' || !password_verify($ancien, (string) ($client['mot_de_passe_hash'] ?? ''))) {
            $erreurs[] = 'Ancien mot de passe incorrect.';
        }
        if (strlen($nouveau) < 8) {
            $erreurs[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        }
        if ($nouveau !== $confirmation) {
            $erreurs[] = 'La confirmation ne correspond pas.';
        }

        if ($erreurs === []) {
            $repo->changeMotDePasse($session['id'], password_hash($nouveau, PASSWORD_BCRYPT));
            $this->flashSucces('Mot de passe mis à jour.');
            $this->rediriger('/compte');
        }

        $this->render('compte/index', ['client' => $client, 'erreurs' => $erreurs, 'succes' => null], 'Mon compte');
    }
}
