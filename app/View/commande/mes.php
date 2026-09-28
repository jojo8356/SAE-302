<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $commandes (UC-08, EF-CLI-08) */
?>
<h1>Mes commandes</h1>

<?php if ($commandes === []): ?>
    <p>Vous n'avez pas encore de commande.</p>
    <p><a href="/catalogue">Parcourir le catalogue →</a></p>
<?php else: ?>
    <table>
        <caption>Historique de vos commandes (prix figés à l'achat — RB-06)</caption>
        <thead>
        <tr>
            <th scope="col">Numéro</th><th scope="col">Date</th><th scope="col">Statut</th>
            <th scope="col">Articles</th><th scope="col">Montant TTC</th><th scope="col"></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($commandes as $commande): ?>
            <tr>
                <td><a href="/mes-commandes/<?= (int) $commande['id_commande'] ?>"><?= e($commande['numero']) ?></a></td>
                <td><?= e(date_fr($commande['date_commande'], true)) ?></td>
                <td><?= e(statut_libelle($commande['statut'])) ?></td>
                <td><?= (int) ($commande['nb_lignes'] ?? 0) ?></td>
                <td><?= euros($commande['montant_total']) ?></td>
                <td><a href="/mes-commandes/<?= (int) $commande['id_commande'] ?>">Détail →</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
