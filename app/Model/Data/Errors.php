<?php

/**
 * MiniShop — taxonomie des erreurs de la couche de gestion des données.
 *
 * DOCUMENTATION SEULEMENT : les classes vivent chacune dans son propre
 * fichier (autoloading PSR-4 — une classe par fichier) :
 *
 *  - QueryError      (QueryError.php)      ≈ erreur d'appel de l'API du store ;
 *  - ConstraintError (ConstraintError.php) ≈ violation UNIQUE / CHECK / ENUM / FK
 *                                           (≈ SQLSTATE 23000) ;
 *  - BusinessError   (BusinessError.php)   ≈ règle métier refusée par une
 *                                           « procédure » ou un « déclencheur »
 *                                           du moteur (≈ SIGNAL SQLSTATE '45000',
 *                                           codes identiques à sql/02 et sql/03) ;
 *  - SchemaError     (SchemaError.php)     ≈ table, colonne ou vue inconnue
 *                                           (≈ SQLSTATE 42S02/42S22).
 *
 * Le pilote JSON et le pilote SQL/PDO partagent cette taxonomie :
 * SqlStore traduit notamment les violations de contrainte SQL en
 * ConstraintError. Ce fichier est documentaire, non chargé par l’autoloader.
 */
