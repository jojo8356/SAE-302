<?php

declare(strict_types=1);

/** @var array<string,mixed> $produit @var array<string,mixed>|null $categorie @var string $etat_stock (UC-03, EF-VIS-04) */
$stock = (int) $produit['stock'];
?>
<nav class="fil-ariane" aria-label="Fil d'Ariane">
    <a href="/">Accueil</a> ›
    <a href="/catalogue">Catalogue</a>
    <?php if ($categorie !== null): ?>› <a href="/catalogue?cat=<?= (int) $categorie['id_categorie'] ?>"><?= e($categorie['nom']) ?></a><?php endif; ?>
</nav>

<div class="fiche">
    <div>
        <?php $visuel = visuel_produit($produit['image_url'] ?? null, new \App\View\Image\AlternativeInformative('Visuel indicatif : ' . (string) $produit['nom']), 800, 600); ?>
        <?php if ($visuel !== null): ?>
            <div class="fiche__media"><?= $visuel // HTML-SAFE : URL validée et échappée par le helper ?></div>
        <?php else: ?>
            <div class="fiche__media fiche__media--repli" aria-hidden="true"><?= e(initiale_produit((string) $produit['nom'])) ?></div>
        <?php endif; ?>

        <h1><?= e($produit['nom']) ?></h1>
        <p class="fiche__meta">
            <?php if ($categorie !== null): ?>
                Catégorie : <a href="/catalogue?cat=<?= (int) $categorie['id_categorie'] ?>"><?= e($categorie['nom']) ?></a> ·
            <?php endif; ?>
            Référence : <strong><?= e($produit['reference']) ?></strong>
        </p>

        <h2>Description</h2>
        <p><?= e($produit['description']) ?></p>

        <h2>Caractéristiques</h2>
        <div class="tableau-defilant" role="region" tabindex="0" aria-label="Tableau de données — faites défiler horizontalement si nécessaire">
<table class="tableau-nombres">
            <caption>Informations produit</caption>
            <tbody>
            <tr><th scope="row">Prix HT</th><td><?= euros($produit['prix_ht']) ?></td></tr>
            <tr><th scope="row">Taux de TVA</th><td><?= e(number_format((float) $produit['tva'], 2, ',', '')) ?> %</td></tr>
            <tr><th scope="row">Prix TTC</th><td><?= euros($produit['prix_ttc']) ?></td></tr>
            <tr><th scope="row">Disponibilité</th><td><?= badge_etat((string) $etat_stock) ?></td></tr>
            </tbody>
        </table>
</div>
    </div>

    <aside class="achat carte" aria-label="Acheter ce produit">
        <p class="achat__prix"><?= euros($produit['prix_ttc']) ?></p>
        <p class="achat__tva">TTC — prix <?= euros($produit['prix_ht']) ?> HT + TVA <?= e(number_format((float) $produit['tva'], 2, ',', '')) ?> %</p>
        <p class="achat__stock"><?= badge_stock($stock) ?></p>

        <?php if ($stock > 0): ?>
            <form method="post" action="/panier/ajouter" data-panier>
                <?= csrf_field() ?>
                <input type="hidden" name="id_produit" value="<?= (int) $produit['id_produit'] ?>">
                <p class="champ-quantite">
                    <label for="quantite">Quantité</label>
                    <input type="number" id="quantite" name="quantite" min="1" max="<?= (int) $stock ?>" value="1" required>
                </p>
                <button type="submit" class="btn--acheter">Ajouter au panier</button>
                <p class="texte-aide">Quantité plafonnée côté serveur au stock disponible (<?= (int) $stock ?> unité<?php if ($stock > 1) { echo 's'; } else { echo ''; } ?>).</p>
            </form>
        <?php else: ?>
            <p class="message message--erreur">Rupture de stock — ce produit n'est pas commandable.</p>
            <button type="button" class="btn--acheter" disabled>Indisponible</button>
        <?php endif; ?>

        <ul class="liste-avantages">
            <li><?= icone('camion', 18) ?> Port calculé avant validation</li>
            <li><?= icone('retour', 18) ?> Annulation avant expédition</li>
            <li><?= icone('cadenas', 18) ?> Prix serveur, jamais côté navigateur</li>
        </ul>
    </aside>
</div>
