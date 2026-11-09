/**
 * MiniShop — E2E UI « Admin > Catégories : page qui défile, bouton
 * de droite coupé » (UC-11, EF-ADM-03).
 *
 * Signalement : dans Admin > Catégories, avec une fenêtre
 * d'environ 1 000 px, toute la page défilait horizontalement et le
 * bouton à droite était coupé (768 et 1 440 px : OK). Cause : le
 * tableau des catégories n'avait aucune règle dédiée — le
 * formulaire d'édition en ligne (trois champs côte à côte et son
 * bouton « Enregistrer ») étalait le tableau selon son contenu et
 * repoussait la colonne Actions (« Supprimer ») hors de la page.
 *
 * Parcours prouvé ici, par clics réels sur les pages (jsdom) :
 *   connexion admin → /admin/categories : le tableau annonce sa
 *   mise en page tenue (classe, colgroup des cinq colonnes), chaque
 *   ligne affiche son formulaire d'édition complet et son bouton
 *   « Supprimer » ; la feuille servie contient les règles qui
 *   garantissent cette tenue à toute largeur (largeurs totalisant
 *   100 %, champs tenus dans leur colonne, boutons en une pièce) ;
 *   l'édition d'une description par le formulaire de la ligne
 *   fonctionne toujours (puis remise en état).
 *
 * jsdom n'a pas de moteur de mise en page (ni de largeur de
 * fenêtre significative) : la tenue à ~1 000 px comme à 768 ou
 * 1 440 px est vérifiée par ce qui la garantit — structure et
 * classes dans le DOM rendu, et règles CSS effectivement servies.
 *
 * Serveur cible : UI_BASE_URL si défini (CI : php -S + dev-router),
 * sinon scripts/wasm-serve.mjs démarré sur UI_PORT (défaut 8114).
 * Prérequis : data/minishop peuplé (le script npm reseede avant).
 *
 * Usage : node scripts/ui-e2e-categories-tableau.mjs      (ou npm run test:ui-categories-tableau)
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
const PORT = Number(process.env.UI_PORT ?? 8114);
let base = (process.env.UI_BASE_URL ?? `http://127.0.0.1:${PORT}`).replace(/\/$/, '');
let serveurLocal = null;

if (!process.env.UI_BASE_URL) {
  if (!existsSync(join(REPO, 'data/minishop/produit.json'))) {
    console.error('[ui-e2e-categories-tableau] base absente — lancez : node scripts/wasm_run.mjs scripts/seed.php');
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
    console.log(`::error title=ui-e2e-categories-tableau::${label.replaceAll('%', '%25').replaceAll('\n', '%0A')}`);
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
  console.log(`\nMiniShop — E2E UI Admin > Catégories, page tenue en page (serveur : ${base})\n`);
  const nav = new NavigateurUI();
  const doc = () => nav.doc();

  // ---------------------------------------------------------- 1. connexion admin
  console.log('== 1 · Connexion administrateur par l\'UI ==');
  await nav.aller('/admin/categories');
  check('redirigé vers le login admin', new URL(nav.page.url).pathname === '/admin/connexion', nav.page.url);
  doc().querySelector('#email').value = 'admin@minishop.fr';
  doc().querySelector('#mot_de_passe').value = 'Admin2026!';
  await nav.cliquerBouton(bouton(doc().querySelector('form[action="/admin/connexion"]'), 'Accéder au back-office'));
  check('admin connecté', nav.texte().includes('Tableau de bord'));

  // ---------------------------------------------------------- 2. le tableau à l'écran
  console.log('\n== 2 · Admin > Catégories : les cinq colonnes, boutons en entier ==');
  await nav.aller('/admin/categories');
  check('titre « Catégories »', doc().querySelector('h1')?.textContent.trim() === 'Catégories');
  const tableau = doc().querySelector('table.tableau-categories');
  check('le tableau porte la classe tableau-categories (mise en page dédiée)', !!tableau);

  const cols = [...tableau.querySelectorAll('colgroup col')].map((c) => c.className);
  check('le colgroup fixe les cinq colonnes dans l\'ordre, Actions en dernière (à droite)',
    cols.join('|') === 'col-edition|col-slug|col-produits|col-description|col-actions',
    cols.join('|'));
  const entetes = [...tableau.querySelectorAll('thead th')];
  check('cinq en-têtes : Nom, Slug, Produits visibles, Description, Actions',
    entetes.map((th) => th.textContent.trim()).join('|') === 'Nom|Slug|Produits visibles|Description|Actions',
    entetes.map((th) => th.textContent.trim()).join('|'));

  const lignes = [...tableau.querySelectorAll('tbody tr')];
  check('les catégories du seed sont listées (4 lignes)', lignes.length === 4, `${lignes.length} ligne(s)`);
  check('chaque ligne a son formulaire d\'édition complet (3 champs + « Enregistrer »)',
    lignes.every((tr) => {
      const form = tr.querySelector('form[action*="/modification"]');
      return form !== null
        && form.querySelector('input[name="nom"]') !== null
        && form.querySelector('input[name="slug"]') !== null
        && form.querySelector('input[name="description"]') !== null
        && [...form.querySelectorAll('button')].some((b) => b.textContent.trim() === 'Enregistrer');
    }));
  check('chaque ligne a son bouton « Supprimer » (bouton-danger) dans la colonne Actions',
    lignes.every((tr) => {
      const form = tr.querySelector('form[action*="/suppression/confirmation"]');
      const b = form?.querySelector('button.bouton-danger');
      return !!b && b.textContent.trim() === 'Supprimer';
    }));
  check('le formulaire de création reste au-dessus du tableau (« Créer »)',
    !!doc().querySelector('form[action="/admin/categories"]')
      && [...doc().querySelectorAll('button')].some((b) => b.textContent.trim() === 'Créer'));

  // ---------------------------------------------------------- 3. la feuille qui garantit la tenue
  console.log('\n== 3 · Feuille servie : les règles de tenue sont là ==');
  const resCss = await fetch(`${base}/css/app.css`, { headers: nav.enTetes() });
  const css = await resCss.text();
  check('la feuille est servie (200) et définit .tableau-categories', resCss.status === 200 && css.includes('.tableau-categories'),
    `HTTP ${resCss.status}`);
  check('largeur 100 % et répartition fixe des colonnes (table-layout)',
    css.includes('width: 100%') && css.includes('table-layout: fixed'));
  const largeurs = ['col-edition', 'col-slug', 'col-produits', 'col-description', 'col-actions']
    .map((nom) => {
      const m = new RegExp(`\\.tableau-categories \\.${nom}\\s*\\{[^}]*width:\\s*(\\d+)%`).exec(css);
      return m ? Number.parseInt(m[1], 10) : null;
    });
  check('les cinq largeurs de colonnes totalisent 100 % (la page tient dans la fenêtre, à ~1 000 px comme ailleurs)',
    largeurs.every((l) => l !== null) && largeurs.reduce((a, b) => a + b, 0) === 100,
    largeurs.join(','));
  check('les champs d\'édition tiennent dans leur colonne et s\'empilent (grille, largeur 100 %)',
    /\.tableau-categories form\s*\{[^}]*display:\s*grid/.test(css)
      && /\.tableau-categories input\s*\{[^}]*width:\s*100%/.test(css)
      && /\.tableau-categories input\s*\{[^}]*box-sizing:\s*border-box/.test(css));
  check('les actions restent étiquetées sur mobile et le bouton est visible',
    css.includes('.tableau-defilant--admin tbody td::before')
      && lignes.every((tr) => tr.querySelector('td[data-label="Actions"] button.bouton-danger')));

  // ---------------------------------------------------------- 4. l'édition fonctionne toujours
  console.log('\n== 4 · Édition d\'une description par le formulaire de la ligne ==');
  const formEdition = doc().querySelector('table.tableau-categories form[action*="/modification"]');
  const champDescription = formEdition.querySelector('input[name="description"]');
  const descriptionInitiale = champDescription.value;
  champDescription.value = 'Description modifiée (e2e)';
  await nav.cliquerBouton(bouton(formEdition, 'Enregistrer'));
  check('toujours sur /admin/categories après l\'édition', new URL(nav.page.url).pathname === '/admin/categories', nav.page.url);
  check('la nouvelle description est affichée dans le tableau',
    [...doc().querySelectorAll('table.tableau-categories tbody td')].some((td) => td.textContent.trim() === 'Description modifiée (e2e)'));
  const formRetour = doc().querySelector('table.tableau-categories form[action*="/modification"]');
  formRetour.querySelector('input[name="description"]').value = descriptionInitiale;
  await nav.cliquerBouton(bouton(formRetour, 'Enregistrer'));
  check('description remise à son état initial en fin de parcours',
    [...doc().querySelectorAll('table.tableau-categories tbody td')].some((td) => td.textContent.trim() === descriptionInitiale));

  console.log(`\n${'='.repeat(64)}`);
  if (échecs > 0) {
    console.log(`E2E UI catégories (tableau) : ${échecs}/${tests} vérification(s) en échec ❌`);
    process.exitCode = 1;
  } else {
    console.log(`E2E UI catégories (tableau) : TOUT EST VERT ✅ (${tests} vérifications, interactions DOM simulées avec jsdom)`);
  }
} catch (e) {
  console.error('\nE2E UI catégories (tableau) : ERREUR fatale —', e);
  process.exitCode = 1;
} finally {
  if (serveurLocal) serveurLocal.kill('SIGTERM');
}
