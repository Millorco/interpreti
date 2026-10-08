<?php
/**
 * Crea un utente amministratore (o ne reimposta la password).
 *
 * Uso, SOLO da riga di comando:
 *     php create_admin.php <username>
 *
 * La password viene chiesta in modo interattivo: non è mai scritta nel codice
 * né passata come argomento (resterebbe nella cronologia della shell).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Accesso negato.');
}

require __DIR__ . '/includes/bootstrap.php';

$username = trim((string) ($argv[1] ?? ''));
if ($username === '' || !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
    fwrite(STDERR, "Uso: php create_admin.php <username>\n"
        . "Lo username deve avere 3-50 caratteri (lettere, cifre, punto, trattino, underscore).\n");
    exit(1);
}

/** Legge una riga dal terminale, nascondendo i caratteri dove possibile. */
function chiedi_password(string $messaggio): string
{
    // Calcolato una sola volta, prima di qualunque lettura da STDIN
    static $interattivo = null;
    if ($interattivo === null) {
        $interattivo = function_exists('stream_isatty') && stream_isatty(STDIN);
    }
    $nascosto = false;
    fwrite(STDOUT, $messaggio);
    if ($interattivo && DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec')) {
        shell_exec('stty -echo 2>/dev/null');
        $nascosto = true;
    } elseif ($interattivo) {
        fwrite(STDOUT, '(attenzione: i caratteri saranno visibili) ');
    }
    $riga = fgets(STDIN);
    if ($nascosto) {
        shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, "\n");
    }
    return $riga === false ? '' : rtrim($riga, "\r\n");
}

try {
    $st = db()->prepare('SELECT id FROM utenti WHERE username = ?');
    $st->execute([$username]);
    $idEsistente = $st->fetchColumn();

    if ($idEsistente !== false) {
        fwrite(STDOUT, "L'utente \"$username\" esiste già: la password verrà reimpostata.\n");
    }

    $password = chiedi_password('Password (almeno 10 caratteri): ');
    $errore = errore_password($password);
    if ($errore !== null) {
        fwrite(STDERR, $errore . "\n");
        exit(1);
    }
    if (chiedi_password('Ripetere la password: ') !== $password) {
        fwrite(STDERR, "Le due password non coincidono.\n");
        exit(1);
    }

    $hash = hash_password($password);
    if ($idEsistente !== false) {
        db()->prepare('UPDATE utenti SET password_hash = ? WHERE id = ?')->execute([$hash, $idEsistente]);
        db()->prepare('DELETE FROM login_tentativi WHERE username = ?')->execute([mb_strtolower($username)]);
        fwrite(STDOUT, "Password dell'utente \"$username\" aggiornata.\n");
    } else {
        db()->prepare('INSERT INTO utenti (username, password_hash) VALUES (?, ?)')->execute([$username, $hash]);
        fwrite(STDOUT, "Utente amministratore \"$username\" creato.\n");
    }
} catch (PDOException $ex) {
    fwrite(STDERR, "Errore di database: " . $ex->getMessage() . "\n"
        . "Controllare includes/config.php e che lo schema sia stato importato.\n");
    exit(1);
}
