import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { JSDOM } from 'jsdom';
const js = await readFile('public/js/preferences.js', 'utf8');
const tz = await readFile('public/js/tz-lookup.js', 'utf8');
const html = `<!doctype html><html lang="fr"><body><nav><a>Catalogue</a></nav><div class="preferences"><h1>Préférences</h1><form id="form-preferences" hidden>
<select id="pref-langue"><option value="fr">Français</option><option value="en">English</option></select><select id="pref-zone"></select>
<p id="pref-decalage"></p><button id="pref-position" type="button">Utiliser ma position</button><button type="submit">Enregistrer</button><button id="pref-reinitialiser" type="button">Réinitialiser</button><p id="pref-etat"></p></form></div>
<time data-date-format="datetime" datetime="2026-01-15T18:15:00Z"></time><time data-date-format="datetime" datetime="2026-07-15T18:15:00Z"></time>
<form data-filtre-jours><input name="du" value="2026-03-29"><input name="au" value="2026-03-29"><input name="debut_utc"><input name="fin_utc"></form></body></html>`;
function setup({langue = 'fr-FR', position, stocke, stockageBloque = false} = {}) {
    const dom = new JSDOM(html, {url:'https://minishop.test', runScripts:'outside-only'});
    const w = dom.window;
    Object.defineProperty(w.navigator, 'language', {value:langue});
    if (position) Object.defineProperty(w.navigator, 'geolocation', {value:position});
    if (stocke) w.localStorage.setItem('minishop.preferences.v1', JSON.stringify(stocke));
    if (stockageBloque) Object.defineProperty(w, 'localStorage', {get() {throw Error('bloqué');}});
    w.eval(tz); w.eval(js);
    return w;
}
const val = (w, selector) => w.document.querySelector(selector).textContent;
let w = setup();
assert.equal(w.document.querySelector('time').textContent, '15/01/2026 19h15');
assert.equal(w.document.querySelectorAll('time')[1].textContent, '15/07/2026 20h15');
assert.equal(w.document.querySelector('#form-preferences').hidden, false);
w.document.querySelector('[data-filtre-jours]').addEventListener('submit', (e) => e.preventDefault());
w.document.querySelector('[data-filtre-jours]').requestSubmit();
assert.equal(w.document.querySelector('[name=debut_utc]').value, '2026-03-28 23:00:00');
assert.equal(w.document.querySelector('[name=fin_utc]').value, '2026-03-29 22:00:00');
const zone = w.document.querySelector('#pref-zone');
zone.value = 'America/New_York';
w.document.querySelector('#form-preferences').requestSubmit();
assert.equal(w.document.querySelector('time').textContent, '15/01/2026 13h15');
assert.equal(w.document.querySelectorAll('time')[1].textContent, '15/07/2026 14h15');
assert.deepEqual(JSON.parse(w.localStorage.getItem('minishop.preferences.v1')), {langue:'fr', zone:'America/New_York'});
w.document.querySelector('[data-filtre-jours]').requestSubmit();
assert.equal(w.document.querySelector('[name=debut_utc]').value, '2026-03-29 04:00:00');
assert.equal(w.document.querySelector('[name=fin_utc]').value, '2026-03-30 04:00:00');
w = setup({langue:'en-US'});
assert.equal(w.document.querySelector('time').textContent, '01/15/2026 13:15');
assert.equal(val(w, 'nav a'), 'Catalog');
assert.equal(w.document.querySelector('#pref-zone').value, '');
assert.match(val(w, '#pref-decalage'), /America\/New_York · UTC/);
let appels = 0;
w = setup({position:{getCurrentPosition(ok) { appels++; ok({coords:{latitude:43.70, longitude:7.26}}); }}});
assert.equal(appels, 0, 'pas de géolocalisation sans clic');
w.document.querySelector('#pref-position').click();
assert.equal(appels, 1);
assert.equal(w.document.querySelector('#pref-zone').value, 'Europe/Paris');
assert.equal(w.localStorage.length, 0, 'position non enregistrée avant validation');
w.document.querySelector('#form-preferences').requestSubmit();
assert.ok(!w.localStorage.getItem('minishop.preferences.v1').includes('43.7'), 'coordonnées jamais sauvegardées');
w.document.querySelector('#pref-reinitialiser').click();
assert.equal(w.localStorage.length, 0);
w = setup({position:{getCurrentPosition(ok, erreur) {erreur({code:1});}}});
w.document.querySelector('#pref-position').click();
assert.match(val(w, '#pref-etat'), /refusée/);
w = setup({stockageBloque:true});
w.document.querySelector('#form-preferences').requestSubmit();
assert.match(val(w, '#pref-etat'), /Stockage indisponible/);
w = setup({stocke:{langue:'en',zone:'Nonexistent/X'}});
assert.equal(w.document.querySelector('time').textContent, '01/15/2026 13:15');
console.log('Préférences : tests DOM, fuseaux, DST, géolocalisation et confidentialité réussis.');
