<?php

declare(strict_types=1);

/** @var list<string> $erreurs @var array<string,string> $valeurs */
$valeurs = array_merge(['nom' => '', 'email' => '', 'message' => ''], $valeurs ?? []);
?>
<div class="auth">
    <header class="entete-page">
        <div>
            <h1>Nous contacter</h1>
            <p class="chapo">Une question sur une commande, un produit ou le projet ? Réponse sous 2 jours ouvrés.</p>
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

    <form method="post" action="/contact">
        <?= csrf_field() ?>
        <fieldset>
            <legend>Votre message</legend>

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
                Les informations transmises servent uniquement à répondre à votre message.
                Aucune donnée n'est conservée en base par cette maquette pédagogique.
            </p>

            <div class="actions-formulaire">
                <button type="submit" class="btn--acheter">Envoyer le message</button>
                <a class="btn btn--secondaire" href="/">Retour à l'accueil</a>
            </div>
        </fieldset>
    </form>
</div>
