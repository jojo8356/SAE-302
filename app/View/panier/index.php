<?php

declare(strict_types=1);

/**
 * @var array{articles: list<array<string,mixed>>, sous_total: float, frais_port: float, total: float, port_motif: string} $recap
 * (UC-06, EF-CLI-06, EF-VIS-09) — le panier vit en session serveur, le client
 * ne fournit jamais ni prix ni stock (EF-GEN-01). Prix unitaires TTC figés
 * depuis le produit, port calculé serveur.
 */
$plafonne = false;
foreach ($recap['articles'] as $ligne) {
    $plafonne = $plafonne || !empty($ligne['plafonne']);
}
?>
<h1>Mon panier</h1>

<?php if ($recap['articles'] === []): ?>
    <p>Votre panier est vide.</p>
    <p><a href="/catalogue">Parcourir le catalogue →</a></p>
<?php else: ?>
    <?php if ($plafonne): ?>
        <p role="alert">Certains articles ont été ramenés au stock disponible (RB-18) :
            la quantité maximale a été ajustée côté serveur.</p>
    <?php endif; ?>

    <table>
        <caption>Panier — <?= count($recap['articles']) ?> article(s)</caption>
        <thead>
        <tr>
            <th scope="col">Produit</th>
            <th scope="col">Prix unitaire TTC</th>
            <th scope="col">Quantité</th>
            <th scope="col">Total TTC</th>
            <th scope="col">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($recap['articles'] as $ligne): ?>
            <tr<?= !empty($ligne['plafonne']) ? ' title="Quantité plafonnée au stock disponible (RB-18)"' : '' ?>>
                <td><a href="/produit/<?= e($ligne['slug']) ?>"><?= e($ligne['nom']) ?></a><br>
                    <small>Réf. <?= e($ligne['reference']) ?> — stock : <?= (int) $ligne['stock_disponible'] ?></small></td>
                <td><?= euros($ligne['prix_unitaire']) ?></td>
                <td>
                    <form method="post" action="/panier/quantite">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_produit" value="<?= (int) $ligne['id_produit'] ?>">
                        <label>
                            <input type="number" name="quantite" min="0" max="<?= (int) $ligne['stock_disponible'] ?>"
                                   value="<?= (int) $ligne['quantite'] ?>">
                        </label>
                        <button type="submit">Mettre à jour</button>
                    </form>
                </td>
                <td><?= euros($ligne['total_ligne']) ?></td>
                <td>
                    <form method="post" action="/panier/retirer">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id_produit" value="<?= (int) $ligne['id_produit'] ?>">
                        <button type="submit">Retirer</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
        <tr>
            <th scope="row" colspan="3">Sous-total (marchandises TTC)</th>
            <td colspan="2"><?= euros($recap['sous_total']) ?></td>
        </tr>
        <tr>
            <th scope="row" colspan="3">Frais de port<br><small><?= e($recap['port_motif']) ?></small></th>
            <td colspan="2"><?= euros($recap['frais_port']) ?></td>
        </tr>
        <tr>
            <th scope="row" colspan="3">Total TTC</th>
            <td colspan="2"><strong><?= euros($recap['total']) ?></strong></td>
        </tr>
        </tfoot>
    </table>

    <p><a href="/catalogue">← Continuer mes achats</a></p>
    <p><a href="/commande/valider"><strong>Valider ma commande →</strong></a></p>
<?php endif; ?>
