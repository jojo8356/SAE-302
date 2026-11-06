#!/usr/bin/env node
/**
 * MiniShop — E2E « Contact : réponse et données » (port 8130 ; UI_BASE_URL en CI).
 *
 * Signalement : le site promettrait une réponse « sous 2 jours ouvrés »
 * sur une maquette, et le pied de page dirait « aucune donnée
 * personnelle conservée » alors que le formulaire demande nom et
 * e-mail. Ni l'une ni l'autre phrase n'existe dans l'application (pas
 * de formulaire de contact du tout) ; les incohérences réelles,
 * voisines, corrigées et vérifiées ici en vrai parcours (jsdom) :
 *   1. /contact : l'absence de réponse, autrefois reléguée en petits
 *      caractères au conditionnel, est dite en pleine page (ni
 *      formulaire ni service client, aucun message, aucune réponse) ;
 *   2. /confidentialite : les demandes de données passent par
 *      « Mon compte », seule voie réelle, et la transparence reste
 *      entière (nom, e-mail enregistrés, 36 mois) ;
 *   3. /mentions-legales : « utilisez des informations fictives »
 *      (conseil) au lieu d'affirmer qu'elles le sont ;
 *   4. pied de page servi : « aucun paiement réel », aucune négation
 *      de conservation des données.
 */

import { spawn, spawnSync } from 'node:child_process';
import { JSDOM } from 'jsdom';

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
  check("l'absence de réponse est dite clairement (aucun message, aucune réponse apportée)",
    visiteur.texte.includes('aucun message ne peut être envoyé')
      && visiteur.texte.includes('aucune réponse ne sera donc apportée'));
  check('aucun délai de réponse promis (ni « jours ouvrés », ni « 2 jours »), plus d’« éventuel canal »',
    !visiteur.texte.includes('jours ouvrés') && !visiteur.texte.includes('2 jours')
      && !visiteur.texte.includes('éventuel canal'));
  const pied = piedDePage(visiteur);
  check('le pied de page servi promet « aucun paiement réel » et ne nie aucune conservation de données',
    pied.includes('aucun paiement réel') && !pied.includes('donnée'));

  console.log("\n[Contact] 2 · Confidentialité : voie de demande réelle, transparence entière");
  const lienConfidentialite = [...visiteur.doc.querySelectorAll('a')].find((a) => a.getAttribute('href') === '/confidentialite');
  await visiteur.aller(lienConfidentialite.getAttribute('href'));
  check('la page Confidentialité s’ouvre depuis Contact (200)',
    visiteur.page.statut === 200 && visiteur.texte.includes('Confidentialité'));
  check('ce qui est enregistré est dit (nom, prénom et e-mail, conservés 36 mois)',
    visiteur.texte.includes('vos nom, prénom et e-mail') && visiteur.texte.includes('conservées 36 mois'));
  check('les demandes passent par « Mon compte », seule voie de demande — plus de renvoi trompeur',
    visiteur.texte.includes("c'est la seule voie de demande")
      && !visiteur.texte.includes('passez par la page'));

  console.log("\n[Contact] 3 · Mentions légales : le fictif est un conseil, pas une affirmation");
  await visiteur.aller('/mentions-legales');
  check('« Utilisez des informations fictives : ce que vous saisissez… est enregistré »',
    visiteur.texte.includes('Utilisez des informations fictives')
      && visiteur.texte.includes('est enregistré pour faire fonctionner la boutique'));
  check('l’ancienne affirmation (« contiennent des données fictives ») a disparu',
    !visiteur.texte.includes('contiennent des données fictives'));
  const lienConf2 = [...visiteur.doc.querySelectorAll('a')].find((a) => a.getAttribute('href') === '/confidentialite');
  check('la section Données personnelles renvoie au détail (lien Confidentialité présent)',
    lienConf2 !== undefined);

  console.log(`\n${échecs === 0 ? '✅' : '❌'} Contact cohérence : ${échecs === 0 ? 'toutes les vérifications réussies' : échecs + ' vérification(s) en échec'}\n`);
  if (serveur) {
    serveur.kill();
    spawnSync('git', ['checkout', '--', 'data'], { stdio: 'inherit' });
  }
  process.exit(échecs === 0 ? 0 : 1);
}

aller().catch(async (error) => {
  console.error('❌ Erreur E2E contact cohérence :', error.message);
  if (serveur) {
    serveur.kill();
    spawnSync('git', ['checkout', '--', 'data'], { stdio: 'inherit' });
  }
  process.exit(1);
});
