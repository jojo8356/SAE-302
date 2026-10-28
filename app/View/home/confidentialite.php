<?php
declare(strict_types=1);
?>
<article class="legal">
    <header><p class="legal__sur-titre">Vos données</p><h1>Politique de confidentialité</h1><p class="chapo">Quelles informations la maquette traite-t-elle et pourquoi ?</p></header>
    <p class="legal__alerte"><strong>Maquette pédagogique :</strong> cette information décrit le code observé, pas une politique complète de production. N’utilisez pas de données sensibles ou bancaires. Responsable du traitement et coordonnées pour exercer les droits : <strong>à renseigner</strong> avant toute utilisation réelle.</p>
    <section><h2>Données traitées</h2>
        <ul>
            <li><strong>Compte :</strong> nom, prénom, adresse e-mail, mot de passe enregistré sous forme de condensat, coordonnées éventuellement saisies et informations de connexion.</li>
            <li><strong>Panier et session :</strong> identifiant de session, contenu du panier, authentification et jeton de protection des formulaires.</li>
            <li><strong>Commandes simulées :</strong> compte associé, adresse de livraison saisie, articles, montants et historique des statuts.</li>
            <li><strong>Contact de démonstration :</strong> nom, e-mail, message et date enregistrés localement dans <code>var/log/contact.jsonl</code>. Aucun e-mail n’est envoyé et aucune réponse n’est garantie. D’anciens messages peuvent n’avoir conservé que leurs 500 premiers caractères.</li>
            <li><strong>Journaux :</strong> en mode JSON, les écritures peuvent conserver l’identité de l’acteur et les anciennes et nouvelles valeurs, y compris des données de compte ou de commande ; l’hébergement peut également produire des journaux techniques selon sa configuration.</li>
        </ul>
        <p>La maquette ne demande pas de numéro de carte bancaire. Les <a href="/cookies">cookies et traceurs</a> sont détaillés séparément.</p>
    </section>
    <section><h2>Finalités et bases juridiques à définir</h2>
        <p>Les données permettent de faire fonctionner le compte, le panier, la sécurité, les commandes simulées et le formulaire de démonstration. Dans un service réel, les bases légales doivent être déterminées pour chaque traitement : par exemple l’exécution d’un contrat ou de mesures précontractuelles pour une commande, une obligation légale pour certaines archives, ou un intérêt légitime pour la sécurité et certaines demandes, sous réserve de leur justification. Aucun traitement publicitaire n’a été identifié dans le code examiné.</p>
    </section>
    <section><h2>Destinataires, hébergement et transferts</h2>
        <p>Les données sont accessibles au personnel autorisé de l’instance et à ses éventuels prestataires techniques selon leur configuration. Le magasin peut utiliser des fichiers JSON ou une base SQL. Identité de l’hébergeur, liste des destinataires et sous-traitants, lieu de stockage, transferts éventuels hors EEE et garanties correspondantes : <strong>à déterminer et publier</strong> pour le déploiement réel. Aucun transfert n’est affirmé ou exclu sans audit de cet environnement.</p>
    </section>
    <section><h2>Durées de conservation</h2>
        <p>Aucune durée effective générale ni purge automatique n’est établie dans la maquette. Le paramètre de 36 mois pour les comptes inactifs est indicatif : il ne déclenche pas à lui seul de suppression. Les comptes, commandes, messages, journaux et sauvegardes peuvent donc rester conservés jusqu’à leur suppression par l’exploitant.</p>
        <p>Avant utilisation réelle, il faut définir et appliquer des durées ou critères justifiés pour chaque catégorie, y compris les archives comptables si elles existent, les sauvegardes et les journaux, puis mettre en place les purges et contrôles correspondants.</p>
    </section>
    <section><h2>Vos droits</h2>
        <p>Dans les conditions prévues par le RGPD, vous disposez notamment des droits d’accès, de rectification, d’effacement, de limitation, d’opposition et, lorsque applicable, de portabilité. Lorsque le traitement repose sur un consentement, celui-ci peut être retiré à tout moment. Vous pouvez aussi introduire une réclamation auprès de la <a href="https://www.cnil.fr/fr/plaintes">CNIL</a>.</p>
        <p>Responsable du traitement et coordonnées effectives pour les demandes : <strong>à renseigner</strong>. Le <a href="/contact">formulaire de démonstration</a> ne garantit pas le traitement d’une demande de droits. Des procédures d’identification, de réponse et d’effacement restent à mettre en place.</p>
    </section>
</article>
