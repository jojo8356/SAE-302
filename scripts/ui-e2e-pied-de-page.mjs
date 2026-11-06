/**
 * MiniShop — E2E UI « Pied de page : pages Aide, Livraison,
 * Confidentialité et Rétractation absentes » (EF-GEN-05, F-24).
 *
 * Signalement : le pied de page ne contenait que « Mentions légales »
 * et « Conditions générales de vente » ; les entrées Aide, Livraison,
 * Confidentialité et Rétractation de la liste de tests (et Contact,
 * cité par EF-GEN-05) n'avaient ni lien, ni page.
 *
 * Parcours prouvé ici, en visiteur ANONYME (les pages statiques sont
 * publiques), par clics réels sur le DOM (jsdom) :
 *   - le pied de page, sur l'accueil comme sur le catalogue, relie
 *     les huit entrées attendues ;
 *   - chaque lien mène à une page réelle (HTTP 200) portant son
 *     propre titre, avec un contenu cohérent (port 4,90 € / 80 €,
 *     rétractation 14 jours, conservation 36 mois…).
 *
 * Serveur cible : UI_BASE_URL si défini (CI : php -S + dev-router),
 * sinon scripts/wasm-serve.mjs démarré sur UI_PORT (défaut 8104).
 * Prérequis : data/minishop peuplé (le script npm reseede avant).
 *
 * Usage : node scripts/ui-e2e-pied-de-page.mjs      (ou npm run test:ui-pied-de-page)
 */

import { JSDOM } from 'jsdom';
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const REPO = join(import.meta.dirname, '..');
const PORT = Number(process.env.UI_PORT ?? 8104);
let base = (process.env.UI_BASE_URL ?? `http://127.0.0.1:${PORT}`).replace(/\/$/, '');
let serveurLocal = null;

if (!process.env.UI_BASE_URL) {
  if (!existsSync(join(REPO, 'data/minishop/produit.json'))) {
    console.error('[ui-e2e-pied-de-page] base absente — lancez : node scripts/wasm_run.mjs scripts/seed.php');
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
    console.log(`::error title=ui-e2e-pied-de-page::${label.replaceAll('%', '%25').replaceAll('\n', '%0A')}`);
  }
};

// ---------------------------------------------------------------- mini-navigateur (lecture seule : ces pages sont publiques)
let page = null;

async function aller(chemin) {
  let url = new URL(chemin, base).href;
  for (let i = 0; i < 6; ++i) {
    const res = await fetch(url, { redirect: 'manual' });
    const html = await res.text();
    if (res.status >= 300 && res.status < 400 && res.headers.get('location')) {
      url = new URL(res.headers.get('location'), url).href;
      continue;
    }
    page = { dom: new JSDOM(html, { url }), url, statut: res.status };
    return page;
  }
  throw new Error(`trop de redirections vers ${chemin}`);
}

const doc = () => page.dom.window.document;
const texte = () => doc().body.textContent.replace(/\s+/g, ' ').trim();

/** Liens du pied de page : { href: libellé }. */
function liensPiedDePage() {
  const liens = {};
  for (const a of doc().querySelectorAll('footer a')) {
    liens[a.getAttribute('href')] = a.textContent.trim();
  }
  return liens;
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

// href → [libellé dans le pied de page, titre H1 attendu, phrase clé]
const ENTREES = {
  '/aide': ['Aide', 'Aide', 'Comment commander ?'],
  '/livraison': ['Livraison', 'Livraison', 'Offerte dès 80,00 €'],
  '/retractation': ['Rétractation', 'Rétractation', '14 jours'],
  '/confidentialite': ['Confidentialité', 'Confidentialité', '36 mois'],
  '/contact': ['Contact', 'Contact', 'maquette pédagogique'],
  '/a-propos': ['À propos', 'À propos', 'MiniShop'],
  '/mentions-legales': ['Mentions légales', 'Mentions légales', 'maquette pédagogique'],
  '/cgv': ['Conditions générales de vente', 'Conditions générales de vente', 'Droit de rétractation'],
};

try {
  await attendreServeur();
  console.log(`\nMiniShop — E2E UI Pied de page (pages Aide, Livraison, Confidentialité, Rétractation) (serveur : ${base})\n`);

  console.log('== 1 · Accueil (visiteur anonyme) : les huit liens du pied de page ==');
  await aller('/');
  check('page d\'accueil en 200 sans connexion', page.statut === 200, `HTTP ${page.statut}`);
  const liensAccueil = liensPiedDePage();
  for (const [href, [libelle]] of Object.entries(ENTREES)) {
    check(`lien « ${libelle} » → ${href}`, liensAccueil[href] === libelle, JSON.stringify(liensAccueil));
  }

  console.log('\n== 2 · Chaque lien mène à sa page (200, titre, contenu) ==');
  for (const [href, [libelle, titre, phrase]] of Object.entries(ENTREES)) {
    await aller(href);
    const h1 = doc().querySelector('h1')?.textContent.trim() ?? '';
    check(`${libelle} : HTTP 200`, page.statut === 200, `HTTP ${page.statut}`);
    check(`${libelle} : titre H1 « ${titre} »`, h1.includes(titre), h1);
    check(`${libelle} : contenu (« ${phrase} »)`, texte().includes(phrase), texte().slice(0, 140));
  }

  console.log('\n== 3 · Le même pied de page sur une autre page (catalogue) ==');
  await aller('/catalogue');
  check('catalogue en 200', page.statut === 200, `HTTP ${page.statut}`);
  const liensCatalogue = liensPiedDePage();
  check('les huit liens sont aussi au pied du catalogue',
    Object.keys(ENTREES).every((href) => liensCatalogue[href] === ENTREES[href][0]),
    JSON.stringify(liensCatalogue));

  console.log('\n== 4 · De page en page : les renvois internes fonctionnent ==');
  await aller('/aide');
  const lienLivraison = [...doc().querySelectorAll('a')].find((a) => a.getAttribute('href') === '/livraison');
  check('depuis Aide, un lien mène à Livraison', !!lienLivraison);
  await aller(lienLivraison.getAttribute('href'));
  check('… et la page Livraison s\'ouvre (200, H1 Livraison)',
    page.statut === 200 && (doc().querySelector('h1')?.textContent.trim() ?? '') === 'Livraison');
  await aller('/retractation');
  const lienCgv = [...doc().querySelectorAll('a')].find((a) => a.getAttribute('href') === '/cgv');
  await aller(lienCgv.getAttribute('href'));
  check('depuis Rétractation, le renvoi aux CGV s\'ouvre (200)',
    page.statut === 200 && texte().includes('Droit de rétractation'));

  console.log(`\n${'='.repeat(64)}`);
  if (échecs > 0) {
    console.log(`E2E UI pied de page : ${échecs}/${tests} vérification(s) en échec ❌`);
    process.exitCode = 1;
  } else {
    console.log(`E2E UI pied de page : TOUT EST VERT ✅ (${tests} vérifications, clics réels sur l'UI)`);
  }
} catch (e) {
  console.error('\nE2E UI pied de page : ERREUR fatale —', e);
  process.exitCode = 1;
} finally {
  if (serveurLocal) serveurLocal.kill('SIGTERM');
}
