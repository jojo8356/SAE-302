import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { JSDOM, VirtualConsole } from 'jsdom';
import { createPhp } from '../../scripts/wasm_lib.mjs';

const i18n = await readFile('public/js/i18n-en.js', 'utf8');
const js = await readFile('public/js/preferences.js', 'utf8');
const uiJs = await readFile('public/js/ui.js', 'utf8');
const tz = await readFile('public/js/tz-lookup.js', 'utf8');

const html = `<!doctype html><html lang="fr"><head><meta name="description" content="MiniShop — catalogue en ligne de matériel informatique et d'objets connectés. Prix TTC, stock en temps réel, commande et suivi."><title>Préférences — MiniShop</title></head><body><nav><a>Catalogue</a></nav><div class="preferences"><h1>Préférences</h1><form id="form-preferences" hidden>
<select id="pref-langue"><option value="fr" lang="fr">Français</option><option value="en" lang="en">English</option></select><select id="pref-zone"></select>
<p id="pref-decalage"></p><button id="pref-position" type="button">Utiliser ma position</button><button type="submit">Enregistrer</button><button id="pref-reinitialiser" type="button">Réinitialiser</button><p id="pref-etat"></p></form></div>
<textarea id="msg" data-compteur="500"></textarea>
<time data-date-format="datetime" datetime="2026-01-15T18:15:00Z"></time><time data-date-format="datetime" datetime="2026-07-15T18:15:00Z"></time>
<form data-filtre-jours><input name="du" value="2026-03-29"><input name="au" value="2026-03-29"><input name="debut_utc"><input name="fin_utc"></form></body></html>`;

function setup({ langue = 'fr-FR', position, stocke, stockageBloque = false, url = 'https://minishop.test', htmlDoc = html, silenceNavigation = false } = {}) {
    const opts = { url, runScripts: 'outside-only' };
    if (silenceNavigation) opts.virtualConsole = new VirtualConsole();
    const dom = new JSDOM(htmlDoc, opts);
    const w = dom.window;
    w.fetch = () => new Promise(() => {});
    Object.defineProperty(w.navigator, 'language', { value: langue });
    if (position) Object.defineProperty(w.navigator, 'geolocation', { value: position });
    if (stocke) w.localStorage.setItem('minishop.preferences.v1', JSON.stringify(stocke));
    if (stockageBloque) Object.defineProperty(w, 'localStorage', { get() { throw Error('bloqué'); } });
    w.eval(uiJs);
    w.eval(tz);
    w.eval(i18n);
    w.eval(js);
    return w;
}

const val = (w, selector) => w.document.querySelector(selector).textContent;

// 1. Fuseaux, heure d'été/hiver, géolocalisation et stockage local
let w = setup();
assert.equal(w.document.documentElement.lang, 'fr');
assert.equal(w.document.title, 'Préférences — MiniShop');
assert.equal(w.document.querySelector('time').textContent, '15/01/2026 19h15');
assert.equal(w.document.querySelectorAll('time')[1].textContent, '15/07/2026 20h15');
assert.equal(w.document.querySelector('#form-preferences').hidden, false);
w.document.querySelector('[data-filtre-jours]').addEventListener('submit', (e) => e.preventDefault());
w.document.querySelector('[data-filtre-jours]').requestSubmit();
assert.equal(w.document.querySelector('[name=debut_utc]').value, '2026-03-28 23:00:00');
assert.equal(w.document.querySelector('[name=fin_utc]').value, '2026-03-29 22:00:00');
const zone = w.document.querySelector('#pref-zone');
zone.value = 'America/New_York';
w.document.querySelector('#form-preferences').requestSubmit();
assert.equal(w.document.querySelector('time').textContent, '15/01/2026 13h15');
assert.equal(w.document.querySelectorAll('time')[1].textContent, '15/07/2026 14h15');
assert.deepEqual(JSON.parse(w.localStorage.getItem('minishop.preferences.v1')), { langue: 'fr', zone: 'America/New_York' });
w.document.querySelector('[data-filtre-jours]').requestSubmit();
assert.equal(w.document.querySelector('[name=debut_utc]').value, '2026-03-29 04:00:00');
assert.equal(w.document.querySelector('[name=fin_utc]').value, '2026-03-30 04:00:00');

// 2. Défaut anglais (New York, format US, titre, attribut lang, compteur dynamique et aller-retour FR <-> EN)
w = setup({ langue: 'en-US' });
assert.equal(w.document.documentElement.lang, 'en');
assert.equal(w.document.title, 'Preferences — MiniShop');
assert.match(w.document.querySelector('meta[name="description"]').getAttribute('content'), /online catalog of computer hardware/);
assert.equal(w.document.querySelector('time').textContent, '01/15/2026 13:15');
assert.equal(val(w, 'nav a'), 'Catalog');
assert.equal(val(w, '#msg-compteur'), '0 / 500 characters');
w.document.querySelector('#msg').value = 'Hello';
w.document.querySelector('#msg').dispatchEvent(new w.Event('input'));
assert.equal(val(w, '#msg-compteur'), '5 / 500 characters');
assert.equal(w.document.querySelector('#pref-zone').value, '');
assert.match(val(w, '#pref-decalage'), /America\/New_York · UTC/);

// Retour en français sans perte et préservation de la saisie utilisateur
w.document.querySelector('#pref-langue').value = 'fr';
w.document.querySelector('#form-preferences').requestSubmit();
assert.equal(w.document.documentElement.lang, 'fr');
assert.equal(w.document.title, 'Préférences — MiniShop');
assert.equal(val(w, 'nav a'), 'Catalogue');
assert.equal(w.document.querySelector('#msg').value, 'Hello');

// 3. Géolocalisation sur clic uniquement, erreurs et stockage indisponible
let appels = 0;
w = setup({ position: { getCurrentPosition(ok) { appels++; ok({ coords: { latitude: 43.70, longitude: 7.26 } }); } } });
assert.equal(appels, 0, 'pas de géolocalisation sans clic');
w.document.querySelector('#pref-position').click();
assert.equal(appels, 1);
assert.equal(w.document.querySelector('#pref-zone').value, 'Europe/Paris');
assert.equal(w.localStorage.length, 0, 'position non enregistrée avant validation');
w.document.querySelector('#form-preferences').requestSubmit();
assert.ok(!w.localStorage.getItem('minishop.preferences.v1').includes('43.7'), 'coordonnées jamais sauvegardées');
w.document.querySelector('#pref-reinitialiser').click();
assert.equal(w.localStorage.length, 0);

w = setup({ position: { getCurrentPosition(ok, erreur) { erreur({ code: 1 }); } } });
w.document.querySelector('#pref-position').click();
assert.match(val(w, '#pref-etat'), /refusée/);

w = setup({ langue: 'en-US', position: { getCurrentPosition(ok, erreur) { erreur({ code: 1 }); } } });
w.document.querySelector('#pref-position').click();
assert.match(val(w, '#pref-etat'), /Location denied/);

w = setup({ stockageBloque: true });
w.document.querySelector('#form-preferences').requestSubmit();
assert.match(val(w, '#pref-etat'), /Stockage indisponible/);

w = setup({ stocke: { langue: 'en', zone: 'Nonexistent/X' } });
assert.equal(w.document.querySelector('time').textContent, '01/15/2026 13:15');

// 4. Recalcul des bornes UTC si une URL filtrée contient d'anciennes bornes d'un autre fuseau
w = setup({
    stocke: { langue: 'fr', zone: 'Asia/Kathmandu' },
    url: 'https://minishop.test/admin/commandes?du=2026-03-29&au=2026-03-29&debut_utc=2026-03-28+23%3A00%3A00&fin_utc=2026-03-29+22%3A00%3A00',
    silenceNavigation: true,
});
assert.equal(w.document.querySelector('[name=debut_utc]').value, '2026-03-28 18:15:00');
assert.equal(w.document.querySelector('[name=fin_utc]').value, '2026-03-29 18:15:00');

// 5. Couverture complète FR <-> EN sur l'ensemble des vues PHP (front, légal, client, back-office) et BusinessError
const php = await createPhp();
const rendu = await php.run({
    code: `<?php
require '/repo/app/bootstrap.php';
use App\\Model\\Data\\BusinessError;

$categories = [
  ['id_categorie' => 1, 'nom' => 'Informatique', 'slug' => 'informatique', 'description' => 'Ordinateurs portables, périphériques et composants', 'nb_produits' => 4],
  ['id_categorie' => 2, 'nom' => 'Audio', 'slug' => 'audio', 'description' => 'Casques, enceintes et microphones', 'nb_produits' => 1],
  ['id_categorie' => 3, 'nom' => 'Accessoires', 'slug' => 'accessoires', 'description' => 'Sacs, câbles, supports et batteries', 'nb_produits' => 3],
  ['id_categorie' => 4, 'nom' => 'Objets connectés', 'slug' => 'objets-connectes', 'description' => 'Montres et bracelets connectés', 'nb_produits' => 2],
];
$produitStock = ['id_produit' => 1, 'reference' => 'REF-001', 'nom' => 'Clavier Test', 'slug' => 'clavier-test', 'categorie' => 'Informatique', 'id_categorie' => 1, 'prix_ht' => 50.0, 'tva' => 20.0, 'prix_ttc' => 60.0, 'stock' => 12, 'seuil_alerte' => 3, 'etat_stock' => 'DISPONIBLE', 'visible' => 1, 'image_url' => 'img/produits/zen14.jpg', 'description' => '', 'date_creation' => '2026-01-15 18:15:00'];
$produitBas = array_merge($produitStock, ['id_produit' => 2, 'stock' => 2, 'etat_stock' => 'TRES_BAS', 'visible' => 0, 'image_url' => '', 'date_creation' => '']);
$produitRupture = array_merge($produitStock, ['id_produit' => 3, 'stock' => 0, 'etat_stock' => 'RUPTURE']);
$parametres = [
  ['cle' => 'frais_port', 'valeur' => '4.90', 'unite' => 'EUR TTC', 'comment' => 'Livraison standard, montant affiché avant la validation (art. L221-5 4° C. conso.)'],
  ['cle' => 'franchise_port', 'valeur' => '80.00', 'unite' => 'EUR TTC', 'comment' => 'Port offert au-delà de ce montant de marchandises'],
  ['cle' => 'tva_defaut', 'valeur' => '20.00', 'unite' => '%', 'comment' => 'Taux par défaut proposé à la création d’un produit'],
  ['cle' => 'conservation_compte', 'valeur' => '24', 'unite' => 'mois', 'comment' => 'Délai en mois depuis la dernière activité du compte ; traitement planifié à installer'],
];
$libellesParam = [
  'frais_port' => 'Frais de port standard (EUR TTC)',
  'franchise_port' => 'Port offert à partir de (EUR TTC de marchandises)',
  'tva_defaut' => 'Taux de TVA par défaut des nouveaux produits (%)',
  'conservation_compte' => 'Délai des comptes inactifs sans commande (mois)',
];
$tris = ['nom' => 'Nom (A→Z)', 'prix_asc' => 'Prix croissant', 'prix_desc' => 'Prix décroissant', 'nouveaute' => 'Nouveautés', 'stock' => 'Stock'];
$recap = [
  'articles' => [['id_produit' => 1, 'nom' => 'Clavier Test', 'slug' => 'clavier-test', 'reference' => 'REF-001', 'prix_unitaire' => 60.0, 'quantite' => 2, 'stock_disponible' => 2, 'total_ligne' => 120.0, 'plafonne' => true]],
  'sous_total' => 120.0, 'frais_port' => 0.0, 'total' => 120.0, 'port_motif' => 'frais de port offerts dès 80,00 € de marchandises'
];
$commandeActive = ['id_commande' => 1, 'numero' => 'MS-2026-0001', 'date_commande' => '2026-01-15 18:15:00', 'statut' => 'PAYEE', 'nb_lignes' => 1, 'montant_total' => 64.90, 'frais_port' => 4.90, 'adresse_livraison' => '12 rue Test', 'client' => 'Alice Dupont'];
$commandeAnnulee = array_merge($commandeActive, ['statut' => 'ANNULEE', 'montant_avant_annulation' => 64.90, 'montant_total' => 0.0]);
$commandeExpediee = array_merge($commandeActive, ['statut' => 'EXPEDIEE']);
$lignesCmd = [['produit_nom' => 'Clavier Test', 'produit_reference' => 'REF-001', 'prix_unitaire' => 60.0, 'quantite' => 1, 'total_ligne' => 60.0]];
$hist = [['changed_at' => '2026-01-15 18:15:00', 'new_status' => 'PAYEE', 'changed_by_role' => 'CLIENT', 'changed_by' => 1, 'commentaire' => 'Annulation demandée par le client']];

function renderPage(string $view, array $vars = [], bool $adminMode = false, string $uri = '/', string $titre = 'Catalogue'): string {
  $_SERVER['REQUEST_URI'] = $uri;
  extract($vars, EXTR_SKIP);
  ob_start();
  require '/repo/app/View/' . $view . '.php';
  $contenu = (string) ob_get_clean();
  $titrePage = $titre;
  $client = $adminMode ? null : ['id' => 1, 'prenom' => 'Alice', 'nom' => 'Dupont', 'email' => 'alice@example.com'];
  $admin = $adminMode ? ['id' => 1, 'prenom' => 'Admin', 'nom' => 'Chef', 'email' => 'admin@minishop.fr', 'role' => 'GESTIONNAIRE'] : null;
  $flashSucces = 'Connexion réussie.';
  $flashErreur = null;
  ob_start();
  require '/repo/app/View/layout.php';
  return (string) ob_get_clean();
}

$pages = [
  'home' => renderPage('home/index', ['categories' => $categories, 'nouveautes' => [$produitStock, $produitBas, $produitRupture]], false, '/', 'MiniShop — matériel informatique & objets connectés'),
  'categories' => renderPage('home/categories', ['categories' => $categories], false, '/categories', 'Nos catégories'),
  'apropos' => renderPage('home/apropos', ['statistiques' => ['visibles' => 12, 'categories' => 4], 'parametres' => $parametres], false, '/a-propos', 'À propos de MiniShop'),
  'aide' => renderPage('home/aide', [], false, '/aide', 'Aide'),
  'contact' => renderPage('home/contact', ['erreurs' => ['Le nom est obligatoire.'], 'valeurs' => ['nom' => 'Alice', 'email' => 'alice@example.com', 'message' => 'Texte utilisateur intact']], false, '/contact', 'Nous contacter'),
  'preferences' => renderPage('home/preferences', [], false, '/preferences', 'Préférences'),
  'mentions' => renderPage('home/mentions_legales', [], false, '/mentions-legales', 'Mentions légales'),
  'cgv' => renderPage('home/cgv', [], false, '/cgv', 'Conditions générales de vente'),
  'cgu' => renderPage('home/cgu', [], false, '/cgu', 'Conditions d’utilisation'),
  'confidentialite' => renderPage('home/confidentialite', [], false, '/confidentialite', 'Confidentialité'),
  'cookies' => renderPage('home/cookies', [], false, '/cookies', 'Cookies et traceurs'),
  'livraison' => renderPage('home/livraison', [], false, '/livraison', 'Livraison'),
  'retractation' => renderPage('home/retractation', [], false, '/retractation', 'Rétractation'),
  'garanties' => renderPage('home/garanties', [], false, '/garanties', 'Garanties légales'),
  'cat_full' => renderPage('catalogue/index', ['resultat' => ['lignes' => [$produitStock], 'total' => 5, 'page' => 2, 'pages' => 3], 'criteres' => ['mot_cle' => 'test', 'id_categorie' => 1, 'prix_min' => 10.0, 'prix_max' => 100.0, 'en_stock' => true, 'tri' => 'nom'], 'categories' => $categories, 'tris' => $tris, 'parametres_url' => ['q' => 'test'], 'est_recherche' => false]),
  'cat_empty' => renderPage('catalogue/index', ['resultat' => ['lignes' => [], 'total' => 0, 'page' => 1, 'pages' => 1], 'criteres' => ['mot_cle' => '', 'id_categorie' => null, 'prix_min' => null, 'prix_max' => null, 'en_stock' => false, 'tri' => 'nom'], 'categories' => $categories, 'tris' => $tris, 'parametres_url' => [], 'est_recherche' => true], false, '/recherche', 'Recherche'),
  'prod_stock' => renderPage('catalogue/produit', ['produit' => $produitStock, 'categorie' => $categories[0], 'etat_stock' => 'DISPONIBLE']),
  'prod_rupture' => renderPage('catalogue/produit', ['produit' => $produitRupture, 'categorie' => $categories[0], 'etat_stock' => 'RUPTURE']),
  'panier_full' => renderPage('panier/index', ['recap' => $recap], false, '/panier', 'Mon panier'),
  'panier_empty' => renderPage('panier/index', ['recap' => ['articles' => [], 'sous_total' => 0.0, 'frais_port' => 0.0, 'total' => 0.0, 'port_motif' => '']], false, '/panier', 'Mon panier'),
  'valider' => renderPage('commande/valider', ['recap' => $recap, 'client' => [], 'erreurs' => [], 'adresse_livraison' => '12 rue Test'], false, '/commande/valider', 'Valider ma commande'),
  'mes_full' => renderPage('commande/mes', ['commandes' => [$commandeActive]], false, '/mes-commandes', 'Mes commandes'),
  'mes_empty' => renderPage('commande/mes', ['commandes' => []], false, '/mes-commandes', 'Mes commandes'),
  'cmd_detail_act' => renderPage('commande/detail', ['commande' => $commandeActive, 'lignes' => $lignesCmd, 'historique' => $hist], false, '/mes-commandes/1', 'Commande MS-2026-0001'),
  'cmd_detail_ann' => renderPage('commande/detail', ['commande' => $commandeAnnulee, 'lignes' => $lignesCmd, 'historique' => $hist], false, '/mes-commandes/1', 'Commande MS-2026-0001'),
  'cmd_conf_ann' => renderPage('commande/confirmer_annulation', ['commande' => $commandeActive], false, '/mes-commandes/1/annuler', 'Annuler la commande MS-2026-0001 ?'),
  'compte' => renderPage('compte/index', ['client' => ['nom' => 'Dupont', 'prenom' => 'Alice', 'email' => 'a@b.c'], 'erreurs' => []], false, '/compte', 'Mon compte'),
  'connexion' => renderPage('auth/connexion', ['erreurs' => ['Email ou mot de passe erroné.'], 'email' => 'alice@example.com'], false, '/connexion', 'Connexion'),
  'inscription' => renderPage('auth/inscription', ['erreurs' => [], 'valeurs' => []], false, '/inscription', 'Créer un compte'),
  'admin_conn' => renderPage('auth/admin_connexion', ['erreurs' => []], false, '/admin/connexion', 'Back-office — connexion'),
  'e403' => renderPage('errors/403', [], false, '/403', 'Accès refusé'),
  'e404' => renderPage('errors/404', [], false, '/404', 'Page introuvable'),
  'e500' => renderPage('errors/500', ['debug' => true], false, '/500', 'Erreur technique'),
  'adm_dash' => renderPage('admin/dashboard', ['indicateurs' => ['ca_jour' => 100.0, 'commandes_jour' => 2, 'ca_total' => 500.0, 'en_preparation' => 1, 'clients' => 3, 'produits' => 12], 'rapport' => ['par_statut' => [['statut' => 'PAYEE', 'nb_commandes' => 2, 'montant_total' => 100.0, 'montant_moyen' => 50.0]], 'top_produits' => [['nom' => 'Clavier Test', 'reference' => 'REF-001', 'unites' => 3, 'ca' => 180.0]]], 'alertes' => [['reference' => 'REF-002', 'nom' => 'Souris', 'stock' => 1, 'seuil_alerte' => 3, 'etat' => 'TRES_BAS']]], true, '/admin', 'Back-office — tableau de bord'),
  'adm_cmds' => renderPage('admin/commandes', ['commandes' => [$commandeActive], 'statuts' => ['BROUILLON','EN_PREPARATION','PAYEE','EXPEDIEE','LIVREE','ANNULEE','RETOURNEE'], 'filtres' => ['statut' => '', 'client' => '', 'du' => '', 'au' => '', 'montant_min' => null]], true, '/admin/commandes', 'Back-office — commandes'),
  'adm_cmd_act' => renderPage('admin/commande_detail', ['commande' => $commandeActive, 'client' => ['prenom' => 'Alice', 'nom' => 'Dupont', 'email' => 'a@b.c'], 'lignes' => $lignesCmd, 'historique' => $hist, 'transitions' => ['EXPEDIEE', 'ANNULEE']], true, '/admin/commandes/1', 'Commande MS-2026-0001'),
  'adm_cmd_ann' => renderPage('admin/commande_detail', ['commande' => $commandeAnnulee, 'client' => null, 'lignes' => $lignesCmd, 'historique' => $hist, 'transitions' => []], true, '/admin/commandes/1', 'Commande MS-2026-0001'),
  'adm_conf_ann1' => renderPage('admin/confirmer_annulation', ['commande' => $commandeActive, 'commentaire' => 'Test'], true, '/admin/commandes/1', 'Confirmer l’annulation'),
  'adm_conf_ann2' => renderPage('admin/confirmer_annulation', ['commande' => $commandeExpediee, 'commentaire' => 'Test'], true, '/admin/commandes/1', 'Confirmer l’annulation'),
  'adm_cats' => renderPage('admin/categories', ['categories' => $categories, 'erreurs' => []], true, '/admin/categories', 'Back-office — catégories'),
  'adm_conf_cat' => renderPage('admin/confirmer_suppression_categorie', ['categorie' => $categories[0]], true, '/admin/categories', 'Supprimer la catégorie Informatique ?'),
  'adm_prods' => renderPage('admin/produits', ['resultat' => ['lignes' => [$produitStock, $produitBas], 'page' => 2, 'pages' => 3, 'total' => 25], 'categories' => $categories, 'q' => '', 'cat' => null], true, '/admin/produits', 'Back-office — produits'),
  'adm_prod_new' => renderPage('admin/produit_form', ['produit' => null, 'categories' => $categories, 'erreurs' => [], 'valeurs' => [], 'tva_defaut' => 20.0], true, '/admin/produit/nouveau', 'Nouveau produit'),
  'adm_prod_edit' => renderPage('admin/produit_form', ['produit' => $produitStock, 'categories' => $categories, 'erreurs' => [], 'valeurs' => [], 'tva_defaut' => 20.0], true, '/admin/produit/1/modification', 'Modifier « Clavier Test »'),
  'adm_conf_prod' => renderPage('admin/confirmer_suppression_produit', ['produit' => $produitStock], true, '/admin/produits', 'Supprimer le produit REF-001 ?'),
  'adm_stocks' => renderPage('admin/stocks', ['etats' => [['id_produit' => 1, 'reference' => 'REF-001', 'nom' => 'Clavier Test', 'stock' => 2, 'seuil_alerte' => 3, 'etat' => 'TRES_BAS']], 'filtre' => '', 'compteurs' => ['RUPTURE' => 1, 'TRES_BAS' => 2, 'DISPONIBLE' => 9], 'valeur_inventaire' => 1200.0, 'erreurs' => []], true, '/admin/stocks', 'Back-office — stocks'),
  'adm_equipe' => renderPage('admin/equipe', ['admins' => [['id_admin' => 1, 'prenom' => 'Admin', 'nom' => 'Chef', 'email' => 'a@b.c', 'role' => 'SUPER', 'actif' => true, 'derniere_connexion' => '2026-01-15 18:15:00'], ['id_admin' => 2, 'prenom' => 'Bob', 'nom' => 'B', 'email' => 'b@b.c', 'role' => 'GESTIONNAIRE', 'actif' => false, 'derniere_connexion' => null]], 'erreurs' => []], true, '/admin/equipe', 'Back-office — équipe'),
  'adm_params' => renderPage('admin/parametres', ['parametres' => $parametres, 'libelles' => $libellesParam, 'erreurs' => []], true, '/admin/parametres', 'Back-office — paramètres'),
];
echo json_encode(['pages' => $pages, 'business' => array_values(BusinessError::MESSAGES)], JSON_UNESCAPED_UNICODE);
`,
});

const donneesPages = JSON.parse(rendu.text);
const identiquesOuDonnees = new Set([
    'Admin Chef', 'Alice Dupont', 'Bob B', 'Clavier Test', 'Souris', 'C',
    'MS-2026-0001', 'REF-001', 'REF-002', 'a@b.c', 'b@b.c',
    'informatique', 'audio', 'accessoires', 'objets-connectes',
    'MiniShop', 'PHP', 'English', 'Français',
    'Menu', 'Contact', 'Audio', 'Pagination', 'Stock', 'Stock *', 'Actions', 'Action',
    'Email', 'Email *', 'Back-office', 'SUPER', 'Slug', 'Description', 'Image (URL)',
    'Visible', 'Mode', 'Message', 'Date', '0 / 2000 characters', '23 / 2000 characters',
]);

for (const msg of donneesPages.business) {
    assert.notEqual(w.MINISHOP_I18N_EN.traduire(msg), msg, `BusinessError non traduite : ${msg}`);
}

for (const [clePage, htmlPage] of Object.entries(donneesPages.pages)) {
    const domFr = new JSDOM(htmlPage);
    const h1Fr = domFr.window.document.querySelector('h1')?.textContent.trim();

    const winEn = setup({ langue: 'en-US', htmlDoc: htmlPage });
    const docEn = winEn.document;
    assert.equal(docEn.documentElement.lang, 'en', `${clePage} : lang HTML non anglais`);
    assert.notEqual(docEn.title, domFr.window.document.title, `${clePage} : <title> non traduit (${docEn.title})`);

    // Cohérence thead th === td[data-label] en anglais sur tous les tableaux d'administration
    for (const table of docEn.querySelectorAll('.tableau-defilant--admin table')) {
        const titres = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
        if (titres.length === 0) continue;
        for (const tr of table.querySelectorAll('tbody tr')) {
            const cellules = [...tr.querySelectorAll(':scope > td')];
            cellules.forEach((td, idx) => {
                if (td.hasAttribute('data-label') && titres[idx]) {
                    assert.equal(td.getAttribute('data-label'), titres[idx], `${clePage} : data-label désynchronisé (${td.getAttribute('data-label')} != ${titres[idx]})`);
                }
            });
        }
    }

    // Aucun nœud texte ni attribut d'interface français oublié
    const collecterTextes = (doc, win) => {
        const liste = [];
        const wlk = doc.createTreeWalker(doc.body, win.NodeFilter.SHOW_TEXT);
        while (wlk.nextNode()) {
            const noeud = wlk.currentNode;
            const parent = noeud.parentElement;
            if (!parent || parent.closest('script, style, noscript, textarea, code, pre, svg, time[data-date-format], #pref-decalage, #pref-etat, [aria-hidden="true"]')) continue;
            if (parent.tagName === 'OPTION' && parent.parentElement?.id === 'pref-zone' && parent.value !== '') continue;
            liste.push(noeud.nodeValue.replace(/\s+/g, ' ').trim());
        }
        return liste;
    };
    const domRefFr = setup({ langue: 'fr-FR', htmlDoc: htmlPage });
    const textesFr = collecterTextes(domRefFr.document, domRefFr);
    const textesEn = collecterTextes(docEn, winEn);
    assert.equal(textesEn.length, textesFr.length, `${clePage} : nombre de nœuds texte modifié`);
    for (let idx = 0; idx < textesFr.length; idx += 1) {
        const fr = textesFr[idx];
        const en = textesEn[idx];
        if (!fr || !/[A-Za-zÀ-ÿ]/.test(fr) || identiquesOuDonnees.has(fr)) continue;
        if (/^\d+ \/ \d+ caractères$/.test(fr)) {
            assert.match(en, /^\d+ \/ \d+ characters$/, `${clePage} : compteur non traduit (${en})`);
            continue;
        }
        assert.notEqual(en, fr, `${clePage} : nœud texte français non traduit : "${fr}"`);
    }

    const elsFr = [...domRefFr.document.querySelectorAll('[aria-label],[placeholder],[title],[alt],[data-label],[data-confirme]')];
    const elsEn = [...docEn.querySelectorAll('[aria-label],[placeholder],[title],[alt],[data-label],[data-confirme]')];
    assert.equal(elsEn.length, elsFr.length, `${clePage} : nombre d'éléments à attributs traduits modifié`);
    for (let idx = 0; idx < elsFr.length; idx += 1) {
        for (const nomAttr of ['aria-label', 'placeholder', 'title', 'alt', 'data-label', 'data-confirme']) {
            if (!elsFr[idx].hasAttribute(nomAttr)) continue;
            if (nomAttr === 'title' && elsFr[idx].matches('time[data-date-format]')) continue;
            const fr = elsFr[idx].getAttribute(nomAttr).replace(/\s+/g, ' ').trim();
            const en = elsEn[idx].getAttribute(nomAttr).replace(/\s+/g, ' ').trim();
            if (!fr || !/[A-Za-zÀ-ÿ]/.test(fr) || identiquesOuDonnees.has(fr)) continue;
            assert.notEqual(en, fr, `${clePage} : attribut ${nomAttr} non traduit : "${fr}"`);
        }
    }

    if (clePage === 'contact') {
        assert.equal(docEn.querySelector('#message').value, 'Texte utilisateur intact', 'textarea modifiée par la traduction');
        assert.equal(docEn.querySelector('#nom').value, 'Alice', 'input modifié par la traduction');
    }
    if (clePage === 'prod_stock') {
        assert.equal(docEn.querySelector('.fiche__media img').getAttribute('alt'), 'Illustrative image: Clavier Test');
    }
    if (clePage === 'preferences') {
        docEn.querySelector('#pref-langue').value = 'fr';
        docEn.querySelector('#form-preferences').requestSubmit();
        assert.equal(docEn.documentElement.lang, 'fr');
        assert.equal(docEn.querySelector('h1').textContent.trim(), h1Fr);
    }
}

console.log(`Préférences : tests DOM, fuseaux, DST, géolocalisation, confidentialité et traduction intégrale FR/EN (${Object.keys(donneesPages.pages).length} vues, ${donneesPages.business.length} erreurs métier) réussis.`);
process.exit(0);
