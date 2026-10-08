-- =====================================================================
--  Dati di prova (OPZIONALI): 6 interpreti fittizi, nomi inventati.
--  Import (dopo schema.sql):  mysql -u UTENTE -p NOME_DATABASE < sql/seed.sql
-- =====================================================================

SET NAMES utf8mb4;

-- 1) Disponibile 24h su 24, tutti i giorni
INSERT INTO interpreti (cognome, nome, nazione_nascita_id, sesso, telefono, telefono2, email, citta, indirizzo,
                        note, disp_h24, disp_solo_feriali, disp_note, attivo)
VALUES ('Benali', 'Samira', (SELECT id FROM nazioni WHERE nome = 'Marocco'), 'F', '+39 333 0000001', NULL, 'samira.benali@example.com', 'Milano',
        'Via dei Tigli 14', 'Esperienza in ambito sanitario e giudiziario.', 1, 0,
        'Reperibile anche di notte per urgenze.', 1);
SET @i := LAST_INSERT_ID();
INSERT INTO interprete_lingua (interprete_id, lingua_id, livello) VALUES
    (@i, (SELECT id FROM lingue WHERE nome = 'Arabo'),    'madrelingua'),
    (@i, (SELECT id FROM lingue WHERE nome = 'Francese'), 'ottimo'),
    (@i, (SELECT id FROM lingue WHERE nome = 'Italiano'), 'ottimo');

-- 2) Lun-Ven, stesso orario 08:00-18:00
INSERT INTO interpreti (cognome, nome, nazione_nascita_id, sesso, telefono, email, citta,
                        note, disp_h24, disp_solo_feriali, disp_note, attivo)
VALUES ('Ferraresi', 'Tommaso', (SELECT id FROM nazioni WHERE nome = 'Italia'), 'M', '+39 333 0000002', 'tommaso.ferraresi@example.com', 'Bologna',
        'Interprete di conferenza.', 0, 1, 'Solo su preavviso di 48h.', 1);
SET @i := LAST_INSERT_ID();
INSERT INTO interprete_lingua (interprete_id, lingua_id, livello) VALUES
    (@i, (SELECT id FROM lingue WHERE nome = 'Italiano'), 'madrelingua'),
    (@i, (SELECT id FROM lingue WHERE nome = 'Inglese'),  'ottimo'),
    (@i, (SELECT id FROM lingue WHERE nome = 'Tedesco'),  'buono');
INSERT INTO disponibilita_fasce (interprete_id, giorno_settimana, ora_inizio, ora_fine) VALUES
    (@i, 1, '08:00', '18:00'), (@i, 2, '08:00', '18:00'), (@i, 3, '08:00', '18:00'),
    (@i, 4, '08:00', '18:00'), (@i, 5, '08:00', '18:00');

-- 3) Fasce spezzate (mattina e pomeriggio) e sabato mattina
INSERT INTO interpreti (cognome, nome, nazione_nascita_id, sesso, telefono, telefono2, email, citta, indirizzo,
                        note, disp_h24, disp_solo_feriali, disp_note, attivo)
VALUES ('Popescu', 'Ioana', (SELECT id FROM nazioni WHERE nome = 'Romania'), 'F', '+39 333 0000003', '051 000003', 'ioana.popescu@example.com', 'Bologna',
        'Via del Porto 3', NULL, 0, 0, NULL, 1);
SET @i := LAST_INSERT_ID();
INSERT INTO interprete_lingua (interprete_id, lingua_id, livello) VALUES
    (@i, (SELECT id FROM lingue WHERE nome = 'Rumeno'),   'madrelingua'),
    (@i, (SELECT id FROM lingue WHERE nome = 'Italiano'), 'ottimo'),
    (@i, (SELECT id FROM lingue WHERE nome = 'Inglese'),  'base');
INSERT INTO disponibilita_fasce (interprete_id, giorno_settimana, ora_inizio, ora_fine) VALUES
    (@i, 1, '08:00', '12:00'), (@i, 1, '15:00', '19:00'),
    (@i, 2, '08:00', '12:00'), (@i, 2, '15:00', '19:00'),
    (@i, 4, '08:00', '12:00'), (@i, 4, '15:00', '19:00'),
    (@i, 6, '09:00', '13:00');

-- 4) Solo sera e fine settimana
INSERT INTO interpreti (cognome, nome, nazione_nascita_id, sesso, telefono, email, citta,
                        note, disp_h24, disp_solo_feriali, disp_note, attivo)
VALUES ('Wang', 'Li', (SELECT id FROM nazioni WHERE nome = 'Cina'), 'F', '+39 333 0000004', 'li.wang@example.com', 'Prato',
        'Preferisce incarichi da remoto.', 0, 0, 'Di giorno lavora, contattare dopo le 18.', 1);
SET @i := LAST_INSERT_ID();
INSERT INTO interprete_lingua (interprete_id, lingua_id, livello) VALUES
    (@i, (SELECT id FROM lingue WHERE nome = 'Cinese'),   'madrelingua'),
    (@i, (SELECT id FROM lingue WHERE nome = 'Italiano'), 'buono'),
    (@i, (SELECT id FROM lingue WHERE nome = 'Inglese'),  'buono');
INSERT INTO disponibilita_fasce (interprete_id, giorno_settimana, ora_inizio, ora_fine) VALUES
    (@i, 1, '18:30', '22:00'), (@i, 3, '18:30', '22:00'), (@i, 5, '18:30', '22:00'),
    (@i, 6, '09:00', '20:00'), (@i, 7, '10:00', '16:00');

-- 5) H24 ma solo nei giorni feriali
INSERT INTO interpreti (cognome, nome, nazione_nascita_id, sesso, telefono, email, citta,
                        note, disp_h24, disp_solo_feriali, disp_note, attivo)
VALUES ('Hoxha', 'Arben', (SELECT id FROM nazioni WHERE nome = 'Albania'), 'M', '+39 333 0000005', 'arben.hoxha@example.com', 'Milano',
        NULL, 1, 1, NULL, 1);
SET @i := LAST_INSERT_ID();
INSERT INTO interprete_lingua (interprete_id, lingua_id, livello) VALUES
    (@i, (SELECT id FROM lingue WHERE nome = 'Albanese'), 'madrelingua'),
    (@i, (SELECT id FROM lingue WHERE nome = 'Italiano'), 'ottimo');

-- 6) Scheda non attiva
INSERT INTO interpreti (cognome, nome, nazione_nascita_id, sesso, telefono, email, citta,
                        note, disp_h24, disp_solo_feriali, disp_note, attivo)
VALUES ('Volkova', 'Irina', (SELECT id FROM nazioni WHERE nome = 'Russia'), 'F', '+39 333 0000006', 'irina.volkova@example.com', 'Torino',
        'Momentaneamente non disponibile (trasferita all''estero).', 0, 0, NULL, 0);
SET @i := LAST_INSERT_ID();
INSERT INTO interprete_lingua (interprete_id, lingua_id, livello) VALUES
    (@i, (SELECT id FROM lingue WHERE nome = 'Russo'),    'madrelingua'),
    (@i, (SELECT id FROM lingue WHERE nome = 'Italiano'), 'buono'),
    (@i, (SELECT id FROM lingue WHERE nome = 'Spagnolo'), 'base');
INSERT INTO disponibilita_fasce (interprete_id, giorno_settimana, ora_inizio, ora_fine) VALUES
    (@i, 2, '14:00', '18:00'), (@i, 4, '14:00', '18:00');

-- Riferimenti del posto di lavoro (esempi)
UPDATE interpreti SET lavoro_nome = 'Studio Traduzioni Aurora', lavoro_indirizzo = 'Via Indipendenza 20, Bologna',
       lavoro_telefono = '051 000010', lavoro_contattabile = 1
 WHERE cognome = 'Ferraresi' AND nome = 'Tommaso';
UPDATE interpreti SET lavoro_nome = 'Import-Export Levante', lavoro_indirizzo = 'Via Pistoiese 8, Prato',
       lavoro_telefono = '0574 000020', lavoro_contattabile = 0
 WHERE cognome = 'Wang' AND nome = 'Li';
