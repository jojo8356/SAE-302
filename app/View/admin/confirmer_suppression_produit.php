<?php

declare(strict_types=1);

/** @var array<string,mixed> $produit (UC-10 — confirmation avant suppression définitive) */
?>
<h1>Supprimer ce produit ?</h1>

<p>Êtes-vous sûr de vouloir supprimer le produit
    <strong><?= e($produit['nom']) ?></strong>
    (réf. <?= e($produit['reference']) ?>) ?</p>

<p><strong>Cette action est définitive.</strong> Un produit jamais commandé
    disparaît du catalogue ; un produit déjà commandé est masqué, pour
    préserver l'historique des commandes (RB-09/17).</p>

<form method="post" action="/admin/produits/<?= (int) $produit['id_produit'] ?>/suppression">
    <?= csrf_field() ?>
    <p><button type="submit" class="bouton-danger">Oui, supprimer définitivement</button></p>
</form>

<p><a href="/admin/produits">Non, revenir à la liste des produits</a></p>
