<?php
// Iniciar firma con FirmaEC Desktop:
//   1. Cliente sube PDF + cedula.
//   2. Este script POST /servicio/documentos con X-API-KEY: <secret>
//      → recibe token JWT (status 201).
//   3. Devuelve al browser: { token, firmaec_url, api_url }
//      donde firmaec_url = firmaec://sistema/firmar?token=<JWT>&url=<api_url>
//   4. Browser abre firmaec_url → SO lanza FirmaEC Desktop.
//   5. Desktop firma localmente con cert del usuario, hace PUT al servicio.
//   6. Cliente hace polling a status.php hasta ver documento firmado.
declare(strict_types=1);

$cfg = require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

if (empty($cfg['api_key'])) {
    respondError(412, 'FIRMAEC_API_KEY (secret) no configurado. Vaya a la pestaña Setup.');
}

if (empty($_FILES['pdf']['tmp_name'])) {
    respondError(400, 'Falta archivo PDF');
}

$cedula = trim((string)($_POST['cedula'] ?? ''));
if ($cedula === '' || !preg_match('/^\d{10,13}$/', $cedula)) {
    respondError(400, 'Cédula inválida (debe ser 10-13 dígitos)');
}

// Parámetros de estampado (todos opcionales, se anexan al URL firmaec://)
$tipoCertificado = trim((string)($_POST['tipo_certificado'] ?? 'Archivo'));   // Archivo | Token
$tipoEstampado   = trim((string)($_POST['tipo_estampado']   ?? 'QR'));        // QR | information1 | information2
$pagina          = trim((string)($_POST['pagina']           ?? '1'));
$llx             = trim((string)($_POST['llx']              ?? '100'));
$lly             = trim((string)($_POST['lly']              ?? '100'));
$razon           = trim((string)($_POST['razon']            ?? ''));
$ubicacion       = trim((string)($_POST['ubicacion']        ?? ''));

$pdfName = $_FILES['pdf']['name'] ?? 'documento.pdf';
$pdfBin  = (string)file_get_contents($_FILES['pdf']['tmp_name']);
if ($pdfBin === '') {
    respondError(400, 'No se pudo leer el PDF');
}
$pdfB64 = base64_encode($pdfBin);

// Construir payload — formato esperado por POST /servicio/documentos:
// { "cedula": "...", "sistema": "...", "documentos": [{"nombre": "...", "documento": "<base64>"}] }
$payload = [
    'cedula'     => $cedula,
    'sistema'    => $cfg['sistema'],
    'documentos' => [
        ['nombre' => $pdfName, 'documento' => $pdfB64],
    ],
];

$endpoint = rtrim($cfg['servicio_base'], '/') . '/documentos';

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-API-KEY: ' . $cfg['api_key'],
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 60,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($body === false) {
    respondError(502, 'No se pudo contactar al servicio FirmaEC', [
        'detail'   => $err,
        'endpoint' => $endpoint,
    ]);
}

if ($code !== 201) {
    respondError(502, 'El servicio rechazó la creación del documento', [
        'http' => $code,
        'body' => mb_substr((string)$body, 0, 2000),
        'hint' => $code === 403
            ? 'API_KEY inválida — verificar X-API-KEY = secret plano y tabla `sistema`.apikey = SHA256(secret)'
            : null,
    ]);
}

$token = trim((string)$body);
if ($token === '' || !str_contains($token, '.')) {
    respondError(502, 'Token JWT vacío o malformado', ['body' => mb_substr((string)$body, 0, 500)]);
}

// URL que FirmaEC Desktop usará (debe estar en tabla `apiurl` con status=true).
$apiUrl = rtrim($cfg['public_base'], '/') . '/api';

// Protocolo firmaec:// — abre FirmaEC Desktop instalado en la PC.
//
// IMPORTANTE - quirks del cliente Desktop oficial 5.1.0:
//   1. La URL debe ir EN TEXTO PLANO (sin URL-encoding). Si se URL-encodea,
//      el cliente parsea mal `%3A%2F%2F` y crashea con NullPointerException.
//   2. Parámetros conocidos (snake_case, NO camelCase):
//        token            JWT obtenido de POST /servicio/documentos
//        url              base API (texto plano)
//        tipo_certificado "Archivo" o "Token"
//        llx, lly         coords inferior-izquierda del estampado (px)
//        pagina           número de página (1, 2, ..., "ultima")
//        razon            razón de la firma (URL-encoded)
//   3. tipo_estampado NO es leído por el Desktop directamente — el tipo
//      (QR / information1 / information2) se decide internamente al firmar.
//      Lo agregamos igualmente por si versiones futuras lo soporten.
$firmaecUrl = 'firmaec://sistema/firmar?token=' . $token
            . '&url=' . $apiUrl
            . '&tipo_certificado=' . urlencode($tipoCertificado)
            . '&tipo_estampado='   . urlencode($tipoEstampado)
            . '&llx='              . urlencode($llx)
            . '&lly='              . urlencode($lly)
            . '&pagina='           . urlencode($pagina);

if ($razon !== '') {
    $firmaecUrl .= '&razon=' . urlencode($razon);
}
if ($ubicacion !== '') {
    $firmaecUrl .= '&ubicacion=' . urlencode($ubicacion);
}

respond(200, [
    'ok'          => true,
    'token'       => $token,
    'api_url'     => $apiUrl,
    'firmaec_url' => $firmaecUrl,
    'note'        => 'Abra la firmaec_url en su browser. Si tiene FirmaEC Desktop instalado, se abrirá automáticamente.',
    'siguiente'   => 'Usar status.php?token=<token> para polling hasta ver documento firmado.',
]);


function respondError(int $http, string $msg, array $extra = []): never
{
    respond($http, ['error' => $msg] + $extra);
}

function respond(int $code, array $payload): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
