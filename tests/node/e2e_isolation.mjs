/** Exécute l'E2E sur une copie jetable et vérifie l'intégrité des données hôte. */
import { mkdtempSync, mkdirSync, cpSync, writeFileSync, readFileSync, readdirSync, rmSync, symlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import assert from 'node:assert/strict';

const racine = join(import.meta.dirname, '../..');
const copie = mkdtempSync(join(tmpdir(), 'minishop-e2e-isolation-'));
try {
  for (const nom of ['app', 'public', 'scripts', 'tests', 'sql']) {
    cpSync(join(racine, nom), join(copie, nom), { recursive: true });
  }
  symlinkSync(join(racine, 'node_modules'), join(copie, 'node_modules'), 'dir');
  const donnees = join(copie, 'data/minishop');
  mkdirSync(donnees, { recursive: true });
  const temoins = {
    'produit.json': 'données personnelles factices, hors format JSON\n',
    'client.json': 'ne pas remplacer\n',
    'journal.jsonl': 'ne pas restaurer\n',
  };
  for (const [nom, valeur] of Object.entries(temoins)) writeFileSync(join(donnees, nom), valeur);
  const test = spawnSync(process.execPath, ['scripts/e2e.mjs'], {
    cwd: copie, encoding: 'utf8', timeout: 180_000,
  });
  assert.equal(test.status, 0, `E2E en échec : ${test.error ?? ''}\n${test.stdout}\n${test.stderr}`);
  assert.match(test.stdout, /E2E : TOUT EST VERT ✅/);
  assert.deepEqual(readdirSync(donnees).sort(), Object.keys(temoins).sort());
  for (const [nom, valeur] of Object.entries(temoins)) {
    assert.equal(readFileSync(join(donnees, nom), 'utf8'), valeur, `${nom} a été modifié`);
  }
  console.log('✅ E2E isolé : données hôte inchangées et parcours réussis.');
  console.log(test.stdout.trim().split('\n').at(-1));
} finally {
  rmSync(copie, { recursive: true, force: true });
}
