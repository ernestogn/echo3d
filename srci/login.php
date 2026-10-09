<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funciones.php';

iniciar_sesion();

// Si ya esta logueado, redirigir al mapa
if (esta_logueado()) {
  header('Location: /srci/index.php');
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre = trim($_POST['nombre'] ?? '');
  $pin    = trim($_POST['pin'] ?? '');

  if ($nombre === '' || $pin === '') {
    $error = 'Ingresa tu nombre y PIN para continuar.';
  } else {
    $stmt = db()->prepare('SELECT id, nombre, pin_hash, rol, activo FROM usuarios WHERE nombre = :nombre LIMIT 1');
    $stmt->execute([':nombre' => $nombre]);
    $usuario = $stmt->fetch();

    if (!$usuario || !$usuario['activo']) {
      $error = 'Usuario no encontrado. Pedi acceso a un administrador.';
    } elseif (!password_verify($pin, $usuario['pin_hash'])) {
      $error = 'PIN incorrecto. Proba de nuevo.';
    } else {
      session_regenerate_id(true);
      $_SESSION['usuario_id'] = $usuario['id'];
      $_SESSION['nombre']     = $usuario['nombre'];
      $_SESSION['rol']        = $usuario['rol'];
      header('Location: /srci/index.php');
      exit;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ingresar — SRCI</title>
  <meta name="description" content="Acceso al Sistema de Reporte Ciudadano de Incidencias.">
  <link rel="stylesheet" href="/srci/assets/css/estilos.css">
</head>
<body>
<div class="login-pantalla">
  <div class="login-caja">

    <div class="login-logo">
      <span class="login-logo-icono" aria-hidden="true">🗺️</span>
      <span class="login-logo-nombre">SRCI</span>
      <span class="login-logo-subtitulo">Sistema de Reporte Ciudadano</span>
    </div>

    <div class="tarjeta">
      <h1 style="font-size:1.25rem;margin-bottom:var(--espacio-lg);">Ingresá a tu cuenta</h1>

      <?php if ($error !== ''): ?>
        <div class="mensaje mensaje-error" role="alert" style="margin-bottom:var(--espacio-lg);">
          <span>⚠️</span>
          <span><?= esc($error) ?></span>
        </div>
      <?php endif; ?>

      <form id="form-login" method="POST" action="/srci/login.php" novalidate>
        <input type="hidden" name="nombre" id="campo-nombre">
        <input type="hidden" name="pin"    id="campo-pin">

        <div class="campo" style="margin-bottom:var(--espacio-lg);">
          <label for="nombre-visible">Nombre de usuario</label>
          <input
            type="text"
            id="nombre-visible"
            name="_nombre_visible"
            autocomplete="username"
            placeholder="Tu nombre"
            required
            value="<?= esc($_POST['_nombre_visible'] ?? '') ?>"
          >
        </div>

        <!-- Display del PIN como puntos -->
        <div class="campo" style="margin-bottom:var(--espacio-sm);">
          <label>PIN (4 dígitos)</label>
          <div class="pin-display" id="pin-display" aria-label="PIN ingresado" aria-live="polite">
            <div class="pin-punto" id="pt0"></div>
            <div class="pin-punto" id="pt1"></div>
            <div class="pin-punto" id="pt2"></div>
            <div class="pin-punto" id="pt3"></div>
          </div>
        </div>

        <!-- Teclado numérico -->
        <div class="pin-teclado" id="pin-teclado" role="group" aria-label="Teclado numérico">
          <button type="button" class="pin-digito" data-digito="1" aria-label="1">1</button>
          <button type="button" class="pin-digito" data-digito="2" aria-label="2">2</button>
          <button type="button" class="pin-digito" data-digito="3" aria-label="3">3</button>
          <button type="button" class="pin-digito" data-digito="4" aria-label="4">4</button>
          <button type="button" class="pin-digito" data-digito="5" aria-label="5">5</button>
          <button type="button" class="pin-digito" data-digito="6" aria-label="6">6</button>
          <button type="button" class="pin-digito" data-digito="7" aria-label="7">7</button>
          <button type="button" class="pin-digito" data-digito="8" aria-label="8">8</button>
          <button type="button" class="pin-digito" data-digito="9" aria-label="9">9</button>
          <button type="button" class="pin-digito" id="btn-borrar" aria-label="Borrar último dígito">⌫</button>
          <button type="button" class="pin-digito" data-digito="0" aria-label="0">0</button>
          <button type="submit" class="pin-digito boton-primario" id="btn-enviar" disabled aria-label="Ingresar">✓</button>
        </div>
      </form>
    </div>

  </div>
</div>

<script src="/srci/assets/js/login.js"></script>
</body>
</html>
