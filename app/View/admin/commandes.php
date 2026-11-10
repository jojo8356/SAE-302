<?php

declare(strict_types=1);

/** @var list<array<string,mixed>> $commandes @var list<string> $statuts @var array{statut:string,client:string,du:string,au:string,montant_min:?float} $filtres (EF-ADM-06) */
?>
<h1>Commandes</h1>

<form method="get" action="/admin/commandes" data-filtre-jours>
    <input type="hidden" name="debut_utc" value="">
    <input type="hidden" name="fin_utc" value="">
    <fieldset>
        <legend>Filtres (cumulables)</legend>
        <p>Les dates utilisent votre fuseau d’affichage ; sans JavaScript, elles utilisent Paris.</p>
        <p>
            <label for="statut">Statut :</label>
            <select id="statut" name="statut">
                <option value="">Tous</option>
                <?php foreach ($statuts as $statut): ?>
                    <option value="<?= e($statut) ?>" <?php if ($filtres['statut'] === $statut) { echo 'selected'; } else { echo ''; } ?>>
                        <?= e(statut_libelle($statut)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label for="client">Client (email) ou n° commande :</label>
            <input id="client" name="client" maxlength="120" value="<?= e($filtres['client']) ?>">
        </p>
        <p>
            <label for="du">Du :</label>
            <input type="date" id="du" name="du" value="<?= e($filtres['du']) ?>">
            <label for="au">au :</label>
            <input type="date" id="au" name="au" value="<?= e($filtres['au']) ?>">
            <label for="montant_min">Montant min (€) :</label>
            <input type="number" id="montant_min" name="montant_min" min="0" step="0.01" value="<?= e($filtres['montant_min']) ?>">
            <button type="submit">Filtrer</button>
        </p>
    </fieldset>
</form>

<p><?= count($commandes) ?> commande(s) affichée(s) (100 max)</p>

<div class="tableau-defilant tableau-defilant--admin" role="region" tabindex="0" aria-label="Tableau de données">
<table class="tableau-commandes">
    <colgroup><col class="col-numero"><col class="col-date"><col class="col-client"><col class="col-statut"><col class="col-montant"><col class="col-detail"></colgroup>
    <thead>
    <tr>
        <th scope="col">Numéro</th><th scope="col">Date</th><th scope="col">Client</th>
        <th scope="col">Statut</th><th scope="col" class="montant">Montant TTC</th><th scope="col">Détail</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($commandes as $commande): ?>
        <tr>
            <td data-label="Numéro"><?= e($commande['numero']) ?></td>
            <td data-label="Date"><?= date_html($commande['date_commande'], true) ?></td>
            <td data-label="Client"><?= e($commande['client'] ?? ($commande['client_nom'] ?? '—')) ?></td>
            <td class="statut" data-label="Statut"><?= e(statut_libelle($commande['statut'])) ?></td>
            <td class="montant" data-label="Montant TTC"><?= euros($commande['montant_total']) ?></td>
            <td data-label="Détail"><a href="/admin/commandes/<?= (int) $commande['id_commande'] ?>">Ouvrir →</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
