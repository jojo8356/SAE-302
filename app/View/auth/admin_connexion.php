<?php

declare(strict_types=1);

/** @var list<string> $erreurs @var string $email (RB-13 : session admin distincte) */
?>
<h1>Back-office — connexion</h1>

<?php if ($erreurs !== []): ?>
    <ul role="alert">
        <?php foreach ($erreurs as $erreur): ?><li>✘ <?= e($erreur) ?></li><?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" action="/admin/connexion">
    <?= csrf_field() ?>
    <fieldset>
        <legend>Compte administrateur (RB-13 : espace strictement séparé de l'espace client)</legend>
        <p><label for="email">Email</label><input type="email" id="email" name="email" maxlength="190" required value="<?= e($email) ?>"></p>
        <p><label for="mot_de_passe">Mot de passe</label><input type="password" id="mot_de_passe" name="mot_de_passe" required></p>
        <p><button type="submit">Accéder au back-office</button></p>
    </fieldset>
</form>
<p><a href="/">← Retour à la boutique</a></p>
