/**
 * MiniShop — exécute un script PHP du dépôt dans le runtime PHP-WASM.
 * (Outil de développement sandbox : sur machine normale, utiliser `php <script>`.)
 *
 * Usage : node scripts/wasm_run.mjs <script.php> [arguments…]
 *   node scripts/wasm_run.mjs tests/php/engine_smoke.php
 *   node scripts/wasm_run.mjs scripts/db_init.php --check
 *
 * ─── Pourquoi le code de sortie n'est PAS celui du runtime ───────────────
 * `php.run()` renvoie `result.exitCode`, mais celui-ci provient du
 * gestionnaire de requêtes PHP-WASM (`wasm_sapi_handle_request`) et non du CLI.
 * Mesuré sur ce dépôt :
 *
 *   script terminé normalement      → 1     ← inversion
 *   `exit(0)` / `exit(1)` / `exit(3)` → 0   ← le code demandé est perdu
 *   erreur fatale (exception non attrapée) → 0
 *
 * Propager cette valeur donnerait deux mensonges symétriques : des suites
 * vertes comptées en échec, et surtout des suites en échec comptées vertes —
 * c'est-à-dire un « TOUT EST VERT » qui ne prouve rien. Le verdict est donc
 * établi sur des signaux observables :
 *
 *   - erreur fatale ou de syntaxe PHP        → 255
 *   - au moins un marqueur d'échec « ❌ »     → 1
 *   - aucune réussite (« ✅ ») et « NON JOUÉ » → 2  (suite non jouable ici)
 *   - sinon                                   → 0
 *
 * Les suites de ce dépôt n'impriment « ❌ » que sur un contrôle en échec. Le
 * calcul du verdict vit dans `scripts/wasm_verdict.mjs`, avec son autotest :
 *
 *     node scripts/wasm_verdict.mjs
 */

import { createPhp, runScript, runScriptAvecArguments, syncBack } from './wasm_lib.mjs';
import { verdict } from './wasm_verdict.mjs';

const [target, ...args] = process.argv.slice(2);
if (!target) {
  console.error('Usage : node scripts/wasm_run.mjs <chemin/relatif/au/dépôt.php> [arguments…]');
  process.exit(2);
}

const php = await createPhp();
const resultat = args.length > 0
  ? await runScriptAvecArguments(php, target, args)
  : await runScript(php, target);
const { text, errors } = resultat;
await syncBack(php);

const sortie = `${text}\n${errors}`;
process.stdout.write(text + (errors ? `\n[PHP errors]\n${errors}` : '') + '\n');

process.exit(verdict(sortie));
