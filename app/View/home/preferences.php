<?php declare(strict_types=1); ?>
<section class="preferences" aria-labelledby="titre-preferences">
    <h1 id="titre-preferences">Préférences</h1>
    <p>Ces choix restent dans ce navigateur et ne modifient pas votre compte. Effacer les données du site les réinitialise.</p>
    <noscript><p class="message message--erreur">JavaScript est nécessaire pour enregistrer les préférences. Sans lui, les dates sont affichées à l’heure de Paris.</p></noscript>
    <form id="form-preferences" hidden>
        <fieldset>
            <legend>Langue et heure</legend>
            <label for="pref-langue">Langue</label>
            <select id="pref-langue"><option value="fr" lang="fr">Français</option><option value="en" lang="en">English</option></select>
            <p id="traduction-perimetre">L’interface et les pages d’information sont traduites (français / anglais) ; les fiches saisies au catalogue conservent leur langue d’enregistrement.</p>
            <label for="pref-zone">Fuseau horaire ou ville</label>
            <select id="pref-zone" aria-describedby="pref-aide"></select>
            <p id="pref-decalage" aria-live="polite"></p>
            <p id="pref-aide">Choisissez une ville ou demandez votre position. L’heure d’été est calculée pour chaque date.</p>
            <button class="btn btn--secondaire" type="button" id="pref-position">Utiliser ma position</button>
            <p>Votre navigateur demandera votre accord. Les coordonnées servent uniquement à déterminer le fuseau sur cet appareil : MiniShop ne les conserve pas et ne les transmet pas. La détection est approximative ; vérifiez le fuseau proposé, notamment près des frontières. Réinitialiser efface le choix enregistré ici, mais ne révoque pas l’autorisation de localisation : pour la retirer, utilisez les paramètres du navigateur pour ce site.</p>
            <div class="preferences__actions">
                <button class="btn btn--acheter" type="submit">Enregistrer</button>
                <button class="btn btn--secondaire" type="button" id="pref-reinitialiser">Réinitialiser</button>
            </div>
            <p role="status" aria-live="polite" id="pref-etat"></p>
        </fieldset>
    </form>
</section>
