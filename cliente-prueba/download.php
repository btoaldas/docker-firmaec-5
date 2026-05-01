<?php
// Descarga del PDF firmado guardado por callback.php.
declare(strict_types=1);

$cedula = trim((string)($_GET['cedula'] ?? ''));
$nombre = trim((string)($_GET['nombre'] ?? ''));

if ($cedula === '' || $nombre === '') {
    http_response_code(400);
    exit('cedula y nombre requeridos');
}

$dir       = sys_get_temp_dir() . '/firmaec-firmados';
$linkBase  = preg_replace('/\.pdf$/i', '', $nombre);
$linkFile  = "$dir/{$cedula}_LATEST_{$linkBase}.pdf";

if (!is_file($linkFile)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'PDF firmado aún no recibido', 'esperado' => $linkFile]);
    exit;
}

$outName = $linkBase . '_firmado.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $outName . '"');
header('Content-Length: ' . filesize($linkFile));
readfile($linkFile);
