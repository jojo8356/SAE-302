<?php

declare(strict_types=1);

/** @var array<string,mixed> $commande (UC-09 — confirmation avant annulation définitive) */
?>
<h1>Annuler cette commande ?</h1>

<p>Êtes-vous sûr de vouloir annuler la commande
    <strong><?= e($commande['numero']) ?></strong>
    (<?= e(date_fr($commande['date_commande'], true)) ?>,
    <?= euros($commande['montant_total']) ?>) ?</p>

<p><strong>Cette action est définitive.</strong> La commande passera au statut
    « Annulée », le stock des articles sera restitué et le montant (port
    compris) soldé (RB-18/20). Vous ne pourrez pas revenir en arrière.</p>

<form method="post" action="/mes-commandes/<?= (int) $commande['id_commande'] ?>/annulation">
    <?= csrf_field() ?>
    <p><button type="submit" class="bouton-danger">Oui, annuler définitivement</button></p>
</form>

<p><a href="/mes-commandes/<?= (int) $commande['id_commande'] ?>">Non, revenir à ma commande</a></p>
