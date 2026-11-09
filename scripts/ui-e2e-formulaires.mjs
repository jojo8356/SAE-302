/**
 * MiniShop — E2E UI « Formulaires d'admin mal alignés » (EF-ADM-01/02/03,
 * EF-VIS-03).
 *
 * Signalements : formulaire produit mal aligné (chaque champ après
 * son libellé, « Description » en bas de la zone de texte) ; dans
 * Catégories, les trois champs de modification sans étiquette et
 * l'aide « Le slug est généré… » qui déborde du cadre ; dans
 * Produits, « Catégorie : » en fin de ligne et sa liste seule sur la
 * suivante ; dans le catalogue (~1 000 px), la grille des filtres
 * désordonnée, « Prix TTC maximum » seul sur la deuxième ligne.
 *
 * Parcours prouvé ici, par clics réels sur les pages (jsdom) :
 *   connexion admin → formulaire produit : dix rangées libellé/champ
 *   liées (for = id), produit témoin créé par le formulaire puis
 *   supprimé proprement ; Produits : le filtre par mot-clé retrouve
 *   le témoin, « Catégorie : » et sa liste dans le même bloc ;
 *   Catégories : légende courte, aide dans le cadre, champs de
 *   modification étiquetés (aria-label), édition réelle puis remise
 *   en état ; catalogue : panneau « Filtrer », champs dans l'ordre
 *   régulier avec prix minimum et maximum complets, filtre appliqué
 *   par clic. La feuille servie contient les grilles écrites.
 *
 * jsdom n'a pas de moteur de mise en page (ni de largeur de fenêtre
 * significative) : l'alignement est vérifié par ce qui le garantit —
 * rangées et blocs dans le DOM rendu, règles CSS servies.
 *
 * Serveur cible : UI_BASE_URL si défini (CI : php -S + dev-router),
 * sinon scripts/wasm-serve.mjs démarré sur UI_PORT (défaut 8118).
 * Prérequis : data/minishop peuplé (le script npm reseede avant).
 *
 * Usage : node scripts/ui-e2e-formulaires.mjs      (ou npm run test:ui-formulaires)
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
const PORT = Number(process.env.UI_PORT ?? 8118);
let base = (process.env.UI_BASE_URL ?? `http://127.0.0.1:${PORT}`).replace(/\/$/, '');
let serveurLocal = null;

if (!process.env.UI_BASE_URL) {
  if (!existsSync(join(REPO, 'data/minishop/produit.json'))) {
    console.error('[ui-e2e-formulaires] base absente — lancez : node scripts/wasm_run.mjs scripts/seed.php');
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
    console.log(`::error title=ui-e2e-formulaires::${label.replaceAll('%', '%25').replaceAll('\n', '%0A')}`);
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

const REFERENCE_TEMOIN = 'E2E-FORM-001';
const NOM_TEMOIN = 'Produit témoin formulaires';

try {
  await attendreServeur();
  console.log(`\nMiniShop — E2E UI Formulaires (produit, catégories, produits, catalogue) (serveur : ${base})\n`);
  const nav = new NavigateurUI();
  const doc = () => nav.doc();

  // ---------------------------------------------------------- 1. connexion admin
  console.log('== 1 · Connexion administrateur par l\'UI ==');
  await nav.aller('/admin/produit/nouveau');
  check('redirigé vers le login admin', new URL(nav.page.url).pathname === '/admin/connexion', nav.page.url);
  doc().querySelector('#email').value = 'admin@minishop.fr';
  doc().querySelector('#mot_de_passe').value = 'Admin2026!';
  await nav.cliquerBouton(bouton(doc().querySelector('form[action="/admin/connexion"]'), 'Accéder au back-office'));
  check('admin connecté', nav.texte().includes('Tableau de bord'));

  // ---------------------------------------------------------- 2. formulaire produit
  console.log('\n== 2 · Formulaire produit : rangées libellé/champ alignées ==');
  await nav.aller('/admin/produit/nouveau');
  const formProduit = doc().querySelector('form.formulaire-produit');
  check('le formulaire porte la classe formulaire-produit', !!formProduit);
  const rangees = [...formProduit.querySelectorAll('p.champ')];
  check('dix rangées libellé/champ (référence… image)', rangees.length === 10, `${rangees.length} rangée(s)`);
  check('chaque rangée lie son libellé à son champ (for = id, dans la même rangée)',
    rangees.every((p) => {
      const label = p.querySelector('label[for]');
      if (!label) return false;
      const champ = p.querySelector(`#${label.getAttribute('for')}`);
      return champ !== null && (champ.tagName === 'INPUT' || champ.tagName === 'SELECT' || champ.tagName === 'TEXTAREA');
    }));
  const rangeeDescription = rangees.find((p) => p.querySelector('label[for="description"]'));
  check('la rangée Description contient la zone de texte (libellé en tête de rangée)',
    !!rangeeDescription && !!rangeeDescription.querySelector('textarea#description'));

  console.log('\n== 3 · Création du produit témoin par ce formulaire ==');
  formProduit.querySelector('#reference').value = REFERENCE_TEMOIN;
  formProduit.querySelector('#nom').value = NOM_TEMOIN;
  formProduit.querySelector('#description').value = 'Créé pour éprouver les formulaires (e2e).';
  formProduit.querySelector('#prix_ht').value = '10.00';
  formProduit.querySelector('#stock').value = '4';
  await nav.cliquerBouton(bouton(formProduit, 'Créer le produit'));
  check('création confirmée et retour à la liste Produits', nav.texte().includes('Produit créé.') && new URL(nav.page.url).pathname === '/admin/produits', nav.page.url);

  // ---------------------------------------------------------- 4. Produits : le filtre
  console.log('\n== 4 · Produits : « Catégorie : » avec sa liste, filtre fonctionnel ==');
  const formFiltresProduits = doc().querySelector('form.formulaire-filtres-produits');
  check('le filtre porte la classe formulaire-filtres-produits', !!formFiltresProduits);
  const blocCategorie = [...formFiltresProduits.querySelectorAll('p.champ')]
    .find((p) => p.querySelector('label[for="cat"]'));
  check('« Catégorie : » et sa liste sont dans le même bloc champ',
    !!blocCategorie && !!blocCategorie.querySelector('select#cat'));
  formFiltresProduits.querySelector('#q').value = 'témoin formulaires';
  await nav.cliquerBouton(bouton(formFiltresProduits, 'Filtrer'));
  check('le filtre retrouve le produit témoin (et lui seul)',
    nav.texte().includes(NOM_TEMOIN) && nav.texte().includes('1 produit(s)'), nav.texte().slice(0, 200));

  console.log('\n== 5 · Nettoyage : suppression du produit témoin ==');
  const formSuppression = [...doc().querySelectorAll('form[action*="/suppression/confirmation"]')]
    .find((f) => f.closest('tr')?.textContent.includes(REFERENCE_TEMOIN));
  await nav.cliquerBouton(bouton(formSuppression, 'Supprimer'));
  check('page de confirmation de suppression', nav.texte().includes('Supprimer ce produit ?'));
  const formConfirmation = doc().querySelector('form[action*="/suppression"]');
  await nav.cliquerBouton(bouton(formConfirmation, 'Oui, supprimer définitivement'));
  await nav.aller(`/admin/produits?q=${encodeURIComponent('témoin formulaires')}`);
  check('le produit témoin a disparu de la liste', !nav.texte().includes(NOM_TEMOIN));

  // ---------------------------------------------------------- 6. Catégories
  console.log('\n== 6 · Catégories : légende courte, aide dans le cadre, champs étiquetés ==');
  await nav.aller('/admin/categories');
  const formCreation = doc().querySelector('form.formulaire-creation-categorie');
  check('le formulaire de création est là', !!formCreation);
  check('la légende du cadre est courte (« Nouvelle catégorie »)',
    formCreation.querySelector('legend')?.textContent.trim() === 'Nouvelle catégorie',
    formCreation.querySelector('legend')?.textContent.trim() ?? '');
  const aide = formCreation.querySelector('fieldset p.aide');
  check('l\'aide « Le slug est généré automatiquement… » est un paragraphe dans le cadre',
    !!aide && aide.textContent.includes('Le slug est généré automatiquement'));
  const formEdition = doc().querySelector('table.tableau-categories form[action*="/modification"]');
  check('les trois champs de modification ont leur étiquette accessible (aria-label)',
    formEdition.querySelector('input[name="nom"]')?.getAttribute('aria-label') === 'Nom de la catégorie'
      && formEdition.querySelector('input[name="slug"]')?.getAttribute('aria-label') === 'Slug de la catégorie'
      && formEdition.querySelector('input[name="description"]')?.getAttribute('aria-label') === 'Description de la catégorie');
  const champDesc = formEdition.querySelector('input[name="description"]');
  const descInitiale = champDesc.value;
  champDesc.value = 'Description modifiée (e2e formulaires)';
  await nav.cliquerBouton(bouton(formEdition, 'Enregistrer'));
  check('l\'édition par le champ étiqueté fonctionne (description affichée)',
    nav.texte().includes('Description modifiée (e2e formulaires)'));
  const formRetour = doc().querySelector('table.tableau-categories form[action*="/modification"]');
  formRetour.querySelector('input[name="description"]').value = descInitiale;
  await nav.cliquerBouton(bouton(formRetour, 'Enregistrer'));
  check('description remise à son état initial', nav.texte().includes(descInitiale));

  // ---------------------------------------------------------- 7. Catalogue : grille des filtres
  console.log('\n== 7 · Catalogue : grille des filtres ordonnée, prix complets ==');
  await nav.aller('/catalogue');
  const panneau = doc().querySelector('details.filtres-catalogue');
  panneau.querySelector('summary').click();
  check('le panneau « Filtrer » s\'ouvre', panneau.open === true);
  const formCatalogue = panneau.querySelector('form[data-filtres]');
  check('le formulaire de filtres porte sa classe de grille', !!formCatalogue);
  const ordreChamps = [...formCatalogue.querySelectorAll('#q, #cat, #prix_min, #prix_max, [name="stock"], #tri')]
    .map((el) => el.id || el.getAttribute('name'));
  check('ordre régulier : q, cat, prix_min, prix_max, stock, tri',
    ordreChamps.join('|') === 'q|cat|prix_min|prix_max|stock|tri', ordreChamps.join('|'));
  check('« Prix TTC minimum » et « Prix TTC maximum » sont deux libellés complets, chacun avec son champ',
    formCatalogue.querySelector('label[for="prix_min"]')?.textContent.trim() === 'Prix TTC minimum (€)'
      && formCatalogue.querySelector('label[for="prix_max"]')?.textContent.trim() === 'Prix TTC maximum (€)'
      && !!formCatalogue.querySelector('p #prix_max'));
  formCatalogue.querySelector('#prix_max').value = '100';
  await nav.cliquerBouton(bouton(formCatalogue, 'Appliquer les filtres'));
  check('filtre prix appliqué par clic (URL prix_max=100, panneau rouvert « Filtrer (actifs) »)',
    new URL(nav.page.url).searchParams.get('prix_max') === '100'
      && (doc().querySelector('details.filtres-catalogue summary')?.textContent.trim() ?? '') === 'Filtrer (actifs)',
    nav.page.url);

  // ---------------------------------------------------------- 8. la feuille qui ordonne
  console.log('\n== 8 · Feuille servie : les grilles et blocs sont écrits ==');
  const resCss = await fetch(`${base}/css/app.css`, { headers: nav.enTetes() });
  const css = await resCss.text();
  check('la feuille est servie (200) et définit les formulaires',
    resCss.status === 200 && css.includes('.formulaire-produit') && css.includes('.panneau-filtres'),
    `HTTP ${resCss.status}`);
  check('produit : rangées en grille 11rem/1fr alignées au départ',
    /\.formulaire-produit \.champ\s*\{[^}]*grid-template-columns:\s*11rem minmax\(0, 1fr\)[^}]*align-items:\s*start/.test(css));
  check('catalogue : libellés et champs restent groupés dans le panneau',
    css.includes('.panneau-filtres label') && !!formCatalogue.querySelector('label[for="prix_min"]')
      && !!formCatalogue.querySelector('label[for="prix_max"]'));
  check('produit : la grille passe en une colonne sur mobile',
    css.includes('.formulaire-produit .champ { grid-template-columns: minmax(0, 1fr); }'));


  console.log(`\n${'='.repeat(64)}`);
  if (échecs > 0) {
    console.log(`E2E UI formulaires : ${échecs}/${tests} vérification(s) en échec ❌`);
    process.exitCode = 1;
  } else {
    console.log(`E2E UI formulaires : TOUT EST VERT ✅ (${tests} vérifications, interactions DOM simulées avec jsdom)`);
  }
} catch (e) {
  console.error('\nE2E UI formulaires : ERREUR fatale —', e);
  process.exitCode = 1;
} finally {
  if (serveurLocal) serveurLocal.kill('SIGTERM');
}
