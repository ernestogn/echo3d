<?php
$pageTitle = 'Mi cuenta — ECHO 3D LASER';
$actual = '';
require __DIR__ . '/parts/header.php';
?>
<section class="page-hero">
  <h1>Mi cuenta</h1>
  <p>Próximamente</p>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="card section-center">
      <div style="font-size:3rem">🛸</div>
      <h2 class="display-title" style="font-size:1.8rem">Estamos construyendo esta nave</h2>
      <p class="text-large" style="margin-inline:auto">Las cuentas de clientes llegan pronto. Mientras tanto, todo se coordina por <a href="<?= wa_link('¡Hola! Quiero consultar por un producto') ?>" target="_blank" rel="noopener">WhatsApp</a>.</p>
      <a class="btn btn-primary" href="catalogo.php">Volver al catálogo</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/parts/footer.php'; ?>