#!/usr/bin/env node
/**
 * MiniShop — E2E « Annulation : historique conservé » (port 8132 ; UI_BASE_URL en CI).
 *
 * Signalement : après une annulation, la commande affichait 0 article
 * et 0,00 € — le client ne voyait plus ce qu'il avait commandé (les
 * lignes étaient supprimées pour restituer le stock, et la formule
 * RB-15 soldait tout). Le 0,00 € est conforme à RB-20 ; l'historique,
 * lui, doit rester.
 *
 * Vérifié ici en vrai parcours navigateur (jsdom) :
 *   1. commande passée et confirmée : le détail montre les articles ;
 *   2. annulation réelle (bouton → confirmation → exécution) ;
 *   3. le détail rouvert montre toujours les articles et leurs prix,
 *      le « Total de la commande avant annulation », puis le
 *      « Montant soldé (annulée — rien à payer) » ;
 *   4. « Mes commandes » recompte les articles de la commande annulée.
 */

import { spawn, spawnSync } from 'node:child_process';
import { JSDOM } from 'jsdom';

const PORT = 8132;
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
    spawnSync('node', ['scripts/wasm_run.mjs', 'scripts/seed.php'], { stdio: 'inherit' });
    serveur = spawn('node', ['scripts/wasm-serve.mjs'], { env: { ...process.env, PORT: String(PORT) }, stdio: 'inherit' });
    await attendreServeur();
  }

  console.log("\n[Annulation] 1 · Une commande réelle, son détail avant annulation");
  const navigateur = new NavigateurUI();
  await navigateur.aller('/catalogue');
  const liens = [...navigateur.doc.querySelectorAll('a[href^="/produit/"]')];
  let lienFiche = liens.find((a) => a.textContent.includes('Câble')) ?? liens[0];
  const nomProduit = lienFiche.textContent.trim();
  await navigateur.aller(lienFiche.getAttribute('href'));
  await navigateur.cliquerBouton(navigateur.doc.querySelector('form[action="/panier/ajouter"] button'));
  await navigateur.aller('/inscription');
  navigateur.remplir('#nom', 'Dupont');
  navigateur.remplir('#prenom', 'Historique');
  navigateur.remplir('#email', 'annulation@example.com');
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
  check('avant annulation : le détail montre l’article commandé',
    navigateur.texte.includes(nomProduit) && navigateur.texte.includes('Montant total TTC'));

  console.log("\n[Annulation] 2 · Annulation réelle, puis relecture du détail");
  await navigateur.cliquerBouton(navigateur.doc.querySelector('button.bouton-danger'));
  check("l'écran de confirmation annonce la restitution du stock et le solde",
    navigateur.texte.includes('stock des articles sera restitué'));
  const boutonConfirmer = [...navigateur.doc.querySelectorAll('button')].find((b) => b.textContent.includes('Oui, annuler'));
  await navigateur.cliquerBouton(boutonConfirmer);
  // l'annulation renvoie à la liste : on rouvre le détail de la commande
  const lienDetail = [...navigateur.doc.querySelectorAll('a[href^="/mes-commandes/"]')]
    .find((a) => /\/mes-commandes\/\d+$/.test(a.getAttribute('href')));
  await navigateur.aller(lienDetail.getAttribute('href'));
  check('après annulation : le détail rouvert montre toujours l’article commandé',
    navigateur.texte.includes(nomProduit));
  check('le total d’origine est affiché (« Total de la commande avant annulation »)',
    navigateur.texte.includes('Total de la commande avant annulation'));
  check('le montant soldé est explicite (« Montant soldé (annulée — rien à payer) »)',
    navigateur.texte.includes('Montant soldé (annulée — rien à payer)'));
  check('le statut lu est bien « Annulée »',
    navigateur.texte.includes('Annulée'));

  console.log("\n[Annulation] 3 · Mes commandes recompte les articles");
  await navigateur.aller('/mes-commandes');
  const ligneAnnulee = [...navigateur.doc.querySelectorAll('tbody tr')].find((tr) => tr.textContent.includes('Annulée'));
  check('la ligne de la commande annulée existe dans la liste', ligneAnnulee !== undefined);
  const cellules = ligneAnnulee ? [...ligneAnnulee.querySelectorAll('td')].map((td) => td.textContent.trim()) : [];
  check('la colonne Articles ne dit plus 0 (1 article commandé)',
    cellules.includes('1'), cellules.join(' | '));

  console.log(`\n${échecs === 0 ? '✅' : '❌'} Annulation historique : ${échecs === 0 ? 'toutes les vérifications réussies' : échecs + ' vérification(s) en échec'}\n`);
  if (serveur) {
    serveur.kill();
    spawnSync('git', ['checkout', '--', 'data'], { stdio: 'inherit' });
  }
  process.exit(échecs === 0 ? 0 : 1);
}

aller().catch(async (error) => {
  console.error('❌ Erreur E2E annulation historique :', error.message);
  if (serveur) {
    serveur.kill();
    spawnSync('git', ['checkout', '--', 'data'], { stdio: 'inherit' });
  }
  process.exit(1);
});
