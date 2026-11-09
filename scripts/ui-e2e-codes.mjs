#!/usr/bin/env node
/**
 * MiniShop — E2E « Codes techniques » (port 8122 ; UI_BASE_URL en CI).
 *
 * Signalement : le client voyait des codes techniques —
 * PORT_OFFERT_A_PARTIR_DE_80.00 et LIVRAISON_STANDARD (panier,
 * récapitulatif, validation), « Tracé par trg_history_statut »,
 * « Création tracée par trg_history_creation » (historiques),
 * « v_etat_stock » (stocks) et les références RB-xx partout, jusque
 * dans les CGV et les mentions légales.
 *
 * Vérifié ici en vrai parcours navigateur (jsdom) :
 *   1. fiche produit, catalogue : aucun code à l'écran ;
 *   2. panier : motif de port en français, standard puis offert ;
 *   3. validation puis détail de commande : historique en français
 *      (« Création de la commande »), aucun trg_ ;
 *   4. CGV et mentions légales : aucun RB-xx ;
 *   5. back-office (tableau de bord, stocks, catégories, détail
 *      commande) : aucun code, stocks « Ajouter / retirer ».
 */

import { spawn, spawnSync } from 'node:child_process';
import { JSDOM } from 'jsdom';

// Ces parcours modifient le serveur ciblé : jamais les lancer implicitement sur les données locales.
if (!process.env.UI_BASE_URL || process.env.UI_TEST_INSTANCE_JETABLE !== '1') {
  console.error('UI_BASE_URL et UI_TEST_INSTANCE_JETABLE=1 requis : utiliser uniquement une instance de test jetable. Ces tests modifient ses données.');
  process.exit(2);
}

const PORT = 8122;
const base = process.env.UI_BASE_URL ?? `http://127.0.0.1:${PORT}`;
let échecs = 0;
let serveur = null;

function check(libellé, ok, détail = '') {
  console.log(`  ${ok ? '✅' : '❌'} ${libellé}${détail !== '' && !ok ? ' — ' + détail : ''}`);
  if (!ok) {
    échecs += 1;
  }
}
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));

// ---------------------------------------------------------------- navigateur UI
/** Mini-navigateur : cookies, redirections suivies comme un navigateur, DOM jsdom cliquable. */
class NavigateurUI {
  constructor() {
    this.cookies = {};
    this.page = null;
    this.soumission = null;
  }

  enTetes(extra = {}) {
    const cookie = Object.entries(this.cookies).map(([k, v]) => `${k}=${v}`).join('; ');
    return cookie ? { cookie, ...extra } : { ...extra };
  }

  mémorise(res) {
    for (const sc of res.headers.getSetCookie()) {
      const m = /^([^=]+)=([^;]*)/.exec(sc);
      if (m) this.cookies[m[1]] = m[2];
    }
  }

  async aller(chemin) {
    let url = new URL(chemin, base).href;
    for (let i = 0; i < 6; ++i) {
      const res = await fetch(url, { headers: this.enTetes(), redirect: 'manual' });
      this.mémorise(res);
      const html = await res.text();
      if (res.status >= 300 && res.status < 400 && res.headers.get('location')) {
        url = new URL(res.headers.get('location'), url).href;
        continue;
      }
      const dom = new JSDOM(html, { url });
      this.page = { dom, url, statut: res.status, enTetes: res.headers };
      this.soumission = null;
      dom.window.document.addEventListener('submit', (e) => {
        e.preventDefault();
        this.soumission = { formulaire: e.target, donnees: new dom.window.FormData(e.target) };
      }, true);
      return this.page;
    }
    throw new Error(`trop de redirections vers ${chemin}`);
  }

  async cliquerBouton(bouton) {
    this.soumission = null;
    bouton.click();
    if (!this.soumission) {
      const formulaire = bouton.closest('form');
      if (formulaire && typeof formulaire.requestSubmit === 'function') {
        formulaire.requestSubmit(bouton);
      }
    }
    if (!this.soumission) {
      throw new Error(`le clic sur « ${bouton.textContent.trim()} » n'a soumis aucun formulaire`);
    }
    const { formulaire, donnees } = this.soumission;
    const action = new URL(formulaire.getAttribute('action') ?? '', this.page.url).href;
    const methode = (formulaire.getAttribute('method') ?? 'get').toLowerCase();
    if (methode === 'get') {
      const params = new URLSearchParams();
      for (const [cle, valeur] of donnees.entries()) params.append(cle, String(valeur));
      const cible = params.toString() ? `${action}?${params}` : action;
      return this.aller(cible);
    }
    const corps = new URLSearchParams();
    for (const [cle, valeur] of donnees.entries()) corps.append(cle, String(valeur));
    const res = await fetch(action, {
      method: 'POST',
      headers: this.enTetes({ 'content-type': 'application/x-www-form-urlencoded' }),
      body: corps.toString(),
      redirect: 'manual',
    });
    this.mémorise(res);
    await res.text();
    if (res.status >= 300 && res.status < 400 && res.headers.get('location')) {
      return this.aller(new URL(res.headers.get('location'), action).pathname);
    }
    return this.aller(new URL(action).pathname);
  }

  remplir(selecteur, valeur) {
    const champ = this.page.dom.window.document.querySelector(selecteur);
    if (!champ) {
      throw new Error(`champ introuvable : ${selecteur}`);
    }
    champ.value = valeur;
  }

  get doc() {
    return this.page.dom.window.document;
  }

  get texte() {
    return this.doc.body?.textContent ?? '';
  }
}

const CODES = ['RB-', 'trg_', 'v_etat_stock', 'v_catalogue', 'PORT_OFFERT', 'LIVRAISON_STANDARD', 'EF-', 'ENF-', 'UC-', 'Varier de', 'sp_revenue_report'];
function aucunCode(texte) {
  for (const code of CODES) {
    if (texte.includes(code)) {
      return code;
    }
  }
  return '';
}

async function attendreServeur() {
  for (let i = 0; i < 60; i += 1) {
    try {
      const res = await fetch(`${base}/catalogue`);
      if (res.ok) {
        return;
      }
    } catch {
      // pas encore prêt
    }
    await attendre(500);
  }
  throw new Error('serveur non prêt');
}

async function aller() {
  if (!process.env.UI_BASE_URL) {
    serveur = spawn('node', ['scripts/wasm-serve.mjs'], { env: { ...process.env, PORT: String(PORT) }, stdio: 'inherit' });
    await attendreServeur();
  }

  console.log('\n[Codes techniques] 1 · Fiche et catalogue sans codes, motifs en français');
  const navigateur = new NavigateurUI();
  await navigateur.aller('/catalogue');
  check("catalogue : aucun code technique à l'écran", aucunCode(navigateur.texte) === '', aucunCode(navigateur.texte));
  const liens = [...navigateur.doc.querySelectorAll('a[href^="/produit/"]')];
  let lienFiche = liens.find((a) => a.textContent.includes('Câble')) ?? liens[0];
  await navigateur.aller(lienFiche.getAttribute('href'));
  check("fiche produit : aucun code technique à l'écran", aucunCode(navigateur.texte) === '', aucunCode(navigateur.texte));
  const boutonAjout = navigateur.doc.querySelector('form[action="/panier/ajouter"] button');
  await navigateur.cliquerBouton(boutonAjout);
  check('panier : motif de port en français (« livraison standard (4,90 €) »)',
    navigateur.texte.includes('livraison standard (4,90 €)'));
  check('panier : ni PORT_OFFERT…, ni LIVRAISON_STANDARD, ni RB-xx',
    aucunCode(navigateur.texte) === '', aucunCode(navigateur.texte));

  console.log('\n[Codes techniques] 2 · Port offert et validation : français aussi');
  await navigateur.aller('/catalogue?q=Casque');
  const lienCasque = [...navigateur.doc.querySelectorAll('a[href^="/produit/"]')].find((a) => (a.getAttribute('aria-label') ?? a.textContent).includes('Casque'));
  if (!lienCasque) throw new Error('Aucun casque disponible dans le catalogue de test');
  await navigateur.aller(lienCasque.getAttribute('href'));
  const boutonAjout2 = navigateur.doc.querySelector('form[action="/panier/ajouter"] button');
  await navigateur.cliquerBouton(boutonAjout2);
  check('panier (≥ 80 €) : « frais de port offerts dès 80,00 € de marchandises »',
    navigateur.texte.includes('frais de port offerts dès 80,00 € de marchandises'));
  check('panier (≥ 80 €) : aucun code technique', aucunCode(navigateur.texte) === '', aucunCode(navigateur.texte));

  const visiteur = navigateur; // même session : le panier suit le client
  await visiteur.aller('/inscription');
  visiteur.remplir('#nom', 'Dupont');
  visiteur.remplir('#prenom', 'Code');
  visiteur.remplir('#email', 'codes@example.com');
  visiteur.remplir('#mot_de_passe', 'MotDePasse1!');
  visiteur.remplir('#confirmation', 'MotDePasse1!');
  visiteur.doc.querySelector('input[name="cgv"]').checked = true;
  await visiteur.cliquerBouton(visiteur.doc.querySelector('form[action="/inscription"] button'));
  await visiteur.aller('/compte');
  visiteur.remplir('#telephone', '0607080910');
  visiteur.remplir('#adresse', '12 rue de France');
  visiteur.remplir('#code_postal', '06000');
  visiteur.remplir('#ville', 'Nice');
  await visiteur.cliquerBouton(visiteur.doc.querySelector('form[action="/compte"] button'));
  await visiteur.aller('/commande/valider');
  check('validation : récapitulatif et motif en français, aucun code',
    aucunCode(visiteur.texte) === '' && visiteur.texte.includes('frais de port offerts'),
    aucunCode(visiteur.texte));
  const boutonValider = visiteur.doc.querySelector('form[action="/commande/valider"] button');
  await visiteur.cliquerBouton(boutonValider);
  check('détail commande : historique en français (« Création de la commande »)',
    visiteur.texte.includes('Création de la commande'));
  check('détail commande : aucun trg_, aucun RB-xx',
    aucunCode(visiteur.texte) === '', aucunCode(visiteur.texte));
  await visiteur.aller('/mes-commandes');
  check('mes commandes : aucun code technique', aucunCode(visiteur.texte) === '', aucunCode(visiteur.texte));
  await visiteur.aller('/compte');
  check('mon compte : aucun code technique', aucunCode(visiteur.texte) === '', aucunCode(visiteur.texte));

  console.log('\n[Codes techniques] 3 · Pages légales et back-office');
  const publicNav = new NavigateurUI();
  await publicNav.aller('/cgv');
  check('CGV : aucun code technique (RB-18 a disparu)', aucunCode(publicNav.texte) === '', aucunCode(publicNav.texte));
  await publicNav.aller('/mentions-legales');
  check('mentions légales : aucun code technique (RB-11/RB-12 ont disparu)', aucunCode(publicNav.texte) === '', aucunCode(publicNav.texte));

  const admin = new NavigateurUI();
  await admin.aller('/admin/connexion');
  admin.remplir('#email', 'admin@minishop.fr');
  admin.remplir('#mot_de_passe', 'Admin2026!');
  await admin.cliquerBouton(admin.doc.querySelector('form[action="/admin/connexion"] button'));
  check('tableau de bord : aucun code technique', aucunCode(admin.texte) === '', aucunCode(admin.texte));
  check('tableau de bord : « Alertes de stock » et « Chiffre d’affaires par statut » sans code',
    admin.texte.includes('Alertes de stock') && admin.texte.includes('par statut'));
  await admin.aller('/admin/stocks');
  check('stocks : « Ajouter / retirer », aucun v_etat_stock ni Varier de',
    admin.texte.includes('Ajouter / retirer') && aucunCode(admin.texte) === '', aucunCode(admin.texte));
  await admin.aller('/admin/categories');
  check('catégories admin : aucun code technique', aucunCode(admin.texte) === '', aucunCode(admin.texte));
  await admin.aller('/admin/produits');
  check('produits admin : aucun code technique', aucunCode(admin.texte) === '', aucunCode(admin.texte));
  await admin.aller('/admin/commandes');
  check('commandes admin : aucun code technique', aucunCode(admin.texte) === '', aucunCode(admin.texte));
  const lienDetail = admin.doc.querySelector('td a[href^="/admin/commandes/"]');
  await admin.aller(lienDetail.getAttribute('href'));
  check('détail commande admin : historique en français, aucun code',
    admin.texte.includes('Création de la commande') && aucunCode(admin.texte) === '',
    aucunCode(admin.texte));
  await admin.aller('/admin/equipe');
  check('équipe : aucun code technique', aucunCode(admin.texte) === '', aucunCode(admin.texte));
  await admin.aller('/admin/produit/nouveau');
  check('formulaire produit : aucun code technique (seuil d’alerte sans RB-03)',
    aucunCode(admin.texte) === '' && admin.texte.includes('Seuil d'), aucunCode(admin.texte));

  console.log(`\n${échecs === 0 ? '✅' : '❌'} Codes techniques : ${échecs === 0 ? 'toutes les vérifications réussies' : échecs + ' vérification(s) en échec'}\n`);
  if (serveur) {
    serveur.kill();
  }
  process.exit(échecs === 0 ? 0 : 1);
}

aller().catch(async (error) => {
  console.error('❌ Erreur E2E codes techniques :', error.message);
  if (serveur) {
    serveur.kill();
  }
  process.exit(1);
});
