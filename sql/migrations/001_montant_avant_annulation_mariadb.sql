-- MariaDB uniquement. Prévoir sauvegarde vérifiée, maintenance et contrôle du
-- schéma AVANT toute exécution. Ne contient ni DROP, ni seed, ni mise à zéro.
-- DDL MariaDB effectue un commit implicite ; cette migration ne se rollback pas.
-- Les anciens montants inconnus restent NULL : aucun montant n'est inventé.
-- IF NOT EXISTS ne corrige pas une colonne préexistante de type incompatible :
-- vérifier ensuite DECIMAL(10,2), nullable, et arrêter si le type est différent.
ALTER TABLE commande
  ADD COLUMN IF NOT EXISTS montant_avant_annulation DECIMAL(10,2) NULL
  COMMENT 'total avant annulation soldee ; NULL si historique inconnu';
