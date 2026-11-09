<?php

declare(strict_types=1);

/** @var list<string> $erreurs @var array<string,string> $valeurs */
$valeurs = array_merge(['nom' => '', 'email' => '', 'message' => ''], $valeurs ?? []);
?>
<div class="auth">
    <header class="entete-page">
        <div>
            <h1>Nous contacter</h1>
            <p class="chapo">Formulaire de démonstration : le message est enregistré localement, sans envoi d’e-mail ni réponse garantie.</p>
        </div>
    </header>

    <?php if (($erreurs ?? []) !== []): ?>
        <div class="message message--erreur" role="alert">
            <?= icone('alerte', 20) ?>
            <ul>
                <?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <p>Consultez notre <a href="/aide">aide</a> ou gérez vos informations dans <a href="/compte">Mon compte</a>.</p>
    <form method="post" action="/contact">
        <?= csrf_field() ?>
        <fieldset>
            <legend class="visuellement-cache">Votre message</legend>
            <h2 class="auth__titre">Votre message</h2>

            <p>
                <label for="nom">Nom et prénom <span class="texte-aide">(obligatoire)</span></label>
                <input type="text" id="nom" name="nom" maxlength="120" required autocomplete="name"
                       value="<?= e($valeurs['nom']) ?>" <?php if (($erreurs ?? []) !== []) { echo 'aria-invalid="true"'; } ?>>
            </p>

            <p>
                <label for="email">Email <span class="texte-aide">(obligatoire)</span></label>
                <input type="email" id="email" name="email" maxlength="190" required autocomplete="email"
                       value="<?= e($valeurs['email']) ?>">
            </p>

            <p>
                <label for="message">Message <span class="texte-aide">(10 caractères minimum)</span></label>
                <textarea id="message" name="message" rows="6" maxlength="2000" required
                          data-compteur="2000"><?= e($valeurs['message']) ?></textarea>
            </p>

            <p class="texte-aide">
                Le nom, l’e-mail et le message sont enregistrés dans un journal local. N’envoyez pas de données sensibles.
                Consultez la <a href="/confidentialite">politique de confidentialité</a> et les limites de conservation.
            </p>

            <div class="actions-formulaire">
                <button type="submit" class="btn--acheter">Enregistrer le message de démonstration</button>
                <a class="btn btn--secondaire" href="/">Retour à l'accueil</a>
            </div>
        </fieldset>
    </form>
</div>
