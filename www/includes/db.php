<?php
/**
 * Connessione al database (PDO, una sola istanza per richiesta).
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = (array) config('db', []);
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $c['host'] ?? 'localhost',
        (int) ($c['port'] ?? 3306),
        $c['name'] ?? '',
        $c['charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, (string) ($c['user'] ?? ''), (string) ($c['pass'] ?? ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES   => false, // vere query preparate lato server
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ]);
    return $pdo;
}
