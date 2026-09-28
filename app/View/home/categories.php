<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $categories (EF-VIS-01) */
?>
<h1>Nos catégories</h1>
<p>Chaque catégorie affiche le nombre de produits actuellement visibles au catalogue (RB-08 : une catégorie peut être vide).</p>
<ul>
    <?php foreach ($categories as $categorie): ?>
        <li>
            <strong><a href="/catalogue?cat=<?= (int) $categorie['id_categorie'] ?>"><?= e($categorie['nom']) ?></a></strong>
            — <?= (int) $categorie['nb_produits'] ?> produit<?= $categorie['nb_produits'] > 1 ? 's' : '' ?>
            <br><small><?= e($categorie['description']) ?></small>
        </li>
    <?php endforeach; ?>
</ul>
