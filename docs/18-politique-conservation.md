# Conservation et préparation d'archives — proposition à valider

## Décision et état réel

Le responsable du traitement doit valider les finalités, bases légales, durées, habilitations et procédures ci-dessous. Ce document n'est ni un avis juridique ni une décision déjà prise. Le projet est une maquette : une commande simulée n'est pas automatiquement un document comptable. L'article L.123-22 du Code de commerce prévoit dix ans pour les documents comptables et pièces justificatives concernés, **pas pour tous les comptes ou toutes les commandes**. Un contentieux réel ou une demande de droits peut justifier une conservation distincte, limitée et réexaminée ; une instruction de gel documentée prime sur une suppression programmée.

**[!] Ce qui existe :** une purge CLI, simulée par défaut, de certains comptes inactifs **sans commande** ; et un outil manuel pour créer des copies chiffrées et tracer les restitutions réalisées via cet outil. Les comptes avec commande restent en clair dans la base active et visibles par les parcours existants ; une copie chiffrée ne constitue **pas** à elle seule leur archivage intermédiaire. Le traitement ne parcourt pas les tables, ne trie ni ne retire les données actives, ne purge aucun journal ou message Contact, ne gère ni restauration de sauvegardes ni suppression à échéance. Il n'est pas installé sur LineServ et aucun cron n'est activé.

## Tableau proposé (non validé et non automatisé sauf mention)

| Catégorie | Finalité et durée proposées | Échéance et actions à valider |
|---|---|---|
| Compte actif | 24 mois après la dernière action de la personne ; la mise à jour de la connexion authentifiée n'est qu'un indicateur partiel. | Purge existante : seuls les comptes sans commande et aux dates fiables, avec marge de deux jours ; examen manuel de tout autre cas et des actions non reliées. Informer avant suppression selon procédure validée. |
| Commandes et comptes associés | En base active durant la relation et les opérations en cours. Ensuite, ne conserver en archive intermédiaire que les éléments nécessaires à une obligation ou à un contentieux identifié, avec échéance justifiée pour chaque dossier ; les seules pièces comptables réellement concernées peuvent relever de dix ans. | [!] Aucun transfert restreint opérationnel : développer et éprouver extraction minimale, exclusion des commandes en cours, contrôle concurrent et retrait sûr des originaux, y compris clés étrangères, historique et tous les parcours. N'effacer ni preuve nécessaire ni stock par effet de bord. Vérifier le fuseau SQL avant utilisation des dates. |
| Messages Contact (`var/log/contact.jsonl`) | Proposition : traitement de la demande puis au plus 12 mois après sa clôture ; pour une demande engageant des droits, conservation adaptée et distincte si nécessaire. | [!] Le fichier n'enregistre pas de clôture fiable : la durée ne peut pas être exécutée correctement sans suivi. Trier les messages et minimiser les données ; définir une procédure d'effacement et de réponse. Ce fichier n'est pas automatiquement associé à un compte. |
| Journaux JSON historiques et journaux de sécurité | Proposition : pour les événements de sécurité, 6 mois glissants, à ajuster jusqu'à un an selon besoin documenté ; autres journaux à inventorier par finalité. Éviter données et snapshots personnels inutiles. | [!] Les anciens journaux JSON peuvent contenir des valeurs personnelles et les nouveaux ne sont pas une garantie d'absence générale de données. Inventorier, séparer les journaux nécessaires, filtrer, limiter l'accès, puis définir rotation et purge testées ; ne pas supprimer une preuve sous gel. |
| Sauvegardes, exports et copies hors ligne | Proposition opérationnelle : 30 jours glissants pour les sauvegardes de reprise, à adapter aux contraintes de restauration, avec chiffrement et accès réservés ; ne pas confondre sauvegarde de reprise et archive légale. | [!] Inventorier notamment les copies hébergeur, leur durée et leur localisation ; établir rotation/suppression, destruction des clés, tests périodiques d'intégrité et de restauration, copie isolée et copie géographiquement distincte. Répercuter les suppressions après restauration avant toute remise en service. Les copies déjà présentes ne sont pas traitées par ce dépôt. |
| Journal de consultation des archives | Proposition : conserver 12 mois, sauf besoin de preuve justifié et validé ; ne journaliser ni contenu, ni nom, ni e-mail. | [!] `acces.jsonl` est local à la machine de restitution ; sa rétention, centralisation protégée et revue sont à organiser. Un UID n'identifie pas une personne si un compte est partagé. |

Les durées proposées ne créent aucun délai légal et ne doivent pas être activées sans validation, registre des traitements et information des personnes mis à jour. La purge d'une source n'efface pas automatiquement les fichiers temporaires, journaux, caches, snapshots ou sauvegardes. Les droits d'accès et d'effacement doivent aussi couvrir les archives et leurs éventuelles exceptions légales.

## Outil manuel : copies chiffrées, pas transfert

`scripts/archive_restreinte.php` utilise les boîtes scellées libsodium (clé publique pour chiffrer ; clé privée pour déchiffrer), PHP CLI avec extensions `sodium` et `posix`, sous Linux/POSIX. Il refuse les répertoires dans le dépôt, ceux accessibles au groupe/autres ou dont le propriétaire n'est pas l'UID effectif, les fichiers source et clés sous forme de lien symbolique final, les clés invalides, fichiers existants et données de plus de **8 Mio**. Les copies et restitutions sont créées en 0600 ; les métadonnées (catégorie, justification et échéance) sont dans l'enveloppe chiffrée. Le nom aléatoire n'identifie pas la personne. Chaque commande de scellement sans `--executer` est une simulation ; elle lit néanmoins la source. Le scellement ne modifie ni ne supprime la source. Il ne garantit pas un instantané cohérent si la source est modifiée pendant la lecture : réaliser l'extraction sous maintenance ou depuis un instantané cohérent. Une interruption peut laisser un fichier chiffré incomplet, qui ne doit pas être utilisé.

La consultation exige un motif prédéfini et écrit d'abord, dans le répertoire privé de restitution, une entrée UTC avec UID et empreinte SHA-256 du fichier chiffré ; si le journal ne peut pas être écrit, aucune restitution n'est créée. L'événement signifie **restitution demandée**, pas qu'elle a réussi. Le fichier restitué est en clair : travailler sur un poste maîtrisé, empêcher sa sauvegarde/synchronisation non voulue et appliquer une procédure d'effacement appropriée. Le journal local est modifiable par son propriétaire ; il ne trace pas l'usage direct d'autres outils de déchiffrement. Prévoir un collecteur séparé protégé et une revue des accès pour une traçabilité fiable. Les boîtes scellées protègent la confidentialité et détectent la corruption, mais **n'authentifient pas l'auteur** : toute personne possédant la clé publique peut créer un fichier. Ne pas utiliser ce format seul comme preuve d'origine.

L'outil n'établit pas la nécessité légale, ne vérifie pas le contenu ni l'échéance après création, et ne supprime pas à échéance. La catégorie et la justification sont des déclarations de l'opérateur, pas des validations. Il est destiné aux petits fichiers préparés et minimisés ; aucun découpage automatique ni outil de sauvegarde volumineuse n'est fourni. Ne pas sceller toute la base au titre d'un seul client. Avant toute utilisation réelle : limiter les champs aux besoins justifiés, prévoir une sélection fiable et un registre séparé des échéances, accès et gels, désigner des personnes habilitées, établir un circuit d'accès ponctuel motivé et tester la restitution et la suppression dans un environnement jetable. Le répertoire doit être hors de **tous** les webroots, dépôts, synchronisations et sauvegardes non autorisées ; le script ne peut pas déterminer tous ces emplacements ni auditer les ACL, comptes privilégiés ou configurations d'hébergement. Vérifier ces aspects et les droits système séparément.

Générer la clé privée sur une machine de confiance distincte, non accessible au compte applicatif ; ne transférer que la clé publique vers le serveur de scellement. Stocker et sauvegarder la clé privée séparément, sous contrôle des personnes habilitées ; documenter rotation, perte et destruction des clés, et canal sécurisé de transfert des copies chiffrées. Une personne pouvant lire la clé privée peut contourner le programme et son journal. Ne pas placer de clé privée dans `.env`, le dépôt, les arguments, les journaux ou les artefacts CI.

### Démonstration locale sans données réelles

À exécuter depuis le dépôt sur une machine Linux dotée de PHP, sodium et posix. Uniquement des données fictives ; la clé privée et la copie sont volontairement dans le même dossier temporaire **pour cette démonstration**, contrairement à l'organisation réelle. Le nettoyage ne concerne que ce répertoire temporaire créé par la commande.

```bash
cd /home/jojokes/Documents/BUT/Projet/s302
(
set -eu
D=$(mktemp -d)
trap 'rm -rf "$D"' EXIT
mkdir -m 700 "$D/cles" "$D/archives" "$D/sortie"
printf 'message fictif\n' > "$D/source.txt"
php scripts/archive_restreinte.php generer-cles --dossier "$D/cles"
php scripts/archive_restreinte.php sceller --source "$D/source.txt" --dossier "$D/archives" --cle-publique "$D/cles/cle-publique.txt" --categorie contact --echeance 2099-01-01 --justification droits
php scripts/archive_restreinte.php sceller --source "$D/source.txt" --dossier "$D/archives" --cle-publique "$D/cles/cle-publique.txt" --categorie contact --echeance 2099-01-01 --justification droits --executer
A=$(find "$D/archives" -name '*.msa' -type f -print -quit)
php scripts/archive_restreinte.php consulter --archive "$A" --cle-privee "$D/cles/cle-privee.txt" --dossier-sortie "$D/sortie" --motif controle
R=$(find "$D/sortie" -name 'restitution-*.bin' -type f -print -quit)
cmp "$D/source.txt" "$R" && echo 'OK : restitution identique ; source inchangée'
test "$(wc -l < "$D/sortie/acces.jsonl")" -eq 1 && echo 'OK : une demande de restitution tracée'
)
```

Sortie attendue : création des clés, `SIMULATION`, création d'une copie chiffrée, restitution en clair, puis les deux lignes `OK`. Les noms aléatoires varient. `2099-01-01` est une date de test, **pas** une durée de conservation approuvée. L'exemple n'installe ni planification ni politique sur l'instance.

## Références

- [CNIL — concilier durées de conservation et archives](https://www.cnil.fr/fr/comment-concilier-les-durees-de-conservation-et-les-archives) : tri, séparation, habilitations et traçabilité des consultations.
- [CNIL — questions-réponses activités commerciales](https://www.cnil.fr/fr/questions-reponses-sur-les-referentiels-relatifs-la-gestion-des-activites-commerciales-et-des) : deux ans d'inactivité pour un compte, par distinction avec la prospection.
- [CNIL — guide sécurité 2024](https://www.cnil.fr/sites/cnil/files/2024-03/cnil_guide_securite_personnelle_2024.pdf) : sécurité des sauvegardes, restauration, journalisation.
- [Code de commerce, article L.123-22](https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000006219327/) ; [RGPD, articles 5 et 17](https://eur-lex.europa.eu/legal-content/FR/TXT/HTML/?uri=CELEX%3A32016R0679).
