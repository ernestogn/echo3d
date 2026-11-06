<?php
$pageTitle = 'Contacto — ECHO 3D LASER';
$actual = 'contacto';
require __DIR__ . '/parts/header.php';
?>
<section class="page-hero">
  <h1>Contacto</h1>
  <p>Hablá con la fábrica</p>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="contact-grid">
      <div class="card">
        <span class="label">// vías directas</span>
        <ul class="info-list" style="margin-top:14px">
          <li><span class="ic">💬</span><div><b>WhatsApp</b><span><a href="<?= wa_link('¡Hola! Tengo una consulta') ?>" target="_blank" rel="noopener"><?= SITE_CELULAR ?></a></span></div></li>
          <li><span class="ic">✉️</span><div><b>Email</b><span><a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a></span></div></li>
          <li><span class="ic">🕘</span><div><b>Horario</b><span>Lunes a sábados, de 9 a 19 hs.</span></div></li>
          <li><span class="ic">📍</span><div><b>Taller</b><span>(Dirección por confirmar — hacemos envíos)</span></div></li>
        </ul>
        <div class="highlight" style="margin-top:18px">
          <div style="font-family:var(--font-titulo);text-transform:uppercase">¿Urgente?</div>
          <p style="margin:8px 0 0;color:var(--petroleo)">El canal más rápido es WhatsApp.</p>
          <a class="btn btn-wa" style="margin-top:14px;position:relative;z-index:2" href="<?= wa_link('¡Hola! Consulta rápida 😀') ?>" target="_blank" rel="noopener">Escribinos ahora</a>
        </div>
      </div>

      <div class="card">
        <span class="label">// formulario</span>
        <form class="form" id="contact-form" style="margin-top:12px">
          <div class="form-msg" role="status"></div>
          <label>Nombre
            <input type="text" name="nombre" required placeholder="Tu nombre">
          </label>
          <label>Email
            <input type="email" name="email" required placeholder="tucorreo@ejemplo.com">
          </label>
          <label>Teléfono (opcional)
            <input type="tel" name="telefono" placeholder="+54 9 …">
          </label>
          <label>Mensaje
            <textarea name="mensaje" rows="5" required placeholder="Contanos tu idea o consulta…"></textarea>
          </label>
          <button class="btn btn-primary" type="submit">Enviar mensaje</button>
        </form>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/parts/footer.php'; ?>