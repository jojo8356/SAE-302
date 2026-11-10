-- L'exploitant choisit expressément d'appliquer le nouveau défaut à son instance.
-- La valeur n'est modifiée que si elle vaut encore l'ancien défaut 36.
-- À coordonner avec l'installation du traitement planifié et une sauvegarde.
UPDATE parametre SET valeur = '24',
    comment = 'Delai depuis la derniere activite du compte ; traitement planifie a installer'
WHERE cle = 'conservation_compte' AND valeur = '36';
