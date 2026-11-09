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

    /* ------------------------------------------------------------------
       Menu principal repliable (≤ 64em ≈ 1024 px).
       Le <details> est rendu OUVERT dans le HTML : sans JavaScript, tous
       les liens restent accessibles (ENF-07). Ici on le referme sur petit
       écran, on le rouvre au-delà du palier, on gère Échap et le clic
       extérieur — les trois comportements attendus d'un menu disclosure.
       ------------------------------------------------------------------ */
    var menu = document.querySelector('[data-menu]');
    if (menu) {
        var boutonMenu = menu.querySelector('summary');
        var petitEcran = window.matchMedia('(max-width: 64em)');

        var synchroniser = function () {
            if (petitEcran.matches) {
                menu.removeAttribute('open');
            } else {
                menu.setAttribute('open', '');
            }
        };
        synchroniser();

        if (typeof petitEcran.addEventListener === 'function') {
            petitEcran.addEventListener('change', synchroniser);
        } else if (typeof petitEcran.addListener === 'function') {
            petitEcran.addListener(synchroniser);
        }

        document.addEventListener('keydown', function (evenement) {
            if (evenement.key !== 'Escape' || !menu.hasAttribute('open') || !petitEcran.matches) {
                return;
            }
            menu.removeAttribute('open');
            if (boutonMenu) { boutonMenu.focus(); }
        });

        document.addEventListener('click', function (evenement) {
            if (!petitEcran.matches || !menu.hasAttribute('open')) { return; }
            if (!menu.contains(evenement.target)) { menu.removeAttribute('open'); }
        });
    }

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
