<?php

declare(strict_types=1);

/** @var array<string,mixed> $categorie (UC-11 — confirmation avant suppression définitive) */
?>
<h1>Supprimer cette catégorie ?</h1>

<p>Êtes-vous sûr de vouloir supprimer la catégorie
    <strong><?= e($categorie['nom']) ?></strong> ?</p>

<p><strong>Cette action est définitive.</strong> Si des produits sont encore
    rattachés à cette catégorie, la suppression sera refusée.</p>

<form method="post" action="/admin/categories/<?= (int) $categorie['id_categorie'] ?>/suppression">
    <?= csrf_field() ?>
    <p><button type="submit" class="bouton-danger">Oui, supprimer définitivement</button></p>
</form>

<p><a href="/admin/categories">Non, revenir à la liste des catégories</a></p>
