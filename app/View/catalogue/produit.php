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

    <h2>Caractéristiques</h2>
    <table class="tableau-caracteristiques">
        <tbody>
        <tr>
            <th scope="row">Prix HT</th>
            <td><?= euros($produit['prix_ht']) ?></td>
        </tr>
        <tr>
            <th scope="row">TVA</th>
            <td><?= e(number_format((float) $produit['tva'], 2, ',', '')) ?> %</td>
        </tr>
        <tr>
            <th scope="row">Prix TTC</th>
            <td><strong><?= euros($produit['prix_ttc']) ?></strong></td>
        </tr>
        <tr>
            <th scope="row">Disponibilité</th>
            <td>
                <?php if ((int) $produit['stock'] > 0): ?>
                    En stock : <strong><?= (int) $produit['stock'] ?> unité<?php if ((int) $produit['stock'] > 1) { echo 's'; } else { echo ''; } ?></strong>
                    [<?= e(etat_libelle($etat_stock)) ?>]
                <?php else: ?>
                    <strong>Rupture de stock</strong> — revenez bientôt.
                <?php endif; ?>
            </td>
        </tr>
        </tbody>
    </table>

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
                <?= (int) $produit['stock'] ?> unité<?php if ((int) $produit['stock'] > 1) { echo 's'; } else { echo ''; } ?> maximum.</small></p>
        </form>
    <?php endif; ?>

    <p><a href="/catalogue">← Retour au catalogue</a></p>
</article>
