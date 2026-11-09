/**
 * Ancien utilitaire de photographie/restauration, conservé pour compatibilité.
 * L'E2E ne l'utilise plus : il travaille exclusivement dans le runtime WASM.
 * Attention : une restauration peut écraser les changements concurrents et
 * n'est pas garantie après SIGKILL, panne ou arrêt brutal. Ne l'employer que
 * sur un dossier temporaire isolé, jamais sur les données utilisateur.
 */

import { readdirSync, readFileSync, rmSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

/** Photo en mémoire des fichiers `.json` d'un dossier (contenu binaire exact). */
export function photographier(dossier) {
  if (!existsSync(dossier)) return new Map();
  return new Map(
    readdirSync(dossier)
      .filter((nom) => nom.endsWith('.json') || nom.endsWith('.jsonl'))
      .map((nom) => [nom, readFileSync(join(dossier, nom))]),
  );
}

/** Remet le dossier exactement dans l'état photographié (ajouts compris). */
export function restaurer(dossier, photo) {
  if (!existsSync(dossier)) mkdirSync(dossier, { recursive: true });
  for (const nom of readdirSync(dossier)) {
    if ((nom.endsWith('.json') || nom.endsWith('.jsonl')) && !photo.has(nom)) {
      rmSync(join(dossier, nom), { force: true });
    }
  }
  for (const [nom, contenu] of photo) writeFileSync(join(dossier, nom), contenu);
}

/**
 * Exécute une fonction et tente la restauration en fin normale / SIGINT / SIGTERM.
 * Ne protège pas contre une modification concurrente ou une interruption brutale.
 * @template T
 * @param {string} dossier
 * @param {() => Promise<T>|T} action
 * @returns {Promise<T>}
 */
export async function avecDonneesIntactes(dossier, action) {
  const photo = photographier(dossier);
  let restaure = false;
  const remettre = () => {
    if (restaure) return;
    restaure = true;
    restaurer(dossier, photo);
  };
  process.on('exit', remettre);
  for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => {
      remettre();
      process.exit(130);
    });
  }
  try {
    return await action();
  } finally {
    remettre();
  }
}
