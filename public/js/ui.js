/* =====================================================================
   MiniShop — comportements transverses (DA §8, WCAG 2.1 AA)
   ---------------------------------------------------------------------
   - focus sur le contenu après activation du lien d'évitement ;
   - retour au premier champ en erreur après un refus serveur ;
   - compteur de caractères des zones de texte longues ;
   - fermeture des messages flash.
   Aucune donnée métier ici : uniquement du confort (ENF-07).
   ===================================================================== */
(function () {
    'use strict';

    /* Lien d'évitement : donne le focus réel au contenu (2.4.1). */
    var evitement = document.querySelector('.lien-evitement');
    var contenu = document.getElementById('contenu');
    if (evitement && contenu) {
        evitement.addEventListener('click', function () {
            contenu.setAttribute('tabindex', '-1');
            contenu.focus();
        });
    }

    /* Après un refus serveur, on ramène l'utilisateur sur le premier champ
       marqué invalide ou, à défaut, sur le résumé d'erreurs. */
    var invalide = document.querySelector('[aria-invalid="true"]');
    if (invalide) {
        invalide.focus();
        if (typeof invalide.scrollIntoView === 'function') {
            invalide.scrollIntoView({ block: 'center' });
        }
        return;
    }

    var alerte = document.querySelector('.message--erreur');
    if (alerte) {
        if (typeof alerte.scrollIntoView === 'function') {
            alerte.scrollIntoView({ block: 'center' });
        }
    }

    /* Compteur de caractères : <textarea data-compteur="500"> */
    Array.prototype.forEach.call(document.querySelectorAll('textarea[data-compteur]'), function (zone) {
        var max = parseInt(zone.getAttribute('data-compteur'), 10);
        if (!max) { return; }
        var info = document.createElement('p');
        info.className = 'texte-aide';
        info.id = (zone.id || 'zone') + '-compteur';
        info.setAttribute('aria-live', 'polite');
        zone.insertAdjacentElement('afterend', info);
        zone.setAttribute('aria-describedby', info.id);

        function maj() {
            info.textContent = zone.value.length + ' / ' + max + ' caractères';
        }
        zone.addEventListener('input', maj);
        maj();
    });
}());
