<?php
/**
 * Eliminazione di una scheda interprete (solo admin, solo POST + CSRF).
 */
require __DIR__ . '/../../includes/bootstrap.php';
require_admin();

if (!is_post()) {
    abort(400, 'Operazione non consentita con questo metodo.');
}
csrf_verify();

$id = input_str($_POST, 'id', 10);
if (ctype_digit($id) && elimina_interprete((int) $id)) {
    flash('ok', 'Scheda eliminata.');
} else {
    flash('errore', 'Scheda non trovata.');
}
redirect(url('index.php'));
