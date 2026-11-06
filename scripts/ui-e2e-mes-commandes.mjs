// MiniShop — E2E UI « Mes commandes (375 px) : montant et Détail
// cachés à droite, légende coupée » (EF-CLI-08, UC-08).
//
// Signalement : sur « Mes commandes » à 375 px, le tableau
// défilait et le montant comme le lien « Détail » restaient
// cachés à droite ; la légende était coupée au lieu de passer à
// la ligne. Cause vérifiée sur la page servie : ce tableau
// était le dernier du parcours SANS aucune règle dédiée — pas
// même une classe — donc dimensionné à son contenu (numéro,
// date complète avec l'heure), colonnes de droite hors écran,
// légende aussi large que le tableau.
//
// Vérifié ici en vrai parcours navigateur (DOM + fetch, cookies
// faits main) : achat complet (inscription, panier, adresse,
// validation) puis « Mes commandes » — le tableau dédié et son
// colgroup, la légende entière, le montant et le lien « Détail »
// de la commande passée, et la feuille servie (100 % fixe,
// colonnes totalisant 100 %, légende qui passe à la ligne,
// montants à droite sur une ligne).
//
// Local :  démarre le serveur PHP-WASM sur le port 8140.
// CI :     UI_BASE_URL pointe sur `php -S` (PHP natif).
// Usage :  node scripts/ui-e2e-mes-commandes.mjs
//          npm run test:ui-mes-commandes

import { spawn, spawnSync } from 'node:child_process';
import { JSDOM } from 'jsdom';

const HOTE = process.env.UI_HOTE ?? '127.0.0.1';
const PORT = 8140;
const BASE_EXTERNE = process.env.UI_BASE_URL;
const base = BASE_EXTERNE ?? `http://${HOTE}:${PORT}`;

let serveur = null;
let echecs = 0;
let total = 0;
function verifier(libelle, condition, precision = '') {
  ++total;
  if (condition) {
    console.log(`  OK  ${libelle}`);
  } else {
    ++echecs;
    console.log(`  ECHEC  ${libelle}${precision ? ` -- ${precision}` : ''}`);
  }
}

/** Navigateur minimal : cookies faits main (jsdom rejette Domain=127.0.0.1). */
class NavigateurUI {
  constructor() {
    this.cookies = {};
    this.page = null;
    this.soumission = null;
  }

  get dom() { return this.page.dom; }
  get doc() { return this.page.dom.window.document; }
  get url() { return this.page.url; }

  enTetes(extra = {}) {
    const cookie = Object.entries(this.cookies).map(([k, v]) => `${k}=${v}`).join('; ');
    return cookie ? { cookie, ...extra } : { ...extra };
  }

  memorise(res) {
    for (const sc of res.headers.getSetCookie()) {
      const m = /^([^=]+)=([^;]*)/.exec(sc);
      if (m) this.cookies[m[1]] = m[2];
    }
  }

  posePage(html, url, statut) {
    const dom = new JSDOM(html, { url });
    this.page = { dom, url, statut };
    dom.window.document.addEventListener('submit', (evenement) => {
      evenement.preventDefault();
      this.soumission = { formulaire: evenement.target, donnees: new dom.window.FormData(evenement.target) };
    }, true);
    return this.page;
  }

  async aller(chemin) {
    let url = new URL(chemin, base).href;
    for (let i = 0; i < 6; ++i) {
      const res = await fetch(url, { headers: this.enTetes(), redirect: 'manual' });
      this.memorise(res);
      const html = await res.text();
      if (res.status >= 300 && res.status < 400 && res.headers.get('location')) {
        url = new URL(res.headers.get('location'), url).href;
        continue;
      }
      return this.posePage(html, url, res.status);
    }
    throw new Error(`trop de redirections vers ${chemin}`);
  }

  async cliquerBouton(bouton) {
    if (!bouton) throw new Error('bouton introuvable');
    this.soumission = null;
    bouton.click();
    if (!this.soumission) {
      const formulaire = bouton.closest('form');
      if (!formulaire) throw new Error('bouton hors formulaire');
      formulaire.requestSubmit(bouton);
    }
    if (!this.soumission) throw new Error('aucune soumission capturee');
    const { formulaire, donnees } = this.soumission;
    const url = new URL(formulaire.getAttribute('action'), this.url).href;
    const corps = new URLSearchParams();
    for (const [k, v] of donnees.entries()) corps.append(k, String(v));
    const res = await fetch(url, {
      method: 'POST',
      headers: this.enTetes({ 'content-type': 'application/x-www-form-urlencoded' }),
      body: corps.toString(),
      redirect: 'manual',
    });
    this.memorise(res);
    if (res.status >= 300 && res.status < 400 && res.headers.get('location')) {
      await res.arrayBuffer();
      return this.aller(new URL(res.headers.get('location'), url).href);
    }
    return this.posePage(await res.text(), url, res.status);
  }

  remplir(selecteur, valeur) {
    const champ = this.doc.querySelector(selecteur);
    if (!champ) throw new Error(`champ introuvable : ${selecteur}`);
    champ.value = valeur;
  }
}

async function demarrer() {
  spawnSync('node', ['scripts/wasm_run.mjs', 'scripts/seed.php'], { stdio: 'inherit' });
  if (!BASE_EXTERNE) {
    serveur = spawn('node', ['scripts/wasm-serve.mjs'], {
      env: { ...process.env, PORT: String(PORT) }, stdio: 'ignore',
    });
    for (let i = 0; i < 30; ++i) {
      try {
        const res = await fetch(base + '/catalogue');
        if (res.ok || res.status === 302) return;
      } catch { /* pas encore pret */ }
      await new Promise((r) => setTimeout(r, 500));
    }
    throw new Error('le serveur local ne repond pas');
  }
}

async function principal() {
  await demarrer();

  console.log('\n== Achat complet, puis « Mes commandes » ==');
  const client = new NavigateurUI();
  await client.aller('/catalogue');
  const liens = [...client.doc.querySelectorAll('a')]
    .map((a) => a.getAttribute('href'))
    .filter((h) => h && h.startsWith('/produit/'));
  for (const href of liens) {
    await client.aller(href);
    if (client.doc.querySelector('form[action="/panier/ajouter"] button')) break;
  }
  await client.cliquerBouton(client.doc.querySelector('form[action="/panier/ajouter"] button'));
  await client.aller('/inscription');
  client.remplir('#nom', 'Mesure');
  client.remplir('#prenom', 'Commandes');
  client.remplir('#email', 'mes-commandes@example.com');
  client.remplir('#mot_de_passe', 'MotDePasse1!');
  client.remplir('#confirmation', 'MotDePasse1!');
  client.doc.querySelector('input[name="cgv"]').checked = true;
  await client.cliquerBouton(client.doc.querySelector('form[action="/inscription"] button'));
  await client.aller('/compte');
  client.remplir('#adresse', '12 rue de France');
  client.remplir('#code_postal', '06000');
  client.remplir('#ville', 'Nice');
  await client.cliquerBouton(client.doc.querySelector('form[action="/compte"] button'));
  await client.aller('/commande/valider');
  await client.cliquerBouton(client.doc.querySelector('form[action="/commande/valider"] button'));
  verifier('la commande est confirmée (détail de la commande atteint)',
    /\/mes-commandes\/\d+$/.test(new URL(client.url).pathname), client.url);

  console.log('\n== Le tableau tient en page : montant et Détail visibles ==');
  await client.aller('/mes-commandes');
  const tableau = client.doc.querySelector('table.tableau-mes-commandes');
  verifier('« Mes commandes » utilise le tableau dédié (.tableau-mes-commandes)', tableau !== null);
  verifier('la légende est là en entier, sans code technique',
    tableau?.querySelector('caption')?.textContent.trim() === "Historique de vos commandes (prix figés à l'achat)"
      && !/RB-\d/.test(client.doc.body.textContent),
    tableau?.querySelector('caption')?.textContent.trim());
  const cols = tableau ? [...tableau.querySelectorAll('colgroup col')].map((c) => c.className) : [];
  verifier('le colgroup fige les 6 colonnes (numero, date, statut, articles, montant, detail)',
    cols.join(',') === 'col-numero,col-date,col-statut,col-articles,col-montant,col-detail',
    cols.join(','));
  const enteteMontant = [...(tableau?.querySelectorAll('th') ?? [])].find((th) => th.textContent.includes('Montant'));
  verifier('l\u2019en-tête « Montant TTC » est aligné avec ses montants (classe montant)',
    enteteMontant?.classList.contains('montant') === true);
  const ligne = tableau?.querySelector('tbody tr');
  const cellules = ligne ? [...ligne.querySelectorAll('td')] : [];
  verifier('la commande passée est listée (numéro et date complète)',
    /^CMD\d{4}-\d{6}$/.test(cellules[0]?.textContent.trim() ?? '')
      && /\d{2}\/\d{2}\/\d{4} \d{2}h\d{2}/.test(cellules[1]?.textContent.trim() ?? ''),
    cellules.map((td) => td.textContent.trim()).join(' | '));
  const celluleMontant = ligne?.querySelector('td.montant');
  verifier('le montant est dans sa colonne, formaté en euros',
    celluleMontant !== null && celluleMontant !== undefined && /^\d[\d\s]*,\d{2} €$/.test(celluleMontant.textContent.trim()),
    celluleMontant?.textContent.trim());
  const lienDetail = cellules[5]?.querySelector('a');
  verifier('le lien « Détail → » est présent dans la dernière colonne',
    lienDetail !== null && lienDetail !== undefined
      && /^\/mes-commandes\/\d+$/.test(lienDetail.getAttribute('href') ?? '')
      && lienDetail.textContent.includes('Détail'),
    cellules[5]?.textContent.trim());

  console.log('\n== La feuille servie tient le tableau en page ==');
  const css = await (await fetch(base + '/minishop.css')).text();
  const bloc = (selecteur) => {
    const i = css.indexOf(selecteur + ' {');
    return i === -1 ? '' : css.slice(i, css.indexOf('}', i));
  };
  verifier('CSS servie : largeur 100 % et répartition fixe des colonnes',
    bloc('.tableau-mes-commandes').includes('width: 100%')
      && bloc('.tableau-mes-commandes').includes('table-layout: fixed'));
  const somme = ['col-numero', 'col-date', 'col-statut', 'col-articles', 'col-montant', 'col-detail']
    .map((c) => {
      const m = /width:\s*(\d+)%/.exec(bloc(`.tableau-mes-commandes .${c}`));
      return m ? Number.parseInt(m[1], 10) : 0;
    })
    .reduce((a, b) => a + b, 0);
  verifier('CSS servie : les largeurs de colonnes totalisent 100 % (rien hors écran)',
    somme === 100, String(somme));
  verifier('CSS servie : la légende peut passer à la ligne (aucun nowrap forcé)',
    bloc('.tableau-mes-commandes caption').includes('text-align: left')
      && !bloc('.tableau-mes-commandes caption').includes('nowrap'));
  verifier('CSS servie : montants à droite, sur une ligne',
    bloc('.tableau-mes-commandes .montant').includes('text-align: right')
      && bloc('.tableau-mes-commandes .montant').includes('white-space: nowrap'));

  console.log(`\nTOTAL UI MES COMMANDES : ${total - echecs}/${total} OK`);
  if (echecs > 0) process.exitCode = 1;
}

principal()
  .catch((erreur) => { console.error(erreur); process.exitCode = 1; })
  .finally(() => {
    if (serveur) serveur.kill();
    if (!BASE_EXTERNE) spawnSync('git', ['checkout', '--', 'data']);
  });
