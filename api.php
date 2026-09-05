<?php
require __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!estaAutenticado()) {
    responderJson(['error' => 'No autenticado.'], 401);
}

$accion = $_GET['accion'] ?? '';

if ($accion === 'cargar') {
    $ruta = rutaDatosTareas();

    if (!$ruta || !file_exists($ruta)) {
        responderJson([
            'proyectos'      => [],
            'tareas'         => [],
            'vista'          => 'todas',
            'proyectoActivo' => null,
        ]);
    }

    $contenido = file_get_contents($ruta);
    $datos = json_decode($contenido, true);

    if (!is_array($datos)) {
        responderJson(['error' => 'Los datos guardados están dañados.'], 500);
    }

    responderJson($datos);
}

if ($accion === 'guardar') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responderJson(['error' => 'Método no permitido.'], 405);
    }

    $cuerpo = file_get_contents('php://input');

    if (strlen($cuerpo) > 4 * 1024 * 1024) {
        responderJson(['error' => 'Los datos superan el tamaño máximo permitido (4 MB).'], 413);
    }

    $datos = json_decode($cuerpo, true);

    if (!is_array($datos) || !array_key_exists('proyectos', $datos) || !array_key_exists('tareas', $datos)) {
        responderJson(['error' => 'El cuerpo enviado no es un estado válido.'], 400);
    }

    $ruta = rutaDatosTareas();
    if (!$ruta) {
        responderJson(['error' => 'No hay sesión activa.'], 401);
    }

    if (!escribirArchivoAtomico($ruta, json_encode($datos, JSON_UNESCAPED_UNICODE))) {
        responderJson(['error' => 'No se pudieron guardar los cambios en el servidor.'], 500);
    }

    responderJson(['ok' => true]);
}

responderJson(['error' => 'Acción no reconocida.'], 400);
