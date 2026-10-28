# Rapport d’audit global du projet

**Date :** 28 octobre 2026 · **Périmètre :** fichiers versionnés de cette branche, pas la copie locale ni les fichiers ignorés de l’utilisateur. [Inventaire exhaustif des chemins](17-inventaire-projet.csv) · [Revue initiale du code potentiellement inutilisé](16-audit-code-mort.md) · [Todolist](10-todo-corrections.md).

L’inventaire couvre **396 fichiers versionnés** : 91 applicatifs, 26 publics, 72 tests, 36 scripts, 4 SQL source, 8 données, 14 copies SQL et captures, 57 documents, 59 livrables, 7 artefacts, 10 fichiers d’outillage/CI et 12 fichiers de configuration/racine. Aucun fichier vide détecté.

Cet audit combine un inventaire automatisé de tout le dépôt et une revue manuelle ciblée sur les anomalies et risques découverts. Il **ne démontre pas l’absence de tous les défauts** et n’est pas une lecture fonctionnelle ligne par ligne de chaque livrable. L’inventaire indique des contrôles de structure, pas une certification du contenu.

## Constats corrigés

| Élément | Problème et correction | Vérification |
|---|---|---|
| Journal JSON | `tail(0)` retournait tout le journal, une limite négative donnait un résultat incohérent. Une limite ≤ 0 renvoie `[]`. La doc n’affirme plus qu’un écran back-office le consulte. | Tests sur journal temporaire : absent, zéro, négatif, ordre, limites et ligne JSON invalide. |
| Provisionnement SQL | Les générateurs publics concaténaient des noms ou un mot de passe non validés. Ils refusent maintenant les identifiants dangereux ; le mot de passe doit provenir de `DbProvision::motDePasse()` (32 hexadécimaux). Le provisioning vérifie aussi la présence de `sudo` avant création du fichier temporaire. | Tests de génération seule avec entrées d’injection, sans exécuter SQL ni sudo. |
| E2E | La photographie/restauration pouvait écraser des modifications concurrentes ou échouer après interruption. L’E2E crée son jeu de démonstration dans le FS PHP-WASM uniquement : pas de chargement ni rapatriement du dossier `data/` hôte. L’ancien utilitaire est conservé mais avertit de son danger. | 303 contrôles HTTP WASM réussis en copie jetable contenant trois fichiers hôte témoins inchangés. Pas de navigateur. |
| Tests SQL | L’ancien test supprimait silencieusement sa cible, y compris une base existante ou celle de l’application si configurée comme cible. Il refuse `DB_NAME`, crée une base absente atomiquement, et n’autorise une réinitialisation qu’avec l’option explicite `--reinitialiser-base-existante`. | Garde-fous, intégration et 29 scénarios réussis sur le serveur MySQL jetable de la CI du commit `796aada`. Aucun serveur SQL local n’a été touché. |
| Tests nouveautés SQL | Le test partagé effectuait deux mises à jour sans condition, refusées par le pilote SQL. Il utilise désormais une condition explicite sur la clé primaire, sans supprimer la protection du pilote. | Cas JSON rejoué localement ; validation MySQL en CI sur base jetable. |
| Contrat HTTP | Un test affirmait tester « 403/500 » en cherchant seulement `page404` ou `erreur`. Il vérifie désormais le code et la vue 500 ; la garde SUPER déclenche désormais une réponse 403 et la page dédiée ; les visiteurs non connectés restent redirigés vers la connexion. | 309 contrôles E2E WASM ; tests HTTP natifs ajoutés en CI. Contrat 500 statique, pas une injection d’erreur 500 réelle. |
| Git / sécurité | Les variantes `.env.*`, certificats P12/PFX et clés privées usuelles sont ignorés, sauf le modèle `.env.example` à la racine. Le scan reconnaît les worktrees Git (`.git` fichier) et les PFX. | Test d’ignore en dépôt temporaire ; scan complet exécuté depuis un worktree temporaire ; scan et autotest sécurité. Les règles `.gitignore` n’enlèvent pas un secret déjà suivi ni dans l’historique. |
| Test des montants | Le seed JSON ne contient pas de commande : le test des montants parcourait des listes vides sans assertion. Il crée maintenant deux commandes réelles de montants différents et vérifie explicitement les lignes et totaux. | Exécuteur PHPUnit local ; validation native par CI. |
| PHPUnit natif | Le commentaire du fichier `phpunit.xml` contenait `--`, interdit en XML : PHPUnit arrêtait avant les tests. Commentaire corrigé et contrôle XML structurel ajouté. | Validation PHPUnit natif en CI ; exécuteur local WASM ne lit pas ce XML. |
| Chargeur SQL CLI | `scripts/load_db.php` appelait récursivement son propre gestionnaire d’erreur quand `STDERR` existe ; il écrit maintenant sur `STDERR`. | Test natif d’un nom de base invalide, sans connexion SQL ni écriture. |
| CI | Les jobs SQL et PHPUnit pouvaient masquer un échec via `|| true`. Les échecs des campagnes existantes sont maintenant bloquants ; la CI initialise aussi la base `minishop_test` utilisée par la campagne (elle chargeait seulement `minishop` auparavant). un job E2E isolé et des vérifications SQL destructives sur service jetable ont été ajoutés. | CI du commit `796aada` réussie ; le vert des anciens jobs concernés n’était **pas** une preuve suffisante du succès de ces campagnes. Le job style reste explicitement non bloquant. |
| 11 candidats | Analyse détaillée dans `docs/16-audit-code-mort.md`. `Filter::isNotNull`, `Journal::tail`, `JsonTable::isDirty` ont des tests ; `sudoDisponible` est réellement utilisé. `page403` est désormais appelée ; `withStore` a été retirée sur demande. Les 5 autres alertes lexicales restent conservées par prudence. | `for()` est conservée et testée ; tout consommateur externe de `withStore()` doit migrer vers `CommandeRepository::for($store)`. |

## Couverture par famille

- `app/`, `public/` : inventaire détaillé des 117 fichiers dans `docs/16-audit-code-mort.md`, revue ciblée des 11 candidats, contrats PHP, sécurité, parcours HTTP WASM. Le rendu visuel réel et tous les chemins ne sont pas démontrés.
- `scripts/`, `tests/`, CI et configuration racine : examen ciblé des runners, du provisionnement, de l’E2E, des tests SQL et des garde-fous Git ; syntaxe des scripts couverte par les contrôles ci-dessous. Certains outils d’installation, sauvegarde, déploiement, capture et performance peuvent modifier des données : usage sur environnement isolé, après lecture de leurs options.
- `sql/`, `tests/sql/` : fichiers inventoriés, scénarios et appels dangereux examinés. Les SQL directs peuvent réinitialiser leurs bases. La campagne SQL réelle n’a pas été lancée dans cette sandbox ; voir le résultat de la CI MySQL isolée, qui ne remplace pas MariaDB ni LineServ.
- `data/`, `uploads/`, ressources : fichiers inventoriés, JSON et SVG analysés structurellement. Les données de démo ne doivent pas être utilisées comme comptes actifs en production. Aucun contenu de compte n’est reproduit ici ; fichiers locaux ignorés et non suivis hors périmètre.
- `docs/`, `livrables/`, `artefacts/`, `.tools/`, `.gitlab/` : inventaire et contrôles de structure applicables, documentation des risques et rapports mis à jour. Les documents bureautiques sont testés comme archives ZIP, **pas** relus intégralement ni validés par affichage ; PDF, images, diagrammes et livrables ne sont pas tous contrôlés visuellement.

## Validation CI

[CI GitHub historique du commit `796aada`](https://github.com/jojo8356/SAE-302/actions/runs/40759093253) : succès des suites isolées en PHP natif 8.2 et 8.5, des contrôles de sécurité, du véritable PHPUnit, des 303 contrôles E2E WASM et de MySQL 8 isolé (29 scénarios, intégration et garde-fous). Le contrôle de style reste non bloquant. Cette validation est antérieure au branchement de la page 403 ; la CI du nouveau commit doit être vérifiée séparément. Elle ne couvre ni MariaDB ni navigateur réel.

**Page 403 — validation du commit `01cc0fa` :** [CI verte](https://github.com/jojo8356/SAE-302/actions/runs/40773408401), y compris les parcours HTTP natifs sur PHP 8.2 et 8.5, l’E2E WASM isolé, PHPUnit, sécurité et MySQL 8 jetable. Le test natif utilise un serveur et des données temporaires ; le rendu dans un navigateur et sur LineServ reste à vérifier.

**Suppression de `withStore` — validation du commit `839e311` :** [CI verte](https://github.com/jojo8356/SAE-302/actions/runs/40775475513), dont PHPUnit natif, suites PHP 8.2/8.5, E2E WASM et MySQL 8 jetable. La migration éventuelle des appels extérieurs reste à la charge de leurs utilisateurs.

## Contrôles automatisés disponibles

`tests/python/test_audit_structurel.py` prend la liste `git ls-files` et vérifie présence/non-vide, synchronisation de l’inventaire CSV, compilation Python sans exécution, analyse JSON, XML et SVG, intégrité interne ZIP des formats bureautiques, `bash -n` et `node --check` si présents. Il ne vérifie ni le comportement ni la qualité visuelle, et n’ouvre pas les fichiers ignorés. Les scripts PHP sont analysés séparément par `scripts/verifier_deprecations.py` et la CI.

- Sandbox : suites isolées PHP-WASM 8.2 vertes, dont 37 tests PHPUnit avec l’exécuteur local ; analyse PHP-WASM 8.5.10 sur 10 fichiers PHP modifiés sans erreur de syntaxe ou dépréciation détectée. Le code de retour WASM n’est pas une preuve native ; diagnostics examinés et CI native nécessaire.
- E2E : 309 vérifications réussies sans navigateur, données témoins inchangées. Security scan : 10 contrôles et autotest conformes. Tests structurels Python conformes.
- PHP natif, navigateur réel/WAVE, MariaDB et LineServ : **non joués dans cette sandbox**. La CI éventuelle utilise MySQL 8, pas MariaDB. Les anciennes campagnes ou mesures datées des autres documents restent historiques.

## Risques restants et auto-évaluation

1. La page 403 est branchée pour le refus SUPER ; l’intégration navigateur réelle reste à vérifier. La suppression de `withStore` est une rupture pour les usages externes éventuels : remplacer `$depot->withStore($store)` par `CommandeRepository::for($store)`. Les autres API publiques et le paramètre `$nom` sont conservés pour compatibilité.
2. L’option SQL de réinitialisation détruit volontairement sa cible. La comparaison avec `DB_NAME` ne protège pas les autres bases, alias, droits ou concurrents ; le harnais shell SQL direct ne possède pas cette protection. Utiliser exclusivement un serveur jetable et sauvegarder.
3. Les données de démonstration versionnées, les journaux et les fonctions d’installation imposent une vérification de configuration et de sécurité avant mise en ligne. Aucun examen de l’historique complet ni des secrets hors index n’est revendiqué.
4. Lors de la préparation des tests facultatifs PHP-WASM, l’audit npm a signalé **9 vulnérabilités** (dont 5 élevées) dans l’outillage Node et ses dépendances, notamment `@php-wasm/node`. Ce n’est pas une analyse de l’application PHP en production ; la mise à niveau majeure n’a pas été entreprise dans cette correction. Ne pas exposer les outils de développement sur Internet ; examiner et mettre à jour ces dépendances dans une tâche dédiée.
5. Les contrôles lexicaux, de syntaxe et de conteneur ne remplacent ni audit de sécurité complet, ni revue métier, ni tests navigateur, ni campagne de recette sur l’hébergement. Les inclusions dynamiques et consommateurs externes peuvent échapper au scanner.

**Auto-évaluation :** points forts : corrections étayées par régressions isolées et nouveaux garde-fous bloquants ; faiblesses : revue manuelle ciblée plutôt qu’exhaustive, SQL et navigateur non disponibles localement, pas de garantie sur les environnements externes. À compléter par une recette réelle sur serveur MariaDB jetable puis sur l’hébergement cible.
