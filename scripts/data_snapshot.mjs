/**
 * MiniShop — photographie / restauration du jeu de données.
 *
 * Les parcours E2E et le seed écrivent dans data/minishop (inscriptions,
 * commandes, mots de passe, produits de test). Lancer les tests ne doit
 * JAMAIS abîmer le jeu de démonstration livré : ce module prend une photo
 * des fichiers JSON avant l'exécution et la remet en place ensuite, y
 * compris après un échec ou un Ctrl-C.
 *
 *   import { photographier, restaurer } from './data_snapshot.mjs';
 *   const photo = photographier('data/minishop');
 *   try { ... } finally { restaurer('data/minishop', photo); }
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
 * Exécute une fonction en garantissant la restauration des données.
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
