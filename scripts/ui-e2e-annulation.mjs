/**
 * MiniShop — E2E UI « Annulation sans confirmation » (UC-09, EF-CLI-10).
 *
 * Signalement : un clic sur « Annuler cette commande » annulait tout de
 * suite, sans « Êtes-vous sûr ? », alors que l'action est définitive —
 * et ce bouton (comme « Supprimer » dans l'admin) avait le même style
 * sombre que les boutons de validation.
 *
 * Parcours prouvés ici, par clics réels sur les pages (jsdom) :
 *   client  : commande créée → « Annuler cette commande » (bouton
 *             danger) → page « Êtes-vous sûr ? » → « Non » : rien n'a
 *             changé → « Annuler » → « Oui, annuler définitivement » :
 *             commande Annulée dans l'historique, bouton disparu ;
 *   admin   : catégorie témoin créée → « Supprimer » (danger) →
 *             confirmation → « Non » : catégorie intacte →
 *             « Supprimer » → « Oui » : catégorie supprimée.
 * Aucune action définitive ne part plus en un seul clic.
 *
 * Serveur cible : UI_BASE_URL si défini (CI : php -S + dev-router),
 * sinon scripts/wasm-serve.mjs démarré sur UI_PORT (défaut 8100).
 * Prérequis : data/minishop peuplé (le script npm reseede avant).
 *
 * Usage : node scripts/ui-e2e-annulation.mjs      (ou npm run test:ui-annulation)
 */

import { JSDOM } from 'jsdom';
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const REPO = join(import.meta.dirname, '..');
const PORT = Number(process.env.UI_PORT ?? 8100);
let base = (process.env.UI_BASE_URL ?? `http://127.0.0.1:${PORT}`).replace(/\/$/, '');
let serveurLocal = null;

if (!process.env.UI_BASE_URL) {
  if (!existsSync(join(REPO, 'data/minishop/produit.json'))) {
    console.error('[ui-e2e-annulation] base absente — lancez : node scripts/wasm_run.mjs scripts/seed.php');
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
    console.log(`::error title=ui-e2e-annulation::${label.replaceAll('%', '%25').replaceAll('\n', '%0A')}`);
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

function boutonParTexte(doc, libelle) {
  const b = [...doc.querySelectorAll('button')].find((x) => x.textContent.trim() === libelle);
  if (!b) throw new Error(`bouton « ${libelle} » introuvable dans la page`);
  return b;
}

function ligneCategorie(doc, nom) {
  for (const tr of doc.querySelectorAll('table tbody tr')) {
    const champ = tr.querySelector('form input[name="nom"]');
    if (champ && champ.value === nom) return tr;
  }
  return null;
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
  console.log(`\nMiniShop — E2E UI Annulation / suppressions avec confirmation (serveur : ${base})\n`);

  // ============================================================ client : annulation
  const nav = new NavigateurUI();
  const doc = () => nav.doc();

  console.log('== 1 · Connexion cliente et commande créée par clics ==');
  await nav.aller('/connexion');
  doc().querySelector('#email').value = 'alice@example.com';
  doc().querySelector('#mot_de_passe').value = 'Demo2026!';
  await nav.cliquerBouton(bouton(doc().querySelector('form[action="/connexion"]'), 'Se connecter'));
  check('connectée', !doc().querySelector('form[action="/connexion"]'));

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
  check('commande créée, page détail affichée', /^\/mes-commandes\/\d+$/.test(new URL(nav.page.url).pathname), nav.page.url);
  const urlDetail = new URL(nav.page.url).pathname;
  check('statut initial « Payée »', nav.texte().includes('Statut : Payée'), nav.texte().slice(0, 160));

  console.log('\n== 2 · Le bouton d\'annulation : style danger, aucun effet immédiat ==');
  const boutonAnnuler = boutonParTexte(doc(), 'Annuler cette commande');
  check('le bouton porte la classe bouton-danger', boutonAnnuler.classList.contains('bouton-danger'),
    boutonAnnuler.getAttribute('class') ?? 'sans classe');
  check('il soumet un GET vers la page de confirmation',
    (boutonAnnuler.closest('form')?.getAttribute('method') ?? '').toLowerCase() === 'get'
      && (boutonAnnuler.closest('form')?.getAttribute('action') ?? '').endsWith('/annulation/confirmation'),
    boutonAnnuler.closest('form')?.getAttribute('action') ?? 'sans formulaire');

  await nav.cliquerBouton(boutonAnnuler);
  check('arrivée sur /annulation/confirmation', new URL(nav.page.url).pathname.endsWith('/annulation/confirmation'), nav.page.url);
  check('« Êtes-vous sûr de vouloir annuler la commande » ?', nav.texte().includes('Êtes-vous sûr de vouloir annuler la commande'));
  check('le caractère définitif est annoncé', nav.texte().includes('Cette action est définitive.'));
  check('le bouton d\'exécution est lui aussi en danger',
    boutonParTexte(doc(), 'Oui, annuler définitivement').classList.contains('bouton-danger'));

  console.log('\n== 3 · « Non » : retour au détail, commande intacte ==');
  const lienNon = [...doc().querySelectorAll('a')].find((a) => a.textContent.trim() === 'Non, revenir à ma commande');
  check('le lien « Non, revenir à ma commande » existe', !!lienNon);
  await nav.aller(lienNon.getAttribute('href'));
  check('retour sur le détail de la commande', new URL(nav.page.url).pathname === urlDetail, nav.page.url);
  check('statut toujours « Payée » (rien n\'a été annulé)', nav.texte().includes('Statut : Payée'));
  check('le bouton d\'annulation est toujours proposé', !!([...doc().querySelectorAll('button')].find((b) => b.textContent.trim() === 'Annuler cette commande')));

  console.log('\n== 4 · « Oui, annuler définitivement » : annulation effective ==');
  await nav.cliquerBouton(boutonParTexte(doc(), 'Annuler cette commande'));
  const formConfirmation = [...doc().querySelectorAll('form')].find((f) => (f.getAttribute('action') ?? '').endsWith('/annulation'));
  await nav.cliquerBouton(bouton(formConfirmation, 'Oui, annuler définitivement'));
  check('redirigé vers l\'historique /mes-commandes', new URL(nav.page.url).pathname === '/mes-commandes', nav.page.url);
  check('confirmation affichée « Commande annulée »', nav.texte().includes('Commande annulée'), nav.texte().slice(0, 200));
  const ligne = [...doc().querySelectorAll('tr')].find((tr) => tr.querySelector(`a[href="${urlDetail}"]`));
  check('la commande est « Annulée » dans l\'historique', !!ligne && ligne.textContent.includes('Annulée'),
    ligne ? ligne.textContent.replace(/\s+/g, ' ').slice(0, 120) : 'ligne absente');
  await nav.aller(urlDetail);
  check('détail : statut « Annulée »', nav.texte().includes('Statut : Annulée'));
  check('détail : plus de bouton d\'annulation (action consommée)',
    ![...doc().querySelectorAll('button')].some((b) => b.textContent.trim() === 'Annuler cette commande'));

  // ============================================================ admin : suppression
  console.log('\n== 5 · Admin : « Supprimer » une catégorie exige la même confirmation ==');
  const admin = new NavigateurUI();
  const docA = () => admin.doc();
  await admin.aller('/admin/categories');
  docA().querySelector('#email').value = 'admin@minishop.fr';
  docA().querySelector('#mot_de_passe').value = 'Admin2026!';
  await admin.cliquerBouton(bouton(docA().querySelector('form[action="/admin/connexion"]'), 'Accéder au back-office'));
  check('admin connecté', admin.texte().includes('Tableau de bord'));

  await admin.aller('/admin/categories');
  docA().querySelector('#nom').value = 'Temporaire';
  docA().querySelector('#description').value = 'Catégorie témoin';
  await admin.cliquerBouton(bouton(docA().querySelector('form[action="/admin/categories"]'), 'Créer'));
  check('catégorie témoin créée', ligneCategorie(docA(), 'Temporaire') !== null);

  const boutonSupprimer = () => {
    const ligne = ligneCategorie(docA(), 'Temporaire');
    if (!ligne) return null;
    return [...ligne.querySelectorAll('button')].find((b) => b.textContent.trim() === 'Supprimer') ?? null;
  };
  check('« Supprimer » porte la classe bouton-danger', boutonSupprimer()?.classList.contains('bouton-danger') ?? false);

  await admin.cliquerBouton(boutonSupprimer());
  check('page de confirmation de suppression', admin.texte().includes('Êtes-vous sûr de vouloir supprimer la catégorie'),
    admin.page.url);
  const lienNonAdmin = [...docA().querySelectorAll('a')].find((a) => a.textContent.trim().startsWith('Non, revenir'));
  await admin.aller(lienNonAdmin.getAttribute('href'));
  check('« Non » : retour à la liste, catégorie intacte', ligneCategorie(docA(), 'Temporaire') !== null);

  await admin.cliquerBouton(boutonSupprimer());
  const formConfAdmin = [...docA().querySelectorAll('form')].find((f) => (f.getAttribute('action') ?? '').endsWith('/suppression'));
  check('le bouton d\'exécution admin est en danger',
    bouton(formConfAdmin, 'Oui, supprimer définitivement').classList.contains('bouton-danger'));
  await admin.cliquerBouton(bouton(formConfAdmin, 'Oui, supprimer définitivement'));
  check('« Catégorie supprimée. » après confirmation', admin.texte().includes('Catégorie supprimée.'), admin.texte().slice(0, 200));
  check('la catégorie a disparu de la liste', ligneCategorie(docA(), 'Temporaire') === null);

  console.log(`\n${'='.repeat(64)}`);
  if (échecs > 0) {
    console.log(`E2E UI annulation : ${échecs}/${tests} vérification(s) en échec ❌`);
    process.exitCode = 1;
  } else {
    console.log(`E2E UI annulation : TOUT EST VERT ✅ (${tests} vérifications, clics réels sur l'UI)`);
  }
} catch (e) {
  console.error('\nE2E UI annulation : ERREUR fatale —', e);
  process.exitCode = 1;
} finally {
  if (serveurLocal) serveurLocal.kill('SIGTERM');
}
