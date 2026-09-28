<?php

declare(strict_types=1);

/** @var array<string,int> $statistiques */
?>
<h1>À propos du moteur de données</h1>
<p>Cette boutique ne fonctionne <strong>ni avec MySQL, ni avec aucune requête SQL</strong> :
les données vivent dans des fichiers JSON (<code>data/minishop/*.json</code>) et sont
manipulées par un moteur maison qui applique, en opérations natives, les mêmes
garanties qu'un SGBD :</p>
<ul>
    <li><strong>Schéma &amp; contraintes</strong> — NOT NULL, ENUM, UNIQUE, CHECK, clés
        étrangères RESTRICT/CASCADE (portrait exact de <code>sql/01_minishop_schema.sql</code>) ;</li>
    <li><strong>16 déclencheurs métier</strong> — prix TTC dérivé (RB-16), prix figé à
        l'achat (RB-06), stock jamais négatif (RB-03/18), machine à états des commandes
        (RB-11), piste d'audit automatique des statuts ;</li>
    <li><strong>Transactions</strong> — snapshot + rollback : un refus ne laisse jamais
        de données incohérentes (ENF-16) ;</li>
    <li><strong>Journal d'audit</strong> — chaque écriture est tracée avec son auteur
        (EF-GEN-04) ;</li>
    <li><strong>Écriture atomique</strong> — fichier temporaire puis renommage : un crash
        ne peut pas corrompre une table.</li>
</ul>
<p>Le jour de la migration finale, le pilote <code>StorageDriver::SQL</code> prendra le
relais avec les scripts <code>sql/01→04</code> déjà livrés — sans changer une ligne des
contrôleurs ni des vues.</p>

<h2>Contenu actuel de la base</h2>
<ul>
    <li><?= (int) $statistiques['produits'] ?> produits (dont <?= (int) $statistiques['visibles'] ?> visibles, RB-19)</li>
    <li><?= (int) $statistiques['clients'] ?> clients</li>
    <li><?= (int) $statistiques['commandes'] ?> commandes</li>
    <li><?= (int) $statistiques['parametres'] ?> paramètres métier (frais de port, franchise, TVA)</li>
</ul>
