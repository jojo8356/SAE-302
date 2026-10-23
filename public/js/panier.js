/* =====================================================================
   MiniShop — panier sans rechargement (EF-CLI-03, DA §8)
   ---------------------------------------------------------------------
   Amélioration progressive : SANS JavaScript, les formulaires font un
   POST classique et tout fonctionne (ENF-07). Avec JavaScript, on évite
   le rechargement et on met à jour le badge + le total.
   Règle absolue : AUCUN prix, AUCUN stock n'est calculé ici. Le serveur
   renvoie les valeurs affichées (EF-GEN-01).
   ===================================================================== */
(function () {
    'use strict';

    var badge = document.getElementById('badge-panier');
    var zone = document.getElementById('zone-annonces');
    var total = document.querySelector('[data-total-panier]');

    /** Affiche un message dans la zone aria-live (texte uniquement, jamais de HTML injecté). */
    function annoncer(message, type) {
        if (!zone) { return; }
        zone.textContent = message;
        zone.className = 'message ' + (type === 'erreur' ? 'message--erreur' : 'message--succes');
        zone.hidden = false;
        zone.setAttribute('role', type === 'erreur' ? 'alert' : 'status');
    }

    function majBadge(nombre) {
        if (!badge) { return; }
        badge.textContent = String(nombre);
        badge.hidden = nombre <= 0;
        var lien = badge.closest('a');
        if (lien) {
            lien.setAttribute('aria-label', 'Panier, ' + nombre + ' article' + (nombre > 1 ? 's' : ''));
        }
    }

    function majTotal(valeur) {
        if (total && typeof valeur === 'string') {
            total.textContent = valeur;
        }
    }

    /** Envoie un formulaire en arrière-plan et applique la réponse serveur. */
    function envoyer(formulaire, action) {
        var bouton = formulaire.querySelector('button[type="submit"]');
        var libelle = bouton ? bouton.textContent : '';
        if (bouton) {
            bouton.disabled = true;
            bouton.textContent = 'Ajout…';
        }

        var donnees = new URLSearchParams(new FormData(formulaire));

        return fetch(action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-Requested-With': 'fetch'
            },
            body: donnees.toString(),
            credentials: 'same-origin'
        })
            .then(function (reponse) {
                return reponse.json().catch(function () {
                    return { ok: false, message: 'Réponse inattendue du serveur.' };
                }).then(function (charge) {
                    charge.statut_http = reponse.status;
                    return charge;
                });
            })
            .then(function (charge) {
                var ok = charge.ok === true;
                annoncer(charge.message || (ok ? 'Produit ajouté au panier.' : 'Ajout impossible.'), ok ? 'succes' : 'erreur');
                if (ok) {
                    majBadge(charge.nombre_articles);
                    majTotal(charge.total_ttc);
                } else if (bouton) {
                    bouton.disabled = true;
                    bouton.setAttribute('aria-disabled', 'true');
                }
                return charge;
            })
            .catch(function () {
                annoncer('Connexion perdue : le formulaire classique reste disponible.', 'erreur');
                formulinaireSecours(formulaire);
            })
            .then(function () {
                if (bouton && !bouton.getAttribute('aria-disabled')) {
                    bouton.disabled = false;
                    bouton.textContent = libelle;
                }
            });
    }

    /** Si le réseau tombe, on laisse le navigateur faire un POST normal. */
    function formulinaireSecours(formulaire) {
        formulaire.dataset.secours = '1';
        formulaire.submit();
    }

    /* --- Ajout au panier ------------------------------------------------ */
    var formulaires = document.querySelectorAll('form[data-panier]');
    Array.prototype.forEach.call(formulaires, function (formulaire) {
        formulaire.addEventListener('submit', function (evenement) {
            if (formulaire.dataset.secours === '1') { return; }
            if (!window.fetch || !window.URLSearchParams) { return; }
            evenement.preventDefault();
            envoyer(formulaire, '/api/panier/ajouter');
        });
    });

    /* --- Filtres du catalogue : application immédiate (EF-VIS-03) ------- */
    var filtres = document.querySelectorAll('form[data-filtres]');
    Array.prototype.forEach.call(filtres, function (formulaire) {
        Array.prototype.forEach.call(formulaire.querySelectorAll('select, input[type="checkbox"]'), function (champ) {
            champ.addEventListener('change', function () {
                formulaire.dataset.applique = '1';
                if (typeof formulaire.requestSubmit === 'function') {
                    formulaire.requestSubmit();
                } else {
                    formulaire.submit();
                }
            });
        });
    });

    /* --- Confirmations d'action destructive ----------------------------- */
    Array.prototype.forEach.call(document.querySelectorAll('form[data-confirme]'), function (formulaire) {
        formulaire.addEventListener('submit', function (evenement) {
            var message = formulaire.getAttribute('data-confirme') || 'Confirmer cette action ?';
            if (!window.confirm(message)) {
                evenement.preventDefault();
            }
        });
    });
}());
