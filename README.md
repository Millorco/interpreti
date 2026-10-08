# Database interpreti

Applicazione web in PHP puro (PDO) e MySQL/MariaDB per gestire un archivio di interpreti:
anagrafica, contatti, lingue con livello, disponibilità settimanale, ricerca per lingua
e scheda stampabile. Nessun framework, nessun Composer, nessun build step.

## Ruoli

| Ruolo | Come accede | Cosa può fare |
|---|---|---|
| **Consultazione** | Senza password, **solo** da IP della rete LAN (default `192.168.10.0/24`) | Cercare e leggere le schede |
| **Admin** | Username + password (anche dentro la LAN) | Tutto: schede, lingue, cambio password |

Chi arriva da un IP esterno alla LAN e non è loggato come admin riceve **403**.

## Requisiti

- PHP 8.0 o superiore con estensioni `pdo_mysql` e `mbstring`
- MySQL 5.7+ oppure MariaDB 10.3+
- Apache 2.4 con `AllowOverride All` (per i file `.htaccess`)

## Installazione

Tutto ciò che va caricato sul server è nella cartella **`www/`**: se ne copia il *contenuto*
(compreso il file nascosto `.htaccess`) nella cartella del sito, ad esempio `/interpreti/`.

### 1. Database

Serve un database già esistente (creato dal pannello dell'hosting, oppure a mano):

```sql
CREATE DATABASE interpreti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'interpreti_app'@'localhost' IDENTIFIED BY 'una-password-robusta';
GRANT SELECT, INSERT, UPDATE, DELETE ON interpreti.* TO 'interpreti_app'@'localhost';
```

Poi importare **`www/database.sql`**:

- **phpMyAdmin**: selezionare il database nella colonna di sinistra, aprire la scheda **Importa**,
  scegliere `database.sql` e premere **Esegui** (codifica `utf-8`, formato `SQL`).
- **riga di comando**: `mysql -u root -p interpreti < www/database.sql`

Il file non crea né seleziona alcun database e non cancella nulla: crea le tabelle solo se mancano
e aggiunge lingue, nazioni e utente `admin` solo se non ci sono già. Si può quindi reimportare
senza perdere dati, ma **non aggiorna** la struttura di tabelle già esistenti.

`dati_di_prova.sql` (facoltativo, nella radice del progetto) aggiunge 6 interpreti fittizi: si
importa allo stesso modo, dopo `database.sql`.

L'utente del database usato dall'applicazione ha bisogno solo di `SELECT, INSERT, UPDATE, DELETE`
(per l'import servono anche `CREATE`, `INDEX` e `REFERENCES`).

### 2. Configurazione

Copiare `www/includes/config.sample.php` in `www/includes/config.php` (sul server:
`includes/config.php`) e compilarlo:

| Chiave | Significato |
|---|---|
| `db` | host, porta, nome database, utente, password |
| `rete_lan` | Rete in CIDR ammessa alla consultazione. Stringa o array, es. `['192.168.10.0/24', '127.0.0.1/32']` |
| `admin_login_fuori_lan` | `true` (default): l'admin può fare login da ovunque. `false`: login e sessione admin solo dalla LAN |
| `trusted_proxies` | IP/CIDR dei reverse proxy fidati (vedi sotto). Vuoto = `X-Forwarded-For` ignorato |
| `session_timeout` | Secondi di inattività dopo cui la sessione admin scade (default 1800) |
| `login_max_tentativi`, `login_blocco_minuti` | Blocco temporaneo dopo N login falliti (default 5 errori / 15 minuti) |
| `debug` | `true` mostra i dettagli degli errori a video: **solo in sviluppo** |

`config.php` è in `.gitignore`. Se si vuole tenerlo del tutto fuori dall'albero del sito, salvarlo
altrove e indicarne il percorso con la variabile d'ambiente `INTERPRETI_CONFIG`
(in Apache: `SetEnv INTERPRETI_CONFIG /percorso/privato/interpreti-config.php`).

### 3. Primo amministratore

`database.sql` crea già un amministratore iniziale:

- username: `admin`
- password: `Interpreti`

**Cambiare la password subito dopo il primo accesso** (menu "Password"): quella iniziale è scritta
nel codice e va considerata pubblica.

Per creare altri amministratori, o reimpostare una password, da riga di comando nella cartella
dell'applicazione (`www/` in locale):

```bash
php create_admin.php mario
```

Lo script chiede la password due volte (minimo 10 caratteri) e salva solo l'hash (Argon2id, o bcrypt
se Argon2 non è disponibile). Rieseguendolo con uno username già
esistente la password viene reimpostata (utile se l'admin l'ha dimenticata). Lo script funziona solo
da CLI: via web risponde 403.

Senza accesso SSH: eseguire lo script in locale puntando `config.php` al database remoto, oppure
creare l'hash in locale con `php -r "echo password_hash('...', PASSWORD_DEFAULT);"` e inserirlo a mano
nella tabella `utenti`.

### 4. Deploy su Apache

**Consigliato:** la document root punta alla cartella `public/`, così `includes/`, `database.sql` e
`create_admin.php` restano fuori dal web.

```apache
<VirtualHost *:80>
    ServerName interpreti.example.lan
    DocumentRoot /var/www/interpreti/public
    <Directory /var/www/interpreti/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Hosting condiviso senza controllo sulla document root:** caricare il contenuto di `www/` in una
sottocartella (es. `/interpreti/`). I file `.htaccess` inclusi negano l'accesso a `includes/`, ai file
`.sql` e a `create_admin.php`, e la radice rimanda a `public/`; l'applicazione sarà raggiungibile
su `/interpreti/` (che porta a `/interpreti/public/`). Attenzione a copiare anche il file `.htaccess`
della radice: inizia con un punto e molti programmi lo nascondono. Dopo il caricamento verificare che
`https://sito/interpreti/includes/config.php` e `https://sito/interpreti/database.sql` rispondano
**403**; una volta importato, `database.sql` si può anche cancellare dal server.

In produzione usare HTTPS: il cookie di sessione riceve automaticamente il flag `Secure`.

## Verificare il filtro IP

1. Da un PC della LAN (es. `192.168.10.20`), senza login: l'elenco deve aprirsi in sola lettura
   (in alto a destra compare "Consultazione").
2. Da un IP esterno (es. telefono in rete mobile, o `curl` da un altro server):
   ```bash
   curl -s -o /dev/null -w "%{http_code}\n" https://sito/index.php                                   # 403
   curl -s -o /dev/null -w "%{http_code}\n" -H "X-Forwarded-For: 192.168.10.5" https://sito/index.php # ancora 403
   ```
   La seconda riga conferma che l'header falsificato non viene creduto.
3. Ogni accesso negato viene scritto nel log degli errori PHP con l'IP rilevato
   (`Interpreti: accesso negato in consultazione da IP ...`): è il modo più rapido per capire quale
   indirizzo "vede" l'applicazione.

Attenzione: `127.0.0.1` **non** fa parte della LAN. Per provare in locale sullo stesso server
aggiungere `'127.0.0.1/32'` a `rete_lan`.

### Server dietro reverse proxy

Dietro un proxy (nginx, HAProxy, bilanciatore, Cloudflare...) `REMOTE_ADDR` è sempre l'IP del proxy.
Le conseguenze sono due, entrambe da evitare:

- se il proxy è in LAN, **tutti** risulterebbero in LAN → consultazione aperta al mondo;
- se il proxy è fuori LAN, nessuno potrebbe consultare.

Soluzione: inserire l'IP del proxy in `trusted_proxies`, ad esempio

```php
'trusted_proxies' => ['192.168.10.2'],
```

Solo in questo caso l'applicazione legge `X-Forwarded-For`, scorrendolo da destra e prendendo il
primo indirizzo che non è un proxy fidato. Se l'header manca o è malformato l'accesso in consultazione
viene negato. Il proxy deve **sovrascrivere o accodare** l'header (in nginx:
`proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;`) e il server applicativo non deve essere
raggiungibile scavalcando il proxy. Con un proxy fidato viene letto anche `X-Forwarded-Proto` per il
flag `Secure` del cookie.

## Struttura

```
www/                      DA CARICARE SUL SERVER (il contenuto, non la cartella)
  .htaccess               protegge i file non pubblici e rimanda a public/
  database.sql            struttura e dati iniziali, da importare (anche da phpMyAdmin)
  create_admin.php        creazione/reset admin (solo CLI)
  public/                 parte visibile (document root consigliata)
    index.php             home: ricerca per lingua ed elenco dei risultati
    scheda.php            scheda di dettaglio stampabile
    login.php, logout.php
    admin/                interprete_form.php, interprete_elimina.php, lingue.php, password.php
    assets/css/style.css, assets/js/app.js
  includes/               (non pubblica)
    bootstrap.php         configurazione, errori, header di sicurezza, sessione
    auth.php              controllo IP/CIDR, login, require_view_access(), require_admin()
    db.php, helpers.php   PDO; e(), url(), CSRF, messaggi
    interpreti.php        ricerca, validazione, salvataggio, disponibilità
    header.php, footer.php, config.sample.php
dati_di_prova.sql         6 interpreti fittizi (facoltativo, non va sul server)
README.md
```

## Note d'uso

- **Disponibilità.** "24h su 24" ha la precedenza: le fasce orarie restano salvate ma sono ignorate
  in scheda e nelle ricerche. "Solo giorni feriali" esclude l'interprete dalle ricerche su sabato e
  domenica. Una fascia non può attraversare la mezzanotte: usare `23:59` come fine giornata e, se
  serve, una seconda fascia dal giorno dopo.
- **Home e ricerca.** La home mostra solo il riquadro di ricerca per lingua (una sola, o "Tutte"):
  l'elenco degli interpreti compare dopo aver premuto "Cerca".
- **Nazione di nascita.** Si sceglie da un menu a tendina alimentato dalla tabella `nazioni`
  (197 voci, caricate da `database.sql`). Per aggiungere o rinominare una voce si interviene
  direttamente sulla tabella.
- **Schede non attive.** L'admin vede in elenco tutte le schede (lo stato è indicato nell'ultima
  colonna, ordinabile); in consultazione compaiono solo quelle attive.
- **Filtri via URL.** Gli altri filtri restano disponibili lato server come parametri dell'indirizzo:
  `q` (testo su cognome/nome/città), `giorno` (1-7), `ora` (HH:MM), `h24=1`,
  `stato` (`attivi`/`tutti`/`inattivi`). Es. `index.php?giorno=2&ora=14:30`.
- **Blocco login.** Dopo 5 errori dallo stesso IP il login è sospeso per 15 minuti; lo stesso avviene
  dopo 15 errori sullo stesso username da IP diversi. Per sbloccare subito: `DELETE FROM login_tentativi;`
- **Eliminare o disattivare.** Togliendo la spunta "Scheda attiva" la scheda resta in archivio ma
  non è più visibile in consultazione; "Elimina" la cancella definitivamente.

## Sicurezza, in breve

Query solo preparate (PDO senza emulazione), escape di ogni output, token CSRF su tutti i POST,
eliminazioni solo via POST, controllo del ruolo lato server su ogni pagina e azione admin,
`session_regenerate_id` al login, cookie `HttpOnly` + `SameSite=Lax` (+ `Secure` su HTTPS), timeout di
inattività, limite ai tentativi di login, header `X-Frame-Options`, `X-Content-Type-Options`,
`Referrer-Policy` e una CSP senza script o stili inline. Gli errori vanno solo nel log PHP.
