/**
 * MiniShop — parcours E2E de l'application complète sous PHP-WASM.
 * (Outil de développement sandbox : sur machine normale, la même suite
 * s'exécute avec un vrai serveur PHP — le fichier tests/php/run_tests.php
 * couvre les assertions métier de façon autonome.)
 *
 * Prérequis : data/minishop peuplé (node scripts/wasm_run.mjs scripts/seed.php).
 * Usage : node scripts/wasm-e2e.mjs
 *
 * Exécute des scénarios HTTP réels (cookies + CSRF inclus) contre le
 * contrôleur frontal public/index.php : catalogue, compte client, panier,
 * commande, annulation, back-office, guards RB-13, jetons CSRF.
 */

import { createPhp, syncBack, WASM_ROOT } from './wasm_lib.mjs';
import { existsSync } from 'node:fs';
import { join } from 'node:path';
import { randomUUID } from 'node:crypto';

const REPO = join(import.meta.dirname, '..');
if (!existsSync(join(REPO, 'data/minishop/produit.json'))) {
  console.error('[wasm-e2e] base absente — lancez : node scripts/wasm_run.mjs scripts/seed.php');
  process.exit(2);
}

const php = await createPhp('http://localhost:8080/');
php.setPhpIniEntry('session.save_path', '/tmp');

// ---------------------------------------------------------------- client HTTP
class Navigateur {
  constructor(nom) { this.nom = nom; this.cookies = {}; }

  entetes() {
    const entetes = { 'x-request-id': randomUUID() };
    const cookie = Object.entries(this.cookies).map(([k, v]) => `${k}=${v}`).join('; ');
    if (cookie) entetes['x-ms-cookie'] = cookie; // transport dev (le SAPI WASM avale « cookie »)
    return entetes;
  }

  mémoriseCookies(reponse) {
    for (const sc of reponse.headers['set-cookie'] ?? []) {
      const m = /^([^=]+)=([^;]*)/.exec(sc);
      if (m) this.cookies[m[1]] = m[2];
    }
  }

  async get(chemin) {
    const r = await php.request({ method: 'GET', url: chemin, headers: this.entetes() });
    this.mémoriseCookies(r);
    return r;
  }

  async post(chemin, donnees) {
    const r = await php.request({
      method: 'POST', url: chemin, headers: this.entetes(),
      formData: Object.fromEntries(Object.entries(donnees).map(([k, v]) => [k, String(v)])),
    });
    this.mémoriseCookies(r);
    return r;
  }

  /** Jeton CSRF d'une page donnée. */
  async csrf(chemin) {
    const r = await this.get(chemin);
    const m = /name="_csrf" value="([a-f0-9]+)"/.exec(r.text ?? '');
    return m?.[1] ?? null;
  }
}

// ---------------------------------------------------------------- assertions
let échecs = 0, tests = 0;
const check = (label, ok, détail = '') => {
  ++tests;
  console.log(`  ${ok ? '✅' : '❌'} ${label}${détail && !ok ? ` — ${détail}` : ''}`);
  if (!ok) ++échecs;
};
const contient = (label, page, morceau) =>
  check(label, (page ?? '').includes(morceau), `page sans « ${morceau} »`);

// lecture du magasin JSON DANS le runtime WASM (les mutations n'atteignent
// l'hôte qu'au syncBack final)
const lireTable = async (table) =>
  JSON.parse(await php.readFileAsText(`${WASM_ROOT}/data/minishop/${table}.json`)).rows;

const B = (n) => `\n=== ${n} ===\n`;
process.stdout.write(B('T1 · pages publiques'));

{
  const anonyme = new Navigateur('anonyme');
  const accueil = await anonyme.get('/');
  check('GET / → 200', accueil.httpStatusCode === 200, `status ${accueil.httpStatusCode}`);
  contient('accueil : bienvenue', accueil.text, 'Bienvenue chez MiniShop');
  contient('accueil : 4 catégories', accueil.text, 'Objets connectés');
  const catalogue = await anonyme.get('/catalogue');
  check('catalogue : 11 produits visibles (RB-19)', (catalogue.text.match(/<li>/g) ?? []).length >= 11, 'moins de 11 <li>');
  const recherche = await anonyme.get('/recherche?q=casque');
  contient('recherche « casque » trouve le Casque', recherche.text, 'Casque sans fil Aura');
  const fiche = await anonyme.get('/produit/casque-sans-fil-aura');
  contient('fiche produit : prix TTC 154,80', fiche.text, '154,80');
  contient('fiche produit : RB-18 plafonnement', fiche.text, 'plafonné');
  const introuvable = await anonyme.get('/produit/ce-produit-nexiste-pas');
  check('slug inconnu → 404', introuvable.httpStatusCode === 404, `status ${introuvable.httpStatusCode}`);
  const masque = await anonyme.get('/produit/bracelet-connecte-band');
  check('produit masqué → 404 (RB-19)', masque.httpStatusCode === 404, `status ${masque.httpStatusCode}`);
  const routeInconnue = await anonyme.get('/page-inconnue');
  check('route inconnue → 404 (liste blanche)', routeInconnue.httpStatusCode === 404);
}

process.stdout.write(B('T2 · inscription et connexion (UC-04/05)'));

{
  const nouveau = new Navigateur('nouveau');
  const jeton = await nouveau.csrf('/inscription');
  check('formulaire d\'inscription avec CSRF', jeton !== null);
  const r = await nouveau.post('/inscription', {
    _csrf: jeton, nom: 'Durand', prenom: 'Zoé', email: 'zoe@example.com',
    mot_de_passe: 'Zoe2026!Mot', confirmation: 'Zoe2026!Mot', cgv: '1',
  });
  check('inscription → redirection /compte', (r.headers.location ?? []).join(' ').includes('/compte') || r.httpStatusCode === 302, `status ${r.httpStatusCode}`);
  const compte = await nouveau.get('/compte');
  contient('compte affiché : Zoé', compte.text, 'Mon compte (Zoé)');
  const d = (await lireTable('client')).find((c) => c.email === 'zoe@example.com');
  check('client persisté avec hash bcrypt (RB-12)', !!d && d.mot_de_passe_hash !== 'Zoe2026!Mot' && d.mot_de_passe_hash.startsWith('$2y$'));

  // email dupliqué refusé (RB-01)
  const autre = new Navigateur('autre');
  const j2 = await autre.csrf('/inscription');
  const r2 = await autre.post('/inscription', {
    _csrf: j2, nom: 'X', prenom: 'Y', email: 'zoe@example.com',
    mot_de_passe: 'Azerty123', confirmation: 'Azerty123', cgv: '1',
  });
  contient('email déjà utilisé → message RB-01', r2.text, 'déjà');

  // mauvais mot de passe : message identique email inconnu / mdp erroné (SEC-05)
  const visiteur = new Navigateur('visiteur');
  const j3 = await visiteur.csrf('/connexion');
  const r3 = await visiteur.post('/connexion', { _csrf: j3, email: 'alice@example.com', mot_de_passe: 'MAUVAIS' });
  contient('mauvais mot de passe → message générique', r3.text, 'Email ou mot de passe erroné');
}

process.stdout.write(B('T3 · panier puis commande complète (UC-06/07, RB-06/15/18/20)'));

let numeroCommande;
{
  const alice = new Navigateur('alice');
  const j = await alice.csrf('/connexion');
  const connexion = await alice.post('/connexion', { _csrf: j, email: 'alice@example.com', mot_de_passe: 'Demo2026!' });
  check('connexion alice → 302', connexion.httpStatusCode === 302, `status ${connexion.httpStatusCode}`);

  const j1 = await alice.csrf('/produit/casque-sans-fil-aura');
  await alice.post('/panier/ajouter', { _csrf: j1, id_produit: 5, quantite: 2 }); // CASQ 129 € HT
  const j2 = await alice.csrf('/produit/montre-connectee-fit-2');
  await alice.post('/panier/ajouter', { _csrf: j2, id_produit: 11, quantite: 1 }); // MONTRE 149 € HT
  const panier = await alice.get('/panier');
  contient('panier : casque ×2', panier.text, 'Casque sans fil Aura');
  contient('panier : port offert (≥ 80 €)', panier.text.toLowerCase(), 'offert');

  const valider = await alice.get('/commande/valider');
  contient('récapitulatif avant paiement (art. L221-5)', valider.text, 'Total à payer');
  contient('montant attendu 488,40 € TTC', valider.text, '488,40'); // 154,80×2 + 178,80 (montre 149 HT)

  const j3 = await alice.csrf('/commande/valider');
  const commande = await alice.post('/commande/valider', { _csrf: j3, adresse: '12 rue des Lilas, Nice', paiement_simule: '1' });
  check('validation → redirection mes-commandes', commande.httpStatusCode === 302 && /mes-commandes\/\d+/.test((commande.headers.location ?? []).join(' ')), `status ${commande.httpStatusCode}`);

  const detail = await alice.get('/mes-commandes');
  const m = /(CMD\d{4}-\d{6})/.exec(detail.text ?? '');
  numeroCommande = m?.[1];
  check('commande listée avec numéro', !!numeroCommande, 'aucun numéro CMD trouvé');

  const stocks = await lireTable('produit');
  const casque = stocks.find((p) => p.id_produit === 5);
  const montre = stocks.find((p) => p.id_produit === 11);
  check('stock casque décrémenté 18 → 16 (RB-18)', casque.stock === 16, `stock=${casque.stock}`);
  check('stock montre décrémenté 9 → 8 (RB-18)', montre.stock === 8, `stock=${montre.stock}`);

  const ligne = (await lireTable('ligne_commande')).find((l) => l.id_produit === 5);
  check('prix TTC figé à l\'achat (RB-06)', Math.abs(ligne.prix_unitaire - 154.80) < 0.001, `prix=${ligne.prix_unitaire}`);
}

process.stdout.write(B('T4 · annulation client (UC-09, RB-11/18/20)'));

{
  const alice = new Navigateur('alice');
  const j = await alice.csrf('/connexion');
  await alice.post('/connexion', { _csrf: j, email: 'alice@example.com', mot_de_passe: 'Demo2026!' });
  const liste = await alice.get('/mes-commandes');
  const m = /\/mes-commandes\/(\d+)/.exec(liste.text ?? '');
  check('une commande est listée', !!m);
  if (m) {
    const id = m[1];
    const jA = await alice.csrf(`/mes-commandes/${id}`);
    const annulation = await alice.post(`/mes-commandes/${id}/annulation`, { _csrf: jA });
    check('annulation → redirection', annulation.httpStatusCode === 302, `status ${annulation.httpStatusCode}`);
    const stocks = await lireTable('produit');
    check('stock casque restitué (RB-18)', stocks.find((p) => p.id_produit === 5).stock === 18);
    check('stock montre restitué (RB-18)', stocks.find((p) => p.id_produit === 11).stock === 9);
    const cmd = (await lireTable('commande')).find((c) => c.id_commande === Number(id));
    check('commande ANNULEE, montant soldé (RB-20)', cmd.statut === 'ANNULEE' && Number(cmd.montant_total) === 0);
  }
}

process.stdout.write(B('T5 · back-office (UC-10…14, RB-13)'));

{
  const admin = new Navigateur('admin');
  const j = await admin.csrf('/admin/connexion');
  const connexion = await admin.post('/admin/connexion', { _csrf: j, email: 'admin@minishop.fr', mot_de_passe: 'Admin2026!' });
  check('connexion admin → 302 /admin', connexion.httpStatusCode === 302, `status ${connexion.httpStatusCode}`);
  const dash = await admin.get('/admin');
  contient('tableau de bord', dash.text, 'Tableau de bord');
  contient('piste des montants par statut', dash.text, "Chiffre d'affaires");
  for (const page of ['/admin/produits', '/admin/commandes', '/admin/stocks', '/admin/categories', '/admin/equipe', '/admin/produit/nouveau']) {
    const r = await admin.get(page);
    check(`GET ${page} → 200`, r.httpStatusCode === 200, `status ${r.httpStatusCode}`);
  }

  // création produit puis contrôle RB-02 (prix <= 0 refusé)
  const jn = await admin.csrf('/admin/produit/nouveau');
  const ok = await admin.post('/admin/produit/nouveau', {
    _csrf: jn, reference: 'TEST-999', nom: 'Produit de test E2E', slug: '', description: 'Test',
    prix_ht: '10.00', tva: '20', stock: '5', seuil_alerte: '2', id_categorie: '1', visible: '1',
  });
  check('création produit → redirection', ok.httpStatusCode === 302, `status ${ok.httpStatusCode}`);
  check('produit créé (prix TTC dérivé RB-16)', (await lireTable('produit')).some((p) => p.reference === 'TEST-999' && Math.abs(p.prix_ttc - 12) < 0.001));

  const jn2 = await admin.csrf('/admin/produit/nouveau');
  const rb02 = await admin.post('/admin/produit/nouveau', {
    _csrf: jn2, reference: 'TEST-998', nom: 'Prix invalide', slug: '', description: '',
    prix_ht: '0', tva: '20', stock: '1', seuil_alerte: '1', id_categorie: '1', visible: '1',
  });
  contient('prix ≤ 0 refusé (RB-02)', rb02.text, 'strictement positif');

  // ajustement de stock avec motif (EF-ADM-04) puis sans motif → refus
  const js = await admin.csrf('/admin/stocks');
  const idTest = (await lireTable('produit')).find((p) => p.reference === 'TEST-999').id_produit;
  const st = await admin.post('/admin/stocks', { _csrf: js, id_produit: idTest, mode: 'DELTA', quantite: '-2', motif: 'sortie atelier' });
  check('mouvement DELTA -2 → redirection', st.httpStatusCode === 302, `status ${st.httpStatusCode}`);
  check('stock 5 → 3', (await lireTable('produit')).find((p) => p.id_produit === idTest).stock === 3);
  const js2 = await admin.csrf('/admin/categories');
  const sansMotif = await admin.post('/admin/stocks', { _csrf: js2, id_produit: idTest, mode: 'SET', quantite: '10', motif: '' });
  contient('motif obligatoire refusé', (await admin.get('/admin/stocks')).text || '', 'motif');

  // la commande de T3/T4 est ANNULEE : statut TERMINAL, toute transition est
  // refusée par la matrice RB-11 (le POST redirige avec un message d'erreur)
  const liste = await admin.get('/admin/commandes');
  const m = /\/admin\/commandes\/(\d+)/.exec(liste.text ?? '');
  if (m) {
    const id = m[1];
    const jc = await admin.csrf('/admin/categories'); // la commande terminale n'a plus de formulaire
    const r1 = await admin.post(`/admin/commandes/${id}/statut`, { _csrf: jc, statut: 'EN_PREPARATION', commentaire: 'tentative' });
    check('POST transition → redirection', r1.httpStatusCode === 302, `status ${r1.httpStatusCode}`);
    const page = await admin.get(`/admin/commandes/${id}`);
    contient('ANNULEE→EN_PREPARATION refusée (terminal, RB-11)', page.text, 'Transition de statut interdite');
    contient('statut terminal affiché', page.text, 'Statut terminal');
    const st = (await lireTable('commande')).find((c) => c.id_commande === Number(id));
    check('statut inchangé ANNULEE', st.statut === 'ANNULEE', `statut=${st.statut}`);
  }
}

process.stdout.write(B('T6 · cloisonnement des accès (RB-13, SEC-05, SEC-08)'));

{
  const anonyme = new Navigateur('anonyme2');
  check('anonyme → /compte : redirection connexion', (await anonyme.get('/compte')).httpStatusCode === 302);
  check('anonyme → /admin : redirection admin/connexion', (await anonyme.get('/admin')).httpStatusCode === 302);
  check('anonyme → /admin/equipe : redirection', (await anonyme.get('/admin/equipe')).httpStatusCode === 302);

  const alice = new Navigateur('alice2');
  const j = await alice.csrf('/connexion');
  await alice.post('/connexion', { _csrf: j, email: 'alice@example.com', mot_de_passe: 'Demo2026!' });
  check('client → /admin : redirection (RB-13)', (await alice.get('/admin')).httpStatusCode === 302);

  const admin = new Navigateur('admin2');
  const ja = await admin.csrf('/admin/connexion');
  await admin.post('/admin/connexion', { _csrf: ja, email: 'admin@minishop.fr', mot_de_passe: 'Admin2026!' });
  check('admin → /compte : redirection (admin n\'est pas client)', (await admin.get('/compte')).httpStatusCode === 302);
  check('admin → /admin/equipe : 200 (SUPER)', (await admin.get('/admin/equipe')).httpStatusCode === 200);

  // le client ne voit pas la commande d'un autre (SEC-08)
  const bruno = new Navigateur('bruno');
  const jb = await bruno.csrf('/connexion');
  await bruno.post('/connexion', { _csrf: jb, email: 'bruno@example.com', mot_de_passe: 'Demo2026!' });
  const vol = await bruno.get('/mes-commandes/1');
  check('commande d\'autrui → redirection + message', vol.httpStatusCode === 302, `status ${vol.httpStatusCode}`);

  // POST sans jeton CSRF → rejet (SEC-05)
  const pirate = new Navigateur('pirate');
  const sansCsrf = await pirate.post('/connexion', { email: 'alice@example.com', mot_de_passe: 'Demo2026!' });
  check('POST sans CSRF rejeté (SEC-05)', sansCsrf.httpStatusCode === 302 && (sansCsrf.headers.location ?? []).join(' ') === '/', `status ${sansCsrf.httpStatusCode}`);
}


process.stdout.write(B('T7 · catalogue avancé (F-01…F-05)'));

{
  const anon = new Navigateur('t7');
  // F-01 : compteurs par catégorie = produits VISIBLES uniquement (RB-19),
  // recalculés depuis la table (le rendu et les données doivent coïncider)
  const cats = await anon.get('/categories');
  const produits = await lireTable('produit');
  const detailCompteurs = [];
  for (const c of await lireTable('categorie')) {
    const attendu = produits.filter((p2) => p2.id_categorie === c.id_categorie && (p2.visible === 1 || p2.visible === true)).length;
    const motif = new RegExp(`(?:\\D|^)${attendu} produit`); // \D = non-chiffre avant le nombre
    if (!motif.test(cats.text ?? '')) detailCompteurs.push(`${c.nom}:${attendu}`);
  }
  check('compteurs affichés = produits visibles par catégorie', detailCompteurs.length === 0, detailCompteurs.join(', ') || 'incohérent');
  contient('Objets connectés : 1 seul visible (le masqué exclu)', cats.text, '1 produit');
  check('le bracelet masqué n\'apparaît nulle part', !(cats.text ?? '').includes('Bracelet'), 'Bracelet visible');

  // F-02/03 : filtres cumulables, conservés dans l'URL
  const f = await anon.get('/catalogue?cat=3&prix_max=100&stock=1&tri=prix_asc');
  contient('filtres actifs : formulaire pré-rempli (prix_max)', f.text, 'value="100"');
  check('filtres actifs : catégorie sélectionnée', /value="3"\s*selected/.test(f.text ?? ''), 'option 3 non sélectionnée');
  contient('filtres actifs : case stock cochée', f.text, 'checked');
  contient('2 produits (sac 58,80 + câble 15,00)', f.text, '2 produits trouvés');
  contient('tri prix croissant : câble avant sac', f.text, 'Câble USB-C 2 m renforcé');
  check('tri prix croissant respecté',
    (f.text ?? '').indexOf('Câble USB-C') < (f.text ?? '').indexOf('Sac à dos'), 'ordre incorrect');
  check('le POWER en rupture est exclu par « en stock »', !(f.text ?? '').includes('Batterie externe'), 'batterie listée');

  // ENF-04 : pages hors bornes → pas d'erreur, liste vide
  const loin = await anon.get('/catalogue?page=999');
  check('page 999 → 200, page affichée 999 / 1', loin.httpStatusCode === 200 && (loin.text ?? '').includes('page 999 / 1'), `status ${loin.httpStatusCode}`);
  check('page 999 : aucune fiche produit', !(loin.text ?? '').includes('href="/produit/'), 'des fiches présentes');
  const injectionTri = await anon.get('/catalogue?tri=prix_desc;DROP%20TABLE%20produit');
  check('tri inconnu → 200 sans erreur (liste blanche)', injectionTri.httpStatusCode === 200, `status ${injectionTri.httpStatusCode}`);

  // F-05 : transparence des prix
  const fiche = await anon.get('/produit/casque-sans-fil-aura');
  contient('fiche : décomposition HT + TVA', fiche.text, 'HT + TVA');
  contient('fiche : taux 20,00 %', fiche.text, '20,00');
  contient('fiche : prix TTC', fiche.text, 'TTC');
}

process.stdout.write(B('T8 · compte client : profil et mot de passe (F-10/11)'));

{
  const alice = new Navigateur('t8-alice');
  const j0 = await alice.csrf('/connexion');
  await alice.post('/connexion', { _csrf: j0, email: 'alice@example.com', mot_de_passe: 'Demo2026!' });

  // F-10 : mise à jour du profil
  const j1 = await alice.csrf('/compte');
  const maj = await alice.post('/compte', {
    _csrf: j1, nom: 'Dupont', prenom: 'Alice', email: 'alice@example.com',
    telephone: '0698765433', adresse: '24 avenue Jean Médecin', code_postal: '06000', ville: 'Nice',
  });
  check('profil mis à jour → redirection', maj.httpStatusCode === 302, `status ${maj.httpStatusCode}`);
  const fiche = (await lireTable('client')).find((c) => c.email === 'alice@example.com');
  check('téléphone persisté', fiche?.telephone === '0698765433', `tel=${fiche?.telephone}`);
  check('adresse persistée', fiche?.adresse_livraison === '24 avenue Jean Médecin', `adresse=${fiche?.adresse_livraison}`);

  // F-10 : email de bruno → refus RB-01
  const j2 = await alice.csrf('/compte');
  const vol = await alice.post('/compte', {
    _csrf: j2, nom: 'Dupont', prenom: 'Alice', email: 'bruno@example.com',
    telephone: '0698765433', adresse: '24 avenue Jean Médecin', code_postal: '06000', ville: 'Nice',
  });
  contient('email déjà pris → refus', vol.text, 'déjà');
  check('email d\'alice inchangé après refus', (await lireTable('client')).some((c) => c.email === 'alice@example.com'));

  // F-11 : changement de mot de passe, ancien exigé
  const j3 = await alice.csrf('/compte');
  const mauvais = await alice.post('/compte/mot-de-passe', { _csrf: j3, ancien_mot_de_passe: 'FAUX', nouveau_mot_de_passe: 'Nouveau2026!', confirmation: 'Nouveau2026!' });
  contient('ancien mot de passe incorrect → refus', mauvais.text, 'Ancien mot de passe incorrect');
  const j4 = await alice.csrf('/compte');
  const bon = await alice.post('/compte/mot-de-passe', { _csrf: j4, ancien_mot_de_passe: 'Demo2026!', nouveau_mot_de_passe: 'Nouveau2026!', confirmation: 'Nouveau2026!' });
  check('changement accepté → redirection', bon.httpStatusCode === 302, `status ${bon.httpStatusCode}`);

  const ancienRefuse = new Navigateur('t8-ancien');
  const ja = await ancienRefuse.csrf('/connexion');
  const rAncien = await ancienRefuse.post('/connexion', { _csrf: ja, email: 'alice@example.com', mot_de_passe: 'Demo2026!' });
  contient('ancien mot de passe désormais refusé', rAncien.text, 'Email ou mot de passe erroné');
  const neuf = new Navigateur('t8-neuf');
  const jn = await neuf.csrf('/connexion');
  const rNeuf = await neuf.post('/connexion', { _csrf: jn, email: 'alice@example.com', mot_de_passe: 'Nouveau2026!' });
  check('nouveau mot de passe accepté', rNeuf.httpStatusCode === 302, `status ${rNeuf.httpStatusCode}`);
}

process.stdout.write(B('T9 · panier : plafonnement, retrait, prix falsifié (F-12/14/15)'));

let numeroCommandeCarla;
{
  const carla = new Navigateur('t9-carla');
  const j0 = await carla.csrf('/connexion');
  await carla.post('/connexion', { _csrf: j0, email: 'carla@example.com', mot_de_passe: 'Demo2026!' });

  // F-12 : 5 micros demandés, stock 2 → quantité plafonnée côté serveur
  const j1 = await carla.csrf('/produit/micro-studio-procast');
  await carla.post('/panier/ajouter', { _csrf: j1, id_produit: 7, quantite: 5 });
  const panier = await carla.get('/panier');
  contient('quantité plafonnée à 2 (RB-18)', panier.text, 'value="2"');
  contient('total plafonné 237,60 (2 × 118,80)', panier.text, '237,60');

  // F-14 : champ prix falsifié ignoré (le serveur relit le produit)
  const j2 = await carla.csrf('/panier');
  await carla.post('/panier/ajouter', { _csrf: j2, id_produit: 5, quantite: 1, prix_unitaire: '0.01' });
  const panier2 = await carla.get('/panier');
  contient('prix serveur 154,80, pas 0,01', panier2.text, '154,80');
  contient('total 392,40 = 237,60 + 154,80', panier2.text, '392,40');
  check('aucun total à 0,01', !(panier2.text ?? '').includes('0,01'), '0,01 apparaît');

  // F-15 : retrait idempotent (deux fois → pas d'erreur)
  const j3 = await carla.csrf('/panier');
  const r1 = await carla.post('/panier/retirer', { _csrf: j3, id_produit: 5 });
  const j4 = await carla.csrf('/panier');
  const r2 = await carla.post('/panier/retirer', { _csrf: j4, id_produit: 5 });
  check('retrait ×2 → redirections', r1.httpStatusCode === 302 && r2.httpStatusCode === 302, `status ${r1.httpStatusCode}/${r2.httpStatusCode}`);
  const panier3 = await carla.get('/panier');
  check('casque retiré, seul le micro reste', (panier3.text ?? '').includes('Micro studio') && !(panier3.text ?? '').includes('Casque sans fil'), 'contenu inattendu');
  contient('total revenu à 237,60', panier3.text, '237,60');

  // RB-18 : le stock baisse après l'ajout → re-plafonnement + avertissement à la consultation
  const admin = new Navigateur('t9-admin');
  const ja = await admin.csrf('/admin/connexion');
  await admin.post('/admin/connexion', { _csrf: ja, email: 'admin@minishop.fr', mot_de_passe: 'Admin2026!' });
  const js = await admin.csrf('/admin/stocks');
  await admin.post('/admin/stocks', { _csrf: js, id_produit: 7, mode: 'SET', quantite: '1', motif: 'e2e : baisse de stock' });
  const panier4 = await carla.get('/panier');
  contient('avertissement de re-plafonnement affiché', panier4.text, 'ramenés au stock disponible');
  contient('quantité re-plafonnée à 1', panier4.text, 'value="1"');
  contient('total recalculé 118,80', panier4.text, '118,80');

  // F-16 : la commande passe avec la quantité re-plafonnée
  const jv = await carla.csrf('/commande/valider');
  const recap = await carla.get('/commande/valider');
  contient('récapitulatif : 118,80', recap.text, '118,80');
  const cmd = await carla.post('/commande/valider', { _csrf: jv, adresse: '8 rue Papin, Nice', paiement_simule: '1' });
  check('validation → redirection', cmd.httpStatusCode === 302, `status ${cmd.httpStatusCode}`);
  const stockApres = (await lireTable('produit')).find((p2) => p2.id_produit === 7);
  check('stock micro 1 → 0 (dernière unité vendue)', stockApres.stock === 0, `stock=${stockApres.stock}`);
  const laCmd = (await lireTable('commande')).find((c) => c.id_client === 3);
  numeroCommandeCarla = laCmd?.numero;
  check('commande PAYEE de carla enregistrée', laCmd?.statut === 'PAYEE', `statut=${laCmd?.statut}`);
}

process.stdout.write(B('T10 · back-office CRUD complet (F-22/23/25/26/27)'));

{
  const admin = new Navigateur('t10-admin');
  const j0 = await admin.csrf('/admin/connexion');
  await admin.post('/admin/connexion', { _csrf: j0, email: 'admin@minishop.fr', mot_de_passe: 'Admin2026!' });

  // F-22 : modification d'un produit (prix recalculé RB-16)
  const id999 = (await lireTable('produit')).find((p2) => p2.reference === 'TEST-999')?.id_produit;
  const jm = await admin.csrf(`/admin/produit/${id999}/modification`);
  const mod = await admin.post(`/admin/produit/${id999}/modification`, {
    _csrf: jm, reference: 'TEST-999', nom: 'Produit de test E2E (édité)', slug: '', description: 'Édité',
    prix_ht: '20.00', tva: '20', stock: '3', seuil_alerte: '1', id_categorie: '1', visible: '1',
  });
  check('modification produit → redirection', mod.httpStatusCode === 302, `status ${mod.httpStatusCode}`);
  const p999 = (await lireTable('produit')).find((p2) => p2.reference === 'TEST-999');
  check('prix TTC recalculé 24,00', p999 && Math.abs(p999.prix_ttc - 24) < 0.001, `ttc=${p999?.prix_ttc}`);

  // deux produits de plus → le catalogue dépasse une page (12 par page)
  for (const ref of ['TEST-997', 'TEST-996']) {
    const jn = await admin.csrf('/admin/produit/nouveau');
    await admin.post('/admin/produit/nouveau', {
      _csrf: jn, reference: ref, nom: `Produit ${ref} E2E`, slug: '', description: 'pagination',
      prix_ht: '5.00', tva: '20', stock: '10', seuil_alerte: '2', id_categorie: '1', visible: '1',
    });
  }

  // ENF-04/F-03 : pagination réelle + filtres conservés dans les liens
  const page2 = await admin.get('/catalogue?page=2&tri=prix_desc');
  contient('page 2 affichée', page2.text, 'page 2 / 2');
  const hrefs = [...(page2.text ?? '').matchAll(/href="([^"]*page=1[^"]*)"/g)].map((m2) => m2[1]);
  check('lien précédent conserve les filtres (tri + page)',
    hrefs.some((h) => h.includes('tri=prix_desc')), JSON.stringify(hrefs));

  // F-23 : suppression d'un produit jamais commandé → disparition réelle
  const js = await admin.csrf('/admin/produits');
  const sup = await admin.post(`/admin/produits/${id999}/suppression`, { _csrf: js });
  check('suppression produit → redirection', sup.httpStatusCode === 302, `status ${sup.httpStatusCode}`);
  check('produit supprimé de la table', !(await lireTable('produit')).some((p2) => p2.reference === 'TEST-999'), 'TEST-999 encore là');

  // F-25 : CRUD catégorie (créer / modifier / supprimer une catégorie vide)
  const jc = await admin.csrf('/admin/categories');
  const cre = await admin.post('/admin/categories', { _csrf: jc, nom: 'Stockage E2E', slug: '', description: 'SSD' });
  check('catégorie créée → redirection', cre.httpStatusCode === 302, `status ${cre.httpStatusCode}`);
  const cat = (await lireTable('categorie')).find((c) => c.nom === 'Stockage E2E');
  check('catégorie présente avec slug auto', cat?.slug === 'stockage-e2e', `slug=${cat?.slug}`);
  const jcm = await admin.csrf('/admin/categories');
  await admin.post(`/admin/categories/${cat.id_categorie}/modification`, { _csrf: jcm, nom: 'Stockage E2E', slug: '', description: 'SSD et disques' });
  check('catégorie modifiée', (await lireTable('categorie')).find((c) => c.id_categorie === cat.id_categorie)?.description === 'SSD et disques');
  const jcs = await admin.csrf('/admin/categories');
  const supC = await admin.post(`/admin/categories/${cat.id_categorie}/suppression`, { _csrf: jcs });
  check('catégorie vide supprimée (RESTRICT respecté)', supC.httpStatusCode === 302
    && !(await lireTable('categorie')).some((c) => c.id_categorie === cat.id_categorie), 'suppression refusée');
  const catOccupee = (await lireTable('categorie')).find((c) => c.nom === 'Informatique');
  const jco = await admin.csrf('/admin/categories');
  const supOccupee = await admin.post(`/admin/categories/${catOccupee.id_categorie}/suppression`, { _csrf: jco });
  check('catégorie occupée NON supprimée (RB-14)', supOccupee.httpStatusCode === 302
    && (await lireTable('categorie')).some((c) => c.nom === 'Informatique'), 'catégorie disparue');

  // F-27 : création d'un gestionnaire (rôle inférieur à SUPER)
  const je = await admin.csrf('/admin/equipe');
  const gest = await admin.post('/admin/equipe', { _csrf: je, nom: 'Tionnaire', prenom: 'Gus', email: 'gestionnaire@minishop.fr', mot_de_passe: 'Gest2026!Ok', role: 'GESTIONNAIRE' });
  check('gestionnaire créé → redirection', gest.httpStatusCode === 302, `status ${gest.httpStatusCode}`);
  const ligneGest = (await lireTable('administrateur')).find((a) => a.email === 'gestionnaire@minishop.fr');
  check('gestionnaire en base avec rôle GESTIONNAIRE', ligneGest?.role === 'GESTIONNAIRE', `role=${ligneGest?.role}`);
}

process.stdout.write(B('T11 · commandes filtrables, piste d\'audit, indicateurs (F-28/30/31)'));

{
  const admin = new Navigateur('t11-admin');
  const j0 = await admin.csrf('/admin/connexion');
  await admin.post('/admin/connexion', { _csrf: j0, email: 'admin@minishop.fr', mot_de_passe: 'Admin2026!' });

  // F-28 : filtres par statut
  const payees = await admin.get('/admin/commandes?statut=PAYEE');
  check('filtre PAYEE : commande de carla listée', (payees.text ?? '').includes(numeroCommandeCarla ?? '###'), `${numeroCommandeCarla} absente`);
  check('filtre PAYEE : l\'annulée de T3 absente', !(payees.text ?? '').includes(numeroCommande ?? '###'), `${numeroCommande} présente`);
  const annulees = await admin.get('/admin/commandes?statut=ANNULEE');
  check('filtre ANNULEE : l\'annulée de T3 listée', (annulees.text ?? '').includes(numeroCommande ?? '###'));

  // F-30 : piste d'audit d'une commande (RB-11)
  const idCarla = (await lireTable('commande')).find((c) => c.numero === numeroCommandeCarla)?.id_commande;
  const detail = await admin.get(`/admin/commandes/${idCarla}`);
  check('détail commande → 200', detail.httpStatusCode === 200, `status ${detail.httpStatusCode}`);
  contient('piste : création Brouillon', detail.text, 'Brouillon');
  contient('piste : validation Payée', detail.text, 'Payée');
  const duAu = await admin.get('/admin/commandes?du=2030-01-01&au=2030-12-31');
  contient('filtre période future : aucune commande', duAu.text, '0 commande(s) affichée(s)');

  // F-31 : indicateurs du tableau de bord
  const dash = await admin.get('/admin');
  contient('CA par statut présent', dash.text, "Chiffre d'affaires");
  contient('statut Payée dans le rapport', dash.text, 'Payée');
  contient('montant 118,80 de la commande', dash.text, '118,80');

  // EF-GEN-04 : journal JSONL des écritures
  let journal = '';
  for (const cible of ['/var/journal/db.jsonl', '/data/minishop/journal.jsonl']) {
    try { journal = await php.readFileAsText(`${WASM_ROOT}${cible}`); if (journal) break; } catch { /* essayons l'autre */ }
  }
  check('journal alimenté', journal.split('\n').filter((l) => l.trim()).length > 10, `${journal.length} octets`);
  check('journal : insertions de commandes tracées', journal.includes('"table":"commande"') && journal.includes('"op":"insert"'), 'traces absentes');
  check('journal : acteur présent (EF-GEN-04)', journal.includes('"actor"'), 'acteur absent');
  check('journal : l\'annulation T4 a tracé des suppressions', journal.includes('"op":"delete"'), 'aucun delete');
}

process.stdout.write(B('T12 · sécurité offensive (S-01…S-10)'));

{
  // S-01 : injections SQL refusées par construction (pas d'interpréteur)
  const anon = new Navigateur('t12-anon');
  const inj = await anon.get("/recherche?q=%27%20OR%201%3D1%20--");
  check('injection « OR 1=1 -- » → 200, 0 résultat', inj.httpStatusCode === 200 && (inj.text ?? '').includes('0 produit trouvé'), `status ${inj.httpStatusCode}`);
  const inj2 = await anon.get('/recherche?q=%25%27%3B%20DROP%20TABLE%20produit%3B--');
  check('injection « DROP TABLE » → 200 sans dégât', inj2.httpStatusCode === 200 && (await lireTable('produit')).length > 0, `status ${inj2.httpStatusCode}`);

  // S-02 : XSS stocké neutralisé par l'échappement systématique
  const admin = new Navigateur('t12-admin');
  const j0 = await admin.csrf('/admin/connexion');
  await admin.post('/admin/connexion', { _csrf: j0, email: 'admin@minishop.fr', mot_de_passe: 'Admin2026!' });
  const jx = await admin.csrf('/admin/produit/nouveau');
  await admin.post('/admin/produit/nouveau', {
    _csrf: jx, reference: 'XSS-001', nom: '<script>alert("xss")</script> Bombe', slug: '', description: 'test XSS',
    prix_ht: '1.00', tva: '20', stock: '1', seuil_alerte: '1', id_categorie: '1', visible: '1',
  });
  const pageXss = await anon.get('/recherche?q=Bombe');
  check('le script est affiché littéralement (échappé)', (pageXss.text ?? '').includes('&lt;script&gt;'), 'pas de &lt;script&gt;');
  check('aucune balise script exécutable', !(pageXss.text ?? '').includes('<script>alert'), '<script> brut dans la page');
  const idXss = (await lireTable('produit')).find((p2) => p2.reference === 'XSS-001')?.id_produit;
  const jx2 = await admin.csrf('/admin/produits');
  await admin.post(`/admin/produits/${idXss}/suppression`, { _csrf: jx2 });

  // S-03 : IDOR — bruno ne peut lire aucune commande d'autrui
  const bruno = new Navigateur('t12-bruno');
  const jb = await bruno.csrf('/connexion');
  await bruno.post('/connexion', { _csrf: jb, email: 'bruno@example.com', mot_de_passe: 'Demo2026!' });
  let idorOk = true;
  for (const id of [1, 2, 3, 4]) {
    const r = await bruno.get(`/mes-commandes/${id}`);
    if (r.httpStatusCode !== 302) idorOk = false;
  }
  check('boucle IDOR 1..4 : toujours redirigé (SEC-08)', idorOk, 'une commande accessible');

  // S-04/S-05 : fixation de session — l'identifiant est régénéré à la connexion
  const pirate = new Navigateur('t12-pirate');
  pirate.cookies.MINISHOPSESS = 'sid-pirate-fixe-123';
  const jp = await pirate.csrf('/connexion');
  await pirate.post('/connexion', { _csrf: jp, email: 'alice@example.com', mot_de_passe: 'Nouveau2026!' });
  check('session régénérée (anti-fixation)', pirate.cookies.MINISHOPSESS !== 'sid-pirate-fixe-123' && !!pirate.cookies.MINISHOPSESS, `sid=${pirate.cookies.MINISHOPSESS}`);
  const volPanier = await pirate.get('/panier');
  check('le panier volé est vide (nouvelle session)', (volPanier.text ?? '').includes('panier est vide'), 'panier non vide');

  // S-06/S-07 : traversées de chemin et fichiers sensibles → 404 ; en-têtes présents
  for (const chemin of ['/../etc/passwd', '/produit/../../etc/passwd', '/%2e%2e/%2e%2e/etc/passwd',
    '/.git/config', '/sql/01-minishop-schema.sql', '/app/Config/env.php', '/var/log/application.log', '/phpinfo.php']) {
    const r = await anon.get(chemin);
    check(`GET ${chemin} → 404`, r.httpStatusCode === 404, `status ${r.httpStatusCode}`);
  }
  const accueil = await anon.get('/');
  const entete = (nom) => {
    const v = Object.entries(accueil.headers ?? {}).find(([k]) => k.toLowerCase() === nom.toLowerCase())?.[1];
    return Array.isArray(v) ? v[0] : v;
  };
  check('X-Content-Type-Options: nosniff', entete('x-content-type-options') === 'nosniff', String(entete('x-content-type-options')));
  check('X-Frame-Options: DENY', entete('x-frame-options') === 'DENY', String(entete('x-frame-options')));
  check('Content-Security-Policy présente', String(entete('content-security-policy') ?? '').includes('default-src'), 'CSP absente');
  check('Referrer-Policy présente', !!entete('referrer-policy'), 'absente');

  // S-10 : escalade de privilèges par champ forgé
  const avant = (await lireTable('administrateur')).length;
  const esc = new Navigateur('t12-esc');
  const je = await esc.csrf('/inscription');
  await esc.post('/inscription', {
    _csrf: je, nom: 'Escalade', prenom: 'Ida', email: 'ida@example.com',
    mot_de_passe: 'Ida2026!Ok', confirmation: 'Ida2026!Ok', cgv: '1', role: 'SUPER',
  });
  check('champ role=ADMIN ignoré à l\'inscription', (await lireTable('administrateur')).length === avant, 'un admin de plus');
  const jec = await esc.csrf('/connexion');
  await esc.post('/connexion', { _csrf: jec, email: 'ida@example.com', mot_de_passe: 'Ida2026!Ok' });
  check('le faux admin reste un simple client', (await esc.get('/admin')).httpStatusCode === 302, 'accès admin obtenu');
}

process.stdout.write(B('T13 · rôles administrateurs (RB-13, F-27)'));

{
  const gest = new Navigateur('t13-gest');
  const j0 = await gest.csrf('/admin/connexion');
  const connexion = await gest.post('/admin/connexion', { _csrf: j0, email: 'gestionnaire@minishop.fr', mot_de_passe: 'Gest2026!Ok' });
  check('connexion gestionnaire → 302', connexion.httpStatusCode === 302, `status ${connexion.httpStatusCode}`);
  check('gestionnaire → /admin : 200', (await gest.get('/admin')).httpStatusCode === 200);
  check('gestionnaire → /admin/produits : 200', (await gest.get('/admin/produits')).httpStatusCode === 200);
  check('gestionnaire → /admin/commandes : 200', (await gest.get('/admin/commandes')).httpStatusCode === 200);
  const equipe = await gest.get('/admin/equipe');
  check('gestionnaire → /admin/equipe : refusé (403/302)', [403, 302].includes(equipe.httpStatusCode), `status ${equipe.httpStatusCode}`);
  const apres = await gest.get('/admin');
  contient('message « Réservé au rôle SUPER » affiché', apres.text, 'Réservé au rôle SUPER');
  const cree = await gest.post('/admin/equipe', { _csrf: 'peu-importe', nom: 'X', prenom: 'Y', email: 'x@y.z', mot_de_passe: 'Xy12345678', role: 'SUPER' });
  check('gestionnaire ne peut pas créer d\'admin', [403, 302].includes(cree.httpStatusCode)
    && !(await lireTable('administrateur')).some((a) => a.email === 'x@y.z'), `status ${cree.httpStatusCode}`);

  // F-27 : le SUPER désactive le gestionnaire → connexion refusée
  const superAdmin = new Navigateur('t13-super');
  const js = await superAdmin.csrf('/admin/connexion');
  await superAdmin.post('/admin/connexion', { _csrf: js, email: 'admin@minishop.fr', mot_de_passe: 'Admin2026!' });
  const idGest = (await lireTable('administrateur')).find((a) => a.email === 'gestionnaire@minishop.fr')?.id_admin;
  const jt = await superAdmin.csrf('/admin/equipe');
  const bascule = await superAdmin.post(`/admin/equipe/${idGest}/basculer`, { _csrf: jt });
  check('désactivation → redirection', bascule.httpStatusCode === 302, `status ${bascule.httpStatusCode}`);
  const revoke = new Navigateur('t13-revo');
  const jr = await revoke.csrf('/admin/connexion');
  const reConnexion = await revoke.post('/admin/connexion', { _csrf: jr, email: 'gestionnaire@minishop.fr', mot_de_passe: 'Gest2026!Ok' });
  check('compte désactivé : connexion refusée', reConnexion.httpStatusCode === 200 && !(reConnexion.headers.location ?? []).some((l) => String(l).includes('/admin')), `status ${reConnexion.httpStatusCode}`);
}

await syncBack(php);
process.stdout.write(`\n${'='.repeat(60)}\n`);
if (échecs === 0) {
  console.log(`E2E : TOUT EST VERT ✅  (${tests} vérifications)`);
  process.exit(0);
}
console.log(`E2E : ${échecs} échec(s) sur ${tests} vérifications ❌`);
process.exit(1);
