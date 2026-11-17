# Synthèse des retours continus sur l’audit — suivi asynchrone

> **Source et portée :** l’utilisateur indique que des retours continus ont eu lieu, sans réunion complète. Cette note rassemble les choix de cadrage déjà exprimés et appliqués dans le dossier. Elle n’est pas le procès-verbal d’une réunion ; aucune date de réunion, liste de participants, approbation ou validation pédagogique n’est inventée.
>
> **D1-L1-07 :** tâche clôturée selon la modalité asynchrone convenue : synthèse écrite des retours continus. Le statut `OK` ne signifie pas qu’une réunion complète s’est tenue.

## Choix de cadrage consignés

| Sujet | Choix retenu et limite | Référence |
|---|---|---|
| Dates et preuves | Coupure historique au 23/10/2026 ; le run restreint de cette date utilisait `--sans-sql --sans-e2e`, sans SHA conservé. Un run ultérieur exécuté le 15/11 sur le commit `b8edfc9` (`--sans-sql`, E2E isolé inclus, 338 vérifications) est consigné séparément ; il ne requalifie pas l’audit historique et ne prouve pas SQL/PDO. | `docs/02`, `docs/11`, `docs/19` |
| Périmètre | Déploiement et staging retirés sans tâche de remplacement. Aucun déploiement, essai LineServ ni restauration de base n’est revendiqué. | `docs/05`, `docs/06`, `docs/19` |
| Équipe et rôles | Permissions GitLab, valeurs R/A/C/I du RACI et rôles de planification A/B/C restent distincts. Aucune lettre A/B/C n’est attribuée nominativement à Johan Polsinelli ou Chahd Gouiaa sans confirmation. | `docs/01`, `docs/05`, `docs/19` |
| CDC et stockage | SQL/MySQL/PDO est le pilote applicatif par défaut ; JSON reste une option explicite. Les repositories appellent les 18 procédures stockées ; leur exécution réelle PDO/MariaDB (`29/29` SQL, `105/105` HTTP SQL, `8/8` concurrence) a été validée sur VM Debian 13 les 16–17/11/2026 (`docs/pv-recette.md`). | `docs/01`, `docs/02`, `docs/04`, `docs/19` |
| Traçabilité et tests | Les identifiants T-* décrivent des scénarios ; après le run JSON non-SQL du 15/11, la campagne VM du 17/11 (`f9b614f` / `cb0f814`) a validé `60/60` étapes (`docs/pv-recette.md`) et les performances `ENF-01` (`tests/perf/dernieres_mesures.txt`, HTTP p95 = `6 ms`). Les 200 entrées manuelles restent `NON JOUÉ`. | `docs/02`, `docs/11`, `docs/19` |
| Sécurité | Les dix scénarios offensifs S-01…S-10 sont distincts des onze contrôles statiques. S-11/SEC-14 est un contrôle statique ciblé, pas une recette offensive ni une certification générale. | `docs/01`, `docs/02`, `docs/07` |
| Règles métier | Le CDC dénombre 20 identifiants : RB-01…RB-11 classées par le document comme issues du sujet, plus RB-12…RB-20 comme compléments. Le PDF source n’étant pas dans le dépôt, cette classification n’est pas présentée comme vérifiée indépendamment ni validée pédagogiquement. | `docs/01`, `docs/19` |
| Séquences UML | Cinq séquences sont documentées : quatre prévues comme imposées et une bonus. Aucune sixième séquence n’est annoncée. | `docs/01`, `docs/05`, `docs/19` |
| Relecture croisée CDC (`D2-L2-06`) | L’équipe (Johan Polsinelli et Chahd Gouiaa) atteste effectuer des vérifications croisées en continu sur le CDC (§1→§8) et le projet, complétées par une pré-inspection technique des §1→§8 ; sur cette attestation explicite (même modalité asynchrone continue que `D1-L1-07`), `D2-L2-06` passe `OK`, sans procès-verbal formel de séance distinct ni validation pédagogique. | `docs/01`, `docs/05`, `docs/19` |
| Relecture croisée Conception BD (`D3-L4-06`) | Après contrôle technique de cohérence (`9` tables, `3` vues, `18` procédures, `1` fonction, `16` déclencheurs) et sur l’attestation explicite des relectures croisées constantes entre **Johan Polsinelli** et **Chahd Gouiaa**, `D3-L4-06` passe `OK` (conception validée par les campagnes SQL `29/29`, HTTP SQL `105/105`, concurrence `8/8` et performances `p95 ≤ 20,6 ms` sur VM Debian 13 les 16–17/11/2026, `docs/pv-recette.md` et `tests/perf/dernieres_mesures.txt`). | `docs/04`, `docs/05`, `docs/19` |

## Statut de la tâche

**D1-L1-07 — OK, dans le périmètre révisé.** Le livrable demandé est cette synthèse des retours continus, rédigée à partir des choix explicitement communiqués et des limites consignées dans les documents. La clôture ne prétend pas qu’une réunion complète a eu lieu, ne nomme pas de participants et ne vaut ni approbation collective ni validation pédagogique.

## Limites maintenues

- Aucune réunion complète ni aucun compte rendu de réunion n’est revendiqué.
- Aucun participant, auteur de revue distinct, vote, approbation ou validation n’est inféré.
- `D2-L2-06` et `D3-L4-06` sont clôturées sur l’attestation utilisateur des relectures croisées continues entre Johan Polsinelli et Chahd Gouiaa (complétées par le pointage documentaire et technique de `docs/01` et `docs/04`), sans prétendre à une validation pédagogique ni à un compte rendu formel de réunion.
- Aucun test manuel LineServ, déploiement ou restauration de base n’est ajouté à la suite des retours.
