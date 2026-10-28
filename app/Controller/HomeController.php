<?php

declare(strict_types=1);

/**
 * MiniShop — pages d'accueil, catégories et pages légales statiques
 * (UC-01 « Consulter les catégories », EF-GEN-05).
 */

namespace App\Controller;

use App\Config\Database;
use App\Model\Data\Views;
use App\Repository\CategorieRepository;
use App\Repository\ProduitRepository;
use App\Security\Csrf;

final class HomeController extends Controller
{
    public function index(): void
    {
        $store = Database::store();
        $categories = (new CategorieRepository($store))->listerAvecCompteur();
        $nouveautes = (new ProduitRepository($store))->searchProducts(['tri' => 'nouveaute', 'par_page' => 4]);

        $this->render('home/index', ['categories' => $categories, 'nouveautes' => $nouveautes['lignes']], 'MiniShop — matériel informatique & objets connectés');
    }

    public function categories(): void
    {
        $store = Database::store();
        $categories = (new CategorieRepository($store))->listerAvecCompteur();

        $this->render('home/categories', ['categories' => $categories], 'Nos catégories');
    }

    public function mentionsLegales(): void
    {
        $this->render('home/mentions_legales', [], 'Mentions légales');
    }

    public function cgv(): void
    {
        $this->render('home/cgv', [], 'Conditions générales de vente');
    }

    public function confidentialite(): void
    {
        $this->render('home/confidentialite', [], 'Confidentialité');
    }

    public function cookies(): void
    {
        $this->render('home/cookies', [], 'Cookies et traceurs');
    }

    public function livraison(): void
    {
        $this->render('home/livraison', [], 'Livraison');
    }

    public function retractation(): void
    {
        $this->render('home/retractation', [], 'Rétractation');
    }

    public function garanties(): void
    {
        $this->render('home/garanties', [], 'Garanties légales');
    }

    public function cgu(): void
    {
        $this->render('home/cgu', [], 'Conditions d’utilisation');
    }

    /** Page d'aide (raccourcis, FAQ courte, accessibilité) — EF-VIS-08. */
    public function aide(): void
    {
        $this->render('home/aide', [], 'Aide');
    }

    /** Formulaire de contact (EF-GEN-05) — affichage. */
    public function formulaireContact(): void
    {
        $this->render('home/contact', ['erreurs' => [], 'valeurs' => []], 'Nous contacter');
    }

    /** Traitement de démonstration : stockage local, sans service de courrier. */
    public function envoyerContact(): void
    {
        Csrf::verifier();

        $nom = $this->str('nom', 120);
        $email = $this->str('email', 190);
        $message = $this->str('message', 2000);
        $erreurs = [];

        if ($nom === '') {
            $erreurs[] = 'Le nom est obligatoire.';
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $erreurs[] = 'Une adresse email valide est obligatoire.';
        }
        if (mb_strlen($message) < 10) {
            $erreurs[] = 'Le message doit contenir au moins 10 caractères.';
        }

        if ($erreurs !== []) {
            $this->render('home/contact', [
                'erreurs' => $erreurs,
                'valeurs' => ['nom' => $nom, 'email' => $email, 'message' => $message],
            ], 'Nous contacter');

            return;
        }

        if (!$this->journaliserContact($nom, $email, $message)) {
            $this->flashErreur('Impossible d’enregistrer le message de démonstration. Aucun message n’a été transmis.');
            $this->rediriger('/contact');
        }
        $this->flashSucces('Message de démonstration enregistré localement. Aucun e-mail n’a été envoyé et aucune réponse n’est garantie.');
        $this->rediriger('/contact');
    }

    /** Trace minimale du message (hors base, sans mot de passe ni IP). */
    private function journaliserContact(string $nom, string $email, string $message): bool
    {
        $dossier = MINISHOP_ROOT . '/var/log';
        if (!is_dir($dossier) && !@mkdir($dossier, 0o700, true) && !is_dir($dossier)) {
            return false;
        }

        $ligne = json_encode([
            'date' => date('c'),
            'nom' => $nom,
            'email' => $email,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($ligne === false) {
            return false;
        }
        $fichier = $dossier . '/contact.jsonl';
        // Un umask restrictif protège aussi la création initiale du fichier.
        $ancienMasque = umask(0o077);
        $ecrit = @file_put_contents($fichier, $ligne . "\n", FILE_APPEND | LOCK_EX);
        umask($ancienMasque);
        if ($ecrit !== false) {
            @chmod($fichier, 0o600);
        }

        return $ecrit === strlen($ligne . "\n");
    }

    /** Vue de synthèse du moteur de données (page « à propos » du projet). */
    public function aPropos(): void
    {
        $store = Database::store();
        $statistiques = [
            'produits' => $store->count('produit'),
            'visibles' => $store->count('produit', [['visible', '=', 1]]),
            'clients' => $store->count('client'),
            'commandes' => $store->count('commande'),
            'parametres' => $store->count('parametre'),
        ];

        $this->render('home/apropos', ['statistiques' => $statistiques], 'À propos du moteur de données');
    }

    public function page404(): void
    {
        $this->render('errors/404', [], 'Page introuvable');
    }

    public function page403(): void
    {
        $this->render('errors/403', [], 'Accès refusé');
    }
}
