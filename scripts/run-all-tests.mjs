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
 *   5. wasm-e2e.mjs       — parcours HTTP complet (seed requis, pollue data/)
 */
import { spawnSync } from 'node:child_process';

const suites = [
  ['Heure / fuseaux (régression)', 'tests/php/heure_test.php'],
  ['Adresse de livraison (régression)', 'tests/php/adresse_livraison_test.php'],
  ['Actions définitives / confirmation (régression)', 'tests/php/actions_destructrices_test.php'],
  ['Stocks mode par défaut (régression)', 'tests/php/stocks_defaut_test.php'],
  ['Pages du pied de page (régression)', 'tests/php/pages_pied_de_page_test.php'],
  ['Catalogue filtres en haut (régression)', 'tests/php/catalogue_filtres_test.php'],
  ['Fiche produit caractéristiques (régression)', 'tests/php/fiche_produit_test.php'],
  ['Panier tableau, mise en page (régression)', 'tests/php/panier_tableau_test.php'],
  ['Commandes admin, colonne Statut (régression)', 'tests/php/commandes_tableau_test.php'],
  ['Catégories admin, page tenue en page (régression)', 'tests/php/categories_tableau_test.php'],
  ['Alignement Produit/Référence panier, stocks, alertes (régression)', 'tests/php/alignement_colonnes_test.php'],
  ['Formulaires produit, catégories, produits, catalogue (régression)', 'tests/php/formulaires_admin_test.php'],
  ['Divers panier, Top 5, photos, description, pied de page, contact (régression)', 'tests/php/divers_test.php'],
  ['Codes techniques visibles (régression)', 'tests/php/codes_techniques_test.php'],
  ['CGV article 4, annulation avant expédition (régression)', 'tests/php/cgv_retractation_test.php'],
  ['Page À propos (régression)', 'tests/php/apropos_test.php'],
  ['Bouton panier (régression)', 'tests/php/panier_cta_test.php'],
  ['Doublon catégorie (régression)', 'tests/php/categorie_doublon_test.php'],
  ['Moteur JSON', 'tests/php/engine_smoke.php'],
  ['Règles de gestion', 'tests/php/run_tests.php'],
  ['Unitaires', 'tests/php/unit_tests.php'],
  ['Fonctionnalités', 'tests/php/features_test.php'],
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

// l'E2E exige un magasin propre (il le pollue par construction : syncBack)
const seed = spawnSync('node', ['scripts/wasm_run.mjs', 'scripts/seed.php'], { stdio: 'inherit' });
const e2e = seed.status === 0
  ? spawnSync('node', ['scripts/wasm-e2e.mjs'], { stdio: 'inherit' })
  : { status: 1 };
if (e2e.status !== 0) {
  console.log('E2E : ÉCHEC ❌');
  process.exit(1);
}

// E2E UI (clics réels sur les pages, jsdom) : il faut un magasin propre,
// l'E2E HTTP vient de le polluer — re-seed avant de le lancer.
console.log(`\n${'='.repeat(64)}\n▸ E2E UI Catégories (clics) — scripts/ui-e2e-categories.mjs\n${'='.repeat(64)}`);
const reseed = spawnSync('node', ['scripts/wasm_run.mjs', 'scripts/seed.php'], { stdio: 'inherit' });
const ui = reseed.status === 0
  ? spawnSync('node', ['scripts/ui-e2e-categories.mjs'], { stdio: 'inherit' })
  : { status: 1 };
if (ui.status !== 0) {
  console.log('E2E UI : ÉCHEC ❌');
  process.exit(1);
}

console.log(`\n${'='.repeat(64)}\n▸ E2E UI Panier (clics) — scripts/ui-e2e-panier.mjs\n${'='.repeat(64)}`);
const uiPanier = spawnSync('node', ['scripts/ui-e2e-panier.mjs'], { stdio: 'inherit' });
if (uiPanier.status !== 0) {
  console.log('E2E UI panier : ÉCHEC ❌');
  process.exit(1);
}

console.log(`\n${'='.repeat(64)}\n▸ E2E UI À propos — scripts/ui-e2e-apropos.mjs\n${'='.repeat(64)}`);
const uiApropos = spawnSync('node', ['scripts/ui-e2e-apropos.mjs'], { stdio: 'inherit' });
if (uiApropos.status !== 0) {
  console.log('E2E UI À propos : ÉCHEC ❌');
  process.exit(1);
}

console.log(`\n${'='.repeat(64)}\n▸ E2E UI Heure — scripts/ui-e2e-heure.mjs\n${'='.repeat(64)}`);
const uiHeure = spawnSync('node', ['scripts/ui-e2e-heure.mjs'], { stdio: 'inherit' });
if (uiHeure.status !== 0) {
  console.log('E2E UI Heure : ÉCHEC ❌');
  process.exit(1);
}

console.log(`\n${'='.repeat(64)}\n▸ E2E UI Annulation / suppressions confirmées — scripts/ui-e2e-annulation.mjs\n${'='.repeat(64)}`);
const uiAnnulation = spawnSync('node', ['scripts/ui-e2e-annulation.mjs'], { stdio: 'inherit' });
if (uiAnnulation.status !== 0) {
  console.log('E2E UI Annulation : ÉCHEC ❌');
  process.exit(1);
}

console.log(`\n${'='.repeat(64)}\n▸ E2E UI Stocks — scripts/ui-e2e-stocks.mjs\n${'='.repeat(64)}`);
const uiStocks = spawnSync('node', ['scripts/ui-e2e-stocks.mjs'], { stdio: 'inherit' });
if (uiStocks.status !== 0) {
  console.log('E2E UI Stocks : ÉCHEC ❌');
  process.exit(1);
}

console.log(`\n${'='.repeat(64)}\n▸ E2E UI Pied de page — scripts/ui-e2e-pied-de-page.mjs\n${'='.repeat(64)}`);
const uiPiedDePage = spawnSync('node', ['scripts/ui-e2e-pied-de-page.mjs'], { stdio: 'inherit' });
if (uiPiedDePage.status !== 0) {
  console.log('E2E UI Pied de page : ÉCHEC ❌');
  process.exit(1);
}

console.log(`\n${'='.repeat(64)}\n▸ E2E UI Catalogue (mobile) — scripts/ui-e2e-catalogue.mjs\n${'='.repeat(64)}`);
const uiCatalogue = spawnSync('node', ['scripts/ui-e2e-catalogue.mjs'], { stdio: 'inherit' });
if (uiCatalogue.status !== 0) {
  console.log('E2E UI Catalogue : ÉCHEC ❌');
  process.exit(1);
}

console.log(`\n${'='.repeat(64)}\n▸ E2E UI Fiche produit — scripts/ui-e2e-produit.mjs\n${'='.repeat(64)}`);
const uiProduit = spawnSync('node', ['scripts/ui-e2e-produit.mjs'], { stdio: 'inherit' });
if (uiProduit.status !== 0) {
  console.log('E2E UI Produit : ÉCHEC ❌');
  process.exit(1);
}

console.log(`\n${'='.repeat(64)}\n▸ E2E UI Panier tableau — scripts/ui-e2e-panier-tableau.mjs\n${'='.repeat(64)}`);
const uiPanierTableau = spawnSync('node', ['scripts/ui-e2e-panier-tableau.mjs'], { stdio: 'inherit' });
if (uiPanierTableau.status !== 0) {
  console.log('E2E UI Panier tableau : ÉCHEC ❌');
  process.exit(1);
}

console.log(`\n${'='.repeat(64)}\n▸ E2E UI Commandes admin (colonne Statut) — scripts/ui-e2e-commandes-tableau.mjs\n${'='.repeat(64)}`);
const uiCommandesTableau = spawnSync('node', ['scripts/ui-e2e-commandes-tableau.mjs'], { stdio: 'inherit' });
if (uiCommandesTableau.status !== 0) {
  console.log('E2E UI Commandes admin : ÉCHEC ❌');
  process.exit(1);
}

console.log(`\n${'='.repeat(64)}\n▸ E2E UI Catégories admin (page tenue en page) — scripts/ui-e2e-categories-tableau.mjs\n${'='.repeat(64)}`);
const uiCategoriesTableau = spawnSync('node', ['scripts/ui-e2e-categories-tableau.mjs'], { stdio: 'inherit' });
if (uiCategoriesTableau.status !== 0) {
  console.log('E2E UI Catégories admin : ÉCHEC ❌');
  process.exit(1);
}

console.log(`\n${'='.repeat(64)}\n▸ E2E UI Alignement Produit/Référence — scripts/ui-e2e-alignement.mjs\n${'='.repeat(64)}`);
const uiAlignement = spawnSync('node', ['scripts/ui-e2e-alignement.mjs'], { stdio: 'inherit' });
if (uiAlignement.status !== 0) {
  console.log('E2E UI Alignement : ÉCHEC ❌');
  process.exit(1);
}

console.log(`\n${'='.repeat(64)}\n▸ E2E UI Formulaires (produit, catégories, produits, catalogue) — scripts/ui-e2e-formulaires.mjs\n${'='.repeat(64)}`);
const uiFormulaires = spawnSync('node', ['scripts/ui-e2e-formulaires.mjs'], { stdio: 'inherit' });
if (uiFormulaires.status !== 0) {
  console.log('E2E UI Formulaires : ÉCHEC ❌');
  process.exit(1);
}

console.log(`\n${'='.repeat(64)}\n▸ E2E UI Divers — scripts/ui-e2e-divers.mjs\n${'='.repeat(64)}`);
const uiDivers = spawnSync('node', ['scripts/ui-e2e-divers.mjs'], { stdio: 'inherit' });

console.log(`\n${'='.repeat(64)}\n▸ E2E UI Codes techniques — scripts/ui-e2e-codes.mjs\n${'='.repeat(64)}`);
const uiCodes = spawnSync('node', ['scripts/ui-e2e-codes.mjs'], { stdio: 'inherit' });

console.log(`\n${'='.repeat(64)}\n▸ E2E UI CGV article 4 — scripts/ui-e2e-retractation.mjs\n${'='.repeat(64)}`);
const uiRetractation = spawnSync('node', ['scripts/ui-e2e-retractation.mjs'], { stdio: 'inherit' });
if (uiDivers.status !== 0) {
  console.log('E2E UI Divers : ÉCHEC ❌');
  process.exit(1);
}
if (uiCodes.status !== 0) {
  console.log('E2E UI Codes techniques : ÉCHEC ❌');
  process.exit(1);
}
if (uiRetractation.status !== 0) {
  console.log('E2E UI CGV article 4 : ÉCHEC ❌');
  process.exit(1);
}
console.log('\nTOUTES LES SUITES SONT VERTES ✅ (moteur, règles, unitaires, fonctionnalités, E2E HTTP, E2E UI)');
