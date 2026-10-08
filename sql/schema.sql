-- =====================================================================
--  Database interpreti - schema
--  MySQL 5.7+ / MariaDB 10.3+   (utf8mb4, InnoDB)
--
--  Import:  mysql -u UTENTE -p NOME_DATABASE < sql/schema.sql
--  Il database va creato prima (vedi README.md).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Utenti amministratori (nessuna password di default: usare create_admin.php)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS utenti (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username        VARCHAR(50)  NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    creato_il       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_accesso  DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_utenti_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tentativi di login falliti (per il blocco temporaneo)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_tentativi (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip         VARCHAR(45) NOT NULL,
    username   VARCHAR(50) NOT NULL,
    creato_il  DATETIME    NOT NULL,
    PRIMARY KEY (id),
    KEY idx_tentativi_ip (ip, creato_il),
    KEY idx_tentativi_username (username, creato_il)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Nazioni (elenco fisso, usato per la nazione di nascita)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS nazioni (
    id    SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome  VARCHAR(80) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_nazioni_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Interpreti
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS interpreti (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cognome            VARCHAR(100) NOT NULL,
    nome               VARCHAR(100) NOT NULL,
    nazione_nascita_id SMALLINT UNSIGNED NULL,
    sesso              ENUM('M','F','X') NULL,
    telefono           VARCHAR(30)  NULL,
    telefono2          VARCHAR(30)  NULL,
    email              VARCHAR(150) NULL,
    citta              VARCHAR(100) NULL,
    indirizzo          VARCHAR(255) NULL,
    -- Riferimenti del posto di lavoro
    lavoro_nome        VARCHAR(150) NULL,
    lavoro_indirizzo   VARCHAR(255) NULL,
    lavoro_telefono    VARCHAR(30)  NULL,
    lavoro_contattabile TINYINT(1)  NOT NULL DEFAULT 0,
    note               TEXT         NULL,
    -- Disponibilità "generale" (le fasce orarie sono in disponibilita_fasce)
    disp_h24           TINYINT(1)   NOT NULL DEFAULT 0,
    disp_solo_feriali  TINYINT(1)   NOT NULL DEFAULT 0,
    disp_note          VARCHAR(500) NULL,
    attivo             TINYINT(1)   NOT NULL DEFAULT 1,
    creato_il          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modificato_il      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_interpreti_nome (cognome, nome),
    KEY idx_interpreti_citta (citta),
    KEY idx_interpreti_attivo (attivo),
    KEY idx_interpreti_nazione (nazione_nascita_id),
    CONSTRAINT fk_interpreti_nazione FOREIGN KEY (nazione_nascita_id)
        REFERENCES nazioni (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Lingue
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lingue (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome        VARCHAR(80)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lingue_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Lingue parlate da ciascun interprete (N:M con livello)
--   - eliminando un interprete spariscono le sue lingue (CASCADE)
--   - una lingua in uso non può essere eliminata (RESTRICT)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS interprete_lingua (
    interprete_id  INT UNSIGNED NOT NULL,
    lingua_id      INT UNSIGNED NOT NULL,
    livello        ENUM('madrelingua','ottimo','buono','base') NOT NULL DEFAULT 'buono',
    PRIMARY KEY (interprete_id, lingua_id),
    KEY idx_il_lingua (lingua_id),
    CONSTRAINT fk_il_interprete FOREIGN KEY (interprete_id)
        REFERENCES interpreti (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_il_lingua FOREIGN KEY (lingua_id)
        REFERENCES lingue (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Fasce orarie di disponibilità (più fasce per lo stesso giorno ammesse)
--   giorno_settimana: 1 = lunedì ... 7 = domenica
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS disponibilita_fasce (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    interprete_id     INT UNSIGNED NOT NULL,
    giorno_settimana  TINYINT UNSIGNED NOT NULL,
    ora_inizio        TIME NOT NULL,
    ora_fine          TIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fasce_ricerca (giorno_settimana, ora_inizio, ora_fine),
    KEY idx_fasce_interprete (interprete_id),
    CONSTRAINT fk_fasce_interprete FOREIGN KEY (interprete_id)
        REFERENCES interpreti (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_fasce_giorno CHECK (giorno_settimana BETWEEN 1 AND 7),
    CONSTRAINT chk_fasce_orari CHECK (ora_fine > ora_inizio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Lingue di esempio
-- ---------------------------------------------------------------------
INSERT IGNORE INTO lingue (nome) VALUES
    ('Italiano'), ('Inglese'), ('Francese'), ('Tedesco'), ('Spagnolo'),
    ('Arabo'), ('Cinese'), ('Russo'), ('Rumeno'), ('Albanese');

-- ---------------------------------------------------------------------
-- Nazioni del mondo (Stati membri ONU, più Città del Vaticano, Palestina,
-- Kosovo e Taiwan)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO nazioni (nome) VALUES
    ('Afghanistan'), ('Albania'), ('Algeria'), ('Andorra'),
    ('Angola'), ('Antigua e Barbuda'), ('Arabia Saudita'), ('Argentina'),
    ('Armenia'), ('Australia'), ('Austria'), ('Azerbaigian'),
    ('Bahamas'), ('Bahrein'), ('Bangladesh'), ('Barbados'),
    ('Belgio'), ('Belize'), ('Benin'), ('Bhutan'),
    ('Bielorussia'), ('Bolivia'), ('Bosnia ed Erzegovina'), ('Botswana'),
    ('Brasile'), ('Brunei'), ('Bulgaria'), ('Burkina Faso'),
    ('Burundi'), ('Cambogia'), ('Camerun'), ('Canada'),
    ('Capo Verde'), ('Ciad'), ('Cile'), ('Cina'),
    ('Cipro'), ('Città del Vaticano'), ('Colombia'), ('Comore'),
    ('Congo (Repubblica del)'), ('Congo (Repubblica Democratica del)'), ('Corea del Nord'), ('Corea del Sud'),
    ('Costa d''Avorio'), ('Costa Rica'), ('Croazia'), ('Cuba'),
    ('Danimarca'), ('Dominica'), ('Ecuador'), ('Egitto'),
    ('El Salvador'), ('Emirati Arabi Uniti'), ('Eritrea'), ('Estonia'),
    ('Eswatini'), ('Etiopia'), ('Figi'), ('Filippine'),
    ('Finlandia'), ('Francia'), ('Gabon'), ('Gambia'),
    ('Georgia'), ('Germania'), ('Ghana'), ('Giamaica'),
    ('Giappone'), ('Gibuti'), ('Giordania'), ('Grecia'),
    ('Grenada'), ('Guatemala'), ('Guinea'), ('Guinea-Bissau'),
    ('Guinea Equatoriale'), ('Guyana'), ('Haiti'), ('Honduras'),
    ('India'), ('Indonesia'), ('Iran'), ('Iraq'),
    ('Irlanda'), ('Islanda'), ('Isole Marshall'), ('Isole Salomone'),
    ('Israele'), ('Italia'), ('Kazakistan'), ('Kenya'),
    ('Kirghizistan'), ('Kiribati'), ('Kosovo'), ('Kuwait'),
    ('Laos'), ('Lesotho'), ('Lettonia'), ('Libano'),
    ('Liberia'), ('Libia'), ('Liechtenstein'), ('Lituania'),
    ('Lussemburgo'), ('Macedonia del Nord'), ('Madagascar'), ('Malawi'),
    ('Malaysia'), ('Maldive'), ('Mali'), ('Malta'),
    ('Marocco'), ('Mauritania'), ('Mauritius'), ('Messico'),
    ('Micronesia'), ('Moldavia'), ('Monaco'), ('Mongolia'),
    ('Montenegro'), ('Mozambico'), ('Myanmar'), ('Namibia'),
    ('Nauru'), ('Nepal'), ('Nicaragua'), ('Niger'),
    ('Nigeria'), ('Norvegia'), ('Nuova Zelanda'), ('Oman'),
    ('Paesi Bassi'), ('Pakistan'), ('Palau'), ('Palestina'),
    ('Panama'), ('Papua Nuova Guinea'), ('Paraguay'), ('Perù'),
    ('Polonia'), ('Portogallo'), ('Qatar'), ('Regno Unito'),
    ('Repubblica Ceca'), ('Repubblica Centrafricana'), ('Repubblica Dominicana'), ('Romania'),
    ('Ruanda'), ('Russia'), ('Saint Kitts e Nevis'), ('Saint Lucia'),
    ('Saint Vincent e Grenadine'), ('Samoa'), ('San Marino'), ('São Tomé e Príncipe'),
    ('Senegal'), ('Serbia'), ('Seychelles'), ('Sierra Leone'),
    ('Singapore'), ('Siria'), ('Slovacchia'), ('Slovenia'),
    ('Somalia'), ('Spagna'), ('Sri Lanka'), ('Stati Uniti d''America'),
    ('Sudafrica'), ('Sudan'), ('Sudan del Sud'), ('Suriname'),
    ('Svezia'), ('Svizzera'), ('Tagikistan'), ('Taiwan'),
    ('Tanzania'), ('Thailandia'), ('Timor Est'), ('Togo'),
    ('Tonga'), ('Trinidad e Tobago'), ('Tunisia'), ('Turchia'),
    ('Turkmenistan'), ('Tuvalu'), ('Ucraina'), ('Uganda'),
    ('Ungheria'), ('Uruguay'), ('Uzbekistan'), ('Vanuatu'),
    ('Venezuela'), ('Vietnam'), ('Yemen'), ('Zambia'),
    ('Zimbabwe');
