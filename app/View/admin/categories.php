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
<form method="post" action="/admin/categories">
    <?= csrf_field() ?>
    <fieldset>
        <legend>Le slug est généré automatiquement si laissé vide (RB-07) ;
            deux catégories de même nom sont fusionnées (UNIQUE)</legend>
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
<table>
    <thead><tr><th scope="col">Nom</th><th scope="col">Slug</th><th scope="col">Produits visibles</th><th scope="col">Description</th><th scope="col">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($categories as $categorie): ?>
        <tr>
            <td>
                <form method="post" action="/admin/categories/<?= (int) $categorie['id_categorie'] ?>/modification">
                    <?= csrf_field() ?>
                    <input name="nom" maxlength="100" required value="<?= e($categorie['nom']) ?>">
                    <input name="slug" maxlength="120" value="<?= e($categorie['slug']) ?>" placeholder="slug (auto si vide)">
                    <input name="description" maxlength="500" value="<?= e($categorie['description']) ?>">
                    <button type="submit">Enregistrer</button>
                </form>
            </td>
            <td><?= e($categorie['slug']) ?></td>
            <td><?= (int) $categorie['nb_produits'] ?></td>
            <td><?= e($categorie['description']) ?></td>
            <td>
                <form method="post" action="/admin/categories/<?= (int) $categorie['id_categorie'] ?>/suppression">
                    <?= csrf_field() ?>
                    <button type="submit">Supprimer</button>
                </form>
                <small>RB-14 : refusée si des produits y sont rattachés.</small>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
