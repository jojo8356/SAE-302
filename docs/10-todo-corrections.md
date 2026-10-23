# 10 — Todolist des corrections (audit `09`) — état **vérifié**

> Suivi d'exécution de l'audit [`09-audit-ecarts.md`](09-audit-ecarts.md).
> Légende : `[x]` fait **et vérifié sur disque** · `[ ]` **toujours manquant** · `[~]` fait autrement, justifié · `[!]` bloqué par l'environnement.
> Chaque ligne indique le fichier concerné et la commande qui permet de le constater.
>
> **Dernière vérification :** 2026-10-23 · branche `arena/6e1d3706-sae-302`.
> Cette version remplace la précédente, qui cochait des livrables **absents du dépôt** (voir §8).

---

## 0. Bloquants — installation, CI, base

- [x] `scripts/load_db.sh` — chargement `sql/01→04` en une commande. Repli `mysql` si PHP absent ; nom de base paramétrable (`DB=minishop_test`).
- [x] `scripts/load_db.php` — moteur PDO du précédent (aucun client `mysql` requis, `DELIMITER` géré).
- [x] `scripts/run_sql_tests.sh` — enveloppe officielle des 29 tests (délègue à `tests/sql/run_tests.sh`).
- [x] `scripts/gen_volumes.sh` — enveloppe de `scripts/gen_volumes_cli.php`.
- [x] `scripts/gen_volumes_cli.php` — `--load` n'utilise plus de sous-processus (contrôle 9) : chargement par `App\Tools\SqlLoader`.
- [x] `app/Tools/SqlLoader.php` — découpage SQL (`DELIMITER`, chaînes, commentaires) + comptage des objets créés.
- [ ] `scripts/build-docs.sh` — **absent**. Aucun rendu documentaire ne peut être produit par le dépôt.
- [x] `docs/diagrams/render.sh` — présent (non versionné jusqu'ici : voir §8).
- [x] `docs/diagrams/src/*.puml` — **14 sources** (cas d'usage ×3, classes, séquences ×5, activité, états, architecture, MCD, MLD).
- [x] `public/css/`, `public/js/`, `public/img/` — design system, JS, SVG (voir §2).
- [x] `tests/charge/reserver.sh` — présent (20 demandes / 12 exemplaires).
- [x] `tests/perf/mesurer.sh` — présent (+ `mesurer_sql.sh`).
- [x] `.gitlab/merge_request_templates/{Lot,Bugfix,Docs,Release}.md` — présents.
- [x] **PHPUnit** — `composer.json` (`phpunit/phpunit ^11`), `phpunit.xml`, `tests/phpunit/` (6 classes, 34 tests) + runner hors ligne.
- [ ] CI GitLab — `base-tests` et `unit` ont désormais leurs scripts, mais le job `docs` échoue encore (`render.sh` absent) ; aucun pipeline n'a été exécuté ici.
- [ ] CI GitHub — le job `unit` reste tolérant : aucun PHPUnit réel n'a été joué dans cette sandbox.
- [x] `var/.gitkeep` — présent.

## 1. Base de données / PDO (barème iii)

- [x] `app/Model/Data/SqlStore.php` — **implémentation PDO réelle** : `select/find/count/sum/exists/all`, `insert/update/delete/nextId`, transactions, `appeler()` pour les procédures, traduction `SQLSTATE 45000 → BusinessError` et `23000 → ConstraintError`, `PDO::ATTR_EMULATE_PREPARES = false`, noms de tables/colonnes validés par `Schema`.
- [x] Contrat unifié : `StoreInterface::transactional()` ajouté (implémenté par `JsonStore` **et** `SqlStore`).
- [ ] **Les procédures stockées ne sont toujours pas appelées par les dépôts** : `SqlStore::appeler('sp_…')` existe mais aucun `Repository` ne l'utilise. Avec `storage_driver = 'sql'`, l'application écrit en SQL direct — les 16 déclencheurs s'exécutent, les 17 procédures non. La phrase du CDC §16.2 (« effectivement appelées ») reste **fausse**.
- [ ] `tests/php/sql_integration.php` — **absent** : aucune exécution MySQL dans cette sandbox.
- [ ] Documentation de bascule (`storage_driver = 'sql'`) — non écrite.

## 2. Design (demande explicite) — **fait**

- [x] `docs/DA.md` — étude de 5 concurrents (patterns observables) → direction artistique (palette + ratios, typographie système, grille 8 px, 12 composants, microcopy, bornes).
- [x] `public/css/app.css` — design system (35 tokens, composants, responsive, `:focus-visible`, `prefers-reduced-motion`, `prefers-contrast`).
- [x] `public/css/print.css` — feuille d'impression.
- [x] `public/js/panier.js`, `public/js/ui.js` — panier sans rechargement (`fetch`, `aria-live`, badge), validations et focus.
- [x] `public/img/{logo,logo-mark,favicon,illustration-achat}.svg`.
- [x] `app/View/layout.php` — skip-link, nav `aria-current`, badge panier, flashs, pied de page.
- [x] `app/Controller/ApiController.php` + routes `/api/panier/*` — JSON, CSRF, montants serveur.
- [x] CSP alignée (`style-src 'self'`, `script-src 'self'`) sans affaiblir le reste.

## 3. Fonctionnel

- [x] Page **contact** (`GET/POST /contact`, CSRF, accusé de réception, journalisation).
- [x] Route **`/mentions`** (alias de `/mentions-legales`) et `/aide`.
- [x] Filtre **`/admin/stocks?etat=…`** + tri par criticité.
- [x] Écran **paramètres** `/admin/parametres` (frais de port, franchise, TVA par défaut), garde `admin`.
- [x] Indicateurs **du jour** + cumul au tableau de bord.
- [ ] Alias `POST /admin/produits` — **absent** (`public/index.php` n'expose que `GET /admin/produits` et `POST /admin/produit/nouveau`).
- [x] 404/403/500 habillées par le design system.

## 4. Tests « grandes entreprises »

- [x] Pyramide réelle : `tests/phpunit` (unitaires) → `tests/php/{engine_smoke,run_tests,unit_tests,features_test,css_contract}` (intégration/contrats) → `scripts/wasm-e2e.mjs` (E2E HTTP) → `tests/security`.
- [x] **PHPUnit** : 6 classes, **34 tests** (`TextTest`, `FilterTest`, `PrixTest`, `TriggersTest`, `JsonStoreTest`, `SecurityContractTest`).
- [x] `tests/phpunit/run-local.php` — runner hors ligne (`npm run test:phpunit`).
- [x] `tests/php/css_contract.php` — 28 vérifications (tokens, contrastes AA, classes des vues, a11y, intégration).
- [ ] `scripts/coverage.php` — **absent** ; `phpunit.xml` ne produit pas de couverture.
- [ ] `tests/php/contract_http.php` — **absent** : les contrats HTTP ne sont couverts que par l'E2E.
- [ ] `docs/11-strategie-tests.md` — **absent**.
- [x] `npm run test:all` exécute **7 suites** + l'E2E (moteur, règles, unitaires, fonctionnalités, design, responsive, PHPUnit) ; le linter strict-types est un contrôle séparé.

## 5. Rendus — **rien de produit**

- [ ] HTML autonome des documents — absent (`livrables/` n'existe pas).
- [ ] DOCX — absent.
- [ ] PDF — absent (le seul PDF du dépôt est `docs/sql-a-ecrire/requetes-sql-a-ecrire.pdf`).
- [ ] Diaporama 12 slides — absent.
- [!] Vidéo de secours 3 min — non produisible sans navigateur/encodeur.
- [ ] `livrables/README.md` — absent.
- Outillage disponible : `scripts/md_to_pdf.py` (fpdf2) — jamais exécuté sur les documents.

## 6. Cohérence documentaire — **aucune correction appliquée**

| Point | État | Preuve |
|---|---|---|
| 29 tests SQL partout | [ ] | `docs/03` : « 28/28 » (l. 13, 27) et « 27 / 27 » ; `.gitlab-ci.yml` : « 28 tests » |
| E2E 180 | [x] | `README.md` corrigé (T1…T14, 180/180) |
| PHP 8.2 minimum | [x] | prérequis README aligné sur `composer.json` |
| `ENF-13` dupliqué → `ENF-22` | [ ] | `annexe-enf.md` : deux lignes `ENF-13` (l. 20 et 34), aucun `ENF-22` |
| MCD 8 entités | [ ] | `docs/03` l. 23 : « MCD (7 entités) » |
| Noms `01_minishop_schema.sql` | [ ] | `docs/07` et `docs/09` citent encore `01-minishop-schema.sql` |
| CDC §16.1/§16.4 à jour | [ ] | §16.1 : « à développer » / « à produire » ; §16.4 : « le code PHP n'est pas encore écrit » |
| Doc `02` : 33 `F-xx` exécutés + PV | [ ] | 33 lignes « conçu » ; « PV de recette laissé à compléter » ; « lots L8→L13 » |
| Doc `03` aligné sur l'application | [ ] | scénario encore écrit pour un terminal MySQL |
| README : arborescence, chiffres, badges | [~] | chiffres et « zéro CSS » corrigés ; badge GitLab toujours factice (`gitlab.example.com`) |

## 7. Reste à faire hors dépôt (ne peut pas être fait par un agent)

- [!] **Noms, prénoms, numéros étudiants** des 3 membres + emails → README §Membres et CDC §3.3 (aucune identité ne peut être inventée).
- [!] **Dépôt GitLab réel** + courriel « Composition SAE » à la référente ; badge et URLs à remplacer.
- [!] **Rejeu des 32 commits** par les 3 personnes (`docs/08` + `scripts/rejeu_gitlab.py`) : l'historique doit être individuel et daté, il ne peut pas être fabriqué ici (le dépôt n'a qu'un commit).
- [!] **Couverture PHPUnit chiffrée** : nécessite Xdebug sur une machine réelle.
- [!] **Mesures ENF-01/ENF-08/ENF-15** : nécessitent un serveur HTTP et MySQL réels.
- [!] **Vidéo de secours** de la démo.

---

## 8. Ce que la version précédente de ce document affirmait à tort

Deux erreurs distinctes, toutes deux vérifiées le 23/10/2026 :

1. **Des livrables cochés `[x]` n'existaient pas du tout** :
   `scripts/build-docs.sh` · `scripts/coverage.php` · `tests/php/contract_http.php` ·
   `tests/php/sql_integration.php` · `docs/11-strategie-tests.md` · `livrables/**` (HTML, DOCX, PDF, PPTX) ·
   les appels des **procédures stockées** par les dépôts · les **11 corrections de chiffres** du §6.
2. **Des livrables existaient sur le disque mais n'étaient pas versionnés** (`git status` les listait
   en `??`) : `docs/diagrams/render.sh`, `docs/diagrams/src/*.puml` (14), `tests/charge/reserver.sh`,
   `tests/perf/mesurer.sh`, `.gitlab/merge_request_templates/*.md`, `var/.gitkeep`, `public/css|js|img`,
   `tests/phpunit/**`, `docs/09`, `docs/10`. Un clone du dépôt ne les contenait pas : ils sont
   désormais suivis par Git (voir le commit de cette passe).

---

## Commandes de vérification (état réel)

```bash
npm install                            # une fois (PHP-WASM)
node scripts/run-all-tests.mjs         # 6 suites + E2E — attendu : tout vert
npm run test:phpunit                   # 34 tests PHPUnit hors ligne
npm run test:design                    # 28 contrats de design
bash tests/security/controles.sh       # 10/10 conformes
bash -n scripts/{load_db,run_sql_tests,gen_volumes}.sh
ls scripts/build-docs.sh docs/diagrams/render.sh tests/perf/mesurer.sh   # → introuvables
```
