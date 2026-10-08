<?php
/**
 * Intestazione comune. Variabile attesa: $titolo_pagina (facoltativa).
 */
$__admin = is_admin();
$__vista = has_view_access();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($titolo_pagina ?? 'Elenco') ?> · Database interpreti</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<header class="topbar no-print">
    <div class="contenitore topbar-interno">
        <a class="marchio" href="<?= e(url('index.php')) ?>">Database interpreti</a>
        <nav class="menu" aria-label="Menu principale">
            <?php if ($__vista): ?>
                <a href="<?= e(url('index.php')) ?>">Home</a>
            <?php endif; ?>
            <?php if ($__admin): ?>
                <a href="<?= e(url('admin/interprete_form.php')) ?>">Nuovo interprete</a>
                <a href="<?= e(url('admin/lingue.php')) ?>">Lingue</a>
                <a href="<?= e(url('admin/password.php')) ?>">Password</a>
                <form method="post" action="<?= e(url('logout.php')) ?>" class="form-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="link-button">Esci (<?= e(admin_username()) ?>)</button>
                </form>
            <?php endif; ?>
        </nav>
        <span class="ruolo <?= $__admin ? 'ruolo-admin' : '' ?>">
            <?= $__admin ? 'Amministratore' : ($__vista ? 'Consultazione' : 'Non autorizzato') ?>
        </span>
        <?php if (!$__admin && login_admin_consentito()): ?>
            <a class="link-accesso" href="<?= e(url('login.php')) ?>">Accesso admin</a>
        <?php endif; ?>
    </div>
</header>
<main class="contenitore">
<?php foreach (flash_prendi() as $__m): ?>
    <div class="avviso avviso-<?= e($__m['tipo']) ?> no-print" role="status"><?= e($__m['msg']) ?></div>
<?php endforeach; ?>
