<?php

declare(strict_types=1);

/** @var array<string,mixed> $client @var list<string> $erreurs (EF-CLI-01/02) */
?>
<h1>Mon compte</h1>

<?php if ($erreurs !== []): ?>
    <ul role="alert">
        <?php foreach ($erreurs as $erreur): ?><li>✘ <?= e($erreur) ?></li><?php endforeach; ?>
    </ul>
<?php endif; ?>

<h2>Mes informations</h2>
<form method="post" action="/compte">
    <?= csrf_field() ?>
    <fieldset>
        <legend>État civil et coordonnées (l'email reste unique — RB-01)</legend>
        <p><label for="nom">Nom *</label><input id="nom" name="nom" maxlength="100" required value="<?= e($client['nom'] ?? '') ?>"></p>
        <p><label for="prenom">Prénom *</label><input id="prenom" name="prenom" maxlength="100" required value="<?= e($client['prenom'] ?? '') ?>"></p>
        <p><label for="email">Email *</label><input type="email" id="email" name="email" maxlength="190" required value="<?= e($client['email'] ?? '') ?>"></p>
        <p><label for="telephone">Téléphone</label><input id="telephone" name="telephone" maxlength="20" value="<?= e($client['telephone'] ?? '') ?>"></p>
        <p><label for="adresse">Adresse de livraison</label><input id="adresse" name="adresse" maxlength="255" value="<?= e($client['adresse_livraison'] ?? '') ?>"></p>
        <p><label for="code_postal">Code postal</label><input id="code_postal" name="code_postal" maxlength="10" value="<?= e($client['code_postal'] ?? '') ?>"></p>
        <p><label for="ville">Ville</label><input id="ville" name="ville" maxlength="100" value="<?= e($client['ville'] ?? '') ?>"></p>
        <p><button type="submit">Enregistrer</button></p>
    </fieldset>
</form>

<h2>Changer mon mot de passe</h2>
<form method="post" action="/compte/mot-de-passe">
    <?= csrf_field() ?>
    <fieldset>
        <legend>L'ancien mot de passe est exigé ; le nouveau est re-haché (RB-12)</legend>
        <p><label for="ancien">Ancien mot de passe *</label><input type="password" id="ancien" name="ancien_mot_de_passe" required></p>
        <p><label for="nouveau">Nouveau mot de passe * (8 min.)</label><input type="password" id="nouveau" name="nouveau_mot_de_passe" minlength="8" required></p>
        <p><label for="confirmation">Confirmation *</label><input type="password" id="confirmation" name="confirmation" minlength="8" required></p>
        <p><button type="submit">Mettre à jour</button></p>
    </fieldset>
</form>

<p><a href="/mes-commandes">Voir mes commandes →</a></p>
