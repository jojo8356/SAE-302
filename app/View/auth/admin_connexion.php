<?php

declare(strict_types=1);

/** @var list<string> $erreurs (RB-13, SEC-10) — connexion back-office séparée. */
?>
<div class="auth">
    <h1>Connexion back-office</h1>
    <p class="chapo">Accès réservé aux comptes administrateurs. Les comptes clients ne peuvent pas se connecter ici.</p>

    <?php if ($erreurs !== []): ?>
        <div class="message message--erreur" role="alert">
            <?= icone('alerte', 20) ?>
            <ul>
                <?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/admin/connexion">
        <?= csrf_field() ?>
        <fieldset>
            <legend class="visuellement-cache">Administrateur</legend>
            <h2 class="auth__titre">Administrateur</h2>
            <p><label for="email">Email professionnel</label><input type="email" id="email" name="email" maxlength="190" required autocomplete="username"></p>
            <p><label for="mot_de_passe">Mot de passe</label><input type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="current-password"></p>
            <p><button type="submit" class="btn--acheter">Accéder au back-office</button></p>
        </fieldset>
    </form>
    <p class="texte-aide"><?= icone('cadenas', 16) ?> Toute tentative échouée est journalisée.</p>
</div>
