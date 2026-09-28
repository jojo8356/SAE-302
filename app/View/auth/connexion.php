<?php

declare(strict_types=1);

/** @var list<string> $erreurs @var string $email (UC-05, EF-VIS-07) */
?>
<h1>Connexion</h1>

<?php if ($erreurs !== []): ?>
    <ul role="alert">
        <?php foreach ($erreurs as $erreur): ?><li>✘ <?= e($erreur) ?></li><?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" action="/connexion">
    <?= csrf_field() ?>
    <fieldset>
        <legend>Espace client</legend>
        <p><label for="email">Email</label><input type="email" id="email" name="email" maxlength="190" required value="<?= e($email) ?>"></p>
        <p><label for="mot_de_passe">Mot de passe</label><input type="password" id="mot_de_passe" name="mot_de_passe" required></p>
        <p><button type="submit">Se connecter</button></p>
    </fieldset>
</form>
<p>Pas encore de compte ? <a href="/inscription">Créez-en un</a>.</p>
