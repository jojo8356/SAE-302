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
import { extname, join, resolve, sep } from 'node:path';

/** Types MIME des assets statiques servis directement (comme php -S). */
const TYPES_STATIQUES = {
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.gif': 'image/gif',
  '.svg': 'image/svg+xml',
  '.ico': 'image/x-icon',
  '.txt': 'text/plain; charset=utf-8',
};

/**
 * Fichier statique réel de public/ pour ce chemin, ou null.
 * Le runtime PHP-WASM ne sert que du PHP : sans ce court-circuit,
 * /minishop.css (l'unique feuille du site) répondrait 404 en sandbox
 * alors que php -S la sert. Confiné à public/, jamais de .php ici
 * (les .php continuent vers le runtime, qui les exécute).
 */
function fichierStatique(chemin) {
  const type = TYPES_STATIQUES[extname(chemin).toLowerCase()];
  if (!type) return null;
  const publicDir = resolve(join(REPO_ROOT, 'public'));
  const candidat = resolve(join(publicDir, `.${chemin}`));
  if (!candidat.startsWith(publicDir + sep)) return null;
  if (!existsSync(candidat) || !statSync(candidat).isFile()) return null;
  return { candidat, type };
}

const PORT = Number(process.argv[2] ?? process.env.PORT ?? 8080);
const BASE_URL = `http://localhost:${PORT}/`;

// base de données absente → peuplement initial avant le premier accès
if (!existsSync(join(REPO_ROOT, 'data/minishop/produit.json'))) {
  console.error('[wasm-serve] data/minishop absent — exécutez d\'abord : node scripts/wasm_run.mjs scripts/seed.php');
  process.exit(2);
}

const php = await createPhp(BASE_URL);
console.log(`[wasm-serve] PHP 8.2 (WASM) prêt — http://0.0.0.0:${PORT}/ (racine : public/)`);

/** File d'attente : le runtime WASM traite une requête à la fois. */
let file = Promise.resolve();
const enqueue = (task) => (file = file.then(task, task));

const serveur = http.createServer((req, res) => {
  // Assets statiques réels de public/ : servis tels quels, hors file PHP.
  if (req.method === 'GET' || req.method === 'HEAD') {
    let chemin = '';
    try {
      chemin = decodeURIComponent(new URL(req.url, 'http://localhost').pathname);
    } catch {
      chemin = '';
    }
    const statique = chemin ? fichierStatique(chemin) : null;
    if (statique) {
      res.writeHead(200, { 'content-type': statique.type });
      res.end(req.method === 'HEAD' ? undefined : readFileSync(statique.candidat));
      return;
    }
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
