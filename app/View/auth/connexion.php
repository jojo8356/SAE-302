<?php

declare(strict_types=1);

/** @var list<string> $erreurs @var string $email (UC-05, EF-VIS-07) */
?>
<div class="auth">
    <h1>Connexion</h1>
    <p class="chapo">Accédez à votre compte, à votre panier et à vos commandes.</p>

    <?php if ($erreurs !== []): ?>
        <div class="message message--erreur" role="alert">
            <?= icone('alerte', 20) ?>
            <ul>
                <?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/connexion">
        <?= csrf_field() ?>
        <fieldset>
            <legend class="visuellement-cache">Espace client</legend>
            <h2 class="auth__titre">Espace client</h2>
            <p>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="190" required autocomplete="email" value="<?= e($email) ?>">
            </p>
            <p>
                <label for="mot_de_passe">Mot de passe</label>
                <input type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="current-password">
            </p>
            <p>
                <button type="submit" class="btn--acheter">Se connecter</button>
            </p>
        </fieldset>
    </form>
    <p>Pas encore de compte ? <a href="/inscription">Créez-en un</a>.</p>
</div>
