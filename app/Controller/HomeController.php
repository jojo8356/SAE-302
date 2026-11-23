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
use App\Repository\ParametreRepository;
use App\Repository\ProduitRepository;
use App\Security\Csrf;
use App\Service\NettoyageJournaux;

final class HomeController extends Controller
{
    /** Affiche la page d'accueil avec les catégories et les 4 nouveautés. */
    public function index(): void
    {
        $store = Database::store();
        $categories = (new CategorieRepository($store))->listerAvecCompteur();
        $nouveautes = (new ProduitRepository($store))->searchProducts(['tri' => 'nouveaute', 'par_page' => 4]);

        $this->render('home/index', ['categories' => $categories, 'nouveautes' => $nouveautes['lignes']], 'MiniShop — matériel informatique & objets connectés');
    }

    /** Affiche la liste des catégories actives et leur compteur de produits. */
    public function categories(): void
    {
        $store = Database::store();
        $categories = (new CategorieRepository($store))->listerAvecCompteur();

        $this->render('home/categories', ['categories' => $categories], 'Nos catégories');
    }

    /** Affiche les mentions légales LCEN de la maquette pédagogique. */
    public function mentionsLegales(): void
    {
        $this->render('home/mentions_legales', [], 'Mentions légales');
    }

    /** Affiche les conditions générales de vente (CGV). */
    public function cgv(): void
    {
        $this->render('home/cgv', [], 'Conditions générales de vente');
    }

    /** Affiche la politique de confidentialité et les durées RGPD. */
    public function confidentialite(): void
    {
        $this->render('home/confidentialite', [], 'Confidentialité');
    }

    /** Affiche la politique d'information sur les cookies et traceurs. */
    public function cookies(): void
    {
        $this->render('home/cookies', [], 'Cookies et traceurs');
    }

    /** Affiche les informations générales de livraison et retours. */
    public function livraison(): void
    {
        $this->render('home/livraison', [], 'Livraison');
    }

    /** Affiche la page d'information sur le droit de rétractation de 14 jours. */
    public function retractation(): void
    {
        $this->render('home/retractation', [], 'Rétractation');
    }

    /** Affiche l'encadré réglementaire des garanties légales. */
    public function garanties(): void
    {
        $this->render('home/garanties', [], 'Garanties légales');
    }

    /** Affiche les conditions générales d'utilisation (CGU). */
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
        return NettoyageJournaux::ajouter($fichier, $ligne . "\n");
    }

    /** Vue de synthèse du moteur de données (page « à propos » du projet). */
    public function aPropos(): void
    {
        $store = Database::store();
        $statistiques = [
            'produits' => $store->count('produit'),
            'visibles' => $store->count('produit', [['visible', '=', 1]]),
            'categories' => $store->count('categorie'),
        ];

        $this->render('home/apropos', [
            'statistiques' => $statistiques,
            'parametres' => (new ParametreRepository($store))->tous(),
        ], 'À propos de MiniShop');
    }

    /** Préférences locales au navigateur, sans données de compte. */
    public function preferences(): void
    {
        $this->render('home/preferences', [], 'Préférences');
    }

    /** Affiche la page d'erreur 404 personnalisée (route inconnue). */
    public function page404(): void
    {
        $this->render('errors/404', [], 'Page introuvable');
    }

    /** Affiche la page d'erreur 403 personnalisée (accès refusé). */
    public function page403(): void
    {
        $this->render('errors/403', [], 'Accès refusé');
    }
}
