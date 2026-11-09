<?php

declare(strict_types=1);

/** @var list<string> $erreurs (UC-04, EF-VIS-06, RB-01/RB-12) */
?>
<div class="auth">
    <h1>Créer un compte</h1>
    <p class="chapo">Un compte permet de simuler des commandes, de suivre leur statut et de les annuler avant expédition.</p>

    <?php if ($erreurs !== []): ?>
        <div class="message message--erreur" role="alert">
            <?= icone('alerte', 20) ?>
            <ul>
                <?php foreach ($erreurs as $erreur): ?><li>✘ <?= e($erreur) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <p class="texte-aide">Les données saisies sont enregistrées pour le compte de démonstration. Consultez la <a href="/confidentialite">politique de confidentialité</a>.</p>
    <form method="post" action="/inscription">
        <?= csrf_field() ?>
        <fieldset>
            <legend class="visuellement-cache">Vos informations</legend>
            <h2 class="auth__titre">Vos informations</h2>
            <p><label for="prenom">Prénom *</label><input type="text" id="prenom" name="prenom" maxlength="100" required value="<?= e($valeurs['prenom'] ?? '') ?>" autocomplete="given-name"></p>
            <p><label for="nom">Nom *</label><input type="text" id="nom" name="nom" maxlength="100" required value="<?= e($valeurs['nom'] ?? '') ?>" autocomplete="family-name"></p>
            <p><label for="email">Email *</label><input type="email" id="email" name="email" maxlength="190" required value="<?= e($valeurs['email'] ?? '') ?>" autocomplete="email"></p>
            <p>
                <label for="mot_de_passe">Mot de passe *</label>
                <input type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="new-password" minlength="8">
                <span class="texte-aide">8 caractères minimum, avec au moins une lettre et un chiffre.</span>
            </p>
            <p><label for="confirmation">Confirmation du mot de passe *</label><input type="password" id="confirmation" name="confirmation" required autocomplete="new-password" minlength="8"></p>
            <p>
                <label for="cgv">
                    <input type="checkbox" id="cgv" name="cgv" value="1" required<?php if (!empty($valeurs['cgv'])) { echo ' checked'; } ?>>
                    J'accepte les <a href="/cgv">conditions générales de vente</a> *
                </label>
            </p>
            <p><button type="submit" class="btn--acheter">Créer mon compte</button></p>
        </fieldset>
    </form>
    <p>Déjà client ? <a href="/connexion">Connectez-vous</a>.</p>
</div>
