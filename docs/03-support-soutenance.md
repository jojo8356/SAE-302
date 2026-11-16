# Support et déroulé de la soutenance — MiniShop (15 minutes)

Document n° 4 du rendu (barème iv : soutenance + questions, 10 points). Le plan ci-dessous est une **trame chronométrée**, non un compte rendu de répétition. Les repères A/B/C sont des rôles de planification, pas des personnes : aucune attribution nominative ni répartition de parole réelle n’est confirmée. Ils ne désignent pas non plus des permissions GitLab ou les valeurs R/A/C/I du RACI.

## 1. Matériel à préparer

- support `docs/03-support-soutenance.md` exporté en PDF (12 diapositives, une par minute) ;
- configuration prête : `.env` (copié depuis `.env.example`), base créée par `./run.sh --db`
- poste prêt : `./run.sh` lancé (équivalent : `php -S 127.0.0.1:8000 -t public scripts/dev-router.php`), base **rejouée à froid** (`./scripts/load_db.sh`),
  deux navigateurs ouverts (front-office en navigation privée côté client, back-office à côté) ;
- terminal ouvert sur `mysql minishop` avec les 4 requêtes de preuve §12.3.5 déjà collées ;
- si un résultat de test est présenté, le rejouer sur une base temporaire isolée et conserver date, révision et sortie ; le fichier `rapport_tests_sql.md` porte une mention future du 27/10/2026 par rapport à la coupure du 23/10, qui n’est pas une preuve de rejeu acquis ;
- liens GitLab prêts (dépôt, MR, `git shortlog -sne`, README) ;
- **filet de sécurité** : 3 minutes de démo enregistrées (vidéo locale) au cas où rien ne se lance.

## 2. Déroulé minute par minute

| min | Qui | Contenu | Preuve affichée | Réf. CDC |
|---:|---|---|---|---|
| 0-1 | A | Contexte de l'entreprise, problème du tableur partagé, ce que MiniShop doit changer ; acteurs | 1 slide (état actuel + 3 acteurs) | §1.1, §3 |
| 1-2 | A | Périmètre strict (in/out), objectifs mesurables, règle anti-dérive | table §13.1 / §2.3 | §2, §11.2 |
| 2-4 | B | **Modélisation** : MCD (8 entités), 3 cardinalités défendues, MLD, normalisation (3FN, 2 dénormalisations protégées) | `mcd_minishop.png`, tableau §12.1.3 | §12.1-§12.3 |
| 4-6 | rôle de planification B | **SQL** : présenter le schéma, les scripts et les requêtes prévues ; un chargement ou résultat ne peut être annoncé qu’après run daté sur base temporaire isolée | fichiers SQL et protocole | §12.4, §12.3.5 |
| 6-8 | rôle de planification B | **Procédures stockées** : présenter SQL comme pilote par défaut et les appels des repositories aux procédures ; distinguer ce raccordement statique d’une validation PDO/MySQL, non rejouée ici | schéma, repositories et code SQL | §7.1, §12.5 |
| 8-9 | rôle de planification B | **Triggers** : expliquer les règles exécutées par la base lors des écritures SQL correspondantes ; le scénario d’écriture directe `T-03` reste une référence et n’est pas présenté comme un résultat courant | script SQL et scénario `T-03` (référence, pas résultat courant) | §12.6 |
| 9-10 | rôle de planification C | **Tests** : distinguer les 29 scénarios SQL, les dates des rapports et les scénarios manuels ; aucune exécution SQL de cette révision n’est revendiquée | matrice `docs/02` ; rapport daté à qualifier | §15, doc tests |
| 10-12 | rôle de planification C | **Parcours front** : montrer uniquement un environnement local isolé disponible le jour de la soutenance ; préciser le mode JSON ou SQL réellement configuré et ne pas présenter une répétition antérieure comme preuve | environnement de démonstration à confirmer | §4.2-§4.4, §7.1 |
| 12-13 | rôle de planification C | **Sécurité** : expliquer les scénarios offensifs `S-01…S-10` et les contrôles statiques ; leurs identifiants ne prouvent pas une exécution. Aucun test manuel LineServ n’est prévu ou revendiqué ici | matrice des scénarios, `tests/security/controles.sh` (11 statiques) | §8, §15.3 |
| 13-14 | rôle de planification A | **Back-office** : présenter les fonctionnalités prévues et préciser que le chemin SQL par défaut appelle les procédures des repositories ; aucun rejeu PDO/MySQL n’est revendiqué ici | environnement de démonstration à confirmer | §4.5, §7.1 |
| 14-15 | rôle de planification A | Organisation : architecture, livrables et limites ; contribution GitLab à vérifier sur le dépôt IUT, sans déduire les auteurs des codes A/B/C | documentation et preuves disponibles | §9, §14, §11.2 |

**Contraintes de tenue du temps** : cible de 15 minutes, répétitions à planifier et à consigner si elles ont lieu. Aucun nombre de participants, durée de parole individuelle, répétition ou démonstration exécutée n’est attesté par cette trame.

## 3. Questions anticipées et réponses courtes (à connaître par cœur)

| Question probable | Réponse (10 à 30 s) | Réf. |
|---|---|---|
| Pourquoi une procédure stockée plutôt que du PHP pour la commande ? | SQL est le pilote par défaut et `CommandeRepository` appelle les procédures de commande ; elles portent atomicité et verrous côté serveur, logique commune et moins d’allers-retours. Le coût est la portabilité et le débogage. Le raccordement statique n’a pas été rejoué sous PDO/MySQL dans cette révision. | §12.5.1, §7.1 |
| Pourquoi garder `prix_ttc` et `montant_total` si c'est calculable ? | Deux dénormalisations **tracées et verrouillées** par trigger : elles portent la valeur contractuelle du moment de l'achat (facturation) et évitent une agrégation à chaque affichage ; sans le trigger, la redondance serait une anomalie | §12.3.2, §12.3.4 |
| Pourquoi `total_ligne` et pas `prix × quantité` à la demande ? | Pour la même raison : le prix payé ne doit plus jamais bouger ; le trigger le recalcule à l'insertion et l'immutabilité l'interdit après | RB-06, RB-09, T-11 |
| Pourquoi deux tables CLIENT et ADMINISTRATEUR et pas une table UTILISATEUR avec un rôle ? | Données et cycle de vie disjoints, aucune écriture possible sur la table admin depuis le front (SEC-10), contrôle d'accès plus simple à prouver ; le surcoût est nul à notre échelle | V4 du §12.8 |
| Pourquoi le `BROUILLON` existe-t-il, on ne peut pas créer la commande directement en `EN_PREPARATION` ? | Sinon `RB-04` (au moins une ligne) est violée à la création ; le brouillon est l'état « panier convertible », jamais exposé à l'admin (`COMMANDE_NON_TRAITABLE`) | V3 du §12.8, T-16 |
| Comment est visée la prévention de survente ? | Le chemin SQL par défaut utilise les procédures et verrous `SELECT … FOR UPDATE` pour les opérations concernées. `C-01` reste un scénario de charge à exécuter en environnement isolé ; aucun résultat 20/12 ni rejeu PDO/MySQL de cette révision n’est revendiqué. Le mode JSON est une option secondaire. | §2.3, C-01, §7.1 |
| Que prévoit la conception si un contrôle d'appartenance est oublié ? | `sp_confirm_order` vérifie l’appartenance, et le repository utilise cette procédure sur le chemin SQL. `T-18` documente le scénario, mais aucun rejeu SQL de cette révision n’est revendiqué. | SEC-08, T-18, §7.1 |
| Pourquoi un trigger et pas la procédure pour écrire l'historique ? | Un `UPDATE` direct (import, reprise, correction) doit aussi laisser une trace. Le scénario `T-22` est prévu pour vérifier l’absence de doublon lors d’un changement de statut ; aucun résultat de rejeu SQL courant n’est revendiqué ici. | A-02, T-22 |
| Quelle est la portée des vérifications anti-injection ? | `SEC-01`, les scénarios offensifs `S-01…S-10` et les contrôles statiques sont des références distinctes. Les 11 contrôles statiques comprennent `S-11`/`SEC-14` ; aucun test offensif ou run CI global n’est revendiqué dans cette trame. | §8.1, §15.3 |
| Et si on tente une écriture SQL qui met le stock à −5 ? | Le modèle décrit un `CHECK` et un déclencheur ; `T-02`/`T-03` référencent les scénarios. Un rejet doit être confirmé par un run isolé avant d’être présenté comme résultat. | §6.1, §6.2 |
| Pourquoi le mot de passe est-il vérifié en PHP et pas en base ? | Seul `password_verify()` sait comparer bcrypt en temps constant ; la base ne renvoie donc que le hash via `sp_get_credentials`, la comparaison reste dans l'application | §8.4, séquence 1 |
| Quelle difficulté de conception est consignée ? | Le registre documentaire décrit un historique autour de `RB-06` et de l’ordre des triggers (`A-01`, `T-11`). Ne pas le présenter comme un rejeu de la version courante sans résultat daté. | `docs/02` §7 |
| Que feriez-vous en poursuite (hors périmètre) ? | Panier persistant (variante V1 prête), paiement réel avec journal de transactions, politique de prix par remise (une table `REMISE` + snapshot), export comptable, et tests Playwright bout en bout | §12.8, §11.2 |
| Où vérifier les contributions GitLab ? | Dans l’historique réel du dépôt IUT et les liens de MR disponibles. Ce checkout ne confirme pas les contributions individuelles ; permissions GitLab, valeurs R/A/C/I et rôles de planification A/B/C restent distincts. | §14 |
| Faut-il relancer la CI après un merge ? | Contrôler le statut et le commit de la PR GitHub dans GitHub Actions. La CI GitHub ne prouve pas un pipeline GitLab IUT, qui n’est pas configuré dans le périmètre actuel | §14.2 |
| Une MR GitLab peut-elle être validée par le statut de la CI GitHub ? | Non : les chaînes GitHub Actions et GitLab sont distinctes ; le statut d’une PR GitHub ne vaut pas pipeline GitLab. Le registre dédié est un modèle à renseigner après une revue réelle ; aucune revue croisée n’est attestée ici. | §14.2 |

### 3.1 Les trois décisions « hors sujet » à défendre en 30 secondes chacune

| Décision | Phrase à dire | Si le jury insiste |
|---|---|---|
| **Les frais de port sont dans le périmètre** | « Le CDC décrit `RB-20` comme une règle visant l’affichage du port avant validation. » | Montrer le scénario `T-28` comme référence, pas comme résultat exécuté ; frais/règles configurables restent des choix de conception à confirmer. |
| **Ventes interdites à stock nul** | « Le modèle SQL décrit les contrôles de stock et l’isolation de l’écriture. » | `T-02` est un scénario de vérification ; ne pas exécuter une écriture de test sur une base réelle ou partagée. |
| **Deux rôles d'administrateur, pas de MFA** | « L'énoncé dit simplement << rôle administrateur >>. On a appliqué le moindre privilège — **OWASP ASVS V4.1.3** et le guide **ANSSI** R27/R29 — en séparant `SUPER` (comptes d'équipe) de `GESTIONNAIRE` (catalogue et commandes). La MFA, exigée par ASVS V4.3.1, est **explicitement hors périmètre et écrite comme un écart** au §8.10 du cahier des charges, avec ce qu'il faudrait pour la livrer. » | Montrer `EF-ADM-10` (garde `SUPER`), `F-32` (un `GESTIONNAIRE` reçoit 403 sur la gestion d'équipe) et le §8.10 (tableau des écarts). |
| **Volumétrie cible, pas mesure** | « Le dimensionnement de conception retient 200 produits et 1 000 commandes par an. » | `scripts/gen_volumes.sh` et les seuils `ENF-*` définissent un protocole ; aucune volumétrie ou mesure de performance n’est revendiquée ici. |

## 4. Check-list de la dernière demi-heure avant l'entrée en salle

- [ ] Confirmer la durée autorisée, l’environnement local isolé et le mode de stockage effectivement configuré ; exclure LineServ et les bases partagées.
- [ ] Si les scripts SQL sont rejoués, utiliser une base temporaire dédiée et conserver date, révision, sortie et résultat ; la mention 29/29 du rapport porte une date future (27/10) et n’est pas un résultat acquis à la coupure du 23/10.
- [ ] Vérifier les écrans de démonstration dans l’environnement réellement disponible, sans présenter un scénario documentaire comme résultat.
- [ ] Préparer les références des 11 contrôles statiques (dont `S-11`/`SEC-14`) et des 10 scénarios offensifs distincts.
- [ ] Ne lancer aucune requête destructive ou tentative de sécurité sur une base de production ou LineServ.
- [ ] `git shortlog -sne main` exécuté et visible (3 auteurs)
- [ ] diaporama exporté en PDF, projeté sans connexion
- [ ] chrono réglé sur 15 min, une personne désignée « gardien du temps »
