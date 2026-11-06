/**
 * MiniShop — E2E UI « Admin > Catégories : un doublon écrase la catégorie
 * existante » (EF-ADM-03, uk_categorie_nom / uk_categorie_slug).
 *
 * Contrairement à scripts/wasm-e2e.mjs (requêtes HTTP composées à la
 * main), cette suite pilote l'interface comme un navigateur : elle
 * charge les vraies pages, remplit les champs par leurs identifiants,
 * CLIQUE sur les boutons (« Accéder au back-office », « Créer »,
 * « Enregistrer », « Supprimer ») et lit le résultat dans le DOM
 * (messages flash role=status/role=alert, lignes du tableau des
 * catégories). Aucun appel d'API direct : le seul transport est
 * l'envoi du formulaire exactement tel que le DOM l'a construit
 * (jeton CSRF inclus), via le DOM jsdom.
 *
 * Scénario = celui du signalement, mot pour mot :
 *   Admin > Catégories, créer « Audio » avec la description « test »
 *   → attendu : refus « Ce nom de catégorie existe déjà. » et vraie
 *     catégorie Audio (description « Casques, enceintes et
 *     microphones ») strictement inchangée, en une seule exemplaire ;
 *   → obtenu avant correctif : « Catégorie enregistrée » et
 *     description de la vraie Audio remplacée par « test ».
 * S'ajoutent : garde d'accès, renommage vers un nom pris (refusé),
 * création valide témoin (acceptée puis supprimée via l'UI).
 *
 * Serveur cible :
 *   - UI_BASE_URL défini → ce serveur (ex. CI : php -S + dev-router) ;
 *   - sinon, scripts/wasm-serve.mjs est démarré sur UI_PORT (défaut
 *     8092) le temps de la suite, puis arrêté.
 * Prérequis : data/minishop peuplé (node scripts/wasm_run.mjs scripts/seed.php).
 *
 * Usage : node scripts/ui-e2e-categories.mjs      (ou npm run test:ui)
 */

import { JSDOM } from 'jsdom';
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const REPO = join(import.meta.dirname, '..');
const PORT = Number(process.env.UI_PORT ?? 8092);
let base = (process.env.UI_BASE_URL ?? `http://127.0.0.1:${PORT}`).replace(/\/$/, '');
let serveurLocal = null;

if (!process.env.UI_BASE_URL) {
  if (!existsSync(join(REPO, 'data/minishop/produit.json'))) {
    console.error('[ui-e2e] base absente — lancez : node scripts/wasm_run.mjs scripts/seed.php');
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
    console.log(`::error title=ui-e2e::${label.replaceAll('%', '%25').replaceAll('\n', '%0A')}`);
  }
};

// ---------------------------------------------------------------- navigateur UI
/** Mini-navigateur : cookies, redirections suivies comme un navigateur, DOM jsdom cliquable. */
class NavigateurUI {
  constructor() {
    this.cookies = {};
    this.page = null;   // { dom, url, statut }
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
      this.page = { dom, url, statut: res.status };
      this.soumission = null;
      // Toute soumission de formulaire est interceptée au niveau DOM :
      // le clic sur le bouton reste réel, seul le transport est rejoué.
      dom.window.document.addEventListener('submit', (e) => {
        e.preventDefault();
        this.soumission = { formulaire: e.target, donnees: new dom.window.FormData(e.target) };
      }, true);
      return this.page;
    }
    throw new Error(`trop de redirections vers ${chemin}`);
  }

  /** Clique sur un bouton de soumission et envoie le formulaire intercepté. */
  async cliquerBouton(bouton) {
    this.soumission = null;
    bouton.click();
    if (!this.soumission) {
      throw new Error(`le clic sur « ${bouton.textContent.trim()} » n'a soumis aucun formulaire`);
    }
    const { formulaire, donnees } = this.soumission;
    const url = new URL(formulaire.getAttribute('action') ?? '', this.page.url).href;
    const corps = new URLSearchParams();
    for (const [cle, valeur] of donnees.entries()) corps.append(cle, String(valeur));
    const res = await fetch(url, {
      method: 'POST',
      headers: this.enTetes({ 'content-type': 'application/x-www-form-urlencoded' }),
      body: corps.toString(),
      redirect: 'manual',
    });
    this.mémorise(res);
    await res.text();
    if (res.status >= 300 && res.status < 400 && res.headers.get('location')) {
      return this.aller(new URL(res.headers.get('location'), url).pathname);
    }
    return this.aller(new URL(url).pathname);
  }

  doc() { return this.page.dom.window.document; }
  texte() { return this.doc().body.textContent.replace(/\s+/g, ' ').trim(); }
}

/** Bouton d'un formulaire, retrouvé par son libellé visible. */
function bouton(page, formulaire, libelle) {
  const b = [...formulaire.querySelectorAll('button')].find((x) => x.textContent.trim() === libelle);
  if (!b) throw new Error(`bouton « ${libelle} » introuvable`);
  return b;
}

/** Ligne du tableau des catégories portant ce nom (champ nom du formulaire d'édition). */
function ligneCategorie(doc, nom) {
  for (const tr of doc.querySelectorAll('table tbody tr')) {
    const champ = tr.querySelector('form input[name="nom"]');
    if (champ && champ.value === nom) return tr;
  }
  return null;
}

/** Description affichée (4e colonne) de la ligne d'une catégorie. */
function descriptionAffichee(doc, nom) {
  const tr = ligneCategorie(doc, nom);
  if (!tr) return null;
  return tr.querySelectorAll('td')[3].textContent.trim();
}

function lignesCategories(doc) {
  return [...doc.querySelectorAll('table tbody tr')];
}

// ---------------------------------------------------------------- démarrage serveur
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
  console.log(`\nMiniShop — E2E UI Admin > Catégories (serveur : ${base})\n`);
  const nav = new NavigateurUI();

  // ---------------------------------------------------------- 1. garde d'accès
  console.log('== 1 · Admin > Catégories sans connexion : redirection vers le login ==');
  await nav.aller('/admin/categories');
  check('redirigé vers /admin/connexion', new URL(nav.page.url).pathname === '/admin/connexion', nav.page.url);
  check('le formulaire de connexion admin est affiché', !!nav.doc().querySelector('form[action="/admin/connexion"]'));

  // ---------------------------------------------------------- 2. connexion par l'UI
  console.log('\n== 2 · Connexion administrateur via le formulaire ==');
  nav.doc().querySelector('#email').value = 'admin@minishop.fr';
  nav.doc().querySelector('#mot_de_passe').value = 'Admin2026!';
  await nav.cliquerBouton(bouton(nav.page, nav.doc().querySelector('form[action="/admin/connexion"]'), 'Accéder au back-office'));
  check('arrivée au tableau de bord', nav.texte().includes('Tableau de bord'));

  // ---------------------------------------------------------- 3. page Catégories
  console.log('\n== 3 · Admin > Catégories : état initial ==');
  await nav.aller('/admin/categories');
  const doc = () => nav.doc();
  check('titre « Catégories »', doc().querySelector('h1')?.textContent.trim() === 'Catégories');
  check('formulaire de création présent (#nom, #description)', !!doc().querySelector('#nom') && !!doc().querySelector('#description'));
  check('4 catégories au départ', lignesCategories(doc()).length === 4, `${lignesCategories(doc()).length} lignes`);
  check(
    'Audio : description d\'origine affichée',
    descriptionAffichee(doc(), 'Audio') === 'Casques, enceintes et microphones',
    descriptionAffichee(doc(), 'Audio') ?? 'ligne absente'
  );

  // ---------------------------------------------------------- 4. LE scénario signalé
  console.log('\n== 4 · Scénario signalé : créer « Audio » / description « test » ==');
  const formCreation = doc().querySelector('form[action="/admin/categories"]');
  doc().querySelector('#nom').value = 'Audio';
  doc().querySelector('#description').value = 'test';
  await nav.cliquerBouton(bouton(nav.page, formCreation, 'Créer'));
  check(
    'refus affiché : « Ce nom de catégorie existe déjà. »',
    nav.doc().querySelector('[role="alert"]')?.textContent.includes('Ce nom de catégorie existe déjà.') ?? false,
    nav.texte().slice(0, 200)
  );
  check('aucun « Catégorie enregistrée »', !nav.texte().includes('Catégorie enregistrée.'));
  check(
    'la vraie Audio est intacte dans le tableau',
    descriptionAffichee(doc(), 'Audio') === 'Casques, enceintes et microphones',
    descriptionAffichee(doc(), 'Audio') ?? 'ligne absente'
  );
  check('la description « test » n\'apparaît nulle part', descriptionAffichee(doc(), 'Audio') !== 'test' && !lignesCategories(doc()).some((tr) => tr.textContent.includes('<td>test</td>')));
  check('toujours 4 catégories, une seule Audio', lignesCategories(doc()).length === 4
    && lignesCategories(doc()).filter((tr) => tr.querySelector('input[name="nom"]')?.value === 'Audio').length === 1);

  // ---------------------------------------------------------- 5. renommage vers un nom pris
  console.log('\n== 5 · Renommer « Accessoires » en « Audio » : refusé aussi ==');
  const ligneAccessoires = ligneCategorie(doc(), 'Accessoires');
  const formEdition = ligneAccessoires.querySelector('form');
  formEdition.querySelector('input[name="nom"]').value = 'Audio';
  await nav.cliquerBouton(bouton(nav.page, formEdition, 'Enregistrer'));
  check(
    'refus affiché au renommage',
    nav.doc().querySelector('[role="alert"]')?.textContent.includes('Ce nom de catégorie existe déjà.') ?? false
  );
  check('Accessoires toujours là, inchangée', ligneCategorie(doc(), 'Accessoires') !== null
    && descriptionAffichee(doc(), 'Accessoires') === 'Sacs, câbles, supports et batteries');
  check('Audio toujours intacte', descriptionAffichee(doc(), 'Audio') === 'Casques, enceintes et microphones');

  // ---------------------------------------------------------- 6. création valide, puis suppression (par l'UI)
  console.log('\n== 6 · Création valide témoin, puis suppression par l\'UI ==');
  doc().querySelector('#nom').value = 'Multimédia';
  doc().querySelector('#description').value = 'Image et son';
  await nav.cliquerBouton(bouton(nav.page, doc().querySelector('form[action="/admin/categories"]'), 'Créer'));
  check('« Catégorie enregistrée. » pour un nom libre',
    nav.doc().querySelector('[role="status"]')?.textContent.includes('Catégorie enregistrée.') ?? false);
  check('Multimédia visible au tableau', descriptionAffichee(doc(), 'Multimédia') === 'Image et son');
  check('5 catégories après création témoin', lignesCategories(doc()).length === 5);

  const ligneMultimedia = ligneCategorie(doc(), 'Multimédia');
  const formSuppression = [...ligneMultimedia.querySelectorAll('form')].find((f) => (f.getAttribute('action') ?? '').includes('suppression'));
  await nav.cliquerBouton(bouton(nav.page, formSuppression, 'Supprimer'));
  check('« Catégorie supprimée. » après nettoyage', nav.doc().querySelector('[role="status"]')?.textContent.includes('Catégorie supprimée.') ?? false);
  check('retour à 4 catégories, Audio intacte', lignesCategories(doc()).length === 4
    && descriptionAffichee(doc(), 'Audio') === 'Casques, enceintes et microphones');

  console.log(`\n${'='.repeat(64)}`);
  if (échecs > 0) {
    console.log(`E2E UI catégories : ${échecs}/${tests} vérification(s) en échec ❌`);
    process.exitCode = 1;
  } else {
    console.log(`E2E UI catégories : TOUT EST VERT ✅ (${tests} vérifications, clics réels sur l'UI)`);
  }
} catch (e) {
  console.error('\nE2E UI catégories : ERREUR fatale —', e);
  process.exitCode = 1;
} finally {
  if (serveurLocal) serveurLocal.kill('SIGTERM');
}
