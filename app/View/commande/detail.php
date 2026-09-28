<?php

declare(strict_types=1);

/** @var array<string,mixed> $commande @var list<array<string,mixed>> $lignes @var list<array<string,mixed>> $historique (UC-08/09, EF-CLI-09, RB-11) */
$annulable = in_array($commande['statut'], ['BROUILLON', 'EN_PREPARATION', 'PAYEE'], true);
?>
<h1>Commande <?= e($commande['numero']) ?></h1>

<p>
    Passée le <?= e(date_fr($commande['date_commande'], true)) ?> ·
    Statut : <strong><?= e(statut_libelle($commande['statut'])) ?></strong> ·
    Livraison : <?= e($commande['adresse_livraison']) ?>
</p>

<h2>Articles (prix TTC figés à l'achat — RB-06)</h2>
<table>
    <thead>
    <tr><th scope="col">Produit</th><th scope="col">Prix unitaire TTC</th><th scope="col">Qté</th><th scope="col">Total TTC</th></tr>
    </thead>
    <tbody>
    <?php foreach ($lignes as $ligne): ?>
        <tr>
            <td><?= e($ligne['produit_nom']) ?> <small>(réf. <?= e($ligne['produit_reference']) ?>)</small></td>
            <td><?= euros($ligne['prix_unitaire']) ?></td>
            <td><?= (int) $ligne['quantite'] ?></td>
            <td><?= euros($ligne['total_ligne']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
    <tr><th scope="row" colspan="3">Frais de port</th><td><?= euros($commande['frais_port']) ?></td></tr>
    <tr><th scope="row" colspan="3">Montant total TTC</th><td><strong><?= euros($commande['montant_total']) ?></strong></td></tr>
    </tfoot>
</table>

<h2>Piste des statuts (RB-11 — alimentée automatiquement par le moteur)</h2>
<table>
    <thead><tr><th scope="col">Quand</th><th scope="col">Statut</th><th scope="col">Par</th><th scope="col">Commentaire</th></tr></thead>
    <tbody>
    <?php foreach ($historique as $entree): ?>
        <tr>
            <td><?= e(date_fr($entree['changed_at'], true)) ?></td>
            <td><?= e(statut_libelle($entree['new_status'])) ?></td>
            <td><?= e($entree['changed_by_role']) ?><?php if (!empty($entree['changed_by'])) { echo ' #' . (int) $entree['changed_by']; } else { echo ''; } ?></td>
            <td><?= e($entree['commentaire']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($annulable): ?>
    <h2>Annuler ma commande</h2>
    <p>Conformément au droit de rétractation (CGV art. 4), vous pouvez annuler tant que
        la commande n'est pas expédiée : le stock sera restitué et le montant (port
        compris) soldé (RB-18/20).</p>
    <form method="post" action="/mes-commandes/<?= (int) $commande['id_commande'] ?>/annulation">
        <?= csrf_field() ?>
        <p><button type="submit">Annuler cette commande</button></p>
    </form>
<?php endif; ?>

<p><a href="/mes-commandes">← Toutes mes commandes</a></p>
