<?php
/**
 * Logout dell'amministratore (solo POST con token CSRF).
 */
require __DIR__ . '/../includes/bootstrap.php';

if (is_post() && is_admin()) {
    csrf_verify();
    logout_admin();
    flash('ok', 'Disconnessione effettuata.');
}
redirect(url(has_view_access() ? 'index.php' : 'login.php'));
