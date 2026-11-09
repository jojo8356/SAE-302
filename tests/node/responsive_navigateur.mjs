#!/usr/bin/env node
/**
 * Test de rendu Chromium réel. Uniquement sur une copie temporaire jetable :
 * aucune donnée ni session de l'application locale n'est utilisée ou restaurée.
 * Prérequis de CI : PHP natif, Playwright et Chromium déjà installé.
 */
import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { cp, mkdir, mkdtemp, rm } from 'node:fs/promises';
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
      return { viewport: largeurViewport, page: document.documentElement.scrollWidth, fautifs };
    });
    if (out) await page.screenshot({ path: path.join(out, `${largeur}-${nom}.png`), fullPage: true });
    assert.ok(mesures.page <= mesures.viewport + 1, `${largeur}px ${nom} : débordement horizontal ${JSON.stringify(mesures)}`);
    assert.deepEqual(mesures.fautifs, [], `${largeur}px ${nom} : champs ou boutons hors écran`);
    console.log(`✅ Chromium ${largeur}px — ${nom}`);
  }
  async function aller(page, route) {
    const reponse = await page.goto(base + route, { waitUntil: 'networkidle' });
    assert.equal(reponse.status(), 200, `${route} : HTTP ${reponse.status()}`);
  }
  for (const largeur of [375, 1440]) {
    const contexte = await browser.newContext({ viewport: { width: largeur, height: 900 } });
    const page = await contexte.newPage();
    await aller(page, '/catalogue');
    await pageVerifiee(page, 'catalogue', largeur, 'a[href^="/produit/"]');
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
    await aller(page, '/mes-commandes');
    await pageVerifiee(page, 'mes-commandes', largeur, '.tableau-commandes-client tbody tr');
    await contexte.close();

    const admin = await browser.newContext({ viewport: { width: largeur, height: 900 } });
    const administration = await admin.newPage();
    await aller(administration, '/admin/connexion');
    await administration.fill('#email', 'admin@minishop.fr');
    await administration.fill('#mot_de_passe', 'Admin2026!');
    await administration.locator('form[action="/admin/connexion"] button').click();
    for (const [route, nom, cible] of [
      ['/admin/categories', 'categories-admin', '.tableau-categories tbody tr'],
      ['/admin/commandes', 'commandes-admin', '.tableau-commandes tbody tr'],
      ['/admin/stocks', 'stocks-admin', '.tableau-stocks tbody tr'],
    ]) {
      await aller(administration, route);
      await pageVerifiee(administration, nom, largeur, cible);
    }
    await admin.close();
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
