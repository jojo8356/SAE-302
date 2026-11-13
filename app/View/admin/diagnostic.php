<?php

declare(strict_types=1);

/** @var bool $actif @var list<array{titre:string,controles:list<array{libelle:string,etat:string,detail:string}>}> $sections @var array<string,int> $compteurs @var int $nombreControles */
$libellesEtat = [
    'ok' => 'Prêt',
    'attention' => 'À vérifier',
    'bloque' => 'Bloquant',
    'manuel' => 'À confirmer',
    'info' => 'Information',
];
$classesEtat = [
    'ok' => 'diagnostic__etat--ok',
    'attention' => 'diagnostic__etat--attention',
    'bloque' => 'diagnostic__etat--bloque',
    'manuel' => 'diagnostic__etat--manuel',
    'info' => 'diagnostic__etat--info',
];
?>
<div class="diagnostic">
    <header class="entete-page">
        <div>
            <p class="fil-ariane">Back-office <span aria-hidden="true">/</span> D1-L15-01</p>
            <h1>Diagnostic de staging — D1-L15-01</h1>
            <p class="chapo">État des prérequis visibles depuis MiniShop, sans lancer le déploiement ni modifier les données.</p>
        </div>
        <span class="badge badge--info">Lecture seule</span>
    </header>

    <?php if (!$actif): ?>
        <section class="diagnostic__panneau" aria-labelledby="diagnostic-desactive">
            <h2 id="diagnostic-desactive">Diagnostic staging désactivé</h2>
            <p>Par défaut, cette page ne révèle aucun renseignement d’environnement. Pour l’activer, définir
                <code>MINISHOP_STAGING_DIAGNOSTIC=1</code> dans la configuration privée d’une instance de staging isolée,
                puis recharger cette page.</p>
            <p class="diagnostic__alerte" role="note">Ne jamais activer ce drapeau en production. Il s’agit d’une activation explicite, pas d’une preuve que la base est bien celle du staging.</p>
        </section>
    <?php else: ?>
        <div class="diagnostic__alerte" role="note">
            <strong>Limite importante :</strong> les contrôles verts décrivent uniquement le processus PHP web.
            Ils ne valident ni le PHP CLI, ni la cible SQL, ni les comptes de démonstration. Cette page ne tente aucune
            connexion SQL de diagnostic et n’exécute jamais <code>scripts/deploy.php</code>. L’authentification SUPER
            utilise les lectures normales de session MiniShop.
        </div>

        <section class="diagnostic__synthese" aria-labelledby="diagnostic-synthese">
            <div>
                <h2 id="diagnostic-synthese">Synthèse du diagnostic web</h2>
                <p><?= (int) $nombreControles ?> contrôles affichés. Ce résultat ne constitue pas une autorisation de déploiement ni une validation de D1-L15-01.</p>
            </div>
            <dl class="diagnostic__totaux">
                <div><dt>Prêts</dt><dd><?= (int) ($compteurs['ok'] ?? 0) ?></dd></div>
                <div><dt>À vérifier</dt><dd><?= (int) ($compteurs['attention'] ?? 0) ?></dd></div>
                <div><dt>À confirmer</dt><dd><?= (int) ($compteurs['manuel'] ?? 0) ?></dd></div>
                <div><dt>Bloquants</dt><dd><?= (int) ($compteurs['bloque'] ?? 0) ?></dd></div>
                <div><dt>Information</dt><dd><?= (int) ($compteurs['info'] ?? 0) ?></dd></div>
            </dl>
        </section>

        <div class="diagnostic__grille">
            <?php foreach ($sections as $index => $section): ?>
                <section class="diagnostic__section" aria-labelledby="diagnostic-section-<?= (int) $index ?>">
                    <h2 id="diagnostic-section-<?= (int) $index ?>"><?= e($section['titre']) ?></h2>
                    <ul class="diagnostic__liste">
                        <?php foreach ($section['controles'] as $controle): ?>
                            <?php
                            $etat = (string) ($controle['etat'] ?? 'info');
                            $classeEtat = array_key_exists($etat, $libellesEtat) ? $etat : 'info';
                            ?>
                            <li class="diagnostic__controle">
                                <div class="diagnostic__texte">
                                    <h3 class="diagnostic__libelle"><?= e($controle['libelle']) ?></h3>
                                    <p class="diagnostic__detail"><?= e($controle['detail']) ?></p>
                                </div>
                                <span class="diagnostic__etat <?= e($classesEtat[$classeEtat]) ?>"><?= e($libellesEtat[$classeEtat]) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        </div>

        <section class="diagnostic__panneau" aria-labelledby="diagnostic-suite">
            <h2 id="diagnostic-suite">Suite requise pour D1-L15-01</h2>
            <ul>
                <li>Confirmer hors de cette page que l’hôte et la base sont ceux du staging isolé, avec des données synthétiques et une sauvegarde de staging.</li>
                <li>Vérifier la configuration effective du PHP CLI et les éventuelles priorités de <code>app/Config/env.php</code>. Le script ne lit pas le fichier <code>.env</code> ; il attend notamment <code>DB_PASS</code>, pas seulement <code>DB_PASSWORD</code>.</li>
                <li>Suivre <code>docs/22-procedure-validation-staging.md</code> uniquement sur staging. La sortie de <code>scripts/deploy.php</code> contient des mots de passe en clair : la conserver temporairement dans un fichier privé et expurger la preuve.</li>
                <li>Garder le statut <strong>PARTIEL</strong> jusqu’à la validation autorisée et la conservation d’une preuve expurgée.</li>
            </ul>
            <p><a class="btn btn--discret" href="/admin/diagnostic">Actualiser le diagnostic</a></p>
        </section>
    <?php endif; ?>
</div>
