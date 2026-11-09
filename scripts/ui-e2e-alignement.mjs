/**
 * MiniShop — E2E UI « Produit/Référence alignées à droite sous des
 * titres à gauche : panier, stocks, alertes » (EF-VIS-03, EF-ADM-04/05).
 *
 * Signalement : sur trois écrans — le panier, Admin > Stocks et les
 * alertes du tableau de bord — les colonnes Produit et Référence
 * (des libellés) apparaissaient alignées à droite sous des titres
 * alignés à gauche : les valeurs ne tombaient pas sous leurs
 * en-têtes. Cause : alignements jamais explicités écran par écran.
 *
 * Parcours prouvé ici, par clics réels sur les pages (jsdom) :
 *   cliente : catalogue → ajout → panier, la colonne Produit (nom +
 *   référence) est en cellule « produit », les montants en cellules
 *   « montant » ; admin : les stocks affichent Réf. et Produit en
 *   cellules alignées comme leurs titres ; un stock passé à zéro
 *   fait apparaître l'alerte correspondante au tableau de bord,
 *   elle aussi alignée ; le stock est remis en état à la fin.
 *   La feuille servie contient la règle écrite pour les trois
 *   tableaux : libellés à gauche, en-têtes et cellules ensemble.
 *
 * jsdom n'a pas de moteur de mise en page : l'alignement visuel est
 * vérifié par ce qui le garantit — classes d'alignement dans le DOM
 * rendu et règles CSS effectivement servies.
 *
 * Serveur cible : UI_BASE_URL si défini (CI : php -S + dev-router),
 * sinon scripts/wasm-serve.mjs démarré sur UI_PORT (défaut 8116).
 * Prérequis : data/minishop peuplé (le script npm reseede avant).
 *
 * Usage : node scripts/ui-e2e-alignement.mjs      (ou npm run test:ui-alignement)
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
const PORT = Number(process.env.UI_PORT ?? 8116);
let base = (process.env.UI_BASE_URL ?? `http://127.0.0.1:${PORT}`).replace(/\/$/, '');
let serveurLocal = null;

if (!process.env.UI_BASE_URL) {
  if (!existsSync(join(REPO, 'data/minishop/produit.json'))) {
    console.error('[ui-e2e-alignement] base absente — lancez : node scripts/wasm_run.mjs scripts/seed.php');
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
    console.log(`::error title=ui-e2e-alignement::${label.replaceAll('%', '%25').replaceAll('\n', '%0A')}`);
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

/** Ligne du tableau des stocks par référence (ou première avec stock ≥ 1). */
function ligneStock(doc, reference = null) {
  for (const tr of doc.querySelectorAll('table.tableau-stocks tbody tr')) {
    const cellules = tr.querySelectorAll('td');
    const form = tr.querySelector('form[action="/admin/stocks"]');
    if (!form) continue;
    const ligne = {
      tr,
      reference: cellules[0]?.textContent.trim() ?? '',
      produit: cellules[1]?.textContent.trim() ?? '',
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
  console.log(`\nMiniShop — E2E UI Alignement Produit/Référence (panier, stocks, alertes) (serveur : ${base})\n`);
  const nav = new NavigateurUI();
  const doc = () => nav.doc();

  // ---------------------------------------------------------- 1. panier (cliente)
  console.log('== 1 · Panier : la colonne Produit sous son titre, les montants à part ==');
  await nav.aller('/catalogue');
  let formAjout = null;
  for (const lien of doc().querySelectorAll('a[href^="/produit/"]')) {
    await nav.aller(lien.getAttribute('href'));
    formAjout = doc().querySelector('form[action="/panier/ajouter"]');
    if (formAjout) break;
  }
  check('fiche produit en stock atteinte', !!formAjout);
  await nav.cliquerBouton(bouton(formAjout, 'Ajouter au panier'));
  check('arrivée sur /panier', new URL(nav.page.url).pathname === '/panier', nav.page.url);
  const tableauPanier = doc().querySelector('table.tableau-panier');
  const enteteProduit = [...tableauPanier.querySelectorAll('thead th')]
    .find((th) => th.textContent.trim() === 'Produit');
  check('panier : l\'en-tête Produit porte la classe d\'alignement des libellés (produit)',
    !!enteteProduit && enteteProduit.classList.contains('produit'));
  const celluleProduit = tableauPanier.querySelector('tbody td.produit');
  check('panier : le produit (nom + « Réf. … — stock : … ») est dans la cellule produit, sous son titre',
    !!celluleProduit && celluleProduit.textContent.includes('Réf.'),
    celluleProduit?.textContent.replace(/\s+/g, ' ').trim() ?? '(absente)');
  check('panier : les montants restent en cellules montant (règle inchangée pour eux)',
    [...tableauPanier.querySelectorAll('tbody td.montant')].length === 2);

  // ---------------------------------------------------------- 2. connexion admin + stocks
  console.log('\n== 2 · Connexion admin, puis Stocks : Réf. et Produit sous leurs titres ==');
  const admin = new NavigateurUI();
  const docA = () => admin.doc();
  await admin.aller('/admin/stocks');
  check('redirigé vers le login admin', new URL(admin.page.url).pathname === '/admin/connexion', admin.page.url);
  docA().querySelector('#email').value = 'admin@minishop.fr';
  docA().querySelector('#mot_de_passe').value = 'Admin2026!';
  await admin.cliquerBouton(bouton(docA().querySelector('form[action="/admin/connexion"]'), 'Accéder au back-office'));
  check('admin connecté', admin.texte().includes('Tableau de bord'));

  await admin.aller('/admin/stocks');
  const tableauStocks = docA().querySelector('table.tableau-stocks');
  check('stocks : le tableau porte la classe tableau-stocks', !!tableauStocks);
  const entetesStocks = [...tableauStocks.querySelectorAll('thead th')];
  check('stocks : les en-têtes Réf. et Produit portent leurs classes d\'alignement',
    entetesStocks[0]?.classList.contains('reference') && entetesStocks[1]?.classList.contains('produit'),
    entetesStocks.map((th) => th.textContent.trim()).join('|'));
  const temoin = ligneStock(docA());
  check('stocks : une ligne témoin avec stock ≥ 1 est affichée', temoin !== null);
  check('stocks : la ligne a sa référence et son produit en cellules alignées comme les titres',
    temoin.tr.querySelector('td.reference')?.textContent.trim() === temoin.reference
      && temoin.tr.querySelector('td.produit')?.textContent.trim() === temoin.produit,
    `${temoin.reference} / ${temoin.produit}`);
  const stockInitial = temoin.stock;

  // ---------------------------------------------------------- 3. alerte au tableau de bord
  console.log('\n== 3 · Stock du témoin passé à zéro : l\'alerte du tableau de bord, elle aussi alignée ==');
  temoin.select.value = 'SET';
  temoin.quantite.value = '0';
  temoin.motif.value = 'alerte provoquée (e2e alignement)';
  await admin.cliquerBouton(bouton(temoin.form, 'Appliquer'));
  check(`confirmation « Stock mis à jour : 0 unité(s). »`, admin.texte().includes('Stock mis à jour : 0 unité(s).'),
    admin.texte().slice(0, 160));
  await admin.aller('/admin');
  const tableauAlertes = docA().querySelector('table.tableau-alertes');
  check('alertes : le tableau des alertes est affiché au tableau de bord', !!tableauAlertes);
  const entetesAlertes = [...tableauAlertes.querySelectorAll('thead th')];
  check('alertes : les en-têtes Référence et Produit portent leurs classes d\'alignement',
    entetesAlertes[0]?.classList.contains('reference') && entetesAlertes[1]?.classList.contains('produit'),
    entetesAlertes.map((th) => th.textContent.trim()).join('|'));
  const ligneAlerte = [...tableauAlertes.querySelectorAll('tbody tr')]
    .find((tr) => tr.querySelector('td.reference')?.textContent.trim() === temoin.reference);
  check(`alertes : le produit témoin ${temoin.reference} est en alerte, référence et produit alignés`,
    !!ligneAlerte && ligneAlerte.querySelector('td.produit')?.textContent.trim() === temoin.produit);

  // ---------------------------------------------------------- 4. la feuille qui garantit l'alignement
  console.log('\n== 4 · Feuille servie : la règle écrite pour les trois tableaux ==');
  const resCss = await fetch(`${base}/css/app.css`, { headers: admin.enTetes() });
  const css = await resCss.text();
  check('la feuille est servie (200) et définit les trois tableaux',
    resCss.status === 200 && css.includes('.tableau-panier') && css.includes('.tableau-stocks') && css.includes('.tableau-alertes'),
    `HTTP ${resCss.status}`);
  check('produit et référence alignés à gauche, montant du panier à droite',
    /\.tableau-panier \.produit,[^}]*\.tableau-stocks \.reference,[^}]*\.tableau-alertes \.produit\s*\{[^}]*text-align:\s*left/s.test(css)
      && /\.tableau-panier \.montant\s*\{[^}]*text-align:\s*right/.test(css));
  check('stocks et alertes sont étiquetés pour les cartes mobiles',
    css.includes('.tableau-defilant--admin tbody td::before') && temoin.tr.querySelector('td.produit')?.dataset.label === 'Produit'
      && ligneAlerte?.querySelector('td.reference')?.dataset.label === 'Référence');


  // ---------------------------------------------------------- 5. remise en état
  console.log('\n== 5 · Remise du stock témoin à son état initial ==');
  await admin.aller('/admin/stocks');
  const aRestaurer = ligneStock(docA(), temoin.reference);
  aRestaurer.select.value = 'SET';
  aRestaurer.quantite.value = String(stockInitial);
  aRestaurer.motif.value = 'remise à l\'état initial (e2e alignement)';
  await admin.cliquerBouton(bouton(aRestaurer.form, 'Appliquer'));
  const restaure = ligneStock(docA(), temoin.reference);
  check(`le stock du témoin est revenu à ${stockInitial}`, restaure.stock === stockInitial, `stock=${restaure.stock}`);

  console.log(`\n${'='.repeat(64)}`);
  if (échecs > 0) {
    console.log(`E2E UI alignement : ${échecs}/${tests} vérification(s) en échec ❌`);
    process.exitCode = 1;
  } else {
    console.log(`E2E UI alignement : TOUT EST VERT ✅ (${tests} vérifications, interactions DOM simulées avec jsdom)`);
  }
} catch (e) {
  console.error('\nE2E UI alignement : ERREUR fatale —', e);
  process.exitCode = 1;
} finally {
  if (serveurLocal) serveurLocal.kill('SIGTERM');
}
