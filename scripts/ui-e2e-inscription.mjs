// MiniShop — E2E UI « Créer un compte : erreur = tout retaper ? »
// (EF-VIS-06, RB-01).
//
// Signalement : après une erreur d'inscription (confirmation de
// mot de passe différente), tous les champs étaient vidés et la
// case CGV décochée — il fallait tout retaper ; et le site
// n'affichait qu'une erreur à la fois (l'email déjà utilisé
// n'était signalé qu'à l'envoi suivant). En vrai POST : nom,
// prénom et email repartaient bien, mais la case CGV perdait son
// état, et l'unicité n'était contrôlée qu'à la création du compte
// — jamais en même temps qu'une autre erreur.
//
// Vérifié ici en vrai parcours navigateur (DOM + fetch, cookies
// faits main) :
//  - les étoiles des champs obligatoires (case CGV comprise) ;
//  - un envoi fautif (confirmation différente + email de
//    démonstration déjà utilisé + case CGV cochée) : LES DEUX
//    erreurs s'affichent ensemble ; nom, prénom et email sont
//    conservés ; seuls les mots de passe sont vidés ; la case
//    CGV reste cochée ;
//  - correction de la seule confirmation : il ne reste que
//    l'erreur d'email — aucun va-et-vient supplémentaire ;
//  - email libre : le compte est créé et la session s'ouvre.
//
// Local :  démarre le serveur PHP-WASM sur le port 8138.
// CI :     UI_BASE_URL pointe sur `php -S` (PHP natif).
// Usage :  node scripts/ui-e2e-inscription.mjs
//          npm run test:ui-inscription

import { spawn, spawnSync } from 'node:child_process';
import { JSDOM } from 'jsdom';

const HOTE = process.env.UI_HOTE ?? '127.0.0.1';
const PORT = 8138;
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
      const dom = new JSDOM(html, { url });
      this.page = { dom, url, statut: res.status };
      dom.window.document.addEventListener('submit', (evenement) => {
        evenement.preventDefault();
        this.soumission = { formulaire: evenement.target, donnees: new dom.window.FormData(evenement.target) };
      }, true);
      return this.page;
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
    const res = await fetch(url, {
      method: 'POST',
      headers: this.enTetes({ 'content-type': 'application/x-www-form-urlencoded' }),
      body: new URLSearchParams([...donnees.entries()].map(([k, v]) => [k, String(v)])).toString(),
      redirect: 'manual',
    });
    this.memorise(res);
    if (res.status >= 300 && res.status < 400 && res.headers.get('location')) {
      await res.arrayBuffer();
      return this.aller(new URL(res.headers.get('location'), url).href);
    }
    // Réponse 200 (formulaire réaffiché avec erreurs) : on lit LE CORPS
    // du POST — recharger l'URL en GET masquerait erreurs et valeurs.
    const html = await res.text();
    const dom = new JSDOM(html, { url });
    this.page = { dom, url, statut: res.status };
    dom.window.document.addEventListener('submit', (evenement) => {
      evenement.preventDefault();
      this.soumission = { formulaire: evenement.target, donnees: new dom.window.FormData(evenement.target) };
    }, true);
    return this.page;
  }
}

async function demarrer() {
  if (!BASE_EXTERNE) {
    spawnSync('node', ['scripts/wasm_run.mjs', 'scripts/seed.php'], { stdio: 'inherit' });
    spawnSync('git', ['checkout', '--', 'data'], { stdio: 'ignore' });
    spawnSync('node', ['scripts/wasm_run.mjs', 'scripts/seed.php'], { stdio: 'inherit' });
    serveur = spawn('node', ['scripts/wasm-serve.mjs'], {
      env: { ...process.env, PORT: String(PORT) }, stdio: 'ignore',
    });
    for (let i = 0; i < 30; ++i) {
      try {
        const res = await fetch(base + '/inscription');
        if (res.ok || res.status === 302) return;
      } catch { /* pas encore pret */ }
      await new Promise((r) => setTimeout(r, 500));
    }
    throw new Error('le serveur local ne repond pas');
  }
  spawnSync('node', ['scripts/wasm_run.mjs', 'scripts/seed.php'], { stdio: 'inherit' });
}

async function principal() {
  await demarrer();

  console.log('\n== Créer un compte : étoiles des champs obligatoires ==');
  const visiteur = new NavigateurUI();
  await visiteur.aller('/inscription');
  const libelle = (id) => visiteur.doc.querySelector(`label[for="${id}"]`)?.textContent.trim() ?? '';
  verifier('Nom, Prénom, Email, Mot de passe et Confirmation portent leur étoile',
    libelle('nom').endsWith('*') && libelle('prenom').endsWith('*')
      && libelle('email').endsWith('*') && libelle('mot_de_passe').includes('*')
      && libelle('confirmation').endsWith('*'),
    [libelle('nom'), libelle('prenom'), libelle('email'), libelle('mot_de_passe'), libelle('confirmation')].join(' | '));
  const libelleCgv = [...visiteur.doc.querySelectorAll('label')].find((l) => l.textContent.includes('conditions générales'))?.textContent.trim() ?? '';
  verifier('la case d\u2019acceptation des CGV (obligatoire) porte aussi son étoile',
    libelleCgv.endsWith('*'), libelleCgv);

  console.log('\n== Envoi fautif : les deux erreurs d\u2019un coup, la saisie conservée ==');
  visiteur.doc.querySelector('#nom').value = 'Durand';
  visiteur.doc.querySelector('#prenom').value = 'Zoe';
  visiteur.doc.querySelector('#email').value = 'alice@example.com'; // déjà utilisé (compte de démonstration)
  visiteur.doc.querySelector('#mot_de_passe').value = 'MotDePasse1!';
  visiteur.doc.querySelector('#confirmation').value = 'AutreMotDePasse2!';
  visiteur.doc.querySelector('input[name="cgv"]').checked = true;
  await visiteur.cliquerBouton(visiteur.doc.querySelector('form[action="/inscription"] button'));
  const erreurs = [...visiteur.doc.querySelectorAll('ul[role="alert"] li')].map((li) => li.textContent.trim());
  verifier('l\u2019erreur de confirmation ET l\u2019email déjà utilisé s\u2019affichent ensemble, dès le premier envoi',
    erreurs.some((e) => e.includes('confirmation')) && erreurs.some((e) => e.includes('déjà utilisée')),
    erreurs.join(' | '));
  verifier('nom, prénom et email sont conservés (pas tout à retaper)',
    visiteur.doc.querySelector('#nom')?.value === 'Durand'
      && visiteur.doc.querySelector('#prenom')?.value === 'Zoe'
      && visiteur.doc.querySelector('#email')?.value === 'alice@example.com');
  verifier('seuls les mots de passe sont vidés',
    visiteur.doc.querySelector('#mot_de_passe')?.value === ''
      && visiteur.doc.querySelector('#confirmation')?.value === '');
  verifier('la case CGV reste cochée après l\u2019erreur',
    visiteur.doc.querySelector('input[name="cgv"]')?.checked === true);

  console.log('\n== Correction progressive : plus de va-et-vient ==');
  visiteur.doc.querySelector('#mot_de_passe').value = 'MotDePasse1!';
  visiteur.doc.querySelector('#confirmation').value = 'MotDePasse1!'; // confirmation réparée
  await visiteur.cliquerBouton(visiteur.doc.querySelector('form[action="/inscription"] button'));
  const erreurs2 = [...visiteur.doc.querySelectorAll('ul[role="alert"] li')].map((li) => li.textContent.trim());
  verifier('confirmation réparée : seule l\u2019erreur d\u2019email subsiste, annoncée dès le départ',
    erreurs2.length === 1 && erreurs2[0].includes('déjà utilisée'),
    erreurs2.join(' | '));
  verifier('la saisie est toujours là après ce second envoi',
    visiteur.doc.querySelector('#email')?.value === 'alice@example.com'
      && visiteur.doc.querySelector('input[name="cgv"]')?.checked === true);

  visiteur.doc.querySelector('#email').value = 'zoe.nouvelle@example.com';
  visiteur.doc.querySelector('#mot_de_passe').value = 'MotDePasse1!';
  visiteur.doc.querySelector('#confirmation').value = 'MotDePasse1!';
  await visiteur.cliquerBouton(visiteur.doc.querySelector('form[action="/inscription"] button'));
  const texteFinal = visiteur.doc.body.textContent.replace(/\s+/g, ' ');
  verifier('email libre : le compte est créé et la session s\u2019ouvre',
    texteFinal.includes('Bienvenue') || texteFinal.includes('Zoe'),
    texteFinal.slice(0, 200));

  console.log(`\nTOTAL UI INSCRIPTION : ${total - echecs}/${total} OK`);
  if (echecs > 0) process.exitCode = 1;
}

principal()
  .catch((erreur) => { console.error(erreur); process.exitCode = 1; })
  .finally(() => {
    if (serveur) serveur.kill();
    if (!BASE_EXTERNE) spawnSync('git', ['checkout', '--', 'data']);
  });
