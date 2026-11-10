#!/usr/bin/env node
/**
 * Test de rendu Chromium réel. Uniquement sur une copie temporaire jetable :
 * aucune donnée ni session de l'application locale n'est utilisée ou restaurée.
 * Prérequis de CI : PHP natif, Playwright et Chromium déjà installé.
 */
import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { cp, mkdir, mkdtemp, readFile, rm } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import net from 'node:net';
import { chromium } from 'playwright';

const root = path.resolve(import.meta.dirname, '../..');
const php = process.env.PHP_BINARY || 'php';
if (spawnSync(php, ['--version'], { stdio: 'ignore' }).status !== 0) {
  console.error('NON JOUÉ : PHP natif est requis.');
  process.exit(2);
}
const tmp = await mkdtemp(path.join(os.tmpdir(), 'minishop-chromium-'));
let serveur;
let browser;
try {
  for (const dossier of ['app', 'public']) {
    await cp(path.join(root, dossier), path.join(tmp, dossier), { recursive: true });
  }
  await mkdir(path.join(tmp, 'scripts'));
  await mkdir(path.join(tmp, 'sessions'));
  for (const fichier of ['dev-router.php', 'seed.php']) {
    await cp(path.join(root, 'scripts', fichier), path.join(tmp, 'scripts', fichier));
  }
  const env = {
    ...process.env, MINISHOP_TESTS: '1', STORAGE_DRIVER: 'json',
    MINISHOP_ENV_FILE: path.join(tmp, 'absent.env'), DATA_PATH: path.join(tmp, 'data/minishop'),
  };
  for (const nom of ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_PASS', 'DB_SOCKET', 'DB']) delete env[nom];
  const seed = spawnSync(php, ['scripts/seed.php'], { cwd: tmp, env, encoding: 'utf8' });
  assert.equal(seed.status, 0, `Seed jetable : ${seed.stderr || seed.stdout}`);
  const reservateur = net.createServer();
  await new Promise((resolve, reject) => reservateur.once('error', reject).listen(0, '127.0.0.1', resolve));
  const port = reservateur.address().port;
  await new Promise((resolve) => reservateur.close(resolve));
  const base = `http://127.0.0.1:${port}`;
  serveur = spawn(php, ['-d', `session.save_path=${path.join(tmp, 'sessions')}`, '-S', `127.0.0.1:${port}`, '-t', 'public', 'scripts/dev-router.php'],
    { cwd: tmp, env, stdio: ['ignore', 'ignore', 'pipe'] });
  let logs = '';
  serveur.stderr.on('data', (chunk) => { logs += chunk.toString(); });
  const expiration = Date.now() + 20000;
  while (true) {
    if (serveur.exitCode !== null) throw new Error(`Serveur arrêté : ${logs}`);
    try {
      const response = await fetch(base);
      if (response.ok) break;
    } catch { /* démarrage du serveur */ }
    if (Date.now() > expiration) throw new Error(`Serveur non prêt : ${logs}`);
    await new Promise((resolve) => setTimeout(resolve, 100));
  }
  browser = await chromium.launch({ headless: true });
  const out = process.env.MINISHOP_BROWSER_ARTIFACTS;
  if (out) await mkdir(out, { recursive: true });

  async function pageVerifiee(page, nom, largeur, cible) {
    const h1 = page.locator('h1').first();
    await h1.waitFor({ state: 'visible' });
    if (cible) {
      assert.ok(await page.locator(cible).count(), `${nom} : élément métier absent : ${cible}`);
      assert.ok(await page.locator(cible).first().isVisible(), `${nom} : élément métier masqué : ${cible}`);
    }
    const mesures = await page.evaluate(() => {
      const largeurViewport = document.documentElement.clientWidth;
      const fautifs = [...document.querySelectorAll('main input, main select, main textarea, main button')]
        .filter((el) => {
          const r = el.getBoundingClientRect();
          const s = getComputedStyle(el);
          return s.display !== 'none' && s.visibility !== 'hidden' && r.width > 1 && r.height > 1
            && (r.left < -1 || r.right > largeurViewport + 1);
        }).map((el) => `${el.tagName}#${el.id || ''} ${Math.round(el.getBoundingClientRect().width)}px`);
      const cellules = [...document.querySelectorAll(
        '.tableau-panier tbody td, .tableau-recapitulatif tbody td, .tableau-commandes-client tbody td, .tableau-defilant--admin tbody td',
      )];
      const cellulesProbleme = largeurViewport <= 560 ? cellules.filter((el) => {
        const r = el.getBoundingClientRect();
        const libelle = el.getAttribute('data-label');
        const avant = getComputedStyle(el, '::before');
        return !libelle || avant.content === 'none' || avant.display === 'none'
          || r.left < -1 || r.right > largeurViewport + 1 || el.scrollWidth > el.clientWidth + 1;
      }).map((el) => `${el.closest('table')?.className || 'table'}:${el.dataset.label || '(sans libellé)'}`) : [];
      return { viewport: largeurViewport, page: document.documentElement.scrollWidth, fautifs, cellulesProbleme };
    });
    if (out) await page.screenshot({ path: path.join(out, `${largeur}-${nom}.png`), fullPage: true });
    assert.ok(mesures.page <= mesures.viewport + 1, `${largeur}px ${nom} : débordement horizontal ${JSON.stringify(mesures)}`);
    assert.deepEqual(mesures.fautifs, [], `${largeur}px ${nom} : champs ou boutons hors écran`);
    assert.deepEqual(mesures.cellulesProbleme, [], `${largeur}px ${nom} : cellules mobiles coupées ou non étiquetées`);
    console.log(`✅ Chromium ${largeur}px — ${nom}`);
  }
  async function aller(page, route) {
    const reponse = await page.goto(base + route, { waitUntil: 'networkidle' });
    assert.equal(reponse.status(), 200, `${route} : HTTP ${reponse.status()}`);
  }
  for (const largeur of [320, 375, 1440]) {
    const contexte = await browser.newContext({ viewport: { width: largeur, height: 900 } });
    const page = await contexte.newPage();
    await aller(page, '/catalogue');
    await pageVerifiee(page, 'catalogue', largeur, 'a[href^="/produit/"]');
    await page.locator('.filtres-catalogue summary').click();
    const prix = await page.evaluate(() => {
      const min = document.querySelector('#prix_min').getBoundingClientRect();
      const max = document.querySelector('#prix_max').getBoundingClientRect();
      return { minLeft: min.left, minRight: min.right, minTop: min.top,
        maxLeft: max.left, maxRight: max.right, maxTop: max.top, viewport: document.documentElement.clientWidth };
    });
    assert.ok(prix.minLeft >= 0 && prix.minRight <= prix.viewport + 1 && prix.maxLeft >= 0 && prix.maxRight <= prix.viewport + 1,
      `Filtres prix coupés : ${JSON.stringify(prix)}`);
    if (largeur >= 360) {
      assert.ok(prix.minRight <= prix.maxLeft, `Filtres prix non côte à côte : ${JSON.stringify(prix)}`);
    } else {
      assert.ok(prix.maxTop > prix.minTop, `Filtres prix non empilés : ${JSON.stringify(prix)}`);
    }
    await page.locator('.filtres-catalogue summary').click();
    const href = await page.locator('a[href^="/produit/"]').first().getAttribute('href');
    await aller(page, href);
    await pageVerifiee(page, 'produit', largeur, '.tableau-caracteristiques');
    const [ajout] = await Promise.all([
      page.waitForResponse((reponse) => reponse.url().includes('/api/panier/ajouter') && reponse.request().method() === 'POST'),
      page.locator('form[action="/panier/ajouter"] button').click(),
    ]);
    assert.ok(ajout.ok(), `Ajout AJAX : HTTP ${ajout.status()}`);
    await aller(page, '/panier');
    await pageVerifiee(page, 'panier', largeur, '.tableau-panier tbody tr');
    await aller(page, '/connexion');
    await page.fill('#email', 'alice@example.com');
    await page.fill('#mot_de_passe', 'Demo2026!');
    await page.locator('form[action="/connexion"] button').click();
    await aller(page, '/commande/valider');
    await pageVerifiee(page, 'validation', largeur, '.tableau-recapitulatif tbody tr');
    await page.locator('form[action="/commande/valider"] button').click();
    await page.waitForURL(/\/mes-commandes\/\d+$/);
    const commandeId = page.url().match(/\/mes-commandes\/(\d+)$/)[1];
    await aller(page, '/mes-commandes');
    await pageVerifiee(page, 'mes-commandes', largeur, '.tableau-commandes-client tbody tr');
    await contexte.close();

    const admin = await browser.newContext({ viewport: { width: largeur, height: 900 }, javaScriptEnabled: false });
    const administration = await admin.newPage();
    await aller(administration, '/admin/connexion');
    await administration.fill('#email', 'admin@minishop.fr');
    await administration.fill('#mot_de_passe', 'Admin2026!');
    await administration.locator('form[action="/admin/connexion"] button').click();
    for (const [route, nom, cible] of [
      ['/admin/categories', 'categories-admin', '.tableau-categories tbody tr'],
      ['/admin/commandes', 'commandes-admin', '.tableau-commandes tbody tr'],
      ['/admin/stocks', 'stocks-admin', '.tableau-stocks tbody tr'],
      ['/admin/produits', 'produits-admin', '.tableau-defilant--admin tbody tr'],
      ['/admin/equipe', 'equipe-admin', '.tableau-defilant--admin tbody tr'],
      ['/admin', 'dashboard-admin', '.kpis'],
    ]) {
      await aller(administration, route);
      await pageVerifiee(administration, nom, largeur, cible);
      const correspondances = await administration.evaluate(() => [...document.querySelectorAll('.tableau-defilant--admin table')].flatMap((table) => {
        const titres = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
        return [...table.querySelectorAll('tbody tr')].flatMap((tr) => [...tr.querySelectorAll(':scope > td')]
          .map((td, i) => td.dataset.label === titres[i] ? null : `${td.dataset.label} != ${titres[i]}`).filter(Boolean));
      }));
      assert.deepEqual(correspondances, [], `${nom} : libellés incohérents`);
    }
    await aller(administration, '/admin/commandes');
    const donnees = JSON.parse(await readFile(path.join(tmp, 'data/minishop/commande.json'), 'utf8'));
    const commande = donnees.rows.find((row) => String(row.id_commande) === commandeId);
    assert.ok(commande, 'Commande de test absente');
    const dateUtc = new Date(Date.parse(commande.date_commande.replace(' ', 'T') + 'Z'));
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-GB', {timeZone:'Europe/Paris', year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(dateUtc).map(({type,value}) => [type,value]));
    const dateLocale = `${parts.year}-${parts.month}-${parts.day}`;
    const instantLocal = new Date(dateLocale + 'T12:00:00Z');
    await administration.fill('#du', dateLocale);
    await administration.fill('#au', dateLocale);
    await administration.locator('form[action="/admin/commandes"] button').click();
    assert.ok((await administration.locator('.tableau-commandes').innerText()).includes(commande.numero), 'filtre du jour affiché exclut une commande');
    const lendemain = new Date(instantLocal.getTime() + 86400000).toISOString().slice(0, 10);
    await administration.fill('#du', lendemain);
    await administration.fill('#au', lendemain);
    await administration.locator('form[action="/admin/commandes"] button').click();
    assert.ok(!(await administration.locator('.tableau-commandes').innerText()).includes(commande.numero), 'filtre du lendemain inclut une commande');
    await aller(administration, '/admin/commandes');
    if (largeur <= 560) {
      // Données longues injectées dans le DOM jetable : rien n'est enregistré.
      await administration.evaluate(() => {
        const row = document.querySelector('.tableau-commandes tbody tr');
        row.querySelector('[data-label="Statut"]').textContent = 'Statut exceptionnel très long '.repeat(6);
        row.querySelector('[data-label="Montant TTC"]').textContent = '123456789012345678901234567890 €';
        row.querySelector('[data-label="Client"]').textContent = 'client'.repeat(35);
      });
      await pageVerifiee(administration, 'commandes-longues', largeur, '.tableau-commandes tbody tr');
    }
    // La confirmation fonctionne sans JavaScript et ne modifie rien au premier POST.
    await aller(administration, `/admin/commandes/${commandeId}`);
    await pageVerifiee(administration, 'detail-admin', largeur, '.tableau-defilant--admin tbody tr');
    await administration.selectOption('#statut', 'ANNULEE');
    await administration.fill('#commentaire', 'Test confirmation');
    await administration.locator('form[action$="/statut"] button').click();
    await administration.getByRole('heading', { name: 'Confirmer l’annulation' }).waitFor();
    assert.ok(await administration.getByText('Les articles non expédiés seront restitués au stock.').isVisible());
    await administration.getByRole('link', { name: 'Non, revenir à la commande' }).click();
    assert.ok(!(await administration.locator('body').innerText()).includes('Montant soldé'));
    await administration.selectOption('#statut', 'ANNULEE');
    await administration.fill('#commentaire', 'Test confirmation');
    await administration.locator('form[action$="/statut"] button').click();
    await administration.getByRole('button', { name: 'Confirmer l’annulation définitive' }).click();
    assert.ok((await administration.locator('body').innerText()).includes('Montant soldé'));
    console.log(`✅ Confirmation administrateur sans JavaScript — ${largeur}px`);
    await admin.close();

    const perso = await browser.newContext({ viewport: { width: largeur, height: 900 }, locale: 'fr-FR',
      geolocation: { latitude: 43.70, longitude: 7.26 }, permissions: ['geolocation'] });
    const personnel = await perso.newPage();
    const demandes = [];
    personnel.on('request', (r) => demandes.push(r.url()));
    await aller(personnel, '/preferences');
    await pageVerifiee(personnel, 'preferences', largeur, '#pref-position');
    await personnel.click('#pref-position');
    await personnel.waitForFunction(() => document.querySelector('#pref-zone').value === 'Europe/Paris');
    assert.equal(await personnel.evaluate(() => localStorage.length), 0, 'position enregistrée avant validation');
    await personnel.selectOption('#pref-zone', 'Asia/Kathmandu');
    await personnel.locator('#form-preferences button[type="submit"]').click();
    const choix = await personnel.evaluate(() => JSON.parse(localStorage.getItem('minishop.preferences.v1')));
    assert.deepEqual(choix, {langue:'fr', zone:'Asia/Kathmandu'});
    assert.ok(!JSON.stringify(choix).includes('43.7'), 'coordonnées enregistrées');
    await aller(personnel, '/');
    const temps = personnel.locator('time[data-date-format="ajout"]').first();
    const utc = await temps.getAttribute('datetime');
    const parties = Object.fromEntries(new Intl.DateTimeFormat('fr-FR', {timeZone:'Asia/Kathmandu', year:'numeric', month:'2-digit', day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).formatToParts(new Date(utc)).map(({type,value}) => [type,value]));
    assert.equal(await temps.innerText(), `${parties.day}/${parties.month}/${parties.year} à ${parties.hour}h${parties.minute}`);
    await aller(personnel, '/admin/connexion');
    await personnel.fill('#email', 'admin@minishop.fr');
    await personnel.fill('#mot_de_passe', 'Admin2026!');
    await personnel.locator('form[action="/admin/connexion"] button').click();
    await personnel.waitForFunction(() => document.querySelector('[data-jour-info]')?.textContent.includes('Asia/Kathmandu'));
    await aller(personnel, '/admin/commandes');
    await personnel.fill('#du', '2026-03-29');
    await personnel.fill('#au', '2026-03-29');
    await personnel.locator('form[action="/admin/commandes"] button').click();
    const url = new URL(personnel.url());
    assert.equal(url.searchParams.get('debut_utc'), '2026-03-28 18:15:00');
    assert.equal(url.searchParams.get('fin_utc'), '2026-03-29 18:15:00');
    assert.ok(demandes.every((url) => url.startsWith(base)), 'ressource externe sollicitée');
    assert.ok(demandes.every((url) => !url.includes('43.7') && !url.includes('7.26')), 'coordonnées transmises');
    console.log(`✅ Préférences et filtres personnels — ${largeur}px`);
    await perso.close();
  }
  console.log('✅ Vérifications Chromium terminées ; aucune garantie sur d’autres navigateurs ou appareils.');
} catch (erreur) {
  console.error('❌ Test de rendu Chromium :', erreur);
  process.exitCode = 1;
} finally {
  if (browser) await browser.close();
  if (serveur && serveur.exitCode === null) {
    serveur.kill('SIGTERM');
    await new Promise((resolve) => serveur.once('exit', resolve));
  }
  await rm(tmp, { recursive: true, force: true });
}
