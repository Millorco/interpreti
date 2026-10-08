<?php
/**
 * Scheda di dettaglio di un interprete (vista pulita e stampabile).
 */
require __DIR__ . '/../includes/bootstrap.php';
require_view_access();

$id = input_str($_GET, 'id', 10);
$i = ctype_digit($id) ? carica_interprete((int) $id) : null;
if (!$i) {
    abort(404, 'Scheda non trovata.');
}
$admin = is_admin();
$h24 = (bool) $i['disp_h24'];
$soloFeriali = (bool) $i['disp_solo_feriali'];
$griglia = griglia_settimanale($i);
$perGiorno = fasce_per_giorno($i['fasce']);

$titolo_pagina = $i['cognome'] . ' ' . $i['nome'];
require __DIR__ . '/../includes/header.php';
?>
<div class="titolo-riga">
    <div>
        <a class="indietro no-print" href="<?= e(url('index.php')) ?>">← Torna all'elenco</a>
        <h1>
            <?= e($i['cognome'] . ' ' . $i['nome']) ?>
            <span class="stato <?= $i['attivo'] ? 'stato-attivo' : 'stato-inattivo' ?>"><?= $i['attivo'] ? 'Attivo' : 'Non attivo' ?></span>
        </h1>
    </div>
    <div class="azioni no-print">
        <button type="button" class="btn" data-azione="stampa">Stampa</button>
        <?php if ($admin): ?>
            <a class="btn btn-primario" href="<?= e(url('admin/interprete_form.php', ['id' => $i['id']])) ?>">Modifica</a>
            <form method="post" action="<?= e(url('admin/interprete_elimina.php')) ?>" class="form-inline"
                  data-conferma="Eliminare definitivamente la scheda di <?= e($i['cognome'] . ' ' . $i['nome']) ?>? L'operazione non è reversibile.">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                <button type="submit" class="btn btn-pericolo">Elimina</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="scheda-colonne">
    <section class="card">
        <h2>Anagrafica</h2>
        <dl class="dati">
            <dt>Cognome</dt><dd><?= e($i['cognome']) ?></dd>
            <dt>Nome</dt><dd><?= e($i['nome']) ?></dd>
            <?php if ($i['nazione_nascita']): ?><dt>Nazione di nascita</dt><dd><?= e($i['nazione_nascita']) ?></dd><?php endif; ?>
            <?php if ($i['sesso']): ?><dt>Sesso</dt><dd><?= e(SESSI[$i['sesso']] ?? '') ?></dd><?php endif; ?>
        </dl>
    </section>

    <section class="card">
        <h2>Contatti</h2>
        <dl class="dati">
            <dt>Telefono</dt><dd><?= $i['telefono'] ? e($i['telefono']) : '—' ?></dd>
            <?php if ($i['telefono2']): ?><dt>Secondo telefono</dt><dd><?= e($i['telefono2']) ?></dd><?php endif; ?>
            <dt>Email</dt>
            <dd><?php if ($i['email']): ?><a href="mailto:<?= e($i['email']) ?>"><?= e($i['email']) ?></a><?php else: ?>—<?php endif; ?></dd>
            <dt>Città</dt><dd><?= $i['citta'] ? e($i['citta']) : '—' ?></dd>
            <?php if ($i['indirizzo']): ?><dt>Indirizzo</dt><dd><?= e($i['indirizzo']) ?></dd><?php endif; ?>
        </dl>
    </section>
</div>

<?php if ($i['lavoro_nome'] || $i['lavoro_indirizzo'] || $i['lavoro_telefono'] || $i['lavoro_contattabile']): ?>
<section class="card">
    <h2>Posto di lavoro</h2>
    <dl class="dati">
        <?php if ($i['lavoro_nome']): ?><dt>Datore di lavoro / sede</dt><dd><?= e($i['lavoro_nome']) ?></dd><?php endif; ?>
        <?php if ($i['lavoro_indirizzo']): ?><dt>Indirizzo</dt><dd><?= e($i['lavoro_indirizzo']) ?></dd><?php endif; ?>
        <?php if ($i['lavoro_telefono']): ?><dt>Telefono</dt><dd><?= e($i['lavoro_telefono']) ?></dd><?php endif; ?>
        <dt>Contattabile al lavoro</dt>
        <dd>
            <span class="stato <?= $i['lavoro_contattabile'] ? 'stato-attivo' : 'stato-no' ?>"><?= $i['lavoro_contattabile'] ? 'Sì' : 'No, non contattare al lavoro' ?></span>
        </dd>
    </dl>
</section>
<?php endif; ?>

<section class="card">
    <h2>Lingue</h2>
    <?php if ($i['lingue']): ?>
        <ul class="elenco-lingue">
            <?php foreach ($i['lingue'] as $l): ?>
                <li>
                    <strong><?= e($l['nome']) ?></strong>
                    <span class="badge badge-<?= e($l['livello']) ?>"><?= e(LIVELLI[$l['livello']] ?? $l['livello']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="tenue">Nessuna lingua indicata.</p>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Disponibilità</h2>
    <?php if ($h24): ?>
        <p class="disp-banner">Disponibile 24h su 24<?= $soloFeriali ? ' · solo giorni feriali (lun-ven)' : '' ?></p>
    <?php elseif (!$perGiorno): ?>
        <p class="tenue">Nessuna fascia oraria indicata.</p>
    <?php elseif ($soloFeriali): ?>
        <p class="tenue">Solo giorni feriali.</p>
    <?php endif; ?>

    <?php if ($h24 || $perGiorno): ?>
    <div class="tabella-scroll">
        <table class="settimana">
            <caption class="solo-sr">Griglia settimanale della disponibilità, a intervalli di mezz'ora</caption>
            <thead>
                <tr>
                    <th scope="col" class="settimana-giorno">Giorno</th>
                    <?php for ($h = 0; $h < 24; $h++): ?>
                        <th scope="col" colspan="2" class="settimana-ora"><?= sprintf('%02d', $h) ?></th>
                    <?php endfor; ?>
                    <th scope="col" class="settimana-testo">Fasce</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (GIORNI as $g => $nomeGiorno): ?>
                <?php $giornoPieno = !in_array(false, $griglia[$g], true); ?>
                <tr>
                    <th scope="row" class="settimana-giorno"><?= e($nomeGiorno) ?></th>
                    <?php foreach ($griglia[$g] as $s => $pieno): ?>
                        <td class="slot<?= $pieno ? ' slot-on' : '' ?><?= $s % 2 === 0 ? ' slot-ora' : '' ?>"></td>
                    <?php endforeach; ?>
                    <td class="settimana-testo">
                        <?php
                        if ($h24) {
                            echo $giornoPieno ? '00:00-24:00' : '—';
                        } elseif (!empty($perGiorno[$g]) && !($soloFeriali && $g >= 6)) {
                            echo e(implode(', ', $perGiorno[$g]));
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($i['disp_note']): ?>
        <p class="nota-disp"><strong>Note sulla disponibilità:</strong> <?= e($i['disp_note']) ?></p>
    <?php endif; ?>
</section>

<?php if ($i['note']): ?>
<section class="card">
    <h2>Note</h2>
    <p class="testo-libero"><?= nl2br(e($i['note'])) ?></p>
</section>
<?php endif; ?>

<p class="tenue piccolo">
    Scheda creata il <?= e(date('d/m/Y', strtotime((string) $i['creato_il']))) ?>,
    ultima modifica il <?= e(date('d/m/Y H:i', strtotime((string) $i['modificato_il']))) ?>.
</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
