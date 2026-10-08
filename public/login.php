<?php
/**
 * Login dell'amministratore.
 */
require __DIR__ . '/../includes/bootstrap.php';

if (!login_admin_consentito()) {
    abort(403, 'Accesso non consentito da questa rete.');
}
if (is_admin()) {
    redirect(url('index.php'));
}

$errore = null;
$username = '';
if (is_post()) {
    csrf_verify();
    $username = input_str($_POST, 'username', 50);
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    if ($username === '' || $password === '') {
        $errore = 'Inserire username e password.';
    } else {
        $errore = tenta_login($username, $password);
        if ($errore === null) {
            flash('ok', 'Accesso effettuato.');
            redirect(url('index.php'));
        }
    }
}

$titolo_pagina = 'Accesso amministratore';
require __DIR__ . '/../includes/header.php';
?>
<section class="card card-stretta">
    <h1>Accesso amministratore</h1>
    <?php if ($errore): ?>
        <div class="avviso avviso-errore" role="alert"><?= e($errore) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('login.php')) ?>" autocomplete="off">
        <?= csrf_field() ?>
        <label class="campo">
            <span>Username</span>
            <input type="text" name="username" value="<?= e($username) ?>" maxlength="50" required autofocus autocomplete="username">
        </label>
        <label class="campo">
            <span>Password</span>
            <input type="password" name="password" maxlength="200" required autocomplete="current-password">
        </label>
        <div class="azioni">
            <button type="submit" class="btn btn-primario">Accedi</button>
        </div>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
