<?php
require_once __DIR__ . '/../db.php';
$pageTitle = $pageTitle ?? SITE_NOMBRE;
$actual = $actual ?? 'inicio';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="description" content="Impresión 3D, corte láser y grabados personalizados. Tecnología con sello artesanal.">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='42' fill='%23e99619' stroke='%2317363a' stroke-width='7'/><circle cx='50' cy='50' r='18' fill='%23f1e9d4' stroke='%2317363a' stroke-width='6'/></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>

<nav class="nav">
  <div class="nav-inner">
    <a class="logo" href="index.php">
      <span class="logo-badge">⚙️</span>
      <span class="logo-txt"><b>ECHO 3D</b><span>LASER · FÁBRICA</span></span>
    </a>
    <button class="hamburger" aria-label="Menú">☰</button>
    <div class="nav-links">
      <a href="index.php" class="<?= $actual === 'inicio' ? 'actual' : '' ?>">Inicio</a>
      <a href="catalogo.php" class="<?= $actual === 'catalogo' ? 'actual' : '' ?>">Catálogo</a>
      <a href="nosotros.php" class="<?= $actual === 'nosotros' ? 'actual' : '' ?>">Nosotros</a>
      <a href="contacto.php" class="<?= $actual === 'contacto' ? 'actual' : '' ?>">Contacto</a>
    </div>
    <button class="cart-btn" data-open-cart title="Carrito">🛒<span class="cart-count">0</span></button>
  </div>
</nav>

<main>
<div class="container" style="padding-top:26px">