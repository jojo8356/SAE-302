<?php

declare(strict_types=1);

/** Page d'erreur 404 — route inconnue ou ressource absente (liste blanche du routeur). */
?>
<div class="erreur-page">
    <p class="erreur-page__code">404</p>
    <h1>Page introuvable</h1>
    <p>La page demandée n'existe pas ou n'est plus disponible.
        Le site fonctionne par <strong>liste blanche</strong> : seule une URL explicitement déclarée peut répondre.</p>
    <p>
        <a class="btn btn--acheter" href="/catalogue">Voir le catalogue</a>
        <a class="btn btn--secondaire" href="/">Retour à l'accueil</a>
    </p>
</div>
