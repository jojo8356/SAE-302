/**
 * MiniShop — serveur HTTP de développement basé sur PHP-WASM (SANS CSS, SANS SQL).
 *
 * ⚠️ Outil de développement pour la sandbox Arena uniquement : sur une machine
 * normale, `php -S 0.0.0.0:8000 -t public` fait exactement la même chose.
 *
 * Usage :  node scripts/wasm-serve.mjs [port]      (défaut 8080)
 * Le dépôt est « monté » dans le runtime WASM ; les requêtes sont exécutées
 * par le contrôleur frontal public/index.php ; les dossiers mutés (data/,
 * var/) sont rapatriés sur l'hôte après chaque écriture.
 */

import http from 'node:http';
import { randomUUID } from 'node:crypto';
import { createPhp, syncBack, REPO_ROOT } from './wasm_lib.mjs';
import { existsSync, readFileSync, statSync } from 'node:fs';
import { extname, join, normalize, sep } from 'node:path';

const PORT = Number(process.argv[2] ?? process.env.PORT ?? 8080);
const BASE_URL = `http://localhost:${PORT}/`;

// base de données absente → peuplement initial avant le premier accès
if (!existsSync(join(REPO_ROOT, 'data/minishop/produit.json'))) {
  console.error('[wasm-serve] data/minishop absent — exécutez d\'abord : node scripts/wasm_run.mjs scripts/seed.php');
  process.exit(2);
}

const php = await createPhp(BASE_URL);
console.log(`[wasm-serve] PHP 8.2 (WASM) prêt — http://0.0.0.0:${PORT}/ (racine : public/)`);

/**
 * Fichiers statiques (CSS, JS, images) : le runtime PHP-WASM n'a pas de
 * serveur web devant lui, donc /css/app.css serait routé vers PHP et
 * répondrait 404 — la page s'afficherait sans style. On les sert ici,
 * comme le ferait Apache ou `php -S`.
 */
const TYPES = {
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.mjs': 'text/javascript; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.ico': 'image/x-icon',
  '.woff2': 'font/woff2',
  '.json': 'application/json; charset=utf-8',
  '.txt': 'text/plain; charset=utf-8',
};

function servirStatique(req, res, cheminUrl) {
  if (req.method !== 'GET' && req.method !== 'HEAD') return false;
  if (!cheminUrl.startsWith('/css/') && !cheminUrl.startsWith('/js/') &&
      !cheminUrl.startsWith('/img/') && cheminUrl !== '/favicon.ico') {
    return false;
  }
  // anti-traversée : on normalise et on vérifie que le chemin reste dans public/
  const relatif = normalize(decodeURIComponent(cheminUrl)).replace(/^([/\\])+/, '');
  const absolu = join(REPO_ROOT, 'public', relatif);
  if (!absolu.startsWith(join(REPO_ROOT, 'public') + sep)) return false;
  if (!existsSync(absolu) || !statSync(absolu).isFile()) return false;

  const corps = readFileSync(absolu);
  res.writeHead(200, {
    'content-type': TYPES[extname(absolu).toLowerCase()] ?? 'application/octet-stream',
    'content-length': corps.length,
    'cache-control': 'no-cache',
    'x-content-type-options': 'nosniff',
  });
  res.end(req.method === 'HEAD' ? undefined : corps);
  return true;
}

/** File d'attente : le runtime WASM traite une requête à la fois. */
let file = Promise.resolve();
const enqueue = (task) => (file = file.then(task, task));

const serveur = http.createServer((req, res) => {
  if (servirStatique(req, res, (req.url ?? '/').split('?')[0])) {
    return;
  }
  enqueue(async () => {
    try {
      const morceaux = [];
      for await (const morceau of req) morceaux.push(morceau);
      const corpsBrut = Buffer.concat(morceaux).toString('utf8');

      const entetes = {};
      for (const [cle, valeur] of Object.entries(req.headers)) {
        if (cle === 'host' || cle === 'connection' || cle === 'content-length') continue;
        entetes[cle] = valeur;
      }
      // identifiant unique par requête : le processus PHP-WASM survit aux
      // requêtes, Auth::demarrer() s'appuie sur cet en-tête pour isoler les
      // sessions d'un visiteur à l'autre (inutile sur un vrai serveur PHP)
      entetes['x-request-id'] = randomUUID();
      // le SAPI WASM avale l'en-tête « cookie » ($_COOKIE reste vide) : on le
      // transporte sous « x-ms-cookie », lu par App\Security\Auth en mode wasm
      if (entetes.cookie) {
        entetes['x-ms-cookie'] = entetes.cookie;
        delete entetes.cookie;
      }

      let requete = { method: req.method, url: req.url, headers: entetes };
      if (req.method === 'POST' && corpsBrut !== '') {
        // formes encodées URL (toutes les écritures MiniShop) → formData WASM
        const forme = {};
        for (const paire of corpsBrut.split('&')) {
          const [cle, valeur] = paire.split('=');
          if (cle !== undefined) {
            forme[decodeURIComponent(cle.replace(/\+/g, ' '))] =
              decodeURIComponent((valeur ?? '').replace(/\+/g, ' '));
          }
        }
        requete = { method: req.method, url: req.url, headers: entetes, formData: forme };
      }

      const reponse = await php.request(requete);
      if (req.method !== 'GET' && req.method !== 'HEAD') {
        await syncBack(php); // persistance des écritures côté hôte
      }

      const sortie = reponse.bytes ? Buffer.from(reponse.bytes) : Buffer.from(reponse.text ?? '', 'utf8');
      const statut = reponse.httpStatusCode ?? 200;
      for (const [cle, valeur] of Object.entries(reponse.headers ?? {})) {
        if (cle.toLowerCase() === 'content-encoding' || cle.toLowerCase() === 'transfer-encoding') continue;
        res.setHeader(cle, Array.isArray(valeur) ? valeur : valeur);
      }
      res.writeHead(statut);
      res.end(sortie);
    } catch (e) {
      console.error('[wasm-serve] erreur requête :', e);
      res.writeHead(502, { 'content-type': 'text/plain; charset=utf-8' });
      res.end('Erreur interne du serveur WASM — consultez les journaux.');
    }
  });
});

serveur.listen(PORT, '0.0.0.0', () => {
  console.log(`[wasm-serve] écoute sur http://0.0.0.0:${PORT}/`);
});
