<?php
/**
 * Creazione / modifica di una scheda interprete (solo admin).
 */
require __DIR__ . '/../../includes/bootstrap.php';
require_admin();

$idTxt = input_str($_GET, 'id', 10);
$id = ($idTxt !== '' && ctype_digit($idTxt)) ? (int) $idTxt : null;
$errori = [];

if ($id !== null) {
    $dati = carica_interprete($id);
    if (!$dati) {
        abort(404, 'Scheda non trovata.');
    }
} else {
    if ($idTxt !== '') {
        abort(404, 'Scheda non trovata.');
    }
    $dati = interprete_vuoto();
}

if (is_post()) {
    csrf_verify();
    [$dati, $errori] = valida_interprete($_POST);
    $dati['id'] = $id;
    if (!$errori) {
        $idSalvato = salva_interprete($dati, $id);
        flash('ok', $id === null ? 'Scheda creata.' : 'Scheda aggiornata.');
        redirect(url('scheda.php', ['id' => $idSalvato]));
    }
}

$lingue = elenco_lingue();
$nazioni = elenco_nazioni();

/** Riga lingua + livello ($n è l'indice, "__i__" nel template usato dal JS). */
$rigaLingua = function ($n, array $r) use ($lingue): string {
    $h = '<div class="riga-dinamica">';
    $h .= '<select name="lingue[' . e($n) . '][lingua_id]" data-campo="lingua_id" aria-label="Lingua">';
    $h .= '<option value="">— lingua —</option>';
    foreach ($lingue as $l) {
        $sel = ((string) ($r['lingua_id'] ?? '') === (string) $l['id']) ? ' selected' : '';
        $h .= '<option value="' . (int) $l['id'] . '"' . $sel . '>' . e($l['nome']) . '</option>';
    }
    $h .= '</select>';
    $h .= '<select name="lingue[' . e($n) . '][livello]" data-campo="livello" aria-label="Livello">';
    foreach (LIVELLI as $k => $nome) {
        $sel = (($r['livello'] ?? 'buono') === $k) ? ' selected' : '';
        $h .= '<option value="' . e($k) . '"' . $sel . '>' . e($nome) . '</option>';
    }
    $h .= '</select>';
    $h .= '<button type="button" class="btn btn-piccolo" data-azione="rimuovi-riga" aria-label="Rimuovi lingua">✕</button>';
    return $h . '</div>';
};

/** Riga giorno + ora inizio + ora fine. */
$rigaFascia = function ($n, array $r): string {
    $h = '<div class="riga-dinamica">';
    $h .= '<select name="fasce[' . e($n) . '][giorno_settimana]" data-campo="giorno_settimana" aria-label="Giorno">';
    foreach (GIORNI as $g => $nome) {
        $sel = ((string) ($r['giorno_settimana'] ?? '1') === (string) $g) ? ' selected' : '';
        $h .= '<option value="' . $g . '"' . $sel . '>' . e($nome) . '</option>';
    }
    $h .= '</select>';
    $h .= '<span class="dalle-alle">dalle</span>';
    $h .= '<input type="time" name="fasce[' . e($n) . '][ora_inizio]" data-campo="ora_inizio" value="' . e(ora_breve($r['ora_inizio'] ?? '')) . '" aria-label="Ora di inizio">';
    $h .= '<span class="dalle-alle">alle</span>';
    $h .= '<input type="time" name="fasce[' . e($n) . '][ora_fine]" data-campo="ora_fine" value="' . e(ora_breve($r['ora_fine'] ?? '')) . '" aria-label="Ora di fine">';
    $h .= '<button type="button" class="btn btn-piccolo" data-azione="rimuovi-riga" aria-label="Rimuovi fascia">✕</button>';
    return $h . '</div>';
};

$titolo_pagina = $id === null ? 'Nuovo interprete' : 'Modifica ' . $dati['cognome'] . ' ' . $dati['nome'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="titolo-riga">
    <div>
        <a class="indietro" href="<?= e($id === null ? url('index.php') : url('scheda.php', ['id' => $id])) ?>">← Annulla e torna indietro</a>
        <h1><?= $id === null ? 'Nuovo interprete' : 'Modifica scheda' ?></h1>
    </div>
</div>

<?php if ($errori): ?>
    <div class="avviso avviso-errore" role="alert">
        <strong>Correggere i seguenti errori:</strong>
        <ul>
            <?php foreach ($errori as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/interprete_form.php', $id === null ? [] : ['id' => $id])) ?>" class="form-scheda">
    <?= csrf_field() ?>

    <section class="card">
        <h2>Anagrafica</h2>
        <div class="griglia-form">
            <label class="campo"><span>Cognome *</span>
                <input type="text" name="cognome" value="<?= e($dati['cognome']) ?>" maxlength="100" required></label>
            <label class="campo"><span>Nome *</span>
                <input type="text" name="nome" value="<?= e($dati['nome']) ?>" maxlength="100" required></label>
            <label class="campo"><span>Nazione di nascita</span>
                <select name="nazione_nascita_id">
                    <option value="">— non indicata —</option>
                    <?php foreach ($nazioni as $n): ?>
                        <option value="<?= (int) $n['id'] ?>" <?= (string) $dati['nazione_nascita_id'] === (string) $n['id'] ? 'selected' : '' ?>><?= e($n['nome']) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="campo"><span>Sesso</span>
                <select name="sesso">
                    <option value="">— non indicato —</option>
                    <?php foreach (SESSI as $k => $nome): ?>
                        <option value="<?= e($k) ?>" <?= $dati['sesso'] === $k ? 'selected' : '' ?>><?= e($nome) ?></option>
                    <?php endforeach; ?>
                </select></label>
        </div>
    </section>

    <section class="card">
        <h2>Contatti</h2>
        <div class="griglia-form">
            <label class="campo"><span>Telefono</span>
                <input type="tel" name="telefono" value="<?= e($dati['telefono']) ?>" maxlength="30"></label>
            <label class="campo"><span>Secondo telefono</span>
                <input type="tel" name="telefono2" value="<?= e($dati['telefono2']) ?>" maxlength="30"></label>
            <label class="campo"><span>Email</span>
                <input type="email" name="email" value="<?= e($dati['email']) ?>" maxlength="150"></label>
            <label class="campo"><span>Città</span>
                <input type="text" name="citta" value="<?= e($dati['citta']) ?>" maxlength="100"></label>
            <label class="campo campo-largo"><span>Indirizzo</span>
                <input type="text" name="indirizzo" value="<?= e($dati['indirizzo']) ?>" maxlength="255"></label>
        </div>
    </section>

    <section class="card">
        <h2>Posto di lavoro</h2>
        <div class="griglia-form">
            <label class="campo"><span>Datore di lavoro / sede</span>
                <input type="text" name="lavoro_nome" value="<?= e($dati['lavoro_nome']) ?>" maxlength="150" placeholder="es. Ospedale Maggiore, reparto..."></label>
            <label class="campo"><span>Telefono del posto di lavoro</span>
                <input type="tel" name="lavoro_telefono" value="<?= e($dati['lavoro_telefono']) ?>" maxlength="30"></label>
            <label class="campo campo-largo"><span>Indirizzo del posto di lavoro</span>
                <input type="text" name="lavoro_indirizzo" value="<?= e($dati['lavoro_indirizzo']) ?>" maxlength="255"></label>
        </div>
        <label class="chip">
            <input type="checkbox" name="lavoro_contattabile" value="1" <?= $dati['lavoro_contattabile'] ? 'checked' : '' ?>>
            <span>Può essere contattato sul posto di lavoro</span>
        </label>
    </section>

    <section class="card">
        <h2>Lingue</h2>
        <div id="righe-lingue" data-prossimo="<?= count($dati['lingue']) ?>">
            <?php foreach (array_values($dati['lingue']) as $n => $r) { echo $rigaLingua($n, $r); } ?>
        </div>
        <button type="button" class="btn" data-azione="aggiungi-riga" data-gruppo="lingue">+ Aggiungi lingua</button>
        <template id="tpl-lingue"><?= $rigaLingua('__i__', []) ?></template>
        <?php if (!$lingue): ?>
            <p class="tenue">Nessuna lingua in elenco: aggiungerle dalla pagina <a href="<?= e(url('admin/lingue.php')) ?>">Lingue</a>.</p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Disponibilità</h2>
        <div class="elenco-check">
            <label class="chip">
                <input type="checkbox" name="disp_h24" value="1" id="disp_h24" <?= $dati['disp_h24'] ? 'checked' : '' ?>>
                <span>Disponibile 24h su 24</span>
            </label>
            <label class="chip">
                <input type="checkbox" name="disp_solo_feriali" value="1" <?= $dati['disp_solo_feriali'] ? 'checked' : '' ?>>
                <span>Solo giorni feriali (lun-ven)</span>
            </label>
        </div>

        <div id="sezione-fasce">
            <h3>Fasce orarie</h3>
            <p class="tenue piccolo" id="avviso-h24" hidden>Con "24h su 24" attivo le fasce orarie vengono conservate ma ignorate.</p>
            <div class="scorciatoia">
                <strong>Lun-Ven stesso orario:</strong>
                <span class="dalle-alle">dalle</span>
                <input type="time" id="lv-inizio" value="08:00" aria-label="Ora di inizio lun-ven">
                <span class="dalle-alle">alle</span>
                <input type="time" id="lv-fine" value="18:00" aria-label="Ora di fine lun-ven">
                <button type="button" class="btn" data-azione="lun-ven">Aggiungi per i 5 giorni feriali</button>
            </div>
            <div id="righe-fasce" data-prossimo="<?= count($dati['fasce']) ?>">
                <?php foreach (array_values($dati['fasce']) as $n => $r) { echo $rigaFascia($n, $r); } ?>
            </div>
            <button type="button" class="btn" data-azione="aggiungi-riga" data-gruppo="fasce">+ Aggiungi fascia</button>
            <template id="tpl-fasce"><?= $rigaFascia('__i__', []) ?></template>
            <p class="tenue piccolo">Più fasce nello stesso giorno sono ammesse (es. 08:00-12:00 e 15:00-19:00), purché non si sovrappongano.
                Per indicare "fino a mezzanotte" usare 23:59.</p>
        </div>

        <label class="campo"><span>Note sulla disponibilità</span>
            <input type="text" name="disp_note" value="<?= e($dati['disp_note']) ?>" maxlength="500" placeholder='es. "solo su preavviso di 48h"'></label>
    </section>

    <section class="card">
        <h2>Note e stato</h2>
        <label class="campo"><span>Note</span>
            <textarea name="note" rows="5" maxlength="5000"><?= e($dati['note']) ?></textarea></label>
        <label class="chip">
            <input type="checkbox" name="attivo" value="1" <?= $dati['attivo'] ? 'checked' : '' ?>>
            <span>Scheda attiva</span>
        </label>
        <p class="tenue piccolo">Togliere la spunta per disattivare la scheda senza eliminarla.</p>
    </section>

    <div class="azioni azioni-fisse">
        <button type="submit" class="btn btn-primario">Salva scheda</button>
        <a class="btn" href="<?= e($id === null ? url('index.php') : url('scheda.php', ['id' => $id])) ?>">Annulla</a>
    </div>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
