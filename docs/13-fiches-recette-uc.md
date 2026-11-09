# Fiches de recette MiniShop — UC-01 à UC-14

**Révision de référence :** branche de cette fiche, à relever au lancement par `git rev-parse --short HEAD`. **Périmètre :** maquette web MiniShop, magasin JSON ou MariaDB selon configuration. **Campagne : non exécutée par la rédaction de ce document.** Aucun résultat, testeur, date d'exécution ni preuve n'est prérempli.

## 0. Mode d'emploi

### Objectif et limites

Le tableau de suivi prêt à remplir est [13-suivi-recette-uc.csv](13-suivi-recette-uc.csv) : chaque scénario et chaque couple de statut y dispose de sa propre ligne, initialement `NON JOUÉ`. Ouvrir le fichier en UTF-8 avec séparateur point-virgule ; ne pas envoyer de preuve contenant des secrets.

Ce document transforme les 14 UC du [cahier des charges](01-cahier-des-charges-MiniShop.md) et de son [annexe](annexes/annexe-cas-usage.md) en scénarios de recette reproductibles. Il couvre nominal, alternatives, erreurs, bornes, sécurité, transitions et pannes prioritaires. « Tous les scénarios possibles » ne peut pas être garanti : les combinaisons de données, configurations et pannes sont ouvertes ; compléter la liste si un risque ou un défaut nouveau apparaît. Un scénario décrit **un résultat attendu**, pas un résultat constaté. Les anciennes annexes contiennent aussi des exigences ou détails techniques qui ne sont pas nécessairement implémentés : cette fiche distingue les attentes sans inventer de succès.

### Méthode étudiée

- [Microsoft — stratégie de tests](https://learn.microsoft.com/en-us/dynamics365/guidance/implementation-guide/testing-strategy-planning) : scénarios métier reproductibles, résultats attendus explicites, cas positifs et négatifs ; [Microsoft — exécution manuelle](https://learn.microsoft.com/en-us/azure/devops/test/run-manual-tests?view=azure-devops) : noter résultat, preuve et anomalie.
- [Atlassian — plan de test](https://www.atlassian.com/software/confluence/resources/guides/how-to/test-plan) et [Atlassian/Xray](https://www.atlassian.com/devops/testing-tutorials/jira-xray-integration-manage-test-cases) : périmètre, risques, identifiants, préconditions, étapes, critères et traçabilité. Cette fiche s'inspire de ces principes, **sans prétendre être un modèle interne officiel de ces entreprises**.
- [ISTQB — techniques de test](https://istqb.org/certifications/certified-tester-model-based-tester-ct-mbt/) : classes d'équivalence, valeurs limites, tables de décision et transitions d'état.
- [Google Testing Blog — bons tests de bout en bout](https://testing.googleblog.com/2016/09/testing-on-toilet-what-makes-good-end.html) : privilégier les parcours critiques de bout en bout, compléter par des tests plus ciblés ; un compteur de tests ne prouve pas l'absence de défauts.

### Codes et verdicts

- **P0** : critique (perte de données, paiement/stock, accès ou parcours bloqué) ; **P1** : important ; **P2** : complémentaire.
- **M** : navigateur ; **R** : requête ou outil contrôlé ; **I** : intégration isolée avec droits adaptés. Ne pas lancer R/I sur une base partagée, sur Internet ou sans autorisation.
- **APP** : comportement attendu d'après le code consulté ; **SPEC** : exigence ou résultat souhaité à vérifier, **pas une promesse d'implémentation**. Un échec de SPEC est une anomalie ou un écart à qualifier, jamais un succès par défaut.
- Chaque scénario est initialement **NON JOUÉ**. Après exécution, reporter dans le journal : `OK`, `KO`, `BLOQUÉ`, `NON JOUÉ` ou `NON APPLICABLE`, le résultat observé et la preuve. Un contrôle d'une interface ne prouve pas une règle côté serveur ; les protections doivent aussi être vérifiées par les tests adaptés.

### Préparer une campagne sans détruire les données

1. Vérifier la version réellement utilisée (`git rev-parse --short HEAD`), le pilote, la base, l'URL, le navigateur, le fuseau et les droits. Les données et identifiants ci-dessous sont des **exemples fictifs**, pas des identités étudiantes. Ne pas entrer de données personnelles réelles ou bancaires.
2. Utiliser un environnement et une base de recette **distincts**, avec sauvegarde et autorisation du propriétaire. Les tests créent comptes et commandes et modifient stock, produits et statuts. Ne jamais lancer `--reset`, `--db-reset`, des scripts de volumétrie ou une fixture SQL sur la base partagée. Une simple lecture peut créer une session et des journaux.
3. Utiliser si disponible un administrateur `SUPER` et, pour les contrôles de rôle, un gestionnaire autorisé à tester ; ne pas présumer les identifiants encore valides. Le jeu de démonstration versionné prévoit `alice@example.com` / `Demo2026!`, `bruno@example.com` / `Demo2026!` et `admin@minishop.fr` / `Admin2026!` si ces comptes n'ont pas été modifiés. Ne pas changer leurs mots de passe dans une base partagée.
4. Créer ou faire créer **dans la recette isolée** :
   - **C1** et **C2** : deux clients distincts actifs ; un e-mail neuf non utilisé, par exemple `uc-nouveau@example.test` ; **A1** : administrateur ; **G1** : gestionnaire si disponible.
   - **K1** : catégorie de test et **K0** : catégorie de test vide ; **P1** : produit visible, stock 5, prix HT 10,00 €, TVA 20 % (TTC 12,00 €) ; **P2** : visible, stock 0 ; **P3** : masqué ; **P4** : visible, stock 1. Références et noms uniques, par exemple `TEST-UC-P1` et `Produit UC P1`.
   - Noter les frais et la franchise réels dans `/admin/parametres`, sans les modifier sur une instance partagée. Prévoir deux commandes de C1 : **O1**, non expédiée, pour l'annulation ; **O2**, distincte, pour les changements de statut. Noter références, identifiants, stocks initiaux et URL de détail.
5. Pour chaque ligne, remettre **uniquement les données de recette isolées** dans l'état indiqué ; si ce n'est pas possible, marquer `BLOQUÉ`. Les opérations de remise en état doivent être décidées par le responsable de l'environnement ; ne pas improviser de suppression ou réinitialisation.

### Déroulement et preuves

Pour un parcours, ouvrir les routes sur l'origine de l'instance, par exemple `http://localhost:8000/catalogue` si le serveur local est configuré sur ce port. Relever **avant et après** : stock, panier, numéro et statut de commande, montants, et éventuelle écriture d'audit. Capturer une preuve expurgée : aucune donnée réelle, mot de passe, cookie ou secret. Pour les transitions, utiliser une commande distincte par cas ou une fixture isolée ; ne pas supposer qu'on peut revenir à un état précédent.

Journal à recopier pour chaque scénario joué :

| Champ | À renseigner par le testeur |
|---|---|
| Campagne / ID scénario |  |
| Version / pilote / navigateur |  |
| Date / testeur |  |
| Statut (`OK`, `KO`, `BLOQUÉ`, `NON JOUÉ`, `NON APPLICABLE`) | NON JOUÉ |
| Résultat observé / écarts |  |
| Preuve expurgée / ticket d'anomalie |  |
| Données utilisées / remise en état |  |

Une anomalie doit indiquer l'ID, la version, le prérequis, les étapes, l'attendu, l'observé, la gravité et la preuve. Ne pas conclure « réussi » sans exécution et comparaison. Les tests des scénarios `SPEC` peuvent révéler des fonctions absentes.

---

## UC-01 — Consulter le catalogue

**Acteur :** visiteur. **But :** parcourir catégories, filtres, tri et pages. **Déclencheur :** ouverture de `/catalogue` ou `/categories`. **Préconditions :** K1, P1, P2 et P3 configurés. **Nominal :** ouvrir le catalogue, voir les cartes, choisir K1, passer de page si possible. **Postcondition :** aucune modification métier de produit ou commande ; une session technique peut être créée. **Références :** EF-VIS-01/03/05, RB-19 ; tests existants : `tests/php/catalogue_stock_test.php`, `scripts/wasm-e2e.mjs` (couverture partielle, à rejouer).

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC01-01 | P0 M APP | Ouvrir `/catalogue`, puis K1 ; rechercher ou paginer pour trouver P1 et P2. | P1 et P2 visibles ; P3 absent ; cartes avec nom, prix TTC et disponibilité. |
| TC01-02 | P1 M APP | Ouvrir `/categories`, cliquer K1. | Navigation vers la catégorie ; compteur cohérent avec les produits visibles. |
| TC01-03 | P1 M APP | Sélectionner K0 vide. | Message de liste vide et possibilité de revenir au catalogue. |
| TC01-04 | P1 M APP | Cumul K1 + prix min/max + « en stock ». | Chaque carte respecte tous les critères ; URL partageable conserve les filtres. |
| TC01-05 | P1 M APP | Choisir tri prix croissant puis décroissant avec produits en stock et en rupture. | Dans chaque groupe, ordre choisi respecté ; ruptures en fin de liste. |
| TC01-06 | P1 M APP | Parcourir pages 1 et 2 avec plus de 12 résultats. | Au plus 12 cartes par page, aucune répétition ou omission ; filtres conservés. |
| TC01-07 | P2 R APP | Demander `page=0`, une page très grande et un tri inconnu sur la copie de recette. | Pas d'erreur technique ; page inférieure bornée, page hors résultat vide, tri inconnu ramené au tri par nom. |
| TC01-08 | P1 M APP | Ouvrir l'accueil et comparer les quatre nouveautés aux dates et ID visibles. | Tri création décroissante puis ID décroissant en cas d'égalité ; date/heure de chaque carte issues de son produit ; si indisponible, repli explicite. |
| TC01-09 | P2 M SPEC | Réduire la fenêtre à 320 px, zoomer à 200 %, parcourir au clavier. | Contenu et commandes utilisables sans perte d'information ni débordement horizontal de la page. |
| TC01-10 | P1 I SPEC | Simuler une indisponibilité du magasin uniquement sur une instance jetable. | Message générique sans secret ni trace technique au visiteur ; aucune altération métier. Noter le code HTTP réellement obtenu. |

## UC-02 — Rechercher un produit

**Acteur :** visiteur. **But :** rechercher par nom, description ou référence. **Déclencheur :** saisie d'une recherche. **Préconditions :** P1 visible et mot distinctif connu. **Nominal :** saisir le mot, valider, consulter les résultats. **Postcondition :** pas d'écriture métier. **Références :** EF-VIS-02, RB-19, SEC-02 ; `tests/php/catalogue_stock_test.php`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC02-01 | P0 M APP | Rechercher le nom exact de P1. | P1 apparaît avec son nom et prix corrects. |
| TC02-02 | P1 M APP | Rechercher une partie du nom puis la référence de P1. | P1 figure dans chaque résultat correspondant. |
| TC02-03 | P1 M SPEC | Rechercher une variante de casse et, avec un produit accentué, sans accent. | Résultats équivalents pour la casse et les accents ; noter pilote et extensions (`intl`) si écart. |
| TC02-04 | P1 M APP | Rechercher un terme inexistant puis une saisie vide. | Aucun résultat avec message adapté pour le premier ; recherche vide équivalente au catalogue. |
| TC02-05 | P1 M APP | Rechercher le nom de P3 masqué. | P3 n'apparaît pas au visiteur. |
| TC02-06 | P1 M APP | Cumul recherche, catégorie, prix et pagination. | Seuls les résultats satisfaisant les filtres apparaissent ; les critères restent dans l'URL. |
| TC02-07 | P1 R SPEC | Envoyer une recherche de 120 puis 121 caractères sur la recette isolée. | Aucune erreur technique ; traitement borné à la limite serveur ou refus explicite, sans divulgation. |
| TC02-08 | P0 M SPEC | Rechercher littéralement `' OR 1=1 --` et `<script>alert(1)</script>` dans la recette. | Aucun script exécuté, aucune modification de données ; la recherche ne retourne pas tous les produits par injection. |
| TC02-09 | P2 R SPEC | Tester caractères Unicode, espaces et caractères `%` / `_` dans la recette. | Aucun plantage ni fuite ; documenter si les caractères SQL sont traités comme littéraux ou motifs. |

## UC-03 — Consulter une fiche produit

**Acteur :** visiteur. **But :** consulter caractéristiques, prix et stock. **Déclencheur :** clic sur une carte ou URL produit. **Préconditions :** P1 visible, P2 en rupture, P3 masqué. **Nominal :** ouvrir P1 depuis le catalogue. **Postcondition :** aucune modification métier. **Références :** EF-VIS-04, RB-02/16/19 ; `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC03-01 | P0 M APP | Ouvrir P1. | Fiche P1, prix TTC 12,00 €, TVA 20 %, catégorie et disponibilité cohérents. |
| TC03-02 | P1 M APP | Ouvrir P2 en rupture. | Rupture clairement indiquée ; ajout au panier non disponible via l'interface. |
| TC03-03 | P0 M APP | Ouvrir directement l'URL de P3 masqué. | Réponse 404 générique ; aucune fiche de P3 divulguée. |
| TC03-04 | P1 M APP | Ouvrir un slug inexistant. | Page 404, sans détail technique. |
| TC03-05 | P1 M APP | Supprimer ou masquer un produit de test non commandé après avoir gardé son URL, puis recharger. | La fiche ne reste pas accessible publiquement. |
| TC03-06 | P2 M APP | Créer un produit sans image locale valide. | Repli visuel présent ; pas de lien d'image cassé ni de contenu externe chargé. |
| TC03-07 | P1 M SPEC | Avec P1 stock 5, essayer quantités 1, 5 puis 6. | 1 et 5 accessibles ; aucune quantité effective supérieure à 5 dans le panier. |
| TC03-08 | P1 R SPEC | Soumettre une URL ou un champ contenant du HTML dans l'environnement de recette. | Pas d'exécution de script ni de divulgation de fichier. |

## UC-04 — Créer un compte

**Acteur :** visiteur. **But :** créer un compte client et se connecter. **Déclencheur :** validation de `/inscription`. **Préconditions :** adresse de test neuve, CGV consultables, session visiteur. **Nominal :** renseigner identité, e-mail neuf, mot de passe et confirmation, cocher les CGV, créer. **Postcondition :** un client créé et connecté ; aucun compte administrateur créé. **Références :** EF-VIS-06, RB-01/12/13 ; `tests/php/features_test.php`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC04-01 | P0 M APP | Utiliser un e-mail neuf et `Test2026!` deux fois, cocher les CGV et créer. | Compte créé ; connexion et accès à `/compte`. Aucun mot de passe en clair affiché. |
| TC04-02 | P0 M APP | Réessayer avec l'e-mail déjà créé. | Doublon refusé ; aucun second compte portant le même e-mail. |
| TC04-03 | P1 M APP | Omettre nom, prénom ou saisir un e-mail invalide. | Création refusée ; messages de validation ; aucune création partielle. |
| TC04-04 | P1 M APP | Donner deux mots de passe différents ou ne pas accepter les CGV. | Création refusée et cause indiquée. |
| TC04-05 | P1 R APP | Tester des mots de passe ASCII de 7 puis 8 caractères avec un e-mail différent par essai. | 7 caractères refusés ; comparer le résultat du cas à 8 à la politique affichée et noter le comportement observé. |
| TC04-06 | P1 R SPEC | Soumettre 8 caractères uniquement alphabétiques puis uniquement numériques, avec confirmation. | Selon l'aide affichée, lettre **et** chiffre devraient être exigés ; l'audit du contrôleur ne montre que la longueur minimale. Écart possible à relever, pas succès présumé. |
| TC04-07 | P0 R SPEC | Retirer le jeton CSRF, puis sur une autre inscription à e-mail neuf injecter `role=SUPER`. | Sans jeton : refus sans création ; avec champ rôle ajouté à une demande valide : compte seulement client. |
| TC04-08 | P1 M SPEC | Ajouter P1 au panier visiteur, puis s'inscrire. | Vérifier que le panier attendu reste cohérent après connexion ; noter toute perte éventuelle. |
| TC04-09 | P1 I SPEC | Deux inscriptions simultanées avec le même e-mail, uniquement en recette isolée. | Au plus un compte créé ; aucune donnée partielle ni erreur technique divulguée. |

## UC-05 — Connexion, déconnexion et gestion du compte

**Acteur :** visiteur ou client. **But :** s'authentifier, gérer son profil et se déconnecter. **Déclencheur :** `/connexion` ou `/compte`. **Préconditions :** C1 et C2 actifs et distincts. **Nominal :** se connecter comme C1, modifier une information de test, enregistrer puis se déconnecter. **Postcondition :** seule la fiche C1 change ; session client fermée après déconnexion. **Références :** EF-VIS-07, EF-CLI-01/02, RB-01/12 ; `tests/php/features_test.php`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC05-01 | P0 M APP | Connecter C1 avec ses bons identifiants. | Accès à son compte et à ses commandes. |
| TC05-02 | P0 M APP | Mauvais mot de passe pour C1 puis adresse inexistante. | Accès refusé ; message générique identique pour ces deux cas. |
| TC05-03 | P1 M APP | Modifier le téléphone de test de C1, enregistrer puis recharger. | Nouvelle valeur conservée pour C1 seulement. |
| TC05-04 | P1 M APP | Remplacer l'e-mail de C1 par celui de C2. | Refus du doublon ; les deux comptes restent inchangés. |
| TC05-05 | P1 M APP | Changer le mot de passe d'un compte **jetable** avec un ancien mot de passe erroné. | Refus ; ancien mot de passe toujours valide. |
| TC05-06 | P1 M APP | Sur un compte jetable, changer avec ancien mot de passe correct et confirmation ; se reconnecter. | Nouveau mot de passe accepté ; ancien refusé ; aucun mot de passe affiché en clair. |
| TC05-07 | P0 M APP | Déconnecter C1 puis ouvrir `/compte` et `/mes-commandes`. | Redirection ou refus ; aucune donnée privée affichée. |
| TC05-08 | P0 R SPEC | Contrôler la régénération d'identifiant après authentification, sans publier les cookies en preuve. | Ancien identifiant ne donne pas accès à la session authentifiée. |
| TC05-09 | P1 I SPEC | Tester plusieurs échecs et l'expiration sur une instance jetable, sans attaquer un service partagé. | Comparer les protections réellement observées au cahier des charges ; temporisation/expiration ne sont pas considérées acquises sans mesure. |
| TC05-10 | P1 R SPEC | Envoyer un changement de profil sans jeton CSRF. | Refus ; profil inchangé. |

## UC-06 — Gérer le panier

**Acteur :** visiteur ou client. **But :** ajouter, modifier, retirer et vérifier les montants. **Déclencheur :** ajout ou ouverture de `/panier`. **Préconditions :** P1 visible stock 5, P2 stock 0, P3 masqué ; panier vide dans une session isolée. **Nominal :** ajouter P1, modifier sa quantité, contrôler le total et retirer. **Postcondition :** état de panier cohérent ; aucun stock débité avant commande. **Références :** EF-CLI-03/04/05/06, RB-15/18/19 ; `tests/php/unit_tests.php`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC06-01 | P0 M APP | Ajouter 1 P1 puis ouvrir `/panier`. | P1, quantité 1, prix TTC 12,00 € et total calculé côté serveur. |
| TC06-02 | P1 M APP | Ajouter P1 une deuxième fois puis changer la quantité à 5. | Une ligne agrégée ; quantité 5 ; total marchandises 60,00 €. |
| TC06-03 | P0 M/R APP | Demander plus de 5 P1 par formulaire ou requête de recette. | Quantité effective plafonnée à 5 ; stock réel inchangé tant qu'aucune commande n'est créée. |
| TC06-04 | P1 M APP | Régler la quantité à 0 puis retirer une ligne. | Ligne retirée ; panier vide si elle était seule. |
| TC06-05 | P0 M/R SPEC | Tenter d'ajouter P2 en rupture et P3 masqué. | Aucune quantité positive achetable pour P2 ; P3 ne peut pas entrer dans un panier valide. |
| TC06-06 | P1 M APP | Après ajout de P1, masquer P1 depuis A1 puis rouvrir le panier C1. | P1 n'est plus présenté comme achetable ; rétablir seulement dans la recette isolée. |
| TC06-07 | P1 M SPEC | Avec des prix de test adaptés, vérifier sous-total et port à 0,01 € sous, exactement à, puis à 0,01 € au-dessus de la franchise configurée. | Port calculé selon les paramètres réels ; total TTC = marchandises TTC + port. |
| TC06-08 | P1 M SPEC | Désactiver JavaScript dans le navigateur de test puis ajouter et retirer P1. | Formulaires classiques fonctionnels sans dépendre de JavaScript. |
| TC06-09 | P0 R SPEC | Modifier les champs de prix client ou omettre CSRF dans une requête de recette. | Prix serveur seul pris en compte ; sans CSRF, aucune mutation de panier. |
| TC06-10 | P1 M SPEC | Dans deux sessions distinctes, modifier le panier C1 puis consulter C2. | Aucun article ou quantité n'est partagé entre les sessions. |

## UC-07 — Passer une commande

**Acteur :** client. **But :** transformer un panier en commande simulée. **Déclencheur :** confirmation sur `/commande/valider`. **Préconditions :** C1 connecté, P1 visible en stock, adresse de test, panier non vide. **Nominal :** contrôler panier et port, saisir adresse, confirmer le paiement simulé, noter le numéro et vérifier le détail. **Postcondition :** commande et lignes enregistrées, stock débité une seule fois, panier vidé ; aucun paiement réel. **Références :** EF-CLI-07, RB-04/06/15/18/20 ; `tests/php/run_tests.php`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC07-01 | P0 M APP | Commander 2 P1 (stock initial 5) avec adresse et paiement simulé coché. | Une commande numérotée, statut payé simulé, lignes au prix TTC 12,00 €, stock final 3 et panier vide. |
| TC07-02 | P1 M APP | Commander P1 en décochant le paiement simulé. | Commande enregistrée en préparation, sans débit réel ; totaux et stock cohérents. |
| TC07-03 | P0 M APP | Tenter de valider un panier vide. | Retour au catalogue ou message ; aucune commande créée. |
| TC07-04 | P1 M APP | Depuis une session visiteur, demander la validation d'un panier. | Connexion exigée avant création ; aucune commande au nom d'un autre client. |
| TC07-05 | P1 M APP | Retirer l'adresse et soumettre. | Refus ; panier et stock inchangés, aucune commande. |
| TC07-06 | P0 I SPEC | Mettre P4 dans le panier puis consommer son unique unité dans une autre session avant validation. | Pas de survente ; si l'opération échoue, aucune commande partielle ni stock négatif. |
| TC07-07 | P0 I SPEC | Avec deux lignes en panier, rendre l'une indisponible juste avant confirmation. | Vérifier le recalcul puis, en cas d'échec de création, rollback total ; aucune écriture partielle ni débit de l'autre ligne. |
| TC07-08 | P0 R SPEC | Double clic / rejouer exactement le POST dans une instance isolée. | Au plus une commande et un seul débit. La consommation du jeton ou l'idempotence ne sont pas établies : noter tout doublon comme anomalie. |
| TC07-09 | P0 I SPEC | Deux clients commandent simultanément P4 (stock 1). | Au plus une unité vendue et stock final non négatif ; mesurer séparément JSON et MariaDB, ne pas présumer la sûreté concurrente. |
| TC07-10 | P1 M APP | Après O2, modifier le prix catalogue de P1 dans la recette puis relire O2. | Le prix de la ligne de commande reste celui figé lors de la validation. |
| TC07-11 | P0 R SPEC | Soumettre une commande sans CSRF ou avec prix manipulé. | Sans jeton : aucune commande ; prix manipulé ignoré, calcul serveur fait foi. |
| TC07-12 | P1 I SPEC | Couper le stockage pendant une validation en environnement jetable. | Message sans secret et état atomique : pas de commande/lignes/stock incohérents. Ne pas couper la base de l'équipe. |

## UC-08 — Consulter l'historique des commandes

**Acteur :** client. **But :** consulter ses commandes et leur détail. **Déclencheur :** ouverture de `/mes-commandes`. **Préconditions :** C1 et C2 distincts ; O1 à C1 ; au moins une commande de C2 pour comparaison. **Nominal :** C1 ouvre sa liste puis O1. **Postcondition :** aucune modification métier. **Références :** EF-CLI-08/09, SEC-08 ; `tests/php/features_test.php`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC08-01 | P0 M APP | C1 ouvre `/mes-commandes` puis O1. | Numéro, date, statut, total, lignes et historique de O1 affichés. |
| TC08-02 | P1 M APP | Avec un client de test sans commande, ouvrir la liste. | État vide clair et lien vers le catalogue. |
| TC08-03 | P0 M APP | C2 ouvre directement l'URL de O1. | Accès refusé ou redirection, sans nom, adresse, lignes ou montant de O1. |
| TC08-04 | P0 M APP | Sans connexion, ouvrir la liste puis une URL de détail. | Connexion exigée ; aucun contenu privé divulgué. |
| TC08-05 | P1 M SPEC | Demander un identifiant inexistant ou mal formé. | Refus ou page générique, sans message technique ou donnée d'un autre client. |
| TC08-06 | P1 M APP | Comparer le prix figé dans O1 avec le prix courant de P1 modifié en recette. | Le détail conserve le prix enregistré lors de la commande. |
| TC08-07 | P1 M SPEC | Modifier le statut de O1 par A1 puis recharger son détail par C1. | Nouveau statut et historique cohérents ; aucune autre commande modifiée. |
| TC08-08 | P2 M SPEC | Ouvrir la liste sur écran étroit au clavier. | Tableau consultable, y compris par défilement horizontal local si nécessaire. |

## UC-09 — Annuler une commande

**Acteur :** client propriétaire. **But :** annuler techniquement une commande simulée non expédiée. **Déclencheur :** bouton sur le détail. **Préconditions :** O1 appartient à C1, statut `EN_PREPARATION` ou `PAYEE`, lignes et stocks notés. **Nominal :** ouvrir O1, annuler, comparer statut, montant et stock. **Postcondition :** O1 annulée, port et total soldés, unités restituées une seule fois. **Références :** EF-CLI-10, RB-11/18/20 ; `tests/php/run_tests.php`, `scripts/wasm-e2e.mjs`. **Ne pas confondre avec la rétractation légale**, non opérationnelle ici.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC09-01 | P0 M APP | Annuler O1 avant expédition ; relever stock avant/après. | Statut annulé ; quantité exacte restituée ; total et port à zéro. |
| TC09-02 | P0 R SPEC | Rejouer une deuxième fois l'annulation de la même O1 dans une instance isolée. | Aucune seconde restitution ; commande et stock restent inchangés. |
| TC09-03 | P0 M/R APP | C2 tente d'annuler O1 de C1. | Refus ; commande et stock inchangés, aucune donnée privée divulguée. |
| TC09-04 | P0 M/R APP | Tenter d'annuler comme client une commande déjà expédiée ou livrée. | Refus ou absence de bouton ; aucune restitution de stock. |
| TC09-05 | P1 M APP | Ouvrir le détail avant expédition. | Le texte distingue clairement annulation technique et droit de rétractation ; aucune promesse de rétractation fonctionnelle. |
| TC09-06 | P0 R SPEC | Soumettre l'annulation sans CSRF ou sans session client. | Refus ; aucun changement. |
| TC09-07 | P0 I SPEC | Simuler une erreur lors de la restitution, uniquement sur une copie jetable. | Transaction annulée : pas de restitution partielle ni de statut incohérent. |
| TC09-08 | P1 M APP | Annuler une commande comportant plusieurs produits de test. | Chaque stock est restitué selon sa quantité, sans doublon ; montant et port soldés. |

## UC-10 — Gérer les produits

**Acteur :** administrateur autorisé. **But :** créer, modifier, masquer et supprimer un produit. **Déclencheur :** action dans `/admin/produits`. **Préconditions :** A1, K1, référence et slug uniques ; produits de test sans données réelles. **Nominal :** créer, retrouver, modifier et vérifier le produit dans le catalogue. **Postcondition :** fiche et visibilité cohérentes ; produit lié à une commande conservé par masquage. **Références :** EF-ADM-01/02, RB-02/07/14/16/19 ; `tests/php/features_test.php`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC10-01 | P0 M APP | A1 crée P1 visible : HT 10 €, TVA 20 %, stock 5, K1. | Produit créé ; TTC 12,00 € ; affiché côté public. |
| TC10-02 | P1 M APP | Modifier nom et prix HT de P1 puis recharger. | Modification persistée ; TTC recalculé ; anciennes commandes gardent leur prix figé. |
| TC10-03 | P1 M APP | Masquer P1, puis le rendre visible. | Absent du catalogue public et fiche inaccessible quand masqué ; retour après réactivation. |
| TC10-04 | P1 M APP | Créer un produit avec référence ou slug déjà utilisé. | Doublon refusé ; pas de seconde fiche. |
| TC10-05 | P0 M/R APP | Essayer prix HT 0 ou négatif et stock négatif. | Refus par navigateur ou serveur ; aucun produit incohérent créé. |
| TC10-06 | P1 M APP | Supprimer un produit de test jamais commandé. | Produit supprimé et plus visible publiquement. |
| TC10-07 | P0 M APP | Demander la suppression d'un produit de test déjà commandé. | Produit masqué et données historiques de commande conservées. |
| TC10-08 | P1 M APP | Créer un produit visible et vérifier l'accueil. | Il apparaît parmi les derniers ajouts selon sa date de création et l'ID en cas d'égalité ; date et heure affichées depuis la base. |
| TC10-09 | P0 R SPEC | Un client ou visiteur tente l'action d'administration ou une soumission sans CSRF. | Refus ; aucune modification produit. |
| TC10-10 | P1 M/R SPEC | Saisir nom contenant du HTML et une image distante ou un chemin externe. | Aucune exécution de script ; les ressources non autorisées ne sont pas chargées. |
| TC10-11 | P1 I SPEC | Deux créations concurrentes de même référence, seulement en recette isolée. | Au plus un produit ; intégrité préservée. |

## UC-11 — Gérer les catégories

**Acteur :** administrateur autorisé. **But :** créer, modifier et supprimer une catégorie. **Déclencheur :** `/admin/categories`. **Préconditions :** A1, K0 vide, K1 contenant P1, noms de test uniques. **Nominal :** créer une catégorie de test, la modifier puis la supprimer si vide. **Postcondition :** catégorie créée ou modifiée correctement, suppression d'une catégorie occupée refusée. **Références :** EF-ADM-03, RB-07/14 ; `tests/php/features_test.php`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC11-01 | P1 M APP | Créer K0 avec nom unique et slug vide. | Catégorie créée ; slug généré automatiquement. |
| TC11-02 | P1 M APP | Modifier le nom et la description de K0. | Valeurs mises à jour et retrouvées après rechargement. |
| TC11-03 | P1 M APP | Supprimer K0 sans produit rattaché. | K0 disparaît ; autres catégories inchangées. |
| TC11-04 | P0 M APP | Tenter de supprimer K1 contenant P1. | Suppression refusée ; K1 et P1 restent présents. |
| TC11-05 | P1 M APP | Créer une catégorie avec un nom déjà présent **uniquement sur K0 de test**. | Vérifier la règle de fusion/mise à jour existante ; pas de seconde catégorie homonyme. Ne pas écraser une catégorie partagée. |
| TC11-06 | P1 M/R SPEC | Envoyer nom vide ou slug déjà pris par une autre catégorie. | Refus ou validation explicite ; pas d'altération d'une catégorie non ciblée. |
| TC11-07 | P0 R SPEC | Tenter une modification sans session admin ou sans CSRF. | Refus ; catégories inchangées. |
| TC11-08 | P1 M SPEC | Saisir un nom contenant HTML dans une catégorie de recette. | Texte échappé ; aucun script exécuté dans le catalogue ou l'administration. |

## UC-12 — Gérer les stocks

**Acteur :** administrateur autorisé. **But :** ajuster un stock avec motif et consulter les alertes. **Déclencheur :** `/admin/stocks`. **Préconditions :** A1, P1 stock 5 et seuil connu, P2 stock 0 ; données isolées. **Nominal :** ajuster P1 avec motif et vérifier la liste. **Postcondition :** stock modifié uniquement pour le produit visé ; aucun stock négatif. **Références :** EF-ADM-04/05, RB-03/17/18 ; `tests/php/run_tests.php`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC12-01 | P0 M APP | P1 stock 5 : mode « Fixer à », quantité 8, motif de test. | P1 vaut 8 ; confirmation ; autres stocks inchangés. |
| TC12-02 | P1 M APP | P1 stock 5 : mode « Varier de », quantité −2, motif. | P1 vaut 3 ; quantité exacte. |
| TC12-03 | P0 M/R APP | P1 stock 5 : varier de −6 ou fixer à −1. | Refus ; P1 reste à 5. |
| TC12-04 | P1 M APP | Soumettre un ajustement sans motif. | Refus navigateur ou serveur ; stock inchangé. |
| TC12-05 | P1 M APP | Filtrer « Ruptures », « Très bas » puis « Disponibles ». | P2 est en rupture ; P1 apparaît selon son stock et le seuil réel. |
| TC12-06 | P1 M SPEC | Vérifier la trace du mouvement et son motif dans le moyen d'audit effectivement disponible. | Trace suffisamment exploitable si exigée ; noter un écart si aucun motif n'est retrouvé. |
| TC12-07 | P0 R SPEC | Ajuster stock sans droit administrateur ou sans CSRF. | Refus ; aucun changement. |
| TC12-08 | P0 I SPEC | Ajustement et commande simultanés sur P4 dans une base jetable. | Aucun stock négatif ni mouvement perdu ; scénario à mesurer séparément pour chaque pilote. |
| TC12-09 | P2 M SPEC | Avec seuil 3, tester stocks −1, 0, 2, 3 et 4. | −1 refusé ; 0 en rupture ; 2 et 3 très bas ; 4 disponible. |

## UC-13 — Consulter et filtrer les commandes côté administration

**Acteur :** administrateur autorisé ou gestionnaire selon droits. **But :** consulter commandes, détails, historique et indicateurs. **Déclencheur :** ouverture de `/admin/commandes` ou `/admin`. **Préconditions :** A1, O1 et O2 avec statuts ou clients distincts ; G1 si disponible. **Nominal :** lister, filtrer, ouvrir un détail puis consulter les indicateurs. **Postcondition :** aucune modification métier. **Références :** EF-ADM-06/08/09, RB-10/11/15 ; `tests/php/features_test.php`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC13-01 | P0 M APP | A1 ouvre `/admin/commandes`. | Liste des commandes autorisées avec numéro, date, client, statut et montant. |
| TC13-02 | P1 M APP | Filtrer par statut, e-mail client ou numéro connu. | Seules les commandes correspondantes apparaissent. |
| TC13-03 | P1 M APP | Filtrer par période incluant O2 et montant minimum connu. | Résultat cohérent avec les dates et montants enregistrés. |
| TC13-04 | P1 M APP | Saisir une valeur sans correspondance. | Liste vide sans erreur ni fuite technique. |
| TC13-05 | P1 M APP | Ouvrir le détail de O2. | Lignes, quantités, prix figés, port, statut et piste d'audit cohérents. |
| TC13-06 | P1 M SPEC | Vérifier les indicateurs du tableau de bord avec un jeu de recette connu. | Totaux et top produits cohérents avec les commandes prises en compte ; aucune valeur inventée. |
| TC13-07 | P0 M/R APP | Visiteur ou client tente `/admin/commandes` et un détail direct. | Redirection ou refus ; aucune donnée de commande administrative divulguée. |
| TC13-08 | P1 M APP | Demander le détail d'un ID inexistant. | Message ou redirection générique, sans erreur technique. |
| TC13-09 | P1 M/R SPEC | Si G1 existe, comparer ses droits aux écrans et fonctions de SUPER. | Les actions réservées à SUPER restent interdites au gestionnaire ; ne pas créer de rôle sans autorisation. |
| TC13-10 | P2 M SPEC | Tester une période inversée et une date invalide. | Pas de fuite ni plantage ; relever le comportement exact et signaler si l'information est ambiguë. |

## UC-14 — Modifier le statut d'une commande

**Acteur :** administrateur autorisé. **But :** faire évoluer une commande, avec piste d'audit. **Déclencheur :** formulaire du détail `/admin/commandes/{id}`. **Préconditions :** A1, O2 avec statut et stock connus ; une commande distincte pour chaque transition. **Nominal :** choisir un statut proposé et valider. **Postcondition :** transition valide, historique cohérent et aucune modification non prévue. **Références :** EF-ADM-07, RB-11/13/15 ; `tests/php/run_tests.php`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC14-01 | P0 M APP | Sur O2 `PAYEE`, choisir `EXPEDIEE`, puis ouvrir l'historique. | Statut expédié ; une trace de changement avec acteur et date ; montant inchangé. |
| TC14-02 | P1 M APP | Sur une commande `EXPEDIEE`, choisir `LIVREE`. | Statut livré ; état terminal sans transition proposée. |
| TC14-03 | P0 R APP | Tenter une transition interdite sur une commande de recette. | Refus ; statut et historique ne changent pas. |
| TC14-04 | P1 R APP | Rejouer une demande de statut identique avec jeton valide. | Statut déjà à jour ; pas de nouvelle ligne d'historique. |
| TC14-05 | P1 M APP | Demander `ANNULEE` sans commentaire depuis l'administration. | Refus ; statut inchangé. |
| TC14-06 | P0 M SPEC | Sur une commande de test non expédiée, annuler via administration avec commentaire ; comparer lignes, stock, total et port. | Vérifier la cohérence métier attendue et restitution unique. **Point à examiner :** le chemin `updateOrderStatus` change le statut sans appeler `cancelOrder` ; ne pas supposer qu'il restitue le stock ou solde les montants. |
| TC14-07 | P0 R SPEC | Visiteur/client ou requête sans CSRF tente de modifier un statut. | Refus ; aucun changement ni ligne d'historique. |
| TC14-08 | P1 R SPEC | Cibler un ID inexistant ou un statut inconnu. | Refus générique ; aucune commande créée ou modifiée. |
| TC14-09 | P1 I SPEC | Tester chaque ligne de la matrice ci-dessous avec commandes indépendantes, une transition par commande. | Transitions autorisées acceptées, interdites refusées, répétitions sans doublon d'historique ; résultat réel enregistré par couple. |
| TC14-10 | P0 I SPEC | Simuler un échec de stockage pendant une transition, sur une copie jetable. | Pas d'état partiellement modifié ; aucun détail technique au visiteur. |

### Matrice des 36 couples de statuts — oracle applicatif à vérifier

`A` : transition autorisée ; `X` : interdite ; `I` : même statut, opération idempotente attendue par le repository (le formulaire ne propose pas forcément ce cas). Les lignes représentent l'état **avant**, les colonnes l'état **demandé**. `EXPEDIEE → ANNULEE` est une possibilité de la matrice **administrateur** ; un client ne peut pas annuler une commande expédiée. Les annulations doivent aussi vérifier leurs effets métier, pas seulement la case de la matrice.

| Origine → cible | BROUILLON | EN_PREPARATION | PAYEE | EXPEDIEE | LIVREE | ANNULEE |
|---|---:|---:|---:|---:|---:|---:|
| BROUILLON | I | A | A | X | X | A |
| EN_PREPARATION | X | I | A | A | X | A |
| PAYEE | X | X | I | A | X | A |
| EXPEDIEE | X | X | X | I | A | A |
| LIVREE | X | X | X | X | I | X |
| ANNULEE | X | X | X | X | X | I |

**Exécution :** attribuer un résultat distinct à chacun des 36 couples sous l'identifiant `TC14-09 / ORIGINE → CIBLE`, sur une commande appropriée et une base jetable ; tracer si le cas est inaccessible en interface et nécessite une requête ou un test de repository. Toute annulation nécessite le commentaire exigé par le contrôleur. Comparer la matrice JSON et SQL sur leurs environnements respectifs, sans conclure pour SQL à partir du test JSON.

## 15. Scénarios transversaux — sécurité, responsive et informations

Ces scénarios complètent les UC : ils ne créent pas artificiellement de nouveaux numéros d'UC. Les tests marqués `I` ou `R` exigent une instance isolée et une autorisation explicite.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TCX-01 | P0 M APP | Sans session puis comme C1, ouvrir `/admin`, `/admin/produits`, `/admin/commandes` et `/admin/stocks`. | Refus ou redirection ; aucune information d'administration visible. |
| TCX-02 | P0 R SPEC | Sur les actions mutatives représentatives, retirer ou falsifier le CSRF. | Chaque mutation refusée ; avant/après identiques. Un code HTTP précis ne doit pas être supposé : certains formulaires redirigent. |
| TCX-03 | P0 R SPEC | Observer les cookies dans le navigateur de recette au démarrage, connexion et déconnexion, sans transmettre leur valeur en preuve. | Cookie de session documenté ; `HttpOnly`, `SameSite=Lax` et `Secure` en HTTPS correctement configuré ; identifiant renouvelé à la connexion. Vérifier également le proxy réel. |
| TCX-04 | P0 M/R SPEC | Saisir HTML ou guillemets dans recherche, nom de produit et catégorie de test. | Aucune balise exécutable ; valeur affichée échappée. |
| TCX-05 | P0 R SPEC | Essayer une commande ou action sur un identifiant appartenant à C2 depuis C1. | Aucune divulgation ou modification de C2. |
| TCX-06 | P1 M APP | Depuis le pied de page, ouvrir mentions, CGV, CGU, livraison, garanties, confidentialité, cookies et rétractation. | Pages accessibles et titres cohérents ; aucune 404 pour les liens internes. |
| TCX-07 | P0 M APP | Lire `/retractation`, l'annulation de commande et la politique de contact. | Annonce explicite : fonction de rétractation réelle et accusé durable non disponibles ; contact local de démonstration, sans e-mail ni délai de réponse garanti. |
| TCX-08 | P0 M SPEC | Audit de préparation d'une vente réelle : chercher la fonction de rétractation en ligne et son accusé durable. | **NON CONFORME / BLOQUÉ tant que non implémentés** ; une page statique ou annulation avant expédition ne suffisent pas. Voir [audit juridique](12-information-juridique.md). |
| TCX-09 | P1 M APP | Contrôler la page cookies et les outils de développement réellement accessibles. | `MINISHOPSESS` expliqué ; `MINISHOPSESSID` propre à l'outil de mesure mentionné ; aucun bandeau de choix fictif pour le seul cookie nécessaire identifié. |
| TCX-10 | P1 M/I SPEC | Dans le navigateur réel et sur l'hébergement cible, inventorier cookies, stockage et requêtes réseau, avant tout choix. | Aucun traceur non exempté déposé sans consentement ; inventorier les tiers éventuels. Les sources du dépôt ne suffisent pas à valider un hébergement externe. |
| TCX-11 | P1 M APP | Envoyer un contact de démonstration valide puis lire le retour. | Succès seulement si le journal local a pu être écrit ; pas de promesse d'e-mail ou de réponse. Utiliser des données fictives. |
| TCX-12 | P1 I APP | Sur une copie jetable, rendre le fichier de contact impossible à écrire puis soumettre. | L'échec est signalé ; aucun succès fictif. Ne pas modifier les permissions de l'instance partagée. |
| TCX-13 | P1 M SPEC | Tester accueil, produit, panier, commande et administration à 320, 375, 768 et 1440 px puis à 200 % de zoom. | Texte et actions lisibles, sans superposition ; pas de défilement horizontal global, sauf conteneur de tableau si nécessaire. Noter navigateur et largeur. |
| TCX-14 | P1 M SPEC | Parcourir menus, formulaires, filtres et panier au clavier ; activer la réduction des animations. | Focus visible, ordre logique, accès aux actions et aux messages ; consigner les limites observées. Ne pas conclure seul à une conformité WCAG/RGAA complète. |
| TCX-15 | P1 I SPEC | Tester panne et retour du magasin sur une instance jetable. | Message sans secrets, aucune mutation partielle, reprise cohérente. Ne pas interrompre MariaDB de l'équipe. |
| TCX-16 | P1 I SPEC | Mesurer p95 et charge avec un jeu de recette défini et une autorisation écrite. | Comparer aux seuils du cahier des charges ; joindre conditions et mesures réelles. **Aucune mesure n'est fournie ici.** |
| TCX-17 | P0 I SPEC | Vérifier la protection de `public/mesurer.php` et `public/gen_volumes.php` dans l'environnement de déploiement. | Outils de développement exclus d'un déploiement public ; ne pas considérer leur garde fondée sur l'hôte comme protection suffisante. |
| TCX-18 | P1 M SPEC | Vérifier suppression/retention des comptes, journaux et sauvegardes sur une instance jetable. | Constater l'état réel ; la valeur 36 mois seule ne prouve pas une purge automatique. Noter les actions manquantes. |

## 16. Synthèse vierge de campagne

Ces lignes ne remplacent pas le journal par scénario. Ne cocher un UC que lorsque les cas applicables de cet UC ont été joués et examinés ; si un cas critique est bloqué ou échoue, reporter l'écart et ne pas annoncer une validation complète.

| UC | Fonction | Scénarios | Statut initial | Preuves / anomalies |
|---|---|---|---|---|
| UC-01 | Catalogue | TC01-01 à TC01-10 | NON JOUÉ |  |
| UC-02 | Recherche | TC02-01 à TC02-09 | NON JOUÉ |  |
| UC-03 | Fiche produit | TC03-01 à TC03-08 | NON JOUÉ |  |
| UC-04 | Inscription | TC04-01 à TC04-09 | NON JOUÉ |  |
| UC-05 | Connexion et compte | TC05-01 à TC05-10 | NON JOUÉ |  |
| UC-06 | Panier | TC06-01 à TC06-10 | NON JOUÉ |  |
| UC-07 | Commande | TC07-01 à TC07-12 | NON JOUÉ |  |
| UC-08 | Historique | TC08-01 à TC08-08 | NON JOUÉ |  |
| UC-09 | Annulation client | TC09-01 à TC09-08 | NON JOUÉ |  |
| UC-10 | Produits | TC10-01 à TC10-11 | NON JOUÉ |  |
| UC-11 | Catégories | TC11-01 à TC11-08 | NON JOUÉ |  |
| UC-12 | Stocks | TC12-01 à TC12-09 | NON JOUÉ |  |
| UC-13 | Consultation administration | TC13-01 à TC13-10 | NON JOUÉ |  |
| UC-14 | Statuts | TC14-01 à TC14-10 et 36 couples TC14-09 | NON JOUÉ |  |
| Transversal | Sécurité, accessibilité, informations, exploitation | TCX-01 à TCX-18 | NON JOUÉ |  |

### Éléments à ne pas confondre

1. Les tests PHP/repository, les contrôles statiques et les requêtes HTTP WASM ne sont pas un test manuel réel sur navigateur ni une preuve de fonctionnement sur MariaDB. Les fichiers de test mentionnés dans chaque fiche n'automatisent **pas nécessairement chaque ligne** de la fiche.
2. Le cahier des charges décrit certaines exigences plus ambitieuses que le code ; en particulier la politique de mot de passe affichée, le rejeu de commande, l'annulation administrative et la purge sont à vérifier. Marquer les écarts et les corriger séparément après reproduction ; ne pas déduire une conformité globale d'un passage vert d'une suite partielle.
3. La page de rétractation est informative : pas de déclaration en ligne ni d'accusé durable réel. Pas de paiement, livraison ou médiation réelle. Aucun texte de cette fiche ne transforme la maquette en boutique juridiquement prête à vendre.
4. Le SQL doit être testé sur une **base de recette jetable identifiée et autorisée**, pas sur la base de travail. Le harnais SQL peut créer et supprimer une base de test : lire son code et vérifier la cible avant de l'utiliser. Ne jamais lancer de script de charge ou de reset par simple copier-coller.
5. Aucun temps de performance, résultat sur appareil, identité de testeur, historique ou validation humaine n'est fabriqué dans cette fiche.

### Contrôle automatique de la fiche

Après récupération des fichiers, depuis le dépôt local :

```bash
cd /home/jojokes/Documents/BUT/Projet/s302
python3 scripts/verifier_fiches_uc.py
```

Le contrôle vérifie la présence des 14 fiches, des identifiants distincts et ordonnés, de la synthèse, de la matrice et des liens locaux. Il ne lance **aucun scénario métier** et ne prouve pas le résultat de la recette.

### Auto-évaluation de la livraison documentaire

La fiche comporte 150 scénarios identifiés, dont 18 transversaux, et 36 couples de transition suivis séparément : 186 lignes dans le CSV, car `TC14-09` reste lui-même un scénario général. Les 14 UC sont couverts par au moins un nominal et des variantes ; l'analyse des limites, rôles, états et échecs réduit les angles morts sans démontrer l'exhaustivité. La vérification documentaire compare aussi la matrice au code JSON : elle ne démontre pas la conformité du moteur MariaDB ni celle de l'interface. Les scénarios restent à adapter au jeu de données de l'équipe, à exécuter puis à analyser ; les divergences APP/SPEC et les manques juridiques connus ne sont pas des résultats de tests.
