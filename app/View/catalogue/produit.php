<?php

declare(strict_types=1);

/** @var array<string,mixed> $produit @var array<string,mixed>|null $categorie @var string $etat_stock (UC-03, EF-VIS-04) */
?>
<article>
    <h1><?= e($produit['nom']) ?></h1>
    <p>
        Catégorie :
        <?php if ($categorie !== null): ?>
            <a href="/catalogue?cat=<?= (int) $categorie['id_categorie'] ?>"><?= e($categorie['nom']) ?></a>
        <?php else: ?>—<?php endif; ?>
        · Référence : <strong><?= e($produit['reference']) ?></strong>
    </p>

    <h2>Prix</h2>
    <p>
        <strong><?= euros($produit['prix_ttc']) ?> TTC</strong>
        (soit <?= euros($produit['prix_ht']) ?> HT + TVA <?= e(number_format((float) $produit['tva'], 2, ',', '')) ?> %,
        prix TTC calculé par le moteur — RB-16)
    </p>

    <h2>Disponibilité</h2>
    <p>
        <?php if ((int) $produit['stock'] > 0): ?>
            En stock : <strong><?= (int) $produit['stock'] ?> unité<?= (int) $produit['stock'] > 1 ? 's' : '' ?></strong>
            [<?= e(etat_libelle($etat_stock)) ?>]
        <?php else: ?>
            <strong>Rupture de stock</strong> — revenez bientôt.
        <?php endif; ?>
    </p>

    <h2>Description</h2>
    <p><?= e($produit['description']) ?></p>

    <?php if ((int) $produit['stock'] > 0): ?>
        <h2>Ajouter au panier</h2>
        <form method="post" action="/panier/ajouter">
            <?= csrf_field() ?>
            <input type="hidden" name="id_produit" value="<?= (int) $produit['id_produit'] ?>">
            <p>
                <label for="quantite">Quantité :</label>
                <input type="number" id="quantite" name="quantite" min="1" max="<?= (int) $produit['stock'] ?>" value="1" required>
                <button type="submit">Ajouter au panier</button>
            </p>
            <p><small>La quantité est plafonnée côté serveur au stock disponible (RB-18) :
                <?= (int) $produit['stock'] ?> unité<?= (int) $produit['stock'] > 1 ? 's' : '' ?> maximum.</small></p>
        </form>
    <?php endif; ?>

    <p><a href="/catalogue">← Retour au catalogue</a></p>
</article>
