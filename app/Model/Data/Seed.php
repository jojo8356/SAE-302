<?php

declare(strict_types=1);

/**
 * MiniShop — jeu de démonstration (équivalent des INSERT de sql/01).
 *
 * Initialise les fichiers data/minishop/*.json : 1 admin, 4 catégories,
 * 12 produits, 3 clients et les 4 paramètres métier. Les mots de passe ne
 * sont JAMAIS stockés en clair : le seed appelle password_hash() (RB-12).
 *
 *   Clients  : alice@example.com / bruno@example.com / carla@example.com — « Demo2026! »
 *   Back-Office : admin@minishop.fr — « Admin2026! » (rôle SUPER)
 *
 * Idempotent : n'écrase jamais une base déjà présente (utiliser force()
 * explicitement pour réinstaller, ou scripts/seed_json.php --reset).
 */

namespace App\Model\Data;

final class Seed
{
    /** @return array<string,string> comptes de démonstration (email => mot de passe clair, doc uniquement) */
    public static function comptesDemo(): array
    {
        return [
            'alice@example.com' => 'Demo2026!',
            'bruno@example.com' => 'Demo2026!',
            'carla@example.com' => 'Demo2026!',
            'admin@minishop.fr' => 'Admin2026!',
        ];
    }

    /** La base est-elle déjà installée (au moins une table non vide) ? */
    public static function estInstalle(StoreInterface $store): bool
    {
        return $store->count('parametre') > 0 || $store->count('produit') > 0;
    }

    /**
     * Crée la base de démonstration si elle n'existe pas encore.
     *
     * @return bool vrai si la base a été (ré)installée
     */
    public static function ensure(StoreInterface $store): bool
    {
        if (self::estInstalle($store)) {
            return false;
        }
        self::force($store);

        return true;
    }

    /** (Ré)installe TOUTES les tables de démonstration, sans ménagement. */
    public static function force(StoreInterface $store): void
    {
        $store->setActor(['id' => null, 'role' => 'SYSTEME', 'nom' => 'seed']);

        $store->replaceAll('parametre', [
            ['cle' => 'frais_port', 'valeur' => '4.90', 'unite' => 'EUR TTC',
                'comment' => 'Livraison standard, montant affiché avant la validation (art. L221-5 4° C. conso.)'],
            ['cle' => 'franchise_port', 'valeur' => '80.00', 'unite' => 'EUR TTC',
                'comment' => 'Port offert au-delà de ce montant de marchandises'],
            ['cle' => 'tva_defaut', 'valeur' => '20.00', 'unite' => '%',
                'comment' => 'Taux par défaut proposé à la création d’un produit'],
            ['cle' => 'conservation_compte', 'valeur' => '36', 'unite' => 'mois',
                'comment' => 'Données client en base active après le dernier contact (référentiel CNIL)'],
        ]);

        $store->replaceAll('administrateur', [
            ['nom' => 'Nguyen', 'prenom' => 'Alice', 'email' => 'admin@minishop.fr',
             'mot_de_passe_hash' => password_hash('Admin2026!', PASSWORD_BCRYPT), 'role' => 'SUPER', 'actif' => true],
        ]);

        $store->replaceAll('categorie', [
            ['nom' => 'Informatique', 'slug' => 'informatique', 'description' => 'Ordinateurs portables, périphériques et composants'],
            ['nom' => 'Audio', 'slug' => 'audio', 'description' => 'Casques, enceintes et microphones'],
            ['nom' => 'Accessoires', 'slug' => 'accessoires', 'description' => 'Sacs, câbles, supports et batteries'],
            ['nom' => 'Objets connectés', 'slug' => 'objets-connectes', 'description' => 'Montres et bracelets connectés'],
        ]);

        $hashClients = password_hash('Demo2026!', PASSWORD_BCRYPT);
        $store->replaceAll('client', [
            ['nom' => 'Dupont', 'prenom' => 'Alice', 'email' => 'alice@example.com', 'mot_de_passe_hash' => $hashClients,
             'telephone' => '0612345678', 'adresse_livraison' => '12 rue de France', 'code_postal' => '06000', 'ville' => 'Nice'],
            ['nom' => 'Martin', 'prenom' => 'Bruno', 'email' => 'bruno@example.com', 'mot_de_passe_hash' => $hashClients,
             'telephone' => '0698765432', 'adresse_livraison' => '5 avenue Jean Médecin', 'code_postal' => '06000', 'ville' => 'Nice'],
            ['nom' => 'Moretti', 'prenom' => 'Carla', 'email' => 'carla@example.com', 'mot_de_passe_hash' => $hashClients,
             'telephone' => null, 'adresse_livraison' => '8 rue Papin', 'code_postal' => '06300', 'ville' => 'Nice'],
        ]);

        // id_categorie : 1 Informatique, 2 Audio, 3 Accessoires, 4 Objets connectés
        $produits = [
            ['PC-PORT-001', 'Ordinateur portable Zen 14', 'ordinateur-portable-zen-14', 'Écran IPS 14", 16 Go de RAM, SSD 512 Go, autonomie 12 h.', 699.00, 20.00, 12, 3, 1, 1, 'img/zen14.jpg'],
            ['PC-FIXE-002', 'Station de travail Turbo X', 'station-de-travail-turbo-x', 'Boîtier compact, 8 cœurs, 32 Go de RAM, SSD NVMe 1 To.', 1249.00, 20.00, 4, 2, 1, 1, 'img/turbox.jpg'],
            ['ECRA-003', 'Écran 27" QHD', 'ecran-27-qhd', 'Dalle IPS QHD 75 Hz, HDR10, pied réglable.', 179.90, 20.00, 25, 5, 1, 1, 'img/qhd27.jpg'],
            ['CLAV-004', 'Clavier mécanique K87', 'clavier-mecanique-k87', 'Switches tactiles, rétroéclairage, sans fil 2,4 GHz.', 79.00, 20.00, 40, 10, 1, 1, 'img/k87.jpg'],
            ['CASQ-005', 'Casque sans fil Aura', 'casque-sans-fil-aura', 'Réduction de bruit active, 35 h d’autonomie.', 129.00, 20.00, 18, 5, 2, 1, 'img/aura.jpg'],
            ['ENCH-006', 'Enceinte portable Boom', 'enceinte-portable-boom', 'IPX7, 20 h d’autonomie, stéréo TrueWireless.', 59.00, 20.00, 30, 8, 2, 1, 'img/boom.jpg'],
            ['MICRO-007', 'Micro studio ProCast', 'micro-studio-procast', 'Condensateur cardioïde, bras articulé inclus.', 99.00, 20.00, 2, 1, 2, 1, 'img/procast.jpg'],
            ['SAC-008', 'Sac à dos 25 L nomade', 'sac-a-dos-25-l-nomade', 'Compartiment 16", tissu déperlant, port USB.', 49.00, 20.00, 22, 6, 3, 1, 'img/sac25.jpg'],
            ['CABLE-009', 'Câble USB-C 2 m renforcé', 'cable-usb-c-2-m-renforce', 'Charge 100 W, transfert 10 Gbps, tresse nylon.', 12.50, 20.00, 150, 30, 3, 1, 'img/usbc.jpg'],
            ['POWER-010', 'Batterie externe 20 000 mAh', 'batterie-externe-20-000-mah', 'Charge rapide 65 W, deux ports USB-C.', 45.00, 20.00, 0, 5, 3, 1, 'img/power20.jpg'],
            ['MONTRE-011', 'Montre connectée Fit 2', 'montre-connectee-fit-2', 'Cardio, GPS, 14 jours d’autonomie, étanche 5 ATM.', 149.00, 20.00, 9, 3, 4, 1, 'img/fit2.jpg'],
            ['BRAC-012', 'Bracelet connecté Band', 'bracelet-connecte-band', 'Suivi du sommeil et des notifications, 10 jours.', 35.00, 20.00, 60, 15, 4, 0, 'img/band.jpg'],
        ];
        $rows = [];
        $now = date('Y-m-d H:i:s');
        foreach ($produits as [$ref, $nom, $slug, $desc, $ht, $tva, $stock, $seuil, $cat, $visible, $img]) {
            $rows[] = [
                'reference' => $ref, 'nom' => $nom, 'slug' => $slug, 'description' => $desc,
                'prix_ht' => $ht, 'tva' => $tva, 'prix_ttc' => round($ht * (1 + $tva / 100), 2),
                'stock' => $stock, 'stock_initial' => $stock, 'seuil_alerte' => $seuil,
                'id_categorie' => $cat, 'visible' => (bool) $visible, 'image_url' => $img,
                'date_creation' => $now, 'date_modification' => $now,
            ];
        }
        $store->replaceAll('produit', $rows);

        $store->replaceAll('commande', []);
        $store->replaceAll('ligne_commande', []);
        $store->replaceAll('order_status_history', []);
    }
}
