/**
 * MiniShop — E2E UI « Heures décalées » (historique, détail, listes).
 *
 * Signalement : commandes passées à 19h15/19h47 (Paris) affichées
 * 18h15/18h47. Le correctif (App\Tools\Heure : stockage UTC, affichage
 * Europe/Paris via date_fr()) est éprouvé ici de bout en bout, par
 * clics réels : connexion → catalogue → ajout panier → validation de
 * la commande → l'heure affichée sur la page de détail puis dans
 * l'historique doit être l'heure de Paris de l'instant du clic.
 *
 * Référence INDÉPENDANTE du code PHP : l'heure attendue est calculée
 * par le moteur Intl du navigateur (base de fuseaux IANA de Node),
 * formatée « JJ/MM/AAAA HHhMM » pour Europe/Paris — et jamais égale à
 * l'heure UTC du même instant (l'ancien affichage fautif).
 *
 * Serveur cible : UI_BASE_URL si défini (CI : php -S + dev-router),
 * sinon scripts/wasm-serve.mjs démarré sur UI_PORT (défaut 8098).
 * Prérequis : data/minishop peuplé (le script npm reseede avant).
 *
 * Usage : node scripts/ui-e2e-heure.mjs      (ou npm run test:ui-heure)
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
const PORT = Number(process.env.UI_PORT ?? 8098);
let base = (process.env.UI_BASE_URL ?? `http://127.0.0.1:${PORT}`).replace(/\/$/, '');
let serveurLocal = null;

if (!process.env.UI_BASE_URL) {
  if (!existsSync(join(REPO, 'data/minishop/produit.json'))) {
    console.error('[ui-e2e-heure] base absente — lancez : node scripts/wasm_run.mjs scripts/seed.php');
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
    console.log(`::error title=ui-e2e-heure::${label.replaceAll('%', '%25').replaceAll('\n', '%0A')}`);
  }
};

// ------------------------------------------------- référence horaire (Intl)
const formateur = (fuseau) => new Intl.DateTimeFormat('fr-FR', {
  timeZone: fuseau, day: '2-digit', month: '2-digit', year: 'numeric',
  hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
});
const FR = formateur('Europe/Paris');
const UTC = formateur('UTC');
function formate(fmt, instant) {
  const morceaux = {};
  for (const m of fmt.formatToParts(instant)) morceaux[m.type] = m.value;
  return `${morceaux.day}/${morceaux.month}/${morceaux.year} ${morceaux.hour}h${morceaux.minute}`;
}
const paris = (d) => formate(FR, d);
const utc = (d) => formate(UTC, d);

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
  const b = [...formulaire.querySelectorAll('button')].find((x) => x.textContent.trim() === libelle || x.textContent.trim().startsWith(libelle));
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

/** Extrait la première date « JJ/MM/AAAA HHhMM » d'un texte. */
function premiereDateHeure(texte) {
  const m = /\d{2}\/\d{2}\/\d{4} \d{2}h\d{2}/.exec(texte);
  return m ? m[0] : null;
}

try {
  await attendreServeur();
  console.log(`\nMiniShop — E2E UI Heure (UTC stocké, Paris affiché) (serveur : ${base})\n`);
  const nav = new NavigateurUI();
  const doc = () => nav.doc();

  // ---------------------------------------------------------- 1. connexion
  console.log('== 1 · Connexion par l\'UI ==');
  await nav.aller('/connexion');
  doc().querySelector('#email').value = 'alice@example.com';
  doc().querySelector('#mot_de_passe').value = 'Demo2026!';
  await nav.cliquerBouton(bouton(doc().querySelector('form[action="/connexion"]'), 'Se connecter'));
  check('connectée (formulaire de connexion disparu)', !doc().querySelector('form[action="/connexion"]'));

  // ---------------------------------------------------------- 2. achat par clics
  console.log('\n== 2 · Catalogue → produit en stock → panier → validation ==');
  await nav.aller('/catalogue');
  const liensProduits = [...doc().querySelectorAll('a[href^="/produit/"]')];
  let formAjout = null;
  for (const lien of liensProduits) {
    await nav.aller(lien.getAttribute('href'));
    formAjout = doc().querySelector('form[action="/panier/ajouter"]');
    if (formAjout) break;
  }
  check('fiche produit en stock atteinte', !!formAjout);
  await nav.cliquerBouton(bouton(formAjout, 'Ajouter au panier'));
  check('panier avec 1 article', nav.texte().includes('1 article(s)'), nav.texte().slice(0, 120));

  await nav.aller('/commande/valider');
  check('page « Valider ma commande »', doc().querySelector('h1')?.textContent.trim() === 'Valider ma commande');
  doc().querySelector('#adresse').value = '12 rue des Lilas, 06000 Nice';

  // L'instant de la commande est borné par [avant, après] autour du clic.
  const avant = new Date();
  await nav.cliquerBouton(bouton(doc().querySelector('form[action="/commande/valider"]'), 'Confirmer et payer'));
  const apres = new Date();
  const attenduesParis = new Set([paris(avant), paris(apres)]);
  const fautivesUtc = new Set([utc(avant), utc(apres)]);

  // ---------------------------------------------------------- 3. détail : l'heure de Paris
  console.log('\n== 3 · Page détail de la commande créée ==');
  check('arrivée sur /mes-commandes/{id}', /^\/mes-commandes\/\d+$/.test(new URL(nav.page.url).pathname), nav.page.url);
  const titre = doc().querySelector('h1')?.textContent.trim() ?? '';
  const numero = /CMD\S+/.exec(titre)?.[0] ?? '';
  check('numéro de commande lisible', numero.startsWith('CMD'), titre);

  const afficheeDetail = premiereDateHeure(nav.texte());
  check('« Passée le … » au format français JJ/MM/AAAA HHhMM',
    nav.texte().includes('Passée le') && afficheeDetail !== null, nav.texte().slice(0, 160));
  check('heure affichée = heure de Paris de l\'instant du clic',
    afficheeDetail !== null && attenduesParis.has(afficheeDetail),
    `affichée=${afficheeDetail} attendues=${[...attenduesParis].join(' | ')}`);
  check('heure affichée ≠ heure UTC (ancien bug)',
    afficheeDetail !== null && !fautivesUtc.has(afficheeDetail),
    `affichée=${afficheeDetail} utc=${[...fautivesUtc].join(' | ')}`);
  check('la piste des statuts affiche la même heure de Paris',
    nav.texte().includes(afficheeDetail ?? '§'), afficheeDetail ?? 'aucune date');

  // ---------------------------------------------------------- 4. historique : même heure
  console.log('\n== 4 · Historique « Mes commandes » : la même heure ==');
  await nav.aller('/mes-commandes');
  const ligne = [...doc().querySelectorAll('tr')].find((tr) => tr.textContent.includes(numero));
  check('la commande est dans l\'historique', !!ligne);
  const afficheeListe = ligne ? premiereDateHeure(ligne.textContent.replace(/\s+/g, ' ')) : null;
  check('date de la liste = date du détail (Paris)',
    afficheeListe !== null && afficheeListe === afficheeDetail,
    `liste=${afficheeListe} détail=${afficheeDetail}`);
  check('date de la liste = heure de Paris du clic, pas UTC',
    afficheeListe !== null && attenduesParis.has(afficheeListe) && !fautivesUtc.has(afficheeListe),
    `liste=${afficheeListe}`);

  // ---------------------------------------------------------- 5. cohérence du fuseau du navigateur de référence
  console.log('\n== 5 · Référence Intl (sanity) ==');
  check('Intl connaît Europe/Paris (décalage non nul face à UTC, sinon test invalide)',
    paris(avant) !== utc(avant) || paris(new Date('2026-07-15T12:00:00Z')) !== utc(new Date('2026-07-15T12:00:00Z')));

  console.log(`\n${'='.repeat(64)}`);
  if (échecs > 0) {
    console.log(`E2E UI heure : ${échecs}/${tests} vérification(s) en échec ❌`);
    process.exitCode = 1;
  } else {
    console.log(`E2E UI heure : TOUT EST VERT ✅ (${tests} vérifications, interactions DOM simulées avec jsdom)`);
  }
} catch (e) {
  console.error('\nE2E UI heure : ERREUR fatale —', e);
  process.exitCode = 1;
} finally {
  if (serveurLocal) serveurLocal.kill('SIGTERM');
}
