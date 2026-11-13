<?php

declare(strict_types=1);

/**
 * Diagnostic préparatoire de D1-L15-01 — staging uniquement.
 *
 * Cette page ne lance jamais scripts/deploy.php et n'effectue aucune
 * connexion de diagnostic, requête SQL ou écriture. La garde SUPER et le
 * layout commun conservent leurs lectures de session habituelles.
 */

namespace App\Controller\Admin;

use App\Config\Env;
use App\Controller\Controller;
use App\Security\Auth;

final class DiagnosticAdminController extends Controller
{
    public function index(): void
    {
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow');

        Auth::exigeSuper();

        // Drapeau absent par défaut : n'afficher les vérifications d'hôte que
        // lorsque l'équipe a explicitement activé cette page sur le staging.
        $actif = Env::get('MINISHOP_STAGING_DIAGNOSTIC', '0') === '1';
        $sections = [];
        if ($actif) {
            $sections = $this->diagnostics();
        }
        $compteurs = ['ok' => 0, 'attention' => 0, 'bloque' => 0, 'manuel' => 0, 'info' => 0];

        foreach ($sections as $section) {
            foreach ($section['controles'] as $controle) {
                ++$compteurs[$controle['etat']];
            }
        }

        $this->render('admin/diagnostic', [
            'actif' => $actif,
            'sections' => $sections,
            'compteurs' => $compteurs,
            'nombreControles' => array_sum($compteurs),
        ], 'Back-office — diagnostic staging D1-L15-01');
    }

    /**
     * Contrôles du runtime web et indicateurs non secrets de configuration.
     * Les réglages du processus CLI et la cible SQL ne sont jamais validés ici.
     *
     * @return list<array{titre:string,controles:list<array{libelle:string,etat:string,detail:string}>}>
     */
    private function diagnostics(): array
    {
        $controle = static function (string $libelle, string $etat, string $detail): array {
            return ['libelle' => $libelle, 'etat' => $etat, 'detail' => $detail];
        };

        $pdoDisponible = class_exists(\PDO::class);
        $pdoDrivers = [];
        if ($pdoDisponible) {
            $pdoDrivers = \PDO::getAvailableDrivers();
        }
        $pdoMysqlDisponible = in_array('mysql', $pdoDrivers, true);
        $versionPhpSuffisante = PHP_VERSION_ID >= 80200;
        $bcryptDisponible = function_exists('password_hash')
            && function_exists('password_algos')
            && defined('PASSWORD_BCRYPT')
            && in_array(PASSWORD_BCRYPT, password_algos(), true);
        $randomBytesDisponible = function_exists('random_bytes');

        $etatPhp = 'bloque';
        if ($versionPhpSuffisante) {
            $etatPhp = 'ok';
        }
        $etatPdo = 'bloque';
        $detailPdo = 'Extension PDO absente du serveur web.';
        if ($pdoDisponible) {
            $etatPdo = 'ok';
            $detailPdo = 'Extension PDO disponible dans le serveur web.';
        }
        $etatPdoMysql = 'bloque';
        $detailPdoMysql = 'Le pilote MySQL de PDO n’est pas chargé dans le serveur web.';
        if ($pdoMysqlDisponible) {
            $etatPdoMysql = 'ok';
            $detailPdoMysql = 'Le pilote MySQL de PDO est chargé dans le serveur web.';
        }
        $etatBcrypt = 'bloque';
        $detailBcrypt = 'PASSWORD_BCRYPT n’est pas disponible dans ce runtime.';
        if ($bcryptDisponible) {
            $etatBcrypt = 'ok';
            $detailBcrypt = 'PASSWORD_BCRYPT est disponible.';
        }
        $etatRandomBytes = 'bloque';
        $detailRandomBytes = 'random_bytes() est indisponible.';
        if ($randomBytesDisponible) {
            $etatRandomBytes = 'ok';
            $detailRandomBytes = 'random_bytes() est disponible.';
        }
        $racine = MINISHOP_ROOT;
        $scriptDeploy = $racine . '/scripts/deploy.php';
        $configLegacy = $racine . '/app/Config/env.php';
        $configLegacyPresente = is_file($configLegacy);

        // Sous Windows, env.php peut résoudre vers le fichier de classe Env.php.
        if ($configLegacyPresente && PHP_OS_FAMILY === 'Windows') {
            $candidat = realpath($configLegacy);
            $classeEnv = realpath($racine . '/app/Config/Env.php');
            if ($candidat !== false && $classeEnv !== false && strcasecmp($candidat, $classeEnv) === 0) {
                $configLegacyPresente = false;
            }
        }

        $processusEnv = $racine . '/.env';
        $scriptLisible = is_file($scriptDeploy) && is_readable($scriptDeploy);
        $dotenvPresent = is_file($processusEnv);

        $runtime = [
            $controle(
                'PHP du serveur web',
                $etatPhp,
                'PHP ' . PHP_VERSION . ' · SAPI ' . PHP_SAPI . '. La version du PHP CLI n’est pas vérifiée par cette page.',
            ),
            $controle(
                'PDO',
                $etatPdo,
                $detailPdo,
            ),
            $controle(
                'PDO MySQL (pdo_mysql)',
                $etatPdoMysql,
                $detailPdoMysql,
            ),
            $controle(
                'Hachage bcrypt',
                $etatBcrypt,
                $detailBcrypt,
            ),
            $controle(
                'Générateur aléatoire cryptographique',
                $etatRandomBytes,
                $detailRandomBytes,
            ),
        ];

        foreach (['json', 'mbstring', 'intl'] as $extension) {
            $chargee = extension_loaded($extension);
            if ($chargee) {
                $etatExtension = 'ok';
                $detailExtension = 'Extension chargée dans le serveur web.';
            } else {
                $etatExtension = 'bloque';
                $detailExtension = 'Extension absente du serveur web.';
            }
            $runtime[] = $controle(
                'Extension PHP ' . $extension,
                $etatExtension,
                $detailExtension,
            );
        }

        if ($scriptLisible) {
            $etatScript = 'ok';
            $detailScript = 'Le fichier est présent et lisible par le serveur web; cela ne prouve pas son exécution par PHP CLI.';
        } else {
            $etatScript = 'bloque';
            $detailScript = 'Le fichier est absent ou illisible depuis le serveur web.';
        }
        $runtime[] = $controle('scripts/deploy.php', $etatScript, $detailScript);

        if ($configLegacyPresente) {
            $etatConfigLegacy = 'attention';
            $detailConfigLegacy = 'Fichier présent : si son inclusion retourne un tableau, scripts/deploy.php peut en prendre les clés db_host, db_port, db_name, db_user et db_pass avant les variables du processus. Vérifier la cible staging hors de cette page; ce contrôle n’inspecte ni n’affiche ses valeurs. Le chargement normal de MiniShop peut lire ce fichier pour l’authentification.';
        } else {
            $etatConfigLegacy = 'ok';
            $detailConfigLegacy = 'Fichier absent : aucun remplacement par ce fichier PHP. La configuration du processus CLI reste à vérifier.';
        }
        if ($dotenvPresent) {
            $etatDotenv = 'attention';
            $detailDotenv = 'Fichier présent, mais scripts/deploy.php ne le lit pas. Ne pas supposer qu’il configure la commande CLI.';
        } else {
            $etatDotenv = 'info';
            $detailDotenv = 'Aucun fichier .env détecté à la racine applicative; des variables CLI peuvent néanmoins être définies séparément.';
        }
        $configuration = [
            $controle('Priorité de app/Config/env.php', $etatConfigLegacy, $detailConfigLegacy),
            $controle('Fichier .env', $etatDotenv, $detailDotenv),
        ];

        $hostPresent = self::variableRenseignee('DB_HOST');
        $portBrut = getenv('DB_PORT');
        $portPresent = is_string($portBrut) && trim($portBrut) !== '';
        $portValide = $portPresent
            && filter_var(trim((string) $portBrut), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) !== false;
        $baseSources = self::variablesRenseignees(['DB', 'DB_NAME']);
        $utilisateurPresent = self::variableRenseignee('DB_USER');
        $motDePassePresent = self::variableRenseignee('DB_PASS');
        $aliasMotDePassePresent = self::variableRenseignee('DB_PASSWORD');

        $configuration[] = $controle(
            'DB_HOST',
            self::etatVariable($hostPresent, $configLegacyPresente),
            self::detailVariable(
                $hostPresent,
                $configLegacyPresente,
                'Présent dans le processus web; vérifier séparément que l’hôte du CLI est celui du staging.',
                'Si elle est également absente du PHP CLI et de app/Config/env.php, scripts/deploy.php utilise 127.0.0.1 par défaut.',
            ),
        );
        if ($portValide) {
            $detailPortPresent = 'DB_PORT paraît valide dans le processus web; sa valeur est masquée et le PHP CLI peut différer.';
        } else {
            $detailPortPresent = 'DB_PORT est présent, mais son format n’est pas valide dans le processus web; le PHP CLI peut différer.';
        }
        $configuration[] = $controle(
            'DB_PORT',
            self::etatVariable($portValide, $configLegacyPresente),
            self::detailVariable(
                $portPresent,
                $configLegacyPresente,
                $detailPortPresent,
                'Si elle est également absente du PHP CLI et de app/Config/env.php, scripts/deploy.php utilise le port 3306 par défaut.',
            ),
        );
        if (count($baseSources) > 1) {
            $detailBasePresente = 'DB et DB_NAME sont tous deux présents dans le processus web; scripts/deploy.php donne priorité à DB. Les valeurs sont masquées.';
        } else {
            $detailBasePresente = 'DB ou DB_NAME est présent dans le processus web; la valeur est masquée et la cible staging reste à confirmer.';
        }
        $configuration[] = $controle(
            'DB ou DB_NAME',
            self::etatVariable($baseSources !== [], $configLegacyPresente),
            self::detailVariable(
                $baseSources !== [],
                $configLegacyPresente,
                $detailBasePresente,
                'Si les deux sources sont également absentes du PHP CLI et de app/Config/env.php, scripts/deploy.php utilise minishop par défaut.',
            ),
        );
        $configuration[] = $controle(
            'DB_USER',
            self::etatVariable($utilisateurPresent, $configLegacyPresente),
            self::detailVariable(
                $utilisateurPresent,
                $configLegacyPresente,
                'Présent dans le processus web; vérifier séparément le compte dédié du PHP CLI et du staging.',
                'Si elle est également absente du PHP CLI et de app/Config/env.php, scripts/deploy.php utilise root par défaut.',
            ),
        );

        if ($aliasMotDePassePresent) {
            $detailMotDePassePresent = 'DB_PASS est présent; DB_PASSWORD ne le remplace pas. Les valeurs sont masquées et le PHP CLI peut différer.';
            $detailMotDePasseAbsent = 'DB_PASSWORD seul est présent, mais scripts/deploy.php attend DB_PASS (sauf configuration PHP prioritaire). La valeur n’est jamais affichée.';
        } else {
            $detailMotDePassePresent = 'DB_PASS est présent dans le processus web; valeur masquée, à vérifier séparément dans le PHP CLI.';
            $detailMotDePasseAbsent = 'Si elle est également absente du PHP CLI et de app/Config/env.php, scripts/deploy.php utilise un mot de passe vide.';
        }
        $configuration[] = $controle(
            'DB_PASS',
            self::etatVariable($motDePassePresent, $configLegacyPresente),
            self::detailVariable(
                $motDePassePresent,
                $configLegacyPresente,
                $detailMotDePassePresent,
                $detailMotDePasseAbsent,
            ),
        );

        $socketPresent = self::variableRenseignee('DB_SOCKET');
        if ($socketPresent) {
            $etatSocket = 'attention';
            $detailSocket = 'DB_SOCKET est présente dans le processus web, mais scripts/deploy.php ne la lit pas; il construit une connexion TCP à partir de DB_HOST et DB_PORT. Valeur masquée; PHP CLI non vérifié.';
        } else {
            $etatSocket = 'info';
            $detailSocket = 'scripts/deploy.php n’utilise pas DB_SOCKET; la cible CLI dépend de DB_HOST et DB_PORT.';
        }
        $configuration[] = $controle('DB_SOCKET', $etatSocket, $detailSocket);

        $configuration[] = $controle(
            'Écart entre processus web et CLI',
            'manuel',
            'Les présences ci-dessus décrivent uniquement ce processus web; elles ne prouvent ni les variables du PHP CLI ni ses valeurs. Aucun secret ni valeur de configuration n’est rendu.',
        );

        $staging = [
            $controle(
                'PHP CLI sur le serveur de staging',
                'manuel',
                'À vérifier séparément sur l’hôte de staging : version PHP CLI et pilote pdo_mysql. Cette page ne lance aucun processus et ne teste pas le script.',
            ),
            $controle(
                'Identité de la base cible',
                'manuel',
                'À confirmer par une source d’exploitation autorisée avant toute commande. Aucun hôte, nom de base ou identifiant n’est affiché et aucune connexion SQL de diagnostic n’est tentée.',
            ),
            $controle(
                'Tables et comptes de démonstration',
                'manuel',
                'Non vérifiés ici. Suivre les contrôles de docs/22-procedure-validation-staging.md sur une base de staging isolée; aucune donnée personnelle ou empreinte n’est affichée.',
            ),
            $controle(
                'Exécution de scripts/deploy.php',
                'info',
                'Jamais lancée par cette page. Elle n’effectue aucune écriture en base et ne modifie ni fichier SQL, ni configuration. La sortie du script contient des mots de passe en clair : la procédure exige un journal privé.',
            ),
        ];

        return [
            ['titre' => 'Runtime web', 'controles' => $runtime],
            ['titre' => 'Configuration attendue par scripts/deploy.php', 'controles' => $configuration],
            ['titre' => 'Contrôles staging hors de cette page', 'controles' => $staging],
        ];
    }

    /** Indique uniquement la présence, sans retourner la valeur. */
    private static function variableRenseignee(string $nom): bool
    {
        $valeur = getenv($nom);

        return is_string($valeur) && trim($valeur) !== '';
    }

    /** @param list<string> $noms @return list<string> */
    private static function variablesRenseignees(array $noms): array
    {
        $presente = [];
        foreach ($noms as $nom) {
            if (self::variableRenseignee($nom)) {
                $presente[] = $nom;
            }
        }

        return $presente;
    }

    private static function etatVariable(bool $presente, bool $configLegacyPresente): string
    {
        if ($configLegacyPresente || $presente) {
            return 'manuel';
        }

        return 'attention';
    }

    private static function detailVariable(
        bool $presente,
        bool $configLegacyPresente,
        string $detailPresente,
        string $detailAbsente,
    ): string {
        if ($presente) {
            $observation = 'Variable renseignée dans le processus web (valeur masquée).';
            $detail = $detailPresente;
        } else {
            $observation = 'Variable absente du processus web.';
            $detail = $detailAbsente;
        }
        $limite = '';
        if ($configLegacyPresente) {
            $limite = ' app/Config/env.php peut primer sur cette variable.';
        }

        return $observation . $limite . ' ' . $detail . ' Le processus CLI peut différer.';
    }
}
