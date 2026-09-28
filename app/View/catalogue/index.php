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
    $parametres = array_filter($parametres, static fn ($v) => $v !== null && $v !== '' && $v !== 0);

    return e('/catalogue?' . http_build_query($parametres));
};
?>
<h1><?= $est_recherche ? 'Recherche' : 'Catalogue' ?></h1>

<form method="get" action="<?= $est_recherche ? '/recherche' : '/catalogue' ?>">
    <fieldset>
        <legend>Filtres (cumulables, conservés dans l'URL — EF-VIS-03)</legend>
        <p>
            <label for="q">Recherche (nom, description, référence) :</label>
            <input type="search" id="q" name="q" maxlength="120" value="<?= e($criteres['mot_cle']) ?>">
        </p>
        <p>
            <label for="cat">Catégorie :</label>
            <select id="cat" name="cat">
                <option value="">Toutes</option>
                <?php foreach ($categories as $categorie): ?>
                    <option value="<?= (int) $categorie['id_categorie'] ?>"
                        <?= (int) $criteres['id_categorie'] === (int) $categorie['id_categorie'] ? 'selected' : '' ?>>
                        <?= e($categorie['nom']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="prix_min">Prix TTC min (€) :</label>
            <input type="number" id="prix_min" name="prix_min" min="0" step="0.01" value="<?= e($criteres['prix_min']) ?>">
            <label for="prix_max">max :</label>
            <input type="number" id="prix_max" name="prix_max" min="0" step="0.01" value="<?= e($criteres['prix_max']) ?>">
        </p>
        <p>
            <label><input type="checkbox" name="stock" value="1" <?= $criteres['en_stock'] ? 'checked' : '' ?>> Uniquement en stock</label>
        </p>
        <p>
            <label for="tri">Trier par :</label>
            <select id="tri" name="tri">
                <?php foreach ($tris as $cle => $libelle): ?>
                    <option value="<?= e($cle) ?>" <?= $criteres['tri'] === $cle ? 'selected' : '' ?>><?= e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Appliquer</button>
        </p>
    </fieldset>
</form>

<p><?= (int) $resultat['total'] ?> produit<?= $resultat['total'] > 1 ? 's' : '' ?> trouvé<?= $resultat['total'] > 1 ? 's' : '' ?>
    — page <?= (int) $resultat['page'] ?> / <?= (int) $resultat['pages'] ?></p>

<ul>
    <?php foreach ($resultat['lignes'] as $produit): ?>
        <li>
            <strong><a href="/produit/<?= e($produit['slug']) ?>"><?= e($produit['nom']) ?></a></strong>
            — <?= euros($produit['prix_ttc']) ?> TTC
            (<?= e($produit['categorie']) ?>)
            <br>
            <small>Réf. <?= e($produit['reference']) ?> —
                <?= (int) $produit['stock'] > 0 ? (int) $produit['stock'] . ' en stock' : 'rupture de stock' ?>
                [<?= e(etat_libelle($produit['etat_stock'])) ?>]</small>
            <br><?= e(mb_substr((string) $produit['description'], 0, 120)) ?>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($resultat['pages'] > 1): ?>
    <nav aria-label="Pagination">
        <p>
            <?php if ($resultat['page'] > 1): ?>
                <a href="<?= $qs($resultat['page'] - 1) ?>">← Précédente</a>
            <?php endif; ?>
            <?php if ($resultat['page'] < $resultat['pages']): ?>
                <a href="<?= $qs($resultat['page'] + 1) ?>">Suivante →</a>
            <?php endif; ?>
        </p>
    </nav>
<?php endif; ?>
