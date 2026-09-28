<?php

declare(strict_types=1);

/** @var list<string> $erreurs @var array<string,string> $valeurs (UC-04, EF-VIS-06) */
?>
<h1>Créer un compte</h1>

<?php if ($erreurs !== []): ?>
    <ul role="alert">
        <?php foreach ($erreurs as $erreur): ?><li>✘ <?= e($erreur) ?></li><?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" action="/inscription">
    <?= csrf_field() ?>
    <fieldset>
        <legend>Vos informations (RB-01 : l'email est unique)</legend>
        <p><label for="nom">Nom *</label><input id="nom" name="nom" maxlength="100" required value="<?= e($valeurs['nom'] ?? '') ?>"></p>
        <p><label for="prenom">Prénom *</label><input id="prenom" name="prenom" maxlength="100" required value="<?= e($valeurs['prenom'] ?? '') ?>"></p>
        <p><label for="email">Email *</label><input type="email" id="email" name="email" maxlength="190" required value="<?= e($valeurs['email'] ?? '') ?>"></p>
        <p><label for="mot_de_passe">Mot de passe * (8 caractères min.)</label><input type="password" id="mot_de_passe" name="mot_de_passe" minlength="8" required></p>
        <p><label for="confirmation">Confirmation *</label><input type="password" id="confirmation" name="confirmation" minlength="8" required></p>
        <p><label><input type="checkbox" name="cgv" value="1" required> J'accepte les <a href="/cgv">conditions générales de vente</a></label></p>
        <p><button type="submit">Créer mon compte</button></p>
    </fieldset>
</form>
<p>Déjà inscrit ? <a href="/connexion">Connectez-vous</a>.</p>
