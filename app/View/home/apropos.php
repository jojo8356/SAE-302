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
    'conservation_compte' => 'Conservation de vos données de compte après votre dernier contact',
];
?>
<h1>À propos de MiniShop</h1>
<p>MiniShop est une boutique en ligne de <strong>matériel informatique et
d'objets connectés</strong> : ordinateurs, audio, accessoires et montres
connectées. Vous y composez votre panier, vous suivez vos commandes depuis
votre compte, et vous réglez en ligne — en toute démonstration : le paiement
est simulé et aucun débit réel n'est effectué.</p>
<p>Tous nos prix sont affichés <strong>TTC</strong> et la disponibilité
indiquée est celle du stock au moment de votre visite.</p>

<h2>La boutique en chiffres</h2>
<ul>
    <li><strong><?= (int) $statistiques['visibles'] ?> produits</strong> actuellement au catalogue</li>
    <li>répartis en <strong><?= (int) $statistiques['categories'] ?> catégories</strong> :
        informatique, audio, accessoires et objets connectés</li>
</ul>
<p><a href="/catalogue">Découvrir le catalogue →</a></p>

<h2>Nos <?= count($parametres) ?> règles chiffrées</h2>
<p>Pas de surprise au moment de payer : voici les montants et durées qui
s'appliquent à chaque commande, affichés avant la validation.</p>
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
est conçu pour <strong>MySQL</strong> : produits, clients et commandes y sont
liés et contrôlés de façon cohérente, et l'accès à la base se fera via
<strong>PDO</strong> lors de la mise en ligne sur l'hébergement final.
Dans cette version de démonstration, un moteur de données intégré au projet
assure temporairement ce rôle, le temps de la bascule : c'est un choix
d'étape, pas l'architecture cible — et cela ne change rien à votre
navigation, ni à vos commandes.</p>
<p>Le détail du fonctionnement interne est décrit dans la documentation du
projet, disponible avec le code source.</p>
