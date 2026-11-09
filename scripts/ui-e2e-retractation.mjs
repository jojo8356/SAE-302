#!/usr/bin/env node
/** Contrôle que l’annulation simulée reste distincte du droit de rétractation. */

import { spawn, spawnSync } from 'node:child_process';
import { JSDOM } from 'jsdom';

// Ces parcours modifient le serveur ciblé : jamais les lancer implicitement sur les données locales.
if (!process.env.UI_BASE_URL || process.env.UI_TEST_INSTANCE_JETABLE !== '1') {
  console.error('UI_BASE_URL et UI_TEST_INSTANCE_JETABLE=1 requis : utiliser uniquement une instance de test jetable. Ces tests modifient ses données.');
  process.exit(2);
}

const PORT = 8126;
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

  console.log("\n[CGV] 1 · Les CGV corrigées, lues comme un client");
  const visiteur = new NavigateurUI();
  await visiteur.aller('/cgv');
  check("la section s'intitule « Annulation avant expédition »",
    visiteur.texte.includes('Annulation avant expédition'));
  check("l'ancien titre « Article 4 — Droit de rétractation » a disparu",
    !visiteur.texte.includes('Article 4 — Droit de rétractation'));
  check('la phrase fautive « Conformément au droit de rétractation, le client peut annuler » a disparu',
    !visiteur.texte.includes('Conformément au droit de rétractation'));
  check('le mécanisme réel est décrit (pas encore expédiée, stock restitué, port compris soldé)',
    visiteur.texte.includes('avant son expédition simulée') && visiteur.texte.includes('stock est restitué') && visiteur.texte.includes('port compris'));
  check('le vrai droit est rappelé à sa place (14 jours à compter de la réception, L221-18)',
    visiteur.texte.includes('14 jours à compter de la réception') && visiteur.texte.includes('L. 221-18'));

  console.log("\n[CGV] 2 · La page Rétractation distingue les deux notions");
  await visiteur.aller('/retractation');
  check('le droit légal ouvre la page (14 jours à compter de la réception)',
    visiteur.texte.includes('14 jours') && visiteur.texte.includes('à compter de la réception'));
  check("la facilité du site porte son vrai nom (« l'annulation avant expédition »)",
    visiteur.texte.includes('annulation avant expédition simulée'));
  check('la distinction est écrite (« ne se confond pas avec le droit de rétractation »)',
    visiteur.texte.includes('distincte du droit légal de rétractation'));

  console.log("\n[CGV] 3 · Parcours réel : le détail de commande parle juste");
  const navigateur = new NavigateurUI();
  await navigateur.aller('/catalogue');
  const liens = [...navigateur.doc.querySelectorAll('a[href^="/produit/"]')];
  let lienFiche = liens.find((a) => a.textContent.includes('Câble')) ?? liens[0];
  await navigateur.aller(lienFiche.getAttribute('href'));
  await navigateur.cliquerBouton(navigateur.doc.querySelector('form[action="/panier/ajouter"] button'));
  await navigateur.aller('/inscription');
  navigateur.remplir('#nom', 'Dupont');
  navigateur.remplir('#prenom', 'Legale');
  navigateur.remplir('#email', 'cgv@example.com');
  navigateur.remplir('#mot_de_passe', 'MotDePasse1!');
  navigateur.remplir('#confirmation', 'MotDePasse1!');
  navigateur.doc.querySelector('input[name="cgv"]').checked = true;
  await navigateur.cliquerBouton(navigateur.doc.querySelector('form[action="/inscription"] button'));
  await navigateur.aller('/compte');
  navigateur.remplir('#telephone', '0607080910');
  navigateur.remplir('#adresse', '12 rue de France');
  navigateur.remplir('#code_postal', '06000');
  navigateur.remplir('#ville', 'Nice');
  await navigateur.cliquerBouton(navigateur.doc.querySelector('form[action="/compte"] button'));
  await navigateur.aller('/commande/valider');
  await navigateur.cliquerBouton(navigateur.doc.querySelector('form[action="/commande/valider"] button'));
  check('sous le bouton d\'annulation : « annulation avant expédition, CGV art. 4 »',
    navigateur.texte.includes('annulation technique') && navigateur.texte.includes('avant expédition') && navigateur.texte.includes('droit légal de rétractation'));
  check('la phrase fautive « Conformément au droit de rétractation » a disparu du détail',
    !navigateur.texte.includes('Conformément au droit de rétractation'));
  await navigateur.cliquerBouton(navigateur.doc.querySelector('button.bouton-danger'));
  check("l'écran de confirmation parle d'annulation définitive (stock restitué, montant soldé)",
    navigateur.texte.includes('Annuler cette commande') && navigateur.texte.includes('stock des articles sera restitué'));
  check("l'écran de confirmation ne promet aucun « droit de rétractation »",
    !navigateur.texte.includes('Conformément au droit de rétractation'));

  console.log("\n[CGV] 4 · Aide et Livraison racontent la même règle");
  const aide = new NavigateurUI();
  await aide.aller('/aide');
  check("l'aide : annuler tant que ce n'est pas expédié, renvoi vers Rétractation",
    aide.texte.includes('annulation est possible tant que la commande') && aide.texte.includes('pas expédiée'));
  await aide.aller('/livraison');
  check('livraison : annulation possible tant que la commande n\'est pas expédiée',
    aide.texte.includes('commande simulée non expédiée peut être annulée'));

  console.log(`\n${échecs === 0 ? '✅' : '❌'} CGV et rétractation : ${échecs === 0 ? 'toutes les vérifications réussies' : échecs + ' vérification(s) en échec'}\n`);
  if (serveur) {
    serveur.kill();
  }
  process.exit(échecs === 0 ? 0 : 1);
}

aller().catch(async (error) => {
  console.error('❌ Erreur E2E CGV et rétractation :', error.message);
  if (serveur) {
    serveur.kill();
  }
  process.exit(1);
});
