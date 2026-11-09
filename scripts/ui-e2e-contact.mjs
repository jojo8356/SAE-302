#!/usr/bin/env node
/**
 * Parcours Contact sur instance jetable uniquement : formulaire CSRF, message
 * stocké localement, sans e-mail ni réponse garantie, et cohérence légale.
 */

import { spawn, spawnSync } from 'node:child_process';
import { JSDOM } from 'jsdom';

// Ces parcours modifient le serveur ciblé : jamais les lancer implicitement sur les données locales.
if (!process.env.UI_BASE_URL || process.env.UI_TEST_INSTANCE_JETABLE !== '1') {
  console.error('UI_BASE_URL et UI_TEST_INSTANCE_JETABLE=1 requis : utiliser uniquement une instance de test jetable. Ces tests modifient ses données.');
  process.exit(2);
}

const PORT = 8130;
const base = process.env.UI_BASE_URL ?? `http://127.0.0.1:${PORT}`;
let échecs = 0;
let serveur = null;

function check(libellé, ok, détail = '') {
  console.log(`  ${ok ? '✅' : '❌'} ${libellé}${détail !== '' && !ok ? ' — ' + détail : ''}`);
  if (!ok) {
    échecs += 1;
  }
}
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));

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

  remplir(selecteur, valeur) {
    const champ = this.page.dom.window.document.querySelector(selecteur);
    if (!champ) {
      throw new Error(`champ introuvable : ${selecteur}`);
    }
    champ.value = valeur;
  }

  get doc() {
    return this.page.dom.window.document;
  }

  get texte() {
    return (this.doc.body?.textContent ?? '').replace(/\s+/g, ' ');
  }
}


async function attendreServeur() {
  for (let i = 0; i < 60; i += 1) {
    try {
      const res = await fetch(`${base}/catalogue`);
      if (res.ok) {
        return;
      }
    } catch {
      // pas encore prêt
    }
    await attendre(500);
  }
  throw new Error('serveur non prêt');
}

function piedDePage(nav) {
  return (nav.doc.querySelector('footer')?.textContent ?? '').replace(/\s+/g, ' ');
}

async function aller() {
  if (!process.env.UI_BASE_URL) {
    serveur = spawn('node', ['scripts/wasm-serve.mjs'], { env: { ...process.env, PORT: String(PORT) }, stdio: 'inherit' });
    await attendreServeur();
  }

  console.log("\n[Contact] 1 · La page Contact, lue comme un client");
  const visiteur = new NavigateurUI();
  await visiteur.aller('/contact');
  check('formulaire de contact POST protégé CSRF',
    !!visiteur.doc.querySelector('form[method="post"][action="/contact"] input[name="_csrf"]'));
  check('stockage local, sans e-mail et sans réponse garantie annoncés',
    visiteur.texte.includes('enregistré localement')
      && visiteur.texte.includes('sans envoi d’e-mail ni réponse garantie'));
  check('aucun délai de réponse promis',
    !visiteur.texte.includes('jours ouvrés') && !visiteur.texte.includes('48 heures'));
  const pied = piedDePage(visiteur);
  check('le pied ne nie pas la conservation de données',
    pied !== '' && !pied.includes('aucune donnée personnelle conservée'));

  visiteur.remplir('#nom', 'Test fictif');
  visiteur.remplir('#email', 'contact-test@example.invalid');
  visiteur.remplir('#message', 'Ceci est un message de démonstration pour le test.');
  const bouton = visiteur.doc.querySelector('form[action="/contact"] button[type="submit"]');
  await visiteur.cliquerBouton(bouton);
  check('confirmation du stockage local sans envoi de mail',
    visiteur.texte.includes('Message de démonstration enregistré localement')
      && visiteur.texte.includes('Aucun e-mail n’a été envoyé')
      && visiteur.texte.includes('aucune réponse n’est garantie'));

  console.log("\n[Contact] 2 · Confidentialité et mentions légales");
  const lienConfidentialite = [...visiteur.doc.querySelectorAll('a')].find((a) => a.getAttribute('href') === '/confidentialite');
  if (!lienConfidentialite) throw new Error('lien confidentialité absent');
  await visiteur.aller(lienConfidentialite.getAttribute('href'));
  check('Confidentialité indique les données du journal local',
    visiteur.page.statut === 200 && visiteur.texte.includes('nom, e-mail, message et date')
      && visiteur.texte.includes('var/log/contact.jsonl'));
  check('pas de mail, pas de réponse garantie et pas de purge automatique annoncés',
    visiteur.texte.includes('Aucun e-mail n’est envoyé')
      && visiteur.texte.includes('aucune réponse n’est garantie')
      && visiteur.texte.includes('Aucune durée effective générale ni purge automatique'));
  check('le formulaire ne garantit pas le traitement des demandes de droits',
    visiteur.texte.includes('ne garantit pas le traitement d’une demande de droits'));
  await visiteur.aller('/mentions-legales');
  check('le formulaire ne remplace pas des coordonnées professionnelles',
    visiteur.texte.includes('ne remplace pas des coordonnées professionnelles fonctionnelles'));
  check('pas de fausse affirmation sur des données fictives',
    !visiteur.texte.includes('contiennent des données fictives'));
  check('lien Confidentialité dans les mentions',
    !!visiteur.doc.querySelector('a[href="/confidentialite"]'));

  console.log(`\n${échecs === 0 ? '✅' : '❌'} Contact cohérence : ${échecs === 0 ? 'toutes les vérifications réussies' : échecs + ' vérification(s) en échec'}\n`);
  if (serveur) {
    serveur.kill();
  }
  process.exit(échecs === 0 ? 0 : 1);
}

aller().catch(async (error) => {
  console.error('❌ Erreur E2E contact cohérence :', error.message);
  if (serveur) {
    serveur.kill();
  }
  process.exit(1);
});
