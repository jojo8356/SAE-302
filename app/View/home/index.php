<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $categories @var list<array<string,mixed>> $nouveautes */
?>
<h1>Bienvenue chez MiniShop</h1>
<p>Catalogue en ligne : ordinateurs, audio, accessoires et objets connectés.
Les prix affichés sont <strong>TTC</strong>, la disponibilité est celle du stock en temps réel.</p>

<h2>Nos catégories</h2>
<ul>
    <?php foreach ($categories as $categorie): ?>
        <li>
            <a href="/catalogue?cat=<?= (int) $categorie['id_categorie'] ?>">
                <?= e($categorie['nom']) ?>
            </a>
            (<?= (int) $categorie['nb_produits'] ?> produit<?= $categorie['nb_produits'] > 1 ? 's' : '' ?>)
            — <?= e($categorie['description']) ?>
        </li>
    <?php endforeach; ?>
</ul>

<h2>Derniers ajouts</h2>
<ul>
    <?php foreach ($nouveautes as $produit): ?>
        <li>
            <a href="/produit/<?= e($produit['slug']) ?>"><?= e($produit['nom']) ?></a>
            — <?= euros($produit['prix_ttc']) ?> TTC
            (<?= e($produit['categorie']) ?>,
            <?= (int) $produit['stock'] > 0 ? (int) $produit['stock'] . ' en stock' : 'rupture' ?>)
        </li>
    <?php endforeach; ?>
</ul>
<p><a href="/catalogue">Voir tout le catalogue →</a></p>
