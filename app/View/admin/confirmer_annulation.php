<?php

declare(strict_types=1);

/** @var array<string,mixed> $commande @var string $commentaire */
?>
<h1>Confirmer l’annulation</h1>
<p>Annuler définitivement la commande <strong><?= e($commande['numero']) ?></strong>
    pour <?= euros($commande['montant_total']) ?> ? Le montant et les frais de port seront soldés ; les articles resteront dans l’historique.</p>
<?php if ($commande['statut'] === 'EXPEDIEE'): ?>
    <p>Cette commande est expédiée : le stock ne sera pas automatiquement restitué. Vérifiez le retour physique avant tout ajustement de stock.</p>
<?php else: ?>
    <p>Les articles non expédiés seront restitués au stock.</p>
<?php endif; ?>
<p>Motif : <?= e($commentaire) ?></p>
<form method="post" action="/admin/commandes/<?= (int) $commande['id_commande'] ?>/statut">
    <?= csrf_field() ?>
    <input type="hidden" name="statut" value="ANNULEE">
    <input type="hidden" name="commentaire" value="<?= e($commentaire) ?>">
    <input type="hidden" name="confirmer_annulation" value="1">
    <button type="submit" class="bouton-danger">Confirmer l’annulation définitive</button>
</form>
<p><a href="/admin/commandes/<?= (int) $commande['id_commande'] ?>">Non, revenir à la commande</a></p>
