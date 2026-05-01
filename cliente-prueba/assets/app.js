// Tabs + flujo firma con FirmaEC Desktop:
//   1. submit form Firmar -> POST /firmar.php (recibe token + firmaec_url)
//   2. Mostrar botón "Abrir FirmaEC Desktop" (link firmaec://...)
//   3. Polling /status.php?token=...&cedula=...&nombre=... cada 5s
//   4. Cuando estado=firmado, mostrar botón Descargar
(function () {
    'use strict';

    // --- Tabs ---
    const buttons = document.querySelectorAll('.tab-btn');
    const tabs    = document.querySelectorAll('.tab');
    buttons.forEach(btn => btn.addEventListener('click', () => {
        const target = btn.dataset.tab;
        buttons.forEach(b => b.classList.toggle('active', b === btn));
        tabs.forEach(t => t.classList.toggle('active', t.id === 'tab-' + target));
    }));

    // --- helpers ---
    function setOutput(el, text, ok) {
        el.hidden = false;
        el.textContent = typeof text === 'string' ? text : JSON.stringify(text, null, 2);
        el.classList.toggle('ok',  !!ok);
        el.classList.toggle('err', !ok);
    }

    function pretty(obj) {
        try { return JSON.stringify(obj, null, 2); } catch (_) { return String(obj); }
    }

    // --- flujo Firmar ---
    const formFirmar    = document.getElementById('formFirmar');
    const outFirmar     = document.getElementById('outFirmar');
    const progFirmar    = document.getElementById('progFirmar');
    const firmaActions  = document.getElementById('firmaActions');
    const btnDesktop    = document.getElementById('btnAbrirDesktop');
    const btnDescargar  = document.getElementById('btnDescargar');
    const btnCancelar   = document.getElementById('btnCancelar');
    const firmaStatus   = document.getElementById('firmaStatus');
    const progPolling   = document.getElementById('progPolling');

    let pollingTimer = null;
    let pollingTick  = 0;

    function stopPolling() {
        if (pollingTimer) { clearInterval(pollingTimer); pollingTimer = null; }
        progPolling.value = 0;
    }

    if (formFirmar) {
        formFirmar.addEventListener('submit', (ev) => {
            ev.preventDefault();
            stopPolling();
            firmaActions.hidden = true;
            btnDescargar.hidden = true;
            outFirmar.hidden = true;

            const xhr = new XMLHttpRequest();
            const fd  = new FormData(formFirmar);
            const cedula = fd.get('cedula');
            const pdfFile = fd.get('pdf');
            const pdfName = pdfFile ? pdfFile.name : 'documento.pdf';

            progFirmar.hidden = false; progFirmar.value = 0;
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) progFirmar.value = (e.loaded / e.total) * 100;
            });

            xhr.responseType = 'text';
            xhr.open('POST', formFirmar.action);

            xhr.onload = () => {
                progFirmar.hidden = true;
                let resp = null;
                try { resp = JSON.parse(xhr.responseText); } catch (_) {}

                if (xhr.status !== 200 || !resp || !resp.firmaec_url) {
                    setOutput(outFirmar, resp || xhr.responseText || 'Error desconocido', false);
                    return;
                }

                setOutput(outFirmar, 'Token JWT obtenido. Abra Desktop y firme.\n\n' + pretty(resp), true);

                // Configurar botones + URL visible para copiar
                btnDesktop.href = resp.firmaec_url;
                const urlBox = document.getElementById('firmaecUrlText');
                if (urlBox) urlBox.textContent = resp.firmaec_url;
                firmaActions.hidden = false;

                // Polling
                pollingTick = 0;
                firmaStatus.textContent = 'Polling cada 5s. Tope: 5 minutos.';
                pollingTimer = setInterval(() => pollEstado(resp.token, cedula, pdfName), 5000);
            };

            xhr.onerror = () => {
                progFirmar.hidden = true;
                setOutput(outFirmar, 'Error de red al iniciar firma', false);
            };

            xhr.send(fd);
        });
    }

    function pollEstado(token, cedula, nombre) {
        pollingTick++;
        progPolling.max = 60;  // 60 ticks * 5s = 5 min
        progPolling.value = pollingTick;
        if (pollingTick > 60) {
            stopPolling();
            firmaStatus.textContent = 'Timeout 5 min sin firma. Cancele o vuelva a iniciar.';
            return;
        }

        const url = 'status.php?token=' + encodeURIComponent(token)
                  + '&cedula=' + encodeURIComponent(cedula)
                  + '&nombre=' + encodeURIComponent(nombre);

        fetch(url).then(r => r.json()).then(data => {
            firmaStatus.innerHTML = `<small>Tick ${pollingTick}/60 · estado: <strong>${data.estado}</strong></small>`;

            if (data.estado === 'firmado') {
                stopPolling();
                btnDescargar.href = data.firmado_url;
                btnDescargar.hidden = false;
                setOutput(outFirmar, 'PDF firmado disponible.\n\n' + pretty(data), true);
            } else if (data.estado === 'token_invalido_o_expirado') {
                stopPolling();
                setOutput(outFirmar, pretty(data), false);
            }
        }).catch(e => {
            firmaStatus.textContent = 'Error polling: ' + e.message;
        });
    }

    if (btnCancelar) {
        btnCancelar.addEventListener('click', () => {
            stopPolling();
            firmaStatus.textContent = 'Cancelado por el usuario.';
        });
    }

    // Copiar URL firmaec://
    const btnCopiar = document.getElementById('btnCopiarUrl');
    if (btnCopiar) {
        btnCopiar.addEventListener('click', async () => {
            const url = btnDesktop.href;
            if (!url || url === '#' || url.endsWith('#')) return;
            try {
                await navigator.clipboard.writeText(url);
                const orig = btnCopiar.textContent;
                btnCopiar.textContent = '✓ URL copiada';
                setTimeout(() => btnCopiar.textContent = orig, 2000);
            } catch (e) {
                alert('No se pudo copiar. Selecciona el texto del cuadro URL y copia manual.');
            }
        });
    }

    // --- flujo Setup ---
    const formSetup = document.getElementById('formSetup');
    const outSetup  = document.getElementById('outSetup');
    if (formSetup) {
        formSetup.addEventListener('submit', (ev) => {
            ev.preventDefault();
            const fd  = new FormData(formSetup);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', formSetup.action);
            xhr.responseType = 'text';
            xhr.onload = () => {
                let resp = null;
                try { resp = JSON.parse(xhr.responseText); } catch (_) { resp = xhr.responseText; }
                setOutput(outSetup, resp, xhr.status >= 200 && xhr.status < 300);
            };
            xhr.onerror = () => setOutput(outSetup, 'Error de red', false);
            xhr.send(fd);
        });
    }
})();
