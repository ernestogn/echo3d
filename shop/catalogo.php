<?php
$pageTitle = 'Catálogo — ECHO 3D LASER';
$actual = 'catalogo';
require __DIR__ . '/parts/header.php';
?>
<section class="page-hero">
  <h1>Catálogo</h1>
  <p>Todo lo que sale de nuestra fábrica</p>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="filters"></div>
    <div class="prod-grid" id="catalog-grid"></div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(location.search);
  const cat = params.get('cat');
  // el estado de filtro inicial se setea después en App.filters
  window.__catInicial = cat;
});
</script>

<?php require __DIR__ . '/parts/footer.php'; ?>