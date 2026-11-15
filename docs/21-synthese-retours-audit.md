# Synthèse des retours continus sur l’audit — suivi asynchrone

> **Source et portée :** l’utilisateur indique que des retours continus ont eu lieu, sans réunion complète. Cette note rassemble les choix de cadrage déjà exprimés et appliqués dans le dossier. Elle n’est pas le procès-verbal d’une réunion ; aucune date de réunion, liste de participants, approbation ou validation pédagogique n’est inventée.
>
> **D1-L1-07 :** tâche clôturée selon la modalité asynchrone convenue : synthèse écrite des retours continus. Le statut `OK` ne signifie pas qu’une réunion complète s’est tenue.

## Choix de cadrage consignés

| Sujet | Choix retenu et limite | Référence |
|---|---|---|
| Dates et preuves | Coupure documentaire au 23/10/2026 ; contrôles locaux datés du 23/10/2026 sur le working tree. La date Git du commit de base (13/11/2026) est postérieure à la coupure. Les mentions futures et les résultats sans run/SHA ne sont pas repris comme preuves acquises. | `docs/02`, `docs/07`, `docs/19` |
| Périmètre | Déploiement et staging retirés sans tâche de remplacement. Aucun déploiement, essai LineServ ni restauration de base n’est revendiqué. | `docs/05`, `docs/06`, `docs/19` |
| Équipe et rôles | Permissions GitLab, valeurs R/A/C/I du RACI et rôles de planification A/B/C restent distincts. Aucune lettre A/B/C n’est attribuée nominativement à Johan Polsinelli ou Chahd Gouiaa sans confirmation. | `docs/01`, `docs/05`, `docs/19` |
| CDC et stockage | PHP/MySQL/PDO reste la cible du cahier des charges ; le stockage applicatif par défaut est JSON et le SQL reste optionnel. Cet écart est ouvert et n’est pas déclaré résolu. | `docs/01`, `docs/02`, `docs/19` |
| Traçabilité et tests | Les identifiants T-* décrivent des scénarios, pas des résultats. Les contrôles SQL ne sont déclarés exécutés qu’avec un run daté et rattaché à un SHA ; aucun rejeu SQL de cette révision n’est revendiqué. | `docs/02`, `docs/11`, `docs/19` |
| Sécurité | Les dix scénarios offensifs S-01…S-10 sont distincts des onze contrôles statiques. S-11/SEC-14 est un contrôle statique ciblé, pas une recette offensive ni une certification générale. | `docs/01`, `docs/02`, `docs/07` |
| Règles métier | Le CDC dénombre 20 identifiants : RB-01…RB-11 classées par le document comme issues du sujet, plus RB-12…RB-20 comme compléments. Le PDF source n’étant pas dans le dépôt, cette classification n’est pas présentée comme vérifiée indépendamment ni validée pédagogiquement. | `docs/01`, `docs/19` |
| Séquences UML | Cinq séquences sont documentées : quatre prévues comme imposées et une bonus. Aucune sixième séquence n’est annoncée. | `docs/01`, `docs/05`, `docs/19` |
| Relecture croisée | Le pointage documentaire ne remplace pas une relecture croisée entre étudiants. Aucune relecture de ce type n’est attestée ; D2-L2-06 reste ouverte. | `docs/05`, `docs/19` |

## Statut de la tâche

**D1-L1-07 — OK, dans le périmètre révisé.** Le livrable demandé est cette synthèse des retours continus, rédigée à partir des choix explicitement communiqués et des limites consignées dans les documents. La clôture ne prétend pas qu’une réunion complète a eu lieu, ne nomme pas de participants et ne vaut ni approbation collective ni validation pédagogique.

## Limites maintenues

- Aucune réunion complète ni aucun compte rendu de réunion n’est revendiqué.
- Aucun participant, auteur de revue distinct, vote, approbation ou validation n’est inféré.
- D2-L2-06 reste ouverte jusqu’à une preuve de relecture croisée par des étudiants distincts.
- Aucun test manuel LineServ, déploiement, restauration de base ou rejeu SQL n’est ajouté à la suite des retours.
