<?php
/**
 * Cambio della password dell'amministratore collegato.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require_admin();

$errore = null;
if (is_post()) {
    csrf_verify();
    $leggi = fn (string $k): string => (isset($_POST[$k]) && is_string($_POST[$k])) ? $_POST[$k] : '';
    $attuale  = $leggi('password_attuale');
    $nuova    = $leggi('password_nuova');
    $conferma = $leggi('password_conferma');

    $st = db()->prepare('SELECT password_hash FROM utenti WHERE id = ?');
    $st->execute([(int) $_SESSION['admin_id']]);
    $hash = $st->fetchColumn();

    if (!$hash || !password_verify($attuale, $hash)) {
        $errore = 'La password attuale non è corretta.';
        error_log('Interpreti: cambio password fallito (password attuale errata) da IP ' . client_ip());
    } elseif ($nuova !== $conferma) {
        $errore = 'La nuova password e la conferma non coincidono.';
    } elseif (($errore = errore_password($nuova)) === null && $nuova === $attuale) {
        $errore = 'La nuova password deve essere diversa da quella attuale.';
    }

    if ($errore === null) {
        db()->prepare('UPDATE utenti SET password_hash = ? WHERE id = ?')
            ->execute([hash_password($nuova), (int) $_SESSION['admin_id']]);
        session_regenerate_id(true);
        flash('ok', 'Password aggiornata.');
        redirect(url('index.php'));
    }
}

$titolo_pagina = 'Cambio password';
require __DIR__ . '/../../includes/header.php';
?>
<section class="card card-stretta">
    <h1>Cambio password</h1>
    <p class="tenue">Utente: <strong><?= e(admin_username()) ?></strong></p>
    <?php if ($errore): ?>
        <div class="avviso avviso-errore" role="alert"><?= e($errore) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('admin/password.php')) ?>" autocomplete="off">
        <?= csrf_field() ?>
        <label class="campo"><span>Password attuale</span>
            <input type="password" name="password_attuale" maxlength="200" required autocomplete="current-password"></label>
        <label class="campo"><span>Nuova password (almeno 10 caratteri)</span>
            <input type="password" name="password_nuova" minlength="10" maxlength="200" required autocomplete="new-password"></label>
        <label class="campo"><span>Conferma nuova password</span>
            <input type="password" name="password_conferma" minlength="10" maxlength="200" required autocomplete="new-password"></label>
        <div class="azioni">
            <button type="submit" class="btn btn-primario">Aggiorna password</button>
        </div>
    </form>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
