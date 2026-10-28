/** Autotest isolé du garde d'exécution avec PHP 8.2 WASM. */
import { createPhp } from '../../scripts/wasm_lib.mjs';
import { verdict } from '../../scripts/wasm_verdict.mjs';

const php = await createPhp();
php.setPhpIniEntry('error_reporting', '-1');
php.setPhpIniEntry('auto_prepend_file', '/repo/tests/php/deprecations_bootstrap.php');
const cas = [
  ['sain', '<?php echo "✅ sain";', 0],
  ['dépréciation utilisateur masquée et sortie tamponnée', '<?php ob_start(); @trigger_error("fixture", E_USER_DEPRECATED); ob_end_clean(); exit(0);', 1],
  ['dépréciation native PHP 8.2', '<?php class X {} $x = new X(); $x->propriete = 1; exit(0);', 1],
];
let echecs = 0;
for (const [nom, code, attendu] of cas) {
  const fichier = '/tmp/fixture-deprecations.php';
  await php.writeFile(fichier, new TextEncoder().encode(code));
  const result = await php.run({ scriptPath: fichier });
  const sortie = `${result.text ?? ''}\n${result.errors ?? ''}`;
  const obtenu = verdict(sortie);
  if (obtenu !== attendu) {
    ++echecs;
    console.log(`❌ ${nom} : attendu ${attendu}, reçu ${obtenu} : ${sortie}`);
  } else {
    console.log(`✅ ${nom}`);
  }
}
console.log(`Autotest dépréciations PHP-WASM : ${cas.length - echecs}/${cas.length}`);
process.exit(echecs ? 1 : 0);
