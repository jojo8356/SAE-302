# E-mail à envoyer à la collègue — recette pré-démo MiniShop sur LineServ

L'e-mail ci-dessous demande une recette manuelle sur l'instance **de production LineServ et sa base réellement configurée**. Il ne présuppose ni que le site est en ligne ni que la recette a été jouée. Ne l'envoyer qu'une fois la cible, l'accès restreint, la sauvegarde et la procédure de retour à l'état de démonstration confirmés. `minishop_test` reste réservée aux tests automatisés GitHub CI.

---

**Objet : Recette pré-démo de MiniShop sur LineServ**

Salut,

Est-ce que tu pourrais tester MiniShop sur **LineServ** pendant la fenêtre de recette pré-démo que nous aurons confirmée ensemble, puis me signaler ce qui fonctionne, bloque ou pourrait être amélioré ? Il s'agit de l'instance de production de notre projet et de la base réellement utilisée par cette instance — pas d'une nouvelle base de test.

Le site n'est pas déclaré disponible par ce message. Le lien prévu est celui communiqué pour LineServ :

https://linserv-info-01.campus.unice.fr/~pj509414/s302/

Avant toute écriture, nous devons confirmer en privé que la configuration de l'application et la base sélectionnée dans phpMyAdmin/Adminer désignent bien la même base de production MiniShop, que l'accès au site est limité aux testeurs et qu'elle ne contient que des données fictives/de démonstration. Une sauvegarde complète de l'état de démonstration et une procédure autorisée pour la restaurer dans **cette même base** doivent aussi être prêtes. Si la cible n'est pas certaine, si tu vois des données réelles ou si un utilisateur non prévu peut accéder au site, arrête-toi et préviens-moi.

Utilise uniquement des comptes et données fictifs créés pour la recette (`example.test`, références de test), sans vraie adresse, donnée personnelle ni moyen de paiement. Si un compte autorisé est nécessaire, je te transmettrai son accès séparément par un canal privé : ne mets aucun mot de passe dans un e-mail, document, ticket ou capture.

Voici les vérifications prévues sur ordinateur (pas de campagne responsive multi-tailles : elle est couverte par les tests automatisés) :

1. **Catalogue et visuels** : parcourir les catégories, rechercher un produit, essayer les filtres et leur réinitialisation, puis ouvrir une fiche produit.
2. **Compte de test** : créer un compte fictif ou utiliser le compte de test autorisé, modifier uniquement ses données synthétiques, puis se déconnecter.
3. **Panier et commande** : ajouter un article de test, changer sa quantité, le retirer, puis passer une commande simulée en vérifiant l'adresse fictive, les frais de port et la confirmation.
4. **Historique et annulation en deux étapes** : retrouver une commande de test, ouvrir son détail et, si elle n'est pas expédiée, vérifier l'écran de confirmation, l'annulation et la conservation de ses lignes dans l'historique.
5. **Administration**, uniquement si tu disposes d'un accès explicitement autorisé : tester produits, catégories, stocks et statuts de commandes sur les éléments de test prévus, jamais sur des données réelles ou non identifiées.
6. **Langue FR/EN et fuseau (`/preferences`)** : passer en anglais, vérifier les pages accessibles, revenir en français, essayer un changement de fuseau, puis réinitialiser les préférences du navigateur.
7. **Informations et mentions légales** : ouvrir les liens du pied de page et signaler les liens cassés ou les textes peu clairs.

Ne lance aucun SQL direct, fixture, import, commande de reset (`run.sh --db`, `scripts/db_init.php`, `scripts/load_db.sh`), test automatisé SQL, purge, outil de volumétrie ou script de charge contre la base de production. Les scénarios qui exigent une intervention de ce type sont à marquer `BLOQUÉ` dans le suivi ; ils ne justifient pas de contourner ces consignes.

Pour chaque problème, peux-tu préciser :

- la page concernée et les étapes pour reproduire le problème ;
- ce que tu attendais et ce qui s'est réellement passé ;
- le message affiché et, si possible, une capture expurgée de données, identifiants, cookies, mots de passe et valeurs de configuration ;
- ton navigateur ;
- si le problème arrive à chaque fois et s'il bloque la suite.

Après la recette, arrête les tests et préviens-moi. **Avant la démo, la base de production LineServ devra obligatoirement être restaurée à l'état de démonstration à partir de la sauvegarde pré-recette**, par la personne autorisée et selon la procédure validée. Ne la réinitialise pas toi-même si la cible ou la procédure n'est pas certaine. L'accès restera restreint jusqu'à la vérification de la restauration ; si elle échoue, nous ne présenterons pas la base comme prête.

Tu peux me répondre avec un problème par paragraphe. Dis-moi aussi quelles pages sont correctes, lesquelles tu n'as pas pu tester et si tu as dû marquer des cas `BLOQUÉ`.

Les **fiches des 14 cas d'utilisation**, le **tableau de suivi** et le **guide LineServ** sont dans le dépôt si tu veux davantage de détails.

Merci beaucoup pour ton aide !

Johan
