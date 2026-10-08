<?php
/**
 * Logica applicativa sugli interpreti: ricerca, lettura, validazione,
 * salvataggio e presentazione della disponibilità.
 * Tutte le query usano istruzioni preparate.
 */

const GIORNI = [1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì', 4 => 'Giovedì', 5 => 'Venerdì', 6 => 'Sabato', 7 => 'Domenica'];
const GIORNI_BREVI = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven', 6 => 'Sab', 7 => 'Dom'];
const LIVELLI = ['madrelingua' => 'Madrelingua', 'ottimo' => 'Ottimo', 'buono' => 'Buono', 'base' => 'Base'];
const SESSI = ['M' => 'Maschile', 'F' => 'Femminile', 'X' => 'Altro / non specificato'];
const MAX_FASCE = 60;
const REGEX_ORA = '/^([01]\d|2[0-3]):[0-5]\d$/';

// =====================================================================
//  Lingue e nazioni
// =====================================================================

/** Elenco completo delle lingue, in ordine alfabetico. */
function elenco_lingue(): array
{
    return db()->query('SELECT id, nome FROM lingue ORDER BY nome')->fetchAll();
}

/** Elenco completo delle nazioni, in ordine alfabetico. */
function elenco_nazioni(): array
{
    return db()->query('SELECT id, nome FROM nazioni ORDER BY nome')->fetchAll();
}

// =====================================================================
//  Ricerca
// =====================================================================

/**
 * Normalizza i filtri di ricerca letti dalla query string.
 * $statoDefault: stato applicato quando la richiesta non lo indica.
 */
function leggi_filtri(array $get, string $statoDefault = 'attivi'): array
{
    $lingue = [];
    if (isset($get['lingue']) && is_array($get['lingue'])) {
        foreach ($get['lingue'] as $id) {
            if (is_string($id) && ctype_digit($id) && (int) $id > 0) {
                $lingue[(int) $id] = (int) $id;
            }
        }
    }
    $giorno = input_str($get, 'giorno', 1);
    $ora    = input_str($get, 'ora', 5);
    $stato  = input_str($get, 'stato', 10);
    $ord    = input_str($get, 'ord', 12);
    return [
        'q'             => input_str($get, 'q', 100),
        // La ricerca ammette una sola lingua: si considera la prima indicata
        'lingue'        => array_slice(array_values($lingue), 0, 1),
        'giorno'        => isset(GIORNI[(int) $giorno]) ? (int) $giorno : null,
        'ora'           => preg_match(REGEX_ORA, $ora) ? $ora : null,
        'h24'           => input_str($get, 'h24', 1) === '1',
        'stato'         => in_array($stato, ['attivi', 'tutti', 'inattivi'], true) ? $stato : $statoDefault,
        'stato_default' => $statoDefault,
        'ord'           => in_array($ord, ['nome', 'citta', 'stato', 'modificato'], true) ? $ord : 'nome',
        'dir'           => input_str($get, 'dir', 4) === 'desc' ? 'desc' : 'asc',
    ];
}

/** Parametri della query string corrispondenti ai filtri (senza i default). */
function filtri_query(array $f, array $extra = []): array
{
    $q = [];
    if ($f['q'] !== '')                      $q['q'] = $f['q'];
    if ($f['lingue'])                        $q['lingue'] = $f['lingue'];
    if ($f['giorno'] !== null)               $q['giorno'] = $f['giorno'];
    if ($f['ora'] !== null)                  $q['ora'] = $f['ora'];
    if ($f['h24'])                           $q['h24'] = 1;
    if ($f['stato'] !== $f['stato_default']) $q['stato'] = $f['stato'];
    if ($f['ord'] !== 'nome')                $q['ord'] = $f['ord'];
    if ($f['dir'] !== 'asc')                 $q['dir'] = $f['dir'];
    return array_merge($q, $extra);
}

/**
 * Traduce i filtri in clausola WHERE + parametri posizionali.
 * Nel testo SQL finiscono solo frammenti fissi e segnaposto "?".
 */
function filtri_sql(array $f): array
{
    $w = [];
    $p = [];

    // Testo libero: ogni parola deve comparire in cognome, nome o città
    if ($f['q'] !== '') {
        $parole = array_slice(preg_split('/\s+/u', $f['q'], -1, PREG_SPLIT_NO_EMPTY), 0, 6);
        foreach ($parole as $parola) {
            $like = '%' . addcslashes($parola, '\\%_') . '%';
            $w[] = '(i.cognome LIKE ? OR i.nome LIKE ? OR i.citta LIKE ?)';
            array_push($p, $like, $like, $like);
        }
    }

    // Lingua richiesta
    foreach ($f['lingue'] as $idLingua) {
        $w[] = 'EXISTS (SELECT 1 FROM interprete_lingua il WHERE il.interprete_id = i.id AND il.lingua_id = ?)';
        $p[] = $idLingua;
    }

    if ($f['h24']) {
        $w[] = 'i.disp_h24 = 1';
    }

    // Giorno e/o ora: chi è H24 è sempre disponibile (le sue fasce sono
    // ignorate), gli altri devono avere una fascia che copre la richiesta.
    if ($f['giorno'] !== null || $f['ora'] !== null) {
        $c = ['df.interprete_id = i.id'];
        if ($f['giorno'] !== null) {
            $c[] = 'df.giorno_settimana = ?';
            $p[] = $f['giorno'];
        }
        if ($f['ora'] !== null) {
            // 23:59 è usato come "fine giornata": lo si considera incluso
            $c[] = "df.ora_inizio <= ? AND (df.ora_fine > ? OR df.ora_fine = '23:59:00')";
            array_push($p, $f['ora'], $f['ora']);
        }
        $w[] = '(i.disp_h24 = 1 OR EXISTS (SELECT 1 FROM disponibilita_fasce df WHERE ' . implode(' AND ', $c) . '))';
        if ($f['giorno'] !== null && $f['giorno'] >= 6) {
            $w[] = 'i.disp_solo_feriali = 0';
        }
    }

    if ($f['stato'] === 'attivi') {
        $w[] = 'i.attivo = 1';
    } elseif ($f['stato'] === 'inattivi') {
        $w[] = 'i.attivo = 0';
    }

    return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $p];
}

function conta_interpreti(array $f): int
{
    [$where, $p] = filtri_sql($f);
    $st = db()->prepare("SELECT COUNT(*) FROM interpreti i $where");
    $st->execute($p);
    return (int) $st->fetchColumn();
}

/**
 * Cerca gli interpreti. Ogni riga restituita contiene anche le chiavi
 * 'nazione_nascita' (nome), 'lingue' e 'fasce'.
 * Con $limite = null restituisce tutti i risultati.
 */
function cerca_interpreti(array $f, ?int $limite = null, int $offset = 0): array
{
    [$where, $p] = filtri_sql($f);

    // Ordinamento: solo espressioni prese da questa lista fissa
    $dir = $f['dir'] === 'desc' ? 'DESC' : 'ASC';
    $ordini = [
        'nome'       => "i.cognome $dir, i.nome $dir",
        'citta'      => "i.citta $dir, i.cognome ASC, i.nome ASC",
        'stato'      => "i.attivo $dir, i.cognome ASC, i.nome ASC",
        'modificato' => "i.modificato_il $dir",
    ];
    $sql = "SELECT i.*, n.nome AS nazione_nascita
              FROM interpreti i LEFT JOIN nazioni n ON n.id = i.nazione_nascita_id
            $where ORDER BY " . $ordini[$f['ord']] . ', i.id ASC';
    if ($limite !== null) {
        // Interi forzati con cast: nessun input arriva nel testo della query
        $sql .= ' LIMIT ' . max(1, (int) $limite) . ' OFFSET ' . max(0, (int) $offset);
    }
    $st = db()->prepare($sql);
    $st->execute($p);
    $righe = $st->fetchAll();
    return allega_lingue_e_fasce($righe);
}

/** Aggiunge a ciascun interprete le sue lingue e le sue fasce (2 query in tutto). */
function allega_lingue_e_fasce(array $righe): array
{
    if (!$righe) {
        return [];
    }
    $perId = [];
    foreach ($righe as $k => $r) {
        $righe[$k]['lingue'] = [];
        $righe[$k]['fasce'] = [];
        $perId[(int) $r['id']] = $k;
    }
    $ids = array_keys($perId);
    $segnaposto = implode(',', array_fill(0, count($ids), '?'));

    $st = db()->prepare(
        "SELECT il.interprete_id, il.lingua_id, il.livello, l.nome
           FROM interprete_lingua il JOIN lingue l ON l.id = il.lingua_id
          WHERE il.interprete_id IN ($segnaposto)
          ORDER BY FIELD(il.livello, 'madrelingua', 'ottimo', 'buono', 'base'), l.nome"
    );
    $st->execute($ids);
    foreach ($st as $r) {
        $righe[$perId[(int) $r['interprete_id']]]['lingue'][] = $r;
    }

    $st = db()->prepare(
        "SELECT interprete_id, giorno_settimana, ora_inizio, ora_fine
           FROM disponibilita_fasce
          WHERE interprete_id IN ($segnaposto)
          ORDER BY giorno_settimana, ora_inizio"
    );
    $st->execute($ids);
    foreach ($st as $r) {
        $righe[$perId[(int) $r['interprete_id']]]['fasce'][] = $r;
    }
    return $righe;
}

/** Carica una scheda completa, oppure null se non esiste. */
function carica_interprete(int $id): ?array
{
    $st = db()->prepare(
        'SELECT i.*, n.nome AS nazione_nascita
           FROM interpreti i LEFT JOIN nazioni n ON n.id = i.nazione_nascita_id
          WHERE i.id = ?'
    );
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ? allega_lingue_e_fasce([$r])[0] : null;
}

// =====================================================================
//  Presentazione della disponibilità
// =====================================================================

/** Fasce raggruppate per giorno: [1 => ['08:00-12:00', '15:00-19:00'], ...] */
function fasce_per_giorno(array $fasce): array
{
    $g = [];
    foreach ($fasce as $f) {
        $g[(int) $f['giorno_settimana']][] = ora_breve($f['ora_inizio']) . '-' . ora_breve($f['ora_fine']);
    }
    ksort($g);
    return $g;
}

/** Etichetta breve per l'elenco: "H24", "Lun-Ven 08-18", "Fasce varie"... */
function disponibilita_sintetica(array $i): string
{
    if (!empty($i['disp_h24'])) {
        return !empty($i['disp_solo_feriali']) ? 'H24 Lun-Ven' : 'H24';
    }
    $perGiorno = fasce_per_giorno($i['fasce'] ?? []);
    if (!$perGiorno) {
        return 'Non indicata';
    }
    // Tutti i giorni devono avere le stesse fasce (al massimo due)
    $firme = array_unique(array_map(fn ($a) => implode(', ', $a), $perGiorno));
    $fasce = reset($perGiorno);
    if (count($firme) !== 1 || count($fasce) > 2) {
        return 'Fasce varie';
    }
    $giorni = array_keys($perGiorno);
    $n = count($giorni);
    if ($n === 1) {
        $etichetta = GIORNI_BREVI[$giorni[0]];
    } elseif ($giorni[$n - 1] - $giorni[0] === $n - 1) {
        $etichetta = GIORNI_BREVI[$giorni[0]] . '-' . GIORNI_BREVI[$giorni[$n - 1]];
    } elseif ($n <= 3) {
        $etichetta = implode(', ', array_map(fn ($g) => GIORNI_BREVI[$g], $giorni));
    } else {
        return 'Fasce varie';
    }
    $orari = implode(', ', $fasce);
    // "08:00-18:00" -> "08-18" quando tutti gli orari sono a ore intere
    if (!preg_match('/:(?!00)\d\d/', $orari)) {
        $orari = str_replace(':00', '', $orari);
    }
    return $etichetta . ' ' . $orari;
}

/**
 * Griglia settimanale a mezz'ore: [giorno 1..7][0..47] => bool.
 * Una casella è "piena" se la mezz'ora è coperta, anche solo in parte.
 */
function griglia_settimanale(array $i): array
{
    $griglia = [];
    $soloFeriali = !empty($i['disp_solo_feriali']);
    foreach (array_keys(GIORNI) as $g) {
        $pieno = !empty($i['disp_h24']) && !($soloFeriali && $g >= 6);
        $griglia[$g] = array_fill(0, 48, $pieno);
    }
    if (!empty($i['disp_h24'])) {
        return $griglia;
    }
    foreach ($i['fasce'] ?? [] as $f) {
        $g = (int) $f['giorno_settimana'];
        if (!isset($griglia[$g]) || ($soloFeriali && $g >= 6)) {
            continue;
        }
        $da = minuti($f['ora_inizio']);
        $a  = minuti($f['ora_fine']);
        for ($s = 0; $s < 48; $s++) {
            if ($da < ($s + 1) * 30 && $a > $s * 30) {
                $griglia[$g][$s] = true;
            }
        }
    }
    return $griglia;
}

/** 'HH:MM[:SS]' -> minuti dalla mezzanotte */
function minuti($ora): int
{
    return (int) substr((string) $ora, 0, 2) * 60 + (int) substr((string) $ora, 3, 2);
}

// =====================================================================
//  Validazione e salvataggio (usati solo dall'area admin)
// =====================================================================

/** Scheda vuota, usata per il form "nuovo interprete". */
function interprete_vuoto(): array
{
    return [
        'id' => null, 'cognome' => '', 'nome' => '', 'nazione_nascita_id' => '', 'sesso' => '',
        'telefono' => '', 'telefono2' => '', 'email' => '', 'citta' => '', 'indirizzo' => '',
        'lavoro_nome' => '', 'lavoro_indirizzo' => '', 'lavoro_telefono' => '', 'lavoro_contattabile' => 0,
        'note' => '',
        'disp_h24' => 0, 'disp_solo_feriali' => 0, 'disp_note' => '', 'attivo' => 1,
        'lingue' => [], 'fasce' => [],
    ];
}

/**
 * Valida i dati inviati dal form.
 * Restituisce [$dati, $errori]: $dati ha la stessa struttura di una scheda
 * caricata dal database (così il form può essere rivisualizzato), $errori
 * è un elenco di messaggi (vuoto se è tutto corretto).
 */
function valida_interprete(array $in): array
{
    $err = [];
    $d = interprete_vuoto();

    // --- Campi di testo con lunghezza massima -------------------------
    $testi = [
        'cognome' => ['Cognome', 100], 'nome' => ['Nome', 100], 'telefono' => ['Telefono', 30],
        'telefono2' => ['Secondo telefono', 30], 'email' => ['Email', 150], 'citta' => ['Città', 100],
        'indirizzo' => ['Indirizzo', 255], 'note' => ['Note', 5000], 'disp_note' => ['Note sulla disponibilità', 500],
        'lavoro_nome' => ['Posto di lavoro', 150], 'lavoro_indirizzo' => ['Indirizzo del posto di lavoro', 255],
        'lavoro_telefono' => ['Telefono del posto di lavoro', 30],
    ];
    foreach ($testi as $campo => [$etichetta, $max]) {
        $v = input_str($in, $campo, 20000);
        if (mb_strlen($v) > $max) {
            $err[] = "$etichetta: massimo $max caratteri.";
            $v = mb_substr($v, 0, $max);
        }
        $d[$campo] = $v;
    }
    if ($d['cognome'] === '') $err[] = 'Il cognome è obbligatorio.';
    if ($d['nome'] === '')    $err[] = 'Il nome è obbligatorio.';

    $telefoni = ['telefono' => 'Telefono', 'telefono2' => 'Secondo telefono', 'lavoro_telefono' => 'Telefono del posto di lavoro'];
    foreach ($telefoni as $campo => $etichetta) {
        if ($d[$campo] !== '' && !preg_match('~^[0-9+\s()./-]{5,30}$~', $d[$campo])) {
            $err[] = "$etichetta: formato non valido (solo cifre, spazi e + ( ) . / -).";
        }
    }
    if ($d['email'] !== '' && filter_var($d['email'], FILTER_VALIDATE_EMAIL) === false) {
        $err[] = 'Email: indirizzo non valido.';
    }

    // --- Nazione di nascita: deve essere una voce della tabella nazioni ---
    $d['nazione_nascita_id'] = input_str($in, 'nazione_nascita_id', 6);
    if ($d['nazione_nascita_id'] !== '') {
        $st = db()->prepare('SELECT COUNT(*) FROM nazioni WHERE id = ?');
        $st->execute([ctype_digit($d['nazione_nascita_id']) ? (int) $d['nazione_nascita_id'] : 0]);
        if ((int) $st->fetchColumn() === 0) {
            $err[] = 'Nazione di nascita: valore non valido.';
            $d['nazione_nascita_id'] = '';
        }
    }

    $d['sesso'] = input_str($in, 'sesso', 1);
    if ($d['sesso'] !== '' && !isset(SESSI[$d['sesso']])) {
        $err[] = 'Sesso: valore non valido.';
        $d['sesso'] = '';
    }

    $d['disp_h24']          = !empty($in['disp_h24']) ? 1 : 0;
    $d['disp_solo_feriali'] = !empty($in['disp_solo_feriali']) ? 1 : 0;
    $d['attivo']            = !empty($in['attivo']) ? 1 : 0;
    $d['lavoro_contattabile'] = !empty($in['lavoro_contattabile']) ? 1 : 0;

    // --- Lingue -----------------------------------------------------------
    $lingueValide = array_column(elenco_lingue(), 'nome', 'id');
    $viste = [];
    foreach ((isset($in['lingue']) && is_array($in['lingue'])) ? $in['lingue'] : [] as $riga) {
        if (!is_array($riga)) {
            continue;
        }
        $idLingua = input_str($riga, 'lingua_id', 10);
        $livello  = input_str($riga, 'livello', 20);
        if ($idLingua === '') {
            continue; // riga lasciata vuota
        }
        $d['lingue'][] = ['lingua_id' => $idLingua, 'livello' => $livello];
        if (!ctype_digit($idLingua) || !isset($lingueValide[(int) $idLingua])) {
            $err[] = 'Lingue: è stata indicata una lingua inesistente.';
        } elseif (isset($viste[(int) $idLingua])) {
            $err[] = 'Lingue: "' . $lingueValide[(int) $idLingua] . '" è indicata più volte.';
        } else {
            $viste[(int) $idLingua] = true;
        }
        if (!isset(LIVELLI[$livello])) {
            $err[] = 'Lingue: livello non valido.';
        }
    }
    if (count($d['lingue']) > 30) {
        $err[] = 'Lingue: troppe righe.';
    }

    // --- Fasce orarie -------------------------------------------------------
    $perGiorno = [];
    foreach ((isset($in['fasce']) && is_array($in['fasce'])) ? $in['fasce'] : [] as $riga) {
        if (!is_array($riga)) {
            continue;
        }
        $giorno = input_str($riga, 'giorno_settimana', 2);
        $inizio = input_str($riga, 'ora_inizio', 8);
        $fine   = input_str($riga, 'ora_fine', 8);
        if ($inizio === '' && $fine === '') {
            continue; // riga lasciata vuota
        }
        $inizio = substr($inizio, 0, 5);
        $fine   = substr($fine, 0, 5);
        $d['fasce'][] = ['giorno_settimana' => $giorno, 'ora_inizio' => $inizio, 'ora_fine' => $fine];
        if (count($d['fasce']) > MAX_FASCE) {
            $err[] = 'Fasce orarie: troppe righe (massimo ' . MAX_FASCE . ').';
            break;
        }
        $n = count($d['fasce']);
        if (!ctype_digit($giorno) || !isset(GIORNI[(int) $giorno])) {
            $err[] = "Fascia n. $n: giorno della settimana non valido.";
            continue;
        }
        if (!preg_match(REGEX_ORA, $inizio) || !preg_match(REGEX_ORA, $fine)) {
            $err[] = "Fascia n. $n (" . GIORNI[(int) $giorno] . '): indicare gli orari nel formato HH:MM.';
            continue;
        }
        if ($fine <= $inizio) {
            $err[] = "Fascia n. $n (" . GIORNI[(int) $giorno] . "): l'ora di fine deve essere successiva all'ora di inizio.";
            continue;
        }
        if ($d['disp_solo_feriali'] && (int) $giorno >= 6) {
            $err[] = "Fascia n. $n: con \"solo giorni feriali\" non si possono indicare fasce di sabato o domenica.";
        }
        foreach ($perGiorno[(int) $giorno] ?? [] as [$i2, $f2]) {
            if ($inizio < $f2 && $fine > $i2) {
                $err[] = "Fascia n. $n (" . GIORNI[(int) $giorno] . "): si sovrappone a un'altra fascia ($i2-$f2).";
                break;
            }
        }
        $perGiorno[(int) $giorno][] = [$inizio, $fine];
    }

    return [$d, array_values(array_unique($err))];
}

/**
 * Inserisce o aggiorna una scheda (dati già validati) in transazione.
 * Restituisce l'id dell'interprete.
 */
function salva_interprete(array $d, ?int $id = null): int
{
    $nullo = fn ($v) => ($v === '' || $v === null) ? null : $v;
    $campi = [
        'cognome'            => $d['cognome'],
        'nome'               => $d['nome'],
        'nazione_nascita_id' => $nullo($d['nazione_nascita_id']),
        'sesso'              => $nullo($d['sesso']),
        'telefono'           => $nullo($d['telefono']),
        'telefono2'          => $nullo($d['telefono2']),
        'email'              => $nullo($d['email']),
        'citta'              => $nullo($d['citta']),
        'indirizzo'          => $nullo($d['indirizzo']),
        'lavoro_nome'        => $nullo($d['lavoro_nome']),
        'lavoro_indirizzo'   => $nullo($d['lavoro_indirizzo']),
        'lavoro_telefono'    => $nullo($d['lavoro_telefono']),
        'lavoro_contattabile' => (int) $d['lavoro_contattabile'],
        'note'               => $nullo($d['note']),
        'disp_h24'           => (int) $d['disp_h24'],
        'disp_solo_feriali'  => (int) $d['disp_solo_feriali'],
        'disp_note'          => $nullo($d['disp_note']),
        'attivo'             => (int) $d['attivo'],
    ];
    $colonne = array_keys($campi); // nomi fissi definiti qui sopra, mai dall'input

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id === null) {
            $sql = 'INSERT INTO interpreti (' . implode(', ', $colonne) . ') VALUES ('
                 . implode(', ', array_fill(0, count($colonne), '?')) . ')';
            $pdo->prepare($sql)->execute(array_values($campi));
            $id = (int) $pdo->lastInsertId();
        } else {
            $sql = 'UPDATE interpreti SET ' . implode(' = ?, ', $colonne) . ' = ?, modificato_il = NOW() WHERE id = ?';
            $pdo->prepare($sql)->execute([...array_values($campi), $id]);
            $pdo->prepare('DELETE FROM interprete_lingua WHERE interprete_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM disponibilita_fasce WHERE interprete_id = ?')->execute([$id]);
        }

        $st = $pdo->prepare('INSERT INTO interprete_lingua (interprete_id, lingua_id, livello) VALUES (?, ?, ?)');
        foreach ($d['lingue'] as $l) {
            $st->execute([$id, (int) $l['lingua_id'], $l['livello']]);
        }
        $st = $pdo->prepare('INSERT INTO disponibilita_fasce (interprete_id, giorno_settimana, ora_inizio, ora_fine) VALUES (?, ?, ?, ?)');
        foreach ($d['fasce'] as $f) {
            $st->execute([$id, (int) $f['giorno_settimana'], $f['ora_inizio'], $f['ora_fine']]);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    return $id;
}

/** Elimina una scheda (lingue e fasce spariscono per ON DELETE CASCADE). */
function elimina_interprete(int $id): bool
{
    $st = db()->prepare('DELETE FROM interpreti WHERE id = ?');
    $st->execute([$id]);
    return $st->rowCount() > 0;
}
