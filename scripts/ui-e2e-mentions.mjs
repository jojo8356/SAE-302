#!/usr/bin/env node
/**
 * MiniShop — E2E « Mentions légales » (port 8128 ; UI_BASE_URL en CI).
 *
 * Signalement : la page Mentions légales ne donnait ni le nom ou un
 * contact de l'éditeur, ni l'hébergeur (l'université, une fois le site
 * sur LineServ) ; et elle annonçait « BUT Informatique / Licence 3 »
 * alors que l'équipe est en BUT2 (même erreur sur la page Contact).
 *
 * Vérifié ici en vrai parcours navigateur (jsdom) :
 *   1. /mentions-legales : éditeur nommé (équipe projet MiniShop),
 *      moyen de le joindre, section Hébergeur (Université Côte
 *      d'Azur, LineServ), BUT2 affiché, « Licence 3 » absent ;
 *   2. le lien « Contact » de la page s'ouvre sur /contact, elle-même
 *      en BUT2 et sans « Licence 3 » ;
 *   3. le README du projet annonce le même cadre (BUT2).
 */

import { spawn, spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

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
  check("l'éditeur est nommé (« l'équipe projet MiniShop »)",
    visiteur.texte.includes("Éditeur : l'équipe projet MiniShop"));
  check('un moyen de joindre l\'éditeur est donné (page Contact du site)',
    visiteur.texte.includes("Pour joindre l'équipe"));
  const lienContact = [...visiteur.doc.querySelectorAll('a')].find((a) => a.getAttribute('href') === '/contact');
  check('le renvoi « Contact » est un vrai lien cliquable', lienContact !== undefined);
  check('l’hébergeur est nommé (Université Côte d’Azur, plateforme LineServ)',
    visiteur.texte.includes('Hébergeur') && visiteur.texte.includes("l'Université Côte d'Azur, sur sa plateforme LineServ"));
  check('le cadre dit vrai (BUT Informatique — BUT2), « Licence 3 » a disparu',
    visiteur.texte.includes('BUT Informatique — BUT2') && !visiteur.texte.includes('Licence 3'));

  console.log("\n[Mentions légales] 2 · Du lien Contact aux mêmes informations justes");
  await visiteur.aller(lienContact.getAttribute('href'));
  check("le lien Contact s'ouvre (page Contact, statut 200)",
    visiteur.page.statut === 200 && visiteur.texte.includes('Contact'));
  check('la page Contact annonce le même cadre (BUT2), sans « Licence 3 »',
    visiteur.texte.includes('BUT2') && !visiteur.texte.includes('Licence 3'));

  console.log("\n[Mentions légales] 3 · Le README du projet raconte la même chose");
  const readme = readFileSync('README.md', 'utf8');
  const ligneCadre = readme.split('\n').find((ligne) => ligne.includes('**Cadre**')) ?? '';
  check('README : la ligne « Cadre » annonce le BUT2 (l’ancien intitulé n’y figure plus)',
    ligneCadre.includes('SAE — BUT Informatique (BUT2), Université Côte d\'Azur')
      && !ligneCadre.includes('Licence 3'));

  console.log(`\n${échecs === 0 ? '✅' : '❌'} Mentions légales : ${échecs === 0 ? 'toutes les vérifications réussies' : échecs + ' vérification(s) en échec'}\n`);
  if (serveur) {
    serveur.kill();
    spawnSync('git', ['checkout', '--', 'data'], { stdio: 'inherit' });
  }
  process.exit(échecs === 0 ? 0 : 1);
}

aller().catch(async (error) => {
  console.error('❌ Erreur E2E mentions légales :', error.message);
  if (serveur) {
    serveur.kill();
    spawnSync('git', ['checkout', '--', 'data'], { stdio: 'inherit' });
  }
  process.exit(1);
});
