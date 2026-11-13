# Compte rendu — vérification de l’audit et validation simple

**Date :** 13 novembre 2026
**Objet :** vérification de l’audit complet et validation simple des choix de périmètre et de documentation.
**Source du compte rendu :** l’utilisateur atteste que la réunion a eu lieu le 13 novembre 2026 et confirme que le présent compte rendu reprend les choix communiqués dans cette conversation. Cette attestation n’a pas été vérifiée indépendamment.

## Participants et limites de consignation

Les noms des participants, l’heure, le lieu et une feuille de présence n’ont pas été fournis ; aucun n’est inventé. La réunion est consignée comme une vérification de l’audit et une validation simple des décisions listées ci-dessous. Ce compte rendu n’affirme pas qu’une tierce personne a validé chaque ligne de l’audit ni qu’une exécution technique externe a été rejouée.

## Décisions validées

1. **Retrait de `D1-L1-06` et de la CI GitLab.** Retirer la tâche et le fichier `.gitlab-ci.yml` de la todolist, de la WBS et du CDC, supprimer le fichier du dépôt et retirer la ligne de l’audit. Les références actuelles doivent être adaptées ; GitHub Actions reste une chaîne distincte et ne vaut pas preuve d’un pipeline GitLab.
2. **`D1-L1-05` — livrable documentaire.** Créer une charte GitLab complète et un registre de revue dans `docs/20-charte-gitlab-revues-croisees.md`, en complément des modèles de MR existants. La validation porte sur la documentation ; l’utilisation réelle de MR et les revues croisées ne sont pas revendiquées sans identifiants ou preuves réels.
3. **`D1-L1-07` — réunion.** Consigner la séance comme une réunion de vérification de l’audit et de validation simple. Le compte rendu reprend les décisions communiquées par l’utilisateur ; il ne complète pas les noms des participants ou des validations individuelles qui n’ont pas été transmis.
4. **`D1-L1-04` — issue de barème.** Conserver le statut `OK` sur la base de la capture de l’issue `s302#2`, qui montre les cinq livrables et les pénalités §16.2. L’épinglage n’est pas confirmé et n’est pas revendiqué.
5. **Mise en ligne (état historique).** À la date du compte rendu, aucun déploiement réel n’était déclaré et cette vérification restait sans preuve ; le présent compte rendu ne prétend pas qu’une mise en ligne a eu lieu.

## Suite à donner

- Les totaux et références de tâches ont été recalés après le retrait du livrable de CI GitLab ; la présente note conserve la décision, sans réintroduire ce livrable comme exigence courante.
- Pour obtenir une preuve d’usage effectif des MR, ajouter au registre des identifiants/liens réels, auteurs, relecteurs distincts, approbations et commentaires résolus.
- La recommandation historique de vérifier une mise en ligne n’a pas été exécutée et est retirée du périmètre courant avec la fonctionnalité correspondante, sans remplacement de charge.

## Mise à jour du périmètre courant

La fonctionnalité de mise en ligne et de diagnostic staging ainsi que ses éléments documentaires associés ont été retirés du dépôt, de la todolist et de la WBS, sans travail de remplacement. Cette suppression ne constitue pas une preuve de déploiement. La campagne manuelle LineServ reste distincte, n’a pas été exécutée et aucune restauration de base n’a été réalisée.

## Validation

L’utilisateur confirme que la réunion avait pour objet la vérification de l’audit et une validation simple, et que les choix ci-dessus constituent le compte rendu demandé. Aucun accès au dépôt IUT ni aucune vérification extérieure n’ont été effectués pour rédiger cette note.
