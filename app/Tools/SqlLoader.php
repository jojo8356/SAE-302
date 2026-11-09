<?php

declare(strict_types=1);

namespace App\Tools;

use PDO;
use PDOException;
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
        $numero = 0;
        foreach ($instructions as $instruction) {
            ++$numero;
            if ($ignorerBases && preg_match('/^(DROP|CREATE)\s+DATABASE\b/i', $instruction) === 1) {
                continue;
            }
            if ($ignorerBases && preg_match('/^USE\s+/i', $instruction) === 1) {
                continue;
            }
            try {
                $this->pdo->exec($instruction);
            } catch (PDOException $erreur) {
                // Sans ce contexte, l'utilisateur reçoit un « SQLSTATE[42000] »
                // anonyme alors que le fichier SQL peut contenir 300 instructions.
                $extrait = preg_replace('/\s+/', ' ', substr($instruction, 0, 160));
                throw new RuntimeException(
                    'échec dans ' . basename($fichier) . ', instruction ' . $numero
                    . ' (sur ' . count($instructions) . ') : ' . $erreur->getMessage()
                    . "\n    début de l’instruction : " . $extrait,
                    0,
                    $erreur,
                );
            }
            ++$executees;
        }

        return $executees;
    }

    /**
     * Découpe un script SQL en instructions exécutables par PDO.
     *
     * Gère :
     *   - les commentaires `-- …`, `# …` et `/* … *\/` **y compris indentés**
     *     et en fin de ligne (MySQL : `--` suivi d'un blanc) ;
     *   - les chaînes '…' et "…" (avec `\\` et le doublement `''`), les
     *     identifiants `…` — les `;` qu'ils contiennent sont ignorés ;
     *   - `DELIMITER xx` / `DELIMITER ;` (corps de procédures et de triggers).
     *
     * Le bug corrigé ici mérite d'être raconté : la première version ne
     * reconnaissait un commentaire qu'en **colonne 0**. Or ces fichiers
     * contiennent des commentaires indentés (`  -- c'est l'interet de …`).
     * L'apostrophe de « l'interet » faisait donc basculer le lecteur en mode
     * chaîne : le délimiteur `$$` n'était plus vu, plusieurs procédures
     * étaient recollées en une seule instruction, et MariaDB répondait
     * « syntax error near '$$' ». Le symptôme désignait MariaDB alors que la
     * faute était dans ce fichier.
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

            // 1. Dans une chaîne / un identifiant : seul le guillemet fermant
            //    (ou son doublement, ou un échappement) compte.
            if ($guillemet !== null) {
                if ($caractere === '\\' && $i + 1 < $longueur) {
                    $courante .= $caractere . $script[$i + 1];
                    $i += 2;
                    continue;
                }
                // MySQL accepte '' dans '…' et "" dans "…" pour échapper.
                if ($caractere === $guillemet && $i + 1 < $longueur && $script[$i + 1] === $guillemet) {
                    $courante .= $caractere . $caractere;
                    $i += 2;
                    continue;
                }
                $courante .= $caractere;
                if ($caractere === $guillemet) {
                    $guillemet = null;
                }
                ++$i;
                continue;
            }

            // 2. DELIMITER : instruction du client, interprétée ici. Reconnue
            //    en début de ligne, indentation comprise.
            if (preg_match('/\G[ \t]*DELIMITER[ \t]+(\S+)/i', $script, $trouve, 0, $i) === 1
                && ($i === 0 || $script[$i - 1] === "\n")) {
                $delimiteur = $trouve[1];
                $i += strlen($trouve[0]);
                // On saute la fin de ligne : elle ne contient rien d'exécutable.
                $finLigne = strpos($script, "\n", $i);
                if ($finLigne === false) {
                    $i = $longueur;
                } else {
                    $i = $finLigne + 1;
                }
                continue;
            }

            // 3. Commentaires — où qu'ils soient, y compris indentés.
            //    `--` n'ouvre un commentaire que suivi d'un blanc (règle MySQL) :
            //    `a - -b` reste du code, `1--2` aussi.
            $suitBlanc = ($i + 2 < $longueur)
                && in_array($script[$i + 2], [' ', "\t", "\n", "\r"], true);
            $estTiretTiret = ($caractere === '-' && substr($script, $i, 2) === '--'
                && ($i + 2 >= $longueur || $suitBlanc));
            if ($caractere === '#' || $estTiretTiret) {
                $finLigne = strpos($script, "\n", $i);
                if ($finLigne === false) {
                    $i = $longueur;
                } else {
                    $i = $finLigne + 1;
                }
                continue;
            }
            if ($caractere === '/' && substr($script, $i, 2) === '/*') {
                // Les commentaires « exécutables » de MySQL (`/*!40101 … */`)
                // portent des instructions : on les conserve tels quels.
                if (substr($script, $i, 3) === '/*!') {
                    $courante .= '/*!';
                    $i += 3;
                    continue;
                }
                $fermeture = strpos($script, '*/', $i + 2);
                if ($fermeture === false) {
                    $i = $longueur;
                } else {
                    $i = $fermeture + 2;
                }
                continue;
            }

            // 4. Guillemets : on entre en mode chaîne.
            if ($caractere === "'" || $caractere === '"' || $caractere === '`') {
                $guillemet = $caractere;
                $courante .= $caractere;
                ++$i;
                continue;
            }

            // 5. Fin d'instruction ?
            $longueurDelimiteur = strlen($delimiteur);
            if (substr($script, $i, $longueurDelimiteur) === $delimiteur) {
                $i += $longueurDelimiteur;
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
