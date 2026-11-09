/**
 * MiniShop — E2E UI « Panier : mise en page du tableau » (UC-06, EF-CLI-06).
 *
 * Signalement : le titre « Panier — 1 article(s) » était coupé sur
 * trois lignes ; dans la ligne produit, libellés et valeurs étaient
 * alignés à droite alors que la quantité et les boutons restaient à
 * gauche ; les lignes Sous-total / Frais de port avaient des fonds
 * gris décalés et « Sous-total (marchandises TTC) » tenait sur trois
 * lignes. Cause : aucune règle CSS dédiée au tableau du panier.
 *
 * Parcours prouvé ici, par clics réels sur les pages (jsdom) :
 *   catalogue → fiche produit en stock → « Ajouter au panier » →
 *   /panier : la légende tient en un seul libellé, les cellules de
 *   montants (en-têtes, ligne, pied) portent la classe qui les
 *   aligne à droite — tout le reste à gauche —, le pied affiche ses
 *   trois lignes complètes ; la feuille servie contient les règles
 *   qui garantissent cette tenue ; la quantité se modifie toujours
 *   par le formulaire de la ligne, et le bouton principal
 *   « Valider ma commande » est toujours là.
 *
 * jsdom n'a pas de moteur de mise en page : la tenue visuelle est
 * vérifiée par ce qui la garantit — structure et classes dans le
 * DOM rendu, et règles CSS effectivement servies.
 *
 * Serveur cible : UI_BASE_URL si défini (CI : php -S + dev-router),
 * sinon scripts/wasm-serve.mjs démarré sur UI_PORT (défaut 8110).
 * Prérequis : data/minishop peuplé (le script npm reseede avant).
 *
 * Usage : node scripts/ui-e2e-panier-tableau.mjs      (ou npm run test:ui-panier-tableau)
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
const PORT = Number(process.env.UI_PORT ?? 8110);
let base = (process.env.UI_BASE_URL ?? `http://127.0.0.1:${PORT}`).replace(/\/$/, '');
let serveurLocal = null;

if (!process.env.UI_BASE_URL) {
  if (!existsSync(join(REPO, 'data/minishop/produit.json'))) {
    console.error('[ui-e2e-panier-tableau] base absente — lancez : node scripts/wasm_run.mjs scripts/seed.php');
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
    console.log(`::error title=ui-e2e-panier-tableau::${label.replaceAll('%', '%25').replaceAll('\n', '%0A')}`);
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

try {
  await attendreServeur();
  console.log(`\nMiniShop — E2E UI Panier, mise en page du tableau (serveur : ${base})\n`);
  const nav = new NavigateurUI();
  const doc = () => nav.doc();

  // ---------------------------------------------------------- 1. remplir le panier par clics
  console.log('== 1 · Catalogue → produit en stock → « Ajouter au panier » ==');
  await nav.aller('/catalogue');
  let formAjout = null;
  for (const lien of doc().querySelectorAll('a[href^="/produit/"]')) {
    await nav.aller(lien.getAttribute('href'));
    formAjout = doc().querySelector('form[action="/panier/ajouter"]');
    if (formAjout) break;
  }
  check('fiche produit en stock atteinte', !!formAjout);
  await nav.cliquerBouton(bouton(formAjout, 'Ajouter au panier'));
  check('arrivée sur /panier avec 1 article', new URL(nav.page.url).pathname === '/panier' && nav.texte().includes('1 article(s)'),
    nav.page.url);

  // ---------------------------------------------------------- 2. le tableau à l'écran
  console.log('\n== 2 · Le tableau du panier : légende, alignements, totaux ==');
  const tableau = doc().querySelector('table.tableau-panier');
  check('le tableau porte la classe tableau-panier (mise en page dédiée)', !!tableau);
  const legende = tableau?.querySelector('caption')?.textContent.replace(/\s+/g, ' ').trim() ?? '';
  check('la légende « Panier — 1 article(s) » est en une seule pièce', legende === 'Panier — 1 article(s)', legende);

  const entetes = [...tableau.querySelectorAll('thead th')];
  check('cinq colonnes : Produit, Prix unitaire TTC, Quantité, Total TTC, Actions',
    entetes.map((th) => th.textContent.trim()).join('|') === 'Produit|Prix unitaire TTC|Quantité|Total TTC|Actions',
    entetes.map((th) => th.textContent.trim()).join('|'));
  check('seuls les en-têtes de montants portent la classe d\'alignement à droite',
    entetes.filter((th) => th.classList.contains('montant')).map((th) => th.textContent.trim()).join('|') === 'Prix unitaire TTC|Total TTC');

  const ligne = tableau.querySelector('tbody tr');
  const cellules = [...ligne.querySelectorAll('td')];
  check('la ligne produit a ses deux montants en cellules montant (prix et total de ligne)',
    cellules.filter((td) => td.classList.contains('montant')).length === 2
      && cellules.every((td) => !td.classList.contains('montant') || /€$/.test(td.textContent.trim())),
    cellules.map((td) => td.textContent.replace(/\s+/g, ' ').trim()).join(' | '));
  check('quantité et boutons restent dans des cellules ordinaires (à gauche)',
    !!ligne.querySelector('form[action="/panier/quantite"] input[name="quantite"]')
      && !!ligne.querySelector('form[action="/panier/retirer"]'));

  const recap = doc().querySelector('.recap');
  const totalAvant = recap?.querySelector('[data-total-panier]')?.textContent.replace(/\s+/g, ' ').trim() ?? '';
  check('récapitulatif unique : sous-total, frais de port et total TTC',
    !!recap && ['Sous-total', 'Frais de port', 'Total TTC'].every((libelle) => recap.textContent.includes(libelle)));
  check('les trois montants du récapitulatif sont présents', recap?.querySelectorAll('dd').length === 3);
  check('le total figure dans une zone identifiable', totalAvant.endsWith('€'));

  // ---------------------------------------------------------- 3. la feuille qui garantit la tenue
  console.log('\n== 3 · Feuille servie : les règles de tenue sont là ==');
  const resCss = await fetch(`${base}/css/app.css`, { headers: nav.enTetes() });
  const css = await resCss.text();
  check('la feuille est servie (200) et définit .tableau-panier', resCss.status === 200 && css.includes('.tableau-panier'),
    `HTTP ${resCss.status}`);
  check('montants à droite, récapitulatif en grille et cartes mobiles étiquetées',
    css.includes('.tableau-panier .montant') && css.includes('text-align: right')
      && css.includes('.recap dl') && css.includes('.tableau-panier td::before') && css.includes('content: attr(data-label)'));
  check('le CTA « Valider ma commande » reste le bouton principal du parcours',
    !!doc().querySelector('button.bouton-principal')
      && doc().querySelector('button.bouton-principal')?.textContent.trim() === 'Valider ma commande →');

  // ---------------------------------------------------------- 4. le parcours fonctionne toujours
  console.log('\n== 4 · Quantité modifiée par le formulaire de la ligne ==');
  const formQuantite = doc().querySelector('form[action="/panier/quantite"]');
  formQuantite.querySelector('input[name="quantite"]').value = '2';
  await nav.cliquerBouton(bouton(formQuantite, 'Mettre à jour'));
  check('toujours sur /panier après la mise à jour', new URL(nav.page.url).pathname === '/panier', nav.page.url);
  const tableauApres = doc().querySelector('table.tableau-panier');
  check('la légende suit : « Panier — 1 article(s) » (une ligne, deux unités)',
    (tableauApres?.querySelector('caption')?.textContent.replace(/\s+/g, ' ').trim() ?? '') === 'Panier — 1 article(s)');
  const totalApres = doc().querySelector('[data-total-panier]')?.textContent.replace(/\s+/g, ' ').trim() ?? '';
  check('le Total TTC a été recalculé après le changement de quantité',
    totalApres !== '' && totalApres !== totalAvant, `avant=${totalAvant} après=${totalApres}`);

  console.log(`\n${'='.repeat(64)}`);
  if (échecs > 0) {
    console.log(`E2E UI panier (tableau) : ${échecs}/${tests} vérification(s) en échec ❌`);
    process.exitCode = 1;
  } else {
    console.log(`E2E UI panier (tableau) : TOUT EST VERT ✅ (${tests} vérifications, interactions DOM simulées avec jsdom)`);
  }
} catch (e) {
  console.error('\nE2E UI panier (tableau) : ERREUR fatale —', e);
  process.exitCode = 1;
} finally {
  if (serveurLocal) serveurLocal.kill('SIGTERM');
}
