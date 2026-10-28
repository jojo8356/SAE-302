# Rapport d’audit global du projet

**Date :** 28 octobre 2026 · **Périmètre :** fichiers versionnés de cette branche, pas la copie locale ni les fichiers ignorés de l’utilisateur. [Inventaire exhaustif des chemins](17-inventaire-projet.csv) · [Revue initiale du code potentiellement inutilisé](16-audit-code-mort.md) · [Todolist](10-todo-corrections.md).

L’inventaire couvre **394 fichiers versionnés** : 90 applicatifs, 26 publics, 71 tests, 36 scripts, 4 SQL source, 8 données, 14 copies SQL et captures, 57 documents, 59 livrables, 7 artefacts, 10 fichiers d’outillage/CI et 12 fichiers de configuration/racine. Aucun fichier vide détecté.

Cet audit combine un inventaire automatisé de tout le dépôt et une revue manuelle ciblée sur les anomalies et risques découverts. Il **ne démontre pas l’absence de tous les défauts** et n’est pas une lecture fonctionnelle ligne par ligne de chaque livrable. L’inventaire indique des contrôles de structure, pas une certification du contenu.

## Constats corrigés

| Élément | Problème et correction | Vérification |
|---|---|---|
| Journal JSON | `tail(0)` retournait tout le journal, une limite négative donnait un résultat incohérent. Une limite ≤ 0 renvoie `[]`. La doc n’affirme plus qu’un écran back-office le consulte. | Tests sur journal temporaire : absent, zéro, négatif, ordre, limites et ligne JSON invalide. |
| Provisionnement SQL | Les générateurs publics concaténaient des noms ou un mot de passe non validés. Ils refusent maintenant les identifiants dangereux ; le mot de passe doit provenir de `DbProvision::motDePasse()` (32 hexadécimaux). Le provisioning vérifie aussi la présence de `sudo` avant création du fichier temporaire. | Tests de génération seule avec entrées d’injection, sans exécuter SQL ni sudo. |
| E2E | La photographie/restauration pouvait écraser des modifications concurrentes ou échouer après interruption. L’E2E crée son jeu de démonstration dans le FS PHP-WASM uniquement : pas de chargement ni rapatriement du dossier `data/` hôte. L’ancien utilitaire est conservé mais avertit de son danger. | 303 contrôles HTTP WASM réussis en copie jetable contenant trois fichiers hôte témoins inchangés. Pas de navigateur. |
| Tests SQL | L’ancien test supprimait silencieusement sa cible, y compris une base existante ou celle de l’application si configurée comme cible. Il refuse `DB_NAME`, crée une base absente atomiquement, et n’autorise une réinitialisation qu’avec l’option explicite `--reinitialiser-base-existante`. | Garde-fous et campagne réelle ajoutés à la CI sur serveur MySQL jetable ; résultats CI à consulter sur le commit correspondant. Aucun serveur SQL local n’a été touché. |
| Contrat HTTP | Un test affirmait tester « 403/500 » en cherchant seulement `page404` ou `erreur`. Il vérifie désormais le code et la vue 500 ; le parcours gestionnaire est vérifié en E2E comme redirection 302 vers `/admin`, avec message et refus de création même avec CSRF valide. Suppression du `http_response_code(403)` immédiatement remplacé par 302. | 303 contrôles E2E ; contrat 500 statique, pas une injection d’erreur 500 réelle. |
| Git / sécurité | Les variantes `.env.*`, certificats P12/PFX et clés privées usuelles sont ignorés, sauf le modèle `.env.example` à la racine. Le scan reconnaît les worktrees Git (`.git` fichier) et les PFX. | Test d’ignore en dépôt temporaire ; scan complet exécuté depuis un worktree temporaire ; scan et autotest sécurité. Les règles `.gitignore` n’enlèvent pas un secret déjà suivi ni dans l’historique. |
| CI | Les jobs SQL et PHPUnit pouvaient masquer un échec via `|| true`. Les échecs des campagnes existantes sont maintenant bloquants ; un job E2E isolé et des vérifications SQL destructives sur service jetable ont été ajoutés. | À vérifier sur le nouveau run CI ; le vert des anciens jobs concernés n’était **pas** une preuve suffisante du succès de ces campagnes. Le job style reste explicitement non bloquant. |
| 11 candidats | Analyse détaillée dans `docs/16-audit-code-mort.md`. `Filter::isNotNull`, `Journal::tail`, `JsonTable::isDirty` ont des tests ; `sudoDisponible` est réellement utilisé. Les 7 autres alertes lexicales restent conservées par prudence. | Un test est une référence textuelle, **pas** une preuve d’usage en production ; aucun retrait d’API publique sur cette seule base. |

## Couverture par famille

- `app/`, `public/` : inventaire détaillé des 116 fichiers dans `docs/16-audit-code-mort.md`, revue ciblée des 11 candidats, contrats PHP, sécurité, parcours HTTP WASM. Le rendu visuel réel et tous les chemins ne sont pas démontrés.
- `scripts/`, `tests/`, CI et configuration racine : examen ciblé des runners, du provisionnement, de l’E2E, des tests SQL et des garde-fous Git ; syntaxe des scripts couverte par les contrôles ci-dessous. Certains outils d’installation, sauvegarde, déploiement, capture et performance peuvent modifier des données : usage sur environnement isolé, après lecture de leurs options.
- `sql/`, `tests/sql/` : fichiers inventoriés, scénarios et appels dangereux examinés. Les SQL directs peuvent réinitialiser leurs bases. La campagne SQL réelle n’a pas été lancée dans cette sandbox ; voir le résultat de la CI MySQL isolée, qui ne remplace pas MariaDB ni LineServ.
- `data/`, `uploads/`, ressources : fichiers inventoriés, JSON et SVG analysés structurellement. Les données de démo ne doivent pas être utilisées comme comptes actifs en production. Aucun contenu de compte n’est reproduit ici ; fichiers locaux ignorés et non suivis hors périmètre.
- `docs/`, `livrables/`, `artefacts/`, `.tools/`, `.gitlab/` : inventaire et contrôles de structure applicables, documentation des risques et rapports mis à jour. Les documents bureautiques sont testés comme archives ZIP, **pas** relus intégralement ni validés par affichage ; PDF, images, diagrammes et livrables ne sont pas tous contrôlés visuellement.

## Contrôles automatisés disponibles

`tests/python/test_audit_structurel.py` prend la liste `git ls-files` et vérifie présence/non-vide, synchronisation de l’inventaire CSV, compilation Python sans exécution, analyse JSON et SVG, intégrité interne ZIP des formats bureautiques, `bash -n` et `node --check` si présents. Il ne vérifie ni le comportement ni la qualité visuelle, et n’ouvre pas les fichiers ignorés. Les scripts PHP sont analysés séparément par `scripts/verifier_deprecations.py` et la CI.

- Sandbox : suites isolées PHP-WASM 8.2 vertes, dont 36 tests PHPUnit avec l’exécuteur local ; analyse PHP-WASM 8.5.10 sur 10 fichiers PHP modifiés sans erreur de syntaxe ou dépréciation détectée. Le code de retour WASM n’est pas une preuve native ; diagnostics examinés et CI native nécessaire.
- E2E : 303 vérifications réussies sans navigateur, données témoins inchangées. Security scan : 10 contrôles et autotest conformes. Tests structurels Python conformes.
- PHP natif, navigateur réel/WAVE, MariaDB et LineServ : **non joués dans cette sandbox**. La CI éventuelle utilise MySQL 8, pas MariaDB. Les anciennes campagnes ou mesures datées des autres documents restent historiques.

## Risques restants et auto-évaluation

1. `page403` et sa vue restent non branchées : la politique actuelle est une redirection 302 ; une vraie page 403 exigerait une décision fonctionnelle et des tests dédiés. `withStore` est redondante avec `for`, mais son retrait casserait potentiellement des appels externes. Les autres API publiques et le paramètre `$nom` sont conservés pour compatibilité.
2. L’option SQL de réinitialisation détruit volontairement sa cible. La comparaison avec `DB_NAME` ne protège pas les autres bases, alias, droits ou concurrents ; le harnais shell SQL direct ne possède pas cette protection. Utiliser exclusivement un serveur jetable et sauvegarder.
3. Les données de démonstration versionnées, les journaux et les fonctions d’installation imposent une vérification de configuration et de sécurité avant mise en ligne. Aucun examen de l’historique complet ni des secrets hors index n’est revendiqué.
4. Les contrôles lexicaux, de syntaxe et de conteneur ne remplacent ni audit de sécurité complet, ni revue métier, ni tests navigateur, ni campagne de recette sur l’hébergement. Les inclusions dynamiques et consommateurs externes peuvent échapper au scanner.

**Auto-évaluation :** points forts : corrections étayées par régressions isolées et nouveaux garde-fous bloquants ; faiblesses : revue manuelle ciblée plutôt qu’exhaustive, SQL et navigateur non disponibles localement, pas de garantie sur les environnements externes. À compléter par la lecture des résultats CI du commit et une recette réelle sur serveur jetable puis sur l’hébergement cible.
