<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $admins @var list<string> $erreurs (EF-ADM-10, RB-12/13 — SUPER uniquement) */
?>
<h1>Équipe d'administration</h1>
<p>Seul le rôle <strong>SUPER</strong> accède à cette page. Les comptes administrateurs ne
sont jamais supprimés (piste conservée), seulement bloqués. Le back-office ne crée
jamais de compte client.</p>

<?php if ($erreurs !== []): ?>
    <ul role="alert">
        <?php foreach ($erreurs as $erreur): ?><li>✘ <?= e($erreur) ?></li><?php endforeach; ?>
    </ul>
<?php endif; ?>

<h2>Créer un compte administrateur</h2>
<form method="post" action="/admin/equipe">
    <?= csrf_field() ?>
    <fieldset>
        <legend>Mot de passe haché (bcrypt) à la création</legend>
        <p><label for="nom">Nom *</label><input id="nom" name="nom" maxlength="100" required></p>
        <p><label for="prenom">Prénom *</label><input id="prenom" name="prenom" maxlength="100" required></p>
        <p><label for="email">Email *</label><input type="email" id="email" name="email" maxlength="190" required></p>
        <p><label for="mot_de_passe">Mot de passe * (8 min.)</label><input type="password" id="mot_de_passe" name="mot_de_passe" minlength="8" required></p>
        <p><label for="role">Rôle :</label>
            <select id="role" name="role">
                <option value="GESTIONNAIRE">Gestionnaire</option>
                <option value="SUPER">Super-administrateur</option>
            </select></p>
        <p><button type="submit">Créer</button></p>
    </fieldset>
</form>

<h2>Comptes existants</h2>
<div class="tableau-defilant tableau-defilant--admin" role="region" tabindex="0" aria-label="Tableau de données">
<table>
    <thead><tr><th scope="col">Nom</th><th scope="col">Email</th><th scope="col">Rôle</th><th scope="col">Actif</th><th scope="col">Dernière connexion</th><th scope="col">Action</th></tr></thead>
    <tbody>
    <?php foreach ($admins as $admin): ?>
        <tr>
            <td data-label="Nom"><?= e($admin['prenom'] . ' ' . $admin['nom']) ?></td>
            <td data-label="Email"><?= e($admin['email']) ?></td>
            <td data-label="Rôle"><?= e($admin['role']) ?></td>
            <td data-label="Actif"><?php if (!empty($admin['actif'])) { echo '✔'; } else { echo '✘ bloqué'; } ?></td>
            <td data-label="Dernière connexion"><?= e(date_fr($admin['derniere_connexion'] ?? null, true)) ?></td>
            <td data-label="Action">
                <form method="post" action="/admin/equipe/<?= (int) $admin['id_admin'] ?>/basculer">
                    <?= csrf_field() ?>
                    <button type="submit"><?php if (!empty($admin['actif'])) { echo 'Bloquer'; } else { echo 'Réactiver'; } ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
