# Charte GitLab — branches, merge requests et revues croisées

**Version :** 1.0 — 23 octobre 2026
**Portée :** règles documentaires de collaboration GitLab et modèle de registre. Ce fichier décrit le processus attendu ; il ne constitue pas, à lui seul, une preuve que des MR ou des revues ont effectivement eu lieu.

## 1. Principes

- Une issue GitLab décrit un besoin identifiable : tâche WBS, exigence fonctionnelle `EF-*`, règle métier ou anomalie.
- Une merge request (MR) lie le travail à son issue, expose les changements et donne lieu à une revue par une autre personne que l’auteur.
- Les rôles de travail A/B dans le README sont des responsabilités de projet ; ils ne déterminent ni les permissions GitLab ni l’identité de l’auteur ou du relecteur.
- Le dépôt et les MR restent sur GitLab IUT. Le workflow GitHub Actions est une chaîne distincte : son résultat ne prouve pas l’existence d’un pipeline GitLab.
- Aucun nom, identifiant de MR, approbation ou résultat de revue n’est supposé dans ce document. Les exemples ci-dessous sont des consignes ou des champs à renseigner, pas des événements passés.

## 2. Issues et découpage du travail

1. Créer ou réutiliser une issue pour l’exigence, la tâche ou le défaut concerné.
2. Donner à l’issue un titre court et un périmètre vérifiable ; ajouter les critères d’acceptation, dépendances et preuves attendues.
3. Lier la MR à l’issue et la fermer uniquement après la revue et la vérification des critères.
4. Éviter de mélanger dans une même MR des lots indépendants ; signaler explicitement toute exception nécessaire.

## 3. Branches et commits

Noms indicatifs, à adapter à l’identifiant réel de l’issue :

```text
feat/<id>-<sujet>     nouvelle fonctionnalité
fix/<id>-<sujet>      correction
security/<id>-<sujet> changement lié à la sécurité
test/<id>-<sujet>     tests

docs/<id>-<sujet>    documentation
```

Convention de commit :

```text
<type>(<périmètre>): <résumé à l’impératif>
```

Types conseillés : `feat`, `fix`, `security`, `test`, `docs`, `refactor` et `chore`. Les messages doivent décrire le changement sans contenir de secret, de donnée personnelle réelle ou de mot de passe.

## 4. Préparation et ouverture d’une MR

L’auteur utilise le modèle adapté dans [`.gitlab/merge_request_templates/`](../.gitlab/merge_request_templates/) et fournit :

- l’issue et les exigences concernées ;
- un résumé des changements et des limites connues ;
- les tests exécutés, leur commande et le résultat réellement observé ;
- les captures ou autres preuves utiles, expurgées de toute donnée sensible ;
- les migrations, changements de configuration et impacts documentaires éventuels.

Avant de demander une revue, l’auteur vérifie le diff, supprime les secrets et données temporaires, et s’assure que les cases du modèle correspondent aux contrôles réellement exécutés. Une case non exécutée reste décochée.

## 5. Revue croisée

Le relecteur doit être distinct de l’auteur. Il vérifie au minimum :

1. la correspondance avec l’issue et les critères d’acceptation ;
2. les chemins nominaux et les cas d’échec importants ;
3. les contrôles serveur, l’autorisation, le CSRF, l’échappement et les requêtes SQL touchés ;
4. les tests et preuves déclarés, sans confondre contrôle statique, test automatisé et recette humaine ;
5. la documentation, les secrets, les données de test et les éventuelles régressions d’interface/accessibilité.

Le relecteur consigne les commentaires dans la MR. L’auteur répond, corrige si nécessaire et marque les fils résolus après traitement. La MR ne peut être considérée comme revue tant que les commentaires bloquants ne sont pas résolus et que l’approbation du pair n’est pas visible ou consignée.

## 6. CI et limites de preuve

La CI versionnée du projet est [GitHub Actions](../.github/workflows/ci.yml). Le fichier `.gitlab-ci.yml` a été retiré du périmètre du projet le 23 octobre 2026. En conséquence :

- un résultat GitHub Actions démontre uniquement l’exécution de cette chaîne sur son commit ;
- il ne démontre pas qu’un pipeline GitLab IUT a tourné ou qu’une MR GitLab a été approuvée ;
- le registre ci-dessous doit distinguer la revue GitLab de tout résultat CI GitHub associé.

## 7. Registre de preuves de MR et de revues

À compléter uniquement avec des références réelles visibles dans GitLab. Une ligne vide ou le présent modèle ne prouve aucune MR.

| Issue | MR (ID/lien) | Auteur | Relecteur distinct | Date de revue | Commentaires bloquants résolus | Approbation/merge visible | CI (plateforme et lien) |
|---|---|---|---|---|---|---|---|
| À renseigner | À renseigner | À renseigner | À renseigner | À renseigner | À renseigner | À renseigner | À renseigner |

Pour chaque MR, conserver les éléments vérifiables : identifiant ou URL, issue liée, approbation/revue, fils de discussion résolus et commit de fusion. Ne pas recopier de secrets ou de données personnelles dans le registre.

## 8. Fermeture de la tâche documentaire

Le livrable documentaire est constitué de cette charte versionnée et des modèles présents dans `.gitlab/merge_request_templates/`. Cela valide la **documentation du processus** ; cela ne valide pas l’utilisation effective de MR, la tenue de revues croisées ou une exécution CI GitLab. Ces éléments ne pourront être attestés qu’avec des références réelles de MR et de revues.
