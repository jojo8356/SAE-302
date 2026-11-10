<?php

declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $parametres
 * @var array<string,string> $libelles
 * @var list<string> $erreurs
 */
?>
<header class="entete-page">
    <div>
        <h1>Paramètres</h1>
        <p class="chapo">Les règles chiffrées du site sont des <strong>données</strong>, pas des constantes :
            les modifier ici suffit, sans redéploiement.</p>
    </div>
</header>

<?php if ($erreurs !== []): ?>
    <div class="message message--erreur" role="alert">
        <?= icone('alerte', 20) ?>
        <ul><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="/admin/parametres">
    <?= csrf_field() ?>
    <fieldset>
        <legend>Règles commerciales et de conservation</legend>

        <?php foreach ($parametres as $parametre): ?>
            <?php $cle = (string) $parametre['cle']; ?>
            <p>
                <label for="param-<?= e($cle) ?>">
                    <?= e($libelles[$cle] ?? $cle) ?>
                    <span class="texte-aide">(<?= e($parametre['unite'] ?? '') ?>)</span>
                </label>
                <input type="text" inputmode="decimal" id="param-<?= e($cle) ?>" name="param_<?= e($cle) ?>"
                       value="<?= e($parametre['valeur']) ?>" maxlength="20" required>
                <?php if (!empty($parametre['comment'])): ?>
                    <span class="texte-aide"><?= e($parametre['comment']) ?></span>
                <?php endif; ?>
            </p>
        <?php endforeach; ?>

        <div class="actions-formulaire">
            <button type="submit" class="btn--acheter">Enregistrer les paramètres</button>
            <a class="btn btn--secondaire" href="/admin">Retour au tableau de bord</a>
        </div>
    </fieldset>
</form>

<section class="carte">
    <h2>Effet des paramètres</h2>
    <ul>
        <li><strong>Frais de port</strong> : affichés dans le panier et le récapitulatif avant validation (obligation légale).</li>
        <li><strong>Franchise</strong> : au-delà de ce montant de marchandises, le port est offert.</li>
        <li><strong>TVA par défaut</strong> : proposée à la création d'un produit ; le prix TTC est recalculé par le moteur.</li>
        <li><strong>Conservation</strong> : délai en mois avant traitement des comptes sans commande. La tâche planifiée doit être installée par l’exploitant ; les comptes avec commande, journaux et sauvegardes nécessitent un traitement distinct.</li>
    </ul>
    <p class="texte-aide">Chaque modification est journalisée avec l'auteur et l'horodatage (EF-GEN-04).</p>
</section>
