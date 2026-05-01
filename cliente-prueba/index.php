<?php
declare(strict_types=1);
$cfg = require __DIR__ . '/config.php';
$apiKeyOk = !empty($cfg['api_key']);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Cliente prueba FirmaEC 5.1 (Desktop)</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
    <h1>FirmaEC 5.1 — Cliente prueba (flujo Desktop)</h1>
    <p class="muted">
        Empaquetado Docker no oficial. Código fuente original:
        <a href="https://minka.gob.ec/mintel/ge/firmaec" target="_blank" rel="noopener">MINTEL Ecuador / MINKA</a>.
    </p>
    <?php if (!$apiKeyOk): ?>
    <p class="warn">
        ⚠ <code>FIRMAEC_API_KEY</code> no configurado. Ir a la pestaña <strong>Setup</strong> primero.
    </p>
    <?php endif; ?>
</header>

<nav class="tabs">
    <button class="tab-btn active" data-tab="firmar">Firmar PDF</button>
    <button class="tab-btn" data-tab="setup">Setup</button>
    <button class="tab-btn" data-tab="info">Endpoints</button>
    <button class="tab-btn" data-tab="ayuda">Ayuda Desktop</button>
</nav>

<section id="tab-firmar" class="tab active">
    <h2>Firmar documento PDF — flujo FirmaEC Desktop</h2>
    <p class="muted">
        Este cliente <strong>NO firma</strong>. Solo coordina: sube el PDF al servicio,
        recibe un token JWT, y abre <code>firmaec://</code> que invoca el FirmaEC
        Desktop instalado en su PC. El Desktop lee el PDF, le pide su certificado
        (.p12 o token físico), firma localmente, y devuelve el firmado al servicio.
        El cliente espera el callback y permite descargar.
    </p>

    <form id="formFirmar" enctype="multipart/form-data" method="post" action="firmar.php">
        <label>Archivo PDF a firmar
            <input type="file" name="pdf" accept="application/pdf" required>
        </label>
        <label>Cédula del firmante (10–13 dígitos)
            <input type="text" name="cedula" pattern="\d{10,13}" required placeholder="0102030405">
        </label>

        <details>
            <summary class="muted">Opciones de estampado (avanzado)</summary>
            <div style="display:flex; flex-direction:column; gap:.7rem; margin-top:.7rem">
                <label>Tipo de certificado
                    <select name="tipo_certificado">
                        <option value="Archivo" selected>Archivo (.p12 / .pfx)</option>
                        <option value="Token">Token físico (USB)</option>
                    </select>
                </label>
                <label>Tipo de estampado visual
                    <select name="tipo_estampado">
                        <option value="QR" selected>QR (default — código QR de validación)</option>
                        <option value="information1">information1 (texto solo, layout 1)</option>
                        <option value="information2">information2 (texto solo, layout 2)</option>
                    </select>
                </label>
                <label>Página (número o "ultima")
                    <input type="text" name="pagina" value="1">
                </label>
                <label>Posición (px desde esquina inferior izquierda)
                    <span class="row">
                        <input type="number" name="llx" value="100" placeholder="X (llx)">
                        <input type="number" name="lly" value="100" placeholder="Y (lly)">
                    </span>
                </label>
                <label>Razón
                    <input type="text" name="razon" value="Prueba de firma" maxlength="200">
                </label>
                <label>Ubicación
                    <input type="text" name="ubicacion" value="Ecuador" maxlength="100">
                </label>
            </div>
        </details>

        <button type="submit"<?= $apiKeyOk ? '' : ' disabled' ?>>1. Iniciar firma</button>
        <progress id="progFirmar" value="0" max="100" hidden></progress>
    </form>

    <div id="firmaActions" hidden>
        <h3 style="margin-top:1.5rem">2. Abra FirmaEC Desktop</h3>
        <p class="muted">
            Click el botón. Si el browser no responde, copie la URL y pruebe
            métodos alternativos abajo.
        </p>
        <a id="btnAbrirDesktop" class="btn-primary" href="#">Abrir FirmaEC Desktop</a>
        <button id="btnCopiarUrl" type="button">Copiar URL firmaec://</button>

        <details style="margin-top:1rem">
            <summary class="muted">URL completa <code>firmaec://...</code> (click para ver)</summary>
            <pre id="firmaecUrlText" class="output" style="margin-top:.5rem; word-break:break-all; user-select:all"></pre>
        </details>

        <details style="margin-top:.5rem">
            <summary class="muted">Métodos alternativos si el botón no abre Desktop</summary>
            <ol class="muted" style="margin-top:.5rem">
                <li>Copiar la URL arriba y pegarla en la barra de direcciones del browser → Enter.</li>
                <li>Windows: pegar la URL en <strong>Win+R</strong> (Ejecutar) → Enter.</li>
                <li>Linux: <code>xdg-open "firmaec://..."</code> en terminal.</li>
                <li>macOS: <code>open "firmaec://..."</code> en terminal.</li>
                <li>Si nada funciona → FirmaEC Desktop no está instalado o el protocolo
                    no quedó registrado. Reinstalar el Desktop.</li>
            </ol>
            <p class="muted" style="margin-top:.5rem">
                Verificar Windows registry: <code>reg query HKEY_CLASSES_ROOT\firmaec</code><br>
                Si no devuelve resultado → protocolo no registrado.
            </p>
        </details>

        <h3 style="margin-top:1.5rem">3. Esperando firma…</h3>
        <p class="muted" id="firmaStatus">Polling cada 5s.</p>
        <progress id="progPolling" value="0" max="100"></progress>
        <div style="margin-top:.5rem">
            <a id="btnDescargar" class="btn-primary" href="#" hidden>Descargar PDF firmado</a>
            <button id="btnCancelar" type="button">Cancelar polling</button>
        </div>
    </div>

    <pre id="outFirmar" class="output" hidden></pre>
</section>

<section id="tab-setup" class="tab">
    <h2>Setup — registrar sistema cliente</h2>
    <p class="muted">
        Antes de firmar: este sistema cliente debe estar registrado en la BD del
        FirmaEC. Setup llena 4 tablas: <code>sistema</code>, <code>sistemamobile</code>,
        <code>apiurl</code> (whitelist URLs Desktop) y <code>version</code>.
        Acción <strong>idempotente</strong>.
    </p>
    <form id="formSetup" method="post" action="setup-sistema.php">
        <label>Nombre del sistema
            <input type="text" name="nombre" value="<?= htmlspecialchars($cfg['sistema']) ?>" required>
        </label>
        <label>Descripción
            <input type="text" name="descripcion" value="Cliente prueba docker-firmaec-5">
        </label>
        <label>Secret (lo que se enviará como <code>X-API-KEY</code>; vacío = autogenerar)
            <input type="text" name="secret" value="">
        </label>
        <label>URL pública del API (que Desktop usará)
            <input type="text" name="api_url" value="<?= htmlspecialchars(rtrim($cfg['public_base'], '/')) ?>/api">
        </label>
        <button type="submit">Registrar / actualizar</button>
    </form>
    <pre id="outSetup" class="output" hidden></pre>
    <p class="muted">
        Tras registrar: copie el campo <strong><code>secret</code></strong> a
        <code>.env</code> como <code>FIRMAEC_CLIENT_API_KEY=&lt;secret&gt;</code> y recree:<br>
        <code>docker compose up -d --force-recreate cliente-prueba</code>
    </p>
</section>

<section id="tab-info" class="tab">
    <h2>Endpoints configurados</h2>
    <table>
        <tr><th>URL pública (vía nginx)</th> <td><code><?= htmlspecialchars($cfg['public_base']) ?></code></td></tr>
        <tr><th>Sistema</th>                 <td><code><?= htmlspecialchars($cfg['sistema']) ?></code></td></tr>
        <tr><th>API key</th>                 <td><code><?= $apiKeyOk ? '(configurada)' : '(VACÍA — ir a Setup)' ?></code></td></tr>
    </table>

    <h3 style="margin-top:1.5rem">Endpoints expuestos</h3>
    <table>
        <tr><th>Crear documentos para firmar</th>
            <td><code>POST <?= htmlspecialchars($cfg['public_base']) ?>/servicio/documentos</code></td></tr>
        <tr><th>Obtener documentos por token</th>
            <td><code>GET <?= htmlspecialchars($cfg['public_base']) ?>/servicio/documentos/{token}</code></td></tr>
        <tr><th>Subir documentos firmados (Desktop)</th>
            <td><code>PUT <?= htmlspecialchars($cfg['public_base']) ?>/servicio/documentos/{token}</code></td></tr>
        <tr><th>Callback REST (recibe firmados)</th>
            <td><code>http://cliente-prueba/callback.php</code> (interno)</td></tr>
    </table>
</section>

<section id="tab-ayuda" class="tab">
    <h2>FirmaEC Desktop — instalación</h2>
    <p class="muted">
        El flujo correcto requiere <strong>FirmaEC Desktop</strong> instalado en
        la misma PC que abre este cliente web. El Desktop registra el protocolo
        <code>firmaec://</code> en el SO; al abrirlo, el browser le pregunta si
        lanzar el Desktop con el token.
    </p>
    <ol class="muted">
        <li>Descargar de <a href="https://www.firmadigital.gob.ec/" target="_blank" rel="noopener">www.firmadigital.gob.ec</a> (sección "Descargar FirmaEC").</li>
        <li>Instalar (Windows / macOS / Linux). Acepta registrar protocolo <code>firmaec://</code>.</li>
        <li>Tener un certificado <code>.p12</code> emitido por una AC ecuatoriana
            autorizada (BCE, Security Data, Anf AC, Consejo de la Judicatura,
            ESET, Lazos EC, EC-LACSEC) o token criptográfico.</li>
        <li>Volver aquí y usar la pestaña Firmar PDF.</li>
    </ol>

    <h3>¿Por qué este flujo?</h3>
    <p class="muted">
        En FirmaEC el cert privado <strong>nunca</strong> sale de la PC del usuario.
        El backend solo coordina (token + descarga + callback). La firma
        criptográfica ocurre 100% local — eso es lo que da validez legal a la firma.
    </p>
</section>

<footer>
    <p class="muted">
        AGPL v3 — heredada del proyecto FirmaEC del MINTEL Ecuador.
        Repositorio <code>docker-firmaec-5</code> solo aporta dockerización; sin garantía.
    </p>
</footer>

<script src="assets/app.js"></script>
</body>
</html>
