<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $categories @var list<array<string,mixed>> $nouveautes */
?>
<section class="hero" aria-labelledby="titre-hero">
    <div>
        <p class="pastille"><?= icone('eclair', 16) ?> Bienvenue chez MiniShop · prix TTC</p>
        <h1 id="titre-hero">Le bon matériel, au bon prix, sans surprise.</h1>
        <p>Ordinateurs, audio, accessoires et objets connectés. Le prix affiché est le prix payé :
            TVA incluse, frais de port annoncés <strong>avant</strong> la validation.</p>
        <div class="hero__actions">
            <a class="btn btn--acheter" href="/catalogue">Voir le catalogue</a>
            <a class="btn btn--secondaire" href="/categories">Parcourir les catégories</a>
        </div>
    </div>
    <?php // Illustration réelle (JPEG 960×524, 63 Ko) : width/height explicites pour
          // éviter tout décalage de mise en page pendant le chargement (CLS). ?>
    <img class="hero__illustration" src="/img/illustration-achat.jpg"
         alt="Ordinateur portable, casque audio, enceinte connectée et colis prêt à être expédié"
         width="960" height="524" loading="eager" decoding="async" fetchpriority="high">
</section>

<section aria-labelledby="titre-categories">
    <h2 id="titre-categories">Nos catégories</h2>
    <div class="grille grille--categories">
        <?php foreach ($categories as $categorie): ?>
            <a class="carte carte--lien carte-categorie" href="/catalogue?cat=<?= (int) $categorie['id_categorie'] ?>">
                <?php // Icône décorative (Lucide, inlinée) : le nom de la catégorie suit
                      // dans le titre, elle est donc masquée aux lecteurs d'écran. ?>
                <span class="carte-categorie__icone"><?= icone_categorie((string) $categorie['slug']) ?></span>
                <div>
                    <h3><?= e($categorie['nom']) ?></h3>
                    <p class="texte-aide"><?= e($categorie['description']) ?></p>
                </div>
                <p class="carte-categorie__compte">
                    <?= (int) $categorie['nb_produits'] ?> produit<?php if ($categorie['nb_produits'] > 1) { echo 's'; } else { echo ''; } ?>
                    disponibles
                </p>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section aria-labelledby="titre-nouveautes">
    <h2 id="titre-nouveautes">Derniers ajouts</h2>
    <div class="grille grille--produits">
        <?php foreach ($nouveautes as $produit): ?>
            <article class="carte carte-produit">
                <?php $visuel = visuel_produit($produit['image_url'] ?? null, (string) $produit['nom']); ?>
                <?php if ($visuel !== null): ?>
                    <div class="carte-produit__media"><?= $visuel // HTML-SAFE : URL validée et échappée par le helper ?></div>
                <?php else: ?>
                    <div class="carte-produit__media carte-produit__media--repli" aria-hidden="true"><?= e(initiale_produit((string) $produit['nom'])) ?></div>
                <?php endif; ?>
                <p class="carte-produit__categorie"><?= e($produit['categorie']) ?></p>
                <h3 class="carte-produit__nom"><?= e($produit['nom']) ?></h3>
                <p class="carte-produit__prix"><?= euros($produit['prix_ttc']) ?> <small>TTC</small></p>
                <div class="carte-produit__pied">
                    <?= badge_stock((int) $produit['stock']) ?>
                    <a class="btn btn--secondaire btn--sm" href="/produit/<?= e($produit['slug']) ?>" aria-label="Voir la fiche de <?= e($produit['nom']) ?>">Voir la fiche</a>
                </div>
                <?php
                // DATETIME enregistré, sans inventer une date pour une valeur absente ou invalide.
                $dateBrute = (string) ($produit['date_creation'] ?? '');
                $dateAjout = false;
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $dateBrute) === 1) {
                    $dateAjout = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $dateBrute);
                }
                ?>
                <p class="carte-produit__date">
                    <?php if ($dateAjout !== false && $dateAjout->format('Y-m-d H:i:s') === $dateBrute): ?>
                        Ajouté le <time datetime="<?= e($dateAjout->format('Y-m-d\TH:i:s')) ?>"><?= e($dateAjout->format('d/m/Y')) ?> à <?= e($dateAjout->format('H\hi')) ?></time>
                    <?php else: ?>
                        Date d’ajout indisponible
                    <?php endif; ?>
                </p>
            </article>
        <?php endforeach; ?>
    </div>
    <p class="marge-haut"><a href="/catalogue">Voir tout le catalogue →</a></p>
</section>

<section class="reassurance" aria-label="Nos engagements">
    <div class="reassurance__item">
        <?= icone('camion', 24) ?>
        <div><strong>Port annoncé avant validation</strong><span>Frais simulés affichés dans le panier.</span></div>
    </div>
    <div class="reassurance__item">
        <?= icone('retour', 24) ?>
        <div><strong>Annulation possible</strong><span>Tant que la commande n'est pas expédiée.</span></div>
    </div>
    <div class="reassurance__item">
        <?= icone('cadenas', 24) ?>
        <div><strong>Paiement simulé</strong><span>Projet pédagogique : aucune carte demandée.</span></div>
    </div>
</section>
