-- =====================================================================
-- T-04 | RB-01 | email client duplique (contrainte unique)
-- Attendu : ERREUR:uk_client_email
-- Le seed contient alice@example.com ; un second INSERT avec le meme
-- email doit etre rejete par la contrainte UNIQUE uk_client_email.
-- Le hash fait EXACTEMENT 60 caracteres : mot_de_passe_hash est CHAR(60), et
-- une chaine plus longue est rejetee avant la contrainte UNIQUE (erreur 1406
-- « Data too long ») — le test passait alors pour la mauvaise raison, ou
-- echouait sur un message sans rapport avec RB-01.
-- =====================================================================
INSERT INTO client (nom, prenom, email, mot_de_passe_hash)
VALUES ('Duplique','Alice','alice@example.com',
        '$2b$12$Kf9Qm2Xr7Tb4Lw8Zn3Vc6Yd1Hs5Ja0PeKf9Qm2Xr7Tb4Lw8Zn3Vc6');
