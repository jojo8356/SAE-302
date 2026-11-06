/**
 * MiniShop — E2E UI « Catalogue : recherche et filtres en bas de page »
 * (format mobile) (UC-02/03, EF-VIS-02/03).
 *
 * Signalement : la recherche et les filtres se retrouvaient après les
 * 12 produits et la pagination — sur mobile, il fallait défiler toute
 * la liste avant de pouvoir filtrer.
 *
 * jsdom n'a pas de moteur de mise en page : le « format mobile » est
 * vérifié ici pour ce qu'il signifie structurellement —
 *   1. l'ordre de lecture : le bloc de filtres est AVANT le premier
 *      produit dans le DOM (ce que lit un écran de téléphone, du haut
 *      vers le bas) ;
 *   2. le panneau est REPLIÉ par défaut derrière un bouton
 *      « Filtrer » bien visible : les produits tiennent dans le
 *      premier écran, un tap ouvre les filtres ;
 *   3. dès qu'un filtre est actif, le panneau se rouvre tout seul et
 *      le bouton annonce « Filtrer (actifs) » ;
 *   4. le gabarit déclare le viewport mobile.
 * Le reste est un vrai parcours : taper une recherche, cliquer
 * « Appliquer », recevoir la liste filtrée.
 *
 * Serveur cible : UI_BASE_URL si défini (CI : php -S + dev-router),
 * sinon scripts/wasm-serve.mjs démarré sur UI_PORT (défaut 8106).
 * Prérequis : data/minishop peuplé (le script npm reseede avant).
 *
 * Usage : node scripts/ui-e2e-catalogue.mjs      (ou npm run test:ui-catalogue)
 */

import { JSDOM } from 'jsdom';
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const REPO = join(import.meta.dirname, '..');
const PORT = Number(process.env.UI_PORT ?? 8106);
let base = (process.env.UI_BASE_URL ?? `http://127.0.0.1:${PORT}`).replace(/\/$/, '');
let serveurLocal = null;

if (!process.env.UI_BASE_URL) {
  if (!existsSync(join(REPO, 'data/minishop/produit.json'))) {
    console.error('[ui-e2e-catalogue] base absente — lancez : node scripts/wasm_run.mjs scripts/seed.php');
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
    console.log(`::error title=ui-e2e-catalogue::${label.replaceAll('%', '%25').replaceAll('\n', '%0A')}`);
  }
};

// ---------------------------------------------------------------- navigateur UI
/** Mini-navigateur : redirections suivies comme un navigateur, DOM jsdom cliquable. */
class NavigateurUI {
  constructor() {
    this.page = null;
    this.soumission = null;
  }

  async aller(chemin) {
    let url = new URL(chemin, base).href;
    for (let i = 0; i < 6; ++i) {
      const res = await fetch(url, { redirect: 'manual' });
      const html = await res.text();
      if (res.status >= 300 && res.status < 400 && res.headers.get('location')) {
        url = new URL(res.headers.get('location'), url).href;
        continue;
      }
      const dom = new JSDOM(html, { url });
      this.page = { dom, url, statut: res.status };
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
    const params = new URLSearchParams();
    for (const [cle, valeur] of donnees.entries()) params.append(cle, String(valeur));
    const cible = params.toString() ? `${action}?${params}` : action;
    return this.aller(cible);
  }

  doc() { return this.page.dom.window.document; }
  texte() { return this.doc().body.textContent.replace(/\s+/g, ' ').trim(); }
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
  console.log(`\nMiniShop — E2E UI Catalogue, format mobile (filtres en haut, repliés) (serveur : ${base})\n`);
  const nav = new NavigateurUI();
  const doc = () => nav.doc();

  // ---------------------------------------------------------- 1. ordre de lecture mobile
  console.log('== 1 · Catalogue sur mobile : filtres en haut, repliés, produits visibles ==');
  await nav.aller('/catalogue');
  check('catalogue en 200', nav.page.statut === 200, `HTTP ${nav.page.statut}`);
  const meta = doc().querySelector('meta[name="viewport"]');
  check('le gabarit déclare le viewport mobile', !!meta && (meta.getAttribute('content') ?? '').includes('width=device-width'));

  const panneau = doc().querySelector('details.filtres-catalogue');
  check('le panneau de filtres existe en haut de page', !!panneau);
  const resume = panneau?.querySelector('summary');
  check('le bouton du panneau s\'appelle « Filtrer »', resume?.textContent.trim() === 'Filtrer', resume?.textContent.trim() ?? '');
  check('le panneau est REPLIÉ par défaut (premier écran = produits)',
    panneau !== null && panneau.open === false);

  const premierProduit = doc().querySelector('a[href^="/produit/"]');
  check(
    'ordre de lecture : le panneau est AVANT le premier produit',
    panneau !== null && premierProduit !== null
      && (panneau.compareDocumentPosition(premierProduit) & doc().defaultView.Node.DOCUMENT_POSITION_FOLLOWING) !== 0
  );
  const produits = [...doc().querySelectorAll('a[href^="/produit/"]')];
  check('les produits sont listés dès l\'arrivée (12 par page)', produits.length >= 8, `${produits.length} liens produits`);

  // ---------------------------------------------------------- 2. un tap ouvre les filtres
  console.log('\n== 2 · Tap sur « Filtrer » : le panneau s\'ouvre ==');
  resume.click();
  check('après le tap, le panneau est ouvert', panneau.open === true);
  check('les champs sont accessibles (recherche, catégorie, prix, tri)',
    !!panneau.querySelector('#q') && !!panneau.querySelector('#cat')
      && !!panneau.querySelector('#prix_min') && !!panneau.querySelector('#tri'));

  // ---------------------------------------------------------- 3. recherche réelle par clic
  console.log('\n== 3 · Recherche « CASQ » et clic « Appliquer » ==');
  panneau.querySelector('#q').value = 'CASQ';
  const boutonAppliquer = [...panneau.querySelectorAll('button')].find((b) => b.textContent.trim() === 'Appliquer');
  await nav.cliquerBouton(boutonAppliquer);
  check('URL filtrée (/catalogue?q=CASQ)', new URL(nav.page.url).searchParams.get('q') === 'CASQ', nav.page.url);

  const panneauActif = doc().querySelector('details.filtres-catalogue');
  check('avec un filtre actif, le panneau se rouvre tout seul', panneauActif !== null && panneauActif.open === true);
  check('le bouton annonce « Filtrer (actifs) »',
    (panneauActif?.querySelector('summary')?.textContent.trim() ?? '') === 'Filtrer (actifs)',
    panneauActif?.querySelector('summary')?.textContent.trim() ?? '');
  check('le mot-clé est conservé dans le champ', panneauActif?.querySelector('#q')?.value === 'CASQ');

  const lignes = [...doc().querySelectorAll('main ul li')];
  check('la liste est filtrée (moins de produits qu\'au départ)',
    lignes.length >= 1 && lignes.length < produits.length, `${lignes.length} lignes`);
  check('chaque résultat correspond à la recherche (Réf. CASQ…)',
    lignes.every((li) => li.textContent.includes('CASQ')),
    lignes[0]?.textContent.replace(/\s+/g, ' ').slice(0, 90) ?? '');

  // ---------------------------------------------------------- 4. retour au catalogue complet
  console.log('\n== 4 · Retour au catalogue complet : panneau de nouveau replié ==');
  await nav.aller('/catalogue');
  const panneauNeuf = doc().querySelector('details.filtres-catalogue');
  check('sans filtre, le panneau est refermé', panneauNeuf !== null && panneauNeuf.open === false);
  check('le bouton redevient « Filtrer »', panneauNeuf?.querySelector('summary')?.textContent.trim() === 'Filtrer');

  console.log(`\n${'='.repeat(64)}`);
  if (échecs > 0) {
    console.log(`E2E UI catalogue : ${échecs}/${tests} vérification(s) en échec ❌`);
    process.exitCode = 1;
  } else {
    console.log(`E2E UI catalogue : TOUT EST VERT ✅ (${tests} vérifications, clics réels sur l'UI, lecture mobile)`);
  }
} catch (e) {
  console.error('\nE2E UI catalogue : ERREUR fatale —', e);
  process.exitCode = 1;
} finally {
  if (serveurLocal) serveurLocal.kill('SIGTERM');
}
