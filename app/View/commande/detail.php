<?php

declare(strict_types=1);

/** @var array<string,mixed> $commande @var list<array<string,mixed>> $lignes @var list<array<string,mixed>> $historique (UC-08/09, EF-CLI-09, RB-11) */
$annulable = in_array($commande['statut'], ['BROUILLON', 'EN_PREPARATION', 'PAYEE'], true);
?>
<h1>Commande <?= e($commande['numero']) ?></h1>

<p>
    Passée le <?= date_html($commande['date_commande'], true) ?> ·
    Statut : <strong><?= e(statut_libelle($commande['statut'])) ?></strong> ·
    Livraison : <?= e($commande['adresse_livraison']) ?>
</p>

<h2>Articles (prix TTC figés à l'achat)</h2>
<div class="tableau-defilant" role="region" tabindex="0" aria-label="Tableau de données — faites défiler horizontalement si nécessaire">
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
    <?php if ($commande['statut'] === 'ANNULEE' && ($commande['montant_avant_annulation'] ?? null) !== null): ?>
    <tr><th scope="row" colspan="3">Total de la commande avant annulation</th><td><?= euros($commande['montant_avant_annulation']) ?></td></tr>
    <tr><th scope="row" colspan="3">Montant soldé (annulée — rien à payer)</th><td><strong><?= euros($commande['montant_total']) ?></strong></td></tr>
    <?php else: ?>
    <tr><th scope="row" colspan="3">Frais de port</th><td><?= euros($commande['frais_port']) ?></td></tr>
    <tr><th scope="row" colspan="3">Montant total TTC</th><td><strong><?= euros($commande['montant_total']) ?></strong></td></tr>
    <?php endif; ?>
    </tfoot>
</table>
</div>

<h2>Historique des statuts</h2>
<div class="tableau-defilant" role="region" tabindex="0" aria-label="Tableau de données — faites défiler horizontalement si nécessaire">
<table>
    <thead><tr><th scope="col">Quand</th><th scope="col">Statut</th><th scope="col">Par</th><th scope="col">Commentaire</th></tr></thead>
    <tbody>
    <?php foreach ($historique as $entree): ?>
        <tr>
            <td><?= date_html($entree['changed_at'], true) ?></td>
            <td><?= e(statut_libelle($entree['new_status'])) ?></td>
            <td><?= e($entree['changed_by_role']) ?><?php if (!empty($entree['changed_by'])) { echo ' #' . (int) $entree['changed_by']; } else { echo ''; } ?></td>
            <td><?= e($entree['commentaire']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($annulable): ?>
    <h2>Annuler ma commande</h2>
    <p>Cette annulation technique de la commande simulée est possible avant expédition :
        le stock est restitué et le montant simulé soldé. Elle est distincte du
        <a href="/retractation">droit légal de rétractation</a>, dont la fonction réelle n’est pas disponible sur cette maquette.</p>
    <form method="get" action="/mes-commandes/<?= (int) $commande['id_commande'] ?>/annulation/confirmation">
        <button type="submit" class="bouton-danger">Annuler cette commande</button>
    </form>
<?php endif; ?>

<p><a href="/mes-commandes">← Toutes mes commandes</a></p>
