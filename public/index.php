<?php

declare(strict_types=1);

/**
 * MiniShop — contrôleur frontal (§9.3) : TOUTE requête HTTP passe ici.
 *
 * Sécurité (SEC-01…) :
 *   - en-têtes durcis (X-Frame-Options, CSP sans style inline, session cookie
 *     HttpOnly/SameSite) ;
 *   - routeur à liste blanche : aucune URL dynamique, aucune inclusion de
 *     fichier pilotée par la requête (anti-LFI/RFI) ;
 *   - jeton CSRF obligatoire sur chaque POST (SEC-05) ;
 *   - les erreurs métier (BusinessError) ne divulguent rien : message humain
 *     en flash, redirection ; les erreurs techniques affichent une page 500
 *     générique (SEC-03), le détail n'est visible qu'en mode DEBUG.
 *
 * Moteur de données : JSON par défaut (StorageDriver), prêt pour la
 * migration SQL finale sans toucher aux contrôleurs.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Controller\Admin\CategorieAdminController;
use App\Controller\Admin\CommandeAdminController;
use App\Controller\Admin\DashboardController;
use App\Controller\Admin\EquipeAdminController;
use App\Controller\Admin\ProduitAdminController;
use App\Controller\Admin\StockAdminController;
use App\Controller\AuthController;
use App\Controller\CatalogueController;
use App\Controller\CommandeController;
use App\Controller\CompteController;
use App\Controller\HomeController;
use App\Controller\PanierController;
use App\Model\Data\BusinessError;
use App\Router;
use App\Security\Auth;

// ----------------------------------------------------------- en-têtes HTTP
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
header('X-XSS-Protection: 0'); // navigation modernes : on s'appuie sur l'échappement, pas sur ce filtre hérité

Auth::demarrer();

$routeur = new Router();

// ------------------------------------------------------------ front-office
$routeur->get('/', [HomeController::class, 'index']);
$routeur->get('/categories', [HomeController::class, 'categories']);
$routeur->get('/a-propos', [HomeController::class, 'aPropos']);
$routeur->get('/mentions-legales', [HomeController::class, 'mentionsLegales']);
$routeur->get('/cgv', [HomeController::class, 'cgv']);

$routeur->get('/catalogue', [CatalogueController::class, 'catalogue']);
$routeur->get('/recherche', [CatalogueController::class, 'recherche']);
$routeur->get('/produit/{slug}', [CatalogueController::class, 'produit']);

$routeur->get('/inscription', [AuthController::class, 'formulaireInscription']);
$routeur->post('/inscription', [AuthController::class, 'inscription']);
$routeur->get('/connexion', [AuthController::class, 'formulaireConnexion']);
$routeur->post('/connexion', [AuthController::class, 'connexion']);
$routeur->get('/deconnexion', [AuthController::class, 'deconnexion']);

$routeur->get('/compte', [CompteController::class, 'index'], 'client');
$routeur->post('/compte', [CompteController::class, 'enregistrer'], 'client');
$routeur->post('/compte/mot-de-passe', [CompteController::class, 'motDePasse'], 'client');

$routeur->get('/panier', [PanierController::class, 'index']);
$routeur->post('/panier/ajouter', [PanierController::class, 'ajouter']);
$routeur->post('/panier/quantite', [PanierController::class, 'quantite']);
$routeur->post('/panier/retirer', [PanierController::class, 'retirer']);

$routeur->get('/commande/valider', [CommandeController::class, 'formulaire'], 'client');
$routeur->post('/commande/valider', [CommandeController::class, 'valider'], 'client');
$routeur->get('/mes-commandes', [CommandeController::class, 'mes'], 'client');
$routeur->get('/mes-commandes/{id}', [CommandeController::class, 'detail'], 'client');
$routeur->post('/mes-commandes/{id}/annulation', [CommandeController::class, 'annuler'], 'client');

// ------------------------------------------------------------ back-office
$routeur->get('/admin/connexion', [AuthController::class, 'formulaireConnexionAdmin']);
$routeur->post('/admin/connexion', [AuthController::class, 'connexionAdmin']);
$routeur->get('/admin/deconnexion', [AuthController::class, 'deconnexionAdmin']);

$routeur->get('/admin', [DashboardController::class, 'index'], 'admin');

$routeur->get('/admin/produits', [ProduitAdminController::class, 'index'], 'admin');
$routeur->get('/admin/produit/nouveau', [ProduitAdminController::class, 'formulaireNouveau'], 'admin');
$routeur->post('/admin/produit/nouveau', [ProduitAdminController::class, 'creer'], 'admin');
$routeur->get('/admin/produit/{id}/modification', [ProduitAdminController::class, 'formulaireModifier'], 'admin');
$routeur->post('/admin/produit/{id}/modification', [ProduitAdminController::class, 'enregistrerModification'], 'admin');
$routeur->post('/admin/produits/{id}/suppression', [ProduitAdminController::class, 'supprimer'], 'admin');

$routeur->get('/admin/categories', [CategorieAdminController::class, 'index'], 'admin');
$routeur->post('/admin/categories', [CategorieAdminController::class, 'creer'], 'admin');
$routeur->post('/admin/categories/{id}/modification', [CategorieAdminController::class, 'modifier'], 'admin');
$routeur->post('/admin/categories/{id}/suppression', [CategorieAdminController::class, 'supprimer'], 'admin');

$routeur->get('/admin/stocks', [StockAdminController::class, 'index'], 'admin');
$routeur->post('/admin/stocks', [StockAdminController::class, 'ajuster'], 'admin');

$routeur->get('/admin/commandes', [CommandeAdminController::class, 'index'], 'admin');
$routeur->get('/admin/commandes/{id}', [CommandeAdminController::class, 'detail'], 'admin');
$routeur->post('/admin/commandes/{id}/statut', [CommandeAdminController::class, 'changerStatut'], 'admin');

$routeur->get('/admin/equipe', [EquipeAdminController::class, 'index'], 'super');
$routeur->post('/admin/equipe', [EquipeAdminController::class, 'creer'], 'super');
$routeur->post('/admin/equipe/{id}/basculer', [EquipeAdminController::class, 'basculer'], 'super');

// ------------------------------------------------------------- dispatch
try {
    $routeur->executer($routeur->resoudre($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/'));
} catch (BusinessError $e) {
    // erreur métier remontée jusqu'ici : message humain, aucun détail interne
    $_SESSION['flash_erreur'] = $e->messageHumain();
    Auth::rediriger($_SERVER['HTTP_REFERER'] ?? '/');
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[MiniShop] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $debug = \App\Config\Database::config()['debug'] ?? false;
    include __DIR__ . '/../app/View/errors/500.php';
}
