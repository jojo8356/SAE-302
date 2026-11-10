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
            const offset = new Intl.DateTimeFormat('en', { timeZone: zoneActive(), timeZoneName: 'shortOffset' }).formatToParts(instant).find((p) => p.type === 'timeZoneName').value;
            element.title = zoneActive() + ' · ' + offset.replace('GMT', 'UTC');
        });
    }
    const textes = {
        'Préférences': 'Preferences', 'Langue': 'Language', 'Langue et heure': 'Language and time',
        'Fuseau horaire ou ville': 'Time zone or city', 'Utiliser ma position': 'Use my location',
        'Enregistrer': 'Save', 'Réinitialiser': 'Reset',
        'Ces choix restent dans ce navigateur et ne modifient pas votre compte. Effacer les données du site les réinitialise.': 'These choices stay in this browser and do not change your account. Clearing site data resets them.',
        'La traduction anglaise du site est en cours : certaines pages restent en français.': 'The English translation is in progress: some pages remain in French.',
        'Choisissez une ville ou demandez votre position. L’heure d’été est calculée pour chaque date.': 'Choose a city or request your location. Daylight saving time is calculated for each date.',
        'Votre navigateur demandera votre accord. Les coordonnées servent uniquement à déterminer le fuseau sur cet appareil : MiniShop ne les conserve pas et ne les transmet pas. La détection est approximative ; vérifiez le fuseau proposé, notamment près des frontières. Réinitialiser efface le choix enregistré ici, mais ne révoque pas l’autorisation de localisation : pour la retirer, utilisez les paramètres du navigateur pour ce site.': 'Your browser will ask for permission. Coordinates are used only on this device to determine the time zone: MiniShop does not save or send them. Detection is approximate; check the suggested time zone, especially near borders. Reset removes the saved choice but does not revoke location permission: use your browser’s site settings to revoke it.',
        'Catalogue': 'Catalog', 'Catégories': 'Categories', 'Contact': 'Contact', 'Mon compte': 'My account', 'Mes commandes': 'My orders', 'Connexion': 'Sign in', 'Créer un compte': 'Create account', 'Déconnexion': 'Sign out', 'À propos': 'About', 'Panier': 'Cart', 'Menu': 'Menu', 'Commandes': 'Orders', 'Produits': 'Products', 'Stocks': 'Stock', 'Paramètres': 'Settings', 'Équipe': 'Team', 'Tableau de bord': 'Dashboard', 'Accueil': 'Home'
    };
    // Ne modifie ni les données utilisateur ni les textes juridiques ; la traduction complète reste à réaliser.
    function traduire() {
        document.querySelectorAll('nav a, nav summary span, .preferences h1, .preferences legend, .preferences label, .preferences p, .preferences button').forEach((el) => {
            if (!el.dataset.texteFr) el.dataset.texteFr = el.textContent.trim();
            const fr = el.dataset.texteFr;
            if (el.children.length === 0) {
                el.textContent = prefs.langue === 'en' ? (textes[fr] || fr) : fr;
                if (prefs.langue === 'en' && textes[fr]) el.lang = 'en'; else el.removeAttribute('lang');
            }
        });
    }
    function afficher() { afficherDates(); traduire(); }
    const formulaire = document.querySelector('#form-preferences');
    if (formulaire) {
        formulaire.hidden = false;
        const langue = document.querySelector('#pref-langue');
        const zone = document.querySelector('#pref-zone');
        const etat = document.querySelector('#pref-etat');
        const options = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : [];
        const zones = [...new Set(['Europe/Paris', 'America/New_York', 'Asia/Kathmandu', 'UTC', ...options, zoneActive()])].sort();
        const auto = document.createElement('option'); auto.value = ''; auto.textContent = 'Selon la langue / By language (Paris / New York)'; zone.append(auto);
        zones.forEach((nom) => { const o = document.createElement('option'); o.value = nom; o.textContent = nom.replace(/_/g, ' '); zone.append(o); });
        langue.value = prefs.langue;
        zone.value = prefs.zone;
        langue.addEventListener('change', () => { afficherDecalage(); });
        zone.addEventListener('change', afficherDecalage);
        function afficherDecalage() {
            const z = zone.value || (langue.value === 'en' ? 'America/New_York' : 'Europe/Paris');
            const nom = new Intl.DateTimeFormat('en', { timeZone: z, timeZoneName: 'shortOffset' }).formatToParts(new Date()).find((p) => p.type === 'timeZoneName').value;
            document.querySelector('#pref-decalage').textContent = z + ' · ' + nom.replace('GMT', 'UTC');
        }
        afficherDecalage();
        document.querySelector('#pref-position').addEventListener('click', () => {
            if (!navigator.geolocation || typeof tzlookup !== 'function') { etat.textContent = 'Localisation indisponible. Choisissez une ville manuellement.'; return; }
            etat.textContent = 'Recherche du fuseau…';
            navigator.geolocation.getCurrentPosition((position) => {
                try {
                    const trouve = tzlookup(position.coords.latitude, position.coords.longitude);
                    if (!valideZone(trouve)) throw new Error('unsupported zone');
                    if (![...zone.options].some((o) => o.value === trouve)) { const o = document.createElement('option'); o.value = trouve; o.textContent = trouve; zone.append(o); }
                    zone.value = trouve;
                    afficherDecalage();
                    etat.textContent = `Fuseau proposé : ${trouve}. Vérifiez puis enregistrez.`;
                } catch (_) { etat.textContent = 'Fuseau introuvable. Choisissez une ville manuellement.'; }
            }, () => { etat.textContent = 'Position refusée ou indisponible. Choisissez une ville manuellement.'; }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 0 });
        });
        formulaire.addEventListener('submit', (event) => {
            event.preventDefault();
            if (zone.value && !valideZone(zone.value)) { etat.textContent = 'Fuseau invalide.'; return; }
            prefs = { langue: langue.value, zone: zone.value };
            try { localStorage.setItem(cle, JSON.stringify(prefs)); etat.textContent = prefs.langue === 'en' ? 'Preferences saved on this browser.' : 'Préférences enregistrées dans ce navigateur.'; }
            catch (_) { etat.textContent = prefs.langue === 'en' ? 'Storage unavailable: choices apply only on this page.' : 'Stockage indisponible : choix actifs sur cette page uniquement.'; }
            afficher();
        });
        document.querySelector('#pref-reinitialiser').addEventListener('click', () => {
            let efface = true;
            try { localStorage.removeItem(cle); } catch (_) { efface = false; }
            prefs = { langue: langueDefaut(), zone: '' }; langue.value = prefs.langue; zone.value = ''; afficherDecalage();
            afficher(); etat.textContent = efface ? 'Préférences réinitialisées.' : 'Stockage inaccessible : effacez les données du site dans votre navigateur.';
        });
    }
    const filtre = document.querySelector('[data-filtre-jours]');
    if (filtre) filtre.addEventListener('submit', (event) => {
        const du = filtre.querySelector('[name="du"]').value;
        const au = filtre.querySelector('[name="au"]').value;
        const debut = du ? borne(du, zoneActive()) : '';
        const fin = au ? borne(suivant(au), zoneActive()) : '';
        if ((du && !debut) || (au && !fin)) { event.preventDefault(); alert('Date inexistante ou non prise en charge dans ce fuseau.'); return; }
        filtre.querySelector('[name="debut_utc"]').value = debut;
        filtre.querySelector('[name="fin_utc"]').value = fin;
    });
    const dashboard = document.querySelector('[data-dashboard-jour]');
    if (dashboard) {
        const jour = jourLocal(new Date(), zoneActive());
        const debut = borne(jour, zoneActive());
        const fin = borne(suivant(jour), zoneActive());
        if (debut && fin) {
            const params = new URLSearchParams({ jour_local: jour, debut_utc: debut, fin_utc: fin });
            fetch('/admin?' + params, { credentials: 'same-origin' }).then((r) => { if (!r.ok) throw new Error('HTTP'); return r.text(); }).then((html) => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                if (doc.querySelector('[data-dashboard-jour]')?.dataset.jourAccepte !== 'oui') throw new Error('Bornes refusées');
                for (const nom of ['data-ca-jour', 'data-commandes-jour']) {
                    const cible = dashboard.querySelector('[' + nom + ']'); const source = doc.querySelector('[' + nom + ']');
                    if (!source || !cible) throw new Error('Données absentes');
                    cible.textContent = source.textContent;
                }
                document.querySelector('[data-jour-info]').textContent = 'Indicateurs du jour : ' + zoneActive();
            }).catch(() => { document.querySelector('[data-jour-info]').textContent = 'Indicateurs du jour indisponibles dans votre fuseau ; valeurs de Paris affichées.'; });
        }
    }
    afficher();
}());
