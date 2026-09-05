<?php
require __DIR__ . '/config.php';

if (!estaAutenticado()) {
    responderJson(['error' => 'No autenticado.'], 401);
}

asegurarCarpetaAdjuntos();

define('ADJUNTO_MAX_BYTES', 20 * 1024 * 1024); // 20 MB

function idAdjuntoValido($id) {
    return is_string($id) && preg_match('/^[a-f0-9]{32}$/', $id) === 1;
}

$accion = $_GET['accion'] ?? '';

if ($accion === 'subir') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responderJson(['error' => 'Método no permitido.'], 405);
    }
    if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        responderJson(['error' => 'No se recibió el archivo correctamente.'], 400);
    }

    $archivo = $_FILES['archivo'];
    if ($archivo['size'] > ADJUNTO_MAX_BYTES) {
        responderJson(['error' => 'El archivo supera el tamaño máximo permitido (20 MB).'], 413);
    }

    $id = bin2hex(random_bytes(16));
    $destino = ADJUNTOS_DIR . '/' . $id;

    if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
        responderJson(['error' => 'No se pudo guardar el archivo en el servidor.'], 500);
    }

    responderJson([
        'id'     => $id,
        'nombre' => $archivo['name'],
        'tamano' => $archivo['size'],
        'tipo'   => $archivo['type'] ?: 'application/octet-stream',
        'subido' => (int) round(microtime(true) * 1000),
    ]);
}

if ($accion === 'descargar') {
    $id = $_GET['id'] ?? '';
    $nombre = $_GET['nombre'] ?? 'archivo';
    if (!idAdjuntoValido($id)) {
        responderJson(['error' => 'Adjunto no válido.'], 400);
    }
    $ruta = ADJUNTOS_DIR . '/' . $id;
    if (!file_exists($ruta)) {
        responderJson(['error' => 'Adjunto no encontrado.'], 404);
    }
    $nombreSeguro = preg_replace('/[\r\n"]/', '_', $nombre);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $nombreSeguro . '"');
    header('Content-Length: ' . filesize($ruta));
    header('Cache-Control: private');
    readfile($ruta);
    exit;
}

if ($accion === 'eliminar') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responderJson(['error' => 'Método no permitido.'], 405);
    }
    $cuerpo = json_decode(file_get_contents('php://input'), true);
    $id = is_array($cuerpo) ? ($cuerpo['id'] ?? '') : '';
    if (!idAdjuntoValido($id)) {
        responderJson(['error' => 'Adjunto no válido.'], 400);
    }
    $ruta = ADJUNTOS_DIR . '/' . $id;
    if (file_exists($ruta)) {
        @unlink($ruta);
    }
    responderJson(['ok' => true]);
}

responderJson(['error' => 'Acción no reconocida.'], 400);
