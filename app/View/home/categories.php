<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $categories */
?>
<header class="entete-page">
    <div>
        <h1>Nos catégories</h1>
        <p class="chapo">Chaque catégorie affiche le nombre de produits <strong>visibles</strong> en stock — le stock réel est la seule source de vérité.</p>
    </div>
</header>

<div class="grille grille--categories">
    <?php foreach ($categories as $categorie): ?>
        <a class="carte carte--lien carte-categorie" href="/catalogue?cat=<?= (int) $categorie['id_categorie'] ?>">
            <span class="carte-categorie__icone"><?= icone_categorie((string) $categorie['slug']) ?></span>
            <div>
                <h2><?= e($categorie['nom']) ?></h2>
                <p class="texte-aide"><?= e($categorie['description']) ?></p>
            </div>
            <p class="carte-categorie__compte">
                <?= (int) $categorie['nb_produits'] ?> produit<?php if ($categorie['nb_produits'] > 1) { echo 's'; } else { echo ''; } ?>
            </p>
        </a>
    <?php endforeach; ?>
</div>
