<?php
// Pagina publica de ayuda: no requiere sesion
require_once __DIR__ . '/includes/auth.php';
iniciar_sesion();
$logueado = !empty($_SESSION['usuario_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ayuda — SRCI</title>
  <meta name="description" content="Guia de uso del Sistema de Reporte Ciudadano de Incidencias.">
  <link rel="stylesheet" href="/srci/assets/css/estilos.css?v=20261010e">
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
          <li>Opcionales: familias afectadas, servicio afectado, si la calle está intransitable, responsable/área y contacto del vecino</li>
          <li><strong>Ubicación</strong>: tocá el mini-mapa para poner el punto exacto (o marcá la ubicación tocando el mapa principal antes de reportar)</li>
          <li><strong>Foto</strong> (opcional, hasta 5 MB) y notas con más detalle</li>
        </ul>
      </li>
      <li>Tocá <strong>Enviar</strong> — ¡listo! Ya aparece en el mapa y en el listado</li>
    </ol>
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
      tipo, estado, barrio, gravedad y rango de fechas. Tocando una fila se expande con todos
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

  <!-- Contacto -->
  <div class="mensaje mensaje-exito" role="note">
    <span>🤝</span>
    <span>¿Dudas o problemas técnicos? Escribile a un organizador (Guillermo Guillaume, Mariana Marclay o Juan Martín Garay) y lo resolvemos.</span>
  </div>

</div>
</body>
</html>
