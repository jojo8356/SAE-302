<?php

declare(strict_types=1);

/**
 * MiniShop — paramètres commerciaux du back-office (EF-ADM-11).
 *
 * Objet : rendre pilotables SANS déploiement les valeurs qui engagent
 * l'affichage légal du prix — frais de port standard, franchise de port
 * offert, TVA par défaut, durée de conservation des comptes.
 *
 * Ces valeurs étaient déjà des données (`parametre`) mais n'étaient
 * modifiables par aucun écran : c'est l'écart corrigé ici (audit `09` §G).
 *
 * Sécurité : route gardée `admin` (RB-13), CSRF obligatoire, valeur
 * numérique validée côté serveur, écriture journalisée par le moteur
 * (EF-GEN-04) — l'auteur est posé par setActor().
 */

namespace App\Controller\Admin;

use App\Config\Database;
use App\Controller\Controller;
use App\Model\Data\BusinessError;
use App\Repository\ParametreRepository;
use App\Security\Auth;

final class ParametreAdminController extends Controller
{
    /** Liste des paramètres éditables et de leur libellé métier. */
    private const LIBELLES = [
        'frais_port' => 'Frais de port standard (EUR TTC)',
        'franchise_port' => 'Port offert à partir de (EUR TTC de marchandises)',
        'tva_defaut' => 'Taux de TVA par défaut des nouveaux produits (%)',
        'conservation_compte' => 'Délai des comptes inactifs sans commande (mois)',
    ];

    public function index(): void
    {
        Auth::exigeAdmin();
        $store = Database::store();

        $this->render('admin/parametres', [
            'parametres' => (new ParametreRepository($store))->tous(),
            'libelles' => self::LIBELLES,
            'erreurs' => [],
        ], 'Back-office — paramètres');
    }

    public function enregistrer(): void
    {
        $this->verifierCsrf();
        Auth::exigeAdmin();
        $store = Database::store();
        $admin = Auth::admin();

        $repository = new ParametreRepository($store);
        $erreurs = [];

        foreach (array_keys(self::LIBELLES) as $cle) {
            $valeur = $this->str('param_' . $cle, 20);
            if ($valeur === '') {
                continue;
            }
            try {
                $store->setActor([
                    'id' => $admin['id'],
                    'role' => 'ADMIN',
                    'nom' => $admin['prenom'] . ' ' . $admin['nom'],
                ]);
                $repository->enregistrer($cle, $valeur);
            } catch (BusinessError $e) {
                $erreurs[] = self::LIBELLES[$cle] . ' : ' . $e->messageHumain();
            }
        }

        if ($erreurs !== []) {
            foreach ($erreurs as $erreur) {
                $this->flashErreur($erreur);
            }
            $this->rediriger('/admin/parametres');
        }

        $this->flashSucces('Paramètres enregistrés : les prochains calculs de port et de TVA en tiennent compte.');
        $this->rediriger('/admin/parametres');
    }
}
