<?php
declare(strict_types=1);

/**
 * Generador de Reportes y Analisis Territorial (SRCI).
 *
 * Pantalla principal del modulo. Es un formulario GET: cada envio (o preset)
 * recalcula en el servidor los KPIs, las distribuciones y la previsualizacion.
 * No usa AJAX ni dependencias externas.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/funciones_reportes.php';

requiere_admin();

$presets = definicion_presets_reportes();
$filtros = filtros_desde_entrada_reporte($_GET);
$preset_activo = strtolower(trim((string)($_GET['preset'] ?? '')));
if (!isset($presets[$preset_activo])) {
  $preset_activo = '';
}

['where' => $where_sql, 'params' => $params] = construir_consulta_reportes($filtros);

$kpis        = obtener_kpis_reporte($where_sql, $params);
$distribucion = obtener_distribucion_reporte($where_sql, $params);
$preview     = obtener_incidencias_reporte(
  $where_sql,
  $params,
  (string)$filtros['orden'],
  (string)$filtros['dir'],
  REPORTES_LIMITE_PREVIEW
);

$mapa_categorias = categorias_por_incidencia(array_column($preview, 'id'));
$query_salida    = http_build_query(parametros_filtros_reporte($filtros));

// Catalogos para los selectores
$barrios = db()->query(
  'SELECT id, nombre, categoria FROM barrios WHERE activo = 1 ORDER BY nombre'
)->fetchAll();

$tipos = db()->query(
  'SELECT id, clave, nombre, icono FROM tipos_incidencia WHERE activo = 1 ORDER BY nombre'
)->fetchAll();

$categorias = categorias_activas();

$responsables = db()->query(
  "SELECT DISTINCT responsable_area
     FROM incidencias
    WHERE responsable_area IS NOT NULL AND responsable_area <> ''
    ORDER BY responsable_area"
)->fetchAll(PDO::FETCH_COLUMN);

$seleccionado = static fn(array $lista, int|string $valor): string => in_array($valor, $lista, true) ? 'selected' : '';
$marcado      = static fn(bool $valor): string => $valor ? 'checked' : '';

$titulo_periodo = periodo_legible_reporte($filtros);
$usuario_nombre = (string)($_SESSION['nombre'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Generador de reportes · SRCI</title>
  <link rel="stylesheet" href="/srci/assets/css/estilos.css?v=20261103">
  <style>
    .rep-presets { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:1.25rem; }
    .rep-preset { display:inline-flex; align-items:center; gap:.45rem; padding:.5rem .85rem; border-radius:999px; border:1.5px solid var(--color-borde); background:var(--color-superficie); color:var(--color-texto); font-size:.85rem; font-weight:600; text-decoration:none; transition:all var(--transicion); }
    .rep-preset:hover { border-color:var(--color-acento); color:var(--color-acento); text-decoration:none; }
    .rep-preset.activo { background:var(--color-acento); border-color:var(--color-acento); color:#fff; }
    .rep-panel { background:var(--color-superficie); border:1px solid var(--color-borde); border-radius:var(--radio-md); padding:var(--espacio-lg); margin-bottom:1.25rem; }
    .rep-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:var(--espacio-md); align-items:end; }
    .rep-grid .campo { min-width:0; }
    .rep-checks { display:flex; flex-wrap:wrap; gap:.75rem 1.25rem; margin-top:.25rem; }
    .rep-checks label { display:inline-flex; align-items:center; gap:.4rem; font-size:.85rem; color:var(--color-texto); cursor:pointer; }
    .rep-checks input[type="checkbox"] { width:16px; height:16px; accent-color:var(--color-acento); }
    .rep-atajos { display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.35rem; }
    .rep-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:.75rem; margin-bottom:1.25rem; }
    .rep-kpi { background:var(--color-superficie); border:1px solid var(--color-borde); border-radius:var(--radio-md); padding:.9rem 1rem; }
    .rep-kpi-valor { font-size:1.5rem; font-weight:800; line-height:1.1; color:var(--color-texto); }
    .rep-kpi-etiqueta { font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:var(--color-texto-suave); font-weight:700; margin-top:.25rem; }
    .rep-kpi.acento .rep-kpi-valor { color:var(--color-acento); }
    .rep-kpi.verde .rep-kpi-valor { color:var(--color-verde); }
    .rep-kpi.rojo .rep-kpi-valor { color:var(--color-rojo); }
    .rep-dos-columnas { display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:1rem; margin-bottom:1.25rem; }
    .rep-bloque { background:var(--color-superficie); border:1px solid var(--color-borde); border-radius:var(--radio-md); padding:var(--espacio-lg); }
    .rep-bloque h2 { font-size:.95rem; margin:0 0 .75rem; }
    .rep-barra { display:grid; grid-template-columns:1fr 92px; gap:.5rem; align-items:center; margin-bottom:.45rem; font-size:.82rem; }
    .rep-barra-pista { grid-column:1 / -1; height:8px; border-radius:999px; background:var(--color-fondo); overflow:hidden; }
    .rep-barra-relleno { display:block; height:100%; border-radius:999px; background:var(--color-acento); }
    .rep-barra-dato { text-align:right; color:var(--color-texto-suave); white-space:nowrap; }
    .rep-acciones { display:flex; flex-wrap:wrap; gap:.5rem; margin:1.25rem 0; }
    .rep-aviso { background:rgba(217,119,6,.12); border:1px solid rgba(217,119,6,.35); color:#92400e; border-radius:var(--radio-md); padding:.75rem 1rem; font-size:.85rem; margin-bottom:1rem; }
    .rep-vacio { color:var(--color-texto-suave); font-size:.9rem; padding:.5rem 0; }
    .rep-cat { display:inline-block; margin:0 .25rem .2rem 0; padding:.1rem .45rem; border-radius:999px; font-size:.72rem; font-weight:600; background:var(--color-fondo); border:1px solid var(--color-borde); }
    .rep-tabla-scroll { overflow-x:auto; }
    @media (max-width:640px) { .rep-barra { grid-template-columns:1fr; } .rep-barra-dato { text-align:left; } }
  </style>
</head>
<body>
<nav class="nav-principal">
  <div class="nav-contenido">
    <a href="/srci/index.php" class="nav-logo"><span class="nav-logo-icono">🗺️</span>SRCI</a>
    <div class="nav-enlaces">
      <a href="/srci/index.php"       class="nav-enlace">Mapa</a>
      <a href="/srci/incidencias.php" class="nav-enlace">Listado</a>
      <a href="/srci/admin/reportes.php" class="nav-enlace activo">Admin</a>
    </div>
    <div class="nav-usuario">
      <a href="/srci/perfil.php" class="nav-perfil">👤 <?= esc($usuario_nombre) ?></a>
      <a href="/srci/logout.php" class="boton boton-secundario boton-sm">Salir</a>
    </div>
  </div>
</nav>

<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="admin-sidebar-titulo">Panel de administración</div>
    <a href="/srci/admin/reportes.php" class="admin-nav-item">📋 Reportes</a>
    <a href="/srci/admin/reportes_generador.php" class="admin-nav-item activo">📊 Generador</a>
    <a href="/srci/admin/usuarios.php" class="admin-nav-item">👥 Usuarios</a>
    <a href="/srci/admin/tipos.php"    class="admin-nav-item">🏷️ Tipos</a>
    <a href="/srci/admin/categorias.php" class="admin-nav-item">🗂️ Categorías</a>
    <a href="/srci/admin/barrios.php"  class="admin-nav-item">🏙️ Barrios</a>
    <a href="/srci/admin/auditoria.php" class="admin-nav-item">🧾 Auditoría</a>
  </aside>

  <main class="admin-main">
    <div class="pagina-encabezado">
      <h1>Generador de reportes y cuadrillas</h1>
      <span style="color:var(--color-texto-suave);font-size:.9rem;">
        <?= (int)$kpis['total'] ?> casos · <?= esc($titulo_periodo) ?>
      </span>
    </div>

    <div class="rep-presets" aria-label="Presets rápidos">
      <?php foreach ($presets as $clave => $preset): ?>
        <a href="/srci/admin/reportes_generador.php?preset=<?= esc($clave) ?>"
           class="rep-preset <?= $preset_activo === $clave ? 'activo' : '' ?>"
           title="<?= esc((string)$preset['descripcion']) ?>">
          <span aria-hidden="true"><?= esc((string)$preset['icono']) ?></span>
          <?= esc((string)$preset['titulo']) ?>
        </a>
      <?php endforeach; ?>
      <a href="/srci/admin/reportes_generador.php" class="rep-preset">🧹 Limpiar filtros</a>
    </div>

    <form method="GET" action="/srci/admin/reportes_generador.php" class="rep-panel" id="form-reportes">
      <div class="rep-grid">
        <div class="campo">
          <label for="rep-fecha-desde">Desde</label>
          <input type="date" id="rep-fecha-desde" name="fecha_desde" value="<?= esc((string)$filtros['fecha_desde']) ?>">
          <div class="rep-atajos">
            <button type="button" class="boton boton-secundario boton-sm" data-rep-fecha="hoy">Hoy</button>
            <button type="button" class="boton boton-secundario boton-sm" data-rep-fecha="7">7 días</button>
            <button type="button" class="boton boton-secundario boton-sm" data-rep-fecha="30">30 días</button>
            <button type="button" class="boton boton-secundario boton-sm" data-rep-fecha="mes">Mes actual</button>
          </div>
        </div>

        <div class="campo">
          <label for="rep-fecha-hasta">Hasta</label>
          <input type="date" id="rep-fecha-hasta" name="fecha_hasta" value="<?= esc((string)$filtros['fecha_hasta']) ?>">
        </div>

        <div class="campo">
          <label for="rep-barrios">Barrios</label>
          <select id="rep-barrios" name="barrios[]" multiple size="5">
            <?php foreach ($barrios as $barrio): ?>
              <option value="<?= (int)$barrio['id'] ?>" <?= $seleccionado($filtros['barrios'], (int)$barrio['id']) ?>>
                <?= esc((string)$barrio['nombre']) ?> · <?= esc(nombre_categoria($barrio['categoria'] ?? null)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="rep-barrio-categoria">Tipo de barrio</label>
          <select id="rep-barrio-categoria" name="barrio_categoria">
            <option value="">Todos</option>
            <?php foreach (REPORTES_CATEGORIAS_BARRIO as $cat): ?>
              <option value="<?= esc($cat) ?>" <?= $seleccionado([$filtros['barrio_categoria']], $cat) ?>>
                <?= esc(nombre_categoria($cat)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="rep-estados">Estado</label>
          <select id="rep-estados" name="estados[]" multiple size="4">
            <?php foreach (REPORTES_ESTADOS_VALIDOS as $estado): ?>
              <option value="<?= esc($estado) ?>" <?= $seleccionado($filtros['estados'], $estado) ?>>
                <?= esc(nombre_estado($estado)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="rep-gravedades">Gravedad</label>
          <select id="rep-gravedades" name="gravedades[]" multiple size="4">
            <?php foreach (REPORTES_GRAVEDADES_VALIDAS as $gravedad): ?>
              <option value="<?= esc($gravedad) ?>" <?= $seleccionado($filtros['gravedades'], $gravedad) ?>>
                <?= esc(nombre_gravedad($gravedad)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="rep-dias">Días abiertos (mínimo)</label>
          <input type="number" id="rep-dias" name="dias_min" min="0" max="3650" step="1"
                 value="<?= $filtros['dias_min'] > 0 ? (int)$filtros['dias_min'] : '' ?>"
                 placeholder="Ej: 15">
        </div>

        <div class="campo">
          <label for="rep-responsable">Área responsable</label>
          <select id="rep-responsable" name="responsable_area">
            <option value="">Todas</option>
            <?php foreach ($responsables as $responsable): ?>
              <option value="<?= esc((string)$responsable) ?>" <?= $seleccionado([$filtros['responsable_area']], (string)$responsable) ?>>
                <?= esc((string)$responsable) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="rep-categorias">Categoría de incidencia</label>
          <select id="rep-categorias" name="categorias[]" multiple size="5">
            <?php foreach ($categorias as $categoria): ?>
              <option value="<?= (int)$categoria['id'] ?>" <?= $seleccionado($filtros['categorias'], (int)$categoria['id']) ?>>
                <?= esc(($categoria['icono'] ?? '') . ' ' . $categoria['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="rep-tipos">Tipo de problema</label>
          <select id="rep-tipos" name="tipos[]" multiple size="5">
            <?php foreach ($tipos as $tipo): ?>
              <option value="<?= (int)$tipo['id'] ?>" <?= $seleccionado($filtros['tipos'], (int)$tipo['id']) ?>>
                <?= esc(($tipo['icono'] ?? '') . ' ' . $tipo['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo" style="grid-column:1 / -1;">
          <label for="rep-texto">Búsqueda libre (calle, notas o contacto)</label>
          <input type="search" id="rep-texto" name="texto" maxlength="120"
                 value="<?= esc((string)$filtros['texto']) ?>" placeholder="Ej: San Martín, inundación, referente...">
        </div>
      </div>

      <div class="rep-checks">
        <label><input type="checkbox" name="familias_mayor_cero" value="1" <?= $marcado((bool)$filtros['familias_mayor_cero']) ?>> Familias afectadas (&gt; 0)</label>
        <label><input type="checkbox" name="calle_intransitable" value="1" <?= $marcado((bool)$filtros['calle_intransitable']) ?>> Calles cortadas</label>
        <label><input type="checkbox" name="con_foto" value="1" <?= $marcado((bool)$filtros['con_foto']) ?>> Con foto</label>
        <label><input type="checkbox" name="con_seguimiento" value="1" <?= $marcado((bool)$filtros['con_seguimiento']) ?>> Con seguimiento</label>
        <label><input type="checkbox" name="incluir_pendientes" value="1" <?= $marcado((bool)$filtros['incluir_pendientes']) ?>> Incluir pendientes de aprobación</label>
      </div>

      <div class="rep-grid" style="margin-top:1rem;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
        <div class="campo">
          <label for="rep-orden">Ordenar por</label>
          <select id="rep-orden" name="orden">
            <?php
              $ordenes = [
                'fecha'    => 'Fecha',
                'barrio'   => 'Barrio',
                'gravedad' => 'Gravedad',
                'estado'   => 'Estado',
                'dias'     => 'Días abiertos',
                'familias' => 'Familias afectadas',
                'id'       => 'Número',
              ];
            ?>
            <?php foreach ($ordenes as $clave => $etiqueta): ?>
              <option value="<?= esc($clave) ?>" <?= $seleccionado([$filtros['orden']], $clave) ?>><?= esc($etiqueta) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="rep-dir">Dirección</label>
          <select id="rep-dir" name="dir">
            <option value="desc" <?= $seleccionado([$filtros['dir']], 'desc') ?>>Descendente</option>
            <option value="asc"  <?= $seleccionado([$filtros['dir']], 'asc') ?>>Ascendente</option>
          </select>
        </div>
        <div class="campo">
          <label>&nbsp;</label>
          <button type="submit" class="boton boton-primario">Aplicar filtros</button>
        </div>
      </div>
    </form>

    <div class="rep-kpis">
      <div class="rep-kpi acento">
        <div class="rep-kpi-valor"><?= (int)$kpis['total'] ?></div>
        <div class="rep-kpi-etiqueta">Casos analizados</div>
      </div>
      <div class="rep-kpi verde">
        <div class="rep-kpi-valor"><?= esc((string)$kpis['tasa_resolucion']) ?>%</div>
        <div class="rep-kpi-etiqueta">Tasa de resolución</div>
      </div>
      <div class="rep-kpi verde">
        <div class="rep-kpi-valor"><?= (int)$kpis['resueltos'] ?></div>
        <div class="rep-kpi-etiqueta">Resueltos</div>
      </div>
      <div class="rep-kpi">
        <div class="rep-kpi-valor"><?= (int)$kpis['en_proceso'] ?></div>
        <div class="rep-kpi-etiqueta">En gestión</div>
      </div>
      <div class="rep-kpi">
        <div class="rep-kpi-valor"><?= (int)$kpis['pendientes'] ?></div>
        <div class="rep-kpi-etiqueta">Pendientes</div>
      </div>
      <div class="rep-kpi rojo">
        <div class="rep-kpi-valor"><?= (int)$kpis['familias_afectadas'] ?></div>
        <div class="rep-kpi-etiqueta">Familias afectadas</div>
      </div>
      <div class="rep-kpi rojo">
        <div class="rep-kpi-valor"><?= (int)$kpis['calles_cortadas'] ?></div>
        <div class="rep-kpi-etiqueta">Calles cortadas</div>
      </div>
      <div class="rep-kpi">
        <div class="rep-kpi-valor"><?= esc((string)$kpis['promedio_dias']) ?></div>
        <div class="rep-kpi-etiqueta">Promedio de días</div>
      </div>
    </div>

    <?php if ((int)$kpis['total'] > REPORTES_LIMITE_IMPRESION): ?>
      <div class="rep-aviso">
        El resultado supera el límite de impresión de <?= (int)REPORTES_LIMITE_IMPRESION ?> casos.
        Las salidas imprimibles incluirán los primeros <?= (int)REPORTES_LIMITE_IMPRESION ?> según el orden elegido.
        Acotá el período o los filtros para el documento completo.
      </div>
    <?php endif; ?>

    <div class="rep-dos-columnas">
      <div class="rep-bloque">
        <h2>Distribución por tipo de problema</h2>
        <?php if (!$distribucion['por_tipo']): ?>
          <p class="rep-vacio">Sin datos para los filtros aplicados.</p>
        <?php else: ?>
          <?php foreach ($distribucion['por_tipo'] as $item): ?>
            <?php $pct = $kpis['total'] > 0 ? round(($item['total'] / $kpis['total']) * 100, 1) : 0; ?>
            <div class="rep-barra">
              <span><?= esc($item['etiqueta']) ?></span>
              <span class="rep-barra-dato"><?= (int)$item['total'] ?> · <?= esc((string)$pct) ?>%</span>
              <span class="rep-barra-pista"><span class="rep-barra-relleno" style="width:<?= esc((string)$pct) ?>%"></span></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="rep-bloque">
        <h2>Distribución por gravedad</h2>
        <?php if (!$distribucion['por_gravedad']): ?>
          <p class="rep-vacio">Sin datos para los filtros aplicados.</p>
        <?php else: ?>
          <?php foreach ($distribucion['por_gravedad'] as $item): ?>
            <?php
              $pct = $kpis['total'] > 0 ? round(($item['total'] / $kpis['total']) * 100, 1) : 0;
              $etiqueta = $item['etiqueta'] === 'sin_dato' ? 'Sin dato' : nombre_gravedad($item['etiqueta']);
            ?>
            <div class="rep-barra">
              <span><?= esc($etiqueta) ?></span>
              <span class="rep-barra-dato"><?= (int)$item['total'] ?> · <?= esc((string)$pct) ?>%</span>
              <span class="rep-barra-pista"><span class="rep-barra-relleno" style="width:<?= esc((string)$pct) ?>%"></span></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="rep-acciones">
      <a href="/srci/admin/imprimir_cuadrilla.php?<?= esc($query_salida) ?>" target="_blank" rel="noopener" class="boton boton-primario">
        📄 Imprimir hoja de cuadrilla (A4 B&amp;W)
      </a>
      <a href="/srci/admin/imprimir_dossier.php?<?= esc($query_salida) ?>" target="_blank" rel="noopener" class="boton boton-secundario">
        📊 Imprimir dossier ejecutivo (A4)
      </a>
      <a href="/srci/admin/exportar_reporte.php?<?= esc($query_salida) ?>&amp;formato=csv" class="boton boton-secundario">
        ⬇ CSV (Excel)
      </a>
      <a href="/srci/admin/exportar_reporte.php?<?= esc($query_salida) ?>&amp;formato=geojson" class="boton boton-secundario">
        ⬇ GeoJSON (QGIS)
      </a>
    </div>

    <div class="rep-bloque">
      <h2>Previsualización (primeros <?= (int)REPORTES_LIMITE_PREVIEW ?> resultados)</h2>
      <?php if (!$preview): ?>
        <p class="rep-vacio">No hay incidencias que coincidan con los filtros.</p>
      <?php else: ?>
        <div class="rep-tabla-scroll">
          <table class="tabla-incidencias">
            <thead>
              <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Barrio</th>
                <th>Dirección</th>
                <th>Tipo</th>
                <th>Categorías</th>
                <th>Gravedad</th>
                <th>Estado</th>
                <th>Días</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($preview as $fila): ?>
                <?php $cats = $mapa_categorias[(int)$fila['id']] ?? []; ?>
                <tr>
                  <td><a href="/srci/detalle.php?id=<?= (int)$fila['id'] ?>">#<?= (int)$fila['id'] ?></a></td>
                  <td style="white-space:nowrap;"><?= esc(fecha_legible((string)$fila['fecha_hora'])) ?></td>
                  <td><?= esc((string)($fila['barrio_nombre'] ?? '—')) ?></td>
                  <td><?= esc((string)($fila['direccion'] ?? '—')) ?></td>
                  <td><?= esc((string)($fila['tipo_nombre'] ?? '—')) ?></td>
                  <td>
                    <?php if ($cats): foreach ($cats as $cat): ?>
                      <span class="rep-cat"><?= esc(($cat['icono'] ?? '') . ' ' . $cat['nombre']) ?></span>
                    <?php endforeach; else: ?>—<?php endif; ?>
                  </td>
                  <td><span class="gravedad-badge <?= clase_gravedad($fila['gravedad'] ?? null) ?>"><?= esc(nombre_gravedad($fila['gravedad'] ?? null)) ?></span></td>
                  <td><span class="estado-badge <?= clase_estado((string)$fila['estado']) ?>"><?= esc(nombre_estado((string)$fila['estado'])) ?></span></td>
                  <td><?= dias_abiertos_reporte($fila) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<script>
  // Atajos de fecha (todo del lado del cliente, sin librerias)
  const isoFecha = (fecha) => {
    const y = fecha.getFullYear();
    const m = String(fecha.getMonth() + 1).padStart(2, '0');
    const d = String(fecha.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  };

  document.querySelectorAll('[data-rep-fecha]').forEach((boton) => {
    boton.addEventListener('click', () => {
      const desde = document.getElementById('rep-fecha-desde');
      const hasta = document.getElementById('rep-fecha-hasta');
      const hoy = new Date();
      let inicio = new Date(hoy);

      if (boton.dataset.repFecha === '7') {
        inicio.setDate(hoy.getDate() - 6);
      } else if (boton.dataset.repFecha === '30') {
        inicio.setDate(hoy.getDate() - 29);
      } else if (boton.dataset.repFecha === 'mes') {
        inicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
      }

      if (desde) desde.value = isoFecha(inicio);
      if (hasta) hasta.value = isoFecha(hoy);
    });
  });
</script>
</body>
</html>
