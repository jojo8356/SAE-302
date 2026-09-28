<?php

declare(strict_types=1);

/** @var array<string,mixed> $commande @var array<string,mixed>|null $client @var list<array<string,mixed>> $lignes @var list<array<string,mixed>> $historique @var list<string> $transitions (EF-ADM-08, RB-09/11) */
?>
<h1>Commande <?= e($commande['numero']) ?></h1>

<p>
    Statut actuel : <strong><?= e(statut_libelle($commande['statut'])) ?></strong> ·
    Passée le <?= e(date_fr($commande['date_commande'], true)) ?> ·
    Montant : <strong><?= euros($commande['montant_total']) ?></strong>
    (dont port <?= euros($commande['frais_port']) ?>)
</p>
<?php if ($client !== null): ?>
    <p>Client : <?= e($client['prenom'] . ' ' . $client['nom']) ?> &lt;<?= e($client['email']) ?>&gt; ·
        Livraison : <?= e($commande['adresse_livraison']) ?></p>
<?php endif; ?>

<h2>Lignes (prix TTC figés — RB-06)</h2>
<table>
    <thead><tr><th scope="col">Produit</th><th scope="col">Réf.</th><th scope="col">Prix unitaire TTC</th><th scope="col">Qté</th><th scope="col">Total TTC</th></tr></thead>
    <tbody>
    <?php foreach ($lignes as $ligne): ?>
        <tr>
            <td><?= e($ligne['produit_nom']) ?></td>
            <td><?= e($ligne['produit_reference']) ?></td>
            <td><?= euros($ligne['prix_unitaire']) ?></td>
            <td><?= (int) $ligne['quantite'] ?></td>
            <td><?= euros($ligne['total_ligne']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Changer le statut (matrice RB-11 appliquée par le moteur)</h2>
<?php if ($transitions === []): ?>
    <p>Statut terminal : aucune transition possible.</p>
<?php else: ?>
    <form method="post" action="/admin/commandes/<?= (int) $commande['id_commande'] ?>/statut">
        <?= csrf_field() ?>
        <p>
            <label for="statut">Nouveau statut :</label>
            <select id="statut" name="statut" required>
                <?php foreach ($transitions as $statutPossible): ?>
                    <option value="<?= e($statutPossible) ?>"><?= e(statut_libelle($statutPossible)) ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="commentaire">Commentaire (obligatoire pour une annulation, tracé dans la piste RB-11) :</label>
            <input id="commentaire" name="commentaire" maxlength="500">
        </p>
        <p><button type="submit">Appliquer</button></p>
    </form>
<?php endif; ?>

<h2>Piste d'audit des statuts (RB-11)</h2>
<table>
    <thead><tr><th scope="col">Quand</th><th scope="col">Statut</th><th scope="col">Par</th><th scope="col">Commentaire</th></tr></thead>
    <tbody>
    <?php foreach ($historique as $entree): ?>
        <tr>
            <td><?= e(date_fr($entree['changed_at'], true)) ?></td>
            <td><?= e(statut_libelle($entree['new_status'])) ?></td>
            <td><?= e($entree['changed_by_role']) ?><?= !empty($entree['changed_by']) ? ' #' . (int) $entree['changed_by'] : '' ?></td>
            <td><?= e($entree['commentaire']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<p><a href="/admin/commandes">← Toutes les commandes</a></p>
