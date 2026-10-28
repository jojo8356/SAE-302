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
 *   4 bis. catalogue_stock_test.php — ruptures, recherches, filtres et pages
 *   4 ter. nouveautes_test.php — dates, visibilité et derniers ajouts
 *   5. css_contract.php   — contrats du design system (tokens, contrastes, CSP)
 *   6. responsive_contract.php — robustesse responsive (320 px → 1536 px)
 *   6. tests/phpunit/**   — suite PHPUnit (exécutée par son runner hors ligne)
 *   7. e2e.mjs            — seed + parcours HTTP complet, données restaurées
 */
import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';

// Le drapeau bloque la provision applicative (DbProvision::contexteDeTest()).
// Attention : la suite SQL explicite peut créer et supprimer minishop_test.
process.env.MINISHOP_TESTS = '1';
const options = process.argv.slice(2);
if (options.some(option => !['--sans-sql', '--sans-e2e'].includes(option))) {
  console.error('Usage : node scripts/run-all-tests.mjs [--sans-sql] [--sans-e2e]');
  process.exit(2);
}
const sansSql = options.includes('--sans-sql');
const sansE2e = options.includes('--sans-e2e');

// Sur une machine avec PHP natif, AUCUNE dépendance npm n'est nécessaire :
// on exécute directement `php <script>`. Le runtime WASM (@php-wasm/node) ne
// sert que dans les environnements sans PHP (sandbox de développement).
const phpExiste = spawnSync('php', ['-r', 'echo PHP_VERSION;'], { encoding: 'utf8' }).status === 0;
if (phpExiste) {
  const natif = spawnSync('php', ['scripts/run-all-tests.php', ...options], { stdio: 'inherit' });
  process.exit(natif.status ?? 1);
}

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
  ['Catalogue : ruptures en dernier', 'tests/php/catalogue_stock_test.php'],
  ['Accueil : derniers ajouts', 'tests/php/nouveautes_test.php'],
  ['Accueil : dates des ajouts', 'tests/php/date_ajout_test.php'],
  ['Cartes produit : accessibilité', 'tests/php/cartes_accessibilite_test.php'],
  ['Contrats de design', 'tests/php/css_contract.php'],
  ['Contrat responsive', 'tests/php/responsive_contract.php'],
  ['Contrats HTTP', 'tests/php/contract_http.php'],
  ['Information juridique et données', 'tests/php/information_juridique_test.php'],
  ['Unitaires PHPUnit', 'tests/phpunit/run-local.php'],
];

let échecs = 0;

console.log(phpExiste
  ? '\nExécution en PHP natif (aucune dépendance npm requise).'
  : '\nPHP natif absent : exécution via le runtime WASM (@php-wasm/node).');

console.log('⏭ NON JOUÉ : analyse de compilation des dépréciations (PHP CLI natif absent).');
console.log('  Les suites WASM contrôlent les dépréciations à l’exécution avec PHP 8.2.');

console.log(`\n${'='.repeat(64)}\n▸ Autotests du contrôle des dépréciations\n${'='.repeat(64)}`);
const autoAnalyse = spawnSync('python3', ['tests/python/test_deprecations.py'], { stdio: 'inherit' });
if (autoAnalyse.status !== 0) ++échecs;
const autoWasm = spawnSync('node', ['tests/js/deprecations_wasm.mjs'], { stdio: 'inherit' });
if (autoWasm.status !== 0) ++échecs;

// Le harnais se vérifie lui-même : sans cela, une régression du calcul du
// verdict rendrait toutes les suites vertes par construction.
console.log(`\n${'='.repeat(64)}\n▸ Autotest du verdict WASM — scripts/wasm_verdict.mjs\n${'='.repeat(64)}`);
const autotest = spawnSync('node', ['scripts/wasm_verdict.mjs'], { stdio: 'inherit' });
if (autotest.status !== 0) ++échecs;

console.log(`\n${'='.repeat(64)}\n▸ Linter strict types — scripts/strict_types_lint.php\n${'='.repeat(64)}`);
const lint = lancer('scripts/strict_types_lint.php');
if (lint.status !== 0) ++échecs;

for (const [nom, fichier] of suites) {
  console.log(`\n${'='.repeat(64)}\n▸ ${nom} — ${fichier}\n${'='.repeat(64)}`);
  const r = lancer(fichier);
  if (r.status !== 0) ++échecs;
}

// Intégration SQL : code 2 = suite non jouable ici (pas de serveur), ce n'est
// pas un échec. La compter comme telle ferait échouer la sandbox sur une suite
// qu'elle ne peut structurellement pas exécuter.
let étatsql = 'NON JOUÉ ⏭ (serveur ou client MySQL absent)';
if (sansSql) {
  étatsql = 'NON JOUÉ ⏭ (--sans-sql)';
  console.log('⏭ NON JOUÉ : intégration SQL (--sans-sql).');
} else if (!existsSync('tests/php/sql_integration.php')) {
  console.log('  ❌ fichier absent');
  ++échecs;
  étatsql = 'fichier absent ❌';
} else {
  console.log(`\n${'='.repeat(64)}\n▸ Intégration SQL — tests/php/sql_integration.php\n${'='.repeat(64)}`);
  const sql = lancer('tests/php/sql_integration.php');
  if (sql.status === 0) étatsql = 'joué ✅';
  if (sql.status !== 0 && sql.status !== 2) {
    ++échecs;
    étatsql = 'ÉCHEC ❌';
  }
}

console.log(`\n${'='.repeat(64)}\n${'='.repeat(64)}`);
if (échecs > 0) {
  console.log(`SUITES : ${échecs} étape(s) en échec ❌ — E2E non joué`);
  process.exit(1);
}
console.log(`SUITES PHP EXÉCUTÉES : TOUT EST VERT ✅ — analyse de compilation : NON JOUÉE — SQL : ${étatsql} — E2E :`);

// L'E2E pilote l'application par requêtes HTTP internes : il repose sur le
// runtime WASM. Sans PHP natif, il tourne partout ; avec PHP natif, il faut
// le paquet npm — on l'annonce clairement au lieu d'échouer salement.
if (sansE2e) {
  console.log('⏭ NON JOUÉ : E2E (--sans-e2e).');
  process.exit(0);
}
const e2eDisponible = !phpExiste || spawnSync('node', ['-e', "require.resolve('@php-wasm/node')"]).status === 0;
if (!e2eDisponible) {
  console.log('⏭  E2E ignoré : @php-wasm/node absent (npm install).');
  console.log('   Les 10 suites PHP ci-dessus sont vertes ; pour l’E2E complet :');
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
