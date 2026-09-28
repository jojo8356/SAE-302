<?php

declare(strict_types=1);

/**
 * MiniShop — les 16 déclencheurs métier de sql/03 portés en opérations JSON.
 *
 * Comme en SQL, les déclencheurs sont la DERNIÈRE barrière : ils s'exécutent
 * dans le pilote (JsonStore), pas dans les contrôleurs — un code applicatif
 * défaillant ne peut donc pas violer RB-02/03/05/06/09/11/14/18, pas plus
 * qu'un appel direct au store.
 *
 * Correspondance exacte avec sql/03_minishop_triggers.sql :
 *
 *   SQL (16 triggers)                       → port PHP (cette classe)
 *   ------------------------------------------------------------------
 *   trg_ligne_controle_insert  (BEFORE INS) → ligne_commande.beforeInsert
 *   trg_ligne_prix_snapshot    (BEFORE INS) → ligne_commande.beforeInsert
 *   trg_ligne_decrement_stock  (AFTER INS)  → ligne_commande.afterInsert
 *   trg_ligne_restore_stock    (AFTER DEL)  → ligne_commande.afterDelete
 *   trg_ligne_immutable        (BEFORE UPD) → ligne_commande.beforeUpdate
 *   trg_produit_ttc            (BEFORE INS) → produit.beforeInsert
 *   trg_produit_ttc_update     (BEFORE UPD) → produit.beforeUpdate
 *   trg_produit_regles         (BEFORE UPD) → produit.beforeUpdate
 *   trg_produit_delete         (BEFORE DEL) → produit.beforeDelete
 *   trg_categorie_slug         (BEFORE INS) → categorie.beforeInsert
 *   trg_categorie_delete       (BEFORE DEL) → categorie.beforeDelete
 *   trg_commande_transition_statut (B UPD)  → commande.beforeUpdate
 *   trg_commande_controle_insert (BEFORE INS)→ commande.beforeInsert
 *   trg_commande_delete        (BEFORE DEL) → commande.beforeDelete
 *   trg_history_statut         (AFTER UPD)  → commande.afterUpdate
 *   trg_history_creation       (AFTER INS)  → commande.afterInsert
 *
 * Deux différences assumées, documentées :
 *   1. trg_history_statut écrit l'ACTEUR réel (client/admin/système, via
 *      StoreInterface::actor()) alors que le trigger SQL ne pouvait tracer
 *      que 'SYSTEME' — même garantie d'exhaustivité (écriture automatique,
 *      impossible à « oublier »), meilleure attributions.
 *   2. MySQL interdit à un trigger de muter sa propre table : chaque règle
 *      est écrite « du bon côté ». Ici les déclencheurs reçoivent le store
 *      complet et peuvent donc tout lire — les règles restent identiques.
 */

namespace App\Model\Data;

final class Triggers
{
    /**
     * Matrice des transitions de statut autorisées (RB-11, §5.3.7 du CDC —
     * la même matrice est décrite dans le diagramme d'états, dans
     * sp_update_order_status et dans trg_commande_transition_statut).
     */
    public const TRANSITIONS = [
        'BROUILLON' => ['EN_PREPARATION', 'PAYEE', 'ANNULEE'],
        'EN_PREPARATION' => ['PAYEE', 'EXPEDIEE', 'ANNULEE'],
        'PAYEE' => ['EXPEDIEE', 'ANNULEE'],
        'EXPEDIEE' => ['LIVREE', 'ANNULEE'],
        'LIVREE' => [],
        'ANNULEE' => [],
    ];

    public const STATUTS = ['BROUILLON', 'EN_PREPARATION', 'PAYEE', 'EXPEDIEE', 'LIVREE', 'ANNULEE'];

    /**
     * Point d'entrée appelé par JsonStore.
     *
     * @param array<string,mixed>|null $old ligne avant l'événement (insert : null)
     * @param array<string,mixed>|null $new ligne après l'événement (delete : null)
     * @return array<string,mixed>|null ligne éventuellement ajustée (BEFORE)
     */
    public static function fire(StoreInterface $store, string $table, string $phase, string $event, ?array $old, ?array $new): ?array
    {
        $handler = self::handlerFor($table, $phase, $event);
        if ($handler === null) {
            return $new; // pas de déclencheur sur cet événement : transparent
        }

        return $handler($store, $old, $new);
    }

    /** @return callable(StoreInterface, ?array, ?array): ?array|null */
    private static function handlerFor(string $table, string $phase, string $event): ?callable
    {
        // convention de clé : « table.beforeInsert » / « table.afterUpdate »…
        $key = $table . '.' . $phase . ucfirst($event);
        $registry = self::registry();

        return $registry[$key] ?? null;
    }

    /**
     * Table des déclencheurs : clé « table.phase.event ».
     * Chaque callback retourne la ligne NEW ajustée (phase BEFORE) ou null.
     *
     * @return array<string, callable(StoreInterface, ?array, ?array): ?array>
     */
    private static function registry(): array
    {
        if (self::$registry !== null) {
            return self::$registry;
        }

        return self::$registry = [
            // ============================================== BLOC A — STOCK
            // trg_ligne_controle_insert + trg_ligne_prix_snapshot (TRG-A1 + TRG-B2)
            'ligne_commande.beforeInsert' => static function (StoreInterface $store, ?array $old, ?array $new): array {
                $produit = $store->find('produit', (int) $new['id_produit']);
                if ($produit === null) {
                    throw new BusinessError('PRODUIT_INTROUVABLE'); // RB-17
                }
                if ((int) $produit['stock'] < (int) $new['quantite']) {
                    throw new BusinessError('STOCK_INSUFFISANT'); // RB-18
                }
                // RB-06 : le prix enregistré est TOUJOURS le prix TTC du
                // produit à l'instant de l'achat, quoi que l'appelant envoie.
                $new['prix_unitaire'] = round((float) $produit['prix_ttc'], 2);
                $new['total_ligne'] = round((int) $new['quantite'] * (float) $produit['prix_ttc'], 2);

                return $new;
            },

            // trg_ligne_decrement_stock (TRG-A2)
            'ligne_commande.afterInsert' => static function (StoreInterface $store, ?array $old, ?array $new): void {
                $produit = $store->find('produit', (int) $new['id_produit']);
                $restant = max((int) ($produit['stock'] ?? 0) - (int) $new['quantite'], 0);
                $store->update('produit', [['id_produit', '=', (int) $new['id_produit']]], ['stock' => $restant]);
            },

            // trg_ligne_restore_stock (TRG-A3)
            'ligne_commande.afterDelete' => static function (StoreInterface $store, ?array $old, ?array $new): void {
                $produit = $store->find('produit', (int) $old['id_produit']);
                $rendu = (int) ($produit['stock'] ?? 0) + (int) $old['quantite'];
                $store->update('produit', [['id_produit', '=', (int) $old['id_produit']]], ['stock' => $rendu]);
            },

            // trg_ligne_immutable (TRG-B1)
            'ligne_commande.beforeUpdate' => static function (StoreInterface $store, ?array $old, ?array $new): array {
                if ((int) $new['quantite'] !== (int) $old['quantite']
                    || (int) $new['id_produit'] !== (int) $old['id_produit']
                    || (float) $new['prix_unitaire'] !== (float) $old['prix_unitaire']) {
                    throw new BusinessError('RB06_PRIX_ET_QUANTITE_NON_MODIFIABLE'); // RB-09 + RB-06
                }

                return $new;
            },

            // ================================= BLOC B — PRIX ET PRODUITS
            // trg_produit_ttc (TRG-B3)
            'produit.beforeInsert' => static function (StoreInterface $store, ?array $old, ?array $new): array {
                // RB-16 : le TTC est dérivé par le moteur, jamais transmis
                $new['prix_ttc'] = round((float) $new['prix_ht'] * (1 + (float) $new['tva'] / 100), 2);
                if (!isset($new['stock_initial']) || (int) $new['stock_initial'] < 0) {
                    $new['stock_initial'] = (int) ($new['stock'] ?? 0);
                }

                return $new;
            },

            // trg_produit_ttc_update + trg_produit_regles (TRG-B4 + TRG-B5)
            'produit.beforeUpdate' => static function (StoreInterface $store, ?array $old, ?array $new): array {
                if ((int) $new['stock'] < 0) {
                    throw new BusinessError('RB03_STOCK_NE_PEUT_PAS_ETRE_NEGATIF'); // RB-03
                }
                $new['prix_ttc'] = round((float) $new['prix_ht'] * (1 + (float) $new['tva'] / 100), 2);

                return $new;
            },

            // trg_produit_delete (TRG-B6)
            'produit.beforeDelete' => static function (StoreInterface $store, ?array $old, ?array $new): void {
                if ($store->exists('ligne_commande', [['id_produit', '=', (int) $old['id_produit']]])) {
                    throw new BusinessError('PRODUIT_REFERENCE_INTERDIT_DE_SUPPRIMER'); // RB-14
                }
            },

            // ===================================== BLOC C — CATÉGORIES
            // trg_categorie_slug (TRG-C1)
            'categorie.beforeInsert' => static function (StoreInterface $store, ?array $old, ?array $new): array {
                if (!isset($new['slug']) || trim((string) $new['slug']) === '') {
                    $new['slug'] = Text::slug((string) $new['nom']);
                }

                return $new;
            },

            // trg_categorie_delete (TRG-C2)
            'categorie.beforeDelete' => static function (StoreInterface $store, ?array $old, ?array $new): void {
                if ($store->exists('produit', [['id_categorie', '=', (int) $old['id_categorie']]])) {
                    throw new BusinessError('CATEGORIE_NON_VIDE'); // RB-14
                }
            },

            // ============================ BLOC D — COMMANDES ET AUDIT
            // trg_commande_controle_insert (TRG-D2)
            'commande.beforeInsert' => static function (StoreInterface $store, ?array $old, ?array $new): array {
                if (!$store->exists('client', [['id_client', '=', (int) $new['id_client']]])) {
                    throw new BusinessError('RB10_COMMANDE_SANS_CLIENT'); // RB-10
                }
                // RB-15 : le montant se calcule, il ne se saisit pas
                if ((float) $new['montant_total'] !== 0.0) {
                    $new['montant_total'] = 0.0;
                }
                if ($new['frais_port'] === null || (float) $new['frais_port'] < 0) {
                    $new['frais_port'] = 0.0;
                }
                if ($new['statut'] === null) {
                    $new['statut'] = 'BROUILLON';
                }

                return $new;
            },

            // trg_commande_transition_statut (TRG-D1)
            'commande.beforeUpdate' => static function (StoreInterface $store, ?array $old, ?array $new): array {
                if (!$store->exists('client', [['id_client', '=', (int) $new['id_client']]])) {
                    throw new BusinessError('RB10_COMMANDE_SANS_CLIENT');
                }

                // RB-15 / RB-20 : une fois la commande validée, le montant DOIT
                // rester égal à (somme des lignes + port) — contrôle inconditionnel.
                $sommeLignes = $store->sum('ligne_commande', 'total_ligne', [['id_commande', '=', (int) $new['id_commande']]]);
                $attendu = round($sommeLignes + (float) $new['frais_port'], 2);
                if ($new['statut'] === 'BROUILLON') {
                    $montantBouge = (float) $new['montant_total'] !== (float) $old['montant_total'];
                    if ($montantBouge && (float) $new['montant_total'] !== $attendu) {
                        throw new BusinessError('RB15_MONTANT_CALCULE_INTERDIT');
                    }
                } elseif ((float) $new['montant_total'] !== $attendu) {
                    throw new BusinessError('RB15_MONTANT_CALCULE_INTERDIT');
                }

                // RB-11 : matrice des transitions
                if ($new['statut'] !== $old['statut']
                    && !in_array($new['statut'], self::TRANSITIONS[$old['statut']] ?? [], true)) {
                    throw new BusinessError('RB11_TRANSITION_STATUT_INTERDITE');
                }

                return $new;
            },

            // trg_commande_delete (TRG-D3)
            'commande.beforeDelete' => static function (StoreInterface $store, ?array $old, ?array $new): void {
                if (!in_array($old['statut'], ['BROUILLON', 'EN_PREPARATION'], true)) {
                    throw new BusinessError('RB09_COMMANDE_VALIDATEE_INTERDITE_DE_SUPPRIMER'); // RB-09
                }
                if ($store->exists('ligne_commande', [['id_commande', '=', (int) $old['id_commande']]])) {
                    throw new BusinessError('RB04_COMMANDE_DOIT_CONTENIR_AU_MOINS_UNE_LIGNE');
                }
            },

            // trg_history_creation (TRG-D5) : le statut initial est tracé
            'commande.afterInsert' => static function (StoreInterface $store, ?array $old, ?array $new): void {
                $actor = $store->actor();
                $store->insert('order_status_history', [
                    'order_id' => (int) $new['id_commande'],
                    'old_status' => null,
                    'new_status' => (string) $new['statut'],
                    'changed_by' => $actor['id'],
                    'changed_by_role' => $actor['role'],
                    'commentaire' => 'Création tracée par trg_history_creation',
                    'changed_at' => date('Y-m-d H:i:s'),
                ]);
            },

            // trg_history_statut (TRG-D4) : tout changement de statut est tracé.
            // Le commentaire fourni avec CETTE mise à jour (colonne modifiée) est
            // repris tel quel ; sinon trace générique horodatée (EF-ADM-08).
            'commande.afterUpdate' => static function (StoreInterface $store, ?array $old, ?array $new): void {
                if ($new['statut'] === $old['statut']) {
                    return;
                }
                $nouveauCommentaire = ($new['commentaire'] ?? null) !== null
                    && ($new['commentaire'] ?? '') !== ($old['commentaire'] ?? '')
                    && trim((string) $new['commentaire']) !== '';
                $commentaireTrace = 'Tracé par trg_history_statut le ';
                if ($nouveauCommentaire) {
                    $commentaireTrace = trim((string) $new['commentaire']);
                } else {
                    $commentaireTrace .= date('Y-m-d H:i:s');
                }
                $actor = $store->actor();
                $store->insert('order_status_history', [
                    'order_id' => (int) $new['id_commande'],
                    'old_status' => (string) $old['statut'],
                    'new_status' => (string) $new['statut'],
                    'changed_by' => $actor['id'],
                    'changed_by_role' => $actor['role'],
                    'commentaire' => $commentaireTrace,
                    'changed_at' => date('Y-m-d H:i:s'),
                ]);
            },
        ];
    }

    /** @var array<string,callable>|null */
    private static ?array $registry = null;
}
