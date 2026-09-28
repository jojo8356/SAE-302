<?php

declare(strict_types=1);

/**
 * MiniShop — gabarit commun (HTML sémantique, volontairement SANS CSS).
 * Variables attendues : $contenu, $titrePage, $client, $admin,
 * $flashSucces, $flashErreur.
 */

$nbArticlesPanier = 0;
try {
    $nbArticlesPanier = \App\Model\PanierSession::chargé()->nombreArticles();
} catch (Throwable) {
    // un panier cassé ne doit jamais empêcher l'affichage d'une page
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titrePage ?? 'MiniShop') ?></title>
</head>
<body>
<header>
    <p><strong><a href="/">MiniShop</a></strong> — matériel informatique &amp; objets connectés</p>
    <nav aria-label="Navigation principale">
        <ul>
            <li><a href="/catalogue">Catalogue</a></li>
            <li><a href="/categories">Catégories</a></li>
            <li><a href="/panier">Panier (<?= (int) $nbArticlesPanier ?>)</a></li>
            <?php if (($client ?? null) !== null): ?>
                <li><a href="/compte">Mon compte (<?= e($client['prenom']) ?>)</a></li>
                <li><a href="/mes-commandes">Mes commandes</a></li>
                <li><a href="/deconnexion">Déconnexion</a></li>
            <?php else: ?>
                <li><a href="/connexion">Connexion</a></li>
                <li><a href="/inscription">Créer un compte</a></li>
            <?php endif; ?>
            <?php if (($admin ?? null) !== null): ?>
                <li><a href="/admin">Back-office</a> (<?= e($admin['role']) ?>)</li>
                <li><a href="/admin/deconnexion">Quitter le BO</a></li>
            <?php endif; ?>
            <li><a href="/a-propos">À propos</a></li>
        </ul>
    </nav>
</header>

<?php if (!empty($flashSucces)): ?>
    <p role="status"><strong>✔ <?= e($flashSucces) ?></strong></p>
<?php endif; ?>
<?php if (!empty($flashErreur)): ?>
    <p role="alert"><strong>✘ <?= e($flashErreur) ?></strong></p>
<?php endif; ?>

<main>
    <?= $contenu ?>
</main>

<footer>
    <hr>
    <p>
        <a href="/mentions-legales">Mentions légales</a> ·
        <a href="/cgv">Conditions générales de vente</a> ·
        MiniShop (maquette pédagogique — aucun paiement réel)
    </p>
</footer>
</body>
</html>
