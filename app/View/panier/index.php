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
<header class="entete-page">
    <div>
        <h1>Mon panier</h1>
        <p class="chapo"><?= count($recap['articles']) ?> article<?php if (count($recap['articles']) > 1) { echo 's'; } else { echo ''; } ?> · les prix et le stock sont recalculés par le serveur à chaque affichage.</p>
    </div>
</header>

<?php if ($recap['articles'] === []): ?>
    <div class="etat-vide">
        <p><strong>Votre panier est vide.</strong></p>
        <p>Parcourez le catalogue pour trouver votre matériel.</p>
        <p><a class="btn btn--acheter" href="/catalogue">Parcourir le catalogue →</a></p>
    </div>
<?php else: ?>
    <?php if ($plafonne): ?>
        <p class="message message--erreur" role="alert">
            <?= icone('alerte', 20) ?>
            <span>Certains articles ont été ramenés au stock disponible (RB-18) : la quantité maximale a été ajustée côté serveur.</span>
        </p>
    <?php endif; ?>

    <div class="tableau-defilant tableau-defilant--carte" role="region" tabindex="0" aria-label="Tableau de données — faites défiler horizontalement si nécessaire">
        <table class="tableau-panier tableau-nombres">
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
                <tr<?php if (!empty($ligne['plafonne'])) { echo ' title="Quantité plafonnée au stock disponible (RB-18)"'; } else { echo ''; } ?>>
                    <td data-label="Produit">
                        <a href="/produit/<?= e($ligne['slug']) ?>"><?= e($ligne['nom']) ?></a><br>
                        <small>Réf. <?= e($ligne['reference']) ?> — stock : <?= (int) $ligne['stock_disponible'] ?></small>
                    </td>
                    <td data-label="Prix unitaire TTC"><?= euros($ligne['prix_unitaire']) ?></td>
                    <td data-label="Quantité">
                        <form method="post" action="/panier/quantite" class="actions-ligne">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_produit" value="<?= (int) $ligne['id_produit'] ?>">
                            <label class="visuellement-cache" for="qte-<?= (int) $ligne['id_produit'] ?>">Quantité pour <?= e($ligne['nom']) ?></label>
                            <input type="number" id="qte-<?= (int) $ligne['id_produit'] ?>" name="quantite" min="0" max="<?= (int) $ligne['stock_disponible'] ?>"
                                   value="<?= (int) $ligne['quantite'] ?>" data-largeur-courte>
                            <button type="submit" class="btn--secondaire btn--sm">Mettre à jour</button>
                        </form>
                    </td>
                    <td data-label="Total TTC"><?= euros($ligne['total_ligne']) ?></td>
                    <td data-label="Actions">
                        <form method="post" action="/panier/retirer" data-confirme="Retirer cet article du panier ?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_produit" value="<?= (int) $ligne['id_produit'] ?>">
                            <button type="submit" class="btn--danger btn--sm">Retirer</button>
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
                <th scope="row" colspan="3">Frais de port <small>(<?= e($recap['port_motif']) ?>)</small></th>
                <td colspan="2"><?= euros($recap['frais_port']) ?></td>
            </tr>
            <tr>
                <th scope="row" colspan="3">Total TTC</th>
                <td colspan="2" data-total-panier><strong><?= euros($recap['total']) ?></strong></td>
            </tr>
            </tfoot>
        </table>
    </div>

    <div class="recap">
        <h2>Récapitulatif</h2>
        <dl>
            <dt>Sous-total</dt>
            <dd><?= euros($recap['sous_total']) ?></dd>
            <dt>Frais de port</dt>
            <dd><?= euros($recap['frais_port']) ?></dd>
            <div class="total">
                <dt><strong>Total TTC</strong></dt>
                <dd><?= euros($recap['total']) ?></dd>
            </div>
        </dl>
        <p class="texte-aide"><?= e($recap['port_motif']) ?> — montant affiché avant validation (art. L221-5 4° C. conso.).</p>
        <a class="btn--acheter" href="/commande/valider">Valider ma commande →</a>
        <p class="marge-haut"><a class="lien-retour" href="/catalogue">← Continuer mes achats</a></p>
    </div>
<?php endif; ?>
