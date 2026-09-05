<?php
/**
 * config.php — rutas, sesión y utilidades compartidas.
 * Lo incluyen login.php y api.php. No genera salida por sí mismo.
 */

define('DATOS_DIR', __DIR__ . '/datos');
define('CREDENCIALES_FILE', DATOS_DIR . '/credenciales.json');
define('ADJUNTOS_DIR', DATOS_DIR . '/adjuntos');

function asegurarCarpetaDatos() {
    if (!is_dir(DATOS_DIR)) {
        if (!mkdir(DATOS_DIR, 0755, true) && !is_dir(DATOS_DIR)) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No se pudo crear la carpeta de datos. Revisa los permisos de escritura en el servidor.']);
            exit;
        }
    }

    $htaccess = DATOS_DIR . '/.htaccess';
    if (!file_exists($htaccess)) {
        $contenido = "Require all denied\n\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n";
        @file_put_contents($htaccess, $contenido, LOCK_EX);
    }

    $indice = DATOS_DIR . '/index.html';
    if (!file_exists($indice)) {
        @file_put_contents($indice, '', LOCK_EX);
    }

    if (!is_writable(DATOS_DIR)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'La carpeta datos/ no tiene permisos de escritura. Cámbiala a 755 (o 775) desde el Administrador de Archivos.']);
        exit;
    }
}

asegurarCarpetaDatos();

function asegurarCarpetaAdjuntos() {
    if (!is_dir(ADJUNTOS_DIR)) {
        @mkdir(ADJUNTOS_DIR, 0755, true);
    }
}

// ---------- sesión ----------

$duracionSesion = 60 * 60 * 24 * 30; // 30 días
$esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => $duracionSesion,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => $esHttps,
]);
session_name('gestor_sesion');
session_start();

// ---------- credenciales ----------

function credencialesExisten() {
    return file_exists(CREDENCIALES_FILE);
}

function leerCredenciales() {
    if (!credencialesExisten()) return null;
    $contenido = file_get_contents(CREDENCIALES_FILE);
    $datos = json_decode($contenido, true);
    return is_array($datos) ? $datos : null;
}

function estaAutenticado() {
    return !empty($_SESSION['autenticado']);
}

function tokenActivo() {
    $cred = leerCredenciales();
    return $cred ? ($cred['token'] ?? null) : null;
}

function rutaDatosTareas() {
    $token = tokenActivo();
    if (!$token) return null;
    return DATOS_DIR . '/tareas-' . $token . '.json';
}

function generarToken() {
    return bin2hex(random_bytes(16));
}

// ---------- archivos ----------

function escribirArchivoAtomico($ruta, $contenido) {
    $temporal = $ruta . '.tmp';
    $fp = fopen($temporal, 'w');
    if ($fp === false) return false;

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return false;
    }
    fwrite($fp, $contenido);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    if (file_exists($ruta)) {
        @copy($ruta, $ruta . '.bak');
    }

    return rename($temporal, $ruta);
}

function responderJson($datos, $codigo = 200) {
    http_response_code($codigo);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($datos);
    exit;
}
