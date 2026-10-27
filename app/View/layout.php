<?php

declare(strict_types=1);

/**
 * MiniShop — gabarit commun (DA `docs/DA.md`, WCAG 2.1 AA).
 *
 * Variables attendues : $contenu, $titrePage, $client, $admin,
 * $flashSucces, $flashErreur.
 *
 * Choix structurants :
 *   - lien d'évitement en premier élément focusable (2.4.1) ;
 *   - en-tête collant, navigation à 44 px de haut (2.5.5) ;
 *   - badge panier annoncé par `aria-live` (4.1.3) ;
 *   - un seul <h1> par page, fourni par la vue ;
 *   - back-office : barre latérale détectée depuis l'URL, même grammaire visuelle.
 */

$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$espaceAdmin = str_starts_with((string) parse_url($uri, PHP_URL_PATH), '/admin');
$cheminBrut = parse_url($uri, PHP_URL_PATH);
$cheminCourant = '/';
if (is_string($cheminBrut) && $cheminBrut !== '') {
    $cheminCourant = $cheminBrut;
}

$nbArticlesPanier = 0;
try {
    $nbArticlesPanier = \App\Model\PanierSession::chargé()->nombreArticles();
} catch (Throwable) {
    // un panier cassé ne doit jamais empêcher l'affichage d'une page
}

$liensAdmin = [
    '/admin' => 'Tableau de bord',
    '/admin/produits' => 'Produits',
    '/admin/categories' => 'Catégories',
    '/admin/stocks' => 'Stocks',
    '/admin/commandes' => 'Commandes',
    '/admin/parametres' => 'Paramètres',
    '/admin/equipe' => 'Équipe',
];

/**
 * Cette page est-elle celle du lien ? (exact, ou préfixe pour les sous-pages)
 *
 * Un seul calcul, réutilisé par tous les liens : mélanger les deux logiques
 * donnait des incohérences visibles — « Catégories » restait marqué actif sur
 * /contact parce que ce lien-là, écrit à la main, ne calculait rien du tout.
 */
$estCourant = static function (string $href) use ($cheminCourant): bool {
    if ($cheminCourant === $href) {
        return true;
    }
    if ($href === '/' || !str_starts_with($cheminCourant, $href)) {
        return false;
    }

    // Préfixe, mais sur une frontière de segment : /catalogue ne doit pas
    // marquer actif /catalogue-promo, ni /compte marquer /compte-rendu.
    $suite = substr($cheminCourant, strlen($href), 1);

    return $suite === '' || $suite === '/' || $suite === '?';
};

/** Lien de navigation avec état courant exposé aux technologies d'assistance. */
$lienNav = static function (string $href, string $libelle) use ($estCourant): string {
    $courant = $estCourant($href);
    $attribut = '';
    if ($courant) {
        $attribut = ' aria-current="page"';
    }

    return '<li><a href="' . e($href) . '"' . $attribut . '>' . e($libelle) . '</a></li>';
};
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="MiniShop — catalogue en ligne de matériel informatique et d'objets connectés. Prix TTC, stock en temps réel, commande et suivi.">
    <meta name="theme-color" content="#1D4ED8">
    <title><?= e($titrePage ?? 'MiniShop') ?> — MiniShop</title>
    <link rel="icon" href="/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/css/app.css">
    <link rel="stylesheet" href="/css/print.css" media="print">
    <script src="/js/panier.js" defer></script>
    <script src="/js/ui.js" defer></script>
</head>
<body>
<a class="lien-evitement" href="#contenu">Aller au contenu principal</a>

<header class="entete">
    <div class="conteneur entete__barre">
        <a class="marque" href="/" aria-label="MiniShop — accueil">
            <?= icone('eclair', 28) ?>
            <span>MiniShop</span>
        </a>

        <nav class="nav-principal" aria-label="Navigation principale">
            <details class="nav-principal__details" data-menu open>
                <summary class="nav-principal__bouton">
                    <?= icone('menu', 22) ?>
                    <span>Menu</span>
                </summary>
                <ul class="nav-principal__liste">
                    <?= $lienNav('/catalogue', 'Catalogue') // HTML-SAFE : échappé par le helper ?>
                <?= $lienNav('/categories', 'Catégories') // HTML-SAFE : échappé par le helper ?>
                <?= $lienNav('/contact', 'Contact') // HTML-SAFE : échappé par le helper ?>
                <li aria-hidden="true" class="nav-separateur"></li>
                <li>
                    <a href="/panier"<?php if ($estCourant('/panier')) { echo ' aria-current="page"'; } ?> aria-label="Panier, <?= (int) $nbArticlesPanier ?> article<?php if ($nbArticlesPanier > 1) { echo 's'; } else { echo ''; } ?>">
                        <?= icone('panier', 20) ?>
                        <span>Panier</span>
                        <span class="pastille-panier" id="badge-panier" <?php if ($nbArticlesPanier <= 0) { echo 'hidden'; } ?>><?= (int) $nbArticlesPanier ?></span>
                    </a>
                </li>
                <?php if (($client ?? null) !== null): ?>
                    <li><a href="/compte"<?php if ($estCourant('/compte')) { echo ' aria-current="page"'; } ?>>Mon compte (<?= e($client['prenom']) ?>)</a></li>
                    <?= $lienNav('/mes-commandes', 'Mes commandes') // HTML-SAFE : échappé par le helper ?>
                    <li><a href="/deconnexion">Déconnexion</a></li>
                <?php elseif (($admin ?? null) === null): ?>
                    <?= $lienNav('/connexion', 'Connexion') // HTML-SAFE : échappé par le helper ?>
                    <?= $lienNav('/inscription', 'Créer un compte') // HTML-SAFE : échappé par le helper ?>
                <?php endif; ?>
                <?php if (($admin ?? null) !== null): ?>
                    <li><a href="/admin">Back-office (<?= e($admin['role']) ?>)</a></li>
                    <li><a href="/admin/deconnexion">Quitter le BO</a></li>
                <?php endif; ?>
                </ul>
            </details>
        </nav>
    </div>
</header>

<main id="contenu"<?php if ($espaceAdmin && ($admin ?? null) !== null): ?> class="conteneur"<?php endif; ?>>
    <p class="message visuellement-cache" id="zone-annonces" role="status" aria-live="polite" hidden></p>

    <?php if (!empty($flashSucces)): ?>
        <p class="message message--succes" role="status">
            <?= icone('coche', 20) ?><span><?= e($flashSucces) ?></span>
        </p>
    <?php endif; ?>
    <?php if (!empty($flashErreur)): ?>
        <p class="message message--erreur" role="alert">
            <?= icone('alerte', 20) ?><span><?= e($flashErreur) ?></span>
        </p>
    <?php endif; ?>

    <?php if ($espaceAdmin && ($admin ?? null) !== null): ?>
        <div class="admin">
            <nav class="admin__nav" aria-label="Navigation du back-office">
                <p>Back-office</p>
                <ul>
                    <?php foreach ($liensAdmin as $href => $libelle): ?>
                        <?php
                        $courant = $cheminCourant === $href;
                        $attribut = '';
                        if ($courant) {
                            $attribut = ' aria-current="page"';
                        }
                        ?>
                        <li><a href="<?= e($href) ?>"<?= $attribut ?>><?= e($libelle) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <div class="admin__contenu">
                <?= $contenu ?>
            </div>
        </div>
    <?php else: ?>
        <?= $contenu ?>
    <?php endif; ?>
</main>

<footer class="pied">
    <div class="conteneur">
        <div class="pied__grille">
            <section>
                <h2>MiniShop</h2>
                <p class="texte-aide pied__texte">
                    Matériel informatique et objets connectés.<br>
                    Prix TTC, stock réel, commande suivie.
                </p>
            </section>
            <section>
                <h2>Boutique</h2>
                <ul>
                    <li><a href="/catalogue">Catalogue</a></li>
                    <li><a href="/categories">Catégories</a></li>
                    <li><a href="/panier">Panier</a></li>
                    <li><a href="/mes-commandes">Mes commandes</a></li>
                </ul>
            </section>
            <section>
                <h2>Informations</h2>
                <ul>
                    <li><a href="/a-propos">À propos</a></li>
                    <?= $lienNav('/contact', 'Contact') // HTML-SAFE : échappé par le helper ?>
                    <li><a href="/cgv">Conditions générales de vente</a></li>
                    <li><a href="/mentions-legales">Mentions légales</a></li>
                </ul>
            </section>
            <section>
                <h2>Confiance</h2>
                <ul>
                    <li><?= icone('cadenas', 16) ?> Paiement simulé (projet pédagogique)</li>
                    <li><?= icone('retour', 16) ?> Annulation avant expédition</li>
                    <li><?= icone('camion', 16) ?> Frais de port annoncés avant validation</li>
                </ul>
            </section>
        </div>
        <p class="pied__bas">
            MiniShop — maquette pédagogique SAE. Aucun paiement réel, aucune donnée personnelle conservée.
            <br>Projet PHP / MySQL — Université Côte d'Azur.
        </p>
    </div>
</footer>
</body>
</html>
