-- =====================================================================
-- T-05 | RB-12 | sp_create_account refuse un mot de passe non hashe
-- Attendu : ERREUR:HASH_MOT_DE_PASSE_INVALIDE
-- La procedure sp_create_account verifie que p_hash fait au moins
-- 60 caracteres (sortie de password_hash). Envoyer le mot de passe
-- en clair ("toto") doit etre refuse par SIGNAL.
-- =====================================================================
CALL sp_create_account(
  'TestHash','Bob','bob-t05@example.invalid',
  'toto',   -- mot de passe en clair, trop court pour etre un hash bcrypt
  @new_id, @code);
