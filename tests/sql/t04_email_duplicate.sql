-- =====================================================================
-- T-04 | RB-01 | email client duplique (contrainte unique)
-- Attendu : ERREUR:uk_client_email
-- Le seed contient alice@example.com ; un second INSERT avec le meme
-- email doit etre rejete par la contrainte UNIQUE uk_client_email.
-- =====================================================================
INSERT INTO client (nom, prenom, email, mot_de_passe_hash)
VALUES ('Duplique','Alice','alice@example.com',
        '$2b$12$abcdefghijklmnopqrstuu0123456789abcdefghijklmnopqrstuu');
