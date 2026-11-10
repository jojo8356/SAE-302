<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $categories @var list<string> $erreurs (UC-11, EF-ADM-03, RB-07/14) */
?>
<h1>Catégories</h1>

<?php if ($erreurs !== []): ?>
    <ul role="alert">
        <?php foreach ($erreurs as $erreur): ?><li>✘ <?= e($erreur) ?></li><?php endforeach; ?>
    </ul>
<?php endif; ?>

<h2>Créer une catégorie</h2>
<form method="post" class="formulaire-creation-categorie" action="/admin/categories">
    <?= csrf_field() ?>
    <fieldset>
        <legend>Nouvelle catégorie</legend>
        <p class="aide">Le slug est généré automatiquement si laissé vide ;
            un nom (ou un slug) déjà utilisé est refusé, sans toucher à la catégorie existante.</p>
        <p><label for="nom">Nom *</label>
            <input id="nom" name="nom" maxlength="100" required></p>
        <p><label for="slug">Slug (optionnel)</label>
            <input id="slug" name="slug" maxlength="120"></p>
        <p><label for="description">Description</label>
            <input id="description" name="description" maxlength="500"></p>
        <p><button type="submit">Créer</button></p>
    </fieldset>
</form>

<h2>Catégories existantes</h2>
<div class="tableau-defilant tableau-defilant--admin" role="region" tabindex="0" aria-label="Tableau de données">
<table class="tableau-categories">
    <colgroup><col class="col-edition"><col class="col-slug"><col class="col-produits"><col class="col-description"><col class="col-actions"></colgroup>
    <thead><tr><th scope="col">Nom</th><th scope="col">Slug</th><th scope="col">Produits visibles</th><th scope="col">Description</th><th scope="col">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($categories as $categorie): ?>
        <tr>
            <td data-label="Nom">
                <form method="post" action="/admin/categories/<?= (int) $categorie['id_categorie'] ?>/modification">
                    <?= csrf_field() ?>
                    <input name="nom" aria-label="Nom de la catégorie" maxlength="100" required value="<?= e($categorie['nom']) ?>">
                    <input name="slug" aria-label="Slug de la catégorie" maxlength="120" value="<?= e($categorie['slug']) ?>" placeholder="slug (auto si vide)">
                    <input name="description" aria-label="Description de la catégorie" maxlength="500" value="<?= e($categorie['description']) ?>">
                    <button type="submit">Enregistrer</button>
                </form>
            </td>
            <td data-label="Slug"><?= e($categorie['slug']) ?></td>
            <td data-label="Produits visibles"><?= (int) $categorie['nb_produits'] ?></td>
            <td data-label="Description"><?= e($categorie['description']) ?></td>
            <td data-label="Actions">
                <form method="get" action="/admin/categories/<?= (int) $categorie['id_categorie'] ?>/suppression/confirmation">
                    <button type="submit" class="bouton-danger">Supprimer</button>
                </form>
                <small>Suppression refusée si des produits y sont rattachés.</small>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
