<?php
declare(strict_types=1);

/**
 * Motor de reportes y salidas imprimibles del SRCI.
 *
 * Este archivo concentra TODA la logica del modulo de reportes territoriales:
 *  - Normalizacion de filtros (GET -> estructura tipada y segura).
 *  - Construccion dinamica del WHERE con parametros nombrados (sin concatenar
 *    valores del usuario) y con EXISTS para las relaciones muchos-a-muchos.
 *  - Presets de un clic (resueltos por clave, nunca por id).
 *  - KPIs, distribuciones y listado de incidencias.
 *  - Helpers de CSV/GeoJSON y de auditoria de cada salida.
 *
 * Reglas de la casa:
 *  - PHP 8 nativo, sin frameworks ni librerias externas.
 *  - PDO con named params y EMULATE_PREPARES = false (db()).
 *  - Cada query arma sus propios parametros: nunca se reutiliza el mismo
 *    placeholder dos veces dentro de una misma sentencia (SQLSTATE[HY093]).
 *  - Los filtros por categoria de incidencia usan EXISTS (NO JOIN) para no
 *    multiplicar filas y romper COUNT/SUM de los KPIs.
 *  - "Dias abiertos" = fecha_hora -> fecha_resolucion (o NOW() si sigue abierta),
 *    la misma regla que usa la planilla y el listado publico.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/funciones.php';

// Limites de salida (evitan documentos y descargas gigantes)
const REPORTES_LIMITE_PREVIEW     = 50;
const REPORTES_LIMITE_IMPRESION   = 300;
const REPORTES_LIMITE_EXPORTACION = 5000;

const REPORTES_ESTADOS_VALIDOS      = ['pendiente', 'en_proceso', 'resuelto'];
const REPORTES_GRAVEDADES_VALIDAS   = ['baja', 'media', 'alta', 'critica'];
const REPORTES_CATEGORIAS_BARRIO    = ['formal', 'informal', 'asentamiento'];
const REPORTES_ORDENES_VALIDOS      = ['fecha', 'barrio', 'gravedad', 'estado', 'dias', 'familias', 'id', 'cuadrilla'];
const REPORTES_TABLAS_CLAVE_VALIDAS = ['categorias_incidencia', 'tipos_incidencia'];

/**
 * Definicion de los presets de un clic.
 *
 * Los presets usan CLAVES semanticas (categorias_claves / tipos_claves) que se
 * resuelven a ids en runtime. Asi el preset funciona igual en desarrollo y en
 * produccion aunque los ids autoincrementales difieran.
 *
 * @return array<string, array{titulo:string, descripcion:string, icono:string, filtros:array<string, mixed>}>
 */
function definicion_presets_reportes(): array
{
  // El periodo "mes en curso" debe seguir la hora local de la ciudad, no la
  // de PHP (que en el VPS corre en UTC y puede adelantar el cambio de mes).
  $hoy = new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires'));

  return [
    'p1' => [
      'titulo'      => 'Emergencias climaticas activas',
      'descripcion' => 'Categoria Climaticos, gravedad alta o critica, sin resolver.',
      'icono'       => '🌧️',
      'filtros'     => [
        'categorias_claves' => ['climaticos'],
        'gravedades'        => ['alta', 'critica'],
        'estados'           => ['pendiente', 'en_proceso'],
      ],
    ],
    'p2' => [
      'titulo'      => 'Casos estancados (mas de 15 dias)',
      'descripcion' => 'Reportes pendientes con 15 dias o mas de antiguedad.',
      'icono'       => '⏳',
      'filtros'     => [
        'estados'  => ['pendiente'],
        'dias_min' => 15,
      ],
    ],
    'p3' => [
      'titulo'      => 'Plan de mantenimiento vial',
      'descripcion' => 'Alcantarillas tapadas u obstruidas sin resolver.',
      'icono'       => '🛣️',
      'filtros'     => [
        'tipos_claves' => ['alcantarilla'],
        'estados'      => ['pendiente', 'en_proceso'],
      ],
    ],
    'p4' => [
      'titulo'      => 'Alumbrado y redes electricas',
      'descripcion' => 'Luminarias rotas y postes caidos sin resolver.',
      'icono'       => '💡',
      'filtros'     => [
        'tipos_claves' => ['luminariarota', 'postecaido'],
        'estados'      => ['pendiente', 'en_proceso'],
      ],
    ],
    'p5' => [
      'titulo'      => 'Balance mensual de gestion',
      'descripcion' => 'Todas las incidencias creadas en el mes en curso.',
      'icono'       => '📅',
      'filtros'     => [
        'fecha_desde' => $hoy->modify('first day of this month')->format('Y-m-d'),
        'fecha_hasta' => $hoy->modify('last day of this month')->format('Y-m-d'),
        // Sin filtro de estado: entran todos los estados
        'estados'     => [],
      ],
    ],
  ];
}

/**
 * Convierte cualquier valor (escalar o array) en una lista de enteros positivos
 * unicos. Se usa para barrios, categorias y tipos.
 *
 * @return int[]
 */
function valores_enteros_positivos(mixed $valor): array
{
  if (!is_array($valor)) {
    $valor = [$valor];
  }

  $ids = [];
  foreach ($valor as $v) {
    if (!is_scalar($v)) {
      continue;
    }
    $n = (int)$v;
    if ($n > 0) {
      $ids[$n] = $n;
    }
  }

  return array_values($ids);
}

/**
 * Convierte cualquier valor en una lista de strings recortados y sin vacios.
 *
 * @return string[]
 */
function valores_texto(mixed $valor): array
{
  if (!is_array($valor)) {
    $valor = [$valor];
  }

  $lista = [];
  foreach ($valor as $v) {
    if (!is_scalar($v)) {
      continue;
    }
    $v = trim((string)$v);
    if ($v !== '') {
      $lista[] = $v;
    }
  }

  return $lista;
}

/**
 * Filtra un array de strings contra una lista blanca.
 *
 * @param string[] $valores
 * @param string[] $permitidos
 * @return string[]
 */
function filtrar_lista_blanca(array $valores, array $permitidos): array
{
  $ok = [];
  foreach ($valores as $v) {
    if (in_array($v, $permitidos, true)) {
      $ok[$v] = $v;
    }
  }

  return array_values($ok);
}

/**
 * Resuelve claves semanticas (ej: "climaticos") a ids reales de la tabla.
 * La tabla se valida con lista blanca para evitar inyeccion.
 *
 * @param string[] $claves
 * @return int[]
 */
function ids_por_clave_reporte(string $tabla, array $claves): array
{
  if (!in_array($tabla, REPORTES_TABLAS_CLAVE_VALIDAS, true)) {
    return [];
  }

  // Comparacion sin distinguir mayusculas: las claves historicas de tipos
  // conviven en formatos como "LuminariaRota" y "PosteCaido".
  $claves = array_values(array_unique(array_filter(
    array_map(static fn($c): string => mb_strtolower(trim((string)$c)), $claves),
    static fn(string $c): bool => $c !== ''
  )));

  if (!$claves) {
    return [];
  }

  $ph = implode(',', array_fill(0, count($claves), '?'));
  $stmt = db()->prepare("SELECT id FROM {$tabla} WHERE LOWER(clave) IN ($ph)");
  $stmt->execute($claves);

  $ids = [];
  foreach ($stmt->fetchAll() as $fila) {
    $ids[] = (int)$fila['id'];
  }

  return array_values(array_unique($ids));
}

/**
 * Valida una fecha YYYY-MM-DD. Devuelve '' si no es valida.
 */
function fecha_valida_reporte(mixed $valor): string
{
  $valor = trim((string)$valor);
  if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m)) {
    return '';
  }

  return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $valor : '';
}

/**
 * Normaliza la entrada (normalmente $_GET) a una estructura de filtros tipada.
 *
 * Claves de entrada soportadas:
 *  - fechas: fecha_desde / fecha_hasta (sobre fecha_hora de creacion)
 *  - barrios[] (ids), barrio_categoria (formal|informal|asentamiento)
 *  - estados[], gravedades[], dias_min (entero >= 0)
 *  - familias_mayor_cero, calle_intransitable, con_foto, con_seguimiento,
 *    incluir_pendientes (booleanos 0/1)
 *  - responsable_area (texto exacto), texto (busqueda libre)
 *  - categorias[] (ids), categorias_claves[] (claves semanticas de presets)
 *  - tipos[] (ids), tipos_claves[] (claves semanticas de presets)
 *  - orden, dir
 *
 * @param array<string, mixed> $entrada
 * @return array<string, mixed>
 */
function normalizar_filtros_reportes(array $entrada): array
{
  $bool = static function (mixed $v): bool {
    if (is_bool($v)) {
      return $v;
    }

    return in_array((string)$v, ['1', 'true', 'on', 'si'], true);
  };

  $filtros = [
    'fecha_desde'         => fecha_valida_reporte($entrada['fecha_desde'] ?? ''),
    'fecha_hasta'         => fecha_valida_reporte($entrada['fecha_hasta'] ?? ''),
    'barrios'             => valores_enteros_positivos($entrada['barrios'] ?? []),
    'barrio_categoria'    => '',
    'estados'             => filtrar_lista_blanca(valores_texto($entrada['estados'] ?? []), REPORTES_ESTADOS_VALIDOS),
    'gravedades'          => filtrar_lista_blanca(valores_texto($entrada['gravedades'] ?? []), REPORTES_GRAVEDADES_VALIDAS),
    'dias_min'            => max(0, min(3650, (int)($entrada['dias_min'] ?? 0))),
    'familias_mayor_cero' => $bool($entrada['familias_mayor_cero'] ?? false),
    'calle_intransitable' => $bool($entrada['calle_intransitable'] ?? false),
    'responsable_area'    => trim((string)($entrada['responsable_area'] ?? '')),
    'con_foto'            => $bool($entrada['con_foto'] ?? false),
    'con_seguimiento'     => $bool($entrada['con_seguimiento'] ?? false),
    'categorias'          => valores_enteros_positivos($entrada['categorias'] ?? []),
    'tipos'               => valores_enteros_positivos($entrada['tipos'] ?? []),
    'texto'               => trim((string)($entrada['texto'] ?? '')),
    'incluir_pendientes'  => $bool($entrada['incluir_pendientes'] ?? false),
    'orden'               => strtolower(trim((string)($entrada['orden'] ?? 'fecha'))),
    'dir'                 => strtolower(trim((string)($entrada['dir'] ?? 'desc'))),
  ];

  $cat_barrio = strtolower(trim((string)($entrada['barrio_categoria'] ?? '')));
  if (in_array($cat_barrio, REPORTES_CATEGORIAS_BARRIO, true)) {
    $filtros['barrio_categoria'] = $cat_barrio;
  }

  // Las claves se resuelven a ids y se suman a los ids explicitos.
  $filtros['categorias'] = array_values(array_unique(array_merge(
    $filtros['categorias'],
    ids_por_clave_reporte('categorias_incidencia', valores_texto($entrada['categorias_claves'] ?? []))
  )));
  $filtros['tipos'] = array_values(array_unique(array_merge(
    $filtros['tipos'],
    ids_por_clave_reporte('tipos_incidencia', valores_texto($entrada['tipos_claves'] ?? []))
  )));

  if (!in_array($filtros['orden'], REPORTES_ORDENES_VALIDOS, true)) {
    $filtros['orden'] = 'fecha';
  }
  if (!in_array($filtros['dir'], ['asc', 'desc'], true)) {
    $filtros['dir'] = 'desc';
  }

  // Recortes defensivos de textos libres
  $filtros['responsable_area'] = mb_substr($filtros['responsable_area'], 0, 100);
  $filtros['texto']            = mb_substr($filtros['texto'], 0, 120);

  return $filtros;
}

/**
 * Normaliza la entrada aplicando primero un preset (si viene ?preset=pN).
 *
 * El link de un preset solo lleva ?preset=pN; al enviar el formulario ya no se
 * manda "preset" y se respetan los filtros manuales del usuario.
 *
 * @param array<string, mixed> $entrada
 * @return array<string, mixed>
 */
function filtros_desde_entrada_reporte(array $entrada): array
{
  $preset  = strtolower(trim((string)($entrada['preset'] ?? '')));
  $presets = definicion_presets_reportes();

  if ($preset !== '' && isset($presets[$preset])) {
    $entrada = array_merge($entrada, $presets[$preset]['filtros']);
  }

  return normalizar_filtros_reportes($entrada);
}

/**
 * Construye la clausula WHERE y el array de parametros nombrados.
 *
 * Importante: siempre se agrega LEFT JOIN barrios b en las consultas que usan
 * este WHERE, porque el filtro por categoria de barrio referencia b.categoria.
 *
 * @param array<string, mixed> $filtros
 * @return array{where:string, params:array<string, mixed>}
 */
function construir_consulta_reportes(array $filtros): array
{
  $filtros = array_merge(normalizar_filtros_reportes([]), $filtros);

  $donde  = ['i.oculto = 0'];
  $params = [];

  // Por defecto solo reportes publicados. El admin puede incluir pendientes
  // desde el checkbox del generador (borradores de la red de colaboradores).
  if (!$filtros['incluir_pendientes']) {
    $donde[] = 'i.aprobado = 1';
  }

  if ($filtros['fecha_desde'] !== '') {
    $donde[]                 = 'i.fecha_hora >= :fecha_desde';
    $params[':fecha_desde']  = $filtros['fecha_desde'] . ' 00:00:00';
  }

  if ($filtros['fecha_hasta'] !== '') {
    $donde[]                 = 'i.fecha_hora <= :fecha_hasta';
    $params[':fecha_hasta']  = $filtros['fecha_hasta'] . ' 23:59:59';
  }

  /**
   * Agrega una condicion IN con placeholders unicos.
   *
   * @param int[]|string[] $valores
   */
  $agregar_lista = static function (string $columna, array $valores, string $prefijo, bool $numerico) use (&$donde, &$params): void {
    if (!$valores) {
      return;
    }

    $placeholders = [];
    foreach (array_values($valores) as $i => $valor) {
      $clave           = ':' . $prefijo . $i;
      $placeholders[]  = $clave;
      $params[$clave]  = $numerico ? (int)$valor : (string)$valor;
    }

    $donde[] = $columna . ' IN (' . implode(', ', $placeholders) . ')';
  };

  $agregar_lista('i.barrio_id', $filtros['barrios'], 'barrio_', true);
  $agregar_lista('i.estado', $filtros['estados'], 'estado_', false);
  $agregar_lista('i.gravedad', $filtros['gravedades'], 'gravedad_', false);
  $agregar_lista('i.tipo_id', $filtros['tipos'], 'tipo_', true);

  if ($filtros['barrio_categoria'] !== '') {
    $donde[]                       = 'b.categoria = :barrio_categoria';
    $params[':barrio_categoria']   = $filtros['barrio_categoria'];
  }

  // Categoria de incidencia: EXISTS para no multiplicar filas (N:M)
  if ($filtros['categorias']) {
    $placeholders = [];
    foreach (array_values($filtros['categorias']) as $i => $id) {
      $clave          = ':categoria_' . $i;
      $placeholders[] = $clave;
      $params[$clave] = (int)$id;
    }
    $donde[] = 'EXISTS (SELECT 1 FROM incidencia_categorias ic'
      . ' WHERE ic.incidencia_id = i.id AND ic.categoria_id IN (' . implode(', ', $placeholders) . '))';
  }

  if ($filtros['dias_min'] > 0) {
    $donde[]             = 'DATEDIFF(COALESCE(i.fecha_resolucion, NOW()), i.fecha_hora) >= :dias_min';
    $params[':dias_min'] = (int)$filtros['dias_min'];
  }

  if ($filtros['familias_mayor_cero']) {
    $donde[] = 'i.familias_afectadas > 0';
  }

  if ($filtros['calle_intransitable']) {
    $donde[] = 'i.calle_intransitable = 1';
  }

  if ($filtros['responsable_area'] !== '') {
    $donde[]                        = 'i.responsable_area = :responsable_area';
    $params[':responsable_area']    = $filtros['responsable_area'];
  }

  if ($filtros['con_foto']) {
    $donde[] = 'EXISTS (SELECT 1 FROM fotos f WHERE f.incidencia_id = i.id)';
  }

  if ($filtros['con_seguimiento']) {
    $donde[] = 'EXISTS (SELECT 1 FROM incidencia_seguimiento s WHERE s.incidencia_id = i.id)';
  }

  if ($filtros['texto'] !== '') {
    $escapado = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filtros['texto']);
    $like     = '%' . $escapado . '%';
    $donde[]  = "(i.direccion LIKE :texto_1 ESCAPE '\\\\'"
      . " OR i.notas LIKE :texto_2 ESCAPE '\\\\'"
      . " OR i.contacto_vecino LIKE :texto_3 ESCAPE '\\\\')";
    $params[':texto_1'] = $like;
    $params[':texto_2'] = $like;
    $params[':texto_3'] = $like;
  }

  return [
    'where'  => 'WHERE ' . implode(' AND ', $donde),
    'params' => $params,
  ];
}

/**
 * Arma el ORDER BY a partir de una lista blanca. Nunca concatena la entrada.
 */
function orden_sql_reporte(string $orden, string $dir): string
{
  $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

  $mapa = [
    'fecha'     => 'i.fecha_hora',
    'barrio'    => 'b.nombre',
    'gravedad'  => "FIELD(i.gravedad, 'baja', 'media', 'alta', 'critica')",
    'estado'    => "FIELD(i.estado, 'pendiente', 'en_proceso', 'resuelto')",
    'dias'      => 'DATEDIFF(COALESCE(i.fecha_resolucion, NOW()), i.fecha_hora)',
    'familias'  => 'i.familias_afectadas',
    'id'        => 'i.id',
  ];

  if ($orden === 'cuadrilla') {
    return "b.nombre IS NULL ASC, b.nombre ASC,"
      . " FIELD(i.gravedad, 'critica', 'alta', 'media', 'baja') ASC,"
      . ' i.fecha_hora ASC, i.id ASC';
  }

  $columna = $mapa[$orden] ?? $mapa['fecha'];

  if ($orden === 'barrio') {
    return 'b.nombre IS NULL ASC, ' . $columna . ' ' . $dir . ', i.id DESC';
  }

  return $columna . ' ' . $dir . ', i.id DESC';
}

/**
 * KPIs del conjunto filtrado. Una sola query de agregacion.
 *
 * @param array<string, mixed> $params
 * @return array<string, int|float>
 */
function obtener_kpis_reporte(string $where_sql, array $params): array
{
  $sql = "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(i.estado = 'resuelto'), 0)   AS resueltos,
            COALESCE(SUM(i.estado = 'en_proceso'), 0) AS en_proceso,
            COALESCE(SUM(i.estado = 'pendiente'), 0)  AS pendientes,
            COALESCE(SUM(i.familias_afectadas), 0)    AS familias_afectadas,
            COALESCE(SUM(i.calle_intransitable = 1), 0) AS calles_cortadas,
            COALESCE(AVG(DATEDIFF(COALESCE(i.fecha_resolucion, NOW()), i.fecha_hora)), 0) AS promedio_dias
          FROM incidencias i
          LEFT JOIN barrios b ON b.id = i.barrio_id
          {$where_sql}";

  $stmt = db()->prepare($sql);
  foreach ($params as $clave => $valor) {
    $stmt->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
  }
  $stmt->execute();
  $fila = $stmt->fetch() ?: [];

  $total     = (int)($fila['total'] ?? 0);
  $resueltos = (int)($fila['resueltos'] ?? 0);

  return [
    'total'             => $total,
    'resueltos'         => $resueltos,
    'en_proceso'        => (int)($fila['en_proceso'] ?? 0),
    'pendientes'        => (int)($fila['pendientes'] ?? 0),
    'familias_afectadas'=> (int)($fila['familias_afectadas'] ?? 0),
    'calles_cortadas'   => (int)($fila['calles_cortadas'] ?? 0),
    'promedio_dias'     => round((float)($fila['promedio_dias'] ?? 0), 1),
    'tasa_resolucion'   => $total > 0 ? round(($resueltos / $total) * 100, 1) : 0.0,
  ];
}

/**
 * Distribuciones para el dossier (por tipo, por gravedad y por estado).
 *
 * @param array<string, mixed> $params
 * @return array{por_tipo: array<int, array{etiqueta:string,total:int}>, por_gravedad: array<int, array{etiqueta:string,total:int}>, por_estado: array<int, array{etiqueta:string,total:int}>}
 */
function obtener_distribucion_reporte(string $where_sql, array $params): array
{
  $consultas = [
    'por_tipo' => "SELECT t.nombre AS etiqueta, COUNT(*) AS total
                     FROM incidencias i
                     JOIN tipos_incidencia t ON t.id = i.tipo_id
                     LEFT JOIN barrios b ON b.id = i.barrio_id
                     {$where_sql}
                     GROUP BY t.id, t.nombre
                     ORDER BY total DESC, t.nombre ASC",
    'por_gravedad' => "SELECT COALESCE(i.gravedad, 'sin_dato') AS etiqueta, COUNT(*) AS total
                         FROM incidencias i
                         LEFT JOIN barrios b ON b.id = i.barrio_id
                         {$where_sql}
                         GROUP BY i.gravedad
                         ORDER BY FIELD(i.gravedad, 'baja', 'media', 'alta', 'critica')",
    'por_estado' => "SELECT i.estado AS etiqueta, COUNT(*) AS total
                       FROM incidencias i
                       LEFT JOIN barrios b ON b.id = i.barrio_id
                       {$where_sql}
                       GROUP BY i.estado
                       ORDER BY FIELD(i.estado, 'pendiente', 'en_proceso', 'resuelto')",
  ];

  $resultado = [];
  foreach ($consultas as $clave => $sql) {
    $stmt = db()->prepare($sql);
    foreach ($params as $param => $valor) {
      $stmt->bindValue($param, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    $filas = [];
    foreach ($stmt->fetchAll() as $fila) {
      $filas[] = [
        'etiqueta' => (string)$fila['etiqueta'],
        'total'    => (int)$fila['total'],
      ];
    }
    $resultado[$clave] = $filas;
  }

  return $resultado;
}

/**
 * Listado de incidencias del reporte con joins y datos de contacto.
 *
 * @param array<string, mixed> $params
 * @return array<int, array<string, mixed>>
 */
function obtener_incidencias_reporte(string $where_sql, array $params, string $orden, string $dir, int $limite): array
{
  $limite = max(1, min(REPORTES_LIMITE_EXPORTACION, $limite));
  $order  = orden_sql_reporte($orden, $dir);

  $sql = "SELECT i.id, i.latitud, i.longitud, i.estado, i.fecha_hora, i.fecha_resolucion,
                 i.notas, i.direccion, i.gravedad, i.familias_afectadas,
                 i.servicio_afectado, i.calle_intransitable, i.responsable_area,
                 i.contacto_vecino, i.aprobado,
                 b.nombre AS barrio_nombre, b.categoria AS barrio_categoria,
                 t.nombre AS tipo_nombre, t.icono AS tipo_icono, t.clave AS tipo_clave,
                 u.nombre AS usuario_nombre,
                 (SELECT ruta_archivo FROM fotos WHERE incidencia_id = i.id ORDER BY id LIMIT 1) AS foto
            FROM incidencias i
            JOIN tipos_incidencia t ON t.id = i.tipo_id
            JOIN usuarios u         ON u.id = i.usuario_id
            LEFT JOIN barrios b     ON b.id = i.barrio_id
            {$where_sql}
            ORDER BY {$order}
            LIMIT :limite";

  $stmt = db()->prepare($sql);
  foreach ($params as $clave => $valor) {
    $stmt->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
  }
  $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
  $stmt->execute();

  return $stmt->fetchAll();
}

/**
 * Dias abiertos de una incidencia (misma regla que la planilla).
 */
function dias_abiertos_reporte(array $fila): int
{
  $inicio = strtotime((string)($fila['fecha_hora'] ?? ''));
  if ($inicio === false) {
    return 0;
  }

  $fin = !empty($fila['fecha_resolucion'])
    ? strtotime((string)$fila['fecha_resolucion'])
    : time();

  if ($fin === false) {
    $fin = time();
  }

  return max(0, (int)floor(($fin - $inicio) / 86400));
}

/**
 * Columnas del CSV (BOM UTF-8 + separador ; para Excel en espanol).
 *
 * @return string[]
 */
function columnas_csv_reporte(): array
{
  return [
    'N°',
    'Fecha',
    'Hora',
    'Barrio',
    'Tipo de barrio',
    'Dirección / calle y altura',
    'Tipo de problema',
    'Categorías',
    'Gravedad',
    'Familias afectadas',
    'Servicio afectado',
    'Calle intransitable',
    'Estado',
    'Responsable / área',
    'Días abiertos',
    'Latitud',
    'Longitud',
    'Foto URL',
    'Relevado por',
  ];
}

/**
 * Neutraliza la inyeccion de formulas en celdas de texto del CSV.
 *
 * Excel/LibreOffice interpretan como formula cualquier celda que empiece con
 * =, +, - o @. Varios campos (direccion, notas, contacto) los escribe el
 * ciudadano, asi que se antepone un apostrofo para forzar texto plano.
 */
function celda_csv_reporte(mixed $valor): string
{
  $texto = (string)$valor;
  if ($texto !== '' && in_array($texto[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
    return "'" . $texto;
  }

  return $texto;
}

/**
 * Convierte una fila de incidencia en una fila de CSV.
 *
 * @param array<string, mixed> $fila
 * @param array<int, array<string, mixed>> $categorias
 * @return array<int, string|int|float>
 */
function fila_csv_reporte(array $fila, array $categorias): array
{
  $fecha = strtotime((string)($fila['fecha_hora'] ?? ''));
  $cats  = implode(', ', array_map(
    static fn(array $c): string => (string)($c['nombre'] ?? ''),
    $categorias
  ));

  $calle = $fila['calle_intransitable'] ?? null;

  return [
    (int)$fila['id'],
    $fecha !== false ? date('Y-m-d', $fecha) : '',
    $fecha !== false ? date('H:i', $fecha) : '',
    celda_csv_reporte($fila['barrio_nombre'] ?? ''),
    celda_csv_reporte(nombre_categoria($fila['barrio_categoria'] ?? null)),
    celda_csv_reporte($fila['direccion'] ?? ''),
    celda_csv_reporte($fila['tipo_nombre'] ?? ''),
    celda_csv_reporte($cats),
    celda_csv_reporte(nombre_gravedad($fila['gravedad'] ?? null)),
    (int)($fila['familias_afectadas'] ?? 0),
    celda_csv_reporte($fila['servicio_afectado'] ?? ''),
    $calle === null ? '' : ((int)$calle === 1 ? 'Sí' : 'No'),
    celda_csv_reporte(nombre_estado((string)($fila['estado'] ?? ''))),
    celda_csv_reporte($fila['responsable_area'] ?? ''),
    dias_abiertos_reporte($fila),
    $fila['latitud'] !== null ? (float)$fila['latitud'] : '',
    $fila['longitud'] !== null ? (float)$fila['longitud'] : '',
    !empty($fila['foto']) ? '/srci/uploads/' . (string)$fila['foto'] : '',
    celda_csv_reporte($fila['usuario_nombre'] ?? ''),
  ];
}

/**
 * Construye una FeatureCollection GeoJSON (WGS84) con propiedades enriquecidas.
 * Las filas sin coordenadas se omiten: quedan disponibles en el CSV.
 *
 * @param array<int, array<string, mixed>> $filas
 * @param array<int, array<int, array<string, mixed>>> $mapa_categorias
 * @return array{type:string, features:array<int, array<string, mixed>>}
 */
function construir_geojson_reporte(array $filas, array $mapa_categorias): array
{
  $features = [];

  foreach ($filas as $fila) {
    if ($fila['latitud'] === null || $fila['longitud'] === null) {
      continue;
    }

    $id    = (int)$fila['id'];
    $cats  = $mapa_categorias[$id] ?? [];

    $features[] = [
      'type'       => 'Feature',
      'geometry'   => [
        'type'        => 'Point',
        'coordinates' => [(float)$fila['longitud'], (float)$fila['latitud']],
      ],
      'properties' => [
        'id'                  => $id,
        'tipo'                => (string)($fila['tipo_nombre'] ?? ''),
        'tipo_clave'          => (string)($fila['tipo_clave'] ?? ''),
        'categorias'          => array_map(
          static fn(array $c): string => (string)($c['nombre'] ?? ''),
          $cats
        ),
        'barrio'              => $fila['barrio_nombre'] ?? null,
        'barrio_categoria'    => $fila['barrio_categoria'] ?? null,
        'direccion'           => $fila['direccion'] ?? null,
        'gravedad'            => $fila['gravedad'] ?? null,
        'estado'              => $fila['estado'] ?? null,
        'familias_afectadas'  => $fila['familias_afectadas'] !== null ? (int)$fila['familias_afectadas'] : null,
        'servicio_afectado'   => $fila['servicio_afectado'] ?? null,
        'calle_intransitable' => $fila['calle_intransitable'] !== null ? (int)$fila['calle_intransitable'] : null,
        'responsable_area'    => $fila['responsable_area'] ?? null,
        'dias_abiertos'       => dias_abiertos_reporte($fila),
        'fecha'               => $fila['fecha_hora'] ?? null,
        'fecha_resolucion'    => $fila['fecha_resolucion'] ?? null,
        'usuario'             => $fila['usuario_nombre'] ?? null,
        'notas'               => $fila['notas'] ?? null,
        'foto_url'            => !empty($fila['foto']) ? '/srci/uploads/' . (string)$fila['foto'] : null,
      ],
    ];
  }

  return [
    'type'     => 'FeatureCollection',
    'features' => $features,
  ];
}

/**
 * Devuelve los parametros GET canonicos para pasar los filtros a las salidas
 * (impresion y exportacion). No incluye "preset" ni "pagina".
 *
 * @param array<string, mixed> $filtros
 * @return array<string, mixed>
 */
function parametros_filtros_reporte(array $filtros): array
{
  $params = [];

  if ($filtros['fecha_desde'] !== '') {
    $params['fecha_desde'] = $filtros['fecha_desde'];
  }
  if ($filtros['fecha_hasta'] !== '') {
    $params['fecha_hasta'] = $filtros['fecha_hasta'];
  }
  if ($filtros['barrios']) {
    $params['barrios'] = $filtros['barrios'];
  }
  if ($filtros['barrio_categoria'] !== '') {
    $params['barrio_categoria'] = $filtros['barrio_categoria'];
  }
  if ($filtros['estados']) {
    $params['estados'] = $filtros['estados'];
  }
  if ($filtros['gravedades']) {
    $params['gravedades'] = $filtros['gravedades'];
  }
  if ($filtros['dias_min'] > 0) {
    $params['dias_min'] = $filtros['dias_min'];
  }
  if ($filtros['familias_mayor_cero']) {
    $params['familias_mayor_cero'] = 1;
  }
  if ($filtros['calle_intransitable']) {
    $params['calle_intransitable'] = 1;
  }
  if ($filtros['responsable_area'] !== '') {
    $params['responsable_area'] = $filtros['responsable_area'];
  }
  if ($filtros['con_foto']) {
    $params['con_foto'] = 1;
  }
  if ($filtros['con_seguimiento']) {
    $params['con_seguimiento'] = 1;
  }
  if ($filtros['categorias']) {
    $params['categorias'] = $filtros['categorias'];
  }
  if ($filtros['tipos']) {
    $params['tipos'] = $filtros['tipos'];
  }
  if ($filtros['texto'] !== '') {
    $params['texto'] = $filtros['texto'];
  }
  if ($filtros['incluir_pendientes']) {
    $params['incluir_pendientes'] = 1;
  }

  $params['orden'] = $filtros['orden'];
  $params['dir']   = $filtros['dir'];

  return $params;
}

/**
 * URL absoluta a una salida del modulo con los filtros actuales.
 *
 * @param array<string, mixed> $filtros
 */
function url_salida_reporte(string $archivo, array $filtros): string
{
  $query = http_build_query(parametros_filtros_reporte($filtros));

  return '/srci/admin/' . rawurlencode($archivo) . ($query !== '' ? '?' . $query : '');
}

/**
 * Descripcion compacta de los filtros activos, para auditoria y para el
 * encabezado de los documentos imprimibles.
 *
 * @param array<string, mixed> $filtros
 */
function describir_filtros_reporte(array $filtros): string
{
  $partes = [];

  if ($filtros['fecha_desde'] !== '' || $filtros['fecha_hasta'] !== '') {
    $desde = $filtros['fecha_desde'] !== '' ? $filtros['fecha_desde'] : 'sin inicio';
    $hasta = $filtros['fecha_hasta'] !== '' ? $filtros['fecha_hasta'] : 'sin fin';
    $partes[] = "Periodo {$desde} a {$hasta}";
  } else {
    $partes[] = 'Periodo: todos los registros';
  }

  if ($filtros['barrios']) {
    $partes[] = 'Barrios: ' . count($filtros['barrios']);
  }
  if ($filtros['barrio_categoria'] !== '') {
    $partes[] = 'Tipo de barrio: ' . nombre_categoria($filtros['barrio_categoria']);
  }
  if ($filtros['estados']) {
    $partes[] = 'Estados: ' . implode(', ', array_map('nombre_estado', $filtros['estados']));
  } else {
    $partes[] = 'Estados: todos';
  }
  if ($filtros['gravedades']) {
    $partes[] = 'Gravedad: ' . implode(', ', array_map('nombre_gravedad', $filtros['gravedades']));
  }
  if ($filtros['dias_min'] > 0) {
    $partes[] = 'Dias abiertos >= ' . $filtros['dias_min'];
  }
  if ($filtros['familias_mayor_cero']) {
    $partes[] = 'Con familias afectadas';
  }
  if ($filtros['calle_intransitable']) {
    $partes[] = 'Calles cortadas';
  }
  if ($filtros['con_foto']) {
    $partes[] = 'Con foto';
  }
  if ($filtros['con_seguimiento']) {
    $partes[] = 'Con seguimiento';
  }
  if ($filtros['responsable_area'] !== '') {
    $partes[] = 'Area: ' . $filtros['responsable_area'];
  }
  if ($filtros['categorias']) {
    $partes[] = 'Categorias de incidencia: ' . count($filtros['categorias']);
  }
  if ($filtros['tipos']) {
    $partes[] = 'Tipos: ' . count($filtros['tipos']);
  }
  if ($filtros['texto'] !== '') {
    $partes[] = 'Texto: "' . $filtros['texto'] . '"';
  }
  if ($filtros['incluir_pendientes']) {
    $partes[] = 'Incluye pendientes de aprobacion';
  }

  $detalle = implode(' | ', $partes);

  return mb_substr($detalle, 0, 500);
}

/**
 * Etiqueta legible del periodo para encabezados de documentos.
 *
 * @param array<string, mixed> $filtros
 */
function periodo_legible_reporte(array $filtros): string
{
  if ($filtros['fecha_desde'] !== '' && $filtros['fecha_hasta'] !== '') {
    return 'del ' . $filtros['fecha_desde'] . ' al ' . $filtros['fecha_hasta'];
  }
  if ($filtros['fecha_desde'] !== '') {
    return 'desde el ' . $filtros['fecha_desde'];
  }
  if ($filtros['fecha_hasta'] !== '') {
    return 'hasta el ' . $filtros['fecha_hasta'];
  }

  return 'todos los registros';
}

/**
 * Registra en auditoria la generacion/exportacion de una salida.
 *
 * Las acciones nuevas ('generar_reporte' y 'exportar_reporte') estan mapeadas
 * en admin/auditoria.php para que se vean con nombre y color propios.
 *
 * @param array<string, mixed> $filtros
 */
function registrar_salida_reporte(string $accion, string $tipo, array $filtros, int $total): void
{
  $usuario_id = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;

  $detalle = $tipo . ' (' . $total . ' ' . ($total === 1 ? 'caso' : 'casos') . '). '
    . describir_filtros_reporte($filtros);

  registrar_auditoria($usuario_id, $accion, null, mb_substr($detalle, 0, 1000));
}
