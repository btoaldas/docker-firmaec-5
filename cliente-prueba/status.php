<?php
// Polling: ¿el callback ya recibió un PDF firmado para esta cédula+nombre?
// Si no, consulta /servicio/documentos/{token} para ver estado del token.
declare(strict_types=1);

$cfg = require __DIR__ . '/config.php';

$token  = trim((string)($_GET['token']  ?? ''));
$cedula = trim((string)($_GET['cedula'] ?? ''));
$nombre = trim((string)($_GET['nombre'] ?? ''));

if ($token === '' || !str_contains($token, '.')) {
    respond(400, ['error' => 'token requerido']);
}

// 1. Buscar archivo firmado guardado por callback.php
$dir = sys_get_temp_dir() . '/firmaec-firmados';
if (is_dir($dir) && $cedula !== '' && $nombre !== '') {
    $linkBase = preg_replace('/\.pdf$/i', '', $nombre);
    $linkFile = "$dir/{$cedula}_LATEST_{$linkBase}.pdf";
    if (is_file($linkFile)) {
        respond(200, [
            'estado'      => 'firmado',
            'firmado_url' => 'download.php?cedula=' . urlencode($cedula) . '&nombre=' . urlencode($nombre),
            'tamano'      => filesize($linkFile),
            'recibido_en' => date('c', filemtime($linkFile)),
        ]);
    }
}

// 2. Estado del token en el servicio
$endpoint = rtrim($cfg['servicio_base'], '/') . '/documentos/' . urlencode($token);
$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 15,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body === false) {
    respond(502, ['estado' => 'error', 'detail' => 'no se pudo consultar al servicio']);
}

if ($code === 400) {
    respond(200, [
        'estado'  => 'token_invalido_o_expirado',
        'detalle' => 'El token ya fue gestionado o expiró. Si firmó OK, revise el callback.',
        'body'    => trim((string)$body),
    ]);
}

if ($code !== 200) {
    respond($code, ['estado' => 'error', 'http' => $code, 'body' => mb_substr((string)$body, 0, 500)]);
}

respond(200, [
    'estado'    => 'pendiente',
    'detalle'   => 'Token válido. Esperando que FirmaEC Desktop firme y envíe el documento.',
    'service'   => json_decode((string)$body, true),
]);


function respond(int $code, array $payload): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
