<?php
require __DIR__ . '/config.php';

if (isset($_GET['salir'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $parametros['path'], $parametros['domain'], $parametros['secure'], $parametros['httponly']);
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

if (estaAutenticado()) {
    header('Location: index.html');
    exit;
}

$esNuevo = !credencialesExisten();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($esNuevo) {
        $clave = $_POST['clave'] ?? '';
        $confirmar = $_POST['confirmar'] ?? '';
        if (strlen($clave) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($clave !== $confirmar) {
            $error = 'Las dos contraseñas no coinciden.';
        } else {
            $credenciales = [
                'hash'  => password_hash($clave, PASSWORD_DEFAULT),
                'token' => generarToken(),
            ];
            if (escribirArchivoAtomico(CREDENCIALES_FILE, json_encode($credenciales))) {
                session_regenerate_id(true);
                $_SESSION['autenticado'] = true;
                header('Location: index.html');
                exit;
            }
            $error = 'No se pudo guardar la contraseña. Revisa los permisos de la carpeta datos/.';
        }
    } else {
        $clave = $_POST['clave'] ?? '';
        $cred = leerCredenciales();
        if ($cred && isset($cred['hash']) && password_verify($clave, $cred['hash'])) {
            session_regenerate_id(true);
            $_SESSION['autenticado'] = true;
            header('Location: index.html');
            exit;
        }
        $error = 'Contraseña incorrecta.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $esNuevo ? 'Crear contraseña' : 'Entrar' ?> · Gestor de tareas</title>
<link rel="icon" type="image/png" href="favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;450;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --fondo:#0D0E10; --panel:#131417; --superficie:#191B1F; --superficie-2:#1F2226;
    --texto:#F4F5F7; --texto-2:#AEB3BC; --texto-3:#787E88;
    --borde:rgba(255,255,255,.07); --borde-2:rgba(255,255,255,.13); --borde-3:rgba(255,255,255,.22);
    --claro:#F4F5F7; --alta:#E0656B;
    --radio-sm:9px;
    --s1:0 1px 2px rgba(0,0,0,.4);
    --s2:0 2px 4px rgba(0,0,0,.3),0 8px 20px rgba(0,0,0,.32);
    --s4:0 8px 20px rgba(0,0,0,.45),0 32px 72px rgba(0,0,0,.6);
    --filo:inset 0 1px 0 rgba(255,255,255,.045);
    --sans:"Inter","Helvetica Neue",Arial,sans-serif;
  }
  *{box-sizing:border-box;}
  html,body{margin:0;padding:0;height:100%;}
  body{
    background:var(--fondo);color:var(--texto);font-family:var(--sans);
    font-size:15px;line-height:1.55;letter-spacing:-.005em;
    -webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;
    display:flex;align-items:center;justify-content:center;min-height:100%;padding:24px;
  }
  .panel{
    background:var(--superficie);border:1px solid var(--borde-2);border-radius:15px;
    box-shadow:var(--s4),var(--filo);width:100%;max-width:376px;padding:32px 30px;
  }
  h1{font-size:19px;font-weight:600;margin:0 0 7px;letter-spacing:-.025em;}
  p.sub{color:var(--texto-3);font-size:13.5px;margin:0 0 24px;line-height:1.5;}
  label{
    display:block;font-size:10.5px;font-weight:600;color:var(--texto-3);margin-bottom:7px;
    letter-spacing:.09em;text-transform:uppercase;
  }
  input[type=password]{
    width:100%;border:1px solid var(--borde-2);border-radius:var(--radio-sm);
    padding:10px 12px;background:var(--panel);font-size:14px;margin-bottom:16px;
    font-family:inherit;color:var(--texto);letter-spacing:inherit;
  }
  input[type=password]:hover{border-color:var(--borde-3);}
  input[type=password]:focus{outline:none;border-color:var(--borde-3);background:var(--fondo);box-shadow:0 0 0 3px rgba(255,255,255,.07);}
  button{
    width:100%;border:1px solid transparent;background:var(--claro);color:#141518;
    border-radius:var(--radio-sm);padding:11px;font-weight:550;font-size:14px;
    cursor:pointer;font-family:inherit;box-shadow:var(--s1),var(--filo);
  }
  button:hover{background:#fff;box-shadow:var(--s2);}
  button:active{background:#E4E6EA;box-shadow:none;}
  .error{
    background:rgba(224,101,107,.1);border:1px solid rgba(224,101,107,.3);color:var(--alta);
    border-radius:var(--radio-sm);padding:10px 12px;font-size:13px;margin-bottom:16px;
  }
</style>
</head>
<body>
  <form class="panel" method="post" autocomplete="off">
    <h1><?= $esNuevo ? 'Crea tu contraseña' : 'Bienvenido de nuevo' ?></h1>
    <p class="sub"><?= $esNuevo
        ? 'Es la primera vez que entras. Elige una contraseña para proteger tus proyectos y tareas.'
        : 'Introduce tu contraseña para entrar.' ?></p>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <label for="clave">Contraseña</label>
    <input type="password" id="clave" name="clave" minlength="8" required autofocus>
    <?php if ($esNuevo): ?>
      <label for="confirmar">Confirmar contraseña</label>
      <input type="password" id="confirmar" name="confirmar" minlength="8" required>
    <?php endif; ?>
    <button type="submit"><?= $esNuevo ? 'Crear y entrar' : 'Entrar' ?></button>
  </form>
</body>
</html>
