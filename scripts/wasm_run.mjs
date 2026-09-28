/**
 * MiniShop — exécute un script PHP du dépôt dans le runtime PHP-WASM.
 * (Outil de développement sandbox : sur machine normale, utiliser `php <script>`.)
 *
 * Usage : node scripts/wasm_run.mjs tests/php/engine_smoke.php
 */

import { createPhp, runScript, syncBack } from './wasm_lib.mjs';

const target = process.argv[2];
if (!target) {
  console.error('Usage : node scripts/wasm_run.mjs <chemin/relatif/au/dépôt.php>');
  process.exit(2);
}

const php = await createPhp();
const { text, errors, exitCode } = await runScript(php, target);
await syncBack(php);
process.stdout.write(text + (errors ? `\n[PHP errors]\n${errors}` : '') + '\n');
process.exit(exitCode);
