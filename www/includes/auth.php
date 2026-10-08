<?php
/**
 * Autenticazione e controllo accessi, centralizzati in questo file.
 *
 *  - CONSULTAZIONE: sola lettura, senza password, solo da IP della rete LAN.
 *  - ADMIN: username + password (anche dentro la LAN).
 *
 * Funzioni principali:
 *   require_view_access()  -> admin loggato OPPURE IP in LAN, altrimenti 403
 *   require_admin()        -> admin loggato, altrimenti login / 403
 */

// =====================================================================
//  Indirizzi IP
// =====================================================================

/**
 * Converte un IP testuale in forma binaria (4 byte per IPv4, 16 per IPv6).
 * Gli IPv4 "mappati" in IPv6 (::ffff:192.168.10.5) sono ridotti a IPv4.
 * Restituisce null se l'indirizzo non è valido.
 */
function ip_binario(string $ip): ?string
{
    $ip = trim($ip);
    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return null;
    }
    $bin = inet_pton($ip);
    if ($bin === false) {
        return null;
    }
    if (strlen($bin) === 16 && strncmp($bin, str_repeat("\0", 10) . "\xff\xff", 12) === 0) {
        $bin = substr($bin, 12);
    }
    return $bin;
}

/**
 * Verifica se $ip appartiene alla rete $cidr (es. '192.168.10.0/24').
 * Un IP senza "/bit" è trattato come host singolo. Supporta IPv4 e IPv6.
 */
function ip_in_cidr(string $ip, string $cidr): bool
{
    $ipBin = ip_binario($ip);
    if ($ipBin === null) {
        return false;
    }
    $parti = explode('/', trim($cidr), 2);
    $reteBin = ip_binario($parti[0]);
    if ($reteBin === null || strlen($reteBin) !== strlen($ipBin)) {
        return false; // rete non valida o famiglia diversa (IPv4 contro IPv6)
    }
    $bitMax = strlen($reteBin) * 8;
    if (!isset($parti[1])) {
        $bit = $bitMax;
    } elseif (ctype_digit($parti[1]) && (int) $parti[1] <= $bitMax) {
        $bit = (int) $parti[1];
    } else {
        return false;
    }

    $byteInteri = intdiv($bit, 8);
    $bitResto   = $bit % 8;
    if ($byteInteri > 0 && substr($ipBin, 0, $byteInteri) !== substr($reteBin, 0, $byteInteri)) {
        return false;
    }
    if ($bitResto === 0) {
        return true;
    }
    $maschera = (0xFF << (8 - $bitResto)) & 0xFF;
    return (ord($ipBin[$byteInteri]) & $maschera) === (ord($reteBin[$byteInteri]) & $maschera);
}

/** true se $ip appartiene ad almeno una delle reti/IP dell'elenco. */
function ip_in_elenco(string $ip, array $elenco): bool
{
    foreach ($elenco as $cidr) {
        if (is_string($cidr) && ip_in_cidr($ip, $cidr)) {
            return true;
        }
    }
    return false;
}

/** true se la connessione arriva direttamente da un reverse proxy fidato. */
function da_proxy_fidato(): bool
{
    $proxy = (array) config('trusted_proxies', []);
    return $proxy && ip_in_elenco((string) ($_SERVER['REMOTE_ADDR'] ?? ''), $proxy);
}

/**
 * IP del client.
 *
 * Di norma è REMOTE_ADDR. L'header X-Forwarded-For viene letto SOLO se
 * REMOTE_ADDR è uno dei 'trusted_proxies' di config.php: in quel caso si
 * scorre la catena da destra e si prende il primo IP che non è un proxy
 * fidato. Se l'header manca o è malformato si restituisce un IP "nullo"
 * (0.0.0.0), così un proxy che sta in LAN non regala l'accesso a nessuno.
 */
function client_ip(): string
{
    $remoto = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (!da_proxy_fidato()) {
        return $remoto;
    }
    $header = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if (trim($header) === '') {
        return '0.0.0.0';
    }
    $proxy  = (array) config('trusted_proxies', []);
    $catena = array_reverse(array_map('trim', explode(',', $header)));
    foreach ($catena as $ip) {
        if (ip_binario($ip) === null) {
            return '0.0.0.0';
        }
        if (!ip_in_elenco($ip, $proxy)) {
            return $ip;
        }
    }
    // Tutta la catena è composta da proxy fidati: la richiesta nasce da uno di loro.
    return end($catena);
}

/** true se il client è nella rete LAN autorizzata alla consultazione. */
function is_lan(): bool
{
    return ip_in_elenco(client_ip(), (array) config('rete_lan', '192.168.10.0/24'));
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return da_proxy_fidato()
        && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

// =====================================================================
//  Sessione
// =====================================================================

function avvia_sessione(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $timeout = max(60, (int) config('session_timeout', 1800));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string) max(1440, $timeout));
    session_name('INTERPRETI_SESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_url(),
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Timeout di inattività della sessione admin
    if (!empty($_SESSION['admin_id'])) {
        if (time() - (int) ($_SESSION['ultima_attivita'] ?? 0) > $timeout) {
            logout_admin();
            flash('info', 'Sessione scaduta per inattività: effettuare di nuovo l\'accesso.');
        } else {
            $_SESSION['ultima_attivita'] = time();
        }
    }
}

// =====================================================================
//  Ruoli
// =====================================================================

/** Il login admin è consentito da questo client? (LAN, o ovunque se configurato) */
function login_admin_consentito(): bool
{
    return (bool) config('admin_login_fuori_lan', true) || is_lan();
}

function is_admin(): bool
{
    return !empty($_SESSION['admin_id']) && login_admin_consentito();
}

function admin_username(): string
{
    return (string) ($_SESSION['admin_username'] ?? '');
}

/** Accesso in lettura: admin loggato oppure client in LAN. */
function has_view_access(): bool
{
    return is_admin() || is_lan();
}

/** Da chiamare in testa alle pagine di consultazione. */
function require_view_access(): void
{
    if (!has_view_access()) {
        error_log('Interpreti: accesso negato in consultazione da IP ' . client_ip());
        abort(403, 'La consultazione è consentita solo dalla rete interna.');
    }
}

/** Da chiamare in testa a OGNI pagina o azione di scrittura. */
function require_admin(): void
{
    if (is_admin()) {
        return;
    }
    if (!login_admin_consentito()) {
        abort(403, 'Accesso non consentito da questa rete.');
    }
    if (is_post()) {
        abort(403, 'Operazione riservata all\'amministratore.');
    }
    redirect(url('login.php'));
}

// =====================================================================
//  Login / logout
// =====================================================================

/** Algoritmo di hash: Argon2id se disponibile, altrimenti bcrypt. */
function algoritmo_password()
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
}

function hash_password(string $password): string
{
    return password_hash($password, algoritmo_password());
}

/** Regole minime per le password admin. Restituisce un messaggio o null. */
function errore_password(string $password): ?string
{
    if (strlen($password) < 10) {
        return 'La password deve avere almeno 10 caratteri.';
    }
    if (strlen($password) > 200) {
        return 'La password è troppo lunga (massimo 200 caratteri).';
    }
    return null;
}

/** Minuti di blocco residui per IP/username, 0 se il login è permesso. */
function login_bloccato(string $ip, string $username): int
{
    $max    = max(1, (int) config('login_max_tentativi', 5));
    $minuti = max(1, (int) config('login_blocco_minuti', 15));
    $soglia = date('Y-m-d H:i:s', time() - $minuti * 60);

    // Blocco per IP dopo $max errori; per username (da qualunque IP) dopo
    // $max * 3, per frenare gli attacchi distribuiti senza permettere a un
    // singolo estraneo di bloccare l'admin con pochi tentativi.
    $st = db()->prepare(
        'SELECT
            (SELECT COUNT(*) FROM login_tentativi WHERE ip = ? AND creato_il > ?) AS per_ip,
            (SELECT COUNT(*) FROM login_tentativi WHERE username = ? AND creato_il > ?) AS per_utente,
            (SELECT MIN(creato_il) FROM login_tentativi WHERE (ip = ? OR username = ?) AND creato_il > ?) AS primo'
    );
    $st->execute([$ip, $soglia, $username, $soglia, $ip, $username, $soglia]);
    $r = $st->fetch();
    if ((int) $r['per_ip'] >= $max || (int) $r['per_utente'] >= $max * 3) {
        $fine = strtotime((string) $r['primo']) + $minuti * 60;
        return max(1, (int) ceil(($fine - time()) / 60));
    }
    return 0;
}

/**
 * Tenta il login admin. Restituisce null se riuscito, altrimenti il
 * messaggio di errore (volutamente generico) da mostrare.
 */
function tenta_login(string $username, string $password): ?string
{
    $ip = client_ip();
    $username = mb_substr($username, 0, 50);
    $chiave = mb_strtolower($username);

    $blocco = login_bloccato($ip, $chiave);
    if ($blocco > 0) {
        error_log("Interpreti: login bloccato per troppi tentativi (IP $ip)");
        return "Troppi tentativi falliti. Riprovare tra circa $blocco minuti.";
    }

    $st = db()->prepare('SELECT id, username, password_hash FROM utenti WHERE username = ?');
    $st->execute([$username]);
    $utente = $st->fetch();

    // Se l'utente non esiste si verifica comunque un hash fittizio, per non
    // rivelare con i tempi di risposta quali username sono validi.
    static $hashFittizio = null;
    if (!$utente && $hashFittizio === null) {
        $hashFittizio = hash_password(bin2hex(random_bytes(8)));
    }
    $ok = password_verify($password, $utente ? $utente['password_hash'] : $hashFittizio) && $utente;

    if (!$ok) {
        db()->prepare('INSERT INTO login_tentativi (ip, username, creato_il) VALUES (?, ?, ?)')
            ->execute([$ip, $chiave, date('Y-m-d H:i:s')]);
        // Pulizia dei tentativi vecchi
        db()->prepare('DELETE FROM login_tentativi WHERE creato_il < ?')
            ->execute([date('Y-m-d H:i:s', time() - 86400)]);
        error_log("Interpreti: login fallito da IP $ip");
        return 'Credenziali non valide.';
    }

    if (password_needs_rehash($utente['password_hash'], algoritmo_password())) {
        db()->prepare('UPDATE utenti SET password_hash = ? WHERE id = ?')
            ->execute([hash_password($password), $utente['id']]);
    }
    db()->prepare('UPDATE utenti SET ultimo_accesso = ? WHERE id = ?')
        ->execute([date('Y-m-d H:i:s'), $utente['id']]);
    db()->prepare('DELETE FROM login_tentativi WHERE ip = ? AND username = ?')
        ->execute([$ip, $chiave]);

    // Nuovo ID di sessione al cambio di privilegi (contro la session fixation)
    session_regenerate_id(true);
    $_SESSION['admin_id']        = (int) $utente['id'];
    $_SESSION['admin_username']  = $utente['username'];
    $_SESSION['ultima_attivita'] = time();
    $_SESSION['csrf']            = bin2hex(random_bytes(32));
    return null;
}

function logout_admin(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['ultima_attivita'], $_SESSION['csrf']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}
