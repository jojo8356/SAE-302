/**
 * MiniShop — exécute un script PHP du dépôt dans le runtime PHP-WASM.
 * (Outil de développement sandbox : sur machine normale, utiliser `php <script>`.)
 *
 * Usage : node scripts/wasm_run.mjs <script.php> [arguments…]
 *   node scripts/wasm_run.mjs tests/php/engine_smoke.php
 *   node scripts/wasm_run.mjs scripts/db_init.php --check
 */

import { createPhp, runScript, runScriptAvecArguments, syncBack } from './wasm_lib.mjs';

const [target, ...args] = process.argv.slice(2);
if (!target) {
  console.error('Usage : node scripts/wasm_run.mjs <chemin/relatif/au/dépôt.php> [arguments…]');
  process.exit(2);
}

const php = await createPhp();
const resultat = args.length > 0
  ? await runScriptAvecArguments(php, target, args)
  : await runScript(php, target);
const { text, errors, exitCode } = resultat;
await syncBack(php);
process.stdout.write(text + (errors ? `\n[PHP errors]\n${errors}` : '') + '\n');
process.exit(exitCode);
