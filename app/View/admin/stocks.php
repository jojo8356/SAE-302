<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $etats @var float $valeur_inventaire @var list<string> $erreurs (UC-12, EF-ADM-04/05, RB-03) */
?>
<h1>Gestion des stocks</h1>

<?php if ($erreurs !== []): ?>
    <ul role="alert">
        <?php foreach ($erreurs as $erreur): ?><li>✘ <?= e($erreur) ?></li><?php endforeach; ?>
    </ul>
<?php endif; ?>

<p>Valeur d'inventaire (prix HT × stock) : <strong><?= euros($valeur_inventaire) ?></strong></p>

<table>
    <caption>v_etat_stock — le moteur refuse tout stock négatif (RB-03) et journalise chaque mouvement avec son auteur</caption>
    <thead>
    <tr>
        <th scope="col">Réf.</th><th scope="col">Produit</th><th scope="col">Stock</th>
        <th scope="col">Seuil</th><th scope="col">État</th><th scope="col">Ajustement (EF-ADM-04)</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($etats as $etat): ?>
        <tr>
            <td><?= e($etat['reference']) ?></td>
            <td><?= e($etat['nom']) ?></td>
            <td><?= (int) $etat['stock'] ?></td>
            <td><?= (int) $etat['seuil_alerte'] ?></td>
            <td><?= e(etat_libelle($etat['etat'])) ?></td>
            <td>
                <form method="post" action="/admin/stocks">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id_produit" value="<?= (int) $etat['id_produit'] ?>">
                    <label>
                        <select name="mode">
                            <option value="SET">Fixer à</option>
                            <option value="DELTA">Varier de</option>
                        </select>
                        <input type="number" name="quantite" value="0">
                    </label>
                    <label>Motif requis :
                        <input type="text" name="motif" maxlength="200" required placeholder="réception fournisseur, inventaire, casse…">
                    </label>
                    <button type="submit">Appliquer</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
