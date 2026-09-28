<?php

declare(strict_types=1);

/**
 * MiniShop — gestion des comptes administrateurs (EF-ADM-10, RB-12/13).
 * Réservé au rôle SUPER ; l'administrateur ne gère JAMAIS les comptes
 * clients (RB-13 : le back-office ne crée pas de compte client).
 */

namespace App\Controller\Admin;

use App\Config\Database;
use App\Controller\Controller;
use App\Model\Data\BusinessError;
use App\Model\Data\Filter;
use App\Security\Auth;

final class EquipeAdminController extends Controller
{
    public function index(): void
    {
        Auth::exigeSuper();
        $admins = Database::store()->select('administrateur', ['order' => [Filter::sort('nom')]]);

        $this->render('admin/equipe', ['admins' => $admins, 'erreurs' => []], 'Back-office — équipe');
    }

    public function creer(): void
    {
        $this->verifierCsrf();
        Auth::exigeSuper();
        $store = Database::store();

        $nom = $this->str('nom', 100);
        $prenom = $this->str('prenom', 100);
        $email = strtolower($this->str('email', 190));
        $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');
        $role = $this->str('role', 20) === 'SUPER' ? 'SUPER' : 'GESTIONNAIRE';

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
        if ($erreurs === [] && $store->exists('administrateur', [Filter::eq('email', $email)])) {
            $erreurs[] = 'Cette adresse email est déjà utilisée.';
        }

        if ($erreurs === []) {
            try {
                $store->insert('administrateur', [
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'mot_de_passe_hash' => password_hash($motDePasse, PASSWORD_BCRYPT), // RB-12
                    'role' => $role,
                ]);
                $this->flashSucces('Compte administrateur créé.');
                $this->rediriger('/admin/equipe');
            } catch (BusinessError $e) {
                $erreurs[] = $e->messageHumain();
            }
        }

        $this->render('admin/equipe', [
            'admins' => $store->select('administrateur', ['order' => [Filter::sort('nom')]]),
            'erreurs' => $erreurs,
        ], 'Back-office — équipe');
    }

    /** Activer / bloquer un compte admin (jamais de suppression : piste conservée). */
    public function basculer(string $id): void
    {
        $this->verifierCsrf();
        $session = Auth::exigeSuper();
        $store = Database::store();

        $admin = $store->find('administrateur', (int) $id);
        if ($admin === null) {
            $this->flashErreur('Compte introuvable.');
            $this->rediriger('/admin/equipe');
        }
        if ((int) $admin['id_admin'] === $session['id']) {
            $this->flashErreur('Impossible de bloquer votre propre compte.');
            $this->rediriger('/admin/equipe');
        }

        $store->setActor(['id' => $session['id'], 'role' => 'ADMIN', 'nom' => $session['prenom'] . ' ' . $session['nom']]);
        $store->update('administrateur', [Filter::eq('id_admin', (int) $id)], ['actif' => !$admin['actif']]);
        $this->flashSucces('Compte ' . ($admin['actif'] ? 'bloqué' : 'réactivé') . '.');
        $this->rediriger('/admin/equipe');
    }
}
