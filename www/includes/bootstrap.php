<?php
/**
 * Inizializzazione comune a tutte le pagine:
 * configurazione, gestione errori, header di sicurezza, sessione.
 */

define('APP_ROOT', dirname(__DIR__));
define('PUBLIC_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'public');
define('IS_CLI', PHP_SAPI === 'cli');

// --- Configurazione ----------------------------------------------------
$fileConfig = getenv('INTERPRETI_CONFIG') ?: (__DIR__ . '/config.php');
if (!is_file($fileConfig)) {
    error_log('Interpreti: file di configurazione non trovato (' . $fileConfig . ')');
    if (!IS_CLI) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    exit("Applicazione non configurata: creare includes/config.php partendo da config.sample.php.\n");
}
$GLOBALS['APP_CONFIG'] = require $fileConfig;

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/interpreti.php';

// --- Errori: mai a video in produzione, sempre nel log -------------------
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', (config('debug', false) || IS_CLI) ? '1' : '0');
date_default_timezone_set((string) config('timezone', 'Europe/Rome'));
mb_internal_encoding('UTF-8');

if (!IS_CLI) {
    set_exception_handler(function (Throwable $ex): void {
        error_log('Interpreti: ' . get_class($ex) . ': ' . $ex->getMessage()
            . ' in ' . $ex->getFile() . ':' . $ex->getLine());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!DOCTYPE html><html lang="it"><meta charset="utf-8"><title>Errore</title>'
           . '<p>Si è verificato un errore imprevisto. Riprovare più tardi.</p>';
        if (config('debug', false)) {
            echo '<pre>' . e((string) $ex) . '</pre>';
        }
        exit;
    });

    // --- Header di sicurezza ---------------------------------------------
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    // Nessuno script o stile inline: tutto il JS/CSS sta in /assets.
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; "
         . "img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    // Le pagine contengono dati personali: niente cache condivise.
    header('Cache-Control: private, no-store');

    avvia_sessione();
}
