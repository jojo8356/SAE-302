<?php

declare(strict_types=1);

/** @var array{lignes: list<array<string,mixed>>, page: int, pages: int, total: int} $resultat @var list<array<string,mixed>> $categories @var string $q @var int|null $cat (EF-ADM-01, RB-19) */
?>
<h1>Produits</h1>
<p><a href="/admin/produit/nouveau">+ Nouveau produit</a></p>

<form method="get" action="/admin/produits">
    <p>
        <label for="q">Recherche :</label>
        <input type="search" id="q" name="q" maxlength="120" value="<?= e($q) ?>">
        <label for="cat">Catégorie :</label>
        <select id="cat" name="cat">
            <option value="">Toutes</option>
            <?php foreach ($categories as $categorie): ?>
                <option value="<?= (int) $categorie['id_categorie'] ?>" <?= (int) $cat === (int) $categorie['id_categorie'] ? 'selected' : '' ?>>
                    <?= e($categorie['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filtrer</button>
    </p>
</form>

<p><?= (int) $resultat['total'] ?> produit(s) — page <?= (int) $resultat['page'] ?> / <?= (int) $resultat['pages'] ?></p>

<table>
    <caption>Le back-office voit aussi les produits masqués (RB-19)</caption>
    <thead>
    <tr>
        <th scope="col">Réf.</th><th scope="col">Nom</th><th scope="col">Catégorie</th>
        <th scope="col">Prix HT</th><th scope="col">TVA</th><th scope="col">Prix TTC (RB-16)</th>
        <th scope="col">Stock</th><th scope="col">Visible</th><th scope="col">Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($resultat['lignes'] as $produit): ?>
        <tr>
            <td><?= e($produit['reference']) ?></td>
            <td><a href="/admin/produit/<?= (int) $produit['id_produit'] ?>/modification"><?= e($produit['nom']) ?></a></td>
            <td><?= e($produit['categorie']) ?></td>
            <td><?= euros($produit['prix_ht']) ?></td>
            <td><?= e(number_format((float) $produit['tva'], 2, ',', '')) ?> %</td>
            <td><?= euros($produit['prix_ttc']) ?></td>
            <td><?= (int) $produit['stock'] ?> <small>[<?= e(etat_libelle($produit['etat_stock'])) ?>]</small></td>
            <td><?= (int) $produit['visible'] === 1 ? '✔ oui' : '✘ masqué' ?></td>
            <td>
                <a href="/admin/produit/<?= (int) $produit['id_produit'] ?>/modification">Modifier</a> ·
                <form method="post" action="/admin/produits/<?= (int) $produit['id_produit'] ?>/suppression" style="display:inline">
                    <?= csrf_field() ?>
                    <button type="submit">Supprimer</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($resultat['pages'] > 1): ?>
    <p>
        <?php if ($resultat['page'] > 1): ?>
            <a href="/admin/produits?q=<?= e($q) ?>&amp;cat=<?= (int) ($cat ?? 0) ?>&amp;page=<?= (int) $resultat['page'] - 1 ?>">← Précédente</a>
        <?php endif; ?>
        <?php if ($resultat['page'] < $resultat['pages']): ?>
            <a href="/admin/produits?q=<?= e($q) ?>&amp;cat=<?= (int) ($cat ?? 0) ?>&amp;page=<?= (int) $resultat['page'] + 1 ?>">Suivante →</a>
        <?php endif; ?>
    </p>
<?php endif; ?>
