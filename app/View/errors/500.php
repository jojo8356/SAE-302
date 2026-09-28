<?php

declare(strict_types=1);

/** Page d'erreur 500 — aucune information technique divulguée (SEC-03). */
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Erreur — MiniShop</title>
</head>
<body>
<h1>Une erreur inattendue est survenue (500)</h1>
<p>Nous sommes désolés : la page n'a pas pu être traitée. Aucune donnée n'a été
corrompue — le moteur de données applique des transactions (ENF-16).</p>
<?php if (!empty($debug)): ?>
    <pre><?= htmlspecialchars((string) ($e ?? null), ENT_QUOTES, 'UTF-8') ?></pre>
<?php endif; ?>
<p><a href="/">Retour à l'accueil</a></p>
</body>
</html>
