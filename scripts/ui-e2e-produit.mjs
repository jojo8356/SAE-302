/**
 * MiniShop — E2E UI « Fiche produit : valeurs du tableau
 * Caractéristiques cachées » (n° 11, UC-03, EF-VIS-04).
 *
 * Signalement : le tableau « Caractéristiques » de la fiche produit
 * n'affichait que les libellés (Prix HT, TVA, Prix TTC,
 * Disponibilité) ; les valeurs étaient repoussées à droite, hors
 * écran, derrière une barre de défilement — alors que pour deux
 * colonnes, tout doit tenir.
 *
 * Parcours prouvé ici, par clics réels sur les pages (jsdom) :
 *   catalogue → fiche du premier produit → le tableau
 *   « Caractéristiques » présente ses 4 lignes, chacune avec un
 *   libellé ET une valeur non vide, dans cet ordre ; la feuille
 *   servie impose au tableau 100 % de large et des colonnes à
 *   répartition fixe (aucun défilement horizontal possible) ; le
 *   prix TTC affiché est un vrai montant en euros, la disponibilité
 *   un état explicite ; le reste du parcours (description, ajout au
 *   panier quand le stock le permet) est intact.
 *
 * jsdom n'a pas de moteur de mise en page : la tenue en page est
 * vérifiée pour ce qui la garantit — structure 2 colonnes complète
 * dans le DOM et règles CSS servies (width: 100%, table-layout:
 * fixed, repli des valeurs longues).
 *
 * Serveur cible : UI_BASE_URL si défini (CI : php -S + dev-router),
 * sinon scripts/wasm-serve.mjs démarré sur UI_PORT (défaut 8108).
 * Prérequis : data/minishop peuplé (le script npm reseede avant).
 *
 * Usage : node scripts/ui-e2e-produit.mjs      (ou npm run test:ui-produit)
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
const PORT = Number(process.env.UI_PORT ?? 8108);
let base = (process.env.UI_BASE_URL ?? `http://127.0.0.1:${PORT}`).replace(/\/$/, '');
let serveurLocal = null;

if (!process.env.UI_BASE_URL) {
  if (!existsSync(join(REPO, 'data/minishop/produit.json'))) {
    console.error('[ui-e2e-produit] base absente — lancez : node scripts/wasm_run.mjs scripts/seed.php');
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
    console.log(`::error title=ui-e2e-produit::${label.replaceAll('%', '%25').replaceAll('\n', '%0A')}`);
  }
};

// ---------------------------------------------------------------- mini-navigateur
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
    page = { dom: new JSDOM(html, { url }), url, statut: res.status, enTetes: res.headers };
    return page;
  }
  throw new Error(`trop de redirections vers ${chemin}`);
}

const doc = () => page.dom.window.document;
const texte = () => doc().body.textContent.replace(/\s+/g, ' ').trim();

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

const LIBELLES = ['Prix HT', 'TVA', 'Prix TTC', 'Disponibilité'];

try {
  await attendreServeur();
  console.log(`\nMiniShop — E2E UI Fiche produit, tableau Caractéristiques (serveur : ${base})\n`);

  // ---------------------------------------------------------- 1. catalogue → fiche
  console.log('== 1 · Catalogue → première fiche produit ==');
  await aller('/catalogue');
  check('catalogue en 200', page.statut === 200, `HTTP ${page.statut}`);
  const lienProduit = doc().querySelector('a[href^="/produit/"]');
  check('un produit du catalogue est cliquable', !!lienProduit);
  const nomAttendu = lienProduit.closest('article')?.querySelector('h2')?.textContent.trim();
  await aller(lienProduit.getAttribute('href'));
  check('fiche produit en 200', page.statut === 200, `HTTP ${page.statut}`);
  check('le titre de la fiche est le nom du produit cliqué',
    (doc().querySelector('h1')?.textContent.trim() ?? '') === nomAttendu,
    doc().querySelector('h1')?.textContent.trim() ?? '');

  // ---------------------------------------------------------- 2. le tableau complet
  console.log('\n== 2 · Tableau « Caractéristiques » : libellés ET valeurs ==');
  check('le titre « Caractéristiques » est affiché',
    [...doc().querySelectorAll('h2')].some((h) => h.textContent.trim() === 'Caractéristiques'));
  const tableau = doc().querySelector('table.tableau-caracteristiques');
  check('le tableau à deux colonnes existe', !!tableau);
  const lignes = tableau ? [...tableau.querySelectorAll('tbody tr')] : [];
  check('exactement 4 lignes de caractéristiques', lignes.length === 4, `${lignes.length} lignes`);

  const valeurs = {};
  let structureOk = lignes.length === 4;
  for (const [i, tr] of lignes.entries()) {
    const cellules = tr.querySelectorAll('th, td');
    if (cellules.length !== 2) structureOk = false;
    const libelle = tr.querySelector('th')?.textContent.trim() ?? '';
    const valeur = tr.querySelector('td')?.textContent.replace(/\s+/g, ' ').trim() ?? '';
    if (libelle !== (LIBELLES[i] ?? '')) structureOk = false;
    valeurs[libelle] = valeur;
  }
  check('chaque ligne : le bon libellé (Prix HT, TVA, Prix TTC, Disponibilité) + 1 valeur',
    structureOk, JSON.stringify(valeurs));
  check('aucune valeur vide ou masquée', LIBELLES.every((l) => (valeurs[l] ?? '') !== '' && valeurs[l] !== '—'),
    JSON.stringify(valeurs));
  check('Prix HT est un montant en euros', /^\d[\d\s]*,\d{2} €$/.test(valeurs['Prix HT'] ?? ''), valeurs['Prix HT'] ?? '');
  check('TVA est un pourcentage', /^\d+,\d{2} %$/.test(valeurs.TVA ?? ''), valeurs.TVA ?? '');
  check('Prix TTC est un montant en euros', /^\d[\d\s]*,\d{2} €$/.test(valeurs['Prix TTC'] ?? ''), valeurs['Prix TTC'] ?? '');
  check('Disponibilité dit un état explicite (En stock… / Rupture de stock)',
    /En stock|Rupture de stock/.test(valeurs['Disponibilité'] ?? ''), valeurs['Disponibilité'] ?? '');

  // ---------------------------------------------------------- 3. la tenue en page (CSS servie)
  console.log('\n== 3 · La feuille servie garantit : tout tient, pas de défilement ==');
  const resCss = await fetch(`${base}/css/app.css`);
  const css = await resCss.text();
  check('la feuille est servie (200)', resCss.status === 200, `HTTP ${resCss.status}`);
  check('le tableau fait 100 % de large, colonnes à répartition fixe',
    css.includes('.tableau-caracteristiques') && css.includes('width: 100%') && css.includes('table-layout: fixed'));
  check('les valeurs longues se replient au lieu de déborder (overflow-wrap)',
    css.includes('overflow-wrap: break-word'));
  check('la CSP autorise la feuille (style-src \'self\')',
    (page.enTetes.get('content-security-policy') ?? '').includes("style-src 'self'"));

  // ---------------------------------------------------------- 4. le reste de la fiche est intact
  console.log('\n== 4 · Parcours fiche préservé ==');
  check('la description est toujours affichée', texte().includes('Description'));
  const posTableau = texte().indexOf('Caractéristiques');
  const posDescription = texte().indexOf('Description');
  check('le tableau vient avant la description dans l\'ordre de lecture',
    posTableau !== -1 && posDescription !== -1 && posTableau < posDescription);
  const enStock = /En stock/.test(valeurs['Disponibilité'] ?? '');
  check('ajout au panier présent si et seulement si le produit est en stock',
    (!!doc().querySelector('form[action="/panier/ajouter"]')) === enStock);

  console.log(`\n${'='.repeat(64)}`);
  if (échecs > 0) {
    console.log(`E2E UI produit : ${échecs}/${tests} vérification(s) en échec ❌`);
    process.exitCode = 1;
  } else {
    console.log(`E2E UI produit : TOUT EST VERT ✅ (${tests} vérifications, interactions DOM simulées avec jsdom)`);
  }
} catch (e) {
  console.error('\nE2E UI produit : ERREUR fatale —', e);
  process.exitCode = 1;
} finally {
  if (serveurLocal) serveurLocal.kill('SIGTERM');
}
