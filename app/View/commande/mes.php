<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $commandes (UC-08, EF-CLI-08) */
?>
<h1>Mes commandes</h1>

<?php if ($commandes === []): ?>
    <p>Vous n'avez pas encore de commande.</p>
    <p><a href="/catalogue">Parcourir le catalogue →</a></p>
<?php else: ?>
    <div class="tableau-defilant tableau-defilant--client" role="region" tabindex="0" aria-label="Historique de vos commandes">
<table class="tableau-commandes-client">
        <caption>Historique de vos commandes (prix figés à l'achat)</caption>
        <thead>
        <tr>
            <th scope="col">Numéro</th><th scope="col">Date</th><th scope="col">Statut</th>
            <th scope="col">Articles</th><th scope="col" class="montant">Montant TTC</th><th scope="col"></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($commandes as $commande): ?>
            <tr>
                <td data-label="Numéro"><a href="/mes-commandes/<?= (int) $commande['id_commande'] ?>"><?= e($commande['numero']) ?></a></td>
                <td data-label="Date"><?= e(date_fr($commande['date_commande'], true)) ?></td>
                <td data-label="Statut"><?= e(statut_libelle($commande['statut'])) ?></td>
                <td data-label="Articles"><?= (int) ($commande['nb_lignes'] ?? 0) ?></td>
                <td class="montant" data-label="Montant TTC"><?= euros($commande['montant_total']) ?></td>
                <td data-label="Détail"><a href="/mes-commandes/<?= (int) $commande['id_commande'] ?>">Détail →</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
