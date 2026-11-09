## Lot concerné

L10 — panier + commande

## Exigences couvertes

EF-CLI-03, EF-CLI-04, EF-CLI-05, EF-CLI-06, EF-CLI-07
Règles touchées : RB-04, RB-05, RB-06, RB-15, RB-18

## Ce que ça change (base / PHP / IHM)

- `sp_add_order_line` : verrou `FOR UPDATE` + agrégation des quantités déjà réservées
- `CommandeRepository::confirmerDepuisPanier()` : un seul `CALL`, transaction, traduction des codes
- `public/js/panier.js` : mises à jour par `textContent` (aucun `innerHTML`)

## Preuves

- [ ] `npm run test:all` → 7 suites vertes
- [ ] `./scripts/run_sql_tests.sh` → 29/29
- [ ] `php -l` sur les fichiers modifiés
- [ ] capture du parcours F-17 (commande créée, stock décrémenté)
- [ ] contrôles CI 1, 2 et 5 : aucun résultat

## Régression vérifiée

commande multi-lignes, annulation puis re-commande, panier hors session.

## Auteur / relecteur

Auteur : @… — Relecteur : @… (obligatoire, autre que l'auteur)

/label ~lot
