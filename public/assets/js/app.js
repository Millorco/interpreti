/*
 * Database interpreti - JavaScript dell'interfaccia (vanilla, nessuna dipendenza).
 * Tutto passa da attributi data-*: nessuno script inline (richiesto dalla CSP).
 */
(function () {
    'use strict';

    // --- Conferma prima di inviare i form "pericolosi" (eliminazioni) ------
    document.addEventListener('submit', function (ev) {
        var messaggio = ev.target.getAttribute('data-conferma');
        if (messaggio && !window.confirm(messaggio)) {
            ev.preventDefault();
        }
    });

    // --- Righe dinamiche (lingue e fasce orarie) ---------------------------
    /**
     * Aggiunge una riga al gruppo indicato clonando il <template id="tpl-GRUPPO">.
     * "valori" (facoltativo) imposta i campi marcati con data-campo.
     */
    function aggiungiRiga(gruppo, valori) {
        var contenitore = document.getElementById('righe-' + gruppo);
        var modello = document.getElementById('tpl-' + gruppo);
        if (!contenitore || !modello) {
            return null;
        }
        var indice = parseInt(contenitore.getAttribute('data-prossimo') || '0', 10);
        contenitore.setAttribute('data-prossimo', String(indice + 1));

        var riga = modello.content.firstElementChild.cloneNode(true);
        riga.querySelectorAll('[name]').forEach(function (campo) {
            campo.name = campo.name.replace('__i__', String(indice));
        });
        if (valori) {
            Object.keys(valori).forEach(function (nome) {
                var campo = riga.querySelector('[data-campo="' + nome + '"]');
                if (campo) {
                    campo.value = valori[nome];
                }
            });
        }
        contenitore.appendChild(riga);
        return riga;
    }

    document.addEventListener('click', function (ev) {
        var el = ev.target.closest('[data-azione]');
        if (!el) {
            return;
        }
        switch (el.getAttribute('data-azione')) {
            case 'stampa':
                window.print();
                break;

            case 'aggiungi-riga': {
                var nuova = aggiungiRiga(el.getAttribute('data-gruppo'));
                var primo = nuova && nuova.querySelector('select, input');
                if (primo) {
                    primo.focus();
                }
                break;
            }

            case 'rimuovi-riga': {
                var riga = el.closest('.riga-dinamica');
                if (riga) {
                    riga.remove();
                }
                break;
            }

            // Scorciatoia: stessa fascia per i cinque giorni feriali
            case 'lun-ven': {
                var inizio = document.getElementById('lv-inizio').value;
                var fine = document.getElementById('lv-fine').value;
                if (!inizio || !fine) {
                    window.alert('Indicare ora di inizio e ora di fine.');
                    break;
                }
                if (fine <= inizio) {
                    window.alert("L'ora di fine deve essere successiva all'ora di inizio.");
                    break;
                }
                for (var giorno = 1; giorno <= 5; giorno++) {
                    aggiungiRiga('fasce', { giorno_settimana: String(giorno), ora_inizio: inizio, ora_fine: fine });
                }
                break;
            }
        }
    });

    // --- "24h su 24": le fasce restano ma vengono mostrate come ignorate ---
    var h24 = document.getElementById('disp_h24');
    var sezioneFasce = document.getElementById('sezione-fasce');
    var avvisoH24 = document.getElementById('avviso-h24');
    function aggiornaH24() {
        sezioneFasce.classList.toggle('ignorata', h24.checked);
        avvisoH24.hidden = !h24.checked;
    }
    if (h24 && sezioneFasce && avvisoH24) {
        h24.addEventListener('change', aggiornaH24);
        aggiornaH24();
    }
})();
