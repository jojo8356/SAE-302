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
        $sections = $actif ? $this->diagnostics() : [];
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
        $pdoDrivers = $pdoDisponible ? \PDO::getAvailableDrivers() : [];
        $bcryptDisponible = function_exists('password_hash')
            && function_exists('password_algos')
            && defined('PASSWORD_BCRYPT')
            && in_array(PASSWORD_BCRYPT, password_algos(), true);
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
                PHP_VERSION_ID >= 80200 ? 'ok' : 'bloque',
                'PHP ' . PHP_VERSION . ' · SAPI ' . PHP_SAPI . '. La version du PHP CLI n’est pas vérifiée par cette page.',
            ),
            $controle(
                'PDO',
                $pdoDisponible ? 'ok' : 'bloque',
                $pdoDisponible ? 'Extension PDO disponible dans le serveur web.' : 'Extension PDO absente du serveur web.',
            ),
            $controle(
                'PDO MySQL (pdo_mysql)',
                in_array('mysql', $pdoDrivers, true) ? 'ok' : 'bloque',
                in_array('mysql', $pdoDrivers, true)
                    ? 'Le pilote MySQL de PDO est chargé dans le serveur web.'
                    : 'Le pilote MySQL de PDO n’est pas chargé dans le serveur web.',
            ),
            $controle(
                'Hachage bcrypt',
                $bcryptDisponible ? 'ok' : 'bloque',
                $bcryptDisponible ? 'PASSWORD_BCRYPT est disponible.' : 'PASSWORD_BCRYPT n’est pas disponible dans ce runtime.',
            ),
            $controle(
                'Générateur aléatoire cryptographique',
                function_exists('random_bytes') ? 'ok' : 'bloque',
                function_exists('random_bytes') ? 'random_bytes() est disponible.' : 'random_bytes() est indisponible.',
            ),
        ];

        foreach (['json', 'mbstring', 'intl'] as $extension) {
            $chargee = extension_loaded($extension);
            $runtime[] = $controle(
                'Extension PHP ' . $extension,
                $chargee ? 'ok' : 'bloque',
                $chargee ? 'Extension chargée dans le serveur web.' : 'Extension absente du serveur web.',
            );
        }

        $runtime[] = $controle(
            'scripts/deploy.php',
            $scriptLisible ? 'ok' : 'bloque',
            $scriptLisible
                ? 'Le fichier est présent et lisible par le serveur web; cela ne prouve pas son exécution par PHP CLI.'
                : 'Le fichier est absent ou illisible depuis le serveur web.',
        );

        $configuration = [
            $controle(
                'Priorité de app/Config/env.php',
                $configLegacyPresente ? 'attention' : 'ok',
                $configLegacyPresente
                    ? 'Fichier présent : si son inclusion retourne un tableau, scripts/deploy.php peut en prendre les clés db_host, db_port, db_name, db_user et db_pass avant les variables du processus. Vérifier la cible staging hors de cette page; ce contrôle n’inspecte ni n’affiche ses valeurs. Le chargement normal de MiniShop peut lire ce fichier pour l’authentification.'
                    : 'Fichier absent : aucun remplacement par ce fichier PHP. La configuration du processus CLI reste à vérifier.',
            ),
            $controle(
                'Fichier .env',
                $dotenvPresent ? 'attention' : 'info',
                $dotenvPresent
                    ? 'Fichier présent, mais scripts/deploy.php ne le lit pas. Ne pas supposer qu’il configure la commande CLI.'
                    : 'Aucun fichier .env détecté à la racine applicative; des variables CLI peuvent néanmoins être définies séparément.',
            ),
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
        $detailPortPresent = $portValide
            ? 'DB_PORT paraît valide dans le processus web; sa valeur est masquée et le PHP CLI peut différer.'
            : 'DB_PORT est présent, mais son format n’est pas valide dans le processus web; le PHP CLI peut différer.';
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
        $detailBasePresente = count($baseSources) > 1
            ? 'DB et DB_NAME sont tous deux présents dans le processus web; scripts/deploy.php donne priorité à DB. Les valeurs sont masquées.'
            : 'DB ou DB_NAME est présent dans le processus web; la valeur est masquée et la cible staging reste à confirmer.';
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

        $detailMotDePassePresent = $aliasMotDePassePresent
            ? 'DB_PASS est présent; DB_PASSWORD ne le remplace pas. Les valeurs sont masquées et le PHP CLI peut différer.'
            : 'DB_PASS est présent dans le processus web; valeur masquée, à vérifier séparément dans le PHP CLI.';
        $detailMotDePasseAbsent = $aliasMotDePassePresent
            ? 'DB_PASSWORD seul est présent, mais scripts/deploy.php attend DB_PASS (sauf configuration PHP prioritaire). La valeur n’est jamais affichée.'
            : 'Si elle est également absente du PHP CLI et de app/Config/env.php, scripts/deploy.php utilise un mot de passe vide.';
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
        $configuration[] = $controle(
            'DB_SOCKET',
            $socketPresent ? 'attention' : 'info',
            $socketPresent
                ? 'DB_SOCKET est présente dans le processus web, mais scripts/deploy.php ne la lit pas; il construit une connexion TCP à partir de DB_HOST et DB_PORT. Valeur masquée; PHP CLI non vérifié.'
                : 'scripts/deploy.php n’utilise pas DB_SOCKET; la cible CLI dépend de DB_HOST et DB_PORT.',
        );

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
        $observation = $presente
            ? 'Variable renseignée dans le processus web (valeur masquée).'
            : 'Variable absente du processus web.';
        $detail = $presente ? $detailPresente : $detailAbsente;
        $limite = $configLegacyPresente
            ? ' app/Config/env.php peut primer sur cette variable.'
            : '';

        return $observation . $limite . ' ' . $detail . ' Le processus CLI peut différer.';
    }
}
