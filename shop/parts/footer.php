</main>

<div class="drawer-backdrop"></div>

<aside class="drawer" aria-label="Carrito">
  <div class="drawer-head">
    <h3>Tu pedido</h3>
    <button class="drawer-close" aria-label="Cerrar">✕</button>
  </div>
  <div class="drawer-body"></div>
  <div class="drawer-foot">
    <div class="cart-total"><span>Total</span><strong><span>$0</span></strong></div>
    <button class="btn btn-wa btn-block drawer-checkout">Pedir por WhatsApp</button>
  </div>
</aside>

<a class="wa-float" href="<?= wa_link('¡Hola! Quiero hacer un pedido 😀') ?>" target="_blank" rel="noopener" title="WhatsApp">💬</a>

<footer class="footer">
  <div class="footer-inner">
    <div>
      <h4><?= SITE_NOMBRE ?></h4>
      <p style="color:var(--crema);opacity:.85;max-width:40ch">Impresión 3D, corte láser y grabados personalizados. Una fábrica simpática con tecnología de otra época.</p>
    </div>
    <div>
      <h4>Sitio</h4>
      <ul>
        <li><a href="index.php">Inicio</a></li>
        <li><a href="catalogo.php">Catálogo</a></li>
        <li><a href="nosotros.php">Nosotros</a></li>
        <li><a href="mi-cuenta.php">Mi cuenta</a></li>
      </ul>
    </div>
    <div>
      <h4>Contacto</h4>
      <ul>
        <li><a href="<?= wa_link('¡Hola! Tengo una consulta') ?>" target="_blank" rel="noopener"><?= SITE_CELULAR ?></a></li>
        <li><a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a></li>
        <li>Lunes a Sábados · 9 a 19 hs</li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">© <span data-year>2026</span> <?= SITE_NOMBRE ?> — Hecho con ⚙️ y ☕</div>
</footer>

<script src="assets/js/products.js"></script>
<script src="assets/js/cart.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>