/**
 * MiniShop — E2E UI « Admin > Stocks : le mode par défaut écrase le
 * stock » (UC-12, EF-ADM-04).
 *
 * Signalement : le mode par défaut était « Fixer à » avec la valeur 0.
 * En voulant ajouter 3 unités à un stock de 5, l'admin clique
 * « Appliquer » sans changer le mode : le stock passe à 3 au lieu de 8.
 *
 * Parcours prouvés ici, par clics réels sur les pages (jsdom) :
 *   1. le select propose « Ajouter / retirer » (DELTA), sélectionné
 *      par défaut, et « Varier de » a disparu ;
 *   2. scénario du signalement : quantité 3, motif saisi, clic sur
 *      « Appliquer » SANS toucher au mode → le stock augmente de 3 ;
 *   3. « Fixer à » reste possible en choix explicite (le stock est
 *      alors remplacé par la valeur saisie) — on s'en sert pour
 *      remettre le stock à son état initial en fin de parcours.
 *
 * Serveur cible : UI_BASE_URL si défini (CI : php -S + dev-router),
 * sinon scripts/wasm-serve.mjs démarré sur UI_PORT (défaut 8102).
 * Prérequis : data/minishop peuplé (le script npm reseede avant).
 *
 * Usage : node scripts/ui-e2e-stocks.mjs      (ou npm run test:ui-stocks)
 */

import { JSDOM } from 'jsdom';
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const REPO = join(import.meta.dirname, '..');
const PORT = Number(process.env.UI_PORT ?? 8102);
let base = (process.env.UI_BASE_URL ?? `http://127.0.0.1:${PORT}`).replace(/\/$/, '');
let serveurLocal = null;

if (!process.env.UI_BASE_URL) {
  if (!existsSync(join(REPO, 'data/minishop/produit.json'))) {
    console.error('[ui-e2e-stocks] base absente — lancez : node scripts/wasm_run.mjs scripts/seed.php');
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
    console.log(`::error title=ui-e2e-stocks::${label.replaceAll('%', '%25').replaceAll('\n', '%0A')}`);
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

/**
 * Ligne du tableau des stocks : par référence si donnée, sinon la
 * première ligne avec un stock ≥ 1 — il faut un stock réel pour que
 * « +3 » (DELTA) et « fixer à 3 » (ancien SET) donnent des résultats
 * différents, sinon le scénario ne discrimine rien.
 */
function ligneStock(doc, reference = null) {
  for (const tr of doc.querySelectorAll('table tbody tr')) {
    const cellules = tr.querySelectorAll('td');
    const form = tr.querySelector('form[action="/admin/stocks"]');
    if (!form) continue;
    const ligne = {
      tr,
      reference: cellules[0]?.textContent.trim() ?? '',
      stock: Number.parseInt(cellules[2]?.textContent.trim() ?? '', 10),
      form,
      select: form.querySelector('select[name="mode"]'),
      quantite: form.querySelector('input[name="quantite"]'),
      motif: form.querySelector('input[name="motif"]'),
    };
    if (reference !== null && ligne.reference === reference) return ligne;
    if (reference === null && ligne.stock >= 1) return ligne;
  }
  return null;
}

try {
  await attendreServeur();
  console.log(`\nMiniShop — E2E UI Stocks : « Ajouter / retirer » par défaut (serveur : ${base})\n`);
  const nav = new NavigateurUI();
  const doc = () => nav.doc();

  // ---------------------------------------------------------- 1. connexion admin
  console.log('== 1 · Connexion administrateur par l\'UI ==');
  await nav.aller('/admin/stocks');
  check('redirigé vers le login admin', new URL(nav.page.url).pathname === '/admin/connexion', nav.page.url);
  doc().querySelector('#email').value = 'admin@minishop.fr';
  doc().querySelector('#mot_de_passe').value = 'Admin2026!';
  await nav.cliquerBouton(bouton(doc().querySelector('form[action="/admin/connexion"]'), 'Accéder au back-office'));
  check('admin connecté', nav.texte().includes('Tableau de bord'));

  // ---------------------------------------------------------- 2. le mode par défaut
  console.log('\n== 2 · Admin > Stocks : le mode proposé par défaut ==');
  await nav.aller('/admin/stocks');
  check('titre « Gestion des stocks »', doc().querySelector('h1')?.textContent.trim() === 'Gestion des stocks');
  const avant = ligneStock(doc());
  check('une ligne de stock avec formulaire est affichée', avant !== null && avant.form !== null);
  check(
    'le mode sélectionné par défaut est « Ajouter / retirer » (DELTA)',
    avant.select?.value === 'DELTA',
    `valeur=${avant.select?.value}`
  );
  const libelles = [...avant.select.querySelectorAll('option')].map((o) => o.textContent.trim());
  check('les options sont « Ajouter / retirer » puis « Fixer à »',
    libelles.length === 2 && libelles[0] === 'Ajouter / retirer' && libelles[1] === 'Fixer à',
    libelles.join(' | '));
  check('l\'ancien libellé « Varier de » a disparu de la page', !nav.texte().includes('Varier de'));
  const stockInitial = avant.stock;
  check(`stock initial lu (${avant.reference})`, Number.isInteger(stockInitial) && stockInitial >= 0,
    `${avant.reference} stock=${stockInitial}`);

  // ---------------------------------------------------------- 3. le scénario signalé
  console.log('\n== 3 · Scénario signalé : +3 SANS toucher au mode → stock + 3 ==');
  avant.quantite.value = '3';
  avant.motif.value = 'réception fournisseur (e2e)';
  // Volontairement : aucun geste sur le select — c'est le geste qui perdait le stock.
  await nav.cliquerBouton(bouton(avant.form, 'Appliquer'));
  check(`confirmation affichée « Stock mis à jour : ${stockInitial + 3} unité(s). »`,
    nav.texte().includes(`Stock mis à jour : ${stockInitial + 3} unité(s).`), nav.texte().slice(0, 200));
  const apresAjout = ligneStock(doc(), avant.reference);
  check('la ligne est toujours le même produit', apresAjout.reference === avant.reference);
  check(`le stock est passé de ${stockInitial} à ${stockInitial + 3} (et non à 3)`,
    apresAjout.stock === stockInitial + 3, `stock=${apresAjout.stock}`);

  // ---------------------------------------------------------- 4. « Fixer à » explicite + remise en état
  console.log('\n== 4 · « Fixer à » en choix explicite, puis remise du stock initial ==');
  apresAjout.select.value = 'SET';
  apresAjout.quantite.value = String(stockInitial);
  apresAjout.motif.value = 'remise à l\'état initial (e2e)';
  await nav.cliquerBouton(bouton(apresAjout.form, 'Appliquer'));
  const restaure = ligneStock(doc(), avant.reference);
  check(`le stock est revenu à sa valeur initiale ${stockInitial} (SET absolu respecté)`,
    restaure.stock === stockInitial, `stock=${restaure.stock}`);
  check('après rechargement, le mode par défaut est toujours « Ajouter / retirer »',
    restaure.select?.value === 'DELTA', `valeur=${restaure.select?.value}`);

  console.log(`\n${'='.repeat(64)}`);
  if (échecs > 0) {
    console.log(`E2E UI stocks : ${échecs}/${tests} vérification(s) en échec ❌`);
    process.exitCode = 1;
  } else {
    console.log(`E2E UI stocks : TOUT EST VERT ✅ (${tests} vérifications, clics réels sur l'UI)`);
  }
} catch (e) {
  console.error('\nE2E UI stocks : ERREUR fatale —', e);
  process.exitCode = 1;
} finally {
  if (serveurLocal) serveurLocal.kill('SIGTERM');
}
