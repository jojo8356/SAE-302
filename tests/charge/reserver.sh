#!/usr/bin/env bash
# =====================================================================
# MiniShop — test de charge « pas de survente » (C-01, §2.3, doc 03).
#
# 20 clients demandent simultanément 1 exemplaire d'un produit dont le
# stock est de 12. Attendu : exactement 12 commandes créées, 8 refus
# STOCK_INSUFFISANT, stock final = 0. Preuve que le verrou FOR UPDATE +
# le trigger BEFORE INSERT ne laissent pas passer la survente.
#
#   DB_USER=root DB_PASS='' bash tests/charge/reserver.sh
#   DEMANDES=20 STOCK=12 bash tests/charge/reserver.sh
#
# Prérequis : base chargée (./scripts/load_db.sh), client mysql.
# Le script crée son propre produit « CHARGE-001 » et nettoie à la fin.
# =====================================================================
set -uo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB="${DB:-${DB_TEST:-minishop}}"
DEMANDES="${DEMANDES:-20}"
STOCK="${STOCK:-12}"

MYSQL="$(command -v mysql || command -v mariadb || true)"
if [ -z "$MYSQL" ]; then echo "✘ client mysql introuvable" >&2; exit 127; fi

AUTH=(-u"$DB_USER" -h"$DB_HOST" -P"$DB_PORT")
[ -n "$DB_PASS" ] && AUTH+=("-p$DB_PASS")
q() { "$MYSQL" "${AUTH[@]}" -N -B "$DB" -e "$1"; }

echo "== MiniShop — charge : $DEMANDES demandes pour $STOCK exemplaires =="

q "DELETE FROM ligne_commande WHERE id_commande IN (SELECT id_commande FROM commande WHERE numero LIKE 'CHG-%');
   DELETE FROM order_status_history WHERE order_id IN (SELECT id_commande FROM commande WHERE numero LIKE 'CHG-%');
   DELETE FROM commande WHERE numero LIKE 'CHG-%';
   DELETE FROM produit WHERE reference = 'CHARGE-001';" >/dev/null

ID_PROD=$(q "INSERT INTO produit (reference, nom, slug, description, prix_ht, tva, stock, seuil_alerte, id_categorie, visible)
             VALUES ('CHARGE-001', 'Produit de charge', 'produit-de-charge', 'Test de survente concurrente', 10.00, 20.00, $STOCK, 0, 1, 1);
             SELECT LAST_INSERT_ID();")
ID_CLIENT=$(q "SELECT id_client FROM client ORDER BY id_client LIMIT 1;")

echo "   produit #$ID_PROD (stock $STOCK), client #$ID_CLIENT"

# 20 appels concurrents à la procédure de commande (chacun dans sa session)
TMP="$(mktemp -d)"
for i in $(seq 1 "$DEMANDES"); do
    (
        "$MYSQL" "${AUTH[@]}" -N -B "$DB" -e "
            CALL sp_create_order_from_basket(
                $ID_CLIENT,
                '1 rue du Test',
                CONCAT('[{\"id_produit\":', $ID_PROD, ', \"quantite\":1, \"prix_unitaire\":10.00}]'),
                1, @id, @num, @montant, @code);
            SELECT CONCAT(@code, ';', IFNULL(@id, 0));" 2>&1
    ) > "$TMP/$i.out" 2>&1 &
done
wait

OK=$(grep -l "OK" "$TMP"/*.out 2>/dev/null | wc -l | tr -d ' ')
STOCK_FINAL=$(q "SELECT stock FROM produit WHERE id_produit = $ID_PROD;")
NB_COMMANDES=$(q "SELECT COUNT(*) FROM commande WHERE numero LIKE 'CHG-%' OR id_commande > 0 AND id_client = $ID_CLIENT AND date_commande >= CURDATE();")

echo
echo "   commandes acceptées : $OK"
echo "   stock final         : $STOCK_FINAL"
echo "   détail : $(cat "$TMP"/*.out 2>/dev/null | tr '\n' ' ' | cut -c1-240)"

FAIL=0
[ "$STOCK_FINAL" = "0" ] || { echo "✘ stock final attendu 0, obtenu $STOCK_FINAL"; FAIL=1; }
[ "$OK" = "$STOCK" ] || { echo "✘ commandes acceptées attendues $STOCK, obtenu $OK"; FAIL=1; }

q "DELETE FROM ligne_commande WHERE id_commande IN (SELECT id_commande FROM commande WHERE numero LIKE 'CHG-%');
   DELETE FROM order_status_history WHERE order_id IN (SELECT id_commande FROM commande WHERE numero LIKE 'CHG-%');
   DELETE FROM commande WHERE numero LIKE 'CHG-%';
   DELETE FROM produit WHERE reference = 'CHARGE-001';" >/dev/null
rm -rf "$TMP"

if [ "$FAIL" -eq 0 ]; then
    echo "✓ aucune survente : $STOCK ventes pour $STOCK exemplaires, $((DEMANDES-STOCK)) refus"
else
    echo "✘ SURVENTE DÉTECTÉE"
fi
exit "$FAIL"
