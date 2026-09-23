#!/usr/bin/env bash
# MiniShop — generateur de volumetrie pour les mesures de performance (ENF-01 / ENF-02).
#
# Pourquoi ce script existe : un objectif de performance pose sur le jeu de demonstration
# (12 produits, 3 commandes) n'a aucune valeur de mesure. La cible retenue au CDC est
# « petit e-commerce reel » : 200 produits, 1 000 commandes sur 12 mois, 10 clients.
#
# Les commandes sont creees EN PASSANT PAR LES PROCEDURES STOCKEES : le generateur exerce
# donc les memes chemins que l'application (snapshot de prix, frais de port, decrement de
# stock, historique) et les mesures portent sur des donnees coherentes avec RB-06/RB-15.
# Les produits sont inseres par INSERT ... VALUES, JAMAIS par INSERT ... SELECT : verifie
# sur MariaDB 11.8, un INSERT ... SELECT ne declenche pas les triggers BEFORE INSERT qui
# affectent NEW, donc `prix_ttc` (NOT NULL, calcule par trg_produit_ttc) serait refuse.
#
# Usage :
#   bash "$DIR_SCRIPTS/gen_volumes.sh"                          # dans minishop_perf (creee si besoin)
#   DB=minishop N_PRODUITS=500 N_COMMANDES=2000 bash "$DIR_SCRIPTS/gen_volumes.sh"
#   bash "$DIR_SCRIPTS/gen_volumes.sh" --purge                  # revient au seul jeu de demonstration
set -uo pipefail
DIR_SCRIPTS="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$(dirname "$0")/.."
DB="${DB:-minishop_perf}"
N_PRODUITS="${N_PRODUITS:-200}"
N_COMMANDES="${N_COMMANDES:-1000}"
N_CLIENTS="${N_CLIENTS:-10}"
BATCH="${BATCH:-25}"
MYSQL="$(command -v mysql || command -v mariadb)"
AUTH=()
if [ -n "${DB_USER:-}" ]; then AUTH=(-u"$DB_USER"); [ -n "${DB_PASS:-}" ] && AUTH+=("-p$DB_PASS"); fi
run()  { "$MYSQL" "${AUTH[@]}" "$DB" "$@"; }
file() { "$MYSQL" "${AUTH[@]}" "$DB" < "$1"; }

if [ "${1:-}" = "--purge" ]; then
  echo "Purge des donnees generees (produits VP-*, clients perf*, commandes id > 100)."
  run -e "SET FOREIGN_KEY_CHECKS=0;
          DELETE FROM ligne_commande        WHERE id_commande > 100;
          DELETE FROM order_status_history  WHERE order_id    > 100;
          DELETE FROM commande              WHERE id_commande > 100;
          DELETE FROM produit               WHERE reference LIKE 'VP-%';
          DELETE FROM client                WHERE email LIKE 'perf%@minishop.local';
          SET FOREIGN_KEY_CHECKS=1;"
  echo "OK — base ramenee au jeu de demonstration."
  exit 0
fi

echo "== generation dans '$DB' : $N_PRODUITS produits, $N_COMMANDES commandes, $N_CLIENTS clients =="
run -e "SELECT (SELECT COUNT(*) FROM produit) AS produits_avant, (SELECT COUNT(*) FROM commande) AS commandes_avant;"

tmp=$(mktemp)

# --- 1) produits -------------------------------------------------------
{ echo "INSERT INTO produit (reference, nom, slug, description, prix_ht, tva, stock, seuil_alerte, id_categorie, visible) VALUES";
  for i in $(seq 1 "$N_PRODUITS"); do
    prix=$(awk  -v i="$i" 'BEGIN{printf "%.2f", 5 + (i*37 % 900) + (i%10)/10}')
    stk=$(awk   -v i="$i" 'BEGIN{print 20 + (i*7 % 180)}')
    cid=$(awk   -v i="$i" 'BEGIN{print 1 + (i % 4)}')
    sep=$([ "$i" -lt "$N_PRODUITS" ] && echo "," || echo "")
    printf "('%s','Produit de mesure %d','produit-de-mesure-%d','Genere par scripts/gen_volumes.sh pour les mesures ENF-01 : description de taille moyenne, prix variable.',%s,20.00,%s,5,%s,1)%s\n" \
      "$(printf 'VP-%04d' "$i")" "$i" "$i" "$prix" "$stk" "$cid" "$sep"
  done; } > "$tmp"
file "$tmp" || { echo "ECHEC insertion produits"; exit 1; }

# --- 2) clients --------------------------------------------------------
{ echo "INSERT IGNORE INTO client (nom, prenom, email, mot_de_passe_hash, adresse_livraison, code_postal, ville, actif) VALUES";
  for i in $(seq 1 "$N_CLIENTS"); do
    sep=$([ "$i" -lt "$N_CLIENTS" ] && echo "," || echo "")
    printf "('Citoyen-%d','Mesure','perf%d@minishop.local',(SELECT mot_de_passe_hash FROM client WHERE id_client=1),'%d rue du Benchmark','06000','Nice',1)%s\n" \
      "$i" "$i" "$i" "$sep"
  done; } > "$tmp"
file "$tmp" || { echo "ECHEC insertion clients"; exit 1; }

# --- 3) commandes via les procedures ----------------------------------
nprod=$(run -N -e "SELECT COUNT(*) FROM produit")
ncli=$(run  -N -e "SELECT COUNT(*) FROM client")
t0=$(date +%s)
i=0
while [ "$i" -lt "$N_COMMANDES" ]; do
  { echo "SET @nprod := $nprod; SET @ncli := $ncli;";
    for j in $(seq "$i" $(( i + BATCH - 1 ))); do
      [ "$j" -ge "$N_COMMANDES" ] && break
      cat <<EOF
CALL sp_create_order(1 + ($j % @ncli), CONCAT('Adresse de mesure ', $j, ', 06000 Nice'), @c$j, @num$j);
CALL sp_add_order_line(@c$j, 1 + ($j % @nprod), 1 + ($j % 3), @l$j, @t$j, @r$j);
CALL sp_add_order_line(@c$j, 1 + ((($j * 7) + 3) % @nprod), 1, @l2$j, @t2$j, @r2$j);
CALL sp_confirm_order(@c$j, 1 + ($j % @ncli), IF($j % 4 = 0, 1, 0), @m$j, @code$j);
EOF
    done; } > "$tmp"
  file "$tmp" || echo "   (lot $i : certaines lignes ont ete refusees par les regles — normal si un stock est epuise)"
  i=$(( i + BATCH ))
  echo "   $i / $N_COMMANDES commandes — $(( $(date +%s) - t0 )) s"
done
rm -f "$tmp"

echo "== bilan =="
run -e "SELECT (SELECT COUNT(*) FROM produit)              AS produits,
            (SELECT COUNT(*) FROM client)                   AS clients,
            (SELECT COUNT(*) FROM commande)                 AS commandes,
            (SELECT COUNT(*) FROM ligne_commande)           AS lignes,
            (SELECT COUNT(*) FROM order_status_history)    AS traces,
            (SELECT COUNT(*) FROM commande WHERE frais_port > 0) AS commandes_avec_port,
            (SELECT COUNT(*) FROM produit WHERE prix_ttc <> ROUND(prix_ht*(1+tva/100),2)) AS ttc_incoherents,
            (SELECT COUNT(*) FROM commande c WHERE c.statut <> 'BROUILLON' AND c.montant_total <>
               (SELECT COALESCE(SUM(l.total_ligne),0) FROM ligne_commande l WHERE l.id_commande=c.id_commande) + c.frais_port) AS montants_incoherents;"
echo "Mesures : DB=$DB ./tests/perf/mesurer.sh   |   Retour au jeu de demo : bash "$DIR_SCRIPTS/gen_volumes.sh" --purge"
