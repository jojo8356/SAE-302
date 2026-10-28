<?php

declare(strict_types=1);

/**
 * @var array{articles: list<array<string,mixed>>, sous_total: float, frais_port: float, total: float, port_motif: string} $recap
 * @var array<string,mixed> $client
 * @var list<string> $erreurs
 * (UC-07, EF-CLI-07) — récapitulatif + port affichés AVANT validation (art. L221-5).
 */
?>
<h1>Valider ma commande</h1>
<p class="message message--info">Maquette pédagogique : aucun paiement ni livraison réelle. Les coordonnées de commande sont enregistrées.</p>

<?php if ($erreurs !== []): ?>
    <ul role="alert">
        <?php foreach ($erreurs as $erreur): ?><li>✘ <?= e($erreur) ?></li><?php endforeach; ?>
    </ul>
<?php endif; ?>

<div class="tableau-defilant" role="region" tabindex="0" aria-label="Tableau de données — faites défiler horizontalement si nécessaire">
<table>
    <caption>Récapitulatif de votre panier</caption>
    <thead>
    <tr><th scope="col">Produit</th><th scope="col">Prix TTC</th><th scope="col">Qté</th><th scope="col">Total TTC</th></tr>
    </thead>
    <tbody>
    <?php foreach ($recap['articles'] as $ligne): ?>
        <tr>
            <td><?= e($ligne['nom']) ?> <small>(réf. <?= e($ligne['reference']) ?>)</small></td>
            <td><?= euros($ligne['prix_unitaire']) ?></td>
            <td><?= (int) $ligne['quantite'] ?></td>
            <td><?= euros($ligne['total_ligne']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
    <tr><th scope="row" colspan="3">Sous-total TTC</th><td><?= euros($recap['sous_total']) ?></td></tr>
    <tr><th scope="row" colspan="3">Frais de port <small>(<?= e($recap['port_motif']) ?>)</small></th><td><?= euros($recap['frais_port']) ?></td></tr>
    <tr><th scope="row" colspan="3">Total simulé</th><td><strong><?= euros($recap['total']) ?></strong></td></tr>
    </tfoot>
</table>
</div>

<form method="post" action="/commande/valider">
    <?= csrf_field() ?>
    <fieldset>
        <legend>Livraison</legend>
        <p>
            <label for="adresse">Adresse de livraison complète *</label>
            <input id="adresse" name="adresse" maxlength="255" required
                   value="<?= e($client['adresse_livraison'] ?? '') ?>"
                   placeholder="12 rue des Lilas, Bât. B">
        </p>
        <p>
            <label><input type="checkbox" name="paiement_simule" value="1" checked>
                Simuler le paiement immédiat (aucun débit réel — la commande passera
                directement au statut « Payée »)</label>
        </p>
        <p>
            <button type="submit">Confirmer et payer (simulé)</button>
        </p>
    </fieldset>
</form>

<p><small>En validant, vous acceptez les <a href="/cgv">CGV</a>. Le stock est vérifié et
décrémenté au moment présent (RB-18) ; le prix de chaque ligne est <strong>figé</strong>
dans la commande (RB-06) et le port gelé avec la commande (RB-20).</small></p>
<p><small>Avant de confirmer : <a href="/livraison">livraison</a>, <a href="/retractation">rétractation</a>, <a href="/garanties">garanties</a> et <a href="/confidentialite">données personnelles</a>.</small></p>
<p><a href="/panier">← Revenir au panier</a></p>
