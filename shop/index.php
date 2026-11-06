<?php
$pageTitle = 'ECHO 3D LASER — Impresión 3D y Corte Láser';
$actual = 'inicio';
require __DIR__ . '/parts/header.php';

$categorias = db()->query('SELECT id, nombre, slug, icono FROM categorias ORDER BY posicion, id')->fetchAll();
?>
<section class="hero">
  <span class="hero-label">Fábrica Retro-futurista · Hecho a mano</span>
  <h1 class="hero-title">Impresión <em>3D</em> & Corte <em>Láser</em></h1>
  <p class="hero-subtitle">Tecnología con sello artesanal</p>
  <div class="hero-cta">
    <a class="btn btn-primary" href="catalogo.php">Ver catálogo</a>
    <a class="btn btn-secondary" href="<?= wa_link('¡Hola! Quiero hacer un pedido 😀') ?>" target="_blank" rel="noopener">Pedir por WhatsApp</a>
  </div>
  <div class="hero-doodles">🖨️ ✂️ ⚙️</div>
</section>

<section class="section">
  <div class="container">
    <span class="section-title">Materiales que usamos</span>
    <div class="marquee-wrap"><span class="marquee-note">rodando materiales…</span></div>
  </div>
</section>

<section class="section">
  <div class="container">
    <span class="section-title">¿Qué fabricamos?</span>
    <div class="icon-grid">
      <?php foreach ($categorias as $c): ?>
      <div class="icon-card">
        <div class="icon-circle"><?= htmlspecialchars($c['icono']) ?></div>
        <h3 class="icon-title"><?= htmlspecialchars($c['nombre']) ?></h3>
        <p class="icon-text"><a class="chip" href="catalogo.php?cat=<?= urlencode($c['slug']) ?>">Ver más →</a></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" style="background:var(--blanco);border-radius:var(--radio-lg)">
  <div class="container">
    <div class="section-center">
      <span class="section-title">Destacados de la fábrica</span>
    </div>
    <div class="prod-grid" id="featured-grid"></div>
  </div>
</section>

<section class="section">
  <div class="container">
    <span class="section-title">Cómo funciona</span>
    <div class="steps">
      <div class="step"><span class="step-num">1</span><b>Elegís</b><p>Sumás al carrito lo que necesitás.</p></div>
      <div class="step"><span class="step-num">2</span><b>Confirmamos</b><p>Cerramos diseño, material y medidas.</p></div>
      <div class="step"><span class="step-num">3</span><b>Fabricamos</b><p>Imprimimos o cortamos con cariño de máquina.</p></div>
      <div class="step"><span class="step-num">4</span><b>Recibís</b><p>Retiro o envío. Listo para usar.</p></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="highlight">
      <div class="display-title" style="font-size:clamp(1.6rem,4vw,2.6rem)">¿Tenés una idea rara?<br><strong>La fabricamos.</strong></div>
      <p class="text-large" style="color:var(--petroleo);margin:14px 0 24px;max-width:56ch">Mandanos una foto, un dibujo o medio boceto. Si se puede imprimir o cortar, lo hacemos realidad.</p>
      <a class="btn btn-primary" style="position:relative;z-index:2" href="<?= wa_link('¡Hola! Tengo una idea para fabricar 😀') ?>" target="_blank" rel="noopener">Contanos tu idea</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/parts/footer.php'; ?>