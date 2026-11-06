// MiniShop — E2E UI « Mon compte (375 px) : les titres des cadres
// chevauchent la bordure » (EF-CLI-01/02).
//
// Signalement : sur « Mon compte » à 375 px, les titres des deux
// cadres (« État civil et coordonnées (l'email reste unique) »,
// « L'ancien mot de passe est exigé ; le nouveau est re-haché »)
// chevauchaient la bordure du cadre. Cause vérifiée sur la page
// servie : deux <fieldset> nus, légendes longues posées telles
// quelles sur la bordure, et aucune règle fieldset/legend
// applicable dans la feuille servie.
//
// Vérifié ici en vrai parcours navigateur (DOM + fetch, cookies
// faits main) : connexion du compte de démonstration, page
// « Mon compte » servie — titres courts, précisions en aide dans
// les cadres, formulaires ciblés — et la feuille servie (légende
// bornée et aérée, passage à la ligne autorisé, cadre qui se
// replie à la largeur de l'écran).
//
// Local :  démarre le serveur PHP-WASM sur le port 8142.
// CI :     UI_BASE_URL pointe sur `php -S` (PHP natif).
// Usage :  node scripts/ui-e2e-compte.mjs
//          npm run test:ui-compte

import { spawn, spawnSync } from 'node:child_process';
import { JSDOM } from 'jsdom';

const HOTE = process.env.UI_HOTE ?? '127.0.0.1';
const PORT = 8142;
const BASE_EXTERNE = process.env.UI_BASE_URL;
const base = BASE_EXTERNE ?? `http://${HOTE}:${PORT}`;

let serveur = null;
let echecs = 0;
let total = 0;
function verifier(libelle, condition, precision = '') {
  ++total;
  if (condition) {
    console.log(`  OK  ${libelle}`);
  } else {
    ++echecs;
    console.log(`  ECHEC  ${libelle}${precision ? ` -- ${precision}` : ''}`);
  }
}

/** Navigateur minimal : cookies faits main (jsdom rejette Domain=127.0.0.1). */
class NavigateurUI {
  constructor() {
    this.cookies = {};
    this.page = null;
    this.soumission = null;
  }

  get dom() { return this.page.dom; }
  get doc() { return this.page.dom.window.document; }
  get url() { return this.page.url; }

  enTetes(extra = {}) {
    const cookie = Object.entries(this.cookies).map(([k, v]) => `${k}=${v}`).join('; ');
    return cookie ? { cookie, ...extra } : { ...extra };
  }

  memorise(res) {
    for (const sc of res.headers.getSetCookie()) {
      const m = /^([^=]+)=([^;]*)/.exec(sc);
      if (m) this.cookies[m[1]] = m[2];
    }
  }

  posePage(html, url, statut) {
    const dom = new JSDOM(html, { url });
    this.page = { dom, url, statut };
    dom.window.document.addEventListener('submit', (evenement) => {
      evenement.preventDefault();
      this.soumission = { formulaire: evenement.target, donnees: new dom.window.FormData(evenement.target) };
    }, true);
    return this.page;
  }

  async aller(chemin) {
    let url = new URL(chemin, base).href;
    for (let i = 0; i < 6; ++i) {
      const res = await fetch(url, { headers: this.enTetes(), redirect: 'manual' });
      this.memorise(res);
      const html = await res.text();
      if (res.status >= 300 && res.status < 400 && res.headers.get('location')) {
        url = new URL(res.headers.get('location'), url).href;
        continue;
      }
      return this.posePage(html, url, res.status);
    }
    throw new Error(`trop de redirections vers ${chemin}`);
  }

  async cliquerBouton(bouton) {
    if (!bouton) throw new Error('bouton introuvable');
    this.soumission = null;
    bouton.click();
    if (!this.soumission) {
      const formulaire = bouton.closest('form');
      if (!formulaire) throw new Error('bouton hors formulaire');
      formulaire.requestSubmit(bouton);
    }
    if (!this.soumission) throw new Error('aucune soumission capturee');
    const { formulaire, donnees } = this.soumission;
    const url = new URL(formulaire.getAttribute('action'), this.url).href;
    const corps = new URLSearchParams();
    for (const [k, v] of donnees.entries()) corps.append(k, String(v));
    const res = await fetch(url, {
      method: 'POST',
      headers: this.enTetes({ 'content-type': 'application/x-www-form-urlencoded' }),
      body: corps.toString(),
      redirect: 'manual',
    });
    this.memorise(res);
    if (res.status >= 300 && res.status < 400 && res.headers.get('location')) {
      await res.arrayBuffer();
      return this.aller(new URL(res.headers.get('location'), url).href);
    }
    return this.posePage(await res.text(), url, res.status);
  }

  remplir(selecteur, valeur) {
    const champ = this.doc.querySelector(selecteur);
    if (!champ) throw new Error(`champ introuvable : ${selecteur}`);
    champ.value = valeur;
  }
}

async function demarrer() {
  spawnSync('node', ['scripts/wasm_run.mjs', 'scripts/seed.php'], { stdio: 'inherit' });
  if (!BASE_EXTERNE) {
    serveur = spawn('node', ['scripts/wasm-serve.mjs'], {
      env: { ...process.env, PORT: String(PORT) }, stdio: 'ignore',
    });
    for (let i = 0; i < 30; ++i) {
      try {
        const res = await fetch(base + '/connexion');
        if (res.ok || res.status === 302) return;
      } catch { /* pas encore pret */ }
      await new Promise((r) => setTimeout(r, 500));
    }
    throw new Error('le serveur local ne repond pas');
  }
}

async function principal() {
  await demarrer();

  console.log('\n== Connexion du compte de démonstration, puis « Mon compte » ==');
  const client = new NavigateurUI();
  await client.aller('/connexion');
  client.remplir('#email', 'alice@example.com');
  client.remplir('#mot_de_passe', 'Demo2026!');
  await client.cliquerBouton(client.doc.querySelector('form[action="/connexion"] button'));
  await client.aller('/compte');
  verifier('la page « Mon compte » est servie au client connecté',
    client.doc.querySelector('h1')?.textContent.trim() === 'Mon compte', client.url);

  console.log('\n== Les titres des cadres restent dans le cadre ==');
  const legendes = [...client.doc.querySelectorAll('legend')].map((l) => l.textContent.trim());
  verifier('les deux titres sont courts, sans parenthèse ni code technique',
    legendes.join('|') === 'État civil et coordonnées|Mot de passe'
      && legendes.every((l) => l.length <= 30),
    legendes.join(' | '));
  const aides = [...client.doc.querySelectorAll('fieldset .aide')].map((p) => p.textContent.trim());
  verifier('les précisions sont des aides DANS les cadres (email unique, ancien mot de passe exigé)',
    aides.some((a) => a.includes('email reste unique'))
      && aides.some((a) => a.includes('ancien mot de passe est exigé') && a.includes('chiffré')),
    aides.join(' | '));
  verifier('plus de jargon ni de code technique dans les titres et aides',
    !/re-haché|RB-\d/.test(client.doc.body.textContent));
  verifier('les deux formulaires portent la classe formulaire-compte',
    client.doc.querySelectorAll('form.formulaire-compte').length === 2
      && client.doc.querySelectorAll('form.formulaire-compte fieldset').length === 2);
  verifier('non-régression : les champs du compte sont pré-remplis et étoilés',
    client.doc.querySelector('#email')?.value === 'alice@example.com'
      && client.doc.querySelector('label[for="nom"]')?.textContent.trim() === 'Nom *');

  console.log('\n== La feuille servie garde les titres dans le cadre ==');
  const css = await (await fetch(base + '/minishop.css')).text();
  const bloc = (selecteur) => {
    const i = css.indexOf(selecteur + ' {');
    return i === -1 ? '' : css.slice(i, css.indexOf('}', i));
  };
  verifier('CSS servie : légende aérée (padding) et bornée à la largeur du cadre (max-width)',
    bloc('.formulaire-compte legend').includes('padding: 0 0.5rem')
      && bloc('.formulaire-compte legend').includes('max-width: 100%'));
  verifier('CSS servie : le titre peut passer à la ligne (white-space normal, pas de ligne forcée)',
    bloc('.formulaire-compte legend').includes('white-space: normal'));
  verifier('CSS servie : le cadre se replie à la largeur de l\u2019écran (min-width 0)',
    bloc('.formulaire-compte fieldset').includes('min-width: 0'));

  console.log(`\nTOTAL UI COMPTE : ${total - echecs}/${total} OK`);
  if (echecs > 0) process.exitCode = 1;
}

principal()
  .catch((erreur) => { console.error(erreur); process.exitCode = 1; })
  .finally(() => {
    if (serveur) serveur.kill();
    if (!BASE_EXTERNE) spawnSync('git', ['checkout', '--', 'data']);
  });
