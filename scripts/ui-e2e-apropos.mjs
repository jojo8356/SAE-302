/**
 * MiniShop — E2E UI « Page À propos » (EF-GEN-05).
 *
 * Même principe que les autres suites ui-e2e-* : les vraies pages sont
 * pilotées au DOM (jsdom), navigation par les liens du site, contenu
 * lu dans la page rendue. Aucun appel d'API direct.
 *
 * Signalement : la page s'adressait aux développeurs (chemins de
 * fichiers, classes, codes RB-…), affirmait que la boutique « ne
 * fonctionne ni avec MySQL, ni avec aucune requête SQL » — phrase
 * lisible comme un hors-sujet PHP/MySQL/PDO par le jury, et fausse
 * après la bascule MySQL de l'hébergement final — et annonçait
 * « 4 paramètres métier » en n'en citant que 3.
 *
 * La suite verrouille la page visiteur : présentation de la boutique,
 * chiffres du catalogue, les 4 règles chiffrées complètes (dont la
 * conservation des données, 24 mois, celle qui manquait), formulation
 * technique honnête (cible MySQL via PDO, démonstration distinguée),
 * et absence du jargon d'implémentation dans le texte affiché.
 *
 * Serveur cible : UI_BASE_URL si défini (ex. CI : php -S + dev-router),
 * sinon scripts/wasm-serve.mjs démarré sur UI_PORT (défaut 8096).
 * Prérequis : data/minishop peuplé (node scripts/wasm_run.mjs scripts/seed.php).
 *
 * Usage : node scripts/ui-e2e-apropos.mjs      (ou npm run test:ui-apropos)
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
const PORT = Number(process.env.UI_PORT ?? 8096);
let base = (process.env.UI_BASE_URL ?? `http://127.0.0.1:${PORT}`).replace(/\/$/, '');
let serveurLocal = null;

if (!process.env.UI_BASE_URL) {
  if (!existsSync(join(REPO, 'data/minishop/produit.json'))) {
    console.error('[ui-e2e-apropos] base absente — lancez : node scripts/wasm_run.mjs scripts/seed.php');
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
    console.log(`::error title=ui-e2e-apropos::${label.replaceAll('%', '%25').replaceAll('\n', '%0A')}`);
  }
};

// ---------------------------------------------------------------- navigateur UI
/** Mini-navigateur : cookies, redirections suivies comme un navigateur, DOM jsdom. */
class NavigateurUI {
  constructor() {
    this.cookies = {};
    this.page = null;
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
      this.page = { dom, url, statut: res.status };
      return this.page;
    }
    throw new Error(`trop de redirections vers ${chemin}`);
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
  console.log(`\nMiniShop — E2E UI Page À propos (serveur : ${base})\n`);
  const nav = new NavigateurUI();
  const doc = () => nav.doc();

  // ---------------------------------------------------------- 1. navigation par le menu
  console.log('== 1 · Accueil → lien « À propos » du menu ==');
  await nav.aller('/');
  const lienApropos = doc().querySelector('nav a[href="/a-propos"]');
  check('le menu contient le lien « À propos »', !!lienApropos && lienApropos.textContent.trim() === 'À propos');
  await nav.aller(lienApropos.getAttribute('href'));
  check('arrivée sur /a-propos (HTTP 200)', nav.page.statut === 200 && new URL(nav.page.url).pathname === '/a-propos',
    `${nav.page.statut} ${nav.page.url}`);

  // ---------------------------------------------------------- 2. page visiteur
  console.log('\n== 2 · Une page qui présente la boutique ==');
  check('titre de page « À propos de MiniShop »', doc().title.includes('À propos de MiniShop'), doc().title);
  check('H1 « À propos de MiniShop »', doc().querySelector('h1')?.textContent.trim() === 'À propos de MiniShop');
  const texte = nav.texte();
  check('la boutique est présentée (matériel informatique, objets connectés)',
    texte.includes('matériel informatique') && texte.includes('objets connectés'));
  check('prix TTC et paiement simulé annoncés', texte.includes('TTC') && texte.includes('simulé'));
  check('les chiffres du catalogue sont là (produits, catégories)',
    /\d+ produits/.test(texte) && texte.includes('4 catégories'), texte.slice(0, 200));

  // ---------------------------------------------------------- 3. les 4 règles, toutes
  console.log('\n== 3 · Les 4 règles chiffrées, sans exception ==');
  const titreRegles = [...doc().querySelectorAll('h2')].find((h) => h.textContent.includes('règles chiffrées'));
  check('le titre annonce 4 règles', !!titreRegles && titreRegles.textContent.includes('Nos 4 règles'), titreRegles?.textContent ?? 'absent');
  // La liste des règles est le premier <ul> après son titre
  // (un paragraphe d'introduction peut s'intercaler).
  let ul = null;
  for (let el = titreRegles?.nextElementSibling; el; el = el.nextElementSibling) {
    if (el.tagName === 'UL') { ul = el; break; }
    if (el.tagName === 'H2') break;
  }
  check('la liste des règles existe', !!ul);
  const items = ul ? [...ul.querySelectorAll('li')].map((li) => li.textContent.replace(/\s+/g, ' ').trim()) : [];
  check('exactement 4 règles listées', items.length === 4, `${items.length} item(s)`);
  check('livraison standard : 4,90 €', items.some((t) => t.includes('Livraison standard') && t.includes('4,90 €')), items.join(' | '));
  check('livraison offerte dès 80,00 €', items.some((t) => t.includes('offerte') && t.includes('80,00 €')));
  check('TVA : 20 %', items.some((t) => t.includes('TVA') && t.includes('20 %')));
  check('conservation des données : 24 mois (la règle qui manquait)',
    items.some((t) => t.includes('Conservation') && t.includes('24 mois')));

  // ---------------------------------------------------------- 4. technique honnête, zéro jargon
  console.log('\n== 4 · Technique : cible MySQL/PDO assumée, jargon absent ==');
  check('PHP, MySQL et PDO sont nommés', texte.includes('PHP') && texte.includes('MySQL') && texte.includes('PDO'));
  check('la démonstration est distinguée de la cible', texte.includes('démonstration') && texte.includes('cible'));
  const interdits = [
    'ni avec MySQL', 'aucune requête SQL', 'data/minishop', '.json', 'StorageDriver',
    'RB-', 'ENF-', 'EF-', 'NOT NULL', 'déclencheur', 'rollback',
    'À propos du moteur de données', 'Contenu actuel de la base',
  ];
  for (const motif of interdits) {
    check(`absent de la page : « ${motif} »`, !texte.includes(motif) && !doc().body.innerHTML.includes(motif));
  }

  // ---------------------------------------------------------- 5. sortie vers le catalogue
  console.log('\n== 5 · La page renvoie vers le catalogue ==');
  const lienCatalogue = [...doc().querySelectorAll('a[href="/catalogue"]')]
    .find((a) => a.textContent.includes('catalogue'));
  check('lien « Découvrir le catalogue » présent', !!lienCatalogue);
  await nav.aller(lienCatalogue.getAttribute('href'));
  check('arrivée sur /catalogue', new URL(nav.page.url).pathname === '/catalogue', nav.page.url);

  console.log(`\n${'='.repeat(64)}`);
  if (échecs > 0) {
    console.log(`E2E UI À propos : ${échecs}/${tests} vérification(s) en échec ❌`);
    process.exitCode = 1;
  } else {
    console.log(`E2E UI À propos : TOUT EST VERT ✅ (${tests} vérifications)`);
  }
} catch (e) {
  console.error('\nE2E UI À propos : ERREUR fatale —', e);
  process.exitCode = 1;
} finally {
  if (serveurLocal) serveurLocal.kill('SIGTERM');
}
