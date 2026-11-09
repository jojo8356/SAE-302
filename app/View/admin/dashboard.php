<?php

declare(strict_types=1);

/**
 * @var array<string,mixed> $indicateurs
 * @var array{par_statut: list<array<string,mixed>>, top_produits: list<array<string,mixed>>} $rapport
 * @var list<array<string,mixed>> $alertes (EF-ADM-00/05/07)
 */
?>
<header class="entete-page">
    <div>
        <h1>Tableau de bord</h1>
        <p class="chapo">Vue d'ensemble : activité du jour, alertes de stock, chiffre d'affaires.</p>
    </div>
</header>

<section class="kpis" aria-label="Indicateurs clés">
    <div class="kpi kpi--accent">
        <p class="kpi__libelle">CA du jour</p>
        <p class="kpi__valeur"><?= euros($indicateurs['ca_jour'] ?? 0) ?></p>
    </div>
    <div class="kpi">
        <p class="kpi__libelle">Commandes du jour</p>
        <p class="kpi__valeur"><?= (int) ($indicateurs['commandes_jour'] ?? 0) ?></p>
    </div>
    <div class="kpi">
        <p class="kpi__libelle">CA cumulé</p>
        <p class="kpi__valeur"><?= euros($indicateurs['ca_total']) ?></p>
    </div>
    <div class="kpi">
        <p class="kpi__libelle">À préparer</p>
        <p class="kpi__valeur"><?= (int) $indicateurs['en_preparation'] ?></p>
    </div>
    <div class="kpi">
        <p class="kpi__libelle">Clients</p>
        <p class="kpi__valeur"><?= (int) $indicateurs['clients'] ?></p>
    </div>
    <div class="kpi">
        <p class="kpi__libelle">Produits</p>
        <p class="kpi__valeur"><?= (int) $indicateurs['produits'] ?></p>
    </div>
</section>

<section>
    <h2>Alertes de stock</h2>
    <?php if ($alertes === []): ?>
        <p class="message message--succes"><?= icone('coche', 20) ?><span>Aucune alerte : tous les stocks sont au-dessus de leur seuil.</span></p>
    <?php else: ?>
        <div class="tableau-defilant tableau-defilant--admin" role="region" tabindex="0" aria-label="Tableau de données — faites défiler horizontalement si nécessaire">
            <table class="tableau-alertes tableau-nombres">
                <caption>Produits sous le seuil — le stock ne peut jamais devenir négatif</caption>
                <thead><tr><th scope="col" class="reference">Référence</th><th scope="col" class="produit">Produit</th><th scope="col">Stock</th><th scope="col">Seuil</th><th scope="col">État</th></tr></thead>
                <tbody>
                <?php foreach ($alertes as $alerte): ?>
                    <tr>
                        <td class="reference" data-label="Référence"><?= e($alerte['reference']) ?></td>
                        <td class="produit" data-label="Produit"><?= e($alerte['nom']) ?></td>
                        <td data-label="Stock"><?= (int) $alerte['stock'] ?></td>
                        <td data-label="Seuil"><?= (int) $alerte['seuil_alerte'] ?></td>
                        <td data-label="État"><?= badge_etat((string) $alerte['etat']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p><a class="btn btn--secondaire btn--sm" href="/admin/stocks">Gérer les stocks →</a></p>
    <?php endif; ?>
</section>

<section>
    <h2>Chiffre d'affaires par statut</h2>
    <div class="tableau-defilant" role="region" tabindex="0" aria-label="Tableau de données — faites défiler horizontalement si nécessaire">
        <table class="tableau-nombres">
            <thead><tr><th scope="col">Statut</th><th scope="col">Nb commandes</th><th scope="col">Montant total</th><th scope="col">Montant moyen</th></tr></thead>
            <tbody>
            <?php foreach ($rapport['par_statut'] as $ligne): ?>
                <tr>
                    <td><?= badge_statut((string) $ligne['statut']) ?></td>
                    <td><?= (int) $ligne['nb_commandes'] ?></td>
                    <td><?= euros($ligne['montant_total']) ?></td>
                    <td><?= euros($ligne['montant_moyen']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section>
    <h2>Top 5 produits</h2>
    <?php if ($rapport['top_produits'] === []): ?>
        <p>Aucun produit vendu pour le moment.</p>
    <?php else: ?>
    <div class="tableau-defilant" role="region" tabindex="0" aria-label="Tableau de données — faites défiler horizontalement si nécessaire">
        <table class="tableau-nombres">
            <thead><tr><th scope="col">#</th><th scope="col">Produit</th><th scope="col">Unités</th><th scope="col">CA</th></tr></thead>
            <tbody>
            <?php $rang = 0; foreach ($rapport['top_produits'] as $top): ++$rang; ?>
                <tr>
                    <td><?= (int) $rang ?></td>
                    <td><?= e($top['nom']) ?> <small>(réf. <?= e($top['reference']) ?>)</small></td>
                    <td><?= (int) $top['unites'] ?></td>
                    <td><?= euros($top['ca']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <p><a class="btn btn--secondaire btn--sm" href="/admin/commandes">Voir les commandes →</a></p>
</section>
