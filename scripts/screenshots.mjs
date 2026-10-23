#!/usr/bin/env node
/**
 * MiniShop — captures d'écran responsive automatisées + audit de débordement.
 * ---------------------------------------------------------------------------
 * Ce script fait trois choses, dans cet ordre :
 *   1. il ouvre chaque page de l'application (visiteur, client connecté, admin)
 *      à plusieurs largeurs d'écran ;
 *   2. il enregistre une capture PLEINE PAGE (fullPage) par couple page × largeur ;
 *   3. il mesure, pour chaque capture, si la page déborde horizontalement et
 *      il nomme l'élément fautif (c'est la vérification que fait un audit
 *      WCAG 1.4.10 « Reflow » à 320 px).
 *
 * Sorties (par défaut dans ./captures) :
 *   captures/<page>/<largeur>.png     les captures
 *   captures/index.html               planche contact (galerie navigable)
 *   captures/rapport.md               rapport lisible (tableau + verdicts)
 *   captures/pdf/*.pdf                si --pdf (feuilles d'impression)
 *
 * Moteur de rendu : Puppeteer (Chromium embarqué) ou Playwright, au choix.
 * Aucun navigateur n'est fourni par ce dépôt : voir « Installation » plus bas.
 *
 * Usage détaillé : node scripts/screenshots.mjs --help
 */

import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const RACINE = path.resolve(import.meta.dirname, '..');

/** Largeurs de référence (nom = suffixe lisible dans le rapport). */
const LARGEURS_REFERENCE = [
  { px: 320, nom: 'mobile-se' },
  { px: 375, nom: 'mobile-13-mini' },
  { px: 390, nom: 'mobile-14' },
  { px: 414, nom: 'mobile-plus' },
  { px: 768, nom: 'tablette' },
  { px: 1024, nom: 'tablette-paysage' },
  { px: 1280, nom: 'portable' },
  { px: 1440, nom: 'desktop' },
];

const LARGEURS_DEFAUT = [320, 390, 768, 1024, 1440];

/** Identifiants du jeu de démonstration (README §Comptes). */
const COMPTES = {
  client: {
    email: process.env.MINISHOP_CLIENT_EMAIL ?? 'alice@example.com',
    motDePasse: process.env.MINISHOP_CLIENT_PASSWORD ?? 'Demo2026!',
    connexion: '/connexion',
  },
  admin: {
    email: process.env.MINISHOP_ADMIN_EMAIL ?? 'admin@minishop.fr',
    motDePasse: process.env.MINISHOP_ADMIN_PASSWORD ?? 'Admin2026!',
    connexion: '/admin/connexion',
  },
};

/**
 * Pages capturées. `session` détermine le contexte de navigation :
 *   public  → visiteur anonyme
 *   client  → après connexion alice@example.com
 *   admin   → après connexion admin@minishop.fr
 * Les jetons {slug}, {idCommande} et {idClient} sont découverts à l'exécution.
 */
const PAGES = [
  { cle: 'accueil', titre: 'Accueil', chemin: '/', session: 'public' },
  { cle: 'catalogue', titre: 'Catalogue', chemin: '/catalogue', session: 'public' },
  { cle: 'catalogue-filtre', titre: 'Catalogue filtré', chemin: '/catalogue?q=casque&tri=prix_asc&stock=1', session: 'public' },
  { cle: 'catalogue-page-2', titre: 'Catalogue page 2', chemin: '/catalogue?page=2', session: 'public' },
  { cle: 'recherche', titre: 'Recherche', chemin: '/recherche?q=usb', session: 'public' },
  { cle: 'produit', titre: 'Fiche produit', chemin: '/produit/{slug}', session: 'public' },
  { cle: 'categories', titre: 'Catégories', chemin: '/categories', session: 'public' },
  { cle: 'contact', titre: 'Contact', chemin: '/contact', session: 'public' },
  { cle: 'contact-erreurs', titre: 'Contact (erreurs)', chemin: '/contact', session: 'public', prep: 'erreurs-contact' },
  { cle: 'aide', titre: 'Aide', chemin: '/aide', session: 'public' },
  { cle: 'cgv', titre: 'CGV', chemin: '/cgv', session: 'public' },
  { cle: 'mentions', titre: 'Mentions légales', chemin: '/mentions', session: 'public' },
  { cle: 'connexion', titre: 'Connexion client', chemin: '/connexion', session: 'public' },
  { cle: 'inscription', titre: 'Inscription', chemin: '/inscription', session: 'public' },
  { cle: 'panier-vide', titre: 'Panier vide', chemin: '/panier', session: 'public' },
  { cle: 'page-404', titre: 'Page 404', chemin: '/cette-page-nexiste-pas', session: 'public' },
  { cle: 'admin-connexion', titre: 'Connexion back-office', chemin: '/admin/connexion', session: 'public' },
  { cle: 'compte', titre: 'Mon compte', chemin: '/compte', session: 'client' },
  { cle: 'mes-commandes', titre: 'Mes commandes', chemin: '/mes-commandes', session: 'client' },
  { cle: 'commande-client', titre: 'Détail commande (client)', chemin: '/mes-commandes/{idCommande}', session: 'client' },
  { cle: 'panier-rempli', titre: 'Panier rempli', chemin: '/panier', session: 'client', prep: 'panier' },
  { cle: 'validation', titre: 'Validation de commande', chemin: '/commande/valider', session: 'client' },
  { cle: 'admin-tableau-de-bord', titre: 'Back-office — tableau de bord', chemin: '/admin', session: 'admin' },
  { cle: 'admin-produits', titre: 'Back-office — produits', chemin: '/admin/produits', session: 'admin' },
  { cle: 'admin-produit-nouveau', titre: 'Back-office — nouveau produit', chemin: '/admin/produit/nouveau', session: 'admin' },
  { cle: 'admin-stocks', titre: 'Back-office — stocks', chemin: '/admin/stocks', session: 'admin' },
  { cle: 'admin-stocks-alerte', titre: 'Back-office — stocks en alerte', chemin: '/admin/stocks?etat=RUPTURE', session: 'admin' },
  { cle: 'admin-commandes', titre: 'Back-office — commandes', chemin: '/admin/commandes', session: 'admin' },
  { cle: 'admin-commande', titre: 'Back-office — détail commande', chemin: '/admin/commandes/{idCommandeAdmin}', session: 'admin' },
  { cle: 'admin-parametres', titre: 'Back-office — paramètres', chemin: '/admin/parametres', session: 'admin' },
  { cle: 'admin-equipe', titre: 'Back-office — équipe', chemin: '/admin/equipe', session: 'admin' },
  { cle: 'admin-categories', titre: 'Back-office — catégories', chemin: '/admin/categories', session: 'admin' },
];

// ---------------------------------------------------------------------------
//  Arguments
// ---------------------------------------------------------------------------

const AIDE = `
MiniShop — captures d'écran responsive

  node scripts/screenshots.mjs [options]

Options
  --url <url>            URL de l'application (défaut : $MINISHOP_URL ou
                         http://127.0.0.1:8000)
  --out <dossier>        Dossier de sortie (défaut : captures)
  --widths 320,390,...   Largeurs en pixels (défaut : ${LARGEURS_DEFAUT.join(', ')})
  --all-widths           Les 8 largeurs de référence (320 → 1440)
  --only a,b             Ne capturer que certaines pages (clés du script)
  --serve                Démarre « php -S » sur le port de l'URL
  --no-server            N'essaie jamais de démarrer un serveur
  --channel chrome       Utilise le Chrome installé (Puppeteer) au lieu du
                         Chromium téléchargé par Puppeteer
  --browser <chemin>     Chemin d'un exécutable Chrome/Chromium
  --pdf                  Ajoute les PDF d'impression (/cgv, /mes-commandes)
  --no-js                Ajoute une capture « JavaScript désactivé »
  --headed               Affiche le navigateur (débogage)
  --check                Vérifie tout sans capturer (serveur, CSS, plan)
  --no-strict            Ne pas échouer en cas de débordement horizontal
  --timeout <ms>         Délai maximum par page (défaut : 20000)
  -h, --help             Cette aide

Exemples
  npm run shots
  npm run shots -- --all-widths --pdf
  npm run shots -- --only accueil,catalogue --widths 320,768,1440
  node scripts/screenshots.mjs --url http://127.0.0.1:8080 --check
`;

function analyserArguments(argv) {
  const options = {
    url: process.env.MINISHOP_URL ?? 'http://127.0.0.1:8000',
    out: 'captures',
    largeurs: [...LARGEURS_DEFAUT],
    only: [],
    serve: false,
    noServer: false,
    channel: null,
    browser: process.env.CHROME_PATH ?? null,
    pdf: false,
    noJs: false,
    headed: false,
    check: false,
    strict: true,
    timeout: 20000,
    aide: false,
  };

  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    const suivant = () => {
      i += 1;
      if (i >= argv.length) throw new Error(`valeur manquante après ${arg}`);
      return argv[i];
    };

    if (arg === '-h' || arg === '--help') options.aide = true;
    else if (arg === '--url') options.url = suivant();
    else if (arg === '--out') options.out = suivant();
    else if (arg === '--widths') {
      options.largeurs = suivant()
        .split(',')
        .map((valeur) => Number.parseInt(valeur.trim(), 10))
        .filter((valeur) => Number.isInteger(valeur) && valeur >= 240 && valeur <= 3840);
      if (options.largeurs.length === 0) throw new Error('--widths : aucune largeur valide');
    } else if (arg === '--all-widths') options.largeurs = LARGEURS_REFERENCE.map((l) => l.px);
    else if (arg === '--only') options.only = suivant().split(',').map((v) => v.trim()).filter(Boolean);
    else if (arg === '--serve') options.serve = true;
    else if (arg === '--no-server') options.noServer = true;
    else if (arg === '--channel') options.channel = suivant();
    else if (arg === '--browser') options.browser = suivant();
    else if (arg === '--pdf') options.pdf = true;
    else if (arg === '--no-js') options.noJs = true;
    else if (arg === '--headed') options.headed = true;
    else if (arg === '--check') options.check = true;
    else if (arg === '--no-strict') options.strict = false;
    else if (arg === '--timeout') options.timeout = Number.parseInt(suivant(), 10);
    else throw new Error(`option inconnue : ${arg} (voir --help)`);
  }

  options.url = options.url.replace(/\/+$/, '');
  return options;
}

// ---------------------------------------------------------------------------
//  Utilitaires
// ---------------------------------------------------------------------------

const log = (message) => console.log(message);
const logErreur = (message) => console.error(message);

function nomLargeur(px) {
  const reference = LARGEURS_REFERENCE.find((l) => l.px === px);
  if (reference) return reference.nom;
  return `${px}px`;
}

function fichierLargeur(px) {
  return `${String(px).padStart(4, '0')}.png`;
}

function attendre(ms) {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}

async function joignable(url) {
  try {
    const controleur = new AbortController();
    const minuteur = setTimeout(() => controleur.abort(), 4000);
    const reponse = await fetch(url, { signal: controleur.signal, redirect: 'manual' });
    clearTimeout(minuteur);
    return reponse.status > 0;
  } catch {
    return false;
  }
}

function portDeUrl(url) {
  const analysee = new URL(url);
  if (analysee.port) return Number.parseInt(analysee.port, 10);
  return analysee.protocol === 'https:' ? 443 : 80;
}

/** Démarre `php -S` si possible ; retourne { arret } ou null. */
async function demarrerServeur(url) {
  const port = portDeUrl(url);
  const hote = new URL(url).hostname;
  if (!['127.0.0.1', 'localhost'].includes(hote)) {
    logErreur(`✘ --serve ne démarre un serveur que sur localhost (URL : ${url})`);
    return null;
  }

  const php = spawn('php', ['-v'], { stdio: 'ignore' });
  const phpDisponible = await new Promise((resolve) => {
    php.once('error', () => resolve(false));
    php.once('exit', (code) => resolve(code === 0));
  });
  if (!phpDisponible) {
    return null;
  }

  log(`→ démarrage de « php -S ${hote}:${port} -t public »`);
  const serveur = spawn('php', ['-S', `${hote}:${port}`, '-t', 'public'], {
    cwd: RACINE,
    stdio: ['ignore', 'ignore', 'pipe'],
  });
  serveur.stderr.on('data', (morceau) => {
    const texte = morceau.toString();
    if (texte.includes('Failed to listen') || texte.includes('Address already in use')) {
      logErreur(`   ${texte.trim()}`);
    }
  });

  for (let essai = 0; essai < 40; essai += 1) {
    await attendre(250);
    if (await joignable(`${url}/`)) {
      return {
        arret() {
          serveur.kill('SIGTERM');
        },
      };
    }
  }

  serveur.kill('SIGTERM');
  logErreur('✘ le serveur PHP n’a pas répondu dans le délai imparti');
  return null;
}

// ---------------------------------------------------------------------------
//  Moteur de rendu : abstraction Puppeteer / Playwright
// ---------------------------------------------------------------------------

class Session {
  constructor(moteur, page, type) {
    this.moteur = moteur;
    this.page = page;
    this.type = type;
  }

  async goto(chemin, attente = 'reseau') {
    const waitUntil = this.type === 'puppeteer'
      ? (attente === 'reseau' ? 'networkidle0' : 'load')
      : (attente === 'reseau' ? 'networkidle' : 'load');
    await this.page.goto(`${this.moteur.url}${chemin}`, { waitUntil, timeout: this.moteur.timeout });
  }

  async type(selecteur, valeur) {
    await this.page.type(selecteur, valeur);
  }

  async cliquer(selecteur) {
    await this.page.click(selecteur);
  }

  async attendreNavigation() {
    const waitUntil = this.type === 'puppeteer' ? 'networkidle0' : 'networkidle';
    return this.page.waitForNavigation({ waitUntil, timeout: this.moteur.timeout });
  }

  async definirViewport(largeur, hauteur = 900) {
    if (this.type === 'puppeteer') {
      await this.page.setViewport({ width: largeur, height: hauteur, deviceScaleFactor: 1 });
    } else {
      await this.page.setViewportSize({ width: largeur, height: hauteur });
    }
  }

  async html() {
    return this.page.content();
  }

  async urlCourante() {
    return this.page.url();
  }

  async capture(fichier) {
    await this.page.screenshot({ path: fichier, fullPage: true });
  }

  async pdf(fichier) {
    await this.page.pdf({ path: fichier, format: 'A4', printBackground: true });
  }

  async evaluer(fonction, argument) {
    return this.page.evaluate(fonction, argument);
  }

  async fermer() {
    if (this.type === 'puppeteer') {
      await this.page.close();
      return;
    }
    await this.page.context().close();
  }
}

class Moteur {
  constructor(url, options) {
    this.url = url;
    this.options = options;
    this.timeout = options.timeout;
    this.browser = null;
    this.type = null;
  }

  async demarrer() {
    throw new Error('non implémenté');
  }

  async nouvelleSession() {
    throw new Error('non implémenté');
  }

  async fermer() {
    if (this.browser) await this.browser.close();
  }
}

class MoteurPuppeteer extends Moteur {
  constructor(puppeteer, url, options) {
    super(url, options);
    this.puppeteer = puppeteer;
    this.type = 'puppeteer';
  }

  async demarrer() {
    const options = {
      headless: !this.options.headed,
      args: ['--no-sandbox', '--disable-dev-shm-usage', '--font-render-hinting=none'],
      defaultViewport: null,
    };
    if (this.options.browser) options.executablePath = this.options.browser;
    if (this.options.channel) options.channel = this.options.channel;
    this.browser = await this.puppeteer.launch(options);
  }

  async nouvelleSession() {
    const contexte = typeof this.browser.createBrowserContext === 'function'
      ? await this.browser.createBrowserContext()
      : await this.browser.createIncognitoBrowserContext();
    const page = await contexte.newPage();
    await page.setDefaultTimeout(this.timeout);
    return new Session(this, page, 'puppeteer');
  }
}

class MoteurPlaywright extends Moteur {
  constructor(chromium, url, options) {
    super(url, options);
    this.chromium = chromium;
    this.type = 'playwright';
  }

  async demarrer() {
    const options = { headless: !this.options.headed };
    if (this.options.browser) options.executablePath = this.options.browser;
    if (this.options.channel) options.channel = this.options.channel;
    this.browser = await this.chromium.launch(options);
  }

  async nouvelleSession() {
    const contexte = await this.browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await contexte.newPage();
    page.setDefaultTimeout(this.timeout);
    return new Session(this, page, 'playwright');
  }
}

/** Charge Puppeteer (prioritaire) puis Playwright ; null si aucun. */
async function chargerMoteur(url, options) {
  try {
    const module = await import('puppeteer');
    const puppeteer = module.default ?? module;
    const moteur = new MoteurPuppeteer(puppeteer, url, options);
    await moteur.demarrer();
    return moteur;
  } catch (erreur) {
    if (!/Cannot find package|Cannot find module/.test(String(erreur))) throw erreur;
  }

  try {
    const module = await import('playwright');
    const moteur = new MoteurPlaywright(module.chromium, url, options);
    await moteur.demarrer();
    return moteur;
  } catch (erreur) {
    if (!/Cannot find package|Cannot find module/.test(String(erreur))) throw erreur;
  }

  return null;
}

// ---------------------------------------------------------------------------
//  Découverte des identifiants (slug du produit, n° de commande)
// ---------------------------------------------------------------------------

async function decouvrirSlug(url) {
  try {
    const reponse = await fetch(`${url}/catalogue`);
    const html = await reponse.text();
    const trouve = /\/produit\/([a-z0-9][a-z0-9-]*)/.exec(html);
    return trouve ? trouve[1] : null;
  } catch {
    return null;
  }
}

// ---------------------------------------------------------------------------
//  Vérification HTTP (mode --check) : rejoue les mêmes pages SANS navigateur
// ---------------------------------------------------------------------------

/**
 * Client HTTP minimal avec jar de cookies : il suit exactement les mêmes
 * routes que les captures (connexion CSRF comprise). Objectif : valider le
 * plan de captures en 2 secondes, sans télécharger Chromium.
 */
class NavigateurHttp {
  constructor(url) {
    this.url = url;
    this.cookies = new Map();
  }

  entetes() {
    const entetes = { 'user-agent': 'MiniShop-screenshots/1.0 (verification)' };
    if (this.cookies.size > 0) {
      entetes.cookie = [...this.cookies.entries()].map(([cle, valeur]) => `${cle}=${valeur}`).join('; ');
    }
    return entetes;
  }

  memoriser(reponse) {
    const brut = reponse.headers.getSetCookie
      ? reponse.headers.getSetCookie()
      : (reponse.headers.get('set-cookie') ? [reponse.headers.get('set-cookie')] : []);
    for (const ligne of brut) {
      const trouve = /^([^=]+)=([^;]*)/.exec(ligne);
      if (trouve) this.cookies.set(trouve[1], trouve[2]);
    }
  }

  async requete(chemin, options = {}) {
    const reponse = await fetch(`${this.url}${chemin}`, {
      ...options,
      headers: { ...this.entetes(), ...(options.headers ?? {}) },
      redirect: 'manual',
    });
    this.memoriser(reponse);
    const texte = await reponse.text();
    return { statut: reponse.status, texte, entetes: reponse.headers };
  }

  async get(chemin) {
    return this.requete(chemin);
  }

  async post(chemin, donnees) {
    return this.requete(chemin, {
      method: 'POST',
      headers: { 'content-type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(donnees).toString(),
    });
  }
}

function extraireCsrf(html) {
  const trouve = /name="_csrf"\s+value="([^"]+)"/.exec(html);
  return trouve ? trouve[1] : null;
}

/** Connexion réelle (jeton CSRF + formulaire), comme le ferait un navigateur. */
async function connecterHttp(client, compte) {
  const page = await client.get(compte.connexion);
  if (page.statut !== 200) return false;
  const jeton = extraireCsrf(page.texte);
  if (!jeton) return false;
  const reponse = await client.post(compte.connexion, {
    _csrf: jeton,
    email: compte.email,
    mot_de_passe: compte.motDePasse,
  });
  return reponse.statut === 302 || reponse.statut === 303;
}

/** Vérifie chaque route du plan et retourne un bilan lisible. */
async function verifierPlan(url, pages) {
  const public_ = new NavigateurHttp(url);
  const client = new NavigateurHttp(url);
  const admin = new NavigateurHttp(url);

  const lignes = [];
  let ok = 0;

  const accueil = await public_.get('/catalogue');
  const slugTrouve = /\/produit\/([a-z0-9][a-z0-9-]*)/.exec(accueil.texte);

  const clientConnecte = await connecterHttp(client, COMPTES.client);
  let idCommande = null;
  let panierRempli = false;
  if (clientConnecte) {
    const commandes = await client.get('/mes-commandes');
    const trouve = /\/mes-commandes\/(\d+)/.exec(commandes.texte);
    if (trouve) idCommande = trouve[1];
    // produits du catalogue : on retient le premier réellement commandable
    // (stock > 0) — un produit en rupture n'affiche pas de bouton d'ajout
    const slugs = [...accueil.texte.matchAll(/\/produit\/([a-z0-9][a-z0-9-]*)/g)]
      .map((m) => m[1])
      .filter((valeur, index, liste) => liste.indexOf(valeur) === index)
      .slice(0, 6);
    for (const candidat of slugs) {
      const fiche = await client.get(`/produit/${candidat}`);
      const jeton = extraireCsrf(fiche.texte);
      const idProduit = /name="id_produit"\s+value="(\d+)"/.exec(fiche.texte);
      if (!jeton || !idProduit) continue;
      await client.post('/panier/ajouter', { _csrf: jeton, id_produit: idProduit[1], quantite: '2' });
      const panier = await client.get('/panier');
      if (/Valider ma commande/.test(panier.texte)) {
        panierRempli = true;
        break;
      }
    }
  }

  const adminConnecte = await connecterHttp(admin, COMPTES.admin);
  let idCommandeAdmin = null;
  if (adminConnecte) {
    const commandes = await admin.get('/admin/commandes');
    const trouve = /\/admin\/commandes\/(\d+)/.exec(commandes.texte);
    if (trouve) idCommandeAdmin = trouve[1];
  }

  for (const page of pages) {
    let chemin = page.chemin;
    if (chemin.includes('{slug}')) {
      if (!slugTrouve) {
        lignes.push({ cle: page.cle, statut: 'ignorée', detail: 'aucun slug produit' });
        continue;
      }
      chemin = chemin.replace('{slug}', slugTrouve[1]);
    }
    if (chemin.includes('{idCommande}')) {
      if (!idCommande) {
        lignes.push({ cle: page.cle, statut: 'ignorée', detail: 'aucune commande client' });
        continue;
      }
      chemin = chemin.replace('{idCommande}', idCommande);
    }
    if (chemin.includes('{idCommandeAdmin}')) {
      if (!idCommandeAdmin) {
        lignes.push({ cle: page.cle, statut: 'ignorée', detail: 'aucune commande admin' });
        continue;
      }
      chemin = chemin.replace('{idCommandeAdmin}', idCommandeAdmin);
    }

    let clientHttp = public_;
    if (page.session === 'client') clientHttp = client;
    if (page.session === 'admin') clientHttp = admin;
    if (page.session === 'client' && !clientConnecte) {
      lignes.push({ cle: page.cle, statut: 'ignorée', detail: 'connexion client impossible' });
      continue;
    }
    if (page.session === 'admin' && !adminConnecte) {
      lignes.push({ cle: page.cle, statut: 'ignorée', detail: 'connexion admin impossible' });
      continue;
    }
    if (page.prep === 'panier' && !panierRempli) {
      lignes.push({ cle: page.cle, statut: 'ignorée', detail: 'panier non rempli' });
      continue;
    }

    const attendu = page.cle === 'page-404' ? 404 : 200;
    const reponse = await clientHttp.get(chemin);
    const statut = reponse.statut === 302 ? '302' : String(reponse.statut);
    const conforme = reponse.statut === attendu;
    if (conforme) ok += 1;
    lignes.push({
      cle: page.cle,
      statut: conforme ? '✅' : `❌ ${statut}`,
      detail: conforme ? chemin : `${chemin} (attendu ${attendu}, reçu ${statut})`,
    });
  }

  return {
    lignes,
    ok,
    total: pages.length,
    slug: slugTrouve ? slugTrouve[1] : null,
    idCommande,
    idCommandeAdmin,
    clientConnecte,
    adminConnecte,
  };
}

// ---------------------------------------------------------------------------
//  Scénarios
// ---------------------------------------------------------------------------

async function connecter(session, compte) {
  await session.goto(compte.connexion);
  await session.type('#email', compte.email);
  await session.type('#mot_de_passe', compte.motDePasse);
  await Promise.all([session.attendreNavigation(), session.cliquer('form[action="' + compte.connexion + '"] button[type="submit"]')]);
  const url = await session.urlCourante();
  if (url.includes(compte.connexion)) {
    throw new Error(`connexion échouée pour ${compte.email} (toujours sur ${url})`);
  }
}

async function remplirPanier(session, slug) {
  for (let i = 0; i < 2; i += 1) {
    await session.goto(`/produit/${slug}`);
    await Promise.all([
      session.attendreNavigation(),
      session.cliquer('form[action="/panier/ajouter"] button[type="submit"]'),
    ]);
  }
}

async function provoquerErreursContact(session) {
  await session.goto('/contact');
  await session.type('#nom', 'A');
  await session.type('#email', 'pas-un-email');
  await session.type('#message', 'court');
  await Promise.all([
    session.attendreNavigation(),
    session.cliquer('form[action="/contact"] button[type="submit"]'),
  ]);
}

async function decouvrirIdCommande(session, motif) {
  const html = await session.html();
  const trouve = motif.exec(html);
  return trouve ? trouve[1] : null;
}

// ---------------------------------------------------------------------------
//  Audit de débordement (WCAG 1.4.10 Reflow)
// ---------------------------------------------------------------------------

/**
 * Exécuté DANS la page. Retourne la largeur utile, la largeur du document et
 * les éléments qui dépassent — en ignorant les régions défilantes prévues
 * (.tableau-defilant) et les éléments décoratifs hors flux (position: fixed).
 */
function auditDebordement() {
  const racine = document.documentElement;
  const largeur = racine.clientWidth;
  const coupables = [];

  document.querySelectorAll('body *').forEach((element) => {
    const rect = element.getBoundingClientRect();
    if (rect.width === 0 || rect.height === 0) return;
    if (element.closest('.tableau-defilant')) return;
    const style = window.getComputedStyle(element);
    if (style.position === 'fixed') return;
    if (style.visibility === 'hidden') return;
    if (rect.right > largeur + 1 || rect.left < -1) {
      const classes = typeof element.className === 'string' ? element.className : '';
      coupables.push({
        balise: element.tagName.toLowerCase(),
        classes: classes.split(/\s+/).filter(Boolean).slice(0, 3).join('.'),
        droite: Math.round(rect.right),
      });
    }
  });

  return {
    largeur,
    debordement: racine.scrollWidth > largeur + 1,
    scrollWidth: racine.scrollWidth,
    coupables: coupables.slice(0, 5),
  };
}

// ---------------------------------------------------------------------------
//  Rapport et galerie
// ---------------------------------------------------------------------------

function echapper(texte) {
  return String(texte)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function construireRapport(resultats, options, moteur) {
  const total = resultats.length;
  const debordements = resultats.filter((r) => r.debordement);
  const lignes = resultats.map((r) => {
    const verdict = r.debordement ? '❌ déborde' : '✅ ok';
    const coupables = r.coupables.length === 0
      ? '—'
      : r.coupables.map((c) => `<${c.balise}${c.classes ? '.' + c.classes : ''} → ${c.droite}px>`).join('<br>');
    return `| ${r.page} | ${r.largeur} px | ${verdict} | ${coupables} | ${r.fichier} |`;
  });

  return `# Rapport de captures responsive — MiniShop

> Généré le ${new Date().toLocaleString('fr-FR')} par \`scripts/screenshots.mjs\`
> Moteur : ${moteur ?? 'non exécuté (mode --check)'} · URL : ${options.url}

## Synthèse

- **${total}** captures sur **${new Set(resultats.map((r) => r.page)).size}** pages
- **${debordements.length}** débordement(s) horizontal(aux) détecté(s)
- Largeurs testées : ${options.largeurs.map((l) => l + ' px').join(' · ')}

${debordements.length === 0
    ? '✅ Aucun débordement horizontal : la page tient dans la largeur à toutes les tailles testées (WCAG 1.4.10 Reflow).'
    : `⚠️ ${debordements.length} cas à corriger (les régions \`.tableau-defilant\` sont exclues : leur défilement est voulu).`}

## Détail

| Page | Largeur | Verdict | Élément(s) hors cadre | Fichier |
|---|---:|---|---|---|
${lignes.join('\n')}

## Protocole manuel complémentaire

Les captures ne remplacent pas trois vérifications humaines :

1. **zoom 400 %** (équivalent 320 px) : aucune barre de défilement horizontale ;
2. **navigation au clavier** : Tab jusqu'au menu, Entrée ouvre, Échap referme ;
3. **appareil réel** : un téléphone, en portrait puis en paysage.
`;
}

function construireGalerie(resultats, options) {
  const pages = [...new Set(resultats.map((r) => r.page))];
  const blocs = pages.map((page) => {
    const pourPage = resultats.filter((r) => r.page === page);
    const vignettes = pourPage.map((r) => {
      const badge = r.debordement ? ' <span class="ko">déborde</span>' : '';
      return `      <figure>
        <a href="${echapper(r.fichier)}"><img src="${echapper(r.fichier)}" alt="Capture ${echapper(page)} à ${r.largeur} px" loading="lazy"></a>
        <figcaption>${r.largeur} px <small>${echapper(nomLargeur(r.largeur))}</small>${badge}</figcaption>
      </figure>`;
    });
    return `  <section>
    <h2>${echapper(page)}</h2>
    <div class="grille">
${vignettes.join('\n')}
    </div>
  </section>`;
  });

  const total = resultats.length;
  const debordements = resultats.filter((r) => r.debordement).length;

  return `<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Captures responsive — MiniShop</title>
<style>
:root { color-scheme: light; --encre:#0f172a; --douce:#475569; --bordure:#e2e8f0; --papier:#fbfaf7; --ok:#166534; --ko:#991b1b; }
* { box-sizing: border-box; }
body { margin:0; padding:2rem clamp(1rem,4vw,2.5rem); font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--papier); color: var(--encre); line-height:1.5; }
h1 { font-size: clamp(1.5rem, 1rem + 2vw, 2.25rem); margin: 0 0 .5rem; letter-spacing:-.02em; }
p.chapo { color: var(--douce); max-width: 70ch; margin: 0 0 2rem; }
section { margin-bottom: 3rem; }
section h2 { font-size: 1.1rem; text-transform: uppercase; letter-spacing: .06em; color: var(--douce); border-bottom: 1px solid var(--bordure); padding-bottom: .4rem; }
.grille { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }
figure { margin:0; background:#fff; border:1px solid var(--bordure); border-radius: 10px; padding:.5rem; }
figure img { width:100%; height:auto; display:block; border-radius: 6px; background:#fff; }
figcaption { font-size:.8rem; padding:.4rem .2rem 0; display:flex; gap:.4rem; align-items:baseline; }
figcaption small { color: var(--douce); }
.ko { color: var(--ko); font-weight:700; margin-left:auto; }
.entete { display:flex; flex-wrap:wrap; gap:1rem; align-items:baseline; justify-content:space-between; }
.bilan { font-weight:700; }
</style>
</head>
<body>
<header class="entete">
  <h1>Captures responsive — MiniShop</h1>
  <p class="bilan">${total} captures · ${debordements === 0 ? '<span style="color:#166534">aucun débordement</span>' : `<span style="color:#991b1b">${debordements} débordement(s)</span>`}</p>
</header>
<p class="chapo">Générées le ${new Date().toLocaleString('fr-FR')} sur ${echapper(options.url)} — largeurs : ${options.largeurs.map((l) => l + ' px').join(' · ')}. Cliquez sur une capture pour l'ouvrir en pleine taille. Les pages longues sont capturées en entier (full page).</p>
${blocs.join('\n')}
</body>
</html>
`;
}

// ---------------------------------------------------------------------------
//  Programme principal
// ---------------------------------------------------------------------------

async function main() {
  let options;
  try {
    options = analyserArguments(process.argv.slice(2));
  } catch (erreur) {
    logErreur(`✘ ${erreur.message}`);
    process.exit(2);
  }

  if (options.aide) {
    log(AIDE);
    return;
  }

  log('MiniShop — captures responsive');
  log(`   URL      : ${options.url}`);
  log(`   largeurs : ${options.largeurs.join(', ')} px`);
  log(`   sortie   : ${path.relative(process.cwd(), path.resolve(RACINE, options.out)) || options.out}`);
  log('');

  // ---- 1. base de données présente ?
  if (!existsSync(path.join(RACINE, 'data/minishop/produit.json'))) {
    logErreur('✘ data/minishop est absent : les pages seraient vides.');
    logErreur('  → php scripts/seed.php');
    logErreur('  → ou, sans PHP local : node scripts/wasm_run.mjs scripts/seed.php');
    process.exit(1);
  }

  // ---- 2. serveur joignable ? sinon on le démarre
  let serveur = null;
  let joignableUrl = await joignable(`${options.url}/`);
  if (!joignableUrl && options.serve && !options.noServer) {
    serveur = await demarrerServeur(options.url);
    joignableUrl = await joignable(`${options.url}/`);
  }

  if (!joignableUrl) {
    logErreur(`✘ aucune application sur ${options.url}`);
    logErreur('  Trois façons de la démarrer :');
    logErreur('    1. php -S 127.0.0.1:8000 -t public        (PHP 8.2 installé)');
    logErreur('    2. node scripts/wasm-serve.mjs 8080        (sandbox, sans PHP local)');
    logErreur('       puis : node scripts/screenshots.mjs --url http://127.0.0.1:8080');
    logErreur('    3. node scripts/screenshots.mjs --serve    (démarrage automatique)');
    if (serveur) serveur.arret();
    process.exit(1);
  }

  // ---- 3. les feuilles de style sont-elles servies ?
  let cssOk = false;
  try {
    const reponse = await fetch(`${options.url}/css/app.css`);
    const type = reponse.headers.get('content-type') ?? '';
    cssOk = reponse.ok && type.includes('text/css');
  } catch {
    cssOk = false;
  }

  const pagesRetenues = options.only.length === 0
    ? PAGES
    : PAGES.filter((page) => options.only.includes(page.cle));

  if (pagesRetenues.length === 0) {
    logErreur(`✘ --only ne correspond à aucune page. Clés disponibles : ${PAGES.map((p) => p.cle).join(', ')}`);
    if (serveur) serveur.arret();
    process.exit(2);
  }

  const plan = pagesRetenues.length * options.largeurs.length;

  if (options.check) {
    log('Mode vérification (--check) — aucune capture ne sera prise.');
    log(`   application   : ${joignableUrl ? '✅ joignable' : '❌ injoignable'}`);
    log(`   CSS servi     : ${cssOk ? '✅ /css/app.css en text/css' : '❌ /css/app.css absent ou mauvais type MIME'}`);
    log(`   captures      : ${plan} (${pagesRetenues.length} pages × ${options.largeurs.length} largeurs)`);
    log('');

    const bilan = await verifierPlan(options.url, pagesRetenues);
    log(`   routes vérifiées : ${bilan.ok}/${bilan.total}`);
    log(`   connexion client : ${bilan.clientConnecte ? '✅' : '❌'} · admin : ${bilan.adminConnecte ? '✅' : '❌'}`);
    log(`   produit / commandes découverts : ${bilan.slug ?? '—'} / ${bilan.idCommande ?? '—'} / ${bilan.idCommandeAdmin ?? '—'}`);

    const enEchec = bilan.lignes.filter((l) => l.statut.startsWith('❌') || l.statut === 'ignorée');
    if (enEchec.length > 0) {
      log('');
      for (const ligne of enEchec) log(`   ${ligne.statut} ${ligne.cle} — ${ligne.detail}`);
    }

    const moteur = await chargerMoteur(options.url, { ...options, headed: false }).catch(() => null);
    if (moteur) {
      log('   navigateur    : ✅ Puppeteer/Playwright disponible');
      await moteur.fermer();
    } else {
      log('   navigateur    : ⚠️ absent — « npm install --save-dev puppeteer »');
    }

    const toutBon = joignableUrl && cssOk && enEchec.length === 0;
    if (serveur) serveur.arret();
    process.exit(toutBon ? 0 : 1);
  }

  if (!cssOk) {
    logErreur('⚠️  /css/app.css n’est pas servi en text/css : les captures seront SANS STYLE.');
    logErreur('    Vérifiez que le serveur sert bien les fichiers de public/ (php -S le fait).');
  }

  // ---- 4. moteur de rendu
  let moteur;
  try {
    moteur = await chargerMoteur(options.url, options);
  } catch (erreur) {
    logErreur(`✘ lancement du navigateur impossible : ${erreur.message}`);
    logErreur('  Chromium n’est peut-être pas téléchargé. Essayez :');
    logErreur('    npx puppeteer browsers install chrome');
    logErreur('    node scripts/screenshots.mjs --channel chrome   # Chrome déjà installé');
    if (serveur) serveur.arret();
    process.exit(1);
  }

  if (!moteur) {
    logErreur('✘ aucun moteur de capture installé.');
    logErreur('  Installez-en un :');
    logErreur('    npm install --save-dev puppeteer        # télécharge Chromium (~150 Mo)');
    logErreur('    npm install --save-dev playwright && npx playwright install chromium');
    logErreur('  Si Chrome est déjà installé :');
    logErreur('    node scripts/screenshots.mjs --channel chrome');
    logErreur('    CHROME_PATH="/chemin/vers/chrome" node scripts/screenshots.mjs');
    if (serveur) serveur.arret();
    process.exit(2);
  }

  const dossierSortie = path.resolve(RACINE, options.out);
  await mkdir(dossierSortie, { recursive: true });

  // ---- 5. découverte du produit de démonstration
  const slug = await decouvrirSlug(options.url);
  if (!slug) logErreur('⚠️  aucun slug produit trouvé : la page « produit » sera ignorée.');
  log(`→ produit de démonstration : ${slug ?? '(aucun)'}`);

  // ---- 6. sessions
  const sessions = {};
  const resultats = [];
  let echecs = 0;

  const ouvrirSession = async (nom) => {
    if (!sessions[nom]) sessions[nom] = await moteur.nouvelleSession();
    return sessions[nom];
  };

  const capturer = async (session, chemin, dossierPage, largeur) => {
    await session.goto(chemin);
    const audit = await session.evaluer(auditDebordement);
    const nomFichier = fichierLargeur(largeur);
    await session.capture(path.join(dossierSortie, dossierPage, nomFichier));
    return { ...audit, fichier: `${dossierPage}/${nomFichier}` };
  };

  try {
    // sessions authentifiées préparées une seule fois
    let idCommandeClient = null;
    let idCommandeAdmin = null;

    let clientPrepare = false;
    let adminPrepare = false;

    const sessionClient = async () => {
      const session = await ouvrirSession('client');
      if (!clientPrepare) {
        await connecter(session, COMPTES.client);
        await session.goto('/mes-commandes');
        idCommandeClient = await decouvrirIdCommande(session, /\/mes-commandes\/(\d+)/);
        if (slug) {
          await remplirPanier(session, slug);
        }
        clientPrepare = true;
      }
      return session;
    };

    const sessionAdmin = async () => {
      const session = await ouvrirSession('admin');
      if (!adminPrepare) {
        await connecter(session, COMPTES.admin);
        await session.goto('/admin/commandes');
        idCommandeAdmin = await decouvrirIdCommande(session, /\/admin\/commandes\/(\d+)/);
        adminPrepare = true;
      }
      return session;
    };

    const sessionPublique = async () => ouvrirSession('public');

    const resoudreChemin = (page) => {
      let chemin = page.chemin;
      if (chemin.includes('{slug}')) {
        if (!slug) return null;
        chemin = chemin.replace('{slug}', slug);
      }
      if (chemin.includes('{idCommande}')) {
        if (!idCommandeClient) return null;
        chemin = chemin.replace('{idCommande}', idCommandeClient);
      }
      if (chemin.includes('{idCommandeAdmin}')) {
        if (!idCommandeAdmin) return null;
        chemin = chemin.replace('{idCommandeAdmin}', idCommandeAdmin);
      }
      return chemin;
    };

    let numero = 0;
    for (const page of pagesRetenues) {
      let session;
      if (page.session === 'client') session = await sessionClient();
      else if (page.session === 'admin') session = await sessionAdmin();
      else session = await sessionPublique();

      if (page.prep === 'erreurs-contact') {
        await provoquerErreursContact(session);
      }

      const chemin = resoudreChemin(page);
      if (chemin === null) {
        log(`  ⚠️  ${page.cle} ignorée (identifiant introuvable)`);
        continue;
      }

      const dossierPage = page.cle;
      await mkdir(path.join(dossierSortie, dossierPage), { recursive: true });

      for (const largeur of options.largeurs) {
        numero += 1;
        await session.definirViewport(largeur);
        try {
          const resultat = await capturer(session, chemin, dossierPage, largeur);
          const verdict = resultat.debordement ? '❌ débordement' : 'ok';
          log(`  [${String(numero).padStart(3, ' ')}/${plan}] ${page.cle} @ ${largeur}px → ${verdict}`);
          resultats.push({
            page: page.cle,
            titre: page.titre,
            largeur,
            debordement: resultat.debordement,
            coupables: resultat.coupables,
            fichier: resultat.fichier,
          });
        } catch (erreur) {
          echecs += 1;
          log(`  [${String(numero).padStart(3, ' ')}/${plan}] ${page.cle} @ ${largeur}px → échec : ${erreur.message}`);
        }
      }
    }

    // ---- 7. JavaScript désactivé (ENF-07) sur la page d'accueil
    if (options.noJs) {
      if (moteur.type !== 'puppeteer') {
        log('⚠️  --no-js n’est disponible qu’avec Puppeteer (option ignorée)');
      } else {
        log('→ variante « JavaScript désactivé » (accueil, ENF-07)');
        const session = await moteur.nouvelleSession();
        await session.page.setJavaScriptEnabled(false);
        const dossier = 'sans-javascript';
        await mkdir(path.join(dossierSortie, dossier), { recursive: true });
        for (const largeur of [320, 1024]) {
          await session.definirViewport(largeur);
          await session.goto('/');
          const audit = await session.evaluer(auditDebordement);
          await session.capture(path.join(dossierSortie, dossier, fichierLargeur(largeur)));
          resultats.push({
            page: 'accueil (sans JavaScript)',
            titre: 'Accueil sans JavaScript',
            largeur,
            debordement: audit.debordement,
            coupables: audit.coupables,
            fichier: `${dossier}/${fichierLargeur(largeur)}`,
          });
        }
        await session.fermer();
      }
    }

    // ---- 8. PDF d'impression
    if (options.pdf) {
      log('→ PDF d’impression');
      await mkdir(path.join(dossierSortie, 'pdf'), { recursive: true });
      const session = await sessionPublique();
      for (const [cle, chemin] of [['cgv', '/cgv'], ['mentions', '/mentions']]) {
        await session.goto(chemin, 'chargement');
        await session.pdf(path.join(dossierSortie, 'pdf', `${cle}.pdf`));
        log(`   pdf/${cle}.pdf`);
      }
    }
  } finally {
    for (const session of Object.values(sessions)) {
      if (session && typeof session.fermer === 'function') {
        await session.fermer().catch(() => {});
      }
    }
    await moteur.fermer().catch(() => {});
    if (serveur) serveur.arret();
  }

  // ---- 9. rapports
  await writeFile(
    path.join(dossierSortie, 'rapport.md'),
    construireRapport(resultats, options, `${moteur.type} (${moteur.browser ? 'navigateur lancé' : ''})`),
    'utf8',
  );
  await writeFile(path.join(dossierSortie, 'index.html'), construireGalerie(resultats, options), 'utf8');

  const debordements = resultats.filter((r) => r.debordement);
  log('');
  log('──────────────────────────────────────────────────────────────');
  log(`captures : ${resultats.length} · échecs : ${echecs} · débordements : ${debordements.length}`);
  if (debordements.length > 0) {
    for (const cas of debordements.slice(0, 10)) {
      const details = cas.coupables.map((c) => `<${c.balise}${c.classes ? '.' + c.classes : ''}>`).join(', ');
      log(`  ❌ ${cas.page} @ ${cas.largeur}px — ${details || 'élément non identifié'}`);
    }
    log('  → voir captures/rapport.md');
  }
  log(`galerie : ${path.join(options.out, 'index.html')}`);
  log(`rapport : ${path.join(options.out, 'rapport.md')}`);

  if (debordements.length > 0 && options.strict) {
    process.exit(1);
  }
  if (echecs > 0) {
    process.exit(1);
  }
}

main().catch((erreur) => {
  logErreur(`✘ erreur inattendue : ${erreur.stack ?? erreur.message}`);
  process.exit(1);
});
