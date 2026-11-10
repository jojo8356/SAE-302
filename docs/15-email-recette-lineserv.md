# E-mail à envoyer à la collègue — transfert et recette MiniShop sur LineServ

L'e-mail ci-dessous demande à la collègue de transférer le projet : il ne présuppose pas qu'un site est déjà en ligne. Le lien IUT est celui communiqué par Johan. L'état réel de cette branche, l'accès et le déploiement LineServ n'ont pas été vérifiés ici. Le [guide LineServ](14-guide-lineserv.md) indique notamment les précautions et blocages techniques à examiner avant publication.

---

**Objet : Transfert et test complet de MiniShop sur LineServ**

Salut,

Est-ce que tu pourrais transférer notre site MiniShop sur **LineServ**, puis le tester et me dire ce qui fonctionne, ce qui bloque ou ce qui pourrait être amélioré ?

Il n'y a pas encore de lien pour le site : il faut récupérer le projet à partir de ma branche et le transférer sur LineServ :

https://iut-git.unice.fr/but2_s302/s302/-/tree/pj509414/dev?ref_type=heads

Utilise tes propres accès LineServ. Si le transfert ou l'initialisation est bloqué, notamment à cause de l'adresse en sous-dossier ou de la séparation des fichiers privés, arrête-toi et dis-moi à quelle étape tu bloques et quel message s'affiche. Ne réinitialise pas de base ou de répertoire existant et ne mets pas de fichiers privés ou de configuration dans un dossier accessible publiquement. Une fois le site installé, envoie-moi son lien.

C'est une **maquette** : le paiement et la livraison sont simulés. Merci d'utiliser uniquement les comptes et données fictifs, sans saisir de vraie carte bancaire ni de données personnelles.

Voici les comptes de démonstration prévus si le jeu de test est initialisé et n'a pas été modifié :

| Type | Adresse e-mail | Mot de passe |
|---|---|---|
| Client Alice | `alice@example.com` | `Demo2026!` |
| Client Bruno | `bruno@example.com` | `Demo2026!` |
| Client Carla | `carla@example.com` | `Demo2026!` |
| Administrateur **SUPER** | `admin@minishop.fr` | `Admin2026!` |

Ces comptes sont connus publiquement : le site de recette doit être à accès restreint ; ne laisse pas le compte administrateur de démonstration exposé publiquement.

Voici les points à tester :

1. **Catalogue et visuels** : naviguer dans les catégories, rechercher un produit, essayer le cumul et la réinitialisation des filtres et du tri, puis ouvrir une fiche produit.
2. **Compte** : t'inscrire ou te connecter avec les données de test, modifier le profil (dont l'adresse de livraison) et te déconnecter.
3. **Panier et commande** : ajouter un article (avec puis sans JavaScript si tu peux), changer sa quantité, le retirer, puis passer une commande simulée en vérifiant l'adresse pré-remplie, les frais de port et la confirmation.
4. **Historique et annulation en 2 étapes** : retrouver ta commande, ouvrir son détail et, si elle n'est pas encore expédiée, tester son annulation en vérifiant bien l'écran de confirmation intermédiaire et le fait que les lignes restent visibles dans l'historique avec le total d'origine avant annulation.
5. **Administration**, si tu as un accès autorisé : consulter le tableau de bord, tester produits, catégories (dont le refus de doublon et la confirmation de suppression), stocks (vérifier que le mode par défaut est bien « Ajouter / retirer ») et statuts de commandes (dont l'écran de confirmation d'annulation, avec retour de stock avant expédition et sans retour automatique si la commande est déjà expédiée), uniquement sur les éléments de test que je t'indiquerai.
6. **Responsive — toutes les pages** : peux-tu ouvrir **chaque page du site**, sur ordinateur et téléphone si possible, pour vérifier que l'affichage s'adapte bien ? Pense notamment à l'accueil, aux catégories, au catalogue, à la recherche, aux fiches produit, aux préférences, à l'inscription, à la connexion, au compte, au panier, à la commande, à l'historique et au détail des commandes, aux pages d'information/légales et à toutes les pages d'administration accessibles. Vérifie aussi les formulaires et messages d'erreur. Signale les pages que tu n'as pas pu tester.

   Pour chaque page, regarde si le menu, les images, les textes, les tableaux, les champs et les boutons sont lisibles et utilisables, sans élément coupé, superposé ou sorti de l'écran. Vérifie les liens et les boutons, et qu'il n'y a pas de défilement horizontal de toute la page. Sur téléphone (**320 et 375 px**), vérifie en particulier que les tableaux du panier, des commandes et de l'administration s'affichent bien en **cartes mobiles lisibles avec les libellés de chaque champ**.

   Si tu peux, essaie le téléphone en portrait et en paysage et zoome à 200 % sur les pages. Sur ordinateur, tu peux aussi utiliser le mode responsive du navigateur : dans Chrome ou Chromium, `F12`, puis `Ctrl+Maj+M`, et essayer les largeurs **320, 375, 768 et 1440 pixels**. La simulation complète le test sur téléphone, mais ne le remplace pas.
7. **Langue FR/EN et fuseau horaire (`/preferences`)** : ouvrir la page **Préférences**, passer le site en **anglais (`English`)** pour vérifier que toutes les pages (publiques, panier, compte, pages légales et administration) sont bien traduites, puis revenir en **français**. Tester aussi le changement de fuseau horaire (manuellement et, si tu veux, avec « Utiliser ma position ») puis vérifier que les dates affichées et les filtres de dates s'adaptent bien, avant de cliquer sur « Réinitialiser ».
8. **Informations et mentions légales** : ouvrir tous les liens du pied de page, notamment les mentions légales, l'à-propos, le contact, l'aide, la livraison, les CGV, les CGU, les garanties, la confidentialité, les cookies et la rétractation, et me signaler les liens cassés ou textes peu clairs.

Pour chaque problème, peux-tu me préciser :

- la page concernée et les étapes pour reproduire le problème ;
- ce que tu attendais et ce qui s'est réellement passé ;
- le message affiché et, si possible, une capture, en masquant les données personnelles et les identifiants ;
- ton appareil, ton navigateur et, pour un problème d'affichage, la taille ou l'orientation de l'écran ;
- si le problème arrive à chaque fois et s'il bloque la suite.

Tu peux me répondre avec un problème par paragraphe. Dis-moi aussi quelles pages sont correctes et lesquelles tu n'as pas pu tester.

Les **fiches des 14 cas d'utilisation**, le **tableau de suivi** et le **cahier des charges** sont dans le GitHub si tu veux davantage de détails.

Merci beaucoup pour ton aide !

Johan
