/**
 * MiniShop — E2E UI « Panier, bouton ‹ Valider ma commande › »
 * (UC-06 → UC-07, EF-CLI-06).
 *
 * Même principe que scripts/ui-e2e-categories.mjs : les vraies pages
 * sont pilotées au DOM (jsdom) — champs remplis, boutons cliqués,
 * résultats lus dans la page. Aucun appel d'API direct.
 *
 * Signalement : le bouton principal du parcours d'achat s'affichait en
 * texte bleu souligné sur un rectangle orange, presque illisible
 * (~1000 px comme 768 px) — c'était un simple <a> sans style de bouton.
 * La suite verrouille le correctif à l'écran :
 *   - sur /panier, le CTA est un <button type="submit"
 *     class="bouton-principal"> dans un GET /commande/valider, et il
 *     n'existe plus aucun <a href="/commande/valider"> ;
 *   - la feuille /minishop.css est liée par le gabarit, servie (200)
 *     et autorisée par la CSP (style-src 'self') ;
 *   - le clic sur le bouton mène réellement à la validation : page de
 *     connexion si on est anonyme, puis page « Valider ma commande »
 *     une fois connecté (le parcours d'achat complet, par clics :
 *     catalogue → fiche produit → Ajouter au panier → panier → CTA).
 * Panier vide : aucun CTA (non-régression).
 *
 * Serveur cible : UI_BASE_URL si défini (ex. CI : php -S + dev-router),
 * sinon scripts/wasm-serve.mjs démarré sur UI_PORT (défaut 8094).
 * Prérequis : data/minishop peuplé (node scripts/wasm_run.mjs scripts/seed.php).
 *
 * Usage : node scripts/ui-e2e-panier.mjs      (ou npm run test:ui-panier)
 */

import { JSDOM } from 'jsdom';
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const REPO = join(import.meta.dirname, '..');
const PORT = Number(process.env.UI_PORT ?? 8094);
let base = (process.env.UI_BASE_URL ?? `http://127.0.0.1:${PORT}`).replace(/\/$/, '');
let serveurLocal = null;

if (!process.env.UI_BASE_URL) {
  if (!existsSync(join(REPO, 'data/minishop/produit.json'))) {
    console.error('[ui-e2e-panier] base absente — lancez : node scripts/wasm_run.mjs scripts/seed.php');
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
    console.log(`::error title=ui-e2e-panier::${label.replaceAll('%', '%25').replaceAll('\n', '%0A')}`);
  }
};

// ---------------------------------------------------------------- navigateur UI
/** Mini-navigateur : cookies, redirections suivies comme un navigateur, DOM jsdom cliquable. */
class NavigateurUI {
  constructor() {
    this.cookies = {};
    this.page = null;   // { dom, url, statut, enTetes }
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

  /** Charge une page (GET) en suivant les redirections, et la rend « cliquable ». */
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

  /** Clique sur un bouton et envoie le formulaire intercepté. */
  async cliquerBouton(bouton) {
    this.soumission = null;
    bouton.click();
    if (!this.soumission) {
      // Repli : certains boutons exigent la soumission explicite du formulaire.
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

/** Bouton d'un formulaire, retrouvé par son libellé visible. */
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
  console.log(`\nMiniShop — E2E UI Panier / bouton « Valider ma commande » (serveur : ${base})\n`);
  const nav = new NavigateurUI();
  const doc = () => nav.doc();

  // ---------------------------------------------------------- 1. panier vide
  console.log('== 1 · Panier vide : aucun CTA ==');
  await nav.aller('/panier');
  check('« Votre panier est vide. »', nav.texte().includes('Votre panier est vide.'));
  check('aucun bouton « Valider ma commande »', !doc().querySelector('.bouton-principal'));

  // ---------------------------------------------------------- 2. ajout par clics
  console.log('\n== 2 · Catalogue → fiche produit → « Ajouter au panier » (clics) ==');
  await nav.aller('/catalogue');
  const liensProduits = [...doc().querySelectorAll('a[href^="/produit/"]')];
  check('des produits du catalogue sont cliquables', liensProduits.length > 0, 'aucun lien produit');
  // Première fiche en stock (une fiche en rupture n'a pas de formulaire d'ajout).
  let formAjout = null;
  for (const lien of liensProduits) {
    await nav.aller(lien.getAttribute('href'));
    formAjout = doc().querySelector('form[action="/panier/ajouter"]');
    if (formAjout) break;
  }
  check('formulaire d\'ajout présent sur une fiche en stock', !!formAjout);
  await nav.cliquerBouton(bouton(formAjout, 'Ajouter au panier'));
  check('arrivée sur /panier après l\'ajout', new URL(nav.page.url).pathname === '/panier', nav.page.url);
  check('le panier contient 1 article', nav.texte().includes('1 article(s)'));

  // ---------------------------------------------------------- 3. le CTA à l'écran
  console.log('\n== 3 · Le CTA du panier : un vrai bouton, plus un lien ==');
  const cta = doc().querySelector('.bouton-principal');
  check('le CTA existe', !!cta);
  check('c\'est un <button type="submit">', cta?.tagName === 'BUTTON' && cta?.type === 'submit',
    cta ? `${cta.tagName} type=${cta.type}` : 'absent');
  check('libellé « Valider ma commande → »', cta?.textContent.trim() === 'Valider ma commande →', cta?.textContent.trim() ?? '');
  check('il soumet un GET vers /commande/valider',
    cta?.closest('form')?.getAttribute('action') === '/commande/valider'
      && (cta?.closest('form')?.getAttribute('method') ?? 'get').toLowerCase() === 'get');
  check('plus aucun lien « Valider ma commande »', !doc().querySelector('a[href="/commande/valider"]'));
  check('« Continuer mes achats » reste un lien (action secondaire)',
    !!doc().querySelector('a[href="/catalogue"]'));
  check('la feuille /minishop.css est liée par le gabarit',
    !!doc().querySelector('link[rel="stylesheet"][href="/minishop.css"]'));
  check("la CSP autorise style-src 'self'",
    (nav.page.enTetes.get('content-security-policy') ?? '').includes("style-src 'self'"),
    nav.page.enTetes.get('content-security-policy') ?? 'absente');

  const resCss = await fetch(`${base}/minishop.css`, { headers: nav.enTetes() });
  const css = await resCss.text();
  check('la feuille est servie (200) et définit .bouton-principal',
    resCss.status === 200 && css.includes('.bouton-principal'), `HTTP ${resCss.status}`);
  check('style du bouton principal : fond foncé, texte blanc, pas de soulignement',
    css.includes('background-color: #9a3412') && css.includes('color: #ffffff') && css.includes('text-decoration: none'));

  // ---------------------------------------------------------- 4. clic anonyme → connexion
  console.log('\n== 4 · Clic sur le CTA sans connexion : page de connexion ==');
  await nav.cliquerBouton(cta);
  check('redirigé vers /connexion', new URL(nav.page.url).pathname === '/connexion', nav.page.url);
  check('le formulaire client est affiché', !!doc().querySelector('form[action="/connexion"]'));

  // ---------------------------------------------------------- 5. connexion puis validation
  console.log('\n== 5 · Connexion par l\'UI, retour panier, clic CTA : page de validation ==');
  doc().querySelector('#email').value = 'alice@example.com';
  doc().querySelector('#mot_de_passe').value = 'Demo2026!';
  await nav.cliquerBouton(bouton(doc().querySelector('form[action="/connexion"]'), 'Se connecter'));
  check('connectée (plus de formulaire de connexion)', !doc().querySelector('form[action="/connexion"]'));

  await nav.aller('/panier');
  const cta2 = doc().querySelector('.bouton-principal');
  check('le panier a survécu à la connexion, CTA toujours un bouton',
    !!cta2 && cta2.tagName === 'BUTTON' && nav.texte().includes('1 article(s)'));
  await nav.cliquerBouton(cta2);
  check('arrivée sur /commande/valider', new URL(nav.page.url).pathname === '/commande/valider', nav.page.url);
  check('titre « Valider ma commande »', doc().querySelector('h1')?.textContent.trim() === 'Valider ma commande');
  check('le bouton final « Confirmer et payer (simulé) » est présent',
    [...doc().querySelectorAll('button')].some((b) => b.textContent.includes('Confirmer et payer')));

  console.log(`\n${'='.repeat(64)}`);
  if (échecs > 0) {
    console.log(`E2E UI panier : ${échecs}/${tests} vérification(s) en échec ❌`);
    process.exitCode = 1;
  } else {
    console.log(`E2E UI panier : TOUT EST VERT ✅ (${tests} vérifications, clics réels sur l'UI)`);
  }
} catch (e) {
  console.error('\nE2E UI panier : ERREUR fatale —', e);
  process.exitCode = 1;
} finally {
  if (serveurLocal) serveurLocal.kill('SIGTERM');
}
