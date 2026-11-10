# Fiches de recette MiniShop — UC-01 à UC-14

**Révision de référence :** branche de cette fiche, à relever au lancement par `git rev-parse --short HEAD`. **Périmètre :** maquette web MiniShop (vues publiques, espace client, back-office, préférences locales FR/EN et fuseau horaire, outils CLI de maintenance), magasin JSON ou MariaDB/MySQL selon configuration. **Campagne manuelle : non exécutée par la rédaction de ce document.** Aucun résultat humain, testeur, date d'exécution ni preuve n'est prérempli.

## 0. Mode d'emploi

### Objectif et limites

Le tableau de suivi prêt à remplir est [13-suivi-recette-uc.csv](13-suivi-recette-uc.csv) : chaque scénario et chaque couple de statut y dispose de sa propre ligne, initialement `NON JOUÉ`. Ouvrir le fichier en UTF-8 avec séparateur point-virgule ; ne pas envoyer de preuve contenant des secrets.

Ce document transforme les 14 UC du [cahier des charges](01-cahier-des-charges-MiniShop.md) et de son [annexe](annexes/annexe-cas-usage.md), enrichis des évolutions livrées sur la maquette (confirmations explicites en deux étapes sans JavaScript, conservation de l'historique des lignes après annulation, mode d'ajustement de stock non destructif par défaut, préférences locales de langue FR/EN et de fuseau horaire sans tiers, tableaux administrateur en cartes mobiles étiquetées `data-label`, double implémentation décorative/informative des visuels produits, mentions légales étudiantes LCEN art. 6, III, 2 et outils CLI de conservation/purge), en scénarios de recette manuelle reproductibles. Il couvre nominal, alternatives, erreurs, bornes, sécurité, responsive, bilinguisme, fuseaux horaires, transitions et pannes prioritaires. « Tous les scénarios possibles » ne peut pas être garanti : les combinaisons de données, configurations et pannes sont ouvertes ; compléter la liste si un risque ou un défaut nouveau apparaît. Un scénario décrit **un résultat attendu**, pas un résultat constaté. Les anciennes annexes contiennent aussi des exigences ou détails techniques qui ne sont pas nécessairement implémentés : cette fiche distingue les attentes sans inventer de succès.

### Méthode étudiée

- [Microsoft — stratégie de tests](https://learn.microsoft.com/en-us/dynamics365/guidance/implementation-guide/testing-strategy-planning) : scénarios métier reproductibles, résultats attendus explicites, cas positifs et négatifs ; [Microsoft — exécution manuelle](https://learn.microsoft.com/en-us/azure/devops/test/run-manual-tests?view=azure-devops) : noter résultat, preuve et anomalie.
- [Atlassian — plan de test](https://www.atlassian.com/software/confluence/resources/guides/how-to/test-plan) et [Atlassian/Xray](https://www.atlassian.com/devops/testing-tutorials/jira-xray-integration-manage-test-cases) : périmètre, risques, identifiants, préconditions, étapes, critères et traçabilité. Cette fiche s'inspire de ces principes, **sans prétendre être un modèle interne officiel de ces entreprises**.
- [ISTQB — techniques de test](https://istqb.org/certifications/certified-tester-model-based-tester-ct-mbt/) : classes d'équivalence, valeurs limites, tables de décision et transitions d'état.
- [Google Testing Blog — bons tests de bout en bout](https://testing.googleblog.com/2016/09/testing-on-toilet-what-makes-good-end.html) : privilégier les parcours critiques de bout en bout, compléter par des tests plus ciblés ; un compteur de tests ne prouve pas l'absence de défauts.

### Codes et verdicts

- **P0** : critique (perte de données, paiement/stock, accès ou parcours bloqué) ; **P1** : important ; **P2** : complémentaire.
- **M** : navigateur ; **R** : requête ou outil contrôlé ; **I** : intégration isolée avec droits adaptés (ou CLI sur copie jetable). Ne pas lancer R/I sur une base partagée, sur Internet ou sans autorisation.
- **APP** : comportement attendu d'après le code consulté ; **SPEC** : exigence ou résultat souhaité à vérifier, **pas une promesse d'implémentation**. Un échec de SPEC est une anomalie ou un écart à qualifier, jamais un succès par défaut.
- Chaque scénario est initialement **NON JOUÉ**. Après exécution, reporter dans le journal : `OK`, `KO`, `BLOQUÉ`, `NON JOUÉ` ou `NON APPLICABLE`, le résultat observé et la preuve. Un contrôle d'une interface ne prouve pas une règle côté serveur ; les protections doivent aussi être vérifiées par les tests adaptés.

### Préparer une campagne sans détruire les données

1. Vérifier la version réellement utilisée (`git rev-parse --short HEAD`), le pilote (`json` ou `sql`), la base, l'URL, le navigateur, la largeur d'écran et l'état initial des préférences navigateur (`/preferences` : clé `localStorage` `minishop.preferences.v1`). Avant les scénarios nominaux en français, cliquer sur « Réinitialiser » dans `/preferences` afin de repartir sur le repli `Français` + `Europe/Paris`. Les données et identifiants des comptes de test ci-dessous sont des **exemples fictifs** ; ne jamais entrer de coordonnées personnelles réelles ou bancaires dans les formulaires.
2. Utiliser un environnement et une base de recette **distincts**, avec sauvegarde et autorisation du propriétaire. Les tests créent comptes et commandes et modifient stock, produits et statuts. Ne jamais lancer `--reset`, `--db-reset`, des scripts de volumétrie, de purge `--executer` ou une fixture SQL sur la base partagée. Une simple lecture peut créer une session et des journaux.
3. Utiliser si disponible un administrateur `SUPER` et, pour les contrôles de rôle, un gestionnaire `GESTIONNAIRE` autorisé à tester ; ne pas présumer les identifiants encore valides. Le jeu de démonstration versionné prévoit `alice@example.com` / `Demo2026!`, `bruno@example.com` / `Demo2026!`, `carla@example.com` / `Demo2026!` et `admin@minishop.fr` / `Admin2026!` si ces comptes n'ont pas été modifiés. Ne pas changer leurs mots de passe dans une base partagée.
4. Créer ou faire créer **dans la recette isolée** :
   - **C1** et **C2** : deux clients distincts actifs ; un e-mail neuf non utilisé, par exemple `uc-nouveau@example.test` ; **A1** : administrateur `SUPER` ; **G1** : administrateur `GESTIONNAIRE` si disponible.
   - **K1** : catégorie de test et **K0** : catégorie de test vide ; **P1** : produit visible, stock 5, prix HT 10,00 €, TVA 20 % (TTC 12,00 €) ; **P2** : visible, stock 0 ; **P3** : masqué ; **P4** : visible, stock 1. Références et noms uniques, par exemple `TEST-UC-P1` et `Produit UC P1`.
   - Noter les frais de port, la franchise, la TVA par défaut et la durée `conservation_compte` (`24` mois par défaut) dans `/admin/parametres`, sans les modifier sur une instance partagée. Prévoir trois commandes de C1 sur la base isolée : **O1**, non expédiée (`EN_PREPARATION` ou `PAYEE`), pour l'annulation client ; **O2**, distincte, pour les changements de statut nominaux ; **O3**, passée à `EXPEDIEE`, pour vérifier l'annulation administrative sans retour automatique de stock. Noter références, identifiants, stocks initiaux et URL de détail.
5. Pour chaque ligne, remettre **uniquement les données de recette isolées** dans l'état indiqué ; si ce n'est pas possible, marquer `BLOQUÉ`. Les opérations de remise en état doivent être décidées par le responsable de l'environnement ; ne pas improviser de suppression ou réinitialisation.

### Déroulement et preuves

Pour un parcours, ouvrir les routes sur l'origine de l'instance, par exemple `http://localhost:8000/catalogue` si le serveur local est configuré sur ce port. Relever **avant et après** : stock, panier, numéro et statut de commande, montants (`montant_total`, `frais_port`, et `Total de la commande avant annulation` le cas échéant), langue/fuseau actifs et éventuelle écriture d'audit. Capturer une preuve expurgée : aucune donnée réelle, mot de passe, cookie ou secret. Pour les transitions, utiliser une commande distincte par cas ou une fixture isolée ; ne pas supposer qu'on peut revenir à un état précédent.

Journal à recopier pour chaque scénario joué :

| Champ | À renseigner par le testeur |
|---|---|
| Campagne / ID scénario |  |
| Version / pilote / navigateur / langue & fuseau |  |
| Date / testeur |  |
| Statut (`OK`, `KO`, `BLOQUÉ`, `NON JOUÉ`, `NON APPLICABLE`) | NON JOUÉ |
| Résultat observé / écarts |  |
| Preuve expurgée / ticket d'anomalie |  |
| Données utilisées / remise en état |  |

Une anomalie doit indiquer l'ID, la version, le prérequis, les étapes, l'attendu, l'observé, la gravité et la preuve. Ne pas conclure « réussi » sans exécution et comparaison. Les tests des scénarios `SPEC` peuvent révéler des fonctions absentes.

---

## UC-01 — Consulter le catalogue

**Acteur :** visiteur. **But :** parcourir l'accueil, les catégories, les filtres, le tri et les pages. **Déclencheur :** ouverture de `/`, `/catalogue` ou `/categories`. **Préconditions :** K1, P1, P2 et P3 configurés ; préférences réinitialisées (`Français`, `Europe/Paris`). **Nominal :** ouvrir le catalogue, voir les cartes, choisir K1, passer de page si possible. **Postcondition :** aucune modification métier de produit ou commande ; une session technique peut être créée. **Références :** EF-VIS-01/03/05, RB-19 ; tests automatisés associés : `tests/php/catalogue_stock_test.php`, `tests/php/date_ajout_test.php`, `tests/php/cartes_accessibilite_test.php`, `scripts/wasm-e2e.mjs` (couverture partielle, à rejouer manuellement).

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC01-01 | P0 M APP | Ouvrir `/catalogue`, puis K1 ; rechercher ou paginer pour trouver P1 et P2. | P1 et P2 visibles ; P3 absent ; cartes avec nom, prix TTC et disponibilité. |
| TC01-02 | P1 M APP | Ouvrir `/categories`, vérifier l'alignement centré de la section et la netteté des icônes SVG, puis cliquer K1. | Section centrée et icônes propres ; navigation vers K1 ; compteur cohérent avec les seuls produits visibles. |
| TC01-03 | P1 M APP | Sélectionner K0 vide. | Message de liste vide clair et lien concret permettant de revenir au catalogue complet. |
| TC01-04 | P1 M APP | Cumul K1 + prix min/max + « en stock ». | Chaque carte respecte tous les critères ; l'URL partageable reproduit exactement la même liste en navigation privée. |
| TC01-05 | P1 M APP | Choisir tri prix croissant puis décroissant avec produits en stock et en rupture. | Dans chaque groupe, l'ordre choisi est respecté ; les produits en rupture figurent en fin de liste. |
| TC01-06 | P1 M APP | Parcourir pages 1 et 2 avec plus de 12 résultats. | Au plus 12 cartes par page par défaut (plafond serveur 60), aucune répétition ni omission ; filtres conservés d'une page à l'autre. |
| TC01-07 | P2 R APP | Demander `page=0`, une page très grande et un tri inconnu sur la copie de recette. | Pas d'erreur technique ; page inférieure bornée à 1, page hors résultat vide avec lien retour page 1, tri inconnu ramené au tri par défaut. |
| TC01-08 | P1 M APP | Ouvrir `/` et comparer les quatre derniers ajouts aux dates UTC stockées et aux ID visibles, puis changer de fuseau dans `/preferences`. | Tri par date de création décroissante puis ID décroissant en cas d'égalité ; balise `<time data-date-format="ajout" datetime="…Z">` fidèle à l'UTC de chaque produit, affichée en `Europe/Paris` sans JS et convertie dans le fuseau choisi avec JS. |
| TC01-09 | P2 M SPEC | Réduire la fenêtre à 320 px puis 375 px, zoomer à 200 %, parcourir au clavier. | Contenu, filtres et commandes utilisables sans texte coupé ni débordement horizontal de la page ; focus clavier visible partout. |
| TC01-10 | P1 I SPEC | Simuler une indisponibilité du magasin uniquement sur une instance jetable. | Message générique sans secret ni trace technique au visiteur (ni promesse infondée sur la page 500) ; aucune altération métier. Noter le code HTTP obtenu. |
| TC01-11 | P1 M APP | Inspecter les visuels sur `/` et `/catalogue` (photographie d'accueil vs vignettes des cartes produits). | Photographie d'accueil locale informative avec `alt` descriptif et dimensions explicites ; vignettes des cartes produits décoratives (`alt=""`) sans redondance vocale avec le titre adjacent ; zéro requête réseau externe. |

## UC-02 — Rechercher un produit

**Acteur :** visiteur. **But :** rechercher par nom, description ou référence et combiner avec les filtres. **Déclencheur :** saisie d'une recherche sur `/recherche` ou `/catalogue`. **Préconditions :** P1 visible et mot distinctif connu. **Nominal :** saisir le mot, valider, consulter les résultats. **Postcondition :** pas d'écriture métier. **Références :** EF-VIS-02, RB-19, SEC-02 ; `tests/php/catalogue_stock_test.php`, `tests/php/catalogue_filtres_test.php`, `scripts/ui-e2e-catalogue.mjs`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC02-01 | P0 M APP | Rechercher le nom exact de P1. | P1 apparaît avec son nom et son prix TTC corrects. |
| TC02-02 | P1 M APP | Rechercher une partie du nom puis la référence de P1. | P1 figure dans chaque résultat correspondant. |
| TC02-03 | P1 M SPEC | Rechercher une variante de casse et, avec un produit accentué, sans accent. | Résultats équivalents pour la casse et les accents ; noter pilote et extensions (`intl`) si écart. |
| TC02-04 | P1 M APP | Rechercher un terme inexistant puis une saisie vide. | Aucun résultat avec message adapté et lien de retour pour le premier ; recherche vide équivalente au catalogue. |
| TC02-05 | P1 M APP | Rechercher le nom de P3 masqué. | P3 n'apparaît jamais au visiteur. |
| TC02-06 | P1 M APP | Cumul recherche, catégorie, prix, disponibilité et pagination. | Seuls les résultats satisfaisant tous les filtres apparaissent ; tous les critères restent dans l'URL au changement de tri ou de page. |
| TC02-07 | P1 R SPEC | Envoyer une recherche de 120 puis 121 caractères sur la recette isolée. | Aucune erreur technique ; traitement borné à la limite serveur ou refus explicite, sans divulgation. |
| TC02-08 | P0 M SPEC | Rechercher littéralement `' OR 1=1 --` et `<script>alert(1)</script>` dans la recette. | Aucun script exécuté, aucune modification de données ; la recherche ne retourne pas tous les produits par injection. |
| TC02-09 | P2 R SPEC | Tester caractères Unicode, espaces et caractères `%` / `_` dans la recette. | Aucun plantage ni fuite ; documenter si les caractères SQL sont traités comme littéraux ou motifs. |
| TC02-10 | P1 M APP | Appliquer plusieurs filtres simultanés sur `/catalogue`, puis cliquer sur le lien de réinitialisation des filtres. | Tous les critères sont effacés d'un coup et le catalogue complet des produits visibles réapparaît. |

## UC-03 — Consulter une fiche produit

**Acteur :** visiteur. **But :** consulter caractéristiques, visuel indicatif, prix TTC et stock. **Déclencheur :** clic sur une carte ou URL `/produit/{slug}`. **Préconditions :** P1 visible, P2 en rupture, P3 masqué. **Nominal :** ouvrir P1 depuis le catalogue. **Postcondition :** aucune modification métier. **Références :** EF-VIS-04, RB-02/16/19 ; `tests/php/fiche_produit_test.php`, `scripts/ui-e2e-produit.mjs`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC03-01 | P0 M APP | Ouvrir P1 (`/produit/{slug}`). | Fiche complète de P1 : prix TTC 12,00 € (TVA 20 % incluse), catégorie, stock disponible, mention pédagogique de visuel indicatif et fil d'Ariane cohérents. |
| TC03-02 | P1 M APP | Ouvrir P2 en rupture. | Rupture clairement indiquée ; ajout au panier désactivé via l'interface. |
| TC03-03 | P0 M APP | Ouvrir directement l'URL de P3 masqué. | Réponse 404 générique habillée par le design system ; aucune information de P3 divulguée. |
| TC03-04 | P1 M APP | Ouvrir un slug inexistant. | Page 404 propre, sans détail technique. |
| TC03-05 | P1 M APP | Supprimer ou masquer un produit de test non commandé après avoir gardé son URL, puis recharger. | La fiche ne reste pas accessible publiquement (404). |
| TC03-06 | P2 M APP | Créer un produit sans image locale valide ou avec un chemin externe refusé. | Repli visuel local propre ; aucune icône d'image cassée ni chargement réseau externe. |
| TC03-07 | P1 M SPEC | Avec P1 stock 5, essayer quantités 1, 5 puis 6. | 1 et 5 acceptées (`max=5` sur le champ) ; toute demande supérieure à 5 est refusée en français sans code technique et le panier n'excède jamais 5. |
| TC03-08 | P1 R SPEC | Soumettre une URL ou un champ contenant du HTML dans l'environnement de recette. | Pas d'exécution de script ni de divulgation de fichier. |
| TC03-09 | P1 M APP | Sur `/produit/{slug}`, inspecter l'image principale de la fiche ET les vignettes de la section « Produits similaires » (en FR puis en EN). | Le visuel principal utilise le mode **informatif** (`alt="Visuel indicatif : <Nom>"` en FR, `"Illustrative image: <Nom>"` en EN) tandis que les cartes similaires utilisent le mode **décoratif** (`alt=""`). |

## UC-04 — Créer un compte

**Acteur :** visiteur. **But :** créer un compte client fictif et se connecter. **Déclencheur :** validation de `/inscription`. **Préconditions :** adresse de test neuve, CGV et politique de confidentialité consultables, session visiteur. **Nominal :** renseigner identité, e-mail neuf, mot de passe et confirmation, cocher les CGV, créer. **Postcondition :** un client créé et connecté ; aucun compte administrateur créé. **Références :** EF-VIS-06, RB-01/12/13 ; `tests/php/features_test.php`, `tests/php/inscription_erreurs_test.php`, `scripts/ui-e2e-inscription.mjs`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC04-01 | P0 M APP | Utiliser un e-mail neuf et `Test2026!` deux fois, cocher les CGV et créer. | Compte créé ; connexion immédiate et redirection vers `/compte` ; aucun mot de passe affiché en clair. |
| TC04-02 | P0 M APP | Réessayer avec l'e-mail déjà créé. | Doublon refusé en français (« Cet email est déjà utilisé. ») ; aucun second compte portant le même e-mail. |
| TC04-03 | P1 M APP | Omettre nom, prénom ou saisir un e-mail invalide. | Création refusée ; messages de validation clairs ; aucune création partielle. |
| TC04-04 | P1 M APP | Donner deux mots de passe différents ou ne pas accepter les CGV. | Création refusée et cause exacte indiquée. |
| TC04-05 | P1 R APP | Tester des mots de passe ASCII de 7 puis 8 caractères avec un e-mail différent par essai. | 7 caractères refusés ; comparer le résultat du cas à 8 à la politique affichée et noter le comportement observé. |
| TC04-06 | P1 R SPEC | Soumettre 8 caractères uniquement alphabétiques puis uniquement numériques, avec confirmation. | Selon l'aide affichée, lettre **et** chiffre devraient être exigés ; le contrôleur actuel ne vérifie que la longueur minimale de 8 caractères. Écart possible à relever, pas succès présumé. |
| TC04-07 | P0 R SPEC | Retirer le jeton CSRF, puis sur une autre inscription à e-mail neuf injecter `role=SUPER`. | Sans jeton : refus 403 sans création ; avec champ rôle ajouté à une demande valide : compte strictement client, table administrateur intacte. |
| TC04-08 | P1 M SPEC | Ajouter P1 au panier visiteur, puis s'inscrire. | Le panier constitué avant inscription reste présent après connexion ; noter toute perte éventuelle. |
| TC04-09 | P1 I SPEC | Deux inscriptions simultanées avec le même e-mail, uniquement en recette isolée. | Au plus un compte créé ; aucune donnée partielle ni erreur technique divulguée. |
| TC04-10 | P1 M APP | Soumettre `/inscription` avec plusieurs erreurs simultanées (nom vide + e-mail invalide + mot de passe trop court + CGV décochées), et vérifier les mentions sous le formulaire. | Toutes les erreurs s'affichent ensemble dès le premier retour ; les valeurs non sensibles saisies sont conservées (mots de passe vidés) ; lien vers `/confidentialite` présent sans case de prospection forcée. |

## UC-05 — Connexion, déconnexion et gestion du compte

**Acteur :** visiteur ou client. **But :** s'authentifier, gérer son profil et son adresse structurée, puis se déconnecter. **Déclencheur :** `/connexion` ou `/compte`. **Préconditions :** C1 et C2 actifs et distincts. **Nominal :** se connecter comme C1, modifier une information de test, enregistrer puis se déconnecter. **Postcondition :** seule la fiche C1 change ; session client fermée après déconnexion. **Références :** EF-VIS-07, EF-CLI-01/02, RB-01/12 ; `tests/php/features_test.php`, `tests/php/adresse_livraison_test.php`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC05-01 | P0 M APP | Connecter C1 avec ses bons identifiants. | Accès à `/compte` et `/mes-commandes`. |
| TC05-02 | P0 M APP | Mauvais mot de passe pour C1 puis adresse inexistante. | Accès refusé ; message générique strictement identique pour ces deux cas (« Email ou mot de passe erroné. »). |
| TC05-03 | P1 M APP | Modifier le téléphone de test de C1 sans changer son e-mail, enregistrer puis recharger. | Nouvelle valeur conservée pour C1 seulement ; aucun faux refus d'unicité sur son propre e-mail. |
| TC05-04 | P1 M APP | Remplacer l'e-mail de C1 par celui de C2. | Refus du doublon ; les deux comptes restent inchangés. |
| TC05-05 | P1 M APP | Changer le mot de passe d'un compte **jetable** avec un ancien mot de passe erroné. | Refus ; ancien mot de passe toujours valide. |
| TC05-06 | P1 M APP | Sur un compte jetable, changer avec ancien mot de passe correct et confirmation ; se reconnecter. | Nouveau mot de passe accepté ; ancien refusé ; aucun mot de passe affiché en clair. |
| TC05-07 | P0 M APP | Déconnecter C1 puis ouvrir `/compte` et `/mes-commandes`. | Redirection vers `/connexion` ; aucune donnée privée affichée. |
| TC05-08 | P0 R SPEC | Contrôler la régénération d'identifiant de session après authentification, sans publier les cookies en preuve. | L'ancien identifiant pré-authentification ne donne pas accès à la session authentifiée. |
| TC05-09 | P1 I SPEC | Tester plusieurs échecs et l'expiration sur une instance jetable, sans attaquer un service partagé. | Comparer les protections réellement observées au cahier des charges ; temporisation/limitation de débit sur `/connexion` ne sont pas considérées acquises sans mesure. |
| TC05-10 | P1 R SPEC | Envoyer un changement de profil sans jeton CSRF. | Refus 403 ; profil inchangé. |
| TC05-11 | P1 M APP | Sur `/compte` (à 1440 px puis à 375 px), renseigner l'adresse structurée fictive (rue, code postal, ville), enregistrer puis ouvrir `/commande/valider` avec un article en panier. | Champs et cartes du profil lisibles sans débordement à 375 px ; l'adresse enregistrée pré-remplit correctement le formulaire de livraison à l'étape de validation. |

## UC-06 — Gérer le panier

**Acteur :** visiteur ou client. **But :** ajouter (avec ou sans JavaScript), modifier, retirer et vérifier les montants calculés par le serveur. **Déclencheur :** ajout depuis catalogue/fiche ou ouverture de `/panier`. **Préconditions :** P1 visible stock 5, P2 stock 0, P3 masqué ; panier vide dans une session isolée. **Nominal :** ajouter P1, observer le retour instantané, modifier sa quantité, contrôler le total et retirer. **Postcondition :** état de panier cohérent ; aucun stock débité avant commande. **Références :** EF-CLI-03/04/05/06, RB-15/18/19 ; `tests/php/unit_tests.php`, `tests/php/panier_tableau_test.php`, `tests/php/panier_cta_test.php`, `scripts/ui-e2e-panier.mjs`, `scripts/ui-e2e-panier-tableau.mjs`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC06-01 | P0 M APP | Avec JavaScript activé, ajouter 1 P1 depuis le catalogue ou la fiche puis ouvrir `/panier`. | Mise à jour sans rechargement via `/api/panier/ajouter` (`#badge-panier`, `aria-label` du lien panier et annonce `#zone-annonces`) ; sur `/panier`, quantité 1, prix TTC 12,00 € et total calculés exclusivement côté serveur. |
| TC06-02 | P1 M APP | Ajouter P1 une deuxième fois puis changer la quantité à 5. | Une seule ligne agrégée ; quantité 5 ; sous-total marchandises TTC 60,00 €. |
| TC06-03 | P0 M/R APP | Demander plus de 5 P1 par formulaire ou requête `/api/panier/ajouter`. | Refus clair sans code technique ; quantité effective plafonnée au stock disponible ; stock en base inchangé tant qu'aucune commande n'est validée. |
| TC06-04 | P1 M APP | Régler la quantité à 0 puis retirer une ligne (et rejouer un second retrait sur la même ligne). | Ligne retirée proprement sans erreur au second retrait (idempotence) ; état « panier vide » affiché si elle était seule. |
| TC06-05 | P0 M/R SPEC | Tenter d'ajouter P2 en rupture et P3 masqué. | Aucune quantité achetable pour P2 ; P3 masqué est rejeté et ne peut pas entrer dans un panier valide. |
| TC06-06 | P1 M APP | Après ajout de P1, masquer P1 depuis A1 puis rouvrir le panier C1. | P1 masqué n'est plus commandable ; rétablir seulement dans la recette isolée. |
| TC06-07 | P1 M SPEC | Avec des prix de test adaptés, vérifier sous-total et port à 0,01 € sous, exactement à, puis à 0,01 € au-dessus de la franchise configurée (80,00 € par défaut). | Port facturé sous le seuil (4,90 € par défaut) et offert dès la franchise atteinte (0,00 €) ; total TTC = marchandises TTC + port. |
| TC06-08 | P1 M APP | Désactiver JavaScript dans les outils du navigateur puis ajouter, modifier la quantité et retirer P1. | Repli complet en formulaires POST classiques avec rechargement de page, sans aucune perte fonctionnelle. |
| TC06-09 | P0 R SPEC | Modifier un champ `prix_unitaire` côté navigateur ou omettre le jeton CSRF sur `/panier/*` et `/api/panier/*`. | Prix envoyé par le client ignoré ; sans CSRF valide, refus 403 et aucune mutation de panier. |
| TC06-10 | P1 M SPEC | Dans deux sessions isolées, modifier le panier C1 puis consulter C2. | Aucun article ni quantité n'est partagé entre les sessions. |
| TC06-11 | P1 M APP | Ouvrir `/panier` avec 2 articles à 320 px, 375 px et 1440 px. | À 320/375 px, lignes repliées proprement avec étiquettes `data-label` sans débordement horizontal ; totaux (HT, TVA, livraison, TTC) affichés une seule fois dans `tfoot` sans encadré redondant ; bouton principal « Valider ma commande » immédiatement visible. |

## UC-07 — Passer une commande

**Acteur :** client. **But :** transformer un panier en commande simulée après récapitulatif précontractuel. **Déclencheur :** ouverture puis confirmation sur `/commande/valider`. **Préconditions :** C1 connecté, P1 visible en stock, adresse fictive au profil, panier non vide. **Nominal :** contrôler le récapitulatif et les frais de port affichés avant validation, vérifier l'adresse pré-remplie, confirmer la commande simulée, noter le numéro et vérifier le détail. **Postcondition :** commande et lignes enregistrées, stock débité une seule fois, trace d'historique créée, panier vidé ; aucun paiement réel. **Références :** EF-CLI-07, RB-04/06/15/18/20, ENF-14/16 ; `tests/php/run_tests.php`, `tests/php/valider_recap_test.php`, `scripts/ui-e2e-valider.mjs`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC07-01 | P0 M APP | Commander 2 P1 (stock initial 5) avec adresse valide et paiement simulé coché. | Commande numérotée `CMDAAAA-NNNNNN`, statut `PAYEE` (simulé), ligne figée à 12,00 € TTC, port exact figé, stock final 3 et panier vidé. |
| TC07-02 | P1 M APP | Commander P1 en décochant l'option de paiement immédiat simulé (si proposée) ou selon le flux nominal `EN_PREPARATION`. | Commande enregistrée au statut attendu (`EN_PREPARATION` ou `PAYEE` selon l'option choisie), sans aucun débit réel ; totaux, historique initial et stock cohérents. |
| TC07-03 | P0 M APP | Tenter d'ouvrir ou de poster `/commande/valider` avec un panier vide. | Redirection propre avec message explicite ; aucune commande vide créée. |
| TC07-04 | P1 M APP | Depuis une session visiteur ayant un panier, cliquer « Valider ma commande ». | Redirection vers `/connexion` puis retour au parcours client après authentification ; aucune commande créée sans session client. |
| TC07-05 | P1 M APP | Vider l'adresse de livraison sur `/commande/valider` et soumettre. | Refus clair en français ; panier et stocks strictement inchangés, aucune commande créée. |
| TC07-06 | P0 I SPEC | Mettre P4 (stock 1) dans le panier de C1 puis consommer cette unité par C2 juste avant la validation de C1. | Pas de survente : C1 reçoit le message humain « Stock insuffisant pour cette quantité. », aucune commande partielle ni stock négatif, panier conservé pour ajustement. |
| TC07-07 | P0 I SPEC | Avec deux lignes en panier (`P1 × 1` et `P4 × 2` pour un stock 1), soumettre la validation. | Rollback intégral : aucune commande créée et le stock de P1 reste strictement inchangé. |
| TC07-08 | P0 R SPEC | Double clic rapide / rejouer exactement le même POST dans une instance isolée. | Le vidage transactionnel du panier ou le contrôle de jeton doit empêcher une double commande ; relever tout doublon éventuel comme anomalie. |
| TC07-09 | P0 I SPEC | Deux clients valident simultanément le dernier exemplaire de P4 (stock 1) sur instance isolée. | Exactement 1 commande réussie et 1 refus `STOCK_INSUFFISANT`, stock final = 0 ; vérifier séparément le verrou fichier JSON et `SELECT … FOR UPDATE` sous MariaDB/MySQL. |
| TC07-10 | P1 M APP | Après validation de O2, augmenter le prix HT de P1 dans `/admin/produits` puis rouvrir O2 côté client. | La ligne de O2 affiche toujours les 12,00 € TTC figés lors de l'achat tandis que le catalogue affiche le nouveau tarif. |
| TC07-11 | P0 R SPEC | Soumettre `/commande/valider` sans jeton CSRF ou en injectant `frais_port=0` et `prix_unitaire=0.01`. | Sans CSRF : refus 403 ; champs injectés ignorés, seuls les montants calculés par le serveur et la base font foi. |
| TC07-12 | P1 I SPEC | Couper le stockage pendant une validation uniquement sur un environnement jetable. | Page d'erreur générique sans secret et état atomique (aucune ligne ou décrémentation orpheline). Ne jamais couper la base de l'équipe. |
| TC07-13 | P1 M APP | Ouvrir `/commande/valider` à 320 px et 375 px avec 2 articles dont un libellé long, et vérifier les mentions précontractuelles. | Tableau récapitulatif lisible en cartes `data-label` sans débordement horizontal, totaux groupés, bandeau de simulation pédagogique visible, bouton affichant le montant total exact et liens `/cgv`, `/livraison`, `/retractation`, `/garanties` accessibles. |

## UC-08 — Consulter l'historique des commandes

**Acteur :** client. **But :** consulter ses commandes, leur détail, leur piste d'audit et l'historique préservé des commandes annulées. **Déclencheur :** ouverture de `/mes-commandes` ou `/mes-commandes/{id}`. **Préconditions :** C1 et C2 distincts ; O1 et une commande annulée chez C1 ; au moins une commande de C2 pour le test d'isolation. **Nominal :** C1 ouvre sa liste puis le détail de O1. **Postcondition :** aucune modification métier. **Références :** EF-CLI-08/09, RB-10/11, SEC-08 ; `tests/php/features_test.php`, `tests/php/mes_commandes_tableau_test.php`, `tests/php/annulation_historique_test.php`, `scripts/ui-e2e-mes-commandes.mjs`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC08-01 | P0 M APP | C1 ouvre `/mes-commandes` puis le détail de O1. | Numéro, date (formatée selon le fuseau `/preferences`), badge de statut, montant aligné en chiffres tabulaires, lignes commandées, frais de port et chronologie des statuts affichés. |
| TC08-02 | P1 M APP | Avec un client de test neuf sans commande, ouvrir `/mes-commandes`. | État vide explicite et lien direct vers `/catalogue`. |
| TC08-03 | P0 M APP | C2 ouvre directement `/mes-commandes/{id_de_O1}` appartenant à C1. | Réponse 404 générique (jamais 403 révélant l'existence) ; aucun nom, adresse, ligne ou montant de C1 divulgué. |
| TC08-04 | P0 M APP | Sans connexion, ouvrir `/mes-commandes` puis `/mes-commandes/1`. | Redirection vers `/connexion` ; aucun contenu privé divulgué. |
| TC08-05 | P1 M SPEC | Demander `/mes-commandes/999999` puis un identifiant non numérique. | Réponse 404 propre sans trace SQL ni message technique. |
| TC08-06 | P1 M APP | Comparer le prix unitaire figé dans O1 avec le prix catalogue courant modifié en recette. | Le détail affiche invariablement le prix figé lors de la commande. |
| TC08-07 | P1 M SPEC | Faire évoluer le statut de O2 par A1 puis recharger son détail côté C1. | Nouveau statut et nouvelle entrée d'historique visibles immédiatement ; aucune autre commande altérée. |
| TC08-08 | P1 M APP | Ouvrir sur `/mes-commandes` puis en détail une commande `ANNULEE`. | La commande annulée reste visible dans la liste ; son détail **conserve toutes les lignes d'origine en historique**, affiche le « Total de la commande avant annulation » ainsi que le montant soldé `0,00 €`, et ne propose plus aucun bouton d'annulation. |
| TC08-09 | P1 M APP | Consulter `/mes-commandes` et `/mes-commandes/{id}` à 320 px et 375 px, puis au clavier. | Passage propre en cartes mobiles étiquetées `data-label` sans défilement horizontal global ; montants, statuts et liens « Voir le détail » entièrement lisibles et atteignables au clavier. |

## UC-09 — Annuler une commande

**Acteur :** client propriétaire. **But :** annuler techniquement en deux étapes une commande simulée non expédiée, avec restitution unique du stock et conservation de l'historique des lignes. **Déclencheur :** clic sur « Annuler la commande » depuis `/mes-commandes/{id}`. **Préconditions :** O1 appartient à C1, statut `EN_PREPARATION` ou `PAYEE`, lignes et stocks initiaux relevés. **Nominal :** ouvrir O1, passer par la page GET de confirmation, confirmer en POST, vérifier statut, lignes conservées, montants et stock. **Postcondition :** O1 `ANNULEE`, lignes préservées, `Total avant annulation` conservé, `montant_total` et `frais_port` à `0,00 €`, unités restituées exactement une fois. **Références :** EF-CLI-10, RB-09/11/18/20 ; `tests/php/actions_destructrices_test.php`, `tests/php/annulation_historique_test.php`, `scripts/ui-e2e-annulation.mjs`, `scripts/ui-e2e-annulation-historique.mjs`, `scripts/wasm-e2e.mjs`. **Ne pas confondre avec la rétractation légale**, non opérationnelle sur cette maquette.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC09-01 | P0 M APP | Sur O1 avant expédition : 1) cliquer « Annuler la commande » (`GET /mes-commandes/{id}/annulation/confirmation`), recharger cette page GET puis tester « Non, garder ma commande » ; 2) rouvrir la confirmation et valider le formulaire POST (même JavaScript coupé). | Étape 1 : aucun effet de bord (statut et stock intacts). Étape 2 : statut `ANNULEE`, stock recrédité **exactement une fois**, **lignes conservées en historique**, `Total de la commande avant annulation` affiché, total et port soldés à `0,00 €`, trace `CLIENT` ajoutée. |
| TC09-02 | P0 R APP | Rejouer une deuxième fois le `POST /mes-commandes/{id}/annulation` sur la même O1 déjà annulée. | Refus propre en français sans code technique ; aucune seconde restitution de stock ; historique non dupliqué. |
| TC09-03 | P0 M/R APP | C2 tente `GET /mes-commandes/{id_de_O1}/annulation/confirmation` puis `POST /mes-commandes/{id_de_O1}/annulation`. | Refus 404 sans fuite d'information ; commande O1 et stock de C1 strictement intacts. |
| TC09-04 | P0 M/R APP | Tenter d'annuler comme client une commande `EXPEDIEE` (O3) ou `LIVREE` (interface puis POST forgé). | Aucun bouton d'annulation affiché ; en POST forgé, refus humain (« Cette commande est déjà expédiée ou livrée… »), statut et stock intacts. |
| TC09-05 | P1 M APP | Lire les mentions affichées sur le détail de O1 et sur l'écran de confirmation d'annulation. | Distinction explicite entre l'annulation technique avant expédition simulée et le droit légal de rétractation après livraison (renvoi vers `/retractation`, aucune promesse de remboursement réel). |
| TC09-06 | P0 R SPEC | Soumettre `POST /mes-commandes/{id}/annulation` sans jeton CSRF ou sans session client. | Refus (403 sans CSRF, redirection `/connexion` hors session) ; aucune mutation. |
| TC09-07 | P0 I SPEC | Simuler une erreur lors de la restitution uniquement sur une copie jetable. | Transaction annulée intégralement : ni restitution partielle ni statut `ANNULEE` incohérent. |
| TC09-08 | P1 M APP | Annuler une commande multi-lignes comportant plusieurs produits distincts. | Chaque produit voit son stock recrédité de sa quantité exacte une seule fois ; toutes les lignes restent lisibles sur le détail annulé. |
| TC09-09 | P1 R/I APP | Lancer concurremment l'annulation client (`C1`) et l'annulation administrateur (`A1`) sur une même commande `EN_PREPARATION` dans une instance isolée. | Une seule annulation aboutit, l'autre reçoit un refus propre ; le stock n'est recrédité **qu'une seule fois** (verrou exclusif JSON / transaction `FOR UPDATE` SQL). |

## UC-10 — Gérer les produits

**Acteur :** administrateur autorisé. **But :** créer, modifier, masquer et supprimer un produit avec confirmation explicite en deux étapes. **Déclencheur :** action dans `/admin/produits`. **Préconditions :** A1 connecté, K1 existante, référence et slug uniques ; produits fictifs. **Nominal :** créer, retrouver, modifier et vérifier le produit côté catalogue. **Postcondition :** fiche et visibilité cohérentes ; suppression en 2 étapes (physique si jamais commandé, masquage `visible = 0` si déjà référencé dans une commande). **Références :** EF-ADM-01/02, RB-02/07/09/14/16/19 ; `tests/php/features_test.php`, `tests/php/actions_destructrices_test.php`, `tests/php/formulaires_admin_test.php`, `scripts/ui-e2e-formulaires.mjs`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC10-01 | P0 M APP | A1 crée P1 visible : HT 10,00 €, TVA 20 %, stock 5, K1. | Produit créé ; TTC 12,00 € calculé ; visible immédiatement côté public. |
| TC10-02 | P1 M APP | Modifier nom et prix HT de P1 puis recharger. | Modification persistée ; TTC recalculé ; commandes antérieures gardent leur prix unitaire figé. |
| TC10-03 | P1 M APP | Masquer P1 (`visible = 0`), vérifier côté visiteur, puis le rendre à nouveau visible. | Absent du catalogue, de la recherche et des compteurs publics (fiche en 404) tant que masqué ; réapparaît après réactivation. |
| TC10-04 | P1 M APP | Créer un produit avec une référence ou un slug déjà utilisé. | Doublon refusé par message clair en français ; aucune seconde fiche ni écrasement. |
| TC10-05 | P0 M/R APP | Essayer prix HT 0 ou négatif et stock négatif. | Refus sans code technique brut ; aucun produit invalide créé. |
| TC10-06 | P1 M APP | Sur un produit de test **jamais commandé** : ouvrir `GET /admin/produits/{id}/suppression/confirmation` (recharger en GET pour vérifier l'absence d'effet), puis confirmer en POST (y compris JavaScript désactivé). | La page GET n'altère rien ; le POST supprime définitivement le produit jamais commandé. |
| TC10-07 | P0 M APP | Sur un produit de test **déjà commandé** : ouvrir la page de confirmation puis valider la suppression en POST. | La page annonce le masquage ; le produit passe à `visible = 0` sans être supprimé de la base, et les commandes historiques qui le référencent restent intégralement consultables. |
| TC10-08 | P1 M APP | Créer un produit visible et vérifier la section « Nouveautés » de l'accueil `/`. | Il apparaît parmi les 4 derniers ajouts avec son horodatage UTC converti dans le fuseau actif. |
| TC10-09 | P0 R SPEC | Un client ou visiteur tente une route `/admin/produit*` ou un POST sans jeton CSRF. | Redirection `/admin/connexion` hors session admin, ou refus 403 sans CSRF ; aucune modification produit. |
| TC10-10 | P1 M/R SPEC | Saisir un nom contenant `<script>` et une URL d'image externe (`https://…`) ou en traversée (`../`). | Nom affiché littéralement échappé ; chemin d'image externe/traversant rejeté ou ramené au repli local, zéro requête sortante. |
| TC10-11 | P1 I SPEC | Deux créations concurrentes de même référence, seulement en recette isolée. | Au plus un produit créé ; intégrité préservée. |
| TC10-12 | P1 M APP | Ouvrir `/admin/produits`, `/admin/produit/nouveau` et `/admin/produit/{id}/modification` à 320 px et 375 px. | Tableau produits replié en cartes mobiles étiquetées `data-label` ; grilles de formulaire empilées proprement en une colonne sans champ tronqué ni débordement horizontal. |

## UC-11 — Gérer les catégories

**Acteur :** administrateur autorisé. **But :** créer, modifier et supprimer une catégorie sans écrasement silencieux de doublon et avec confirmation en deux étapes. **Déclencheur :** `/admin/categories`. **Préconditions :** A1 connecté, K0 vide, K1 contenant P1, noms de test uniques. **Nominal :** créer une catégorie de test, la modifier puis la supprimer après confirmation lorsqu'elle est vide. **Postcondition :** catégorie créée ou modifiée proprement ; doublon de nom/slug refusé ; suppression d'une catégorie occupée refusée avec aide à la réaffectation. **Références :** EF-ADM-03, RB-07/14 ; `tests/php/categorie_doublon_test.php`, `tests/php/categories_tableau_test.php`, `tests/php/actions_destructrices_test.php`, `scripts/ui-e2e-categories.mjs`, `scripts/ui-e2e-categories-tableau.mjs`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC11-01 | P1 M APP | Créer K0 avec nom unique et slug laissé vide. | Catégorie créée ; slug normalisé généré automatiquement. |
| TC11-02 | P1 M APP | Modifier le nom et la description de K0. | Valeurs mises à jour et retrouvées après rechargement ; aucun faux refus d'unicité sur K0 elle-même. |
| TC11-03 | P1 M APP | Cliquer « Supprimer » sur K0 vide (`GET /admin/categories/{id}/suppression/confirmation`), vérifier que le GET seul ne supprime rien, puis confirmer en POST. | K0 est supprimée uniquement après le POST de confirmation ; autres catégories intactes. |
| TC11-04 | P0 M APP | Ouvrir la confirmation de suppression de K1 contenant P1 puis tenter de supprimer. | Avertissement du nombre de produits rattachés et refus clair en français (avec lien vers `/admin/produits` pour réaffecter) ; K1 et P1 restent intacts. |
| TC11-05 | P0 M APP | Tenter de créer une nouvelle catégorie portant le même nom que K0 (y compris variante de casse ou d'accent) avec une autre description, ou de renommer une autre catégorie sur K0. | Refus explicite (« Ce nom de catégorie existe déjà. » / « Ce slug de catégorie existe déjà. ») ; **aucune fusion silencieuse** et la description d'origine de K0 reste strictement inchangée. |
| TC11-06 | P1 M/R SPEC | Envoyer un nom vide ou un slug explicite déjà pris par une autre catégorie. | Refus de validation clair ; aucune altération d'une catégorie existante. |
| TC11-07 | P0 R SPEC | Tenter une création, modification ou suppression sans session admin ou sans jeton CSRF. | Refus ; catégories inchangées. |
| TC11-08 | P1 M SPEC | Saisir un nom ou une description contenant du HTML dans une catégorie de recette. | Texte échappé partout (admin, catalogue, filtres) ; aucun script exécuté. |
| TC11-09 | P1 M APP | Ouvrir `/admin/categories` à 320 px et 375 px et tester l'édition d'une ligne. | Cartes mobiles `data-label` et champs d'édition empilés lisibles sans chevauchement ni débordement horizontal de la page. |

## UC-12 — Gérer les stocks

**Acteur :** administrateur autorisé. **But :** ajuster un stock avec motif obligatoire (mode additif par défaut non destructif) et consulter les alertes. **Déclencheur :** `/admin/stocks`. **Préconditions :** A1 connecté, P1 stock 5 et seuil d'alerte connu, P2 stock 0 ; données isolées. **Nominal :** vérifier le mode sélectionné par défaut, ajuster P1 avec motif et contrôler la liste et les filtres d'alerte. **Postcondition :** stock modifié uniquement pour le produit visé ; aucun stock négatif ni code technique brut. **Références :** EF-ADM-04/05, RB-03/17/18, ENF-13 ; `tests/php/stocks_defaut_test.php`, `tests/php/run_tests.php`, `scripts/ui-e2e-stocks.mjs`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC12-01 | P0 M APP | Ouvrir `/admin/stocks`, vérifier l'option sélectionnée par défaut sur la ligne P1 (stock 5), saisir `+3` et un motif de réception sans changer le sélecteur, puis valider. | Le mode sélectionné par défaut est **« Ajouter / retirer » (`DELTA`)** et non « Fixer à » (évite d'écraser accidentellement le stock) ; P1 passe de 5 à **8** ; autres stocks inchangés. |
| TC12-02 | P1 M APP | Sur P1 (stock 8) : tester `DELTA` avec `-3` et motif, puis choisir explicitement « Fixer à » (`SET`) avec `10` et motif d'inventaire. | P1 passe à 5 puis à 10 exactement ; confirmation lisible. |
| TC12-03 | P0 M/R APP | Sur P1 (stock 5) : soumettre `DELTA` `-6` puis `SET` `-1`. | Refus traduit en français (« Le stock ne peut pas devenir négatif. », sans code `RB03_…`) ; P1 reste à 5. |
| TC12-04 | P1 M APP | Soumettre un ajustement avec le champ motif vide ou composé d'espaces. | Refus navigateur ou serveur ; stock inchangé. |
| TC12-05 | P1 M APP | Filtrer `/admin/stocks?etat=alerte`, `rupture`, `tres_bas` puis `ok`. | P2 remonte en `RUPTURE` ; les articles ≤ seuil remontent en `TRES_BAS` ; cohérence exacte entre vue SQL et calcul applicatif. |
| TC12-06 | P1 M SPEC | Vérifier la trace du mouvement et de son motif dans le journal d'audit disponible. | Mouvement et motif retrouvés dans la trace d'audit ; noter un écart si le motif n'apparaît pas sur le pilote testé. |
| TC12-07 | P0 R SPEC | Tenter `POST /admin/stocks` sans session administrateur ou sans jeton CSRF. | Refus ; aucun changement de stock. |
| TC12-08 | P0 I SPEC | Ajustement administrateur et validation de commande simultanés sur P4 dans une base jetable. | Aucun stock négatif ni mouvement perdu ; tester séparément JSON et MariaDB/MySQL. |
| TC12-09 | P2 M SPEC | Avec seuil d'alerte = 3, tester successivement stocks 0, 2, 3 et 4 (et vérifier le refus de −1). | −1 refusé ; 0 classé `RUPTURE` ; 2 et 3 classés `TRES_BAS` ; 4 classé disponible (`OK`). |
| TC12-10 | P1 M APP | Ouvrir `/admin/stocks` à 320 px et 375 px et effectuer un ajustement sur un produit de test. | Cartes mobiles étiquetées `data-label` ; sélecteur de mode, champ quantité, champ motif et bouton « Appliquer » utilisables sans débordement horizontal. |

## UC-13 — Consulter et filtrer les commandes côté administration

**Acteur :** administrateur `SUPER` ou `GESTIONNAIRE` selon droits. **But :** consulter commandes, détails, historique, indicateurs et paramètres en tenant compte du fuseau horaire choisi et du rendu mobile en cartes. **Déclencheur :** ouverture de `/admin/commandes`, `/admin`, `/admin/equipe` ou `/admin/parametres`. **Préconditions :** A1 (`SUPER`), O1 et O2 avec statuts/dates connus ; G1 (`GESTIONNAIRE`) si disponible. **Nominal :** lister, filtrer par critères et dates, ouvrir un détail puis consulter le tableau de bord. **Postcondition :** aucune modification métier lors des consultations. **Références :** EF-ADM-06/08/09, RB-10/11/13/15 ; `tests/php/commandes_tableau_test.php`, `tests/php/heure_test.php`, `scripts/ui-e2e-commandes-tableau.mjs`, `scripts/ui-e2e-heure.mjs`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC13-01 | P0 M APP | A1 ouvre `/admin/commandes`. | Liste des commandes avec numéro, date locale (issue de l'UTC), client, badge de statut et montant aligné à droite. |
| TC13-02 | P1 M APP | Filtrer par statut, recherche client/numéro et montant minimum connu. | Seules les commandes correspondantes apparaissent ; le total cumulé affiché correspond aux résultats filtrés. |
| TC13-03 | P1 M APP | Filtrer par période `du` / `au` incluant O2 (avec JavaScript activé puis désactivé). | Les champs cachés `debut_utc` / `fin_utc` transmettent les bornes exactes du jour local choisi dans `/preferences` converties en UTC (et se recalculent si une URL signet porte d'anciennes bornes) ; sans JS, repli propre sur `Europe/Paris`. |
| TC13-04 | P1 M APP | Saisir un critère sans aucune correspondance. | État vide clair sans erreur ni fuite technique. |
| TC13-05 | P1 M APP | Ouvrir `/admin/commandes/{id}` sur O2 puis sur une commande annulée. | Lignes, quantités, prix figés, port, statut, piste d'audit complète (auteur, rôle, date locale, commentaire) et, si annulée, `Total avant annulation` + lignes préservées. |
| TC13-06 | P1 M APP | Ouvrir `/admin` avec un jeu de recette connu. | Chiffre d'affaires par statut, panier moyen, top 5 produits et indicateurs « du jour » (bornés selon `jour_local` du fuseau actif) cohérents avec les commandes non annulées. |
| TC13-07 | P0 M/R APP | Visiteur non connecté puis client C1 tentent `/admin`, `/admin/commandes` et `/admin/commandes/1`. | Redirection vers `/admin/connexion` sans fuite de données administratives. |
| TC13-08 | P1 M APP | Demander `/admin/commandes/999999` en tant que A1. | Réponse 404 générique propre, sans erreur technique. |
| TC13-09 | P0 M/R APP | Connecter G1 (`GESTIONNAIRE`) et tester `GET /admin/equipe` puis `POST /admin/equipe` (avec CSRF valide) ; contrôler aussi `/admin/parametres` avec A1. | `G1` reçoit un **HTTP 403 réel** (page « Accès refusé » avec lien vers `/admin`) sur `/admin/equipe` ; dans `/admin/parametres`, A1 voit `frais_port`, `franchise_port`, `tva_defaut` et `conservation_compte` (`24` mois par défaut, borné de 1 à 120). |
| TC13-10 | P2 M APP | Sur `/admin/commandes`, saisir une période inversée (`du > au`) puis une date invalide. | Message de demande de correction lisible sans erreur 500 ni trace SQL. |
| TC13-11 | P1 M APP | Ouvrir `/admin`, `/admin/commandes`, `/admin/commandes/{id}` et `/admin/equipe` à 320 px et 375 px (avec e-mails et numéros longs). | Tous les tableaux `.tableau-defilant--admin` passent en **cartes mobiles à champs étiquetés (`data-label`)** ; valeurs longues repliées sur plusieurs lignes sans dépasser de la carte, actions accessibles et zéro défilement horizontal global. |
| TC13-12 | P1 M/R APP | Sur base isolée avec une commande créée à `22:30:00Z` en été (soit `00:30` le lendemain à `Europe/Paris` et `18:30` le jour même à `America/New_York`), basculer le fuseau dans `/preferences` et filtrer ce jour sur `/admin/commandes` et `/admin`. | La commande est rattachée au lendemain sous `Europe/Paris` et au jour même sous `America/New_York`, tandis que son horodatage stocké en base reste invariant en UTC. |

## UC-14 — Modifier le statut d'une commande

**Acteur :** administrateur autorisé. **But :** faire évoluer le statut d'une commande selon la matrice `RB-11`, avec parcours dédié de confirmation d'annulation (distinguant commandes avant expédition et commandes déjà expédiées) et piste d'audit complète. **Déclencheur :** formulaire du détail `/admin/commandes/{id}`. **Préconditions :** A1 connecté ; O2 (pour transitions nominales), une commande non expédiée distincte (pour `TC14-06`) et O3 `EXPEDIEE` (pour `TC14-11`) avec stocks initiaux relevés. **Nominal :** choisir un statut autorisé et valider. **Postcondition :** transition valide, historique enrichi d'une entrée unique et effets de stock exacts. **Références :** EF-ADM-07, RB-09/11/13/15/18 ; `tests/php/actions_destructrices_test.php`, `tests/phpunit/JsonStoreTest.php`, `tests/php/sql_integration.php`, `scripts/wasm-e2e.mjs`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TC14-01 | P0 M APP | Sur O2 `PAYEE`, sélectionner `EXPEDIEE`, valider puis relire l'historique. | Statut `EXPEDIEE` ; nouvelle trace avec auteur `ADMIN`, identifiant et horodatage ; montants et stocks inchangés. |
| TC14-02 | P1 M APP | Sur une commande `EXPEDIEE`, sélectionner `LIVREE` et valider. | Statut `LIVREE` ; état terminal atteint, le formulaire ne propose plus aucune transition ultérieure. |
| TC14-03 | P0 R APP | Poster hors interface une transition interdite (ex. `EXPEDIEE` → `BROUILLON` ou `LIVREE` → `ANNULEE`). | Refus humain en français (sans code `RB11_…` brut) ; statut et historique strictement inchangés. |
| TC14-04 | P1 R APP | Rejouer une demande vers le statut déjà actif avec jeton CSRF valide. | Opération idempotente : statut inchangé et aucune ligne d'historique dupliquée. |
| TC14-05 | P1 M APP | Demander `ANNULEE` depuis `/admin/commandes/{id}` avec un commentaire vide. | Refus explicite exigeant un motif d'annulation ; statut et stock inchangés. |
| TC14-06 | P0 M APP | Sur une commande **non expédiée** (`BROUILLON`, `EN_PREPARATION` ou `PAYEE`), choisir `ANNULEE` avec un motif et soumettre une 1re fois (même JavaScript coupé), tester « Non, revenir à la commande », puis recommencer et valider l'écran de confirmation (`confirmer_annulation=1`). | Le 1er envoi affiche la page « Confirmer l'annulation » **sans modifier la base** ; « Non » laisse la commande intacte ; la confirmation passe le statut à `ANNULEE`, **restitue le stock exactement une fois**, conserve les lignes, mémorise `Total avant annulation`, solde total et port à `0,00 €` et trace l'administrateur + son motif. |
| TC14-07 | P0 R SPEC | Visiteur, client C1 ou requête sans jeton CSRF tente `POST /admin/commandes/{id}/statut`. | Refus (redirection `/admin/connexion` ou 403 CSRF) ; aucun changement ni ligne d'historique. |
| TC14-08 | P1 R SPEC | Cibler un identifiant de commande inexistant ou une valeur de statut inconnue. | Refus propre sans erreur SQL ni création parasite. |
| TC14-09 | P1 I SPEC | Tester chaque ligne de la matrice ci-dessous avec commandes indépendantes sur base isolée, une transition par commande. | Transitions `A` acceptées, `X` refusées, `I` sans doublon d'historique ; résultat réel consigné par couple `TC14-09/DE→VERS`. |
| TC14-10 | P0 I SPEC | Simuler un échec de stockage pendant une transition uniquement sur une copie jetable. | Rollback complet : ni statut partiellement modifié ni trace orpheline ; aucun secret divulgué. |
| TC14-11 | P0 M APP | Sur O3 déjà au statut `EXPEDIEE` : vérifier côté client l'absence d'annulation, puis côté A1 choisir `ANNULEE` avec motif, lire l'avertissement de l'écran de confirmation (même sans JS) et confirmer. | L'écran avertit explicitement que **la commande étant expédiée, le stock ne sera pas automatiquement restitué** ; après confirmation, O3 passe à `ANNULEE`, total et port sont soldés (`Total avant annulation` et lignes conservés), la trace `ADMIN` est écrite et **le stock produit reste inchangé** (retour physique éventuel à saisir dans `/admin/stocks`). |
| TC14-12 | P1 I APP | Sur une base SQL jetable, appeler directement `CALL sp_update_order_status(id_cmd, 'ANNULEE', id_admin, 'motif', @code)` sans passer par `sp_cancel_order`. | Refus par la garde `ANNULATION_VIA_PARCOURS_DEDIE` ; statut, montants et stock demeurent intacts. |

### Matrice des 36 couples de statuts — oracle applicatif à vérifier

`A` : transition autorisée ; `X` : interdite ; `I` : même statut, opération idempotente attendue par le repository (le formulaire ne propose pas forcément ce cas). Les lignes représentent l'état **avant**, les colonnes l'état **demandé**. `EXPEDIEE → ANNULEE` est une possibilité réservée à l'**administrateur** via le parcours dédié de confirmation (sans restitution automatique du stock) ; un client ne peut jamais annuler une commande expédiée. Les annulations avant expédition (`BROUILLON`, `EN_PREPARATION`, `PAYEE` → `ANNULEE`) doivent restituer le stock une seule fois tout en conservant les lignes en historique.

| Origine → cible | BROUILLON | EN_PREPARATION | PAYEE | EXPEDIEE | LIVREE | ANNULEE |
|---|---:|---:|---:|---:|---:|---:|
| BROUILLON | I | A | A | X | X | A |
| EN_PREPARATION | X | I | A | A | X | A |
| PAYEE | X | X | I | A | X | A |
| EXPEDIEE | X | X | X | I | A | A |
| LIVREE | X | X | X | X | I | X |
| ANNULEE | X | X | X | X | X | I |

**Exécution :** attribuer un résultat distinct à chacun des 36 couples sous l'identifiant `TC14-09 / ORIGINE → CIBLE`, sur une commande appropriée et une base jetable ; tracer si le cas est inaccessible en interface et nécessite une requête ou un test de repository. Toute annulation nécessite le commentaire exigé par le contrôleur et le passage par le parcours d'annulation dédié. Comparer la matrice JSON (`app/Model/Data/Triggers.php`) et SQL (`sql/02_minishop_procedures.sql`, `sql/03_minishop_triggers.sql`) sur leurs environnements respectifs, sans conclure pour SQL à partir du seul test JSON.

## 15. Scénarios transversaux — sécurité, responsive, préférences FR/EN, fuseau horaire, mentions légales et conservation

Ces scénarios complètent les 14 UC sans créer artificiellement de nouveaux numéros d'UC. Les tests marqués `I` ou `R` exigent une instance isolée et une autorisation explicite. Références associées : `docs/12-information-juridique.md`, `docs/18-politique-conservation.md`, `docs/DA.md`, `tests/node/preferences_test.mjs`, `tests/node/responsive_navigateur.mjs`, `tests/php/mentions_legales_test.php`, `tests/php/retention_comptes_test.php`, `tests/python/test_retention_cli.py`.

| ID | Priorité / méthode / base | Actions et données | Résultat attendu |
|---|---|---|---|
| TCX-01 | P0 M APP | Sans session puis connecté comme C1, ouvrir `/admin`, `/admin/produits`, `/admin/commandes`, `/admin/stocks`, `/admin/equipe` et `/admin/parametres`. | Redirection vers `/admin/connexion` sans aucune information d'administration divulguée. |
| TCX-02 | P0 R SPEC | Sur les actions mutatives représentatives (panier HTML et `/api/panier/*`, commande, compte, annulation, back-office), retirer ou falsifier le jeton CSRF. | Chaque mutation est refusée (403) avec message humain explicite ; état avant/après strictement identique. |
| TCX-03 | P0 R SPEC | Inspecter les en-têtes HTTP (`Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`) et les cookies de session au démarrage, à la connexion et à la déconnexion, sans publier leur valeur. | CSP stricte (`default-src 'none'`, `script-src 'self'`, `style-src 'self'`, `connect-src 'self'`, `frame-ancestors 'none'`), zéro script/style en ligne ; cookie de session `HttpOnly`, `SameSite=Lax` (et `Secure` en HTTPS) renouvelé à la connexion. |
| TCX-04 | P0 M/R SPEC | Saisir du HTML ou des guillemets dans recherche, nom de produit, catégorie, adresse et formulaire de contact de test. | Aucune balise exécutable ; valeurs affichées littéralement échappées partout. |
| TCX-05 | P0 R SPEC | Essayer une consultation ou une action sur un identifiant appartenant à C2 depuis la session de C1. | Aucune divulgation ni modification des données de C2 (réponse 404 sur les commandes d'autrui). |
| TCX-06 | P1 M APP | Depuis le pied de page et la navigation, ouvrir `/a-propos`, `/aide`, `/contact`, `/mentions-legales` (et `/mentions`), `/cgv`, `/cgu`, `/livraison`, `/retractation`, `/garanties`, `/confidentialite`, `/cookies` et `/preferences`. | Toutes les pages répondent en HTTP 200 sans session requise, avec titres cohérents et aucun lien interne en 404. |
| TCX-07 | P0 M APP | Lire `/retractation`, `/cgv`, l'écran d'annulation de commande et `/contact`. | Annonce loyale de maquette scolaire : ni paiement réel, ni livraison, ni fonction légale de rétractation en ligne avec accusé durable ; formulaire de contact enregistré localement à titre de démonstration sans promesse d'envoi d'e-mail ni de délai de réponse. |
| TCX-08 | P0 M SPEC | Audit de préparation à une éventuelle vente réelle : chercher une fonction de rétractation en ligne (art. L.221-21-1) et son accusé sur support durable. | **NON CONFORME / BLOQUÉ pour une boutique commerciale réelle** tant que non implémentés ; documenté honnêtement dans [12-information-juridique.md](12-information-juridique.md) pour la maquette scolaire. |
| TCX-09 | P1 M APP | Contrôler `/cookies` et l'onglet Application/Stockage du navigateur sur un parcours complet. | Seul le cookie de session technique nécessaire (`HttpOnly`) et, après action sur `/preferences`, l'entrée `localStorage` `minishop.preferences.v1` sont présents ; aucun cookie publicitaire, analytique ni `sessionStorage`, ni `document.cookie` manipulé en JS. |
| TCX-10 | P1 M/I SPEC | Dans le navigateur réel et sur l'hébergement cible (LineServ), inventorier cookies, stockage et requêtes réseau dès le premier chargement. | Zéro requête vers un CDN, police distante ou service tiers ; vérifier que l'hébergement réel n'injecte pas de traceur externe supplémentaire. |
| TCX-11 | P1 M APP | Envoyer un message fictif valide via `/contact` puis lire l'accusé affiché. | Succès affiché uniquement après écriture effective dans le journal local ; rappel explicite qu'aucun courriel n'est expédié. |
| TCX-12 | P1 I APP | Sur une copie jetable uniquement, rendre le fichier de réception de contact non inscriptible puis soumettre `/contact`. | L'échec est signalé proprement à l'utilisateur ; aucun faux message de confirmation n'est affiché. Ne jamais modifier les permissions d'une instance partagée. |
| TCX-13 | P1 M SPEC | Tester accueil, catalogue, fiche produit, panier, validation, espace client, pages légales et toutes les vues d'administration à **320, 375, 768 et 1440 px**, en portrait/paysage sur téléphone réel et à **200 % de zoom**. | Aucun contenu coupé ou superposé, aucun défilement horizontal global de la page ; tableaux clients et administrateur repliés en cartes étiquetées `data-label` lisibles sur mobile. Noter appareil, navigateur et largeur. |
| TCX-14 | P1 M SPEC | Parcourir menus, skip-link, formulaires, filtres, panier, confirmations et préférences au clavier seul (`Tab`, `Maj+Tab`, `Entrée`, `Espace`) puis avec un lecteur d'écran réel (NVDA, Orca ou VoiceOver) et `prefers-reduced-motion`. | Focus visible partout, ordre logique, annonces vocales du panier pertinentes et animations réduites respectées ; consigner honnêtement toute gêne observée sans conclure seul à une certification WCAG/RGAA. |
| TCX-15 | P1 I SPEC | Tester panne et retour du magasin de données sur une instance jetable. | Page d'erreur générique sans secret (et sans affirmer faussement sur la 500 qu'aucune donnée n'a été modifiée), aucune écriture partielle, reprise propre au retour du service. |
| TCX-16 | P1 I SPEC | Mesurer les temps p95 et la tenue à la charge (`tests/perf/mesurer.sh`, `tests/charge/reserver.sh`) avec un jeu de recette défini et une autorisation écrite. | Comparer aux seuils du cahier des charges ; joindre conditions matérielles et mesures réelles. **Aucune mesure de serveur de recette n'est inventée ici.** |
| TCX-17 | P0 I SPEC | Vérifier l'absence ou le blocage de `public/mesurer.php` et `public/gen_volumes.php` lors d'un déploiement sur serveur partagé (LineServ). | Outils de développement exclus du dossier web public (cf. [14-guide-lineserv.md](14-guide-lineserv.md)) ; ne pas se reposer sur la seule garde d'hôte local. |
| TCX-18 | P1 I APP | Sur une copie jetable uniquement, tester la politique de conservation (`conservation_compte = 24` mois, [18-politique-conservation.md](18-politique-conservation.md)) via `scripts/purger_comptes.php`, `scripts/nettoyage_donnees.php` et `scripts/archive_restreinte.php` d'abord en simulation par défaut, puis avec `--executer`. | En simulation : aucune écriture. Avec `--executer` : `purger_comptes.php` ne supprime que les comptes inactifs ≥ 24 mois + 2 jours **sans commande** (ceux ayant des commandes ou une date future/invalide sont conservés et signalés) ; `nettoyage_donnees.php` applique 12 mois aux contacts, 6 mois aux journaux et 30 jours aux sauvegardes du dossier dédié marqué `.minishop-sauvegardes` (en gardant au moins les 2 plus récentes) ; `archive_restreinte.php` crée une archive chiffrée privée sans modifier la source. Noter qu'aucun `cron` automatique n'est installé par défaut. |
| TCX-19 | P0 M APP | Ouvrir `/mentions-legales` (et `/mentions`) ainsi que `/cgu` en français puis en anglais. | Mentions étudiantes complètes et exactes : édition non professionnelle dans le cadre de la SAE S3.02 (BUT Informatique 2e année, IUT Nice Côte d'Azur — Université Côte d'Azur) par **Johan Polsinelli** (`johan.polsinelli@etu.unice.fr`) et **Chahd Gouiaa** (`chahd.gouiaa@etu.unice.fr` / `chahd.gouiaa@etu.univ-cotedazur.fr`), co-responsabilité de la publication, dispense de domicile/téléphone personnel au titre de l'article 6, III, 2 de la LCEN, et hébergeur pédagogique visé (`linserv-info-01.campus.unice.fr`, 28 avenue Valrose, 06103 Nice Cedex 2, `+33 (0)4 89 15 00 00`). |
| TCX-20 | P0 M APP | Ouvrir `/preferences` sans être connecté : tester le choix manuel de langue (`Français` / `English`) et de fuseau IANA, vérifier le repli par défaut (`Français` → `Europe/Paris`, `English` → `America/New_York`), cliquer « Réinitialiser », puis tester avec `localStorage` bloqué et avec JavaScript désactivé. | Préférences enregistrées uniquement dans `localStorage` (`minishop.preferences.v1`), sans compte requis ni cookie de suivi ; « Réinitialiser » vide le stockage local et précise que l'autorisation navigateur de géolocalisation se retire dans les paramètres du navigateur ; comportement dégradé propre sans erreur bloquante si `localStorage` ou JS est désactivé. |
| TCX-21 | P1 M APP | Sur `/preferences` avec l'onglet Réseau ouvert : cliquer « Utiliser ma position » en refusant d'abord la permission navigateur, puis en l'autorisant (ou via l'émulateur de capteurs DevTools). | En cas de refus : message explicite et fuseau précédent conservé. En cas d'accord : calcul **100 % local** du fuseau (`public/js/tz-lookup.js` CC0) avec **zéro requête réseau externe**, **zéro coordonnée GPS transmise au serveur ou stockée dans `localStorage`**, affichage du fuseau estimé avec mention de marge d'approximation près des frontières et possibilité de correction manuelle. |
| TCX-22 | P0 M APP | Basculer en `English` sur `/preferences`, parcourir toutes les vues publiques, les 12 pages d'information/légales, le panier AJAX (`Adding…`, `Cart, 1 item`, refus de stock traduit) et l'espace client, puis revenir en `Français`. | Attribut `<html lang="en">` et `<title>` synchronisés ; interface et erreurs métier intégralement traduites en anglais sans code technique (seuls les noms/descriptions libres de produits issus de la base restent dans leur langue d'origine) ; aucun débordement lié à la longueur des libellés ; retour `EN → FR` restaure le français sans perte des saisies de formulaire. |
| TCX-23 | P0 M APP | Connecté en administrateur avec la langue `English`, parcourir toutes les pages `/admin*` (tableau de bord, produits, catégories, stocks, commandes, confirmation d'annulation, équipe, paramètres) à 1440 px puis à 375 px, puis repasser en `Français`. | Tous les titres, filtres, badges de statut, boutons, messages flash, écrans de confirmation et attributs `data-label` des cartes mobiles des tableaux administrateur sont traduits en anglais, puis reviennent fidèlement en français. |
| TCX-24 | P1 M APP | Inspecter les balises `<time datetime="…Z">` sur l'accueil, `/mes-commandes`, `/admin/commandes` et l'historique des statuts d'une commande en changeant de fuseau dans `/preferences` (dont dates d'hiver UTC+1 et d'été UTC+2), puis JavaScript coupé. | L'attribut `datetime` conserve toujours l'horodatage UTC canonique (`Z`), l'affichage humain s'adapte immédiatement au fuseau choisi (avec bascule heure d'hiver / heure d'été exacte), et le rendu serveur sans JavaScript affiche proprement en `Europe/Paris`. |
| TCX-25 | P1 M/I SPEC | Vérifier `/confidentialite` et, sur une copie jetable, créer/modifier un client et un administrateur fictifs puis inspecter le journal d'audit local (`app/Model/Data/Journal.php`). | `/confidentialite` expose clairement le périmètre de maquette scolaire, le stockage local des préférences et les durées de conservation ; les nouvelles traces d'audit des tables `client` et `administrateur` ne contiennent ni nom, ni e-mail, ni adresse, ni `mot_de_passe_hash`. |

## 16. Synthèse vierge de campagne

Ces lignes ne remplacent pas le journal par scénario. Ne cocher un UC que lorsque les cas applicables de cet UC ont été joués et examinés ; si un cas critique est bloqué ou échoue, reporter l'écart et ne pas annoncer une validation complète.

| UC | Fonction | Scénarios | Statut initial | Preuves / anomalies |
|---|---|---|---|---|
| UC-01 | Catalogue et accueil | TC01-01 à TC01-11 | NON JOUÉ |  |
| UC-02 | Recherche et filtres | TC02-01 à TC02-10 | NON JOUÉ |  |
| UC-03 | Fiche produit et visuels | TC03-01 à TC03-09 | NON JOUÉ |  |
| UC-04 | Inscription | TC04-01 à TC04-10 | NON JOUÉ |  |
| UC-05 | Connexion et compte | TC05-01 à TC05-11 | NON JOUÉ |  |
| UC-06 | Panier (avec et sans JS) | TC06-01 à TC06-11 | NON JOUÉ |  |
| UC-07 | Validation de commande | TC07-01 à TC07-13 | NON JOUÉ |  |
| UC-08 | Historique client | TC08-01 à TC08-09 | NON JOUÉ |  |
| UC-09 | Annulation client (2 étapes) | TC09-01 à TC09-09 | NON JOUÉ |  |
| UC-10 | Produits administration | TC10-01 à TC10-12 | NON JOUÉ |  |
| UC-11 | Catégories administration | TC11-01 à TC11-09 | NON JOUÉ |  |
| UC-12 | Stocks administration | TC12-01 à TC12-10 | NON JOUÉ |  |
| UC-13 | Commandes, KPI et rôles admin | TC13-01 à TC13-12 | NON JOUÉ |  |
| UC-14 | Statuts et annulation admin | TC14-01 à TC14-12 et 36 couples TC14-09 | NON JOUÉ |  |
| Transversal | Sécurité, responsive, FR/EN, fuseau, LCEN, conservation | TCX-01 à TCX-25 | NON JOUÉ |  |

### Éléments à ne pas confondre

1. Les tests automatisés du dépôt (suites PHP, PHPUnit, requêtes HTTP PHP-WASM, tests JSDOM `FR ↔ EN`, intégration SQL sur base jetable et Chromium headless en CI) sécurisent les non-régressions logiques et structurelles, mais **ne remplacent pas** un test manuel humain sur appareils physiques, lecteurs d'écran réels et instance déployée sur LineServ. Les fichiers de test mentionnés dans chaque fiche n'automatisent **pas nécessairement chaque nuance perceptive** de la fiche.
2. Le cahier des charges décrit certaines exigences plus ambitieuses que l'état maquette scolaire : en particulier la complexité lettre+chiffre du mot de passe (`TC04-06`), la limitation de débit sur `/connexion` (`TC05-09`), l'absence de `cron` installé d'office et l'exclusion volontaire des comptes possédant des commandes dans `purger_comptes.php` (`TCX-18`). Marquer les écarts observés et ne pas déduire une conformité de production commerciale du passage vert des suites automatisées.
3. La page de rétractation et les mentions légales correspondent à une **maquette pédagogique étudiante** : pas de déclaration de rétractation en ligne ni d'accusé durable réel, pas de paiement ni de livraison réels, et traduction anglaise à vocation pédagogique non relue par un juriste. Aucun texte de cette fiche ne transforme la maquette en boutique juridiquement prête à vendre.
4. Le SQL et les outils CLI de purge/nettoyage doivent être testés uniquement sur une **base ou copie de recette jetable identifiée et autorisée**, jamais sur la base de travail partagée.
5. Aucun temps de performance, résultat sur appareil, identité de testeur, historique ou validation humaine n'est fabriqué dans cette fiche : les 209 lignes de [13-suivi-recette-uc.csv](13-suivi-recette-uc.csv) restent à `NON JOUÉ` tant qu'un membre de l'équipe ne les a pas effectivement déroulées.

### Contrôle automatique de la fiche

Après récupération des fichiers, depuis le dépôt local :

```bash
cd /home/jojokes/Documents/BUT/Projet/s302
python3 scripts/verifier_fiches_uc.py
```

Sortie attendue :

```text
Fiches UC : 45 contrôles ; 173 scénarios, 36 couples, 209 lignes de suivi ; 0 erreur(s).
Contrôle documentaire réussi : aucune exécution métier n’a été effectuée.
```

Le contrôle vérifie la présence des 14 fiches, des identifiants distincts et ordonnés, de la synthèse, de la matrice conforme à `Triggers::TRANSITIONS`, des liens locaux, des chemins du dépôt cités et de l'alignement exact du fichier CSV. Il ne lance **aucun scénario métier** et ne prouve pas le résultat de la recette humaine.

### Auto-évaluation de la livraison documentaire

La fiche comporte **173 scénarios** identifiés (148 répartis sur `UC-01` à `UC-14` et 25 transversaux `TCX-01` à `TCX-25`) ainsi que **36 couples de transition** suivis séparément, soit **209 lignes** dans `docs/13-suivi-recette-uc.csv` (`TC14-09` restant le scénario chapeau de la matrice). Toutes les évolutions récentes du dépôt sont intégrées (confirmations sans JavaScript en 2 étapes, conservation des lignes après annulation, distinction avant/après expédition pour l'annulation administrateur et garde `ANNULATION_VIA_PARCOURS_DEDIE`, mode par défaut additif sur les stocks, refus des doublons de catégories, préférences locales FR/EN et fuseau horaire par choix manuel ou géolocalisation locale CC0 sans tiers, traduction intégrale des 48 vues et du panier AJAX, stockage UTC et bornes locales des filtres admin, cartes mobiles `data-label` à 320/375 px, double mode décoratif/informatif des visuels produits, mentions légales étudiantes LCEN art. 6, III, 2 et outils CLI de conservation à 24/12/6 mois et 30 jours). Les scénarios restent à exécuter humainement sur l'environnement cible puis à consigner avec leurs preuves réelles.
