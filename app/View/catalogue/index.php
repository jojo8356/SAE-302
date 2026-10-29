<?php

declare(strict_types=1);

/**
 * @var array{lignes: list<array<string,mixed>>, total: int, page: int, pages: int} $resultat
 * @var array{mot_cle: string, id_categorie: ?int, prix_min: ?float, prix_max: ?float, en_stock: bool, tri: string} $criteres
 * @var list<array<string,mixed>> $categories
 * @var array<string,string> $tris
 * @var array<string,mixed> $parametres_url
 * @var bool $est_recherche
 */
$qs = static function (int $page) use ($parametres_url): string {
    $parametres = array_merge($parametres_url, ['page' => $page]);
    $parametres = array_filter($parametres, static fn (mixed $v) => $v !== null && $v !== '' && $v !== 0);

    return e('/catalogue?' . http_build_query($parametres));
};

$lienSans = static function (string $cle) use ($parametres_url): string {
    unset($parametres_url[$cle]);

    return e('/catalogue?' . http_build_query($parametres_url));
};
?>
<header class="entete-page">
    <div>
        <h1><?php if ($est_recherche) { echo 'Recherche'; } else { echo 'Catalogue'; } ?></h1>
        <p class="chapo">
            <?php if ((int) $resultat['total'] > 1): ?>
                <?= (int) $resultat['total'] ?> produits trouvés · page <?= (int) $resultat['page'] ?> / <?= (int) $resultat['pages'] ?>
            <?php else: ?>
                <?= (int) $resultat['total'] ?> produit trouvé · page <?= (int) $resultat['page'] ?> / <?= (int) $resultat['pages'] ?>
            <?php endif; ?>
        </p>
    </div>
</header>

<div class="catalogue">
    <aside class="panneau-filtres" aria-label="Filtres du catalogue">
        <form method="get" action="<?php if ($est_recherche) { echo '/recherche'; } else { echo '/catalogue'; } ?>" data-filtres>
            <fieldset>
                <legend>Filtres</legend>

                <p>
                    <label for="q">Recherche</label>
                    <input type="search" id="q" name="q" maxlength="120" value="<?= e($criteres['mot_cle']) ?>"
                           placeholder="nom, description, référence">
                </p>

                <p>
                    <label for="cat">Catégorie</label>
                    <select id="cat" name="cat">
                        <option value="">Toutes</option>
                        <?php foreach ($categories as $categorie): ?>
                            <option value="<?= (int) $categorie['id_categorie'] ?>"
                                <?php if ((int) $criteres['id_categorie'] === (int) $categorie['id_categorie']) { echo 'selected'; } else { echo ''; } ?>>
                                <?= e($categorie['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <label for="prix_min">Prix TTC minimum (€)</label>
                    <input type="number" id="prix_min" name="prix_min" min="0" step="0.01" value="<?= e($criteres['prix_min']) ?>">
                </p>
                <p>
                    <label for="prix_max">Prix TTC maximum (€)</label>
                    <input type="number" id="prix_max" name="prix_max" min="0" step="0.01" value="<?= e($criteres['prix_max']) ?>">
                </p>

                <p>
                    <label for="stock">
                        <input type="checkbox" id="stock" name="stock" value="1" <?php if ($criteres['en_stock']) { echo 'checked'; } else { echo ''; } ?>>
                        Uniquement en stock
                    </label>
                </p>

                <p>
                    <label for="tri">Trier par</label>
                    <span class="texte-aide">Les produits en rupture sont affichés en dernier.</span>
                    <select id="tri" name="tri">
                        <?php foreach ($tris as $cle => $libelle): ?>
                            <option value="<?= e($cle) ?>" <?php if ($criteres['tri'] === $cle) { echo 'selected'; } else { echo ''; } ?>><?= e($libelle) ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <button type="submit" class="btn--acheter">Appliquer les filtres</button>
                </p>
                <p class="texte-aide">Les filtres se conservent dans l'URL : l'adresse est partageable.</p>
            </fieldset>
        </form>
    </aside>

    <div>
        <?php
        $actifs = [];
        if ($criteres['mot_cle'] !== '') {
            $actifs[] = ['q', 'Recherche : ' . $criteres['mot_cle']];
        }
        if ($criteres['id_categorie'] !== null) {
            foreach ($categories as $categorie) {
                if ((int) $categorie['id_categorie'] === (int) $criteres['id_categorie']) {
                    $actifs[] = ['cat', 'Catégorie : ' . $categorie['nom']];
                }
            }
        }
        if ($criteres['prix_min'] !== null) {
            $actifs[] = ['prix_min', 'Prix ≥ ' . euros($criteres['prix_min'])];
        }
        if ($criteres['prix_max'] !== null) {
            $actifs[] = ['prix_max', 'Prix ≤ ' . euros($criteres['prix_max'])];
        }
        if ($criteres['en_stock']) {
            $actifs[] = ['stock', 'En stock uniquement'];
        }
        ?>
        <?php if ($actifs !== []): ?>
            <ul class="pastilles" aria-label="Filtres actifs">
                <?php foreach ($actifs as [$cle, $libelle]): ?>
                    <li class="pastille">
                        <?= e($libelle) ?>
                        <a href="<?= $lienSans($cle) ?>" aria-label="Retirer le filtre <?= e($libelle) ?>">✕</a>
                    </li>
                <?php endforeach; ?>
                <li class="pastille"><a href="/catalogue">Tout effacer</a></li>
            </ul>
        <?php endif; ?>

        <?php if ($resultat['lignes'] === []): ?>
            <div class="etat-vide">
                <p><strong>Aucun produit ne correspond à ces critères.</strong></p>
                <p>Essayez d'élargir la recherche, ou repartez du catalogue complet.</p>
                <p><a class="btn btn--secondaire" href="/catalogue">Voir tout le catalogue</a></p>
            </div>
        <?php else: ?>
            <div class="grille grille--produits">
                <?php foreach ($resultat['lignes'] as $produit): ?>
                    <article class="carte carte-produit">
                        <?php $visuel = visuel_produit($produit['image_url'] ?? null, new \App\View\Image\AlternativeDecorative()); ?>
                        <?php if ($visuel !== null): ?>
                            <div class="carte-produit__media"><?= $visuel // HTML-SAFE : URL validée et échappée par le helper ?></div>
                        <?php else: ?>
                            <div class="carte-produit__media carte-produit__media--repli" aria-hidden="true"><?= e(initiale_produit((string) $produit['nom'])) ?></div>
                        <?php endif; ?>
                        <p class="carte-produit__categorie"><?= e($produit['categorie']) ?></p>
                        <h2 class="carte-produit__nom"><?= e($produit['nom']) ?></h2>
                        <p class="texte-aide">Réf. <?= e($produit['reference']) ?></p>
                        <p class="carte-produit__prix"><?= euros($produit['prix_ttc']) ?> <small>TTC</small></p>
                        <div class="carte-produit__pied">
                            <?= badge_stock((int) $produit['stock']) ?>
                            <a class="btn btn--secondaire btn--sm" href="/produit/<?= e($produit['slug']) ?>" aria-label="Voir la fiche de <?= e($produit['nom']) ?>">Voir la fiche</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($resultat['pages'] > 1): ?>
            <nav class="pagination" aria-label="Pagination">
                <?php if ($resultat['page'] > 1): ?>
                    <a href="<?= $qs($resultat['page'] - 1) ?>" rel="prev">← Précédente</a>
                <?php endif; ?>
                <?php
                $debut = max(1, $resultat['page'] - 2);
                $fin = min($resultat['pages'], $resultat['page'] + 2);
                for ($p = $debut; $p <= $fin; ++$p):
                    ?>
                    <?php if ($p === (int) $resultat['page']): ?>
                        <span aria-current="page"><?= (int) $p ?></span>
                    <?php else: ?>
                        <a href="<?= $qs($p) ?>"><?= (int) $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($resultat['page'] < $resultat['pages']): ?>
                    <a href="<?= $qs($resultat['page'] + 1) ?>" rel="next">Suivante →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>
