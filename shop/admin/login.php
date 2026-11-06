<?php
require_once __DIR__ . '/../db.php';
session_start();

if (!empty($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

$err = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $u = trim($_POST['usuario'] ?? '');
    $p = $_POST['clave'] ?? '';
    if (hash_equals(ADMIN_USER, $u) && password_verify($p, ADMIN_PASS_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: index.php');
        exit;
    }
    $err = 'Usuario o contraseña incorrectos.';
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · <?= SITE_NOMBRE ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="admin-body">
<div class="login-wrap">
  <div class="login-box">
    <h1>⚙️ ECHO 3D <br><span style="color:var(--naranja)">Panel</span></h1>
    <?php if ($err): ?><div class="admin-msg err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post" action="login.php">
      <label>Usuario
        <input type="text" name="usuario" required autofocus autocomplete="username">
      </label>
      <label>Contraseña
        <input type="password" name="clave" required autocomplete="current-password">
      </label>
      <button class="btn btn-primary" type="submit" style="width:100%">Entrar</button>
    </form>
    <p style="margin-top:16px;font-size:.8rem;color:var(--petroleo)">Solo personal autorizado. <a href="../index.php">← Volver al sitio</a></p>
  </div>
</div>
</body>
</html>