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

// Sur une machine avec PHP natif, AUCUNE dépendance npm n'est nécessaire :
// on exécute directement `php <script>`. Le runtime WASM (@php-wasm/node) ne
// sert que dans les environnements sans PHP (sandbox de développement).
const phpExiste = spawnSync('php', ['-r', 'echo PHP_VERSION;'], { encoding: 'utf8' }).status === 0;

/** Construit la commande d'un script PHP : natif si possible, sinon WASM. */
const commande = (fichier) => {
  if (phpExiste) return ['php', [fichier]];
  return ['node', ['scripts/wasm_run.mjs', fichier]];
};

const lancer = (fichier) => {
  const [programme, arguments_] = commande(fichier);
  return spawnSync(programme, arguments_, { stdio: 'inherit' });
};

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

console.log(phpExiste
  ? '\nExécution en PHP natif (aucune dépendance npm requise).'
  : '\nPHP natif absent : exécution via le runtime WASM (@php-wasm/node).');

console.log(`\n${'='.repeat(64)}\n▸ Linter strict types — scripts/strict_types_lint.php\n${'='.repeat(64)}`);
const lint = lancer('scripts/strict_types_lint.php');
if (lint.status !== 0) ++échecs;

for (const [nom, fichier] of suites) {
  console.log(`\n${'='.repeat(64)}\n▸ ${nom} — ${fichier}\n${'='.repeat(64)}`);
  const r = lancer(fichier);
  if (r.status !== 0) ++échecs;
}

console.log(`\n${'='.repeat(64)}\n${'='.repeat(64)}`);
if (échecs > 0) {
  console.log(`SUITES : ${échecs} étape(s) en échec ❌ — E2E non joué`);
  process.exit(1);
}
console.log('SUITES PHP : TOUT EST VERT ✅ — E2E :');

// L'E2E pilote l'application par requêtes HTTP internes : il repose sur le
// runtime WASM. Sans PHP natif, il tourne partout ; avec PHP natif, il faut
// le paquet npm — on l'annonce clairement au lieu d'échouer salement.
const e2eDisponible = !phpExiste || spawnSync('node', ['-e', "require.resolve('@php-wasm/node')"]).status === 0;
if (!e2eDisponible) {
  console.log('⏭  E2E ignoré : @php-wasm/node absent (npm install).');
  console.log('   Les 8 suites PHP ci-dessus sont vertes ; pour l’E2E complet :');
  console.log('     npm install && npm run test:e2e');
  process.exit(0);
}

// l'E2E exige un magasin propre et écrit (seed + parcours) : scripts/e2e.mjs
// photographie data/minishop avant, le restaure après — même en cas d'échec.
const e2e = spawnSync('node', ['scripts/e2e.mjs'], { stdio: 'inherit' });
if (e2e.status !== 0) {
  console.log('E2E : ÉCHEC ❌');
  process.exit(1);
}
console.log('\nTOUTES LES SUITES SONT VERTES ✅ (moteur, règles, unitaires, fonctionnalités, design, responsive, PHPUnit, E2E)');
