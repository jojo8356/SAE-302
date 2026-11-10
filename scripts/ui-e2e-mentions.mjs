#!/usr/bin/env node
/** Mentions légales : ne pas inventer l’éditeur ou l’hébergement ; instance jetable. */

import { spawn, spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

// Ces parcours modifient le serveur ciblé : jamais les lancer implicitement sur les données locales.
if (!process.env.UI_BASE_URL || process.env.UI_TEST_INSTANCE_JETABLE !== '1') {
  console.error('UI_BASE_URL et UI_TEST_INSTANCE_JETABLE=1 requis : utiliser uniquement une instance de test jetable. Ces tests modifient ses données.');
  process.exit(2);
}

const PORT = 8128;
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

async function aller() {
  if (!process.env.UI_BASE_URL) {
    serveur = spawn('node', ['scripts/wasm-serve.mjs'], { env: { ...process.env, PORT: String(PORT) }, stdio: 'inherit' });
    await attendreServeur();
  }

  console.log("\n[Mentions légales] 1 · La page complète, lue comme un client");
  const visiteur = new NavigateurUI();
  await visiteur.aller('/mentions-legales');
  check('éditeurs étudiants, co-direction de publication et emails universitaires renseignés',
    visiteur.texte.includes('Éditeur et responsable de publication')
      && visiteur.texte.includes('Johan Polsinelli')
      && visiteur.texte.includes('Chahd Gouiaa')
      && visiteur.texte.includes('Directeur de publication : co-responsabilité de l’équipe projet')
      && visiteur.texte.includes('johan.polsinelli@etu.unice.fr')
      && visiteur.texte.includes('chahd.gouiaa@etu.univ-cotedazur.fr')
      && !visiteur.texte.includes('à renseigner'));
  check('pas de vendeur affirmé sans vérification', visiteur.texte.includes('Aucun vendeur n’est identifié'));
  const lienContact = [...visiteur.doc.querySelectorAll('a')].find((a) => a.getAttribute('href') === '/contact');
  check('renvoi Contact présent sans le présenter comme un contact professionnel',
    lienContact !== undefined && visiteur.texte.includes('ne remplace pas des coordonnées professionnelles fonctionnelles'));
  check('hébergeur pédagogique Université Côte d’Azur / IUT avec réserve hors serveur',
    visiteur.texte.includes('Université Côte d’Azur — IUT Nice Côte d’Azur')
      && visiteur.texte.includes('linserv-info-01.campus.unice.fr')
      && visiteur.texte.includes('28 avenue Valrose, 06103 Nice Cedex 2')
      && visiteur.texte.includes('+33 (0)4 89 15 00 00')
      && visiteur.texte.includes('hors de ce serveur universitaire'));
  check('le caractère pédagogique et non productif est indiqué',
    visiteur.texte.includes('Projet pédagogique') && visiteur.texte.includes('pas une boutique en production'));
  check('lien confidentialité présent', !!visiteur.doc.querySelector('a[href="/confidentialite"]'));

  console.log("\n[Mentions légales] 2 · Contact de démonstration");
  await visiteur.aller(lienContact.getAttribute('href'));
  check('lien Contact : page 200 avec formulaire', visiteur.page.statut === 200
    && !!visiteur.doc.querySelector('form[action="/contact"]'));
  check('stockage local, sans mail ni réponse garantie', visiteur.texte.includes('enregistré localement')
    && visiteur.texte.includes('sans envoi d’e-mail ni réponse garantie'));

  console.log("\n[Mentions légales] 3 · Le README du projet raconte la même chose");
  const readme = readFileSync('README.md', 'utf8');
  const ligneCadre = readme.split('\n').find((ligne) => ligne.includes('**Cadre**')) ?? '';
  check('README : la ligne « Cadre » annonce le BUT2 (l’ancien intitulé n’y figure plus)',
    ligneCadre.includes('SAE — BUT Informatique (BUT2), Université Côte d\'Azur')
      && !ligneCadre.includes('Licence 3'));

  console.log(`\n${échecs === 0 ? '✅' : '❌'} Mentions légales : ${échecs === 0 ? 'toutes les vérifications réussies' : échecs + ' vérification(s) en échec'}\n`);
  if (serveur) {
    serveur.kill();
  }
  process.exit(échecs === 0 ? 0 : 1);
}

aller().catch(async (error) => {
  console.error('❌ Erreur E2E mentions légales :', error.message);
  if (serveur) {
    serveur.kill();
  }
  process.exit(1);
});
