<?php
// Registra el sistema cliente para poder usar el flujo FirmaEC Desktop:
//
//  - tabla `sistema`       (apikey=SHA256(secret); flujo POST /servicio/documentos)
//  - tabla `sistemamobile` (apikey=SHA256(secret); flujo POST /servicio/getjwt — opcional)
//  - tabla `apiurl`        (whitelist de URLs que FirmaEC Desktop puede consumir)
//  - tabla `version`       (control de versión cliente, status=true)
//
// El SECRET se envía como X-API-KEY (texto plano). El servicio aplica
// SHA256(secret).toUpperCase() y compara con sistema.apikey / sistemamobile.apikey.
declare(strict_types=1);

$cfg = require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

$nombre    = trim((string)($_POST['nombre']      ?? ''));
$desc      = trim((string)($_POST['descripcion'] ?? 'Cliente prueba docker-firmaec-5'));
$secret    = trim((string)($_POST['secret']      ?? ''));
$apiUrlPub = trim((string)($_POST['api_url']     ?? ''));

if ($nombre === '') {
    respond(400, ['error' => 'nombre es requerido']);
}

if ($secret === '') {
    $secret = $nombre . '_secret';
}

// URL pública del API que FirmaEC Desktop usará para descargar/subir documentos.
// Default: la PUBLIC base + /api (servicio está bajo nginx).
if ($apiUrlPub === '') {
    $apiUrlPub = rtrim($cfg['public_base'], '/') . '/api';
}

$apikey_hash = strtoupper(hash('sha256', $secret));

try {
    $dsn = sprintf('pgsql:host=%s;dbname=%s', $cfg['db_host'], $cfg['db_name']);
    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    respond(500, ['error' => 'No se pudo conectar a PostgreSQL', 'detail' => $e->getMessage()]);
}

try {
    foreach (['sistema', 'sistemamobile', 'apiurl', 'version'] as $tbl) {
        $st = $pdo->prepare("SELECT to_regclass(:t) AS t");
        $st->execute([':t' => "public.$tbl"]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (empty($row['t'])) {
            respond(503, [
                'error'  => "La tabla `$tbl` aún no existe.",
                'detail' => 'Hibernate la crea al desplegar servicio.war. Espere 1-2 min tras levantar el stack.',
            ]);
        }
    }
} catch (Throwable $e) {
    respond(500, ['error' => 'Error al consultar schema', 'detail' => $e->getMessage()]);
}

$result = [];

try {
    $pdo->beginTransaction();

    // --- tabla `sistema` ---
    // url        = callback (REST) — donde el servicio POSTea el PDF firmado.
    //              Usar Docker DNS interno: http://cliente-prueba/callback.php
    // apikey     = SHA256(secret) — verificación X-API-KEY al CREAR documentos.
    // apikeyrest = SHA256(secret) — el servicio lo envía como X-API-KEY al
    //              callback.php para que validemos. Si NULL, el servicio
    //              usa SOAP en lugar de REST.
    $st = $pdo->prepare('SELECT id FROM sistema WHERE nombre = :n LIMIT 1');
    $st->execute([':n' => $nombre]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $callback = 'http://cliente-prueba/callback.php';

    if ($row) {
        $pdo->prepare('
            UPDATE sistema SET descripcion=:d, url=:u, apikey=:k, apikeyrest=:k2 WHERE id=:id
        ')->execute([':d'=>$desc, ':u'=>$callback, ':k'=>$apikey_hash, ':k2'=>$apikey_hash, ':id'=>$row['id']]);
        $result['sistema'] = ['id' => (int)$row['id'], 'action' => 'updated'];
    } else {
        $pdo->prepare('
            INSERT INTO sistema (nombre, descripcion, url, apikey, apikeyrest)
            VALUES (:n, :d, :u, :k, :k2)
        ')->execute([':n'=>$nombre, ':d'=>$desc, ':u'=>$callback, ':k'=>$apikey_hash, ':k2'=>$apikey_hash]);
        $result['sistema'] = ['id' => (int)$pdo->lastInsertId('sistema_id_seq'), 'action' => 'inserted'];
    }

    // --- tabla `sistemamobile` (opcional, para flujo getjwt) ---
    $st = $pdo->prepare('SELECT id FROM sistemamobile WHERE nombre = :n LIMIT 1');
    $st->execute([':n' => $nombre]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $pdo->prepare('UPDATE sistemamobile SET descripcion=:d, apikey=:k WHERE id=:id')
            ->execute([':d'=>$desc, ':k'=>$apikey_hash, ':id'=>$row['id']]);
        $result['sistemamobile'] = ['id' => (int)$row['id'], 'action' => 'updated'];
    } else {
        $pdo->prepare('
            INSERT INTO sistemamobile (nombre, descripcion, apikey) VALUES (:n, :d, :k)
        ')->execute([':n'=>$nombre, ':d'=>$desc, ':k'=>$apikey_hash]);
        $result['sistemamobile'] = ['id' => (int)$pdo->lastInsertId('sistemamobile_id_seq'), 'action' => 'inserted'];
    }

    // --- tabla `apiurl` (whitelist URLs Desktop) ---
    $st = $pdo->prepare('SELECT id FROM apiurl WHERE url = :u LIMIT 1');
    $st->execute([':u' => $apiUrlPub]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $pdo->prepare('UPDATE apiurl SET nombre=:n, status=true WHERE id=:id')
            ->execute([':n'=>"docker-firmaec-5 ($nombre)", ':id'=>$row['id']]);
        $result['apiurl'] = ['id' => (int)$row['id'], 'action' => 'updated', 'url' => $apiUrlPub];
    } else {
        $pdo->prepare('INSERT INTO apiurl (nombre, url, status) VALUES (:n, :u, true)')
            ->execute([':n'=>"docker-firmaec-5 ($nombre)", ':u'=>$apiUrlPub]);
        $result['apiurl'] = ['id' => (int)$pdo->lastInsertId('apiurl_id_seq'), 'action' => 'inserted', 'url' => $apiUrlPub];
    }

    // --- tabla `version` ---
    $st = $pdo->prepare("
        SELECT id FROM version
         WHERE sistemaoperativo='LINUX' AND aplicacion='docker-firmaec-5' AND version='5.1' LIMIT 1
    ");
    $st->execute();
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $pdo->prepare('UPDATE version SET status=true, descripcion=:d WHERE id=:id')
            ->execute([':d'=>'Auto-registrada por cliente prueba', ':id'=>$row['id']]);
        $result['version'] = ['id' => (int)$row['id'], 'action' => 'updated'];
    } else {
        $pdo->prepare("
            INSERT INTO version (sistemaoperativo, aplicacion, version, descripcion, status, fechaliberacion)
            VALUES ('LINUX', 'docker-firmaec-5', '5.1', 'Auto-registrada por cliente prueba', true, NOW())
        ")->execute();
        $result['version'] = ['id' => (int)$pdo->lastInsertId('version_id_seq'), 'action' => 'inserted'];
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    respond(500, ['error' => 'Error en transacción BD', 'detail' => $e->getMessage()]);
}

respond(200, [
    'ok'          => true,
    'nombre'      => $nombre,
    'secret'      => $secret,
    'apikey_hash' => $apikey_hash,
    'tablas'      => $result,
    'note'        => 'Copie el SECRET (texto plano) al .env como FIRMAEC_CLIENT_API_KEY y recree:',
    'cmd'         => 'docker compose up -d --force-recreate cliente-prueba',
]);


function respond(int $code, array $payload): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
