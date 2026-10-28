<?php

declare(strict_types=1);

/** @var array<string,int> $statistiques */
?>
<h1>À propos du moteur de données</h1>
<p>Cette maquette peut utiliser un moteur JSON ou un magasin SQL selon sa configuration.
En mode JSON, les données vivent dans des fichiers <code>data/minishop/*.json</code> et
sont manipulées par un moteur maison. Les règles ci-dessous décrivent ce mode :</p>
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
<p>Le pilote SQL et les scripts <code>sql/01→04</code> sont disponibles selon la configuration.
La protection, la conservation et la suppression des données doivent être vérifiées pour chaque déploiement.</p>

<h2>Contenu actuel de la base</h2>
<ul>
    <li><?= (int) $statistiques['produits'] ?> produits (dont <?= (int) $statistiques['visibles'] ?> visibles, RB-19)</li>
    <li><?= (int) $statistiques['clients'] ?> clients</li>
    <li><?= (int) $statistiques['commandes'] ?> commandes</li>
    <li><?= (int) $statistiques['parametres'] ?> paramètres métier (frais de port, franchise, TVA)</li>
</ul>
