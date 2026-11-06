/**
 * MiniShop — E2E UI « Admin > Commandes : colonne Statut coupée »
 * (UC-13, EF-ADM-06).
 *
 * Signalement : dans Admin > Commandes, le tableau défilait
 * horizontalement et la colonne Statut — la plus utile pour piloter
 * les commandes — était coupée à droite. Cause : aucune règle CSS
 * dédiée au tableau des commandes, qui s'étalait selon son contenu.
 *
 * Parcours prouvé ici, par clics réels sur les pages (jsdom) :
 *   connexion admin → /admin/commandes : le tableau annonce sa mise
 *   en page tenue (classe, colgroup des six colonnes), chaque ligne
 *   affiche son statut en entier dans sa cellule dédiée et son
 *   montant aligné ; la feuille servie contient les règles qui
 *   garantissent cette tenue (largeurs totalisant 100 %, statut
 *   jamais coupé) ; le filtre par numéro et le lien « Ouvrir → »
 *   vers le détail fonctionnent toujours.
 *
 * jsdom n'a pas de moteur de mise en page : la tenue visuelle est
 * vérifiée par ce qui la garantit — structure et classes dans le
 * DOM rendu, et règles CSS effectivement servies.
 *
 * Serveur cible : UI_BASE_URL si défini (CI : php -S + dev-router),
 * sinon scripts/wasm-serve.mjs démarré sur UI_PORT (défaut 8112).
 * Prérequis : data/minishop peuplé (le script npm reseede avant).
 *
 * Usage : node scripts/ui-e2e-commandes-tableau.mjs      (ou npm run test:ui-commandes-tableau)
 */

import { JSDOM } from 'jsdom';
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const REPO = join(import.meta.dirname, '..');
const PORT = Number(process.env.UI_PORT ?? 8112);
let base = (process.env.UI_BASE_URL ?? `http://127.0.0.1:${PORT}`).replace(/\/$/, '');
let serveurLocal = null;

if (!process.env.UI_BASE_URL) {
  if (!existsSync(join(REPO, 'data/minishop/produit.json'))) {
    console.error('[ui-e2e-commandes-tableau] base absente — lancez : node scripts/wasm_run.mjs scripts/seed.php');
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
    console.log(`::error title=ui-e2e-commandes-tableau::${label.replaceAll('%', '%25').replaceAll('\n', '%0A')}`);
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

const STATUTS_LIBELLES = ['Brouillon', 'En préparation', 'Payée', 'Expédiée', 'Livrée', 'Annulée'];

try {
  await attendreServeur();
  console.log(`\nMiniShop — E2E UI Admin > Commandes, colonne Statut (serveur : ${base})\n`);
  const nav = new NavigateurUI();
  const doc = () => nav.doc();

  // ---------------------------------------------------------- 1. une commande créée par clics (le seed d'usine n'en contient aucune)
  console.log('== 1 · Une cliente crée une commande par clics (donnée du tableau) ==');
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
  check('fiche produit en stock atteinte', !!formAjout);
  await nav.cliquerBouton(bouton(formAjout, 'Ajouter au panier'));
  await nav.aller('/commande/valider');
  doc().querySelector('#adresse').value = '12 rue de France, 06000 Nice';
  await nav.cliquerBouton(bouton(doc().querySelector('form[action="/commande/valider"]'), 'Confirmer et payer (simulé)'));
  check('commande créée (statut Payée)', /\/mes-commandes\/\d+$/.test(new URL(nav.page.url).pathname) && nav.texte().includes('Statut : Payée'),
    nav.page.url);
  await nav.aller('/deconnexion');

  // ---------------------------------------------------------- 2. connexion admin
  console.log('\n== 2 · Connexion administrateur par l\'UI ==');
  await nav.aller('/admin/commandes');
  check('redirigé vers le login admin', new URL(nav.page.url).pathname === '/admin/connexion', nav.page.url);
  doc().querySelector('#email').value = 'admin@minishop.fr';
  doc().querySelector('#mot_de_passe').value = 'Admin2026!';
  await nav.cliquerBouton(bouton(doc().querySelector('form[action="/admin/connexion"]'), 'Accéder au back-office'));
  check('admin connecté', nav.texte().includes('Tableau de bord'));

  // ---------------------------------------------------------- 2. le tableau à l'écran
  console.log('\n== 3 · Admin > Commandes : les six colonnes, Statut en entier ==');
  await nav.aller('/admin/commandes');
  check('titre « Commandes »', doc().querySelector('h1')?.textContent.trim() === 'Commandes');
  const tableau = doc().querySelector('table.tableau-commandes');
  check('le tableau porte la classe tableau-commandes (mise en page dédiée)', !!tableau);

  const cols = [...tableau.querySelectorAll('colgroup col')].map((c) => c.className);
  check('le colgroup fixe les six colonnes dans l\'ordre, Statut en quatrième',
    cols.join('|') === 'col-numero|col-date|col-client|col-statut|col-montant|col-detail',
    cols.join('|'));
  const entetes = [...tableau.querySelectorAll('thead th')];
  check('six en-têtes : Numéro, Date, Client, Statut, Montant TTC, Détail',
    entetes.map((th) => th.textContent.trim()).join('|') === 'Numéro|Date|Client|Statut|Montant TTC|Détail',
    entetes.map((th) => th.textContent.trim()).join('|'));
  check('seul l\'en-tête Montant TTC porte la classe d\'alignement à droite',
    entetes.filter((th) => th.classList.contains('montant')).map((th) => th.textContent.trim()).join('|') === 'Montant TTC');

  const lignes = [...tableau.querySelectorAll('tbody tr')];
  check('au moins une commande est listée', lignes.length >= 1, `${lignes.length} ligne(s)`);
  check('chaque ligne a son statut en entier, dans une cellule statut',
    lignes.every((tr) => {
      const td = tr.querySelector('td.statut');
      return td !== null && STATUTS_LIBELLES.includes(td.textContent.trim());
    }),
    lignes.map((tr) => tr.querySelector('td.statut')?.textContent.trim() ?? '(absent)').join(' | '));
  check('chaque ligne a son montant en cellule montant (en euros)',
    lignes.every((tr) => {
      const td = tr.querySelector('td.montant');
      return td !== null && /€$/.test(td.textContent.trim());
    }));
  check('chaque ligne garde son lien « Ouvrir → » vers le détail',
    lignes.every((tr) => /^\/admin\/commandes\/\d+$/.test(tr.querySelector('td:last-child a')?.getAttribute('href') ?? '')));

  // ---------------------------------------------------------- 3. la feuille qui garantit la tenue
  console.log('\n== 4 · Feuille servie : les règles de tenue sont là ==');
  const resCss = await fetch(`${base}/minishop.css`, { headers: nav.enTetes() });
  const css = await resCss.text();
  check('la feuille est servie (200) et définit .tableau-commandes', resCss.status === 200 && css.includes('.tableau-commandes'),
    `HTTP ${resCss.status}`);
  check('largeur 100 % et répartition fixe des colonnes (table-layout)',
    css.includes('.tableau-commandes') && css.includes('width: 100%') && css.includes('table-layout: fixed'));
  const largeurs = ['col-numero', 'col-date', 'col-client', 'col-statut', 'col-montant', 'col-detail']
    .map((nom) => {
      const m = new RegExp(`\\.tableau-commandes \\.${nom}\\s*\\{[^}]*width:\\s*(\\d+)%`).exec(css);
      return m ? Number.parseInt(m[1], 10) : null;
    });
  check('les six largeurs de colonnes totalisent 100 % (tout tient dans la page)',
    largeurs.every((l) => l !== null) && largeurs.reduce((a, b) => a + b, 0) === 100,
    largeurs.join(','));
  check('la colonne Statut a sa largeur propre et son libellé ne se coupe pas (nowrap)',
    /\.tableau-commandes \.statut\s*\{[^}]*white-space:\s*nowrap/.test(css));

  // ---------------------------------------------------------- 4. le pilotage fonctionne toujours
  console.log('\n== 5 · Filtre par numéro et lien détail : le pilotage est intact ==');
  const numero = lignes[0].querySelector('td')?.textContent.trim() ?? '';
  const formFiltres = doc().querySelector('form[action="/admin/commandes"]');
  formFiltres.querySelector('#client').value = numero;
  await nav.cliquerBouton(bouton(formFiltres, 'Filtrer'));
  check('toujours sur /admin/commandes après le filtre', new URL(nav.page.url).pathname === '/admin/commandes', nav.page.url);
  const tableauFiltre = doc().querySelector('table.tableau-commandes');
  const lignesFiltrees = [...tableauFiltre.querySelectorAll('tbody tr')];
  check(`le filtre par numéro ne garde que la commande ${numero}`,
    lignesFiltrees.length === 1 && lignesFiltrees[0].querySelector('td')?.textContent.trim() === numero,
    `${lignesFiltrees.length} ligne(s)`);
  check('la ligne filtrée affiche toujours son statut en entier',
    STATUTS_LIBELLES.includes(lignesFiltrees[0]?.querySelector('td.statut')?.textContent.trim() ?? ''));
  const hrefDetail = lignesFiltrees[0].querySelector('td:last-child a')?.getAttribute('href') ?? '';
  await nav.aller(hrefDetail);
  check('le lien « Ouvrir → » mène au détail de la commande', nav.texte().includes(numero),
    nav.page.url);

  console.log(`\n${'='.repeat(64)}`);
  if (échecs > 0) {
    console.log(`E2E UI commandes (tableau) : ${échecs}/${tests} vérification(s) en échec ❌`);
    process.exitCode = 1;
  } else {
    console.log(`E2E UI commandes (tableau) : TOUT EST VERT ✅ (${tests} vérifications, clics réels sur l'UI)`);
  }
} catch (e) {
  console.error('\nE2E UI commandes (tableau) : ERREUR fatale —', e);
  process.exitCode = 1;
} finally {
  if (serveurLocal) serveurLocal.kill('SIGTERM');
}
