<?php
/**
 * Funzioni di utilità generiche: configurazione, escape, URL, CSRF, messaggi.
 */

/** Legge un valore di configurazione (o tutto l'array se $chiave è null). */
function config(?string $chiave = null, $default = null)
{
    $cfg = $GLOBALS['APP_CONFIG'] ?? [];
    if ($chiave === null) {
        return $cfg;
    }
    return array_key_exists($chiave, $cfg) ? $cfg[$chiave] : $default;
}

/** Escape per l'output HTML. Da usare su OGNI valore stampato. */
function e($valore): string
{
    return htmlspecialchars((string) ($valore ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Percorso base dell'applicazione (la cartella "public" vista dal browser),
 * sempre con "/" finale. Funziona anche se l'app è in una sottocartella.
 */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $file   = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '';
    $pub    = realpath(PUBLIC_DIR) ?: '';

    // Quanti livelli sotto /public si trova lo script corrente (es. admin/ = 1)
    $livelli = 0;
    if ($pub !== '' && strncmp($file, $pub, strlen($pub)) === 0) {
        $rel = ltrim(str_replace('\\', '/', substr($file, strlen($pub))), '/');
        $livelli = substr_count($rel, '/');
    }
    $dir = dirname($script);
    for ($i = 0; $i < $livelli; $i++) {
        $dir = dirname($dir);
    }
    $dir = str_replace('\\', '/', $dir);
    return $base = rtrim($dir, '/') . '/';
}

/** Costruisce un URL interno, es. url('scheda.php', ['id' => 3]). */
function url(string $percorso = '', array $query = []): string
{
    $u = base_url() . ltrim($percorso, '/');
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

function redirect(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

/** Interrompe la richiesta con una pagina di errore generica. */
function abort(int $codice, string $messaggio): void
{
    http_response_code($codice);
    $titoli = [400 => 'Richiesta non valida', 403 => 'Accesso negato', 404 => 'Pagina non trovata'];
    $titolo_pagina = $titoli[$codice] ?? 'Errore';
    require __DIR__ . '/header.php';
    echo '<section class="card errore-pagina"><h1>' . e($codice . ' – ' . $titolo_pagina) . '</h1><p>'
        . e($messaggio) . '</p></section>';
    require __DIR__ . '/footer.php';
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Legge una stringa da un array di input (ignora valori non scalari). */
function input_str(array $sorgente, string $chiave, int $max = 10000): string
{
    $v = $sorgente[$chiave] ?? '';
    if (!is_string($v)) {
        return '';
    }
    // Sequenze UTF-8 non valide sostituite con "?" (il database le rifiuterebbe)
    $v = trim(str_replace("\0", '', mb_scrub($v, 'UTF-8')));
    return mb_substr($v, 0, $max);
}

// --- CSRF ----------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Verifica il token CSRF della richiesta POST; in caso di errore blocca. */
function csrf_verify(): void
{
    $inviato = $_POST['csrf'] ?? '';
    if (!is_string($inviato) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $inviato)) {
        abort(403, 'Richiesta non valida o scaduta. Tornare indietro, ricaricare la pagina e riprovare.');
    }
}

// --- Messaggi "flash" (mostrati una sola volta dopo un redirect) ---------

function flash(string $tipo, string $messaggio): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'msg' => $messaggio];
}

function flash_prendi(): array
{
    $m = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($m) ? $m : [];
}

// --- Formattazione ---------------------------------------------------------

/** '08:00:00' -> '08:00' */
function ora_breve($ora): string
{
    return substr((string) $ora, 0, 5);
}

/** '1984-03-12' -> '12/03/1984' */
function data_it($data): string
{
    if (!$data) {
        return '';
    }
    $d = DateTime::createFromFormat('!Y-m-d', substr((string) $data, 0, 10));
    return $d ? $d->format('d/m/Y') : '';
}
