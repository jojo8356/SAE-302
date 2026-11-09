<?php

declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $etats
 * @var string $filtre
 * @var array{RUPTURE:int, TRES_BAS:int, DISPONIBLE:int} $compteurs
 * @var float $valeur_inventaire
 * @var list<string> $erreurs (UC-12, EF-ADM-04/05, RB-03)
 */
$filtre = $filtre ?? '';
$compteurs = $compteurs ?? ['RUPTURE' => 0, 'TRES_BAS' => 0, 'DISPONIBLE' => 0];
?>
<header class="entete-page">
    <div>
        <h1>Gestion des stocks</h1>
        <p class="chapo">Valeur d'inventaire (prix HT × stock) : <strong><?= euros($valeur_inventaire) ?></strong>.
            Tout mouvement exige un motif et est journalisé avec son auteur.</p>
    </div>
</header>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur" role="alert">
        <?= icone('alerte', 20) ?>
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<nav class="pastilles" aria-label="Filtrer par état de stock">
    <a class="pastille<?php if ($filtre === '') { echo ' pastille--active'; } ?>" href="/admin/stocks">Tous</a>
    <a class="pastille<?php if ($filtre === 'RUPTURE') { echo ' pastille--active'; } ?>" href="/admin/stocks?etat=RUPTURE">Ruptures (<?= (int) $compteurs['RUPTURE'] ?>)</a>
    <a class="pastille<?php if ($filtre === 'TRES_BAS') { echo ' pastille--active'; } ?>" href="/admin/stocks?etat=TRES_BAS">Très bas (<?= (int) $compteurs['TRES_BAS'] ?>)</a>
    <a class="pastille<?php if ($filtre === 'DISPONIBLE') { echo ' pastille--active'; } ?>" href="/admin/stocks?etat=DISPONIBLE">Disponibles (<?= (int) $compteurs['DISPONIBLE'] ?>)</a>
</nav>

<?php if ($etats === []): ?>
    <div class="etat-vide">
        <p><strong>Aucun produit dans cet état.</strong></p>
        <p><a class="btn btn--secondaire" href="/admin/stocks">Voir tous les stocks</a></p>
    </div>
<?php else: ?>
    <div class="tableau-defilant tableau-defilant--admin" role="region" tabindex="0" aria-label="Tableau de données — faites défiler horizontalement si nécessaire">
        <table class="tableau-stocks tableau-nombres">
            <caption>Le stock ne peut jamais devenir négatif ; chaque mouvement est journalisé avec son auteur</caption>
            <thead>
            <tr>
                <th scope="col" class="reference">Réf.</th><th scope="col" class="produit">Produit</th><th scope="col">Stock</th>
                <th scope="col">Seuil</th><th scope="col">État</th><th scope="col">Ajustement</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($etats as $etat): ?>
                <tr>
                    <td class="reference" data-label="Référence"><?= e($etat['reference']) ?></td>
                    <td class="produit" data-label="Produit"><?= e($etat['nom']) ?></td>
                    <td data-label="Stock"><?= (int) $etat['stock'] ?></td>
                    <td data-label="Seuil"><?= (int) $etat['seuil_alerte'] ?></td>
                    <td data-label="État"><?= badge_etat((string) $etat['etat']) ?></td>
                    <td data-label="Ajustement">
                        <form method="post" action="/admin/stocks" class="form-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_produit" value="<?= (int) $etat['id_produit'] ?>">
                            <label class="visuellement-cache" for="mode-<?= (int) $etat['id_produit'] ?>">Mode</label>
                            <select id="mode-<?= (int) $etat['id_produit'] ?>" name="mode" data-largeur-courte>
                                <option value="DELTA" selected>Ajouter / retirer</option>
                                <option value="SET">Fixer à</option>
                            </select>
                            <label class="visuellement-cache" for="qte-<?= (int) $etat['id_produit'] ?>">Quantité</label>
                            <input type="number" id="qte-<?= (int) $etat['id_produit'] ?>" name="quantite" value="0" data-largeur-courte>
                            <label class="visuellement-cache" for="motif-<?= (int) $etat['id_produit'] ?>">Motif</label>
                            <input type="text" id="motif-<?= (int) $etat['id_produit'] ?>" name="motif" maxlength="200" required
                                   placeholder="réception fournisseur, inventaire, casse…">
                            <button type="submit" class="btn--secondaire btn--sm">Appliquer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
