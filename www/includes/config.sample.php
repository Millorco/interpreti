<?php
/**
 * Configurazione dell'applicazione.
 *
 * Copiare questo file in  includes/config.php  e compilare i valori.
 * In alternativa, per tenerlo fuori dall'albero del sito, salvarlo altrove e
 * indicarne il percorso con la variabile d'ambiente INTERPRETI_CONFIG
 * (es. in Apache:  SetEnv INTERPRETI_CONFIG /percorso/privato/interpreti-config.php).
 *
 * NON mettere config.php sotto controllo di versione.
 */
return [
    // --- Database ------------------------------------------------------
    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'interpreti',
        'user'    => 'interpreti_app',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // --- Accesso in consultazione (sola lettura, senza password) --------
    // Rete (o elenco di reti) in notazione CIDR da cui è consentita la
    // consultazione. Si può indicare una stringa o un array, es.:
    //   'rete_lan' => ['192.168.10.0/24', '127.0.0.1/32'],
	//'rete_lan' => ['0.0.0.0/0', '::/0'],
    'rete_lan' => '192.168.10.0/24',

    // true  = l'admin può fare login (con password) anche da fuori LAN
    // false = pagina di login e sessione admin valide solo dalla LAN
    'admin_login_fuori_lan' => true,

    // Reverse proxy fidati (IP singoli o CIDR). SOLO se REMOTE_ADDR è in
    // questo elenco viene letto l'header X-Forwarded-For. Lasciare vuoto
    // se il server non è dietro un proxy.
    'trusted_proxies' => [],

    // --- Sessione admin e login ------------------------------------------
    'session_timeout'      => 1800, // secondi di inattività prima del logout
    'login_max_tentativi'  => 5,    // errori consentiti prima del blocco
    'login_blocco_minuti'  => 15,   // durata del blocco / finestra di conteggio

    // --- Varie ---------------------------------------------------------
    'timezone'       => 'Europe/Rome',
    'righe_per_pagina' => 25,
    // true mostra i dettagli degli errori a video: usare SOLO in sviluppo.
    'debug'          => false,
];
