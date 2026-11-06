<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_helpers.php';
$adminPage = $adminPage ?? '';
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin · <?= SITE_NOMBRE ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="admin-body">

<div class="admin-top">
  <div class="awrap">
    <h1>⚙️ ECHO 3D · Admin</h1>
    <nav>
      <a href="index.php" class="<?= $adminPage === 'home' ? 'actual' : '' ?>">Panel</a>
      <a href="productos.php" class="<?= $adminPage === 'productos' ? 'actual' : '' ?>">Productos</a>
      <a href="categorias.php" class="<?= $adminPage === 'categorias' ? 'actual' : '' ?>">Categorías</a>
      <a href="mensajes.php" class="<?= $adminPage === 'mensajes' ? 'actual' : '' ?>">Mensajes</a>
      <a href="../index.php" target="_blank" rel="noopener">Ver sitio</a>
      <a class="admin-btn peligro" href="logout.php" style="font-size:.8rem">Salir ⇢</a>
    </nav>
  </div>
</div>

<div class="admin-main">