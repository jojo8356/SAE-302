#!/usr/bin/env node
/**
 * MiniShop — E2E « Valider ma commande à 375 px » (port 8134 ; UI_BASE_URL en CI).
 *
 * Signalement « important » : dans le récapitulatif de validation, on
 * ne voyait jamais en même temps le libellé et le montant — les
 * libellés du pied (Sous-total, Frais de port, Total à payer) sans
 * les montants, puis les montants sans les libellés après défilement
 * horizontal du tableau. Le client pouvait confirmer et payer sans
 * voir clairement le total. (Le texte sous le bouton ne contient
 * plus RB-18/RB-06/RB-20 depuis le signalement n° 18.)
 *
 * Vérifié ici en vrai parcours navigateur (jsdom) :
 *   1. parcours d'achat réel jusqu'à /commande/valider ;
 *   2. le récapitulatif est le tableau dédié (.tableau-recapitulatif,
 *      colgroup de 4 colonnes), chaque ligne du pied porte son
 *      libellé ET son montant dans la même ligne ;
 *   3. la feuille servie tient le tableau en page (100 %, répartition
 *      fixe totalisant 100 %) : pas de défilement horizontal ;
 *   4. le bouton de confirmation affiche le total à payer ;
 *   5. aucun code technique sur la page.
 */

import { spawn, spawnSync } from 'node:child_process';
import { JSDOM } from 'jsdom';

// Ces parcours modifient le serveur ciblé : jamais les lancer implicitement sur les données locales.
if (!process.env.UI_BASE_URL || process.env.UI_TEST_INSTANCE_JETABLE !== '1') {
  console.error('UI_BASE_URL et UI_TEST_INSTANCE_JETABLE=1 requis : utiliser uniquement une instance de test jetable. Ces tests modifient ses données.');
  process.exit(2);
}

const PORT = 8134;
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

  console.log("\n[Valider 375 px] 1 · Parcours réel jusqu'à la page Valider ma commande");
  const navigateur = new NavigateurUI();
  await navigateur.aller('/catalogue');
  const liens = [...navigateur.doc.querySelectorAll('a[href^="/produit/"]')];
  const lienFiche = liens.find((a) => a.textContent.includes('Câble')) ?? liens[0];
  await navigateur.aller(lienFiche.getAttribute('href'));
  await navigateur.cliquerBouton(navigateur.doc.querySelector('form[action="/panier/ajouter"] button'));
  await navigateur.aller('/inscription');
  navigateur.remplir('#nom', 'Dupont');
  navigateur.remplir('#prenom', 'Valide');
  navigateur.remplir('#email', 'valider@example.com');
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
  check('la page Valider est atteinte avec son récapitulatif',
    navigateur.texte.includes('Récapitulatif de votre panier'));

  console.log("\n[Valider 375 px] 2 · Libellés et montants ensemble, sans défilement");
  const tableau = navigateur.doc.querySelector('table.tableau-recapitulatif');
  check('le récapitulatif est le tableau dédié (.tableau-recapitulatif)', tableau !== null);
  const cols = tableau ? [...tableau.querySelectorAll('colgroup col')].map((c) => c.className) : [];
  check('le colgroup fige les 4 colonnes (produit, prix, qté, total)',
    cols.join(',') === 'col-produit,col-prix,col-qte,col-total', cols.join(','));
  const lignesPied = tableau ? [...tableau.querySelectorAll('tfoot tr')] : [];
  const paires = [['Sous-total', 'sous-total'], ['Frais de port', 'port'], ['Total simulé', 'total']];
  const montants = {};
  for (const [libellé] of paires) {
    const ligne = lignesPied.find((tr) => tr.querySelector('th')?.textContent.includes(libellé));
    const montant = ligne?.querySelector('td')?.textContent.trim() ?? '';
    montants[libellé] = montant;
    check(`la ligne « ${libellé} » affiche son montant dans la même ligne (${montant})`,
      /\d/.test(montant));
  }
  const enEuros = (s) => Number.parseFloat(s.replace(/[^0-9,]/g, '').replace(',', '.'));
  check('les montants lus se recomposent (sous-total + port = total à payer)',
    Math.abs(enEuros(montants['Sous-total']) + enEuros(montants['Frais de port']) - enEuros(montants['Total simulé'])) < 0.005,
    JSON.stringify(montants));

  console.log("\n[Valider 375 px] 3 · La feuille servie tient le tableau en page");
  const cssResponse = await fetch(`${base}/css/app.css`);
  const css = await cssResponse.text();
  check('la feuille réellement chargée prévoit les cartes mobiles', cssResponse.ok
    && css.includes('.tableau-defilant--client table')
    && css.includes('min-width: 0') && css.includes('width: 100%'));
  check('les libellés de colonnes sont repris dans les cartes et les totaux restent ensemble',
    css.includes('content: attr(data-label)')
      && css.includes('.tableau-recapitulatif tfoot tr')
      && css.includes('justify-content: space-between'));

  console.log("\n[Valider 375 px] 4 · Le total est sous les yeux au moment de confirmer");
  const bouton = navigateur.doc.querySelector('form[action="/commande/valider"] button[type="submit"], form[action="/commande/valider"] button');
  check('le bouton de confirmation affiche le total à payer',
    bouton !== null && bouton.textContent.includes(montants['Total simulé']),
    bouton?.textContent.trim());
  check('aucun code technique sur la page (RB-18/RB-06/RB-20 ont disparu au signalement n° 18)',
    !/RB-\d|EF-[A-Z]|ENF-\d|UC-\d/.test(navigateur.texte));

  console.log(`\n${échecs === 0 ? '✅' : '❌'} Valider à 375 px : ${échecs === 0 ? 'toutes les vérifications réussies' : échecs + ' vérification(s) en échec'}\n`);
  if (serveur) {
    serveur.kill();
  }
  process.exit(échecs === 0 ? 0 : 1);
}

aller().catch(async (error) => {
  console.error('❌ Erreur E2E valider 375 px :', error.message);
  if (serveur) {
    serveur.kill();
  }
  process.exit(1);
});
