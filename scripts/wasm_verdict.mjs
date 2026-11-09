/**
 * MiniShop — verdict d'exécution d'un script PHP lancé via PHP-WASM.
 *
 * `php.run()` renvoie `result.exitCode`, mais celui-ci provient du gestionnaire
 * de requêtes PHP-WASM (`wasm_sapi_handle_request`) et non du CLI. Mesuré sur
 * ce dépôt :
 *
 *   script terminé normalement        → 1     ← inversion
 *   `exit(0)` / `exit(1)` / `exit(3)` → 0     ← le code demandé est perdu
 *   erreur fatale                     → 0
 *
 * Propager cette valeur produirait deux mensonges symétriques : des suites
 * vertes comptées en échec, et surtout des suites en échec comptées vertes —
 * un « TOUT EST VERT » qui ne prouve rien. Le verdict est donc établi sur des
 * signaux observables dans la sortie, dont le sens est fixé par la convention
 * de ce dépôt : les suites n'impriment « ❌ » que sur un contrôle en échec.
 *
 * Autotest : node scripts/wasm_verdict.mjs
 */

/** Codes de sortie, alignés sur la convention Unix et sur `run-all-tests`. */
export const VERT = 0;
export const ECHEC = 1;
export const NON_JOUE = 2;
export const ERREUR_PHP = 255;

/**
 * Traduit la sortie complète d'un script (stdout + stderr) en code de sortie.
 *
 * L'ordre des règles n'est pas décoratif : une erreur fatale peut précéder un
 * « ✅ » déjà imprimé, et une suite non jouable n'imprime aucun « ✅ ». Tester
 * « NON JOUÉ » avant l'échec transformerait un plantage en simple « non joué ».
 */
export function verdict(sortie) {
  if (/PHP (Fatal error|Parse error)/.test(sortie)) return ERREUR_PHP;
  if (sortie.includes('❌')) return ECHEC;
  // `✘` préfixe les erreurs des outils en ligne de commande (`db_init.php`,
  // `capture_perf.sh`). Aucune suite verte n'en imprime : mesuré sur les 9
  // suites. Sans cette règle, `db_init.php --test-db` en échec sortait en 0.
  if (sortie.includes('✘')) return ECHEC;
  if (sortie.includes('NON JOUÉ') && !sortie.includes('✅')) return NON_JOUE;
  return VERT;
}

// ---------------------------------------------------------------- autotest
// Exécuté uniquement quand ce fichier est lancé directement :
//   node scripts/wasm_verdict.mjs
const lanceDirectement = process.argv[1] !== undefined
  && import.meta.url === new URL(`file://${process.argv[1]}`).href;

if (lanceDirectement) {
  const cas = [
    ['suite verte', 'RÈGLES : 29/29 CONFORMES ✅', VERT],
    ['suite en échec', '✅ 25 contrôles\n❌ 1 contrôle en échec', ECHEC],
    ['suite non jouable', '⏭ NON JOUÉ : serveur absent', NON_JOUE],
    ['erreur de syntaxe', 'PHP Parse error: syntax error', ERREUR_PHP],
    ['fatale après des ✅', '✅ 3 contrôles\nPHP Fatal error: OOM', ERREUR_PHP],
    ['non joué avec une réussite partielle', '✅ 3 contrôles\nNON JOUÉ : E2E', VERT],
    ['sortie vide', '', VERT],
    ['outil CLI en échec', "✘ création automatique impossible", ECHEC],
    ['outil CLI, avertissement sans échec', 'ⓘ extension facultative absente : intl', VERT],
  ];

  let echecs = 0;
  for (const [nom, sortie, attendu] of cas) {
    const obtenu = verdict(sortie);
    const ok = obtenu === attendu;
    if (!ok) ++echecs;
    console.log(`${ok ? '  ✅' : '  ❌'} ${nom} → ${obtenu}${ok ? '' : ` (attendu ${attendu})`}`);
  }
  console.log(echecs === 0
    ? `\nVERDICT WASM : TOUT EST VERT ✅ (${cas.length} cas)`
    : `\nVERDICT WASM : ${echecs} échec(s) ❌`);
  process.exit(echecs === 0 ? VERT : ECHEC);
}
