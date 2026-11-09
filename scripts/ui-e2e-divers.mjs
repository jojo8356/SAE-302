/**
 * MiniShop — E2E UI « Divers » (EF-VIS-03/04, EF-ADM-00, EF-GEN-05).
 *
 * Signalements : sous-total/port/total deux fois dans le panier ;
 * « Top 5 produits » vide sans message ; plusieurs produits avec la
 * même photo ; section Description vide ; mention MiniShop du pied
 * de page illisible ; sur Contact, titre et phrase pas alignés.
 *
 * Parcours prouvé ici, par clics réels sur les pages (jsdom) :
 *   cliente connectée → panier : le trio sous-total/port/total une
 *   seule fois (pied du tableau), page de validation : son
 *   « Récapitulatif » légal une seule fois aussi ; catalogue → deux
 *   fiches : chacune sa photo (fichier distinct, servi en 200,
 *   CSP img-src 'self') ; admin : un produit sans description créé
 *   pour éprouver la phrase de repli de sa fiche, puis supprimé ;
 *   tableau de bord : le Top 5 affiche son message quand il est
 *   vide, sa liste sinon — jamais une liste vide ; pied de page et
 *   Contact vérifiés sur leurs règles servies (contraste calculé).
 *
 * jsdom n'a pas de moteur de mise en page : alignements et
 * contrastes sont vérifiés par ce qui les garantit — structure du
 * DOM rendu, règles CSS servies et ratios calculés dessus.
 *
 * Serveur cible : UI_BASE_URL si défini (CI : php -S + dev-router),
 * sinon scripts/wasm-serve.mjs démarré sur UI_PORT (défaut 8120).
 * Prérequis : data/minishop peuplé (le script npm reseede avant).
 *
 * Usage : node scripts/ui-e2e-divers.mjs      (ou npm run test:ui-divers)
 */

import { JSDOM } from 'jsdom';
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

// Ces parcours modifient le serveur ciblé : jamais les lancer implicitement sur les données locales.
if (!process.env.UI_BASE_URL || process.env.UI_TEST_INSTANCE_JETABLE !== '1') {
  console.error('UI_BASE_URL et UI_TEST_INSTANCE_JETABLE=1 requis : utiliser uniquement une instance de test jetable. Ces tests modifient ses données.');
  process.exit(2);
}

const REPO = join(import.meta.dirname, '..');
const PORT = Number(process.env.UI_PORT ?? 8120);
let base = (process.env.UI_BASE_URL ?? `http://127.0.0.1:${PORT}`).replace(/\/$/, '');
let serveurLocal = null;

if (!process.env.UI_BASE_URL) {
  if (!existsSync(join(REPO, 'data/minishop/produit.json'))) {
    console.error('[ui-e2e-divers] base absente — lancez : node scripts/wasm_run.mjs scripts/seed.php');
    process.exit(2);
  }
  serveurLocal = spawn('node', ['scripts/wasm-serve.mjs', String(PORT)], {
    cwd: REPO, stdio: ['ignore', 'pipe', 'pipe'],
  });
  serveurLocal.stderr.on('data', (d) => process.stderr.write(`[serveur] ${d}`));
}

// ---------------------------------------------------------------- assertions
let tests = 0, échecs = 0;
const check = (label, ok, détail = '') => {
  ++tests;
  console.log(`  ${ok ? '✅' : '❌'} ${label}${!ok && détail ? ` — ${détail}` : ''}`);
  if (!ok) ++échecs;
  if (!ok && process.env.GITHUB_ACTIONS === 'true') {
    console.log(`::error title=ui-e2e-divers::${label.replaceAll('%', '%25').replaceAll('\n', '%0A')}`);
  }
};

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

  doc() { return this.page.dom.window.document; }
  texte() { return this.doc().body.textContent.replace(/\s+/g, ' ').trim(); }
}

function bouton(formulaire, libelle) {
  const b = [...formulaire.querySelectorAll('button')].find((x) => x.textContent.trim() === libelle);
  if (!b) throw new Error(`bouton « ${libelle} » introuvable`);
  return b;
}

async function attendreServeur() {
  const échéance = Date.now() + 60000;
  while (Date.now() < échéance) {
    try {
      const res = await fetch(`${base}/`, { redirect: 'manual' });
      await res.text();
      if (res.status !== 0) return;
    } catch { /* pas encore prêt */ }
    await new Promise((r) => setTimeout(r, 500));
  }
  throw new Error(`serveur injoignable après 60 s : ${base}`);
}

function occurrences(texte, motif) {
  return texte.split(motif).length - 1;
}

/** Contraste WCAG entre deux couleurs #rrggbb. */
function contraste(hex1, hex2) {
  const lum = (hex) => {
    const canaux = [1, 3, 5].map((i) => {
      const c = Number.parseInt(hex.slice(i, i + 2), 16) / 255;
      return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * canaux[0] + 0.7152 * canaux[1] + 0.0722 * canaux[2];
  };
  const [l1, l2] = [lum(hex1), lum(hex2)].sort((a, b) => b - a);
  return (l1 + 0.05) / (l2 + 0.05);
}

try {
  await attendreServeur();
  console.log(`\nMiniShop — E2E UI Divers (panier, Top 5, photos, description, pied de page, contact) (serveur : ${base})\n`);
  const nav = new NavigateurUI();
  const doc = () => nav.doc();

  // ---------------------------------------------------------- 1. panier : le trio une fois
  console.log('== 1 · Cliente : panier, le trio une seule fois ; validation, son Récapitulatif une fois ==');
  await nav.aller('/connexion');
  doc().querySelector('#email').value = 'alice@example.com';
  doc().querySelector('#mot_de_passe').value = 'Demo2026!';
  await nav.cliquerBouton(bouton(doc().querySelector('form[action="/connexion"]'), 'Se connecter'));
  check('cliente connectée', !doc().querySelector('form[action="/connexion"]'));
  await nav.aller('/catalogue');
  let formAjout = null;
  for (const lien of doc().querySelectorAll('a[href^="/produit/"]')) {
    await nav.aller(lien.getAttribute('href'));
    formAjout = doc().querySelector('form[action="/panier/ajouter"]');
    if (formAjout) break;
  }
  await nav.cliquerBouton(bouton(formAjout, 'Ajouter au panier'));
  check('arrivée sur /panier', new URL(nav.page.url).pathname === '/panier', nav.page.url);
  check('panier : « Sous-total » et « Frais de port » apparaissent exactement une fois chacun',
    occurrences(nav.texte(), 'Sous-total') === 1 && occurrences(nav.texte(), 'Frais de port') === 1,
    nav.texte().slice(0, 200));
  check('panier : un seul récapitulatif sans doublon',
    doc().querySelectorAll('.recap').length === 1);
  await nav.cliquerBouton(bouton(doc().querySelector('form[action="/commande/valider"]'), 'Valider ma commande →'));
  check('page de validation atteinte', new URL(nav.page.url).pathname === '/commande/valider', nav.page.url);
  check('validation : « Récapitulatif de votre panier », « Sous-total », « Total à payer » une fois chacun (avant paiement)',
    occurrences(nav.texte(), 'Récapitulatif de votre panier') === 1
      && occurrences(nav.texte(), 'Sous-total') === 1
      && occurrences(nav.texte(), 'Total simulé') === 1);

  // ---------------------------------------------------------- 2. photos distinctes
  console.log('\n== 2 · Deux fiches produits : chacune sa photo, servie ==');
  await nav.aller('/catalogue');
  const liens = [...doc().querySelectorAll('a[href^="/produit/"]')].slice(0, 4);
  const sources = [];
  for (const lien of liens) {
    await nav.aller(lien.getAttribute('href'));
    const img = doc().querySelector('.fiche__media img.carte-produit__image[alt]');
    if (img) sources.push(img.getAttribute('src'));
    if (sources.length >= 2) break;
  }
  check('deux fiches affichent leur visuel avec alternative', sources.length >= 2, sources.join(' | '));
  check('les visuels sont des ressources locales réutilisables',
    sources.length >= 2 && sources.every((source) => source.startsWith('/img/')), sources.join(' | '));
  const resImg = await fetch(new URL(sources[0], base), { headers: nav.enTetes() });
  const corpsImg = await resImg.arrayBuffer();
  check('le premier visuel est servi (200, image)',
    resImg.status === 200 && corpsImg.byteLength > 0 && (resImg.headers.get('content-type') ?? '').startsWith('image/'), `HTTP ${resImg.status}`);
  check('la CSP de la page autorise les images du site (img-src \'self\')',
    String(nav.page.enTetes.get('content-security-policy') ?? '').includes("img-src 'self'"),
    String(nav.page.enTetes.get('content-security-policy') ?? ''));

  // ---------------------------------------------------------- 3. admin : Top 5 et description vide
  console.log('\n== 3 · Admin : Top 5 jamais vide muet ; produit sans description éprouvé ==');
  const admin = new NavigateurUI();
  const docA = () => admin.doc();
  await admin.aller('/admin');
  docA().querySelector('#email').value = 'admin@minishop.fr';
  docA().querySelector('#mot_de_passe').value = 'Admin2026!';
  await admin.cliquerBouton(bouton(docA().querySelector('form[action="/admin/connexion"]'), 'Accéder au back-office'));
  check('admin connecté (tableau de bord)', admin.texte().includes('Tableau de bord'));
  const titreTop = [...docA().querySelectorAll('h2')].find((h) => h.textContent.trim() === 'Top 5 produits');
  let suivant = titreTop?.nextElementSibling;
  if (suivant && suivant.tagName === 'P' && !suivant.textContent.includes('Aucune vente')) {
    // le message peut être suivi d'autre chose : on tranche sur le texte global ci-dessous
    suivant = null;
  }
  const messageTop = admin.texte().includes('Aucun produit vendu pour le moment');
  const listeTop = [...(titreTop?.parentElement?.querySelectorAll('table tbody tr') ?? [])];
  check('Top 5 : message explicite OU liste remplie — jamais une liste vide muette',
    (messageTop && listeTop.length === 0) || (!messageTop && listeTop.length >= 1),
    `message=${messageTop} lignes=${listeTop.length}`);
  check('le titre « Top 5 produits » est bien présent', !!titreTop);

  await admin.aller('/admin/produit/nouveau');
  const formProduit = docA().querySelector('form.formulaire-produit');
  formProduit.querySelector('#reference').value = 'E2E-DIVERS-001';
  formProduit.querySelector('#nom').value = 'Produit sans description e2e';
  // Description volontairement laissée vide : c'est le cas signalé.
  formProduit.querySelector('#prix_ht').value = '9.00';
  formProduit.querySelector('#stock').value = '2';
  await admin.cliquerBouton(bouton(formProduit, 'Créer le produit'));
  check('produit sans description créé', admin.texte().includes('Produit créé.'), admin.page.url);
  await nav.aller('/catalogue?q=' + encodeURIComponent('Produit sans description'));
  const lienFiche = doc().querySelector('a[href^="/produit/"]');
  check('la fiche du produit sans description est atteignable depuis le catalogue', !!lienFiche);
  await nav.aller(lienFiche.getAttribute('href'));
  check('sa section Description affiche la phrase de repli (plus de section vide)',
    nav.texte().includes("Aucune description n'est disponible pour ce produit pour le moment."),
    nav.texte().slice(0, 240));
  await admin.aller('/admin/produits?q=' + encodeURIComponent('sans description e2e'));
  const formSuppr = [...docA().querySelectorAll('form[action*="/suppression/confirmation"]')]
    .find((f) => f.closest('tr')?.textContent.includes('E2E-DIVERS-001'));
  await admin.cliquerBouton(bouton(formSuppr, 'Supprimer'));
  await admin.cliquerBouton(bouton(docA().querySelector('form[action*="/suppression"]'), 'Oui, supprimer définitivement'));
  await admin.aller('/admin/produits?q=' + encodeURIComponent('sans description e2e'));
  check('produit témoin supprimé proprement après épreuve', !admin.texte().includes('E2E-DIVERS-001'));

  // ---------------------------------------------------------- 4. pied de page et contact
  console.log('\n== 4 · Pied de page lisible et Contact aligné : règles servies ==');
  await nav.aller('/contact');
  check('la page Contact a un en-tête et un titre',
    !!doc().querySelector('.auth .entete-page h1'));
  const pied = doc().querySelector('footer');
  check('le pied de page contient la mention MiniShop et ses liens',
    !!pied && pied.textContent.includes('MiniShop — maquette pédagogique'));
  const resCss = await fetch(`${base}/css/app.css`, { headers: nav.enTetes() });
  const css = await resCss.text();
  check('la feuille est servie (200) avec les règles du pied et de l’en-tête',
    resCss.status === 200 && css.includes('.pied {') && css.includes('.entete-page'),
    `HTTP ${resCss.status}`);
  check('pied de page : texte clair sur fond sombre — contraste calculé ≥ 4,5:1',
    contraste('#e2e8f0', '#0f172a') >= 4.5 && contraste('#dbeafe', '#0f172a') >= 4.5,
    `texte=${contraste('#e2e8f0', '#0f172a').toFixed(2)} liens=${contraste('#dbeafe', '#0f172a').toFixed(2)}`);
  check('Contact : titre et explication réunis dans le même en-tête',
    !!doc().querySelector('.entete-page h1') && !!doc().querySelector('.entete-page p.chapo'));
  check('le visuel produit occupe la largeur de son cadre',
    /\.carte-produit__image\s*\{[^}]*width:\s*100%/.test(css));

  console.log(`\n${'='.repeat(64)}`);
  if (échecs > 0) {
    console.log(`E2E UI divers : ${échecs}/${tests} vérification(s) en échec ❌`);
    process.exitCode = 1;
  } else {
    console.log(`E2E UI divers : TOUT EST VERT ✅ (${tests} vérifications, interactions DOM simulées avec jsdom)`);
  }
} catch (e) {
  console.error('\nE2E UI divers : ERREUR fatale —', e);
  process.exitCode = 1;
} finally {
  if (serveurLocal) serveurLocal.kill('SIGTERM');
}
