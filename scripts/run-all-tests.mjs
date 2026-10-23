/**
 * MiniShop — exécute TOUTES les suites de tests dans l'ordre, s'arrête au
 * premier échec. Utilisable en sandbox (PHP-WASM) comme sur une machine
 * PHP normale (les suites PHP sont autonomes).
 *
 *   node scripts/run-all-tests.mjs        (sandbox)
 *   php scripts/run-all-tests.php         (machine normale — même logique)
 *
 * Suites, de la plus rapide à la plus intégrée :
 *   0. strict_types_lint.php — linter « strict types » (php-cs-fixer/PHPStan L9)
 *   1. engine_smoke.php   — moteur JSON (contraintes, triggers, transactions)
 *   2. run_tests.php      — règles de gestion T-01…T-29 (CDC §6)
 *   3. unit_tests.php     — unitaires (Text, Csrf, RB-11, PanierSession…)
 *   4. features_test.php  — fonctionnalités UC-01…UC-14 via les repositories
 *   5. css_contract.php   — contrats du design system (tokens, contrastes, CSP)
 *   6. responsive_contract.php — robustesse responsive (320 px → 1536 px)
 *   6. tests/phpunit/**   — suite PHPUnit (exécutée par son runner hors ligne)
 *   7. e2e.mjs            — seed + parcours HTTP complet, données restaurées
 */
import { spawnSync } from 'node:child_process';

const suites = [
  ['Moteur JSON', 'tests/php/engine_smoke.php'],
  ['Règles de gestion', 'tests/php/run_tests.php'],
  ['Unitaires', 'tests/php/unit_tests.php'],
  ['Fonctionnalités', 'tests/php/features_test.php'],
  ['Contrats de design', 'tests/php/css_contract.php'],
  ['Contrat responsive', 'tests/php/responsive_contract.php'],
  ['Unitaires PHPUnit', 'tests/phpunit/run-local.php'],
];

let échecs = 0;

console.log(`\n${'='.repeat(64)}\n▸ Linter strict types — scripts/strict_types_lint.php\n${'='.repeat(64)}`);
const lint = spawnSync('node', ['scripts/wasm_run.mjs', 'scripts/strict_types_lint.php'], { stdio: 'inherit' });
if (lint.status !== 0) ++échecs;

for (const [nom, fichier] of suites) {
  console.log(`\n${'='.repeat(64)}\n▸ ${nom} — ${fichier}\n${'='.repeat(64)}`);
  const r = spawnSync('node', ['scripts/wasm_run.mjs', fichier], { stdio: 'inherit' });
  if (r.status !== 0) ++échecs;
}

console.log(`\n${'='.repeat(64)}\n${'='.repeat(64)}`);
if (échecs > 0) {
  console.log(`SUITES : ${échecs} étape(s) en échec ❌ — E2E non joué`);
  process.exit(1);
}
console.log('SUITES PHP : TOUT EST VERT ✅ — E2E :');

// l'E2E exige un magasin propre et écrit (seed + parcours) : scripts/e2e.mjs
// photographie data/minishop avant, le restaure après — même en cas d'échec.
const e2e = spawnSync('node', ['scripts/e2e.mjs'], { stdio: 'inherit' });
if (e2e.status !== 0) {
  console.log('E2E : ÉCHEC ❌');
  process.exit(1);
}
console.log('\nTOUTES LES SUITES SONT VERTES ✅ (moteur, règles, unitaires, fonctionnalités, design, responsive, PHPUnit, E2E)');
