/**
 * MiniShop — utilitaires PHP-WASM (outils de développement uniquement).
 *
 * La sandbox Arena ne dispose pas de binaire PHP natif : on exécute l'application
 * avec PHP 8.2 compilé en WebAssembly (@php-wasm/node). SUR UNE MACHINE NORMALE,
 * RIEN DE TOUT CI-DESSOUS N'EST NÉCESSAIRE : `php -S 0.0.0.0:8000 -t public`
 * suffit (cf. README). Ces scripts ne sont jamais utilisés par l'application.
 *
 * Principe : le dépôt est « monté » dans le système de fichiers du runtime WASM
 * (upload applicatif avant usage, puis rapatriement des données mutées
 * data/ + var/ après chaque requête ou exécution).
 */

import { NodePHP } from '@php-wasm/node';
import { readdirSync, statSync, readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { join, dirname, relative } from 'node:path';

export const REPO_ROOT = join(import.meta.dirname, '..');
export const WASM_ROOT = '/repo';

/** Dossiers utiles au fonctionnement de l'application dans le runtime WASM. */
// `tests/sql` est monté aussi : les tests unitaires vérifient que le harnais SQL
// aligne sa collation de session sur celle du schéma (cf. ERROR 1267 sur les
// colonnes de vue). Sans ce montage, le contrôle serait muet en sandbox.
export const UPLOAD_DIRS = ['app', 'public', 'data', 'tests/php', 'tests/phpunit', 'tests/sql', 'scripts', 'sql'];

/** Dossiers que l'application peut muter → à rapatrier sur l'hôte. */
export const MUTABLE_DIRS = ['data', 'var'];

/** Crée le runtime PHP-WASM prêt à servir l'application. */
export async function createPhp(absoluteUrl = 'http://localhost:8080/', { inclureDonnees = true } = {}) {
  const php = await NodePHP.load('8.2', {
    requestHandler: { documentRoot: `${WASM_ROOT}/public`, absoluteUrl },
  });
  for (const dir of UPLOAD_DIRS) {
    if (dir === 'data' && !inclureDonnees) continue;
    const hostDir = join(REPO_ROOT, dir);
    if (existsSync(hostDir)) {
      await uploadTree(php, hostDir, `${WASM_ROOT}/${dir}`);
    }
  }
  php.setPhpIniEntry('session.save_path', '/tmp');
  php.setPhpIniEntry('session.gc_probability', '0');
  return php;
}

/** Copie récursive hôte → FS WASM. */
export async function uploadTree(php, hostDir, wasmDir) {
  await php.mkdir(wasmDir);
  const walk = async (dir, base) => {
    for (const entry of readdirSync(dir)) {
      const hostPath = join(dir, entry);
      const wasmPath = `${base}/${entry}`;
      if (statSync(hostPath).isDirectory()) {
        await php.mkdir(wasmPath);
        await walk(hostPath, wasmPath);
      } else {
        const bytes = readFileSync(hostPath);
        await php.writeFile(wasmPath, new Uint8Array(bytes));
      }
    }
  };
  await walk(hostDir, wasmDir);
}

/** Rapatrie les dossiers mutés (data/, var/) du FS WASM vers l'hôte. */
export async function syncBack(php, dirs = MUTABLE_DIRS) {
  for (const dir of dirs) {
    await downloadTree(php, `${WASM_ROOT}/${dir}`, join(REPO_ROOT, dir));
  }
}

async function downloadTree(php, wasmDir, hostDir) {
  try {
    const entries = await php.listFiles(wasmDir);
    mkdirSync(hostDir, { recursive: true });
    for (const entry of entries) {
      const wasmPath = `${wasmDir}/${entry}`;
      const hostPath = join(hostDir, entry);
      const isDir = await php.isDir(wasmPath);
      if (isDir) {
        await downloadTree(php, wasmPath, hostPath);
      } else {
        const bytes = await php.readFileAsBuffer(wasmPath);
        mkdirSync(dirname(hostPath), { recursive: true });
        writeFileSync(hostPath, Buffer.from(bytes));
      }
    }
  } catch {
    /* dossier absent côté WASM : rien à rapatrier */
  }
}

/** Exécute un script PHP du dépôt (chemin relatif au repo) et affiche sa sortie. */
export async function runScript(php, relativePath) {
  const result = await php.run({ scriptPath: `${WASM_ROOT}/${relativePath}` });
  const errors = result.errors ?? '';
  return { text: result.text ?? '', errors, exitCode: result.exitCode ?? 0 };
}

/**
 * Exécute un script PHP avec des arguments de ligne de commande.
 *
 * Le runtime php-wasm n'expose pas `$argv` en mode « scriptPath » : on amorce
 * un fragment PHP qui place les arguments dans $_SERVER puis charge le script.
 * Les scripts concernés lisent $_SERVER['argv'] (voir scripts/db_init.php).
 */
export async function runScriptAvecArguments(php, relativePath, args = []) {
  // argv[0] = script (chemin dans le runtime), argv[1…] = arguments bruts
  const chemins = [`${WASM_ROOT}/${relativePath}`, ...args];
  const amorce = `<?php $_SERVER['argv'] = ${JSON.stringify(chemins)}; `
    + `$_SERVER['argc'] = ${chemins.length}; require ${JSON.stringify(`${WASM_ROOT}/${relativePath}`)};`;
  const result = await php.run({ code: amorce });
  const errors = result.errors ?? '';
  return { text: result.text ?? '', errors, exitCode: result.exitCode ?? 0 };
}

/** Vrai si le chemin hôte existe (utilitaire). */
export function hostExists(p) {
  return existsSync(join(REPO_ROOT, p));
}
