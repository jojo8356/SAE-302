<?php

declare(strict_types=1);

/** Page d'erreur 403 — accès refusé (RB-13, SEC-10). */
?>
<div class="erreur-page">
    <p class="erreur-page__code">403</p>
    <h1>Accès refusé</h1>
    <p>Cette page est réservée à un rôle que votre compte ne possède pas.
        Si vous pensez qu'il s'agit d'une erreur, contactez un administrateur.</p>
    <p>
        <a class="btn btn--acheter" href="/admin">Retour au back-office</a>
        <a class="btn btn--secondaire" href="/">Retour à l'accueil</a>
        <a class="btn btn--secondaire" href="/contact">Nous contacter</a>
    </p>
</div>
