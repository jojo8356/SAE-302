<?php

declare(strict_types=1);

/**
 * MiniShop — support partagé des suites HTTP de bout en bout.
 *
 * Client HTTP minimal en `fsockopen` (aucune extension requise, patron repris
 * de `tests/php/dev_router_test.php`), cycle de vie du serveur intégré PHP
 * (`php -S` en sous-processus) et compteur de vérifications.
 *
 * Ce fichier n'est jamais chargé par l'application : il sert uniquement aux
 * suites `http_sql_e2e.php` et `concurrence_http.php`, qui prouvent le chemin
 * applicatif complet sur le pilote SQL (ce que les scénarios `T-*` prouvent
 * au niveau des procédures et l'E2E WASM au niveau JSON).
 */

namespace MiniShop\Tests;

/** Réponse HTTP brute reçue du serveur de test. */
final class HttpReponse
{
    /** @param array<string, list<string>> $entetes noms d'en-têtes en minuscules */
    public function __construct(
        public readonly int $statut,
        public readonly array $entetes,
        public readonly string $corps,
    ) {
    }

    /** Valeur de l'en-tête `Location` (redirection), `null` si absente. */
    public function location(): ?string
    {
        $valeurs = $this->entetes['location'] ?? [];
        if ($valeurs === []) {
            return null;
        }

        return $valeurs[0];
    }

    /** Vrai si le corps contient la chaîne cherchée. */
    public function contient(string $aiguille): bool
    {
        return str_contains($this->corps, $aiguille);
    }

    /** Toutes les valeurs d'un en-tête (ex. `set-cookie`). @return list<string> */
    public function valeurs(string $nom): array
    {
        return $this->entetes[strtolower($nom)] ?? [];
    }
}

/**
 * Client HTTP avec bocal de cookies.
 *
 * Les redirections ne sont JAMAIS suivies automatiquement : chaque 302 est
 * une assertion explicite de la suite (cible attendue, pas de chaîne
 * d'URLs devinée).
 */
final class HttpClient
{
    /** @var array<string, string> bocal de cookies (nom => valeur) */
    private array $cookies = [];

    public function __construct(private readonly int $port)
    {
    }

    public function get(string $chemin): HttpReponse
    {
        return $this->requete('GET', $chemin, null);
    }

    /** @param array<string, string> $champs champs de formulaire (POST) */
    public function post(string $chemin, array $champs): HttpReponse
    {
        return $this->requete('POST', $chemin, http_build_query($champs));
    }

    /** Extrait le jeton CSRF d'une page HTML (champ caché `_csrf`). */
    public function csrf(string $corps): ?string
    {
        if (preg_match('#name="_csrf" value="([a-f0-9]+)"#', $corps, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * GET le formulaire puis POST avec le jeton CSRF frais de cette page.
     *
     * @param array<string, string> $champs champs de formulaire (hors `_csrf`)
     */
    public function postFormulaire(string $cheminFormulaire, string $cheminPost, array $champs): HttpReponse
    {
        $page = $this->get($cheminFormulaire);
        $jeton = $this->csrf($page->corps);
        if ($jeton !== null) {
            $champs['_csrf'] = $jeton;
        }

        return $this->post($cheminPost, $champs);
    }

    /** Cookie de session courant (`PHPSESSID`), `null` si absent. */
    public function cookieSession(): ?string
    {
        return $this->cookies['PHPSESSID'] ?? null;
    }

    /** Émet une requête HTTP/1.1 brute et analyse la réponse. */
    private function requete(string $methode, string $chemin, ?string $corpsForm): HttpReponse
    {
        $socket = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 5.0);
        if ($socket === false) {
            return new HttpReponse(0, [], '');
        }
        $lignes = [
            $methode . ' ' . $chemin . ' HTTP/1.1',
            'Host: 127.0.0.1:' . $this->port,
            'Connection: close',
            'User-Agent: MiniShop-Tests-HTTP',
        ];
        if ($this->cookies !== []) {
            $paires = [];
            foreach ($this->cookies as $nom => $valeur) {
                $paires[] = $nom . '=' . $valeur;
            }
            $lignes[] = 'Cookie: ' . implode('; ', $paires);
        }
        if ($corpsForm !== null) {
            $lignes[] = 'Content-Type: application/x-www-form-urlencoded';
            $lignes[] = 'Content-Length: ' . strlen($corpsForm);
        }
        $requete = implode("\r\n", $lignes) . "\r\n\r\n";
        if ($corpsForm !== null) {
            $requete .= $corpsForm;
        }
        fwrite($socket, $requete);
        stream_set_timeout($socket, 10);
        $brut = '';
        while (!feof($socket)) {
            $morceau = fread($socket, 8192);
            if ($morceau === false) {
                break;
            }
            if ($morceau === '') {
                $infos = stream_get_meta_data($socket);
                if ($infos['timed_out']) {
                    break;
                }
                continue;
            }
            $brut .= $morceau;
        }
        fclose($socket);

        return $this->analyser($brut);
    }

    /** Découpe statut / en-têtes / corps, mémorise les cookies, décode le chunked. */
    private function analyser(string $brut): HttpReponse
    {
        $statut = 0;
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $brut, $m) === 1) {
            $statut = (int) $m[1];
        }
        $entetes = [];
        $corps = $brut;
        $position = strpos($brut, "\r\n\r\n");
        if ($position !== false) {
            $blocEntetes = substr($brut, 0, $position);
            $corps = substr($brut, $position + 4);
            $lignes = explode("\r\n", $blocEntetes);
            array_shift($lignes);
            foreach ($lignes as $ligne) {
                $deuxPoints = strpos($ligne, ':');
                if ($deuxPoints === false) {
                    continue;
                }
                $nom = strtolower(trim(substr($ligne, 0, $deuxPoints)));
                $valeur = trim(substr($ligne, $deuxPoints + 1));
                $entetes[$nom][] = $valeur;
            }
        }
        foreach ($entetes['set-cookie'] ?? [] as $cookie) {
            $this->memoriserCookie($cookie);
        }
        $estChunked = false;
        foreach ($entetes['transfer-encoding'] ?? [] as $valeur) {
            if (str_contains(strtolower($valeur), 'chunked')) {
                $estChunked = true;
            }
        }
        if ($estChunked) {
            $decode = $this->decoderChunked($corps);
            if ($decode !== '') {
                $corps = $decode;
            }
        }

        return new HttpReponse($statut, $entetes, $corps);
    }

    /** Mémorise (ou supprime si valeur vide) un cookie `Set-Cookie`. */
    private function memoriserCookie(string $enTete): void
    {
        $pointVirgule = strpos($enTete, ';');
        $paire = $enTete;
        if ($pointVirgule !== false) {
            $paire = substr($enTete, 0, $pointVirgule);
        }
        $egal = strpos($paire, '=');
        if ($egal === false) {
            return;
        }
        $nom = trim(substr($paire, 0, $egal));
        $valeur = trim(substr($paire, $egal + 1));
        if ($nom === '') {
            return;
        }
        if ($valeur === '') {
            unset($this->cookies[$nom]);

            return;
        }
        $this->cookies[$nom] = $valeur;
    }

    /** Décode un corps `Transfer-Encoding: chunked` (défensif, jamais d'exception). */
    private function decoderChunked(string $corps): string
    {
        $decode = '';
        $reste = $corps;
        while ($reste !== '') {
            $finLigne = strpos($reste, "\r\n");
            if ($finLigne === false) {
                break;
            }
            $taille = (int) hexdec(trim(substr($reste, 0, $finLigne)));
            if ($taille <= 0) {
                break;
            }
            $decode .= substr($reste, $finLigne + 2, $taille);
            $reste = substr($reste, $finLigne + 2 + $taille + 2);
        }

        return $decode;
    }
}

/**
 * Cycle de vie du serveur intégré PHP de test (`php -S` + routeur de dev).
 *
 * L'environnement du sous-processus est explicite : le pilote SQL et la base
 * de test sont imposés par variables réelles (prioritaires sur `.env`), pour
 * ne jamais dépendre du fichier de configuration local de la machine.
 */
final class ServeurTest
{
    private mixed $processus = null;

    /** @var array<int, mixed> tuyaux du sous-processus */
    private array $tuyaux = [];

    /** @param array<string, string> $environnement variables d'environnement du serveur */
    public function __construct(
        private readonly int $port,
        private readonly string $racine,
        private readonly array $environnement,
    ) {
    }

    /** Lance `php -S` et attend sa première réponse (15 s maximum). */
    public function demarrer(): bool
    {
        $descripteurs = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $tuyaux = [];
        $commande = [
            PHP_BINARY,
            '-S',
            '127.0.0.1:' . $this->port,
            '-t',
            $this->racine . '/public',
            $this->racine . '/scripts/dev-router.php',
        ];
        $processus = proc_open($commande, $descripteurs, $tuyaux, $this->racine, $this->environnement);
        if (!is_resource($processus)) {
            fwrite(STDERR, "proc_open a échoué pour le serveur de test (port {$this->port}).\n");

            return false;
        }
        $this->processus = $processus;
        $this->tuyaux = $tuyaux;
        foreach ($this->tuyaux as $tuyau) {
            stream_set_blocking($tuyau, false);
        }
        $echeance = microtime(true) + 15.0;
        while (microtime(true) < $echeance) {
            $statut = proc_get_status($this->processus);
            if (!$statut['running']) {
                fwrite(STDERR, "Le serveur de test s'est arrêté au démarrage (port {$this->port}).\n");

                return false;
            }
            $sonde = new HttpClient($this->port);
            $reponse = $sonde->get('/');
            if ($reponse->statut !== 0) {
                return true;
            }
            usleep(100000);
        }
        fwrite(STDERR, "Le serveur de test n'a pas répondu dans les 15 s (port {$this->port}).\n");

        return false;
    }

    /** Arrête le serveur (fermeture des tuyaux puis terminaison). */
    public function arreter(): void
    {
        foreach ($this->tuyaux as $tuyau) {
            if (is_resource($tuyau)) {
                fclose($tuyau);
            }
        }
        if (is_resource($this->processus)) {
            proc_terminate($this->processus);
            proc_close($this->processus);
        }
        $this->processus = null;
        $this->tuyaux = [];
    }
}

/** Compteur de vérifications (patron repris des suites `tests/php/*_test.php`). */
final class Verificateur
{
    private int $total = 0;
    private int $echecs = 0;

    /** Enregistre un verdict booléen. */
    public function ok(bool $condition, string $etiquette): void
    {
        ++$this->total;
        if ($condition) {
            echo "  ✓ {$etiquette}\n";
        } else {
            ++$this->echecs;
            echo "  ✗ ÉCHEC — {$etiquette}\n";
        }
    }

    /** Vérifie une égalité stricte (avec diagnostic des deux valeurs). */
    public function egal(mixed $attendu, mixed $reel, string $etiquette): void
    {
        $this->ok(
            $attendu === $reel,
            $etiquette . ' (attendu ' . var_export($attendu, true) . ', obtenu ' . var_export($reel, true) . ')'
        );
    }

    /** Vérifie la présence d'une chaîne dans un corps HTML. */
    public function contient(string $corps, string $aiguille, string $etiquette): void
    {
        $this->ok(str_contains($corps, $aiguille), $etiquette . " (chaîne absente : « {$aiguille} »)");
    }

    /** Vérifie l'absence d'une chaîne dans un corps HTML. */
    public function neContientPas(string $corps, string $aiguille, string $etiquette): void
    {
        $this->ok(!str_contains($corps, $aiguille), $etiquette . " (chaîne présente à tort : « {$aiguille} »)");
    }

    public function total(): int
    {
        return $this->total;
    }

    public function echecs(): int
    {
        return $this->echecs;
    }

    /** Affiche le bilan et retourne le code de sortie (0 = vert, 1 = échec). */
    public function bilan(string $nom): int
    {
        echo "\n============================================================\n";
        if ($this->echecs > 0) {
            echo "{$nom} : {$this->echecs}/{$this->total} vérification(s) en échec ❌\n";

            return 1;
        }
        echo "{$nom} : {$this->total}/{$this->total} vérifications vertes ✅\n";

        return 0;
    }
}

/** Port TCP libre (demandé à l'OS puis relâché pour le serveur de test). */
function portLibre(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    if ($socket === false) {
        return 18000 + random_int(0, 2000);
    }
    $nom = stream_socket_get_name($socket, false);
    fclose($socket);
    if (!is_string($nom)) {
        return 18000 + random_int(0, 2000);
    }
    $port = (int) substr($nom, strrpos($nom, ':') + 1);
    if ($port > 0) {
        return $port;
    }

    return 18000 + random_int(0, 2000);
}

/**
 * Construit l'environnement complet d'un sous-processus : les variables
 * essentielles héritées du processus courant, surchargées par `$surcharge`.
 *
 * @param array<string, string> $surcharge
 * @return array<string, string>
 */
function environnement(array $surcharge): array
{
    $base = [];
    foreach (['PATH', 'HOME', 'TMPDIR', 'LANG', 'LC_ALL', 'SYSTEMROOT', 'COMSPEC', 'PATHEXT'] as $cle) {
        $valeur = getenv($cle);
        if (is_string($valeur) && $valeur !== '') {
            $base[$cle] = $valeur;
        }
    }
    foreach ($surcharge as $cle => $valeur) {
        $base[$cle] = $valeur;
    }

    return $base;
}
