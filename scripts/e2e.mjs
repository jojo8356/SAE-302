/** Lance les parcours HTTP dans le système de fichiers WASM jetable. */
import { spawnSync } from 'node:child_process';
import { join } from 'node:path';

const racine = join(import.meta.dirname, '..');
const e2e = spawnSync('node', ['scripts/wasm-e2e.mjs'], {
  cwd: racine,
  env: { ...process.env, MINISHOP_TESTS: '1' },
  stdio: 'inherit',
});
if (e2e.error) console.error('❌ Impossible de lancer les parcours E2E :', e2e.error.message);
process.exit(e2e.status ?? 1);
