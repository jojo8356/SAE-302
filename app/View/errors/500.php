<?php

declare(strict_types=1);

/**
 * Page d'erreur 500 — aucune fuite technique (SEC-03).
 * Le détail est journalisé (error_log) et n'apparaît ici qu'en mode debug.
 * @var bool|null $debug
 */
?>
<div class="erreur-page">
    <p class="erreur-page__code">500</p>
    <h1>Erreur technique</h1>
    <p>Une erreur inattendue est survenue. Vérifiez l’état de votre commande ou de votre compte avant de réessayer.</p>
    <p>Vous pouvez revenir à l'accueil ou réessayer dans quelques instants.</p>
    <?php if (($debug ?? false) === true): ?>
        <p class="message message--erreur">Mode debug : consultez <code>var/log</code> pour le détail.</p>
    <?php endif; ?>
    <p>
        <a class="btn btn--acheter" href="/">Retour à l'accueil</a>
        <a class="btn btn--secondaire" href="/catalogue">Voir le catalogue</a>
    </p>
</div>
