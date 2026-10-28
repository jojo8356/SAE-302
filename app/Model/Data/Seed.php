<?php

declare(strict_types=1);

/**
 * MiniShop — jeu de démonstration (équivalent des INSERT de sql/01).
 *
 * Initialise les fichiers data/minishop/*.json : 1 admin, 4 catégories,
 * 36 produits (12 familles, mêmes visuels par type), 3 clients et les 4 paramètres métier. Les mots de passe ne
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
            ['PC-PORT-001', 'Ordinateur portable Zen 14', 'ordinateur-portable-zen-14', 'Écran IPS 14", 16 Go de RAM, SSD 512 Go, autonomie 12 h.', 699.00, 20.00, 12, 3, 1, 1, 'img/produits/zen14.jpg'],
            ['PC-FIXE-002', 'Station de travail Turbo X', 'station-de-travail-turbo-x', 'Boîtier compact, 8 cœurs, 32 Go de RAM, SSD NVMe 1 To.', 1249.00, 20.00, 4, 2, 1, 1, 'img/produits/turbox.jpg'],
            ['ECRA-003', 'Écran 27" QHD', 'ecran-27-qhd', 'Dalle IPS QHD 75 Hz, HDR10, pied réglable.', 179.90, 20.00, 25, 5, 1, 1, 'img/produits/qhd27.jpg'],
            ['CLAV-004', 'Clavier mécanique K87', 'clavier-mecanique-k87', 'Switches tactiles, rétroéclairage, sans fil 2,4 GHz.', 79.00, 20.00, 40, 10, 1, 1, 'img/produits/k87.jpg'],
            ['CASQ-005', 'Casque sans fil Aura', 'casque-sans-fil-aura', 'Réduction de bruit active, 35 h d’autonomie.', 129.00, 20.00, 18, 5, 2, 1, 'img/produits/aura.jpg'],
            ['ENCH-006', 'Enceinte portable Boom', 'enceinte-portable-boom', 'IPX7, 20 h d’autonomie, stéréo TrueWireless.', 59.00, 20.00, 30, 8, 2, 1, 'img/produits/boom.jpg'],
            ['MICRO-007', 'Micro studio ProCast', 'micro-studio-procast', 'Condensateur cardioïde, bras articulé inclus.', 99.00, 20.00, 2, 2, 2, 1, 'img/produits/procast.jpg'],
            ['SAC-008', 'Sac à dos 25 L nomade', 'sac-a-dos-25-l-nomade', 'Compartiment 16", tissu déperlant, port USB.', 49.00, 20.00, 22, 6, 3, 1, 'img/produits/sac25.jpg'],
            ['CABLE-009', 'Câble USB-C 2 m renforcé', 'cable-usb-c-2-m-renforce', 'Charge 100 W, transfert 10 Gbps, tresse nylon.', 12.50, 20.00, 150, 30, 3, 1, 'img/produits/usbc.jpg'],
            ['POWER-010', 'Batterie externe 20 000 mAh', 'batterie-externe-20-000-mah', 'Charge rapide 65 W, deux ports USB-C.', 45.00, 20.00, 0, 5, 3, 1, 'img/produits/power20.jpg'],
            ['MONTRE-011', 'Montre connectée Fit 2', 'montre-connectee-fit-2', 'Cardio, GPS, 14 jours d’autonomie, étanche 5 ATM.', 149.00, 20.00, 9, 3, 4, 1, 'img/produits/fit2.jpg'],
            ['BRAC-012', 'Bracelet connecté Band', 'bracelet-connecte-band', 'Suivi du sommeil et des notifications, 10 jours.', 35.00, 20.00, 60, 15, 4, 0, 'img/produits/band.jpg'],
            // --- Catalogue étendu : 24 produits de plus, mêmes visuels par TYPE de
            // produit (un packshot par famille, réutilisé : cadre homogène, pas de
            // 36 photos à produire). Objectif : 36 produits = 3 pages de 12.
            ['PC-PORT-013', 'Ordinateur portable Zen 16', 'ordinateur-portable-zen-16', 'Dalle 16 pouces, 32 Go de RAM, SSD 1 To.', 899.00, 20.00, 8, 2, 1, 1, 'img/produits/zen14.jpg'],
            ['PC-PORT-014', 'Ordinateur portable Zen Air', 'ordinateur-portable-zen-air', 'Ultraléger 1,1 kg, 16 Go de RAM, autonomie 18 h.', 1049.00, 20.00, 5, 2, 1, 1, 'img/produits/zen14.jpg'],
            ['PC-FIXE-015', 'Station de travail Turbo S', 'station-de-travail-turbo-s', '6 cœurs, 16 Go de RAM, SSD 512 Go, silencieux.', 949.00, 20.00, 6, 2, 1, 1, 'img/produits/turbox.jpg'],
            ['ECRA-016', 'Dalle 32 pouces 4K', 'dalle-32-pouces-4k', '3840 x 2160, 60 Hz, DisplayPort et HDMI.', 429.00, 20.00, 12, 3, 1, 1, 'img/produits/qhd27.jpg'],
            ['CLAV-017', 'Clavier compact K65', 'clavier-compact-k65', 'Format 65 %, switches linéaires, USB-C détachable.', 59.00, 20.00, 26, 8, 1, 1, 'img/produits/k87.jpg'],
            ['CLAV-018', 'Clavier sans fil K50', 'clavier-sans-fil-k50', 'Membrane silencieuse, pavé numérique, pile 24 mois.', 45.00, 20.00, 33, 10, 1, 1, 'img/produits/k87.jpg'],
            ['CASQ-019', 'Casque filaire Studio', 'casque-filaire-studio', 'Transducteurs 50 mm, câble détachable, pliable.', 89.00, 20.00, 14, 4, 2, 1, 'img/produits/aura.jpg'],
            ['CASQ-020', 'Casque nomade Compact', 'casque-nomade-compact', 'Bluetooth 5.3, réduction passive, 28 h.', 69.00, 20.00, 21, 6, 2, 1, 'img/produits/aura.jpg'],
            ['ENCH-021', 'Enceinte nomade Mini', 'enceinte-nomade-mini', 'Format poche, IPX5, 12 h d’autonomie.', 39.00, 20.00, 40, 10, 2, 1, 'img/produits/boom.jpg'],
            ['ENCH-022', 'Enceinte de bureau Duo', 'enceinte-de-bureau-duo', 'Paire stéréo, alimentation USB, réglage de tonalité.', 79.00, 20.00, 17, 5, 2, 1, 'img/produits/boom.jpg'],
            ['MICRO-023', 'Micro USB Podcast', 'micro-usb-podcast', 'Cardioïde, USB-C, socle anti-vibration.', 75.00, 20.00, 11, 3, 2, 1, 'img/produits/procast.jpg'],
            ['MICRO-024', 'Micro USB Conférence', 'micro-usb-conference', 'Omnidirectionnel, portée 3 m, pied télescopique.', 109.00, 20.00, 7, 2, 2, 1, 'img/produits/procast.jpg'],
            ['SAC-025', 'Sac à dos 18 L urbain', 'sac-a-dos-18-l-urbain', 'Compartiment ordinateur 14 pouces, dos respirant.', 39.00, 20.00, 28, 8, 3, 1, 'img/produits/sac25.jpg'],
            ['SAC-026', 'Sacoche 13 pouces quotidien', 'sacoche-13-pouces-quotidien', 'Renforts, poche avant, bandoulière réglable.', 29.00, 20.00, 35, 10, 3, 1, 'img/produits/sac25.jpg'],
            ['CABLE-027', 'Câble USB-C 1 m tressé', 'cable-usb-c-1-m-tresse', 'Charge 60 W, transfert 480 Mbps, connecteurs renforcés.', 8.50, 20.00, 200, 40, 3, 1, 'img/produits/usbc.jpg'],
            ['CABLE-028', 'Câble USB-C 3 m pliable', 'cable-usb-c-3-m-pliable', 'Charge 100 W, rangement intégré, gaine souple.', 16.00, 20.00, 90, 20, 3, 1, 'img/produits/usbc.jpg'],
            ['POWER-029', 'Batterie externe 10 000 mAh', 'batterie-externe-10-000-mah', 'Compacte, 30 W, un port USB-C et un USB-A.', 25.00, 20.00, 60, 15, 3, 1, 'img/produits/power20.jpg'],
            ['POWER-030', 'Batterie solaire 26 000 mAh', 'batterie-solaire-26-000-mah', 'Panneau solaire, 65 W, étanche IP65.', 69.00, 20.00, 0, 4, 3, 1, 'img/produits/power20.jpg'],
            ['MONTRE-031', 'Montre connectée Fit 3', 'montre-connectee-fit-3', 'Cardio, SpO2, 10 jours d’autonomie, 5 ATM.', 179.00, 20.00, 13, 3, 4, 1, 'img/produits/fit2.jpg'],
            ['MONTRE-032', 'Montre connectée Active', 'montre-connectee-active', 'GPS intégré, 40 profils sportifs, NFC.', 199.00, 20.00, 0, 3, 4, 1, 'img/produits/fit2.jpg'],
            ['MONTRE-035', 'Montre connectée Sport GPS', 'montre-connectee-sport-gps', 'Altimètre, boussole, 20 h en mode GPS.', 229.00, 20.00, 8, 2, 4, 1, 'img/produits/fit2.jpg'],
            ['BRAC-033', 'Bracelet connecté Move', 'bracelet-connecte-move', 'Podomètre, sommeil, 12 jours, écran AMOLED.', 55.00, 20.00, 44, 12, 4, 1, 'img/produits/band.jpg'],
            ['BRAC-034', 'Bracelet connecté Kids', 'bracelet-connecte-kids', 'Suivi d’activité ludique, GPS de secours, 7 jours.', 49.00, 20.00, 30, 10, 4, 1, 'img/produits/band.jpg'],
            ['BRAC-036', 'Bracelet connecté Band Lite', 'bracelet-connecte-band-lite', 'Écran monochrome, 14 jours, étanche 3 ATM.', 29.00, 20.00, 70, 15, 4, 1, 'img/produits/band.jpg'],
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
