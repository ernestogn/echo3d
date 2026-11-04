<?php
declare(strict_types=1);

/**
 * Dossier ejecutivo A4 (color) para autoridades.
 *
 * Incluye membrete institucional, resumen de indicadores, distribuciones en
 * barras CSS nativas y tabla detallada. Sin librerias externas: se imprime con
 * window.print() y @media print.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/funciones_reportes.php';

requiere_admin();

$filtros = normalizar_filtros_reportes($_GET);
['where' => $where_sql, 'params' => $params] = construir_consulta_reportes($filtros);

$kpis         = obtener_kpis_reporte($where_sql, $params);
$distribucion = obtener_distribucion_reporte($where_sql, $params);
$filas        = obtener_incidencias_reporte(
  $where_sql,
  $params,
  (string)$filtros['orden'],
  (string)$filtros['dir'],
  REPORTES_LIMITE_IMPRESION
);

$mapa_categorias = categorias_por_incidencia(array_column($filas, 'id'));

registrar_salida_reporte('generar_reporte', 'Dossier ejecutivo', $filtros, count($filas));

$periodo  = periodo_legible_reporte($filtros);
$total    = (int)$kpis['total'];
$impresos = count($filas);
$emisor   = (string)($_SESSION['nombre'] ?? '');
// Hora local de la base (PHP puede correr en UTC): evita 3 h de desfasaje.
$hoy      = (string)db()->query("SELECT DATE_FORMAT(NOW(), '%d/%m/%Y %H:%i')")->fetchColumn();
$truncado = $total > REPORTES_LIMITE_IMPRESION;

$etiqueta_gravedad = static function (string $clave): string {
  return $clave === 'sin_dato' ? 'Sin dato' : nombre_gravedad($clave);
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dossier ejecutivo · SRCI</title>
  <style>
    :root {
      --tinta:#1e293b;
      --tinta-suave:#64748b;
      --linea:#cbd5e1;
      --acento:#4f46e5;
      --papel:#ffffff;
      --fondo:#f1f5f9;
    }
    * { box-sizing:border-box; }
    html, body { background:var(--papel); color:var(--tinta); }
    body {
      margin:0;
      padding:10mm;
      font-family:Arial, Helvetica, sans-serif;
      font-size:9.5pt;
      line-height:1.4;
    }
    a { color:var(--acento); text-decoration:none; }
    .barra-acciones { display:flex; gap:.5rem; margin-bottom:8mm; }
    .barra-acciones button, .barra-acciones a {
      font:inherit; font-size:9pt; padding:2mm 4mm; border:1.5px solid var(--acento);
      background:var(--papel); color:var(--acento); border-radius:2mm; cursor:pointer;
    }
    .membrete {
      border-bottom:3px solid var(--acento);
      padding-bottom:3mm;
      margin-bottom:3mm;
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:6mm;
    }
    .membrete-sistema { font-size:8pt; letter-spacing:.09em; text-transform:uppercase; color:var(--tinta-suave); }
    .membrete-titulo { font-size:19pt; font-weight:800; margin:.5mm 0; color:var(--tinta); }
    .membrete-sub { font-size:9pt; color:var(--tinta-suave); }
    .membrete-sello {
      flex:none; border:2px solid var(--acento); color:var(--acento);
      border-radius:50%; width:26mm; height:26mm; display:flex;
      align-items:center; justify-content:center; text-align:center;
      font-size:7pt; font-weight:800; text-transform:uppercase; line-height:1.2;
    }
    .meta {
      display:flex; flex-wrap:wrap; justify-content:space-between; gap:1mm 6mm;
      font-size:8.5pt; color:var(--tinta-suave); margin-bottom:4mm;
    }
    .meta strong { color:var(--tinta); }
    .aviso {
      background:#fef3c7; border:1px solid #f59e0b; color:#92400e;
      padding:2mm 3mm; margin-bottom:4mm; font-size:8.5pt; font-weight:700;
    }
    .seccion-titulo {
      font-size:10pt; text-transform:uppercase; letter-spacing:.07em;
      color:var(--acento); border-bottom:1.5px solid var(--acento);
      padding-bottom:1mm; margin:5mm 0 3mm;
    }
    .kpis { display:grid; grid-template-columns:repeat(4, 1fr); gap:2.5mm; }
    .kpi { background:var(--fondo); border:1px solid var(--linea); border-radius:2mm; padding:2.5mm 3mm; }
    .kpi-valor { font-size:16pt; font-weight:800; line-height:1.1; }
    .kpi-valor.acento { color:var(--acento); }
    .kpi-valor.verde { color:#16a34a; }
    .kpi-valor.rojo { color:#dc2626; }
    .kpi-etiqueta { font-size:7.5pt; text-transform:uppercase; letter-spacing:.05em; color:var(--tinta-suave); font-weight:700; }
    .distribuciones { display:grid; grid-template-columns:1fr 1fr; gap:5mm; }
    .barra { margin-bottom:1.8mm; }
    .barra-fila { display:flex; justify-content:space-between; gap:3mm; font-size:8.5pt; }
    .barra-dato { color:var(--tinta-suave); white-space:nowrap; }
    .barra-pista { height:5px; background:#e2e8f0; border-radius:99px; margin-top:.8mm; overflow:hidden; }
    .barra-relleno { display:block; height:100%; background:var(--acento); border-radius:99px; }
    .barra-relleno.gravedad { background:#dc2626; }
    table { width:100%; border-collapse:collapse; font-size:8pt; }
    thead th {
      background:var(--fondo); color:var(--tinta); text-align:left;
      padding:1.6mm 2mm; border-bottom:1.5px solid var(--acento); font-size:7.5pt;
      text-transform:uppercase; letter-spacing:.04em;
    }
    tbody td { padding:1.4mm 2mm; border-bottom:1px solid var(--linea); vertical-align:top; }
    tbody tr:nth-child(even) td { background:#f8fafc; }
    .chip { display:inline-block; padding:.2mm 1.4mm; border-radius:99px; font-size:7pt; border:1px solid var(--linea); color:var(--tinta-suave); }
    .firmas { display:grid; grid-template-columns:1fr 1fr; gap:12mm; margin-top:12mm; break-inside:avoid; }
    .firma { text-align:center; font-size:8.5pt; }
    .firma .relleno { height:14mm; }
    .firma .linea-firma { border-top:1.5px solid var(--tinta); padding-top:1.5mm; }
    .pie {
      margin-top:7mm; border-top:1px solid var(--linea); padding-top:2mm;
      font-size:7.5pt; color:var(--tinta-suave);
      display:flex; justify-content:space-between; gap:4mm;
    }
    @media print {
      @page { size:A4 portrait; margin:10mm; }
      body { padding:0; }
      .no-print { display:none !important; }
      thead { display:table-header-group; }
      tr { break-inside:avoid; page-break-inside:avoid; }
    }
  </style>
</head>
<body>
  <div class="barra-acciones no-print">
    <button type="button" onclick="window.print()">🖨 Imprimir de nuevo</button>
    <a href="/srci/admin/reportes_generador.php">← Volver al generador</a>
  </div>

  <header class="membrete">
    <div>
      <div class="membrete-sistema">Municipalidad de Concepción del Uruguay</div>
      <div class="membrete-titulo">Dossier ejecutivo de incidencias</div>
      <div class="membrete-sub">
        Sistema de Reporte Ciudadano de Incidencias (SRCI) · Período: <?= esc($periodo) ?>
      </div>
    </div>
    <div class="membrete-sello">SRCI<br>Reporte<br>Territorial</div>
  </header>

  <div class="meta">
    <span><strong>Emisión:</strong> <?= esc($hoy) ?></span>
    <span><strong>Emitido por:</strong> <?= esc($emisor) ?></span>
    <span><strong>Casos analizados:</strong> <?= $total ?> · <strong>Detallados:</strong> <?= $impresos ?></span>
  </div>

  <div class="meta">
    <span><strong>Filtros aplicados:</strong> <?= esc(describir_filtros_reporte($filtros)) ?></span>
  </div>

  <?php if ($truncado): ?>
    <div class="aviso">
      El resultado supera el límite de impresión de <?= REPORTES_LIMITE_IMPRESION ?> casos:
      se detallan los primeros <?= REPORTES_LIMITE_IMPRESION ?>. Las cifras agregadas son sobre el total filtrado.
    </div>
  <?php endif; ?>

  <div class="seccion-titulo">Resumen ejecutivo de indicadores</div>
  <div class="kpis">
    <div class="kpi">
      <div class="kpi-valor acento"><?= $total ?></div>
      <div class="kpi-etiqueta">Casos analizados</div>
    </div>
    <div class="kpi">
      <div class="kpi-valor verde"><?= esc((string)$kpis['tasa_resolucion']) ?>%</div>
      <div class="kpi-etiqueta">Tasa de resolución</div>
    </div>
    <div class="kpi">
      <div class="kpi-valor rojo"><?= (int)$kpis['familias_afectadas'] ?></div>
      <div class="kpi-etiqueta">Familias afectadas</div>
    </div>
    <div class="kpi">
      <div class="kpi-valor rojo"><?= (int)$kpis['calles_cortadas'] ?></div>
      <div class="kpi-etiqueta">Calles cortadas</div>
    </div>
    <div class="kpi">
      <div class="kpi-valor"><?= (int)$kpis['resueltos'] ?></div>
      <div class="kpi-etiqueta">Resueltos</div>
    </div>
    <div class="kpi">
      <div class="kpi-valor"><?= (int)$kpis['en_proceso'] ?></div>
      <div class="kpi-etiqueta">En gestión</div>
    </div>
    <div class="kpi">
      <div class="kpi-valor"><?= (int)$kpis['pendientes'] ?></div>
      <div class="kpi-etiqueta">Pendientes</div>
    </div>
    <div class="kpi">
      <div class="kpi-valor"><?= esc((string)$kpis['promedio_dias']) ?></div>
      <div class="kpi-etiqueta">Promedio de días abiertos</div>
    </div>
  </div>

  <div class="seccion-titulo">Distribución de los casos</div>
  <div class="distribuciones">
    <div>
      <div class="membrete-sub" style="margin-bottom:2mm;"><strong>Por tipo de problema</strong></div>
      <?php if (!$distribucion['por_tipo']): ?>
        <p class="barra-dato">Sin datos.</p>
      <?php else: ?>
        <?php foreach ($distribucion['por_tipo'] as $item): ?>
          <?php $pct = $total > 0 ? round(($item['total'] / $total) * 100, 1) : 0; ?>
          <div class="barra">
            <div class="barra-fila"><span><?= esc($item['etiqueta']) ?></span><span class="barra-dato"><?= (int)$item['total'] ?> · <?= esc((string)$pct) ?>%</span></div>
            <div class="barra-pista"><span class="barra-relleno" style="width:<?= esc((string)$pct) ?>%"></span></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div>
      <div class="membrete-sub" style="margin-bottom:2mm;"><strong>Por gravedad</strong></div>
      <?php if (!$distribucion['por_gravedad']): ?>
        <p class="barra-dato">Sin datos.</p>
      <?php else: ?>
        <?php foreach ($distribucion['por_gravedad'] as $item): ?>
          <?php $pct = $total > 0 ? round(($item['total'] / $total) * 100, 1) : 0; ?>
          <div class="barra">
            <div class="barra-fila"><span><?= esc($etiqueta_gravedad($item['etiqueta'])) ?></span><span class="barra-dato"><?= (int)$item['total'] ?> · <?= esc((string)$pct) ?>%</span></div>
            <div class="barra-pista"><span class="barra-relleno gravedad" style="width:<?= esc((string)$pct) ?>%"></span></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="seccion-titulo">Detalle de casos</div>
  <?php if (!$filas): ?>
    <p class="barra-dato">No hay incidencias que coincidan con los filtros aplicados.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Fecha</th>
          <th>Barrio</th>
          <th>Dirección</th>
          <th>Problema</th>
          <th>Categorías</th>
          <th>Gravedad</th>
          <th>Familias</th>
          <th>Estado</th>
          <th>Días</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($filas as $fila): ?>
          <?php $cats = $mapa_categorias[(int)$fila['id']] ?? []; ?>
          <tr>
            <td><?= (int)$fila['id'] ?></td>
            <td style="white-space:nowrap;"><?= esc(date('d/m/Y', strtotime((string)$fila['fecha_hora']))) ?></td>
            <td><?= esc((string)($fila['barrio_nombre'] ?? '—')) ?></td>
            <td><?= esc((string)($fila['direccion'] ?? '—')) ?></td>
            <td><?= esc((string)($fila['tipo_nombre'] ?? '—')) ?></td>
            <td>
              <?php if ($cats): foreach ($cats as $cat): ?>
                <span class="chip"><?= esc((string)$cat['nombre']) ?></span>
              <?php endforeach; else: ?>—<?php endif; ?>
            </td>
            <td><?= esc(nombre_gravedad($fila['gravedad'] ?? null)) ?></td>
            <td><?= (int)($fila['familias_afectadas'] ?? 0) ?></td>
            <td><?= esc(nombre_estado((string)$fila['estado'])) ?></td>
            <td><?= dias_abiertos_reporte($fila) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div class="firmas">
    <div class="firma">
      <div class="relleno"></div>
      <div class="linea-firma">Firma y aclaración — Autoridad municipal</div>
    </div>
    <div class="firma">
      <div class="relleno"></div>
      <div class="linea-firma">Firma y aclaración — Responsable de fiscalización</div>
    </div>
  </div>

  <div class="pie">
    <span>Municipalidad de Concepción del Uruguay · SRCI — Sistema de Reporte Ciudadano de Incidencias</span>
    <span>Foja ________ de ________ · Emitido el <?= esc($hoy) ?></span>
  </div>

  <script>
    window.addEventListener('load', () => {
      setTimeout(() => window.print(), 350);
    });
  </script>
</body>
</html>
