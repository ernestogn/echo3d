<?php
declare(strict_types=1);

/**
 * Hoja de cuadrilla / orden de trabajo (A4 vertical, blanco y negro estricto).
 *
 * Pensada para imprimir y salir a la calle: alto contraste, casillas para
 * tildar con lapicera, lineas para observaciones y bloques de firma.
 * Tope de REPORTES_LIMITE_IMPRESION casos para no generar documentos enormes.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/funciones_reportes.php';

requiere_admin();

$filtros = normalizar_filtros_reportes($_GET);
['where' => $where_sql, 'params' => $params] = construir_consulta_reportes($filtros);

$kpis = obtener_kpis_reporte($where_sql, $params);
$filas = obtener_incidencias_reporte(
  $where_sql,
  $params,
  'cuadrilla',
  'asc',
  REPORTES_LIMITE_IMPRESION
);

$mapa_categorias = categorias_por_incidencia(array_column($filas, 'id'));

registrar_salida_reporte('generar_reporte', 'Hoja de cuadrilla', $filtros, count($filas));

$grupos = [];
foreach ($filas as $fila) {
  $barrio = trim((string)($fila['barrio_nombre'] ?? ''));
  if ($barrio === '') {
    $barrio = 'Sin barrio asignado';
  }
  $grupos[$barrio][] = $fila;
}

$periodo     = periodo_legible_reporte($filtros);
$total       = (int)$kpis['total'];
$impresos    = count($filas);
$emisor      = (string)($_SESSION['nombre'] ?? '');
// Hora local de la base (PHP puede correr en UTC): evita 3 h de desfasaje.
$hoy         = (string)db()->query("SELECT DATE_FORMAT(NOW(), '%d/%m/%Y %H:%i')")->fetchColumn();
$truncado    = $total > REPORTES_LIMITE_IMPRESION;
$n           = 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hoja de cuadrilla · SRCI</title>
  <style>
    :root { --tinta:#000; --papel:#fff; }
    * { box-sizing:border-box; }
    html, body { background:var(--papel); color:var(--tinta); }
    body {
      margin:0;
      padding:10mm;
      font-family:Arial, Helvetica, sans-serif;
      font-size:10.5pt;
      line-height:1.35;
    }
    a { color:var(--tinta); text-decoration:none; }
    .barra-acciones { display:flex; gap:.5rem; margin-bottom:8mm; }
    .barra-acciones button, .barra-acciones a {
      font:inherit; font-size:9pt; padding:2mm 4mm; border:1.5px solid var(--tinta);
      background:var(--papel); color:var(--tinta); border-radius:2mm; cursor:pointer;
    }
    .membrete { border-bottom:2.5px solid var(--tinta); padding-bottom:2.5mm; margin-bottom:3mm; }
    .membrete-sistema { font-size:8.5pt; letter-spacing:.08em; text-transform:uppercase; }
    .membrete-titulo { font-size:17pt; font-weight:800; margin:.5mm 0; }
    .membrete-sub { font-size:9pt; }
    .meta {
      display:flex; flex-wrap:wrap; justify-content:space-between; gap:1mm 6mm;
      font-size:8.5pt; margin-bottom:3mm;
    }
    .campos-operativos {
      display:grid; grid-template-columns:repeat(5, 1fr); gap:2mm;
      border:1.5px solid var(--tinta); padding:2.5mm; margin-bottom:4mm;
    }
    .campo-mano { font-size:8pt; }
    .campo-mano .etiqueta { display:block; text-transform:uppercase; letter-spacing:.04em; }
    .campo-mano .linea { display:block; border-bottom:1px solid var(--tinta); height:6mm; }
    .aviso {
      border:1.5px solid var(--tinta); padding:2mm 3mm; margin-bottom:4mm;
      font-size:8.5pt; font-weight:700;
    }
    .grupo-barrio { margin-bottom:4mm; }
    .grupo-titulo {
      font-size:11pt; font-weight:800; text-transform:uppercase;
      border-bottom:2px solid var(--tinta); padding-bottom:1mm; margin-bottom:2.5mm;
    }
    .grupo-cuenta { font-size:8pt; font-weight:400; text-transform:none; float:right; }
    .tarjeta {
      border:1.5px solid var(--tinta); padding:2.5mm 3mm; margin-bottom:2.5mm;
      break-inside:avoid; page-break-inside:avoid;
    }
    .tarjeta-cabecera { display:flex; align-items:flex-start; gap:3mm; }
    .numero {
      flex:none; min-width:11mm; height:11mm; border:2px solid var(--tinta);
      display:flex; align-items:center; justify-content:center;
      font-size:13pt; font-weight:800;
    }
    .direccion { font-size:12.5pt; font-weight:800; line-height:1.15; }
    .lugar { font-size:8.5pt; }
    .ficha { display:grid; grid-template-columns:1fr 1fr; gap:.5mm 6mm; font-size:9pt; margin-top:1.5mm; }
    .ficha .ancho { grid-column:1 / -1; }
    .etiqueta-campo { font-weight:700; }
    .categorias { font-size:8pt; }
    .casillas { display:flex; flex-wrap:wrap; gap:2mm 8mm; margin-top:2.5mm; font-size:9pt; }
    .casilla-item { display:inline-flex; align-items:center; gap:2mm; }
    .casilla { width:4mm; height:4mm; border:1.5px solid var(--tinta); display:inline-block; }
    .observaciones { margin-top:2.5mm; font-size:8.5pt; }
    .linea-punteada { border-bottom:1px dotted var(--tinta); height:5.5mm; }
    .firmas { display:grid; grid-template-columns:1fr 1fr; gap:12mm; margin-top:12mm; break-inside:avoid; }
    .firma { text-align:center; font-size:8.5pt; }
    .firma .relleno { height:14mm; }
    .firma .linea-firma { border-top:1.5px solid var(--tinta); padding-top:1.5mm; }
    .pie { margin-top:6mm; border-top:1px solid var(--tinta); padding-top:2mm; font-size:7.5pt; display:flex; justify-content:space-between; gap:4mm; }
    @media print {
      @page { size:A4 portrait; margin:10mm; }
      body { padding:0; }
      .no-print { display:none !important; }
      .tarjeta { break-inside:avoid; page-break-inside:avoid; }
    }
  </style>
</head>
<body>
  <div class="barra-acciones no-print">
    <button type="button" onclick="window.print()">🖨 Imprimir de nuevo</button>
    <a href="/srci/admin/reportes_generador.php">← Volver al generador</a>
  </div>

  <header class="membrete">
    <div class="membrete-sistema">Municipalidad de Concepción del Uruguay · SRCI</div>
    <div class="membrete-titulo">Hoja de cuadrilla / Orden de trabajo</div>
    <div class="membrete-sub">
      Período: <?= esc($periodo) ?> · Emitido: <?= esc($hoy) ?> · Por: <?= esc($emisor) ?>
    </div>
  </header>

  <div class="meta">
    <span><strong>Casos seleccionados:</strong> <?= $total ?></span>
    <span><strong>Casos impresos:</strong> <?= $impresos ?></span>
    <span><strong>Filtros:</strong> <?= esc(describir_filtros_reporte($filtros)) ?></span>
  </div>

  <div class="campos-operativos">
    <div class="campo-mano"><span class="etiqueta">Fecha de salida</span><span class="linea"></span></div>
    <div class="campo-mano"><span class="etiqueta">Móvil / cuadrilla N°</span><span class="linea"></span></div>
    <div class="campo-mano"><span class="etiqueta">Capataz a cargo</span><span class="linea"></span></div>
    <div class="campo-mano"><span class="etiqueta">Hora de salida</span><span class="linea"></span></div>
    <div class="campo-mano"><span class="etiqueta">Hora de regreso</span><span class="linea"></span></div>
  </div>

  <?php if ($truncado): ?>
    <div class="aviso">
      ATENCIÓN: el resultado tiene <?= $total ?> casos y se imprimen los primeros
      <?= REPORTES_LIMITE_IMPRESION ?>. Acote el período o los filtros para el listado completo.
    </div>
  <?php endif; ?>

  <?php if (!$filas): ?>
    <p><strong>No hay incidencias que coincidan con los filtros aplicados.</strong></p>
  <?php endif; ?>

  <?php foreach ($grupos as $barrio => $casos): ?>
    <section class="grupo-barrio">
      <div class="grupo-titulo">
        <?= esc($barrio) ?>
        <span class="grupo-cuenta"><?= count($casos) ?> <?= count($casos) === 1 ? 'punto' : 'puntos' ?></span>
      </div>

      <?php foreach ($casos as $fila): ?>
        <?php
          $n++;
          $cats = $mapa_categorias[(int)$fila['id']] ?? [];
          $nombres_cats = implode(', ', array_map(
            static fn(array $c): string => (string)($c['nombre'] ?? ''),
            $cats
          ));
        ?>
        <article class="tarjeta">
          <div class="tarjeta-cabecera">
            <div class="numero">#<?= $n ?></div>
            <div>
              <div class="direccion"><?= esc((string)($fila['direccion'] ?? 'Sin dirección')) ?></div>
              <div class="lugar">
                Barrio: <strong><?= esc((string)($fila['barrio_nombre'] ?? '—')) ?></strong>
                <?php if (!empty($fila['barrio_categoria'])): ?>
                  · Tipo: <?= esc(nombre_categoria((string)$fila['barrio_categoria'])) ?>
                <?php endif; ?>
                · Reporte #<?= (int)$fila['id'] ?>
                · <?= esc(fecha_legible((string)$fila['fecha_hora'])) ?>
              </div>
            </div>
          </div>

          <div class="ficha">
            <div><span class="etiqueta-campo">Problema:</span> <?= esc((string)($fila['tipo_nombre'] ?? '—')) ?></div>
            <div><span class="etiqueta-campo">Gravedad:</span> <?= esc(nombre_gravedad($fila['gravedad'] ?? null)) ?></div>
            <?php if ($nombres_cats !== ''): ?>
              <div class="ancho categorias"><span class="etiqueta-campo">Categorías:</span> <?= esc($nombres_cats) ?></div>
            <?php endif; ?>
            <?php if (!empty($fila['servicio_afectado'])): ?>
              <div><span class="etiqueta-campo">Servicio afectado:</span> <?= esc((string)$fila['servicio_afectado']) ?></div>
            <?php endif; ?>
            <div><span class="etiqueta-campo">Familias afectadas:</span> <?= (int)($fila['familias_afectadas'] ?? 0) ?></div>
            <div><span class="etiqueta-campo">Calle intransitable:</span> <?= ((int)($fila['calle_intransitable'] ?? 0) === 1) ? 'Sí' : 'No' ?></div>
            <?php if (!empty($fila['responsable_area'])): ?>
              <div><span class="etiqueta-campo">Área responsable:</span> <?= esc((string)$fila['responsable_area']) ?></div>
            <?php endif; ?>
            <?php if (!empty($fila['contacto_vecino'])): ?>
              <div><span class="etiqueta-campo">Contacto vecino:</span> <?= esc((string)$fila['contacto_vecino']) ?></div>
            <?php endif; ?>
            <?php if (!empty($fila['notas'])): ?>
              <div class="ancho"><span class="etiqueta-campo">Notas:</span> <?= esc((string)$fila['notas']) ?></div>
            <?php endif; ?>
          </div>

          <div class="casillas">
            <span class="casilla-item"><span class="casilla"></span> Resuelto</span>
            <span class="casilla-item"><span class="casilla"></span> Requiere maquinaria pesada</span>
            <span class="casilla-item"><span class="casilla"></span> No se localizó el problema</span>
          </div>

          <div class="observaciones">
            <div>Observaciones de la cuadrilla:</div>
            <div class="linea-punteada"></div>
            <div class="linea-punteada"></div>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>

  <div class="firmas">
    <div class="firma">
      <div class="relleno"></div>
      <div class="linea-firma">Firma y aclaración del capataz</div>
    </div>
    <div class="firma">
      <div class="relleno"></div>
      <div class="linea-firma">Firma y aclaración del inspector de fiscalización</div>
    </div>
  </div>

  <div class="pie">
    <span>SRCI — Sistema de Reporte Ciudadano de Incidencias · Municipalidad de Concepción del Uruguay</span>
    <span>Documento generado el <?= esc($hoy) ?></span>
  </div>

  <script>
    window.addEventListener('load', () => {
      setTimeout(() => window.print(), 350);
    });
  </script>
</body>
</html>
