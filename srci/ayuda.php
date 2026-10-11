<?php
// Pagina publica de ayuda: no requiere sesion
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funciones.php';
iniciar_sesion();
$logueado = !empty($_SESSION['usuario_id']);
$es_admin = $logueado && es_admin();

// --- Herramienta admin: activar usuarios inactivos y enviarles link, en lotes ---
define('LOTE_LINKS_TAMANO', 10);

// Condicion del lote: SOLO referentes (rol usuario) con email real.
// Los administradores se activan uno por uno desde Admin -> Usuarios.
// Emails no ruteables (dominio .local, ej: la cuenta semilla 'admin') quedan fuera.
$SQL_LOTE_WHERE = "activo = 0 AND rol = 'usuario' AND email IS NOT NULL AND email <> '' AND email NOT LIKE '%@%.local'";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'lote_links') {
  if (!$es_admin) {
    http_response_code(403);
    die('Solo administradores.');
  }
  validar_csrf();

  $inactivos = db()->query(
    'SELECT id, nombre, email FROM usuarios
      WHERE ' . $SQL_LOTE_WHERE . '
      ORDER BY id
      LIMIT ' . LOTE_LINKS_TAMANO
  )->fetchAll();

  $resultados = [];
  foreach ($inactivos as $u) {
    $pin = generar_pin();
    // activo=1 + pin nuevo (condicion activo=0 por si acaso: nunca re-activar un ya activo)
    db()->prepare('UPDATE usuarios SET activo = 1, pin_hash = :h WHERE id = :id AND activo = 0')
       ->execute([':h' => password_hash($pin, PASSWORD_DEFAULT), ':id' => (int)$u['id']]);
    $link    = generar_magic_link((int)$u['id']);
    $enviado = enviar_activacion_por_email($u['email'], $u['nombre'], $link, $pin);
    registrar_auditoria(
      (int)$_SESSION['usuario_id'],
      'activar',
      null,
      'Lote masivo: activo a ' . $u['nombre'] . ' (<' . $u['email'] . '>) — PIN nuevo + link de acceso (' . MAGIC_LINK_DIAS . ' dias)' . ($enviado ? '' : ' — EMAIL FALLO')
    );
    $resultados[] = ['nombre' => $u['nombre'], 'email' => $u['email'], 'ok' => $enviado];
  }

  // Flash + redirect (evita reenviar el lote al recargar)
  $_SESSION['lote_links_resultado'] = $resultados;
  header('Location: /srci/ayuda.php#herramientas');
  exit;
}

$resultado_lote = $_SESSION['lote_links_resultado'] ?? null;
unset($_SESSION['lote_links_resultado']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ayuda — SRCI</title>
  <meta name="description" content="Guia de uso del Sistema de Reporte Ciudadano de Incidencias.">
  <link rel="stylesheet" href="/srci/assets/css/estilos.css?v=20261011j">
</head>
<body>
<nav class="nav-principal" role="navigation">
  <div class="nav-contenido">
    <a href="/srci/index.php" class="nav-logo">
      <span class="nav-logo-icono" aria-hidden="true">🗺️</span>
      SRCI
    </a>
    <div class="nav-enlaces">
      <a href="/srci/index.php"       class="nav-enlace">Mapa</a>
      <a href="/srci/incidencias.php" class="nav-enlace">Listado</a>
      <a href="/srci/ayuda.php"       class="nav-enlace activo">Ayuda</a>
    </div>
    <div class="nav-usuario">
      <?php if ($logueado): ?>
        <a href="/srci/perfil.php" class="nav-perfil">👤 <?= esc((string)($_SESSION['nombre'] ?? '')) ?></a>
        <a href="/srci/logout.php" class="boton boton-secundario boton-sm">Salir</a>
      <?php else: ?>
        <a href="/srci/login.php" class="boton boton-primario boton-sm">Ingresar</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div style="max-width:860px;margin:0 auto;padding:var(--espacio-lg) var(--espacio-md) var(--espacio-xl);">

  <div class="pagina-encabezado">
    <h1>❓ Guía de uso</h1>
    <p style="color:var(--color-texto-suave);margin-top:var(--espacio-xs);">
      Sistema de Reporte Ciudadano de Incidencias — todo lo que necesitás saber en 5 minutos.
    </p>
  </div>

  <!-- 1. Que es -->
  <div class="tarjeta" style="margin-bottom:var(--espacio-lg);">
    <h2>🗺️ ¿Qué es el SRCI?</h2>
    <p style="color:var(--color-texto);line-height:1.7;">
      Es un mapa de la ciudad donde los referentes barriales marcan problemas urbanos:
      árboles caídos, alcantarillas tapadas, fugas de agua, cableado en riesgo, basurales, etc.
      Cada reporte queda geolocalizado, con foto y gravedad, para que el Municipio y los
      organizadores puedan gestionar y dar seguimiento hasta que se resuelva.
    </p>
  </div>

  <!-- 2. Como ingresar -->
  <div class="tarjeta" style="margin-bottom:var(--espacio-lg);">
    <h2>🔑 Cómo ingresar</h2>
    <ol style="line-height:2;color:var(--color-texto);padding-left:var(--espacio-lg);">
      <li>Entrá a <strong>echo3dlaser.com.ar/srci</strong></li>
      <li>Escribí tu <strong>email</strong> (el que te registraste)</li>
      <li>Tu <strong>PIN de 4 dígitos</strong> con el teclado en pantalla</li>
      <li>Tocá <strong>✓</strong> para entrar</li>
    </ol>
    <div class="mensaje mensaje-info" role="note">
      <span>💡</span>
      <span>El PIN te llegó por email cuando te dieron de alta. Si no lo encontrás, revisá la carpeta de <strong>spam / correo no deseado</strong> (viene de <em>admin@echo3dlaser.com.ar</em> con asunto "Tu PIN de acceso — SRCI").</span>
    </div>
    <p style="color:var(--color-texto-suave);line-height:1.7;">
      <strong>¿Perdiste o olvidaste el PIN?</strong> Pedile a un organizador que te genere uno nuevo:
      en segundos te llega otro email con un PIN nuevo (el anterior deja de funcionar).
    </p>
  </div>

  <!-- 3. Reportar -->
  <div class="tarjeta" style="margin-bottom:var(--espacio-lg);">
    <h2>📸 Cómo reportar una incidencia</h2>
    <ol style="line-height:2;color:var(--color-texto);padding-left:var(--espacio-lg);">
      <li>Tocá el botón celeste <strong>"＋ Reportar incidencia"</strong> (abajo a la derecha)</li>
      <li><strong>Paso 1:</strong> elegí el tipo de problema (🌳 árbol caído, 🕳️ alcantarilla, 💧 fuga de agua…)</li>
      <li><strong>Paso 2:</strong> completá los datos:
        <ul style="line-height:1.9;margin-top:var(--espacio-xs);">
          <li><strong>Barrio y dirección</strong> (obligatorios)</li>
          <li><strong>Gravedad</strong>: baja, media, alta o crítica (obligatoria)</li>
          <li><strong>Categorías</strong>: no tenés que elegirlas ahora — el equipo SRCI las asigna al revisar cada reporte</li>
          <li>Opcionales: familias afectadas, servicio afectado, si la calle está intransitable, responsable/área y contacto del vecino</li>
          <li><strong>Ubicación</strong>: tocá el mini-mapa para poner el punto exacto (o marcá la ubicación tocando el mapa principal antes de reportar)</li>
          <li><strong>Foto</strong> (opcional, hasta 5 MB) y notas con más detalle</li>
        </ul>
      </li>
      <li>Tocá <strong>Enviar</strong> — ¡listo! Ya aparece en el mapa y en el listado</li>
    </ol>
  </div>

  <!-- 3b. Categorias -->
  <div class="tarjeta" style="margin-bottom:var(--espacio-lg);">
    <h2>🗂️ Categorías</h2>
    <p style="color:var(--color-texto);line-height:1.7;">
      Además del <strong>tipo</strong> de problema, cada reporte puede llevar una o varias
      <strong>categorías</strong>. Son etiquetas <strong>independientes</strong> y
      <strong>no excluyentes</strong>: un mismo reporte puede ser, por ejemplo, de
      <em>Basura</em> y de <em>Agua y cloaca</em> a la vez.
    </p>
    <div style="display:flex;gap:var(--espacio-sm);flex-wrap:wrap;margin:var(--espacio-md) 0;">
      <span class="categoria-badge" style="--cat-color:#2563eb">🌧️ Climáticos</span>
      <span class="categoria-badge" style="--cat-color:#ea580c">🛣️ Calles rotas</span>
      <span class="categoria-badge" style="--cat-color:#d97706">💡 Luminarias</span>
      <span class="categoria-badge" style="--cat-color:#16a34a">🌳 Arbolado</span>
      <span class="categoria-badge" style="--cat-color:#64748b">🗑️ Basura</span>
      <span class="categoria-badge" style="--cat-color:#0891b2">💧 Agua y cloaca</span>
    </div>
    <ul style="line-height:1.9;color:var(--color-texto);">
      <li>A cada reporte se le asigna al menos una categoría. La de <strong>Climáticos</strong> es la que se aplica por defecto.</li>
      <li>Las categorías las asigna el <strong>equipo SRCI</strong> al crear o editar una incidencia; por ahora el vecino no las elige al reportar.</li>
      <li>En el <strong>mapa</strong> hay un <strong>desplegable</strong> para ver solo las incidencias de una categoría.</li>
      <li>En el <strong>listado</strong> podés filtrar por categoría y la columna "Categorías" muestra las de cada reporte.</li>
      <li>Las exportaciones <strong>CSV</strong> y <strong>GeoJSON</strong> también incluyen las categorías.</li>
    </ul>
  </div>

  <!-- 4. Colores del mapa -->
  <div class="tarjeta" style="margin-bottom:var(--espacio-lg);">
    <h2>🎨 Qué significan los colores</h2>
    <p style="color:var(--color-texto);margin-bottom:var(--espacio-md);">
      Cada punto del mapa tiene un color según su <strong>gravedad</strong>, y dentro muestra el
      <strong>emoji del tipo</strong> de problema:
    </p>
    <div style="display:flex;gap:var(--espacio-md);flex-wrap:wrap;">
      <span class="gravedad-badge gravedad-baja">🟢 Baja</span>
      <span class="gravedad-badge gravedad-media">🟡 Media</span>
      <span class="gravedad-badge gravedad-alta">🟠 Alta</span>
      <span class="gravedad-badge gravedad-critica">🔴 Crítica</span>
    </div>
    <p style="color:var(--color-texto);margin-top:var(--espacio-lg);line-height:1.7;">
      El <strong>estado</strong> se ve así:
    </p>
    <div style="display:flex;gap:var(--espacio-md);flex-wrap:wrap;margin-top:var(--espacio-sm);">
      <span class="estado-badge estado-pendiente">Pendiente — recién cargado</span>
      <span class="estado-badge estado-en_proceso">🔵 Anillo azul = en gestión</span>
      <span class="estado-badge estado-resuelto">⚪ Gris con ✓ = resuelto</span>
    </div>
    <p style="color:var(--color-texto-suave);margin-top:var(--espacio-md);line-height:1.7;">
      Así, de un vistazo, lo <strong>urgente</strong> salta a la vista (colores cálidos) y lo
      <strong>resuelto</strong> se apaga del mapa. Tocá cualquier punto para ver el detalle completo.
    </p>
  </div>

  <!-- 5. Listado -->
  <div class="tarjeta" style="margin-bottom:var(--espacio-lg);">
    <h2>📋 Buscar y filtrar</h2>
    <p style="color:var(--color-texto);line-height:1.7;">
      En la sección <strong>Listado</strong> ves todos los reportes en tabla. Podés filtrar por
      tipo, categoría, estado, barrio, gravedad y rango de fechas. Tocando una fila se expande con todos
      los detalles, y desde ahí entrás al detalle completo con fotos.
    </p>
    <p style="color:var(--color-texto);line-height:1.7;margin-top:var(--espacio-sm);">
      También podés <strong>descargar</strong> la información: botón <strong>CSV</strong> (para Excel)
      o <strong>GeoJSON</strong> (para mapas/GIS).
    </p>
  </div>

  <!-- 6. Editar -->
  <div class="tarjeta" style="margin-bottom:var(--espacio-lg);">
    <h2>✏️ Corregir un reporte</h2>
    <p style="color:var(--color-texto);line-height:1.7;">
      ¿Cargaste mal una dirección, la gravedad o querés agregar una foto mejor? Entrá al reporte
      y usá el botón <strong>Editar</strong> para corregir los datos. Todos los cambios quedan
      registrados con fecha y autor.
    </p>
  </div>

  <!-- 7. FAQ -->
  <div class="tarjeta" style="margin-bottom:var(--espacio-lg);">
    <h2>🙋 Preguntas frecuentes</h2>
    <dl style="line-height:1.8;color:var(--color-texto);">
      <dt style="font-weight:700;margin-top:var(--espacio-md);">No me llega el email con el PIN</dt>
      <dd>Esperá un par de minutos y revisá spam. Si sigue sin llegar, pedile a un organizador que te lo reenvíe ("Nuevo PIN").</dd>

      <dt style="font-weight:700;margin-top:var(--espacio-md);">Me dice que mi usuario está inactivo</dt>
      <dd>Tu cuenta todavía no fue activada. Avisale a un organizador: te la activan y te llega un PIN nuevo por email.</dd>

      <dt style="font-weight:700;margin-top:var(--espacio-md);">No encuentro mi barrio en la lista</dt>
      <dd>Avisale a un organizador para que lo agregue al sistema.</dd>

      <dt style="font-weight:700;margin-top:var(--espacio-md);">Cargué el punto mal ubicado</dt>
      <dd>Editá el reporte y mové el punto en el mini-mapa, o tocá el mapa principal en el lugar correcto antes de corregir.</dd>

      <dt style="font-weight:700;margin-top:var(--espacio-md);">¿Puedo entrar desde el celular?</dt>
      <dd>¡Sí! El sistema está pensado para usar desde el teléfono. Podés instalarlo como app: en el navegador, menú → "Agregar a pantalla de inicio".</dd>

      <dt style="font-weight:700;margin-top:var(--espacio-md);">¿Se borran los reportes?</dt>
      <dd>No. Los reportes nunca se borran: si algo se cargó de más, un organizador lo oculta. Queda todo registrado.</dd>
    </dl>
  </div>

  <!-- Herramientas admin: activacion masiva en lotes -->
  <?php if ($es_admin):
    $pendientes_email = (int)db()->query("SELECT COUNT(*) FROM usuarios WHERE {$SQL_LOTE_WHERE}")->fetchColumn();
    $sin_email_lista  = db()->query("SELECT nombre FROM usuarios WHERE activo = 0 AND (email IS NULL OR email = '') ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
    $historial_lotes  = db()->query("SELECT a.fecha_hora, a.detalle, u.nombre AS admin_nombre
                                       FROM auditoria a
                                  LEFT JOIN usuarios u ON u.id = a.usuario_id
                                      WHERE a.accion = 'activar' AND a.detalle LIKE 'Lote masivo:%'
                                   ORDER BY a.id DESC LIMIT 10")->fetchAll();
  ?>
  <div class="tarjeta" id="herramientas" style="margin-bottom:var(--espacio-lg);border-color:rgba(79,70,229,.35);">
    <h2>⚙️ Herramientas — activación masiva <span style="font-weight:400;color:var(--color-texto-suave);font-size:.85rem;">(solo organizadores)</span></h2>
    <p style="color:var(--color-texto);line-height:1.7;">
      Activa usuarios inactivos y les envía por email un <strong>link de acceso directo</strong>
      (válido <?= MAGIC_LINK_DIAS ?> días, entra sin PIN) más un <strong>PIN de respaldo</strong>.
      Solo procesa cuentas de <strong>referentes</strong> (rol usuario) con email válido; los
      <strong>administradores</strong> se activan uno por uno desde <a href="/srci/admin/usuarios.php">Admin → Usuarios</a>.
      Cada click procesa un lote de <?= LOTE_LINKS_TAMANO ?> usuarios; cada activación queda
      registrada en <a href="/srci/admin/auditoria.php">Auditoría</a> con autor, fecha y resultado del envío.
    </p>
    <p style="color:var(--color-texto);line-height:1.7;">
      📬 Pendientes con email: <strong><?= $pendientes_email ?></strong>
      <?php if ($sin_email_lista): ?>
        <br>🚫 Sin email (cargalo en <a href="/srci/admin/usuarios.php">Admin → Usuarios</a> para poder activarlos):
        <strong><?= count($sin_email_lista) ?></strong> — <?= esc(implode(', ', $sin_email_lista)) ?>
      <?php endif; ?>
    </p>

    <form method="POST" action="/srci/ayuda.php" onsubmit="return confirm('¿Activar y enviar link a los próximos <?= min(LOTE_LINKS_TAMANO, $pendientes_email) ?> usuarios inactivos con email?');">
      <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
      <input type="hidden" name="accion"     value="lote_links">
      <button type="submit" class="boton boton-primario" <?= $pendientes_email === 0 ? 'disabled' : '' ?>>
        🚀 Activar y enviar link (lote de <?= min(LOTE_LINKS_TAMANO, $pendientes_email) ?>)
      </button>
      <?php if ($pendientes_email === 0): ?>
        <span style="color:var(--color-verde);font-weight:600;">✅ No queda nadie pendiente con email</span>
      <?php endif; ?>
    </form>

    <?php if (is_array($resultado_lote) && $resultado_lote):
      $oks = count(array_filter($resultado_lote, fn($r) => $r['ok']));
    ?>
      <div class="mensaje mensaje-<?= $oks === count($resultado_lote) ? 'exito' : 'info' ?>" style="margin-top:var(--espacio-md);">
        <span><?= $oks === count($resultado_lote) ? '✅' : '⚠️' ?></span>
        <span>Último lote: <strong><?= $oks ?> de <?= count($resultado_lote) ?></strong> emails enviados.
        Los usuarios ya están activos; si algún email falló, reenvialo desde Admin → Usuarios (🔗 Link o Nuevo PIN).</span>
      </div>
      <div class="tabla-contenedor" style="margin-top:var(--espacio-md);">
        <table class="tabla-incidencias">
          <thead><tr><th>Usuario</th><th>Email</th><th>Resultado</th></tr></thead>
          <tbody>
            <?php foreach ($resultado_lote as $r): ?>
              <tr>
                <td><?= esc($r['nombre']) ?></td>
                <td><?= esc($r['email']) ?></td>
                <td>
                  <?php if ($r['ok']): ?>
                    <span class="estado-badge estado-resuelto">✅ Activado + link enviado</span>
                  <?php else: ?>
                    <span class="estado-badge estado-pendiente">❌ Email falló — activado igual</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($historial_lotes): ?>
      <h3 style="margin-top:var(--espacio-lg);">🧾 Últimos lotes (trazabilidad)</h3>
      <ul style="line-height:1.9;color:var(--color-texto);font-size:.88rem;padding-left:var(--espacio-lg);">
        <?php foreach ($historial_lotes as $h): ?>
          <li>
            <strong><?= esc(fecha_legible($h['fecha_hora'])) ?></strong> — <?= esc($h['detalle']) ?>
            <?php if ($h['admin_nombre']): ?><span style="color:var(--color-texto-suave);">(por <?= esc($h['admin_nombre']) ?>)</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Contacto -->
  <div class="mensaje mensaje-exito" role="note">
    <span>🤝</span>
    <span>¿Dudas o problemas técnicos? Escribile a un organizador y lo resolvemos.</span>
  </div>

</div>
</body>
</html>
