-- =====================================================================
-- MiniShop — 01 : script de création de la base de données (DDL + données)
-- ---------------------------------------------------------------------
-- SAE · BUT Informatique / L3 — groupe de 3 étudiants
-- SGBD cible  : MySQL 8.0.16 ou plus récent (les CHECK sont appliqués
--               depuis la 8.0.16) — testé aussi sur MariaDB 11.8.
-- Charset     : utf8mb4 / utf8mb4_unicode_ci (identique MySQL et MariaDB)
-- Moteur      : InnoDB (transactions, clés étrangères, verrouillage de lignes)
--
-- Chargement (depuis la racine du dépôt) :
--   mysql -u root -p                     < sql/01_minishop_schema.sql
--   mysql -u root -p minishop            < sql/02_minishop_procedures.sql
--   mysql -u root -p minishop            < sql/03_minishop_triggers.sql
--   mysql -u root -p minishop            < sql/04_minishop_demo.sql
-- (le fichier 01 crée la base et fait USE ; 02/03 contiennent la directive
--  DELIMITER ; 03/04 supposent la base et les objets des étapes précédentes)
--
-- Ou, via PHP PDO (script public/install.php) : exécuter les 3 fichiers
-- dans cet ordre (le fichier 02 contient des DELIMITER, donc il faut le
-- client mysql ou un parseur dédié ; en PDO, copier le corps des routines
-- sans la ligne DELIMITER).
--
-- Conventions de nommage (règle CT-nommage) :
--   tables      MAJUSCULES au pluriel        CLIENT, PRODUIT, LIGNE_COMMANDE
--   clés primaires id_<table-singulier>      id_client, id_produit
--   clés étrangères  <table-référencée>_id    id_commande (fk vers COMMANDE)
--   index          idx_*, uniques uk_*, FK fk_*
--
-- Le document « Spécification du système » §12 et le document
-- « Conception de BD » §2 décrivent la justification de chaque choix.
-- =====================================================================

-- (le harnais scripts/load_db.sh cree la base ; le USE ci-dessous rend le
--  fichier autonome lorsqu'il est joue directement : mysql < sql/01...sql)
DROP DATABASE IF EXISTS minishop;   -- à retirer si la base est partagée
CREATE DATABASE IF NOT EXISTS minishop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE minishop;

-- Collation de connexion = collation de la base. Les procédures et les
-- déclencheurs MÉMORISENT la collation de la connexion qui les a créés :
-- un chargement en utf8mb4_general_ci (défaut de `SET NAMES utf8mb4` selon
-- la version du serveur) provoque ensuite l'erreur 1267 « Illegal mix of
-- collations » quand l'application appelle sp_search_products.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 0;           -- permet un rejeu idempotent du script

-- ---------------------------------------------------------------------
-- Table ADMINISTRATEUR  (RB-13 : seul un compte du Back-office gère le catalogue)
-- ---------------------------------------------------------------------
CREATE TABLE administrateur (
  id_admin            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom                 VARCHAR(100) NOT NULL,
  prenom              VARCHAR(100) NOT NULL,
  email               VARCHAR(190) NOT NULL,
  mot_de_passe_hash   CHAR(60)     NOT NULL COMMENT 'sortie de password_hash() : jamais de mot de passe en clair (RB-12)',
  role                ENUM('SUPER','GESTIONNAIRE') NOT NULL DEFAULT 'GESTIONNAIRE'
                      COMMENT 'SUPER = peut créer/bloquer des comptes admins',
  actif               TINYINT(1)   NOT NULL DEFAULT 1,
  date_creation       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  derniere_connexion  DATETIME     NULL,
  CONSTRAINT pk_administrateur PRIMARY KEY (id_admin),
  CONSTRAINT uk_admin_email    UNIQUE KEY (email),
  CONSTRAINT ck_admin_actif    CHECK (actif IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Personnel du Back-office : produits, catégories, stocks, commandes';

-- ---------------------------------------------------------------------
-- Table CLIENT
-- RB-01 : l'email est unique (contrainte uk_client_email)
-- ---------------------------------------------------------------------
CREATE TABLE client (
  id_client           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom                 VARCHAR(100) NOT NULL,
  prenom              VARCHAR(100) NOT NULL,
  email               VARCHAR(190) NOT NULL,
  mot_de_passe_hash   CHAR(60)     NOT NULL COMMENT 'password_hash(PASSWORD_BCRYPT) ; +10 si re-hash ultérieur',
  telephone           VARCHAR(20)  NULL,
  adresse_livraison   VARCHAR(255) NULL,
  code_postal         VARCHAR(10)  NULL,
  ville               VARCHAR(100) NULL,
  actif               TINYINT(1)   NOT NULL DEFAULT 1,
  date_creation       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  derniere_connexion  DATETIME     NULL,
  CONSTRAINT pk_client        PRIMARY KEY (id_client),
  CONSTRAINT uk_client_email  UNIQUE KEY (email),
  CONSTRAINT ck_client_actif  CHECK (actif IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Client inscrit : panier, commandes, historique';

-- ---------------------------------------------------------------------
-- Table CATEGORIE
-- RB-07 / RB-08 : 1 catégorie -> N produits ; 1 produit -> exactement 1 catégorie
-- ---------------------------------------------------------------------
CREATE TABLE categorie (
  id_categorie      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom               VARCHAR(100) NOT NULL,
  slug              VARCHAR(120) NOT NULL,
  description       VARCHAR(500) NULL,
  CONSTRAINT pk_categorie       PRIMARY KEY (id_categorie),
  CONSTRAINT uk_categorie_nom   UNIQUE KEY (nom),
  CONSTRAINT uk_categorie_slug  UNIQUE KEY (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Rayon du catalogue';

-- ---------------------------------------------------------------------
-- Table PRODUIT
-- RB-02 : prix strictement positif      (ck_produit_prix, défaut NOT NULL)
-- RB-03 : stock jamais négatif          (ck_produit_stock + trg_produit_stock_non_negatif)
-- RB-14 : référence et slug uniques
-- ---------------------------------------------------------------------
CREATE TABLE produit (
  id_produit       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference        VARCHAR(30)  NOT NULL,
  nom              VARCHAR(150) NOT NULL,
  slug             VARCHAR(180) NOT NULL,
  description      TEXT         NULL,
  prix_ht          DECIMAL(10,2) NOT NULL,
  tva              DECIMAL(5,2)  NOT NULL DEFAULT 20.00,
  prix_ttc         DECIMAL(10,2) NOT NULL
                   COMMENT 'généré par trg_produit_ttc = ROUND(prix_ht*(1+tva/100),2) ; jamais écrit par l''application',
  stock            INT          NOT NULL DEFAULT 0 COMMENT 'quantité physique disponible (RB-03)',

  stock_initial  INT          NOT NULL DEFAULT -1 COMMENT 'quantite du seed : etat de reference de la fixture de test (-1 = pas encore renseigne ; alimente par trg_produit_ttc)',
  seuil_alerte     INT          NOT NULL DEFAULT 3 COMMENT 'dessous : produit en alerte (EF-ADM-05)',
  id_categorie     INT UNSIGNED NOT NULL COMMENT 'RB-07 : une seule catégorie par produit',
  visible          TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1 = visible côté client',
  image_url        VARCHAR(255) NULL,
  date_creation    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  date_modification DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_produit          PRIMARY KEY (id_produit),
  CONSTRAINT fk_produit_categorie FOREIGN KEY (id_categorie)
        REFERENCES categorie (id_categorie) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT uk_produit_reference UNIQUE KEY (reference),
  CONSTRAINT uk_produit_slug      UNIQUE KEY (slug),
  CONSTRAINT ck_produit_prix      CHECK (prix_ht > 0),
  CONSTRAINT ck_produit_stock     CHECK (stock >= 0),
  CONSTRAINT ck_produit_visible   CHECK (visible IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Article du catalogue (RB-02, RB-03, RB-07, RB-14)';

-- Ligne ajoutée après coup pour alléger la création de table (fulltext recherche EF-VIS-02)
ALTER TABLE produit ADD FULLTEXT KEY ft_produit_recherche (nom, description);
CREATE INDEX idx_produit_categorie ON produit (id_categorie);
CREATE INDEX idx_produit_visible    ON produit (visible, stock);

-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS parametre;
CREATE TABLE parametre (
  cle     VARCHAR(50)  NOT NULL,
  valeur  VARCHAR(100) NOT NULL,
  unite   VARCHAR(10)  NULL COMMENT 'EUR, %, mois, jours...',
  comment VARCHAR(255) NULL,
  date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_parametre PRIMARY KEY (cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO parametre (cle, valeur, unite, comment) VALUES
  ('frais_port',            '4.90',  'EUR TTC', 'Livraison standard, montant affiche avant la validation (art. L221-5 4 deg C. conso.)'),
  ('franchise_port',        '80.00', 'EUR TTC', 'Port offert au-dela de ce montant de marchandises'),
  ('tva_defaut',            '20.00', '%',       'Taux par defaut propose a la creation d un produit'),
  ('conservation_compte',   '36',    'mois',    'Donnees client en base active apres le dernier contact (referentiel CNIL)');

-- ---------------------------------------------------------------------
-- Table COMMANDE
-- RB-10 : 1 commande -> 1 seul client (id_client NOT NULL)
-- RB-11 : statut contraint par l'ENUM + la matrice de transitions
-- RB-15 : montant_total est une somme calculée, jamais une saisie
-- ---------------------------------------------------------------------
-- ---------------------------------------------------------------------
-- Table de parametres metier : les regles chiffrees (frais de port, seuil de
-- franchise, TVA par defaut, durees de conservation) sont des DONNEES et non
-- des constantes recopiees dans le code PHP : c'est ce qui permet de les faire
-- evoluer sans toucher a une ligne de SQL ni a une ligne de PHP, et de les
-- tracer. Les procedures les lisent via fn_param() (RB-15, ENF-14).

CREATE TABLE commande (
  id_commande      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero           VARCHAR(20)  NOT NULL COMMENT 'numéro lisible CMD2026-000123',
  id_client        INT UNSIGNED NOT NULL,
  adresse_livraison VARCHAR(255) NOT NULL,
  statut           ENUM('BROUILLON','EN_PREPARATION','PAYEE','EXPEDIEE','LIVREE','ANNULEE')
                   NOT NULL DEFAULT 'BROUILLON',
  montant_total    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  frais_port       DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'somme figee au snapshot, pas une saisie (RB-15)',
  commentaire      VARCHAR(500) NULL,
  date_commande    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  date_modification DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_commande       PRIMARY KEY (id_commande),
  CONSTRAINT uk_commande_numero UNIQUE KEY (numero),
  CONSTRAINT fk_commande_client FOREIGN KEY (id_client)
        REFERENCES client (id_client) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT ck_commande_montant CHECK (montant_total >= 0),
  CONSTRAINT ck_commande_port    CHECK (frais_port >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Commande d''un client (RB-10, RB-11, RB-15)';

CREATE INDEX idx_commande_client ON commande (id_client);
CREATE INDEX idx_commande_statut ON commande (statut);

-- ---------------------------------------------------------------------
-- Table LIGNE_COMMANDE
-- RB-04 : au moins une ligne (contrôle dans sp_confirm_order + trg_commande_delete)
-- RB-05 : quantité > 0 (ck + défaut NOT NULL)
-- RB-06 : prix_unitaire = prix TTC AU MOMENT DE L'ACHAT (figé par trg_ligne_prix_snapshot)
-- ---------------------------------------------------------------------
CREATE TABLE ligne_commande (
  id_ligne        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_commande     INT UNSIGNED NOT NULL,
  id_produit      INT UNSIGNED NOT NULL,
  quantite        INT          NOT NULL,
  prix_unitaire   DECIMAL(10,2) NOT NULL COMMENT 'RB-06 : snapshot du prix au moment de l''achat',
  total_ligne     DECIMAL(10,2) NOT NULL COMMENT 'généré = quantite * prix_unitaire',
  CONSTRAINT pk_ligne_commande PRIMARY KEY (id_ligne),
  CONSTRAINT fk_ligne_commande FOREIGN KEY (id_commande)
        REFERENCES commande (id_commande) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ligne_produit  FOREIGN KEY (id_produit)
        REFERENCES produit (id_produit) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_ligne_quantite CHECK (quantite > 0),
  CONSTRAINT ck_ligne_prix     CHECK (prix_unitaire > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Détail d''une commande ; produit fige a l instant T';

CREATE INDEX idx_ligne_commande ON ligne_commande (id_commande);
CREATE INDEX idx_ligne_produit  ON ligne_commande (id_produit);

-- ---------------------------------------------------------------------
-- Table ORDER_STATUS_HISTORY
-- Historique des changements de statut, alimenté UNIQUEMENT par le
-- trigger trg_history_statut (personne ne peut « oublier » de tracer).
-- ---------------------------------------------------------------------
CREATE TABLE order_status_history (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id      INT UNSIGNED NOT NULL,
  old_status    VARCHAR(20)  NULL,
  new_status    VARCHAR(20)  NOT NULL,
  changed_by    INT UNSIGNED NULL COMMENT 'id_client si le client annule, sinon id_admin',
  changed_by_role ENUM('CLIENT','ADMIN','SYSTEME') NOT NULL DEFAULT 'SYSTEME',
  commentaire   VARCHAR(500) NULL,
  changed_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_history PRIMARY KEY (id),
  CONSTRAINT fk_history_commande FOREIGN KEY (order_id)
        REFERENCES commande (id_commande) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Piste d''audit des statuts (RB-11) — écriture automatique par trigger';

-- ---------------------------------------------------------------------
-- FIN DES TABLES — insertion du jeu de données de démonstration
-- (mot de passe des comptes de test : Demo2026! pour les clients,
--  Admin2026! pour le back-office — hash générés par password_hash()).
-- ---------------------------------------------------------------------
INSERT INTO administrateur (nom, prenom, email, mot_de_passe_hash, role) VALUES
  ('Nguyen','Alice','admin@minishop.fr','$2b$10$ASp02qmyg5r3VvXRIV60/ObRO3IDB6bJ7DWgH20FOECvoNtDPNNW2','SUPER');

INSERT INTO categorie (nom, slug, description) VALUES
  ('Informatique','informatique','Ordinateurs portables, périphériques et composants'),
  ('Audio','audio','Casques, enceintes et microphones'),
  ('Accessoires','accessoires','Sacs, câbles, supports et batteries'),
  ('Objets connectés','objets-connectes','Montres et bracelets connectés');

-- id_categorie : 1 Informatique, 2 Audio, 3 Accessoires, 4 Objets connectés
-- prix_ttc est posé ici volontairement : le trigger trg_produit_ttc le
-- recalcule à chaque écriture suivante (il sert surtout en UPDATE).
INSERT INTO produit
  (reference, nom, slug, description, prix_ht, tva, prix_ttc, stock, seuil_alerte, id_categorie, visible, image_url) VALUES
  ('PC-PORT-001','Ordinateur portable Zen 14','ordinateur-portable-zen-14','Écran IPS 14", 16 Go de RAM, SSD 512 Go, autonomie 12 h.',699.00,20.00,838.80,12,3,1,1,'img/produits/zen14.jpg'),
  ('PC-FIXE-002','Station de travail Turbo X','station-de-travail-turbo-x','Boîtier compact, 8 cœurs, 32 Go de RAM, SSD NVMe 1 To.',1249.00,20.00,1498.80,4,2,1,1,'img/produits/turbox.jpg'),
  ('ECRA-003','Écran 27" QHD','ecran-27-qhd','Dalle IPS QHD 75 Hz, HDR10, pied réglable.',179.90,20.00,215.88,25,5,1,1,'img/produits/qhd27.jpg'),
  ('CLAV-004','Clavier mécanique K87','clavier-mecanique-k87','Switches tactiles, rétroéclairage, sans fil 2,4 GHz.',79.00,20.00,94.80,40,10,1,1,'img/produits/k87.jpg'),
  ('CASQ-005','Casque sans fil Aura','casque-sans-fil-aura','Réduction de bruit active, 35 h d''autonomie.',129.00,20.00,154.80,18,5,2,1,'img/produits/aura.jpg'),
  ('ENCH-006','Enceinte portable Boom','enceinte-portable-boom','IPX7, 20 h d''autonomie, stéréo TrueWireless.',59.00,20.00,70.80,30,8,2,1,'img/produits/boom.jpg'),
  ('MICRO-007','Micro studio ProCast','micro-studio-procast','Condensateur cardioïde, bras articulé inclus.',99.00,20.00,118.80,2,2,2,1,'img/produits/procast.jpg'),
  ('SAC-008','Sac à dos 25 L nomade','sac-a-dos-25-l-nomade','Compartiment 16", tissu déperlant, port USB.',49.00,20.00,58.80,22,6,3,1,'img/produits/sac25.jpg'),
  ('CABLE-009','Cable USB-C 2 m renforcé','cable-usb-c-2-m-renforce','Charge 100 W, transfert 10 Gbps, tresse nylon.',12.50,20.00,15.00,150,30,3,1,'img/produits/usbc.jpg'),
  ('POWER-010','Batterie externe 20 000 mAh','batterie-externe-20-000-mah','Charge rapide 65 W, deux ports USB-C.',45.00,20.00,54.00,0,5,3,1,'img/produits/power20.jpg'),
  ('MONTRE-011','Montre connectée Fit 2','montre-connectee-fit-2','Cardio, GPS, 14 jours d''autonomie, étanche 5 ATM.',149.00,20.00,178.80,9,3,4,1,'img/produits/fit2.jpg'),
  ('BRAC-012','Bracelet connecté Band','bracelet-connecte-band','Suivi du sommeil et des notifications, 10 jours.',35.00,20.00,42.00,60,15,4,0,'img/produits/band.jpg'),
  ('PC-PORT-013','Ordinateur portable Zen 16','ordinateur-portable-zen-16','Dalle 16 pouces, 32 Go de RAM, SSD 1 To.',899.00,20.00,1078.80,8,2,1,1,'img/produits/zen14.jpg'),
  ('PC-PORT-014','Ordinateur portable Zen Air','ordinateur-portable-zen-air','Ultraléger 1,1 kg, 16 Go de RAM, autonomie 18 h.',1049.00,20.00,1258.80,5,2,1,1,'img/produits/zen14.jpg'),
  ('PC-FIXE-015','Station de travail Turbo S','station-de-travail-turbo-s','6 cœurs, 16 Go de RAM, SSD 512 Go, silencieux.',949.00,20.00,1138.80,6,2,1,1,'img/produits/turbox.jpg'),
  ('ECRA-016','Dalle 32 pouces 4K','dalle-32-pouces-4k','3840 x 2160, 60 Hz, DisplayPort et HDMI.',429.00,20.00,514.80,12,3,1,1,'img/produits/qhd27.jpg'),
  ('CLAV-017','Clavier compact K65','clavier-compact-k65','Format 65 %, switches linéaires, USB-C détachable.',59.00,20.00,70.80,26,8,1,1,'img/produits/k87.jpg'),
  ('CLAV-018','Clavier sans fil K50','clavier-sans-fil-k50','Membrane silencieuse, pavé numérique, pile 24 mois.',45.00,20.00,54.00,33,10,1,1,'img/produits/k87.jpg'),
  ('CASQ-019','Casque filaire Studio','casque-filaire-studio','Transducteurs 50 mm, câble détachable, pliable.',89.00,20.00,106.80,14,4,2,1,'img/produits/aura.jpg'),
  ('CASQ-020','Casque nomade Compact','casque-nomade-compact','Bluetooth 5.3, réduction passive, 28 h.',69.00,20.00,82.80,21,6,2,1,'img/produits/aura.jpg'),
  ('ENCH-021','Enceinte nomade Mini','enceinte-nomade-mini','Format poche, IPX5, 12 h d''autonomie.',39.00,20.00,46.80,40,10,2,1,'img/produits/boom.jpg'),
  ('ENCH-022','Enceinte de bureau Duo','enceinte-de-bureau-duo','Paire stéréo, alimentation USB, réglage de tonalité.',79.00,20.00,94.80,17,5,2,1,'img/produits/boom.jpg'),
  ('MICRO-023','Micro USB Podcast','micro-usb-podcast','Cardioïde, USB-C, socle anti-vibration.',75.00,20.00,90.00,11,3,2,1,'img/produits/procast.jpg'),
  ('MICRO-024','Micro USB Conférence','micro-usb-conference','Omnidirectionnel, portée 3 m, pied télescopique.',109.00,20.00,130.80,7,2,2,1,'img/produits/procast.jpg'),
  ('SAC-025','Sac à dos 18 L urbain','sac-a-dos-18-l-urbain','Compartiment ordinateur 14 pouces, dos respirant.',39.00,20.00,46.80,28,8,3,1,'img/produits/sac25.jpg'),
  ('SAC-026','Sacoche 13 pouces quotidien','sacoche-13-pouces-quotidien','Renforts, poche avant, bandoulière réglable.',29.00,20.00,34.80,35,10,3,1,'img/produits/sac25.jpg'),
  ('CABLE-027','Câble USB-C 1 m tressé','cable-usb-c-1-m-tresse','Charge 60 W, transfert 480 Mbps, connecteurs renforcés.',8.50,20.00,10.20,200,40,3,1,'img/produits/usbc.jpg'),
  ('CABLE-028','Câble USB-C 3 m pliable','cable-usb-c-3-m-pliable','Charge 100 W, rangement intégré, gaine souple.',16.00,20.00,19.20,90,20,3,1,'img/produits/usbc.jpg'),
  ('POWER-029','Batterie externe 10 000 mAh','batterie-externe-10-000-mah','Compacte, 30 W, un port USB-C et un USB-A.',25.00,20.00,30.00,60,15,3,1,'img/produits/power20.jpg'),
  ('POWER-030','Batterie solaire 26 000 mAh','batterie-solaire-26-000-mah','Panneau solaire, 65 W, étanche IP65.',69.00,20.00,82.80,0,4,3,1,'img/produits/power20.jpg'),
  ('MONTRE-031','Montre connectée Fit 3','montre-connectee-fit-3','Cardio, SpO2, 10 jours d''autonomie, 5 ATM.',179.00,20.00,214.80,13,3,4,1,'img/produits/fit2.jpg'),
  ('MONTRE-032','Montre connectée Active','montre-connectee-active','GPS intégré, 40 profils sportifs, NFC.',199.00,20.00,238.80,0,3,4,1,'img/produits/fit2.jpg'),
  ('MONTRE-035','Montre connectée Sport GPS','montre-connectee-sport-gps','Altimètre, boussole, 20 h en mode GPS.',229.00,20.00,274.80,8,2,4,1,'img/produits/fit2.jpg'),
  ('BRAC-033','Bracelet connecté Move','bracelet-connecte-move','Podomètre, sommeil, 12 jours, écran AMOLED.',55.00,20.00,66.00,44,12,4,1,'img/produits/band.jpg'),
  ('BRAC-034','Bracelet connecté Kids','bracelet-connecte-kids','Suivi d''activité ludique, GPS de secours, 7 jours.',49.00,20.00,58.80,30,10,4,1,'img/produits/band.jpg'),
  ('BRAC-036','Bracelet connecté Band Lite','bracelet-connecte-band-lite','Écran monochrome, 14 jours, étanche 3 ATM.',29.00,20.00,34.80,70,15,4,1,'img/produits/band.jpg');

INSERT INTO client (nom, prenom, email, mot_de_passe_hash, telephone, adresse_livraison, code_postal, ville) VALUES
  ('Dupont','Alice','alice@example.com','$2b$10$ldtyUy7EyQ4YJ0L8qBWZxuTuefzalZmCDOnXNnBHHZNm8cZqOerjO','0612345678','12 rue de France','06000','Nice'),
  ('Martin','Bruno','bruno@example.com','$2b$10$ldtyUy7EyQ4YJ0L8qBWZxuTuefzalZmCDOnXNnBHHZNm8cZqOerjO','0698765432','5 avenue Jean Médecin','06000','Nice'),
  ('Moretti','Carla','carla@example.com','$2b$10$ldtyUy7EyQ4YJ0L8qBWZxuTuefzalZmCDOnXNnBHHZNm8cZqOerjO',NULL,'8 rue Papin','06300','Nice');

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- VUES : une seule définition des formules partagées par le front-office,
-- le back-office et la procédure sp_search_products (évite la divergence
-- de calcul entre PHP et SQL, cf. RB-16 / RB-19 / ENF-13).
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_etat_stock AS
SELECT p.id_produit AS id_produit, p.reference AS reference, p.nom AS nom, p.stock AS stock, p.seuil_alerte AS seuil_alerte,
       CASE WHEN p.stock = 0                     THEN 'RUPTURE'
            WHEN p.stock <= p.seuil_alerte        THEN 'TRES_BAS'
            ELSE 'DISPONIBLE' END AS etat
  FROM produit p;

CREATE OR REPLACE VIEW v_catalogue AS
SELECT p.id_produit, p.reference, p.slug, p.nom, p.description, p.prix_ht, p.tva,
       p.prix_ttc, p.stock, p.visible, p.image_url,
       c.id_categorie, c.nom AS categorie, c.slug AS categorie_slug,
       e.etat
  FROM produit p
  JOIN categorie c   ON c.id_categorie = p.id_categorie
  JOIN v_etat_stock e ON e.id_produit  = p.id_produit
 WHERE p.visible = 1;

CREATE OR REPLACE VIEW v_commandes_client AS
SELECT o.id_commande, o.numero, o.statut, o.montant_total, o.frais_port, o.adresse_livraison,
       o.date_commande, o.id_client,
       CONCAT(cl.prenom, ' ', cl.nom) AS client,
       (SELECT COUNT(*) FROM ligne_commande l WHERE l.id_commande = o.id_commande) AS nb_lignes
  FROM commande o
  JOIN client cl ON cl.id_client = o.id_client;

-- =====================================================================
-- Historique de l'application : les commandes de démonstration sont
-- créées par les procédures stockées (voir sql/04_minishop_demo_orders.sql)
-- afin que les triggers fassent réellement sonner le dispositif.
-- =====================================================================
