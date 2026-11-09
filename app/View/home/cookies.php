<?php
declare(strict_types=1);
?>
<article class="legal">
    <header><p class="legal__sur-titre">Vos données</p><h1>Cookies et traceurs</h1><p class="chapo">Ce que la maquette utilise réellement.</p></header>
    <section><h2>Session nécessaire au fonctionnement</h2>
        <p>La navigation sur l’application démarre une session PHP et utilise le cookie <code>MINISHOPSESS</code>, qui contient un identifiant de session, pas le contenu du panier ni le mot de passe. Il permet de retrouver côté serveur le panier, la connexion et le jeton de protection contre les requêtes frauduleuses (CSRF).</p>
        <p>Le cookie est configuré <code>HttpOnly</code> et <code>SameSite=Lax</code>. L’attribut <code>Secure</code> dépend de la détection HTTPS par le serveur : un déploiement derrière un proxy doit être vérifié. La durée et les autres paramètres dépendent de la configuration PHP et du navigateur ; le réglage habituel est un cookie de session, sans durée fixe annoncée ici.</p>
        <p>Ce traceur est utilisé pour un service demandé par l’utilisateur et peut être exempté de consentement selon les conditions CNIL. Le bloquer ou le supprimer peut vider le panier et interrompre la connexion. Vous pouvez le supprimer dans les réglages de cookies de votre navigateur ; une nouvelle session peut être créée lors d’une visite ultérieure.</p>
    </section>
    <section><h2>Mesure technique de développement</h2>
        <p>L’outil séparé <code>/mesurer.php</code>, s’il est accessible en développement, utilise son propre cookie de session <code>MINISHOPSESSID</code> pour protéger son formulaire de mesure. Il est configuré comme cookie de session, <code>HttpOnly</code>, <code>SameSite=Lax</code> et actuellement sans attribut <code>Secure</code>. Cet outil n’est pas destiné au public et doit être exclu d’un déploiement réel.</p>
    </section>
    <section><h2>Publicité, statistiques et services tiers</h2>
        <p>Dans le code applicatif examiné, aucun cookie publicitaire, outil de mesure d’audience, pixel marketing, <code>localStorage</code> ou <code>sessionStorage</code> n’a été identifié. Les ressources de la boutique sont servies localement. Cette observation ne couvre pas une éventuelle modification, extension ou configuration d’hébergement extérieure.</p>
        <p>Il n’y a donc pas de bandeau de consentement pour des traceurs facultatifs dans cette maquette. Avant d’ajouter un traceur non exempté, il faut recueillir un consentement préalable et libre, permettre un refus aussi simple que l’acceptation, permettre le retrait et conserver une preuve du choix. Les conditions d’exemption d’une mesure d’audience doivent être vérifiées au cas par cas.</p>
        <p>En savoir plus : <a href="https://www.cnil.fr/fr/cookies-et-autres-traceurs/regles/cookies/comment-mettre-mon-site-web-en-conformite">recommandations de la CNIL</a> et <a href="/confidentialite">politique de confidentialité</a>.</p>
    </section>
</article>
