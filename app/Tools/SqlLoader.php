<?php

declare(strict_types=1);

namespace App\Tools;

use PDO;
use RuntimeException;

/**
 * MiniShop — chargeur de scripts SQL (portable, sans client `mysql`).
 *
 * Pourquoi ne pas appeler `mysql < sql/01…` en shell ? Parce qu'un projet
 * noté n'a pas le droit de dépendre d'un binaire présent sur une seule
 * machine : CI Linux, poste Windows, conteneur Alpine n'ont pas tous le même
 * client. Ce chargeur lit les fichiers `sql/*.sql`, découpe les instructions
 * en respectant `DELIMITER $$` (indispensable pour les procédures et les
 * déclencheurs) et les exécute par PDO.
 *
 * Il est utilisé par :
 *   - scripts/load_db.php  (chargement d'une base de dev / de test) ;
 *   - scripts/gen_volumes_cli.php --load (avant une campagne de mesure) ;
 *   - App\Tools\SqlLoader::compteObjets() (vérification des objets créés).
 */
final class SqlLoader
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Charge un ou plusieurs fichiers dans l'ordre fourni. */
    public function chargerFichiers(array $fichiers, bool $ignorerBases = false): int
    {
        $total = 0;
        foreach ($fichiers as $fichier) {
            if (!is_file($fichier)) {
                throw new RuntimeException('script SQL introuvable : ' . $fichier);
            }
            $total += $this->chargerFichier($fichier, $ignorerBases);
        }

        return $total;
    }

    /**
     * Charge un fichier SQL et retourne le nombre d'instructions exécutées.
     *
     * $ignorerBases = true : on saute `CREATE/DROP DATABASE` et `USE`, car la
     * connexion PDO pointe déjà sur la base voulue (le fichier 01 est écrit
     * pour le client `mysql`, où il doit créer `minishop` lui-même).
     */
    public function chargerFichier(string $fichier, bool $ignorerBases = false): int
    {
        $contenu = (string) file_get_contents($fichier);
        $instructions = self::decouper($contenu);

        $executees = 0;
        foreach ($instructions as $instruction) {
            if ($ignorerBases && preg_match('/^(DROP|CREATE)\s+DATABASE\b/i', $instruction) === 1) {
                continue;
            }
            if ($ignorerBases && preg_match('/^USE\s+/i', $instruction) === 1) {
                continue;
            }
            $this->pdo->exec($instruction);
            ++$executees;
        }

        return $executees;
    }

    /**
     * Découpe un script SQL en instructions exécutables par PDO.
     *
     * Gère :
     *   - les commentaires `-- …` et `# …` en début de ligne ;
     *   - les chaînes '…' et "…" (les `;` qu'elles contiennent sont ignorés) ;
     *   - `DELIMITER xx` / `DELIMITER ;` (corps de procédures et de triggers).
     *
     * @return list<string>
     */
    public static function decouper(string $script): array
    {
        $instructions = [];
        $courante = '';
        $delimiteur = ';';
        $longueur = strlen($script);
        $guillemet = null;
        $i = 0;

        while ($i < $longueur) {
            $caractere = $script[$i];

            // Dans une chaîne : on ne s'arrête qu'au guillemet fermant.
            if ($guillemet !== null) {
                $courante .= $caractere;
                if ($caractere === '\\' && $i + 1 < $longueur) {
                    $courante .= $script[$i + 1];
                    $i += 2;
                    continue;
                }
                if ($caractere === $guillemet) {
                    $guillemet = null;
                }
                ++$i;
                continue;
            }

            // Commentaire de fin de ligne.
            $debutLigne = ($i === 0) || ($script[$i - 1] === "\n");
            $estCommentaire = $debutLigne
                && ($caractere === '#'
                    || ($caractere === '-' && substr($script, $i, 3) === '-- ')
                    || ($caractere === '-' && substr($script, $i, 3) === '--' . "\n"));
            if ($estCommentaire) {
                $fin = strpos($script, "\n", $i);
                if ($fin === false) {
                    $i = $longueur;
                } else {
                    $i = $fin + 1;
                }
                continue;
            }

            if ($caractere === "'" || $caractere === '"' || $caractere === '`') {
                $guillemet = $caractere;
                $courante .= $caractere;
                ++$i;
                continue;
            }

            // DELIMITER (instruction client, à interpréter ici).
            if ($debutLigne && preg_match('/\G[ \t]*DELIMITER[ \t]+(\S+)/i', $script, $trouve, 0, $i) === 1) {
                $delimiteur = $trouve[1];
                $i += strlen($trouve[0]);
                continue;
            }

            // Fin d'instruction ?
            if (substr($script, $i, strlen($delimiteur)) === $delimiteur) {
                $i += strlen($delimiteur);
                $instruction = trim($courante);
                if ($instruction !== '') {
                    $instructions[] = $instruction;
                }
                $courante = '';
                continue;
            }

            $courante .= $caractere;
            ++$i;
        }

        $reste = trim($courante);
        if ($reste !== '') {
            $instructions[] = $reste;
        }

        return $instructions;
    }

    /**
     * Vérifie que le schéma, les procédures, les fonctions, les déclencheurs
     * et les vues attendus existent réellement (preuve du point 3 de l'audit).
     *
     * @return array{tables:int, procedures:int, fonctions:int, triggers:int, vues:int, details:array<string,int>}
     */
    public function compteObjets(): array
    {
        $compteurs = [];
        $requetes = [
            'tables' => "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'",
            'procedures' => "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = 'PROCEDURE'",
            'fonctions' => "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = 'FUNCTION'",
            'triggers' => "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()",
            'vues' => "SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE()",
        ];

        foreach ($requetes as $nom => $requete) {
            $compteurs[$nom] = (int) $this->pdo->query($requete)->fetchColumn();
        }

        return [
            'tables' => $compteurs['tables'],
            'procedures' => $compteurs['procedures'],
            'fonctions' => $compteurs['fonctions'],
            'triggers' => $compteurs['triggers'],
            'vues' => $compteurs['vues'],
            'details' => $compteurs,
        ];
    }
}
