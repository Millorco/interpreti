<?php
/**
 * Home: ricerca per lingua. L'elenco degli interpreti compare solo dopo
 * aver avviato una ricerca (con ordinamento e paginazione).
 * Accessibile in consultazione (LAN) e agli admin.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_view_access();

// Senza filtro di stato a video: l'admin vede tutte le schede, la consultazione solo le attive
$filtri = leggi_filtri($_GET, is_admin() ? 'tutti' : 'attivi');

// L'elenco viene mostrato solo se è stata avviata una ricerca
$cercato = false;
foreach (['lingue', 'q', 'giorno', 'ora', 'h24', 'stato'] as $parametro) {
    $cercato = $cercato || isset($_GET[$parametro]);
}
/** Query string dei filtri correnti; conserva la ricerca anche con "Tutte" le lingue. */
$qs = function (array $f, array $extra = []): array {
    $q = filtri_query($f, $extra);
    return isset($q['lingue']) ? $q : ['lingue' => ['']] + $q;
};

$perPagina = max(5, (int) config('righe_per_pagina', 25));
$totale = $cercato ? conta_interpreti($filtri) : 0;
$pagine = max(1, (int) ceil($totale / $perPagina));
$pagina = min($pagine, max(1, (int) input_str($_GET, 'p', 6)));
$interpreti = $cercato ? cerca_interpreti($filtri, $perPagina, ($pagina - 1) * $perPagina) : [];
$lingue = elenco_lingue();
$admin = is_admin();

/** Link di intestazione colonna che inverte l'ordinamento. */
$linkOrdine = function (string $colonna, string $etichetta) use ($filtri, $qs): string {
    $attiva = $filtri['ord'] === $colonna;
    $dir = ($attiva && $filtri['dir'] === 'asc') ? 'desc' : 'asc';
    $f = array_merge($filtri, ['ord' => $colonna, 'dir' => $dir]);
    $freccia = $attiva ? ($filtri['dir'] === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="' . e(url('index.php', $qs($f))) . '">' . e($etichetta . $freccia) . '</a>';
};
$ariaOrdine = fn (string $c): string => $filtri['ord'] === $c
    ? ' aria-sort="' . ($filtri['dir'] === 'asc' ? 'ascending' : 'descending') . '"' : '';

$titolo_pagina = 'Home';
require __DIR__ . '/../includes/header.php';
?>
<div class="titolo-riga">
    <h1>Interpreti</h1>
    <div class="azioni no-print">
        <?php if ($admin): ?>
            <a class="btn btn-primario" href="<?= e(url('admin/interprete_form.php')) ?>">+ Nuovo interprete</a>
        <?php endif; ?>
    </div>
</div>

<form method="get" action="<?= e(url('index.php')) ?>" class="card filtri no-print">
    <fieldset class="filtro-lingue">
        <legend>Lingua</legend>
        <div class="elenco-check">
            <label class="chip">
                <input type="radio" name="lingue[]" value="" <?= $filtri['lingue'] ? '' : 'checked' ?>>
                <span>Tutte</span>
            </label>
            <?php foreach ($lingue as $l): ?>
                <label class="chip">
                    <input type="radio" name="lingue[]" value="<?= (int) $l['id'] ?>"
                        <?= in_array((int) $l['id'], $filtri['lingue'], true) ? 'checked' : '' ?>>
                    <span><?= e($l['nome']) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <div class="azioni">
        <button type="submit" class="btn btn-primario">Cerca</button>
        <a class="btn" href="<?= e(url('index.php')) ?>">Azzera filtri</a>
    </div>
</form>

<?php
if (!$cercato) {
    require __DIR__ . '/../includes/footer.php';
    return;
}
?>
<p class="conteggio">
    <?= $totale === 1 ? '1 interprete trovato' : e($totale) . ' interpreti trovati' ?>
    <?php if ($pagine > 1): ?> · pagina <?= $pagina ?> di <?= $pagine ?><?php endif; ?>
</p>

<?php if (!$interpreti): ?>
    <div class="card vuoto">Nessun interprete corrisponde ai criteri di ricerca.</div>
<?php else: ?>
<div class="tabella-scroll">
    <table class="tabella">
        <thead>
            <tr>
                <th<?= $ariaOrdine('nome') ?>><?= $linkOrdine('nome', 'Cognome e nome') ?></th>
                <th>Lingue</th>
                <th>Disponibilità</th>
                <th>Telefono</th>
                <th<?= $ariaOrdine('citta') ?>><?= $linkOrdine('citta', 'Città') ?></th>
                <th<?= $ariaOrdine('stato') ?>><?= $linkOrdine('stato', 'Stato') ?></th>
                <?php if ($admin): ?><th class="no-print"><span class="solo-sr">Azioni</span></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($interpreti as $i): ?>
            <tr class="<?= $i['attivo'] ? '' : 'riga-inattiva' ?>">
                <td data-etichetta="Nome">
                    <a class="nome-link" href="<?= e(url('scheda.php', ['id' => $i['id']])) ?>">
                        <?= e($i['cognome'] . ' ' . $i['nome']) ?>
                    </a>
                </td>
                <td data-etichetta="Lingue">
                    <?php foreach ($i['lingue'] as $l): ?>
                        <span class="badge badge-<?= e($l['livello']) ?>" title="<?= e(LIVELLI[$l['livello']] ?? '') ?>"><?= e($l['nome']) ?></span>
                    <?php endforeach; ?>
                </td>
                <td data-etichetta="Disponibilità">
                    <?php $sint = disponibilita_sintetica($i); ?>
                    <span class="disp <?= $i['disp_h24'] ? 'disp-h24' : '' ?>"><?= e($sint) ?></span>
                </td>
                <td data-etichetta="Telefono" class="nowrap"><?= e($i['telefono']) ?></td>
                <td data-etichetta="Città"><?= e($i['citta']) ?></td>
                <td data-etichetta="Stato">
                    <span class="stato <?= $i['attivo'] ? 'stato-attivo' : 'stato-inattivo' ?>"><?= $i['attivo'] ? 'Attivo' : 'Non attivo' ?></span>
                </td>
                <?php if ($admin): ?>
                    <td class="no-print nowrap">
                        <a href="<?= e(url('admin/interprete_form.php', ['id' => $i['id']])) ?>">Modifica</a>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="legenda no-print">
    Livello:
    <?php foreach (LIVELLI as $k => $nome): ?>
        <span class="badge badge-<?= e($k) ?>"><?= e($nome) ?></span>
    <?php endforeach; ?>
</p>
<?php endif; ?>

<?php if ($pagine > 1): ?>
<nav class="paginazione no-print" aria-label="Pagine">
    <?php if ($pagina > 1): ?>
        <a class="btn" href="<?= e(url('index.php', $qs($filtri, ['p' => $pagina - 1]))) ?>">← Precedente</a>
    <?php endif; ?>
    <?php for ($n = 1; $n <= $pagine; $n++): ?>
        <?php if ($n === 1 || $n === $pagine || abs($n - $pagina) <= 2): ?>
            <?php if ($n === $pagina): ?>
                <span class="btn btn-corrente" aria-current="page"><?= $n ?></span>
            <?php else: ?>
                <a class="btn" href="<?= e(url('index.php', $qs($filtri, ['p' => $n]))) ?>"><?= $n ?></a>
            <?php endif; ?>
        <?php elseif (abs($n - $pagina) === 3): ?>
            <span class="puntini">…</span>
        <?php endif; ?>
    <?php endfor; ?>
    <?php if ($pagina < $pagine): ?>
        <a class="btn" href="<?= e(url('index.php', $qs($filtri, ['p' => $pagina + 1]))) ?>">Successiva →</a>
    <?php endif; ?>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
