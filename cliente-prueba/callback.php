<?php
// Callback REST que el servicio FirmaEC invoca con el PDF firmado.
//
// El servicio (firmadigital-servicio) hace POST aquí cuando el FirmaEC Desktop
// del usuario completó la firma:
//   POST http://cliente-prueba/callback.php
//   Header: X-API-KEY: <apikeyrest>  (debe matchear sistema.apikeyrest en BD)
//   Content-Type: application/json
//   Body: {
//     "cedula":            "1234567890",
//     "nombreDocumento":   "documento.pdf",
//     "archivo":           "<base64 PDF firmado>",
//     "firmasValidas":     true,
//     "integridadDocumento": true,
//     "error":             "null",
//     "certificado":       [{...}]
//   }
//
// Respuesta esperada por el servicio: texto plano "OK" exacto.
declare(strict_types=1);

$cfg = require __DIR__ . '/config.php';

$logFile = '/tmp/firmaec-callback.log';
file_put_contents($logFile,
    sprintf("[%s] %s %s\n", date('c'), $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']),
    FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('ERROR');
}

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

// Aceptamos: secret plano o el hash directo (servicio puede enviar cualquiera
// según versión).
$expectedHash  = strtoupper(hash('sha256', (string)$cfg['api_key']));
$apiKeyUpper   = strtoupper((string)$apiKey);

if ($apiKeyUpper !== $expectedHash && $apiKey !== $cfg['api_key']) {
    file_put_contents($logFile, "  X-API-KEY rechazada: $apiKey\n", FILE_APPEND);
    http_response_code(403);
    exit('ERROR');
}

$raw  = (string)file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || empty($data['archivo']) || empty($data['nombreDocumento'])) {
    file_put_contents($logFile, "  body inválido: " . mb_substr($raw, 0, 200) . "\n", FILE_APPEND);
    http_response_code(400);
    exit('ERROR');
}

$cedula  = (string)($data['cedula']          ?? 'desconocida');
$nombre  = (string)$data['nombreDocumento'];
$archivo = (string)$data['archivo'];

$pdfBin = base64_decode($archivo, true);
if ($pdfBin === false || !str_starts_with($pdfBin, '%PDF')) {
    file_put_contents($logFile, "  archivo no es PDF válido\n", FILE_APPEND);
    http_response_code(400);
    exit('ERROR');
}

// Guardar firmado. Indexado por cedula + timestamp para el polling.
$dir = sys_get_temp_dir() . '/firmaec-firmados';
if (!is_dir($dir)) mkdir($dir, 0700, true);

$ts   = time();
$base = sprintf('%s_%d_%s', $cedula, $ts, preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombre));

file_put_contents("$dir/$base", $pdfBin);
file_put_contents("$dir/{$base}.meta.json", json_encode([
    'cedula'          => $cedula,
    'nombreDocumento' => $nombre,
    'firmasValidas'   => $data['firmasValidas']       ?? null,
    'integridad'      => $data['integridadDocumento'] ?? null,
    'certificado'     => $data['certificado']         ?? [],
    'recibido_en'     => date('c', $ts),
    'tamano'          => strlen($pdfBin),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// También link estable por nombre para que status.php lo encuentre.
$linkBase = preg_replace('/\.pdf$/i', '', $nombre);
$linkFile = "$dir/{$cedula}_LATEST_{$linkBase}.pdf";
@copy("$dir/$base", $linkFile);

file_put_contents($logFile,
    sprintf("  guardado %s/%s (%d bytes)\n", $dir, $base, strlen($pdfBin)),
    FILE_APPEND);

http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo 'OK';
