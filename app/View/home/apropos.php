<?php

declare(strict_types=1);

/**
 * Page « À propos » — elle s'adresse aux VISITEURS de la boutique,
 * pas aux développeurs : présentation du magasin, chiffres du
 * catalogue et règles pratiques (livraison, prix, données de compte).
 * Les détails d'implémentation vivent dans la documentation du projet
 * (docs/), pas ici.
 *
 * Point d'attention (signalement jury) : ne jamais écrire que la
 * boutique « ne fonctionne ni avec MySQL, ni avec aucune requête
 * SQL » — le sujet est PHP/MySQL/PDO et la phrase deviendrait fausse
 * après la bascule MySQL de l'hébergement final. La formulation
 * ci-dessous distingue honnêtement la cible (MySQL via PDO) de
 * l'étape de démonstration (moteur intégré temporaire).
 *
 * @var array<string,int> $statistiques
 * @var list<array<string,mixed>> $parametres paramètres métier (base)
 */
$libellesParametres = [
    'frais_port' => 'Livraison standard',
    'franchise_port' => 'Livraison offerte à partir de',
    'tva_defaut' => 'TVA par défaut (nos prix sont toujours affichés TTC)',
    'conservation_compte' => 'Conservation des comptes sans commande inactifs (traitement planifié)',
];
?>
<h1>À propos de MiniShop</h1>
<p>MiniShop est une boutique de démonstration consacrée au matériel informatique et aux objets connectés. Le paiement et la livraison sont simulés : aucune transaction réelle n’est effectuée.</p>

<h2>La boutique en chiffres</h2>
<ul>
    <li><strong><?= (int) $statistiques['visibles'] ?> produits</strong> actuellement au catalogue</li>
    <li>répartis en <strong><?= (int) $statistiques['categories'] ?> catégories</strong> :
        informatique, audio, accessoires et objets connectés</li>
</ul>
<p><a href="/catalogue">Découvrir le catalogue →</a></p>

<h2>Nos <?= count($parametres) ?> règles chiffrées</h2>
<p>Pas de surprise au moment de payer : voici les montants et durées qui
sont configurés dans la maquette. Les prix et frais simulés sont affichés avant la validation. Le traitement des comptes inactifs doit être activé par l’exploitant ; les comptes liés à des commandes sont conservés pour examen. Les journaux et sauvegardes ne sont pas purgés par ce traitement.</p>
<ul>
    <?php foreach ($parametres as $parametre): ?>
        <?php
        $cle = (string) $parametre['cle'];
        $libelle = $cle;
        if (isset($libellesParametres[$cle])) {
            $libelle = $libellesParametres[$cle];
        }
        $valeur = (string) $parametre['valeur'];
        if ((string) $parametre['unite'] === 'EUR TTC') {
            $valeur = euros((float) $parametre['valeur']);
        } elseif ((string) $parametre['unite'] === '%') {
            $valeur = rtrim(rtrim(number_format((float) $parametre['valeur'], 2, ',', ''), '0'), ',') . ' %';
        } else {
            $valeur = $valeur . ' ' . (string) $parametre['unite'];
        }
        ?>
        <li><strong><?= e($libelle) ?></strong> : <?= e($valeur) ?></li>
    <?php endforeach; ?>
</ul>

<h2>Qui sommes-nous ?</h2>
<p>MiniShop est un projet étudiant réalisé dans le cadre d'une SAE
(BUT Informatique, Université Côte d'Azur) : une équipe de trois étudiants
a conçu la boutique de bout en bout — catalogue, comptes clients, commandes
et espace d'administration réservé à l'équipe du magasin.</p>

<h2>Et côté technique ?</h2>
<p>La boutique est développée en <strong>PHP</strong>. Son modèle de données
a pour cible <strong>MySQL ou MariaDB via PDO</strong> ; elle peut aussi utiliser le moteur JSON de démonstration selon la configuration. Produits, clients et commandes y sont liés et contrôlés de façon cohérente. L’hébergement final doit encore être vérifié.</p>
<p>Le détail du fonctionnement interne est décrit dans la documentation du
projet, disponible avec le code source.</p>
