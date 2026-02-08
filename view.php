<?php
require __DIR__ . '/auth.php';

require_login();

$DOCUMENT_ROOTS = require __DIR__ . "/config.php";

$base = $_GET["base"] ?? "";
$rel = $_GET["file"] ?? "";

if (!isset($DOCUMENT_ROOTS[$base])) {
    http_response_code(404);
    echo "Documento no encontrado.";
    exit;
}

if ($rel === "" || strpos($rel, "..") !== false) {
    http_response_code(400);
    echo "Ruta inválida.";
    exit;
}

$root = $DOCUMENT_ROOTS[$base];
$root_real = realpath($root);
if ($root_real === false) {
    http_response_code(500);
    echo "Directorio de documentos no disponible.";
    exit;
}

$full = realpath($root_real . DIRECTORY_SEPARATOR . $rel);
if ($full === false || strpos($full, $root_real . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    echo "Documento no encontrado.";
    exit;
}

if (!is_file($full) || !preg_match('/\.pdf$/i', $full)) {
    http_response_code(404);
    echo "Documento no encontrado.";
    exit;
}

$filename = basename($full);
header("Content-Type: application/pdf");
header("X-Content-Type-Options: nosniff");
header("Content-Disposition: inline; filename=\"" . addslashes($filename) . "\"");
header("Content-Length: " . filesize($full));

readfile($full);
exit;
