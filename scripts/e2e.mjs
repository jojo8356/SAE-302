/**
 * MiniShop — lancement hermétique du parcours E2E.
 *
 *   1. photographie de data/minishop (jeu de démonstration livré) ;
 *   2. seed (base complète, mots de passe hachés) ;
 *   3. parcours HTTP complet (scripts/wasm-e2e.mjs) ;
 *   4. restauration à l'identique — même si le parcours échoue.
 *
 * Sans cette enveloppe, `npm run test:e2e` remplaçait le mot de passe du
 * compte de démonstration et laissait des produits « TEST-… » au catalogue.
 */

import { spawnSync } from 'node:child_process';
import { join } from 'node:path';
import { avecDonneesIntactes } from './data_snapshot.mjs';

const RACINE = join(import.meta.dirname, '..');
const DONNEES = join(RACINE, 'data/minishop');

const code = await avecDonneesIntactes(DONNEES, () => {
  const seed = spawnSync('node', ['scripts/wasm_run.mjs', 'scripts/seed.php'], {
    cwd: RACINE,
    env: { ...process.env, MINISHOP_TESTS: '1' },
    stdio: 'inherit',
  });
  if (seed.status !== 0) {
    console.error('✘ seed en échec — E2E interrompu');
    return seed.status ?? 1;
  }
  const e2e = spawnSync('node', ['scripts/wasm-e2e.mjs'], { cwd: RACINE, env: { ...process.env, MINISHOP_TESTS: '1' }, stdio: 'inherit' });
  return e2e.status ?? 1;
});

console.log('→ data/minishop restauré à l’identique (jeu de démonstration intact).');
process.exit(code);
