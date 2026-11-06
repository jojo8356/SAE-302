<?php

declare(strict_types=1);

/**
 * @var array<string,mixed> $indicateurs
 * @var array{par_statut: list<array<string,mixed>>, top_produits: list<array<string,mixed>>} $rapport
 * @var list<array<string,mixed>> $alertes (EF-ADM-00/05/07)
 */
?>
<h1>Tableau de bord</h1>

<h2>Indicateurs</h2>
<ul>
    <li><strong><?= (int) $indicateurs['commandes'] ?></strong> commandes dont
        <strong><?= (int) $indicateurs['en_preparation'] ?></strong> en préparation</li>
    <li><strong><?= (int) $indicateurs['clients'] ?></strong> clients</li>
    <li><strong><?= (int) $indicateurs['produits'] ?></strong> produits au catalogue</li>
    <li>Chiffre d'affaires cumulé (hors annulées) : <strong><?= euros($indicateurs['ca_total']) ?></strong></li>
</ul>

<h2>Alertes de stock</h2>
<?php if ($alertes === []): ?>
    <p>Aucune alerte : tous les stocks sont au-dessus de leur seuil.</p>
<?php else: ?>
    <table class="tableau-alertes">
        <thead><tr><th scope="col" class="reference">Référence</th><th scope="col" class="produit">Produit</th><th scope="col">Stock</th><th scope="col">Seuil</th><th scope="col">État</th></tr></thead>
        <tbody>
        <?php foreach ($alertes as $alerte): ?>
            <tr>
                <td class="reference"><?= e($alerte['reference']) ?></td>
                <td class="produit"><?= e($alerte['nom']) ?></td>
                <td><?= (int) $alerte['stock'] ?></td>
                <td><?= (int) $alerte['seuil_alerte'] ?></td>
                <td><?= e(etat_libelle($alerte['etat'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p><a href="/admin/stocks">Gérer les stocks →</a></p>
<?php endif; ?>

<h2>Chiffre d'affaires par statut</h2>
<table>
    <thead><tr><th scope="col">Statut</th><th scope="col">Nb commandes</th><th scope="col">Montant total</th><th scope="col">Montant moyen</th></tr></thead>
    <tbody>
    <?php foreach ($rapport['par_statut'] as $ligne): ?>
        <tr>
            <td><?= e(statut_libelle($ligne['statut'])) ?></td>
            <td><?= (int) $ligne['nb_commandes'] ?></td>
            <td><?= euros($ligne['montant_total']) ?></td>
            <td><?= euros($ligne['montant_moyen']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Top 5 produits</h2>
<?php if ($rapport['top_produits'] === []): ?>
    <p>Aucune vente enregistrée pour le moment : le classement des produits
        les plus vendus apparaîtra dès les premières commandes.</p>
<?php else: ?>
<ol>
    <?php foreach ($rapport['top_produits'] as $top): ?>
        <li><?= e($top['nom']) ?> (réf. <?= e($top['reference']) ?>) —
            <?= (int) $top['unites'] ?> unités, <?= euros($top['ca']) ?> de CA</li>
    <?php endforeach; ?>
</ol>
<?php endif; ?>

<p><a href="/admin/commandes">Voir les commandes →</a></p>
