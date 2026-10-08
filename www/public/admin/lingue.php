<?php
/**
 * Gestione dell'elenco delle lingue (solo admin).
 */
require __DIR__ . '/../../includes/bootstrap.php';
require_admin();

/** Valida il nome; restituisce [$nome, $errore|null]. */
$leggiLingua = function (array $in): array {
    $nome = input_str($in, 'nome', 200);
    $errore = null;
    if ($nome === '' || mb_strlen($nome) > 80) {
        $errore = 'Il nome della lingua è obbligatorio (massimo 80 caratteri).';
    }
    return [$nome, $errore];
};

if (is_post()) {
    csrf_verify();
    $azione = input_str($_POST, 'azione', 20);
    $id = input_str($_POST, 'id', 10);
    $id = ctype_digit($id) ? (int) $id : 0;

    try {
        if ($azione === 'aggiungi') {
            [$nome, $errore] = $leggiLingua($_POST);
            if ($errore) {
                flash('errore', $errore);
            } else {
                db()->prepare('INSERT INTO lingue (nome) VALUES (?)')->execute([$nome]);
                flash('ok', 'Lingua aggiunta.');
            }
        } elseif ($azione === 'rinomina' && $id > 0) {
            [$nome, $errore] = $leggiLingua($_POST);
            if ($errore) {
                flash('errore', $errore);
            } else {
                db()->prepare('UPDATE lingue SET nome = ? WHERE id = ?')->execute([$nome, $id]);
                flash('ok', 'Lingua aggiornata.');
            }
        } elseif ($azione === 'elimina' && $id > 0) {
            $st = db()->prepare('SELECT COUNT(*) FROM interprete_lingua WHERE lingua_id = ?');
            $st->execute([$id]);
            if ((int) $st->fetchColumn() > 0) {
                flash('errore', 'La lingua è usata in almeno una scheda e non può essere eliminata.');
            } else {
                db()->prepare('DELETE FROM lingue WHERE id = ?')->execute([$id]);
                flash('ok', 'Lingua eliminata.');
            }
        } else {
            flash('errore', 'Operazione non riconosciuta.');
        }
    } catch (PDOException $ex) {
        // 23000 = violazione di vincolo (nome duplicato, lingua in uso)
        if ($ex->getCode() !== '23000') {
            throw $ex;
        }
        flash('errore', $azione === 'elimina'
            ? 'La lingua è usata in almeno una scheda e non può essere eliminata.'
            : 'Esiste già una lingua con questo nome.');
    }
    redirect(url('admin/lingue.php'));
}

$lingue = db()->query(
    'SELECT l.id, l.nome, COUNT(il.interprete_id) AS usi
       FROM lingue l LEFT JOIN interprete_lingua il ON il.lingua_id = l.id
      GROUP BY l.id, l.nome
      ORDER BY l.nome'
)->fetchAll();

$titolo_pagina = 'Lingue';
require __DIR__ . '/../../includes/header.php';
?>
<h1>Lingue</h1>

<section class="card">
    <h2>Aggiungi lingua</h2>
    <form method="post" action="<?= e(url('admin/lingue.php')) ?>" class="riga-dinamica">
        <?= csrf_field() ?>
        <input type="hidden" name="azione" value="aggiungi">
        <input type="text" name="nome" maxlength="80" required placeholder="Nome (es. Portoghese)" aria-label="Nome della lingua">
        <button type="submit" class="btn btn-primario">Aggiungi</button>
    </form>
</section>

<section class="card">
    <h2>Elenco (<?= count($lingue) ?>)</h2>
    <?php if (!$lingue): ?>
        <p class="tenue">Nessuna lingua presente.</p>
    <?php endif; ?>
    <div class="elenco-gestione">
    <?php foreach ($lingue as $l): ?>
        <div class="riga-gestione">
            <form method="post" action="<?= e(url('admin/lingue.php')) ?>" class="riga-dinamica">
                <?= csrf_field() ?>
                <input type="hidden" name="azione" value="rinomina">
                <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                <input type="text" name="nome" value="<?= e($l['nome']) ?>" maxlength="80" required aria-label="Nome della lingua">
                <button type="submit" class="btn btn-piccolo">Salva</button>
            </form>
            <span class="tenue piccolo usi"><?= (int) $l['usi'] === 1 ? '1 scheda' : (int) $l['usi'] . ' schede' ?></span>
            <?php if ((int) $l['usi'] === 0): ?>
                <form method="post" action="<?= e(url('admin/lingue.php')) ?>"
                      data-conferma="Eliminare la lingua &quot;<?= e($l['nome']) ?>&quot;?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="azione" value="elimina">
                    <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                    <button type="submit" class="btn btn-piccolo btn-pericolo">Elimina</button>
                </form>
            <?php else: ?>
                <button type="button" class="btn btn-piccolo" disabled title="In uso: non eliminabile">Elimina</button>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
