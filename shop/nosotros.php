<?php
$pageTitle = 'Nosotros — ECHO 3D LASER';
$actual = 'nosotros';
require __DIR__ . '/parts/header.php';
?>
<section class="page-hero">
  <h1>Nosotros</h1>
  <p>La fábrica de las máquinas simpáticas</p>
</section>

<section class="section section-grid" style="padding-top:0">
  <div class="card">
    <span class="label">// historia</span>
    <h3 class="display-title" style="font-size:1.6rem">De un taller a una fábrica de ideas</h3>
    <p>ECHO 3D LASER nació como un taller de barrio donde se juntaban una impresora, un láser y muchas ganas de fabricar cosas que no existen en ningún local.</p>
    <p>Cada pieza que sale de acá tiene manos (y máquinas) atrás: la diseñamos, la imprimimos o la cortamos con el mismo estándar: <strong>que quede bien, que dure y que se note el cariño.</strong></p>
    <p>Somos de ese estilo artesanal pero con tecnología — una estética de vieja ciencia ficción aplicada a objetos reales.</p>
  </div>

  <div class="card" style="background:var(--petroleo-oscuro);color:var(--crema);border:var(--linea-media)">
    <span class="label" style="color:var(--verde-lima)">// valores</span>
    <ul class="info-list">
      <li><span class="ic">🛠️</span><div><b>Hecho bien a la primera</b><span>Revisamos cada pieza antes de que llegue a tus manos.</span></div></li>
      <li><span class="ic">🎨</span><div><b>Sin diseños genéricos</b><span>Todo se puede personalizar. Si no existe, lo creamos.</span></div></li>
      <li><span class="ic">⚡</span><div><b>Rapidez honesta</b><span>Te decimos plazos reales y los cumplimos.</span></div></li>
      <li><span class="ic">♻️</span><div><b>Menos desperdicio</b><span>Optimizamos material y reciclamos filamentos.</span></div></li>
    </ul>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="highlight">
      <div class="display-title" style="font-size:clamp(1.6rem,4vw,2.6rem)">Impresión 3D + corte láser<br><strong>en un solo lugar.</strong></div>
      <p class="text-large" style="color:var(--petroleo);margin:14px 0 24px;max-width:58ch">Figuras, repuestos, cartelería, regalos, souvenirs… Si está en tu cabeza, lo ponemos en la impresora.</p>
      <a class="btn btn-primary" style="position:relative;z-index:2" href="catalogo.php">Ir al catálogo</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/parts/footer.php'; ?>