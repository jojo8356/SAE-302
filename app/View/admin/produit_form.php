<?php

declare(strict_types=1);

/**
 * @var array<string,mixed>|null $produit
 * @var list<array<string,mixed>> $categories
 * @var list<string> $erreurs
 * @var array<string,mixed> $valeurs
 * @var float $tva_defaut
 * (UC-10, EF-ADM-01/02, RB-02/07/14/15/16/17/19)
 */
$estCreation = $produit === null || ($produit === []);
$v = static fn (string $cle, string $defaut = '') => e($valeurs[$cle] ?? ($produit[$cle] ?? $defaut));
$tvaDefautFormatee = '20.00';
if ($tva_defaut !== null) {
    $tvaDefautFormatee = number_format((float) $tva_defaut, 2, '.', '');
}
$visibleDefaut = (int) ($produit['visible'] ?? 1) === 1;
if ($valeurs !== []) {
    $visibleDefaut = (int) ($valeurs['visible'] ?? 0) === 1;
}
?>
<h1><?php if ($estCreation) { echo 'Nouveau produit'; } else { echo 'Modifier « ' . e($produit['nom'] ?? '') . ' »'; } ?></h1>

<?php if ($erreurs !== []): ?>
    <ul role="alert">
        <?php foreach ($erreurs as $erreur): ?><li>✘ <?= e($erreur) ?></li><?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" action="<?php if ($estCreation) { echo '/admin/produit/nouveau'; } else { echo '/admin/produit/' . (int) $produit['id_produit'] . '/modification'; } ?>">
    <?= csrf_field() ?>
    <fieldset>
        <legend>Fiche produit (contraintes appliquées par le moteur : référence et slug uniques,
            prix HT &gt; 0 — RB-02 — stock ≥ 0, prix TTC dérivé automatiquement — RB-16)</legend>
        <p><label for="reference">Référence *</label>
            <input id="reference" name="reference" maxlength="30" required value="<?= $v('reference') ?>"></p>
        <p><label for="nom">Nom *</label>
            <input id="nom" name="nom" maxlength="150" required value="<?= $v('nom') ?>"></p>
        <p><label for="slug">Slug (URL) — vide = généré depuis le nom</label>
            <input id="slug" name="slug" maxlength="180" value="<?= $v('slug') ?>"></p>
        <p><label for="id_categorie">Catégorie *</label>
            <select id="id_categorie" name="id_categorie" required>
                <?php foreach ($categories as $categorie): ?>
                    <option value="<?= (int) $categorie['id_categorie'] ?>"
                        <?php if ((int) ($valeurs['id_categorie'] ?? $produit['id_categorie'] ?? 0) === (int) $categorie['id_categorie']) { echo 'selected'; } else { echo ''; } ?>>
                        <?= e($categorie['nom']) ?>
                    </option>
                <?php endforeach; ?>
            </select></p>
        <p><label for="description">Description</label>
            <textarea id="description" name="description" rows="4" cols="60"><?= $v('description') ?></textarea></p>
        <p><label for="prix_ht">Prix HT (€) *</label>
            <input type="number" id="prix_ht" name="prix_ht" min="0.01" step="0.01" required
                   value="<?= $v('prix_ht', '') ?>"></p>
        <p><label for="tva">TVA (%) *</label>
            <input type="number" id="tva" name="tva" min="0" max="100" step="0.01" required
                   value="<?= $v('tva', $tvaDefautFormatee) ?>"></p>
        <p><label for="stock">Stock *</label>
            <input type="number" id="stock" name="stock" min="0" required value="<?= $v('stock', '0') ?>"></p>
        <p><label for="seuil_alerte">Seuil d'alerte (RB-03) *</label>
            <input type="number" id="seuil_alerte" name="seuil_alerte" min="0" required value="<?= $v('seuil_alerte', '3') ?>"></p>
        <p><label for="image_url">Image (URL)</label>
            <input id="image_url" name="image_url" maxlength="255" value="<?= $v('image_url') ?>"></p>
        <p><label><input type="checkbox" name="visible" value="1"
                <?php if ($visibleDefaut) { echo 'checked'; } else { echo ''; } ?>>
            Visible au catalogue (RB-19)</label></p>
        <p><button type="submit"><?php if ($estCreation) { echo 'Créer le produit'; } else { echo 'Enregistrer'; } ?></button></p>
    </fieldset>
</form>

<?php if (!$estCreation): ?>
    <h2>Stock de ce produit</h2>
    <p>Le stock se pilote depuis <a href="/admin/stocks">la page de gestion des stocks</a>
        (écriture tracée avec motif obligatoire — EF-ADM-04).</p>
<?php endif; ?>

<p><a href="/admin/produits">← Retour à la liste</a></p>
