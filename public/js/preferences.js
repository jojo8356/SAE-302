/* Préférences propres au navigateur. Les coordonnées ne sont ni envoyées ni sauvegardées. */
(function () {
    'use strict';
    const cle = 'minishop.preferences.v1';
    const valideZone = (zone) => {
        if (typeof zone !== 'string' || zone.length > 100) return false;
        try { new Intl.DateTimeFormat('fr-FR', { timeZone: zone }).format(); return true; }
        catch (_) { return false; }
    };
    const langueDefaut = () => /^en(?:-|$)/i.test(navigator.language || '') ? 'en' : 'fr';
    let prefs = { langue: langueDefaut(), zone: '' };
    try {
        const stocke = JSON.parse(localStorage.getItem(cle) || 'null');
        if (stocke && (stocke.langue === 'fr' || stocke.langue === 'en')) prefs.langue = stocke.langue;
        if (stocke && valideZone(stocke.zone)) prefs.zone = stocke.zone;
    } catch (_) { /* mode privé ou stockage indisponible : repli en mémoire */ }
    const zoneActive = () => prefs.zone || (prefs.langue === 'en' ? 'America/New_York' : 'Europe/Paris');
    const parties = (instant, zone) => Object.fromEntries(new Intl.DateTimeFormat('en-GB', {
        timeZone: zone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
    }).formatToParts(instant).map(({ type, value }) => [type, value]));
    const jourLocal = (instant, zone) => {
        const p = parties(instant, zone);
        return `${p.year}-${p.month}-${p.day}`;
    };
    // Première seconde du jour civil : recherche dans une fenêtre UTC suffisamment large.
    // La date absente (ex. saut international de date) est refusée, jamais inventée.
    const borne = (jour, zone) => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(jour)) return null;
        const milieu = Date.parse(jour + 'T00:00:00Z');
        if (!Number.isFinite(milieu) || new Date(milieu).toISOString().slice(0, 10) !== jour) return null;
        let bas = Math.floor(milieu / 1000) - 36 * 3600;
        let haut = Math.floor(milieu / 1000) + 36 * 3600;
        while (bas < haut) {
            const m = Math.floor((bas + haut) / 2);
            if (jourLocal(new Date(m * 1000), zone) < jour) bas = m + 1;
            else haut = m;
        }
        if (jourLocal(new Date(bas * 1000), zone) !== jour) return null;
        return new Date(bas * 1000).toISOString().slice(0, 19).replace('T', ' ');
    };
    const suivant = (jour) => {
        const d = new Date(jour + 'T00:00:00Z');
        if (!Number.isFinite(d.getTime()) || d.toISOString().slice(0, 10) !== jour) return '';
        d.setUTCDate(d.getUTCDate() + 1);
        return d.toISOString().slice(0, 10);
    };
    function afficherDates() {
        document.querySelectorAll('time[data-date-format][datetime]').forEach((element) => {
            const instant = new Date(element.getAttribute('datetime'));
            if (!Number.isFinite(instant.getTime())) return;
            const p = parties(instant, zoneActive());
            const date = prefs.langue === 'en' ? `${p.month}/${p.day}/${p.year}` : `${p.day}/${p.month}/${p.year}`;
            const heure = prefs.langue === 'en' ? `${p.hour}:${p.minute}` : `${p.hour}h${p.minute}`;
            element.textContent = date + (element.dataset.dateFormat === 'date' ? '' : (element.dataset.dateFormat === 'ajout' ? (prefs.langue === 'en' ? ' at ' : ' à ') : ' ') + heure);
            const offset = new Intl.DateTimeFormat('en', { timeZone: zoneActive(), timeZoneName: 'shortOffset' }).formatToParts(instant).find((part) => part.type === 'timeZoneName').value;
            element.title = zoneActive() + ' · ' + offset.replace('GMT', 'UTC');
        });
    }

    const repli = {
        'Préférences': 'Preferences', 'Langue': 'Language', 'Langue et heure': 'Language and time',
        'Fuseau horaire ou ville': 'Time zone or city', 'Utiliser ma position': 'Use my location',
        'Enregistrer': 'Save', 'Réinitialiser': 'Reset', 'Catalogue': 'Catalog', 'Catégories': 'Categories',
        'Contact': 'Contact', 'Mon compte': 'My account', 'Mes commandes': 'My orders',
        'Connexion': 'Sign in', 'Créer un compte': 'Create account', 'Déconnexion': 'Sign out',
        'À propos': 'About', 'Panier': 'Cart', 'Menu': 'Menu', 'Commandes': 'Orders',
        'Produits': 'Products', 'Stocks': 'Stock', 'Paramètres': 'Settings', 'Équipe': 'Team',
        'Tableau de bord': 'Dashboard', 'Diagnostic staging': 'Staging diagnostics', 'Accueil': 'Home'
    };
    const traduireChaine = (source) => {
        if (window.MINISHOP_I18N_EN && typeof window.MINISHOP_I18N_EN.traduire === 'function') {
            return window.MINISHOP_I18N_EN.traduire(source);
        }
        return Object.prototype.hasOwnProperty.call(repli, source) ? repli[source] : source;
    };

    const originauxTexte = new WeakMap();
    const originauxAttributs = new WeakMap();
    const titreInitial = document.title;
    const attributsTraduits = ['aria-label', 'placeholder', 'title', 'alt', 'data-label', 'data-confirme'];
    const selecteurIgnore = 'script, style, noscript, textarea, code, pre, svg, time[data-date-format], #pref-decalage, #pref-etat, [aria-hidden="true"]';

    function traduire() {
        const anglais = prefs.langue === 'en';
        document.documentElement.lang = anglais ? 'en' : 'fr';
        if (titreInitial) {
            document.title = anglais ? traduireChaine(titreInitial) : titreInitial;
        }
        const metaDesc = document.querySelector('meta[name="description"]');
        if (metaDesc) {
            if (!originauxAttributs.has(metaDesc)) {
                originauxAttributs.set(metaDesc, { content: metaDesc.getAttribute('content') || '' });
            }
            const descFr = originauxAttributs.get(metaDesc).content;
            metaDesc.setAttribute('content', anglais ? traduireChaine(descFr) : descFr);
        }

        if (document.body) {
            const parcours = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
            while (parcours.nextNode()) {
                const noeud = parcours.currentNode;
                const parent = noeud.parentElement;
                if (!parent || parent.closest(selecteurIgnore)) continue;
                if (parent.tagName === 'OPTION' && (parent.hasAttribute('lang') || (parent.parentElement && parent.parentElement.id === 'pref-zone' && parent.value !== ''))) {
                    continue;
                }
                if (!originauxTexte.has(noeud)) {
                    originauxTexte.set(noeud, noeud.nodeValue);
                }
                const brutFr = originauxTexte.get(noeud);
                if (!anglais) {
                    if (noeud.nodeValue !== brutFr) noeud.nodeValue = brutFr;
                    continue;
                }
                const decoupe = brutFr.match(/^(\s*)([\s\S]*?)(\s*)$/);
                const coeur = decoupe[2].replace(/\s+/g, ' ').trim();
                if (!coeur) continue;
                const traduit = traduireChaine(coeur);
                if (traduit !== coeur) {
                    const avant = /^[,.;:?!)]/.test(traduit) ? '' : decoupe[1];
                    noeud.nodeValue = avant + traduit + decoupe[3];
                }
            }
        }

        document.querySelectorAll('[aria-label],[placeholder],[title],[alt],[data-label],[data-confirme]').forEach((el) => {
            let carte = originauxAttributs.get(el);
            if (!carte) {
                carte = {};
                originauxAttributs.set(el, carte);
            }
            for (let i = 0; i < attributsTraduits.length; i += 1) {
                const nom = attributsTraduits[i];
                if (!el.hasAttribute(nom)) continue;
                if (nom === 'title' && el.matches('time[data-date-format]')) continue;
                if (!Object.prototype.hasOwnProperty.call(carte, nom)) {
                    carte[nom] = el.getAttribute(nom);
                }
                const fr = carte[nom];
                const norm = fr.replace(/\s+/g, ' ').trim();
                if (!norm) continue;
                el.setAttribute(nom, anglais ? traduireChaine(norm) : fr);
            }
        });
    }

    function afficher() {
        traduire();
        afficherDates();
    }

    const formulaire = document.querySelector('#form-preferences');
    if (formulaire) {
        formulaire.hidden = false;
        const langue = document.querySelector('#pref-langue');
        const zone = document.querySelector('#pref-zone');
        const etat = document.querySelector('#pref-etat');
        const options = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : [];
        const zones = [...new Set(['Europe/Paris', 'America/New_York', 'Asia/Kathmandu', 'UTC', ...options, zoneActive()])].sort();
        const auto = document.createElement('option');
        auto.value = '';
        auto.textContent = 'Selon la langue (Paris / New York)';
        zone.append(auto);
        zones.forEach((nom) => {
            const o = document.createElement('option');
            o.value = nom;
            o.textContent = nom.replace(/_/g, ' ');
            zone.append(o);
        });
        langue.value = prefs.langue;
        zone.value = prefs.zone;
        const estEnFormulaire = () => (langue.value || prefs.langue) === 'en';
        function afficherDecalage() {
            const z = zone.value || (langue.value === 'en' ? 'America/New_York' : 'Europe/Paris');
            const nom = new Intl.DateTimeFormat('en', { timeZone: z, timeZoneName: 'shortOffset' }).formatToParts(new Date()).find((p) => p.type === 'timeZoneName').value;
            document.querySelector('#pref-decalage').textContent = z + ' · ' + nom.replace('GMT', 'UTC');
        }
        langue.addEventListener('change', afficherDecalage);
        zone.addEventListener('change', afficherDecalage);
        afficherDecalage();
        document.querySelector('#pref-position').addEventListener('click', () => {
            if (!navigator.geolocation || typeof tzlookup !== 'function') {
                etat.textContent = estEnFormulaire() ? 'Location unavailable. Choose a city manually.' : 'Localisation indisponible. Choisissez une ville manuellement.';
                return;
            }
            etat.textContent = estEnFormulaire() ? 'Detecting time zone…' : 'Recherche du fuseau…';
            navigator.geolocation.getCurrentPosition((position) => {
                try {
                    const trouve = tzlookup(position.coords.latitude, position.coords.longitude);
                    if (!valideZone(trouve)) throw new Error('unsupported zone');
                    if (![...zone.options].some((o) => o.value === trouve)) {
                        const o = document.createElement('option');
                        o.value = trouve;
                        o.textContent = trouve;
                        zone.append(o);
                    }
                    zone.value = trouve;
                    afficherDecalage();
                    etat.textContent = estEnFormulaire()
                        ? `Suggested time zone: ${trouve}. Review and save.`
                        : `Fuseau proposé : ${trouve}. Vérifiez puis enregistrez.`;
                } catch (_) {
                    etat.textContent = estEnFormulaire()
                        ? 'Time zone not found. Choose a city manually.'
                        : 'Fuseau introuvable. Choisissez une ville manuellement.';
                }
            }, () => {
                etat.textContent = estEnFormulaire()
                    ? 'Location denied or unavailable. Choose a city manually.'
                    : 'Position refusée ou indisponible. Choisissez une ville manuellement.';
            }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 0 });
        });
        formulaire.addEventListener('submit', (event) => {
            event.preventDefault();
            if (zone.value && !valideZone(zone.value)) {
                etat.textContent = estEnFormulaire() ? 'Invalid time zone.' : 'Fuseau invalide.';
                return;
            }
            prefs = { langue: langue.value, zone: zone.value };
            afficher();
            try {
                localStorage.setItem(cle, JSON.stringify(prefs));
                etat.textContent = prefs.langue === 'en' ? 'Preferences saved on this browser.' : 'Préférences enregistrées dans ce navigateur.';
            } catch (_) {
                etat.textContent = prefs.langue === 'en' ? 'Storage unavailable: choices apply only on this page.' : 'Stockage indisponible : choix actifs sur cette page uniquement.';
            }
        });
        document.querySelector('#pref-reinitialiser').addEventListener('click', () => {
            let efface = true;
            try { localStorage.removeItem(cle); } catch (_) { efface = false; }
            prefs = { langue: langueDefaut(), zone: '' };
            langue.value = prefs.langue;
            zone.value = '';
            afficherDecalage();
            afficher();
            etat.textContent = efface
                ? (prefs.langue === 'en' ? 'Preferences reset.' : 'Préférences réinitialisées.')
                : (prefs.langue === 'en' ? 'Storage inaccessible: clear site data in your browser.' : 'Stockage inaccessible : effacez les données du site dans votre navigateur.');
        });
    }

    const filtre = document.querySelector('[data-filtre-jours]');
    if (filtre) {
        const paramsUrl = new URLSearchParams(window.location.search);
        const duUrl = paramsUrl.get('du') || '';
        const auUrl = paramsUrl.get('au') || '';
        if (duUrl || auUrl) {
            const debutAttendu = duUrl ? borne(duUrl, zoneActive()) : '';
            const finAttendue = auUrl ? borne(suivant(auUrl), zoneActive()) : '';
            if (debutAttendu !== null && finAttendue !== null) {
                const champDebut = filtre.querySelector('[name="debut_utc"]');
                const champFin = filtre.querySelector('[name="fin_utc"]');
                if (champDebut) champDebut.value = debutAttendu;
                if (champFin) champFin.value = finAttendue;
                if ((duUrl && (paramsUrl.get('debut_utc') || '') !== debutAttendu) || (auUrl && (paramsUrl.get('fin_utc') || '') !== finAttendue)) {
                    if (duUrl) paramsUrl.set('debut_utc', debutAttendu); else paramsUrl.delete('debut_utc');
                    if (auUrl) paramsUrl.set('fin_utc', finAttendue); else paramsUrl.delete('fin_utc');
                    const cible = window.location.pathname + '?' + paramsUrl.toString();
                    if (typeof window.location.replace === 'function') {
                        window.location.replace(cible);
                    }
                }
            }
        }
        filtre.addEventListener('submit', (event) => {
            const du = filtre.querySelector('[name="du"]').value;
            const au = filtre.querySelector('[name="au"]').value;
            const debut = du ? borne(du, zoneActive()) : '';
            const fin = au ? borne(suivant(au), zoneActive()) : '';
            if ((du && !debut) || (au && !fin)) {
                event.preventDefault();
                alert(prefs.langue === 'en' ? 'Non-existent or unsupported date in this time zone.' : 'Date inexistante ou non prise en charge dans ce fuseau.');
                return;
            }
            filtre.querySelector('[name="debut_utc"]').value = debut;
            filtre.querySelector('[name="fin_utc"]').value = fin;
        });
    }

    const dashboard = document.querySelector('[data-dashboard-jour]');
    if (dashboard) {
        const jour = jourLocal(new Date(), zoneActive());
        const debut = borne(jour, zoneActive());
        const fin = borne(suivant(jour), zoneActive());
        if (debut && fin) {
            const params = new URLSearchParams({ jour_local: jour, debut_utc: debut, fin_utc: fin });
            fetch('/admin?' + params, { credentials: 'same-origin' }).then((r) => {
                if (!r.ok) throw new Error('HTTP');
                return r.text();
            }).then((html) => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                if (doc.querySelector('[data-dashboard-jour]')?.dataset.jourAccepte !== 'oui') throw new Error('Bornes refusées');
                for (const nom of ['data-ca-jour', 'data-commandes-jour']) {
                    const cible = dashboard.querySelector('[' + nom + ']');
                    const source = doc.querySelector('[' + nom + ']');
                    if (!source || !cible) throw new Error('Données absentes');
                    cible.textContent = source.textContent;
                }
                const info = document.querySelector('[data-jour-info]');
                if (info) {
                    info.textContent = (prefs.langue === 'en' ? "Today's metrics: " : 'Indicateurs du jour : ') + zoneActive();
                }
            }).catch(() => {
                const info = document.querySelector('[data-jour-info]');
                if (info) {
                    info.textContent = prefs.langue === 'en'
                        ? "Today's metrics unavailable in your time zone; Paris values shown."
                        : 'Indicateurs du jour indisponibles dans votre fuseau ; valeurs de Paris affichées.';
                }
            });
        }
    }
    afficher();
}());
