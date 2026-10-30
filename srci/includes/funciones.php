<?php
declare(strict_types=1);

define('MAX_FOTO_BYTES', 5 * 1024 * 1024); // 5 MB
define('UPLOADS_DIR', __DIR__ . '/../uploads/');
define('UPLOADS_URL', '/srci/uploads/');

// MIME types permitidos para fotos
const MIME_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp'];

// Sanitiza texto para salida HTML
function esc(string $valor): string
{
  return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

// --- Magic links de acceso (ingreso sin PIN) ---

// Fallback solo para desarrollo; en produccion definir en config.local.php
if (!defined('SRCI_MAGIC_SECRET')) {
  define('SRCI_MAGIC_SECRET', 'srci-dev-secret-cambiar-en-config-local');
}

define('MAGIC_LINK_DIAS', 30);

// Genera un magic link firmado (HMAC-SHA256) para que un usuario ingrese sin PIN
function generar_magic_link(int $usuario_id): string
{
  $expira  = time() + MAGIC_LINK_DIAS * 86400;
  $payload = $usuario_id . '.' . $expira;
  $firma   = hash_hmac('sha256', $payload, SRCI_MAGIC_SECRET);
  return 'https://echo3dlaser.com.ar/srci/acceso.php?t=' . $payload . '.' . $firma;
}

// Valida un token de magic link: devuelve el id de usuario o null si es invalido/vencido
function validar_magic_token(string $token): ?int
{
  $partes = explode('.', $token);
  if (count($partes) !== 3) {
    return null;
  }
  [$uid, $expira, $firma] = $partes;
  if (!ctype_digit($uid) || !ctype_digit($expira)) {
    return null;
  }
  $esperada = hash_hmac('sha256', $uid . '.' . $expira, SRCI_MAGIC_SECRET);
  if (!hash_equals($esperada, $firma)) {
    return null;
  }
  if ((int)$expira < time()) {
    return null;
  }
  return (int)$uid;
}

// Formatea fecha en formato legible en espanol
function fecha_legible(string $fecha_hora): string
{
  $ts = strtotime($fecha_hora);
  $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
  return date('d', $ts) . ' ' . $meses[(int)date('m', $ts) - 1] . ' ' . date('Y H:i', $ts);
}

// Genera un PIN numerico de 4 digitos como string
function generar_pin(): string
{
  return str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
}

// Devuelve el nombre legible del estado de incidencia
function nombre_estado(string $estado): string
{
  return match($estado) {
    'pendiente'  => 'Pendiente',
    'en_proceso' => 'En gestión',
    'resuelto'   => 'Resuelto',
    default      => 'Desconocido',
  };
}

// Devuelve clase CSS segun estado
function clase_estado(string $estado): string
{
  return match($estado) {
    'pendiente'  => 'estado-pendiente',
    'en_proceso' => 'estado-en-proceso',
    'resuelto'   => 'estado-resuelto',
    default      => '',
  };
}

// Devuelve el nombre legible de la gravedad
function nombre_gravedad(?string $gravedad): string
{
  return match($gravedad) {
    'baja'    => 'Baja',
    'media'   => 'Media',
    'alta'    => 'Alta',
    'critica' => 'Crítica',
    default   => 'Sin dato',
  };
}

// Devuelve clase CSS segun gravedad
function clase_gravedad(?string $gravedad): string
{
  return match($gravedad) {
    'baja'    => 'gravedad-baja',
    'media'   => 'gravedad-media',
    'alta'    => 'gravedad-alta',
    'critica' => 'gravedad-critica',
    default   => '',
  };
}

// Devuelve el nombre legible de la categoria del barrio
function nombre_categoria(?string $categoria): string
{
  return match($categoria) {
    'formal'       => 'Formal',
    'informal'     => 'Informal',
    'asentamiento' => 'Asentamiento',
    default        => 'Sin categoría',
  };
}

// Devuelve clase CSS segun categoria del barrio
function clase_categoria(?string $categoria): string
{
  return match($categoria) {
    'formal'       => 'cat-formal',
    'informal'     => 'cat-informal',
    'asentamiento' => 'cat-asentamiento',
    default        => 'cat-sin',
  };
}

// Color sugerido por categoria (fallback del render del poligono)
function color_categoria(?string $categoria): string
{
  return match($categoria) {
    'formal'       => '#2563eb',
    'informal'     => '#d97706',
    'asentamiento' => '#dc2626',
    default        => '#4f46e5',
  };
}

// Valida/normaliza un color hex #rrggbb; null si no es valido
function color_valido(?string $color): ?string
{
  if ($color === null || $color === '') return null;
  $color = trim($color);
  return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : null;
}

// --- Categorias de incidencias (etiquetas independientes del tipo, no excluyentes) ---

// Categorias activas (catalogo), cacheadas en static
function categorias_activas(): array
{
  static $categorias = null;
  if ($categorias === null) {
    try {
      $categorias = db()->query('SELECT id, clave, nombre, icono, color FROM categorias_incidencia WHERE activo = 1 ORDER BY id')->fetchAll();
    } catch (Throwable $e) {
      $categorias = [];
    }
  }
  return $categorias;
}

// Categorias marcadas como "visible a usuarios" (activas + visible_usuario = 1).
// Por ahora todas vienen en 0: el selector de categorias es solo de admins.
function categorias_visibles(): array
{
  static $visibles = null;
  if ($visibles === null) {
    try {
      $visibles = db()->query('SELECT id, clave, nombre, icono, color FROM categorias_incidencia WHERE activo = 1 AND visible_usuario = 1 ORDER BY id')->fetchAll();
    } catch (Throwable $e) {
      $visibles = [];
    }
  }
  return $visibles;
}

// True si hay categorias que los usuarios regulares pueden elegir.
// Si es false, index.php no muestra el selector de categorias (solo admins).
function hay_categorias_visibles(): bool
{
  return count(categorias_visibles()) > 0;
}

// Id de la categoria por defecto (Climaticos); 0 si no existe
function categoria_default_id(): int
{
  $st = db()->prepare('SELECT id FROM categorias_incidencia WHERE clave = :c LIMIT 1');
  $st->execute([':c' => 'climaticos']);
  return (int)($st->fetchColumn() ?: 0);
}

// Categorias asignadas a una incidencia (id, clave, nombre, icono, color)
function categorias_de_incidencia(int $incidencia_id): array
{
  $st = db()->prepare(
    'SELECT c.id, c.clave, c.nombre, c.icono, c.color
       FROM incidencia_categorias ic
       JOIN categorias_incidencia c ON c.id = ic.categoria_id
      WHERE ic.incidencia_id = :id
      ORDER BY c.id'
  );
  $st->execute([':id' => $incidencia_id]);
  return $st->fetchAll();
}

// Asigna (reemplaza) las categorias de una incidencia. Ignora ids invalidos o
// inactivos. Si no queda ninguna, aplica la categoria por defecto (Climaticos).
function guardar_categorias_incidencia(int $incidencia_id, array $categoria_ids): void
{
  $validas = [];
  $activas = [];
  foreach (categorias_activas() as $c) {
    $activas[(int)$c['id']] = true;
  }
  foreach ($categoria_ids as $cid) {
    $cid = (int)$cid;
    if ($cid > 0 && isset($activas[$cid]) && !in_array($cid, $validas, true)) {
      $validas[] = $cid;
    }
  }
  if (!$validas) {
    $def = categoria_default_id();
    if ($def > 0) $validas[] = $def;
  }

  db()->prepare('DELETE FROM incidencia_categorias WHERE incidencia_id = :id')->execute([':id' => $incidencia_id]);
  if ($validas) {
    $ins = db()->prepare('INSERT INTO incidencia_categorias (incidencia_id, categoria_id) VALUES (:i, :c)');
    foreach ($validas as $cid) {
      $ins->execute([':i' => $incidencia_id, ':c' => $cid]);
    }
  }
}

// Categorias de varias incidencias a la vez: [incidencia_id => [cats...]]
function categorias_por_incidencia(array $incidencia_ids): array
{
  $incidencia_ids = array_values(array_unique(array_map('intval', $incidencia_ids)));
  $incidencia_ids = array_filter($incidencia_ids, fn($v) => $v > 0);
  if (!$incidencia_ids) return [];

  $ph = implode(',', array_fill(0, count($incidencia_ids), '?'));
  $st = db()->prepare(
    "SELECT ic.incidencia_id, c.id, c.clave, c.nombre, c.icono, c.color
       FROM incidencia_categorias ic
       JOIN categorias_incidencia c ON c.id = ic.categoria_id
      WHERE ic.incidencia_id IN ($ph)
      ORDER BY c.id"
  );
  $st->execute($incidencia_ids);

  $mapa = [];
  foreach ($st->fetchAll() as $row) {
    $iid = (int)$row['incidencia_id'];
    unset($row['incidencia_id']);
    $mapa[$iid][] = $row;
  }
  return $mapa;
}

// --- Seguimiento de incidencias (notas fechadas, append-only) ---

// Notas de seguimiento de una incidencia, ordenadas por fecha ascendente.
// Cada fila trae nombre del autor (LEFT JOIN: el autor puede haberse eliminado).
function seguimiento_de_incidencia(int $incidencia_id): array
{
  try {
    $st = db()->prepare(
      'SELECT s.id, s.nota, s.fecha_hora, s.usuario_id, u.nombre AS usuario_nombre
         FROM incidencia_seguimiento s
         LEFT JOIN usuarios u ON u.id = s.usuario_id
        WHERE s.incidencia_id = :id
        ORDER BY s.id'
    );
    $st->execute([':id' => $incidencia_id]);
    return $st->fetchAll();
  } catch (Throwable $e) {
    return [];
  }
}

// Cuenta las notas de seguimiento de varias incidencias a la vez.
// Devuelve [incidencia_id => total]. Una sola query (GROUP BY).
function contar_seguimiento_por_incidencia(array $incidencia_ids): array
{
  $incidencia_ids = array_values(array_unique(array_map('intval', $incidencia_ids)));
  $incidencia_ids = array_filter($incidencia_ids, fn($v) => $v > 0);
  if (!$incidencia_ids) return [];

  try {
    $ph = implode(',', array_fill(0, count($incidencia_ids), '?'));
    $st = db()->prepare(
      "SELECT incidencia_id, COUNT(*) AS total
         FROM incidencia_seguimiento
        WHERE incidencia_id IN ($ph)
        GROUP BY incidencia_id"
    );
    $st->execute($incidencia_ids);

    $mapa = [];
    foreach ($st->fetchAll() as $row) {
      $mapa[(int)$row['incidencia_id']] = (int)$row['total'];
    }
    return $mapa;
  } catch (Throwable $e) {
    return [];
  }
}

// --- Espejo en Google Sheets (via Apps Script Web App) ---
// Configurar en config.local.php (fuera de git):
//   define('SRCI_SHEETS_WEBAPP_URL', 'https://script.google.com/macros/s/.../exec');
//   define('SRCI_SHEETS_SECRET', 'secreto_compartido');
// Fire-and-forget: si Google falla o tarda, el flujo principal sigue igual

// Envia un payload al webhook de Sheets; nunca lanza excepciones
function enviar_a_sheets(array $payload): void
{
  if (!defined('SRCI_SHEETS_WEBAPP_URL') || SRCI_SHEETS_WEBAPP_URL === '') {
    return;
  }
  try {
    $payload['secreto'] = defined('SRCI_SHEETS_SECRET') ? SRCI_SHEETS_SECRET : '';
    $ch = curl_init(SRCI_SHEETS_WEBAPP_URL);
    curl_setopt_array($ch, [
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
      CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,  // /exec responde 302: el echo final se llama con GET (el payload viaja en la URL)
      CURLOPT_CONNECTTIMEOUT => 2,
      CURLOPT_TIMEOUT        => 5,
    ]);
    curl_exec($ch);
    curl_close($ch);
  } catch (Throwable $e) {
    // El espejo no debe interrumpir el flujo
  }
}

// Ejecuta el sync de entrada (hoja -> app) llamando al Web App de Google
// Devuelve [ok, procesadas, errores, detalle] o [false, 0, 0, error]
function sincronizar_hoja(): array
{
  if (!defined('SRCI_SHEETS_WEBAPP_URL') || SRCI_SHEETS_WEBAPP_URL === '' || !defined('SRCI_SHEETS_SECRET')) {
    return [false, 0, 0, 'La integracion con Sheets no esta configurada.'];
  }
  try {
    $ch = curl_init(SRCI_SHEETS_WEBAPP_URL . '?accion=sync&secreto=' . urlencode(SRCI_SHEETS_SECRET));
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_CONNECTTIMEOUT => 3,
      CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!is_string($resp) || $resp === '') {
      return [false, 0, 0, 'Google no respondio. Probá de nuevo en un momento.'];
    }
    $datos = json_decode($resp, true);
    if (!is_array($datos)) {
      return [false, 0, 0, 'Respuesta inesperada de Google.'];
    }
    return [
      (bool)($datos['ok'] ?? false),
      (int)($datos['procesadas'] ?? 0),
      (int)($datos['errores'] ?? 0),
      $datos['detalle'] ?? [],
    ];
  } catch (Throwable $e) {
    return [false, 0, 0, 'Error de conexion con Google.'];
  }
}

// --- Barrios delimitados por poligono (ray casting, WGS84) ---

// Devuelve ['id' => int, 'nombre' => string] si el punto cae dentro del
// poligono de un barrio; null si no. Poligonos cacheados en static.
// Area aproximada de un anillo (shoelace, en grados^2) para resolver solapes:
// cuando varios poligonos contienen el punto gana el mas chico (el barrio
// interno que se ve "arriba", ej: Las Mandarinas dentro de Villa Itape).
function area_anillo_barrio(array $anillo): float
{
  $area = 0;
  $n = count($anillo);
  for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
    $area += ($anillo[$j][0] - $anillo[$i][0]) * ($anillo[$j][1] + $anillo[$i][1]);
  }
  return abs($area / 2);
}

function barrio_por_punto(float $lat, float $lng): ?array
{
  static $poligonos = null;
  if ($poligonos === null) {
    $poligonos = [];
    try {
      foreach (db()->query('SELECT id, nombre, poligono FROM barrios WHERE poligono IS NOT NULL') as $b) {
        $pol = json_decode((string)$b['poligono'], true);
        if (is_array($pol) && $pol) {
          $poligonos[] = ['id' => (int)$b['id'], 'nombre' => $b['nombre'], 'anillos' => $pol];
        }
      }
    } catch (Throwable $e) {
      $poligonos = [];
    }
  }
  // Con poligonos superpuestos gana el de MENOR area (el interno/visible)
  $ganador = null;
  $areaGanadora = INF;
  foreach ($poligonos as $p) {
    foreach ($p['anillos'] as $anillo) {
      $n = count($anillo); $dentro = false;
      for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $xi = $anillo[$i][0]; $yi = $anillo[$i][1]; // x = lat, y = lng
        $xj = $anillo[$j][0]; $yj = $anillo[$j][1];
        if ((($yi > $lng) != ($yj > $lng)) &&
            ($lat < ($xj - $xi) * ($lng - $yi) / ($yj - $yi) + $xi)) {
          $dentro = !$dentro;
        }
      }
      if ($dentro) {
        $area = area_anillo_barrio($anillo);
        if ($area < $areaGanadora) {
          $areaGanadora = $area;
          $ganador = ['id' => $p['id'], 'nombre' => $p['nombre']];
        }
      }
    }
  }
  return $ganador;
}

// Convierte un GeoJSON (FeatureCollection/Feature/Polygon/MultiPolygon) al
// formato interno de anillos [[[lat,lng],...], ...]. GeoJSON usa [lng,lat].
// Devuelve ['ok'=>bool, 'anillos'=>array, 'error'=>string].
// Nota: los huecos (agujeros) se descartan: el ray-casting no los soporta.
function geojson_a_anillos(string $json): array
{
  $json = trim($json);
  if ($json === '') {
    return ['ok' => false, 'anillos' => [], 'error' => 'No se recibio ningun GeoJSON.'];
  }
  if (strlen($json) > 2 * 1024 * 1024) {
    return ['ok' => false, 'anillos' => [], 'error' => 'El GeoJSON es demasiado grande (max 2 MB).'];
  }
  $data = json_decode($json, true);
  if (!is_array($data)) {
    return ['ok' => false, 'anillos' => [], 'error' => 'JSON invalido.'];
  }

  // Reunir las geometrias del documento
  $geometrias = [];
  if (!isset($data['type'])) {
    return ['ok' => false, 'anillos' => [], 'error' => 'El GeoJSON no tiene el campo "type".'];
  }
  if ($data['type'] === 'FeatureCollection' && isset($data['features']) && is_array($data['features'])) {
    foreach ($data['features'] as $f) {
      if (is_array($f) && isset($f['geometry'])) $geometrias[] = $f['geometry'];
      elseif (is_array($f) && isset($f['type'])) $geometrias[] = $f;
    }
  } elseif ($data['type'] === 'Feature' && isset($data['geometry'])) {
    $geometrias[] = $data['geometry'];
  } else {
    $geometrias[] = $data; // geometria suelta (Polygon/MultiPolygon)
  }

  $anillos  = [];
  $vertices = 0;
  foreach ($geometrias as $g) {
    if (!is_array($g) || !isset($g['type'], $g['coordinates'])) continue;
    if ($g['type'] === 'Polygon') {
      $poligonos = [$g['coordinates']];
    } elseif ($g['type'] === 'MultiPolygon') {
      $poligonos = $g['coordinates'];
    } else {
      continue; // ignora puntos, lineas, etc.
    }
    foreach ($poligonos as $poly) {
      if (!is_array($poly) || !isset($poly[0]) || !is_array($poly[0])) continue;
      $anillo = [];
      foreach ($poly[0] as $pt) { // [0] = anillo exterior (se descartan huecos)
        if (!is_array($pt) || count($pt) < 2) continue;
        $lng = (float)$pt[0];
        $lat = (float)$pt[1];
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) continue;
        $anillo[] = [$lat, $lng];
      }
      if (count($anillo) >= 3) {
        $anillos[] = $anillo;
        $vertices += count($anillo);
      }
    }
  }

  if (!$anillos) {
    return ['ok' => false, 'anillos' => [], 'error' => 'No se encontro ningun poligono valido.'];
  }
  if ($vertices > 20000) {
    return ['ok' => false, 'anillos' => [], 'error' => 'El poligono tiene demasiados vertices (max 20000).'];
  }
  return ['ok' => true, 'anillos' => $anillos, 'error' => ''];
}

// Inverso: convierte anillos internos a una FeatureCollection GeoJSON
// (cada anillo se exporta como su propio Polygon, con coordenadas [lng,lat]).
function anillos_a_geojson(array $anillos): array
{
  $features = [];
  foreach ($anillos as $anillo) {
    if (!is_array($anillo) || count($anillo) < 3) continue;
    $ring = [];
    foreach ($anillo as $pt) {
      if (!is_array($pt) || count($pt) < 2) continue;
      $ring[] = [(float)$pt[1], (float)$pt[0]]; // [lng,lat]
    }
    if (count($ring) >= 3) {
      $features[] = [
        'type'       => 'Feature',
        'properties' => new stdClass(),
        'geometry'   => ['type' => 'Polygon', 'coordinates' => [$ring]],
      ];
    }
  }
  return ['type' => 'FeatureCollection', 'features' => $features];
}

// Cuenta anillos y vertices de un poligono interno (JSON [[[lat,lng],...],...])
function contar_anillos_poligono(?string $poligono): array
{
  if ($poligono === null || $poligono === '') return ['anillos' => 0, 'vertices' => 0];
  $anillos = json_decode($poligono, true);
  if (!is_array($anillos)) return ['anillos' => 0, 'vertices' => 0];
  $vertices = 0;
  foreach ($anillos as $a) {
    if (is_array($a)) $vertices += count($a);
  }
  return ['anillos' => count($anillos), 'vertices' => $vertices];
}

// Registra una accion en el log de auditoria (quien, cuando, que)
// Nunca rompe el flujo principal si falla. $usuario_id puede ser null
// (eventos sin usuario logueado: bloqueos anti fuerza bruta del login).
function registrar_auditoria(?int $usuario_id, string $accion, ?int $incidencia_id, string $detalle = ''): void
{
  try {
    $stmt = db()->prepare('INSERT INTO auditoria (usuario_id, accion, incidencia_id, detalle) VALUES (:u, :a, :i, :d)');
    $stmt->execute([
      ':u' => $usuario_id,
      ':a' => $accion,
      ':i' => $incidencia_id,
      ':d' => $detalle !== '' ? $detalle : null,
    ]);
  } catch (PDOException $e) {
    // La auditoria no debe interrumpir el flujo
  }
}

// Valida y guarda una foto subida
// Devuelve la ruta relativa o lanza Exception
function guardar_foto(array $archivo): string
{
  if ($archivo['error'] !== UPLOAD_ERR_OK) {
    throw new RuntimeException('Error al recibir el archivo.');
  }
  if ($archivo['size'] > MAX_FOTO_BYTES) {
    throw new RuntimeException('La foto supera el limite de 5 MB.');
  }

  // Verificar MIME real con finfo (no confiar en $_FILES['type'])
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($archivo['tmp_name']);
  if (!in_array($mime, MIME_PERMITIDOS, true)) {
    throw new RuntimeException('Solo se aceptan imagenes JPG, PNG o WEBP.');
  }

  $extension  = match($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
  };
  $nombre_seguro = bin2hex(random_bytes(16)) . '.' . $extension;
  $ruta_destino  = UPLOADS_DIR . $nombre_seguro;

  if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
    throw new RuntimeException('No pudimos guardar la foto. Proba de nuevo.');
  }

  return $nombre_seguro;
}

// Envia un email con el PIN al usuario (HTML con estilo + texto plano de respaldo)
function enviar_pin_por_email(string $email, string $nombre, string $pin): bool
{
  $url    = 'https://echo3dlaser.com.ar/srci/';
  $asunto = '=?UTF-8?B?' . base64_encode('Tu PIN de acceso — SRCI') . '?=';

  // Parte texto plano (clientes sin HTML)
  $texto = "Hola {$nombre},\n\n"
         . "Tu PIN de acceso al Sistema de Reporte Ciudadano es: {$pin}\n\n"
         . "Ingresa con tu email y este PIN en: {$url}\n\n"
         . "Si no pediste este PIN, ignora este mensaje.\n";

  // Parte HTML (estilos inline: los clientes de correo ignoran <style>)
  $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">
        <tr><td style="background:#4f46e5;padding:26px 32px;text-align:center;">
          <div style="font-size:30px;line-height:1;">🗺️</div>
          <div style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:.5px;margin-top:6px;">SRCI</div>
          <div style="color:#c7d2fe;font-size:13px;margin-top:2px;">Sistema de Reporte Ciudadano</div>
        </td></tr>
        <tr><td style="padding:30px 32px;">
          <p style="margin:0 0 8px;color:#0f172a;font-size:17px;font-weight:700;">Hola {$nombre} 👋</p>
          <p style="margin:0 0 20px;color:#475569;font-size:14px;line-height:1.6;">Este es tu PIN de acceso al mapa de incidencias. Ingresalo junto a tu email en la pantalla de login.</p>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr><td align="center" style="padding:6px 0 20px;">
              <div style="display:inline-block;background:#eef2ff;border:2px dashed #4f46e5;border-radius:12px;padding:14px 34px;">
                <span style="font-size:32px;font-weight:800;letter-spacing:10px;color:#4f46e5;">{$pin}</span>
              </div>
            </td></tr>
          </table>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr><td align="center">
              <a href="{$url}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:13px 34px;border-radius:10px;">Entrar al mapa</a>
            </td></tr>
          </table>
          <p style="margin:18px 0 0;color:#64748b;font-size:12px;line-height:1.6;">Si el botón no funciona, copiá este enlace:<br><a href="{$url}" style="color:#4f46e5;">{$url}</a></p>
        </td></tr>
        <tr><td style="padding:16px 32px;border-top:1px solid #e2e8f0;">
          <p style="margin:0;color:#94a3b8;font-size:11px;line-height:1.6;">Si no pediste este PIN, ignorá este mensaje. Correo automático del Sistema de Reporte Ciudadano — echo3dlaser.com.ar</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

  $limite = md5(uniqid((string)random_int(0, mt_getrandmax()), true));
  $headers = implode("\r\n", [
    'From: SRCI <admin@echo3dlaser.com.ar>',
    'MIME-Version: 1.0',
    "Content-Type: multipart/alternative; boundary=\"{$limite}\"",
  ]);

  $cuerpo = "--{$limite}\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($texto)) . "\r\n"
          . "--{$limite}\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($html)) . "\r\n"
          . "--{$limite}--\r\n";

  // Envelope sender alineado con el From (evita DMARC:Quarantine / spam de Gmail)
  return @mail($email, $asunto, $cuerpo, $headers, '-fadmin@echo3dlaser.com.ar');
}

// Envia el email de activacion: link de acceso directo (magic link, sin PIN)
// + PIN de respaldo para cuando el link venza. Usada por la activacion en lotes.
function enviar_activacion_por_email(string $email, string $nombre, string $link, string $pin): bool
{
  $asunto = '=?UTF-8?B?' . base64_encode('Tu acceso al SRCI — cuenta activada') . '?=';
  $dias_link = (string)MAGIC_LINK_DIAS;

  // Parte texto plano (clientes sin HTML)
  $texto = "Hola {$nombre},\n\n"
         . "Tu cuenta del Sistema de Reporte Ciudadano fue activada.\n\n"
         . "Entra directo con este link (válido " . MAGIC_LINK_DIAS . " días, sin PIN):\n"
         . "{$link}\n\n"
         . "Tu PIN de respaldo (entra con tu email + este PIN cuando el link venza): {$pin}\n\n"
         . "Si no esperabas este mensaje, ignoralo.\n";

  // Parte HTML (estilos inline: los clientes de correo ignoran <style>)
  $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">
        <tr><td style="background:#4f46e5;padding:26px 32px;text-align:center;">
          <div style="font-size:30px;line-height:1;">🗺️</div>
          <div style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:.5px;margin-top:6px;">SRCI</div>
          <div style="color:#c7d2fe;font-size:13px;margin-top:2px;">Sistema de Reporte Ciudadano</div>
        </td></tr>
        <tr><td style="padding:30px 32px;">
          <p style="margin:0 0 8px;color:#0f172a;font-size:17px;font-weight:700;">Hola {$nombre} 👋</p>
          <p style="margin:0 0 20px;color:#475569;font-size:14px;line-height:1.6;">Tu cuenta fue <strong>activada</strong>. Tocá el botón para entrar directo al mapa de incidencias (no hace falta PIN).</p>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr><td align="center">
              <a href="{$link}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:13px 34px;border-radius:10px;">Entrar al mapa</a>
            </td></tr>
            <tr><td align="center" style="padding-top:8px;">
              <span style="color:#64748b;font-size:12px;">Link válido por {$dias_link} días — si el botón no funciona, copiá este enlace:</span><br>
              <a href="{$link}" style="color:#4f46e5;font-size:12px;word-break:break-all;">{$link}</a>
            </td></tr>
          </table>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:22px;">
            <tr><td align="center" style="padding:6px 0 4px;">
              <div style="display:inline-block;background:#eef2ff;border:2px dashed #4f46e5;border-radius:12px;padding:12px 30px;">
                <span style="font-size:26px;font-weight:800;letter-spacing:8px;color:#4f46e5;">{$pin}</span>
              </div>
            </td></tr>
            <tr><td align="center" style="padding-top:6px;">
              <span style="color:#64748b;font-size:12px;">PIN de respaldo: cuando el link venza, entra con tu email + este PIN.</span>
            </td></tr>
          </table>
        </td></tr>
        <tr><td style="padding:16px 32px;border-top:1px solid #e2e8f0;">
          <p style="margin:0;color:#94a3b8;font-size:11px;line-height:1.6;">Si no esperabas este mensaje, ignoralo. Correo automático del Sistema de Reporte Ciudadano — echo3dlaser.com.ar</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

  $limite = md5(uniqid((string)random_int(0, mt_getrandmax()), true));
  $headers = implode("\r\n", [
    'From: SRCI <admin@echo3dlaser.com.ar>',
    'MIME-Version: 1.0',
    "Content-Type: multipart/alternative; boundary=\"{$limite}\"",
  ]);

  $cuerpo = "--{$limite}\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($texto)) . "\r\n"
          . "--{$limite}\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($html)) . "\r\n"
          . "--{$limite}--\r\n";

  // Envelope sender alineado con el From (evita DMARC:Quarantine / spam de Gmail)
  return @mail($email, $asunto, $cuerpo, $headers, '-fadmin@echo3dlaser.com.ar');
}

// --- Notificaciones por email al creador de una incidencia ---
// True si el email es ruteable (presente y no de dominio interno *.local)
function email_ruteable(?string $email): bool
{
  return $email !== null && $email !== '' && !str_ends_with(strtolower($email), '.local');
}

// Notifica al creador de la incidencia que algo paso con su reporte:
// crear | editar | estado | ocultar | restaurar. Lo haga el propio creador
// o un organizador, el email sale igual. Nunca interrumpe el flujo principal.
// $detalle: texto extra opcional (ej: "Estado: Pendiente → Resuelto" o campos cambiados).
function notificar_creador_por_email(int $incidencia_id, string $evento, string $detalle = ''): bool
{
  $asuntos = [
    'crear'     => 'Tu reporte #' . $incidencia_id . ' quedó cargado — SRCI',
    'editar'    => 'Tu reporte #' . $incidencia_id . ' fue actualizado — SRCI',
    'estado'    => 'Tu reporte #' . $incidencia_id . ': nuevo estado — SRCI',
    'ocultar'   => 'Tu reporte #' . $incidencia_id . ' fue ocultado — SRCI',
    'restaurar' => 'Tu reporte #' . $incidencia_id . ' fue restaurado — SRCI',
    'aprobar'   => 'Tu reporte #' . $incidencia_id . ' fue aprobado y ya está publicado — SRCI',
    'rechazar'  => 'Tu reporte #' . $incidencia_id . ' fue rechazado — SRCI',
  ];
  if (!isset($asuntos[$evento])) {
    return false;
  }

  try {
    $st = db()->prepare(
      'SELECT i.id, i.usuario_id, i.direccion, i.gravedad, i.estado,
              t.nombre AS tipo_nombre, t.icono AS tipo_icono,
              b.nombre AS barrio_nombre,
              u.email  AS creador_email, u.nombre AS creador_nombre
         FROM incidencias i
         JOIN tipos_incidencia t ON t.id = i.tipo_id
         LEFT JOIN barrios b     ON b.id = i.barrio_id
         JOIN usuarios u         ON u.id = i.usuario_id
        WHERE i.id = :id LIMIT 1'
    );
    $st->execute([':id' => $incidencia_id]);
    $inc = $st->fetch();
  } catch (Throwable $e) {
    return false;
  }
  if (!$inc || !email_ruteable((string)($inc['creador_email'] ?? ''))) {
    return false;
  }

  $creador      = (string)$inc['creador_nombre'];
  $actor_nombre = (string)($_SESSION['nombre'] ?? 'Un usuario');
  $actor_rol    = (function_exists('es_admin') && es_admin()) ? 'organizador' : 'referente';
  $lo_hizo      = isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] === (int)$inc['usuario_id']
    ? 'Lo hiciste vos desde tu cuenta.'
    : "Lo hizo {$actor_nombre} ({$actor_rol}).";

  $intro = match($evento) {
    'crear'     => 'Tu reporte quedó cargado en el sistema. ¡Gracias por avisar!',
    'editar'    => 'Tu reporte fue actualizado.',
    'estado'    => 'El estado de tu reporte cambió.',
    'ocultar'   => 'Tu reporte fue ocultado de la lista y del mapa (no se borra: queda registrado).',
    'restaurar' => 'Tu reporte vuelve a estar visible en la lista y en el mapa.',
    'aprobar'   => 'Tu reporte fue aprobado y ya está publicado: aparece en la lista y en el mapa.',
    'rechazar'  => 'Tu reporte fue rechazado, así que no se publica (queda registrado, no se borra).',
  };

  $url            = 'https://echo3dlaser.com.ar/srci/detalle.php?id=' . $incidencia_id;
  $asunto         = '=?UTF-8?B?' . base64_encode($asuntos[$evento]) . '?=';
  $estado_legible = nombre_estado((string)$inc['estado']);
  $lineas = [
    'Tipo'      => trim(($inc['tipo_icono'] ?? '') . ' ' . ($inc['tipo_nombre'] ?? '')),
    'Dirección' => ($inc['direccion'] ?? '') !== '' ? (string)$inc['direccion'] : '—',
    'Barrio'    => ($inc['barrio_nombre'] ?? '') !== '' ? (string)$inc['barrio_nombre'] : '—',
    'Gravedad'  => nombre_gravedad(isset($inc['gravedad']) ? (string)$inc['gravedad'] : null),
    'Estado'    => $estado_legible,
  ];

  // Parte texto plano (clientes sin HTML)
  $texto = "Hola {$creador},\n\n"
         . "{$intro}\n"
         . "{$lo_hizo}\n"
         . ($detalle !== '' ? "{$detalle}\n" : '')
         . "\nResumen del reporte #{$incidencia_id}:\n";
  foreach ($lineas as $k => $v) {
    $texto .= "- {$k}: {$v}\n";
  }
  $texto .= "\nVer el reporte: {$url}\n\n"
          . "Mensaje automático del SRCI — no respondas este email.\n";

  // Parte HTML (estilos inline: los clientes de correo ignoran <style>)
  $filas_html = '';
  foreach ($lineas as $k => $v) {
    $filas_html .= '<tr>'
      . '<td style="padding:8px 12px;color:#64748b;font-size:13px;font-weight:700;border-bottom:1px solid #f1f5f9;white-space:nowrap;">' . esc($k) . '</td>'
      . '<td style="padding:8px 12px;color:#0f172a;font-size:13px;border-bottom:1px solid #f1f5f9;">' . esc($v) . '</td>'
      . '</tr>';
  }
  $creador_html = esc($creador);
  $lo_hizo_html = esc($lo_hizo);
  $detalle_html = $detalle !== ''
    ? '<p style="margin:0 0 16px;color:#475569;font-size:13px;line-height:1.6;background:#f8fafc;border-left:3px solid #4f46e5;padding:10px 14px;border-radius:0 8px 8px 0;">' . esc($detalle) . '</p>'
    : '';
  $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">
        <tr><td style="background:#4f46e5;padding:26px 32px;text-align:center;">
          <div style="font-size:30px;line-height:1;">🗺️</div>
          <div style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:.5px;margin-top:6px;">SRCI</div>
          <div style="color:#c7d2fe;font-size:13px;margin-top:2px;">Sistema de Reporte Ciudadano</div>
        </td></tr>
        <tr><td style="padding:30px 32px;">
          <p style="margin:0 0 8px;color:#0f172a;font-size:17px;font-weight:700;">Hola {$creador_html} 👋</p>
          <p style="margin:0 0 6px;color:#475569;font-size:14px;line-height:1.6;">{$intro}</p>
          <p style="margin:0 0 16px;color:#94a3b8;font-size:12px;">{$lo_hizo_html}</p>
          {$detalle_html}
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;margin-bottom:20px;">
            {$filas_html}
          </table>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr><td align="center">
              <a href="{$url}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:13px 34px;border-radius:10px;">Ver el reporte</a>
            </td></tr>
          </table>
          <p style="margin:18px 0 0;color:#64748b;font-size:12px;line-height:1.6;">Si el botón no funciona, copiá este enlace:<br><a href="{$url}" style="color:#4f46e5;">{$url}</a></p>
        </td></tr>
        <tr><td style="padding:16px 32px;border-top:1px solid #e2e8f0;">
          <p style="margin:0;color:#94a3b8;font-size:11px;line-height:1.6;">Mensaje automático del Sistema de Reporte Ciudadano — echo3dlaser.com.ar. No respondas este email.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

  $limite = md5(uniqid((string)random_int(0, mt_getrandmax()), true));
  $headers = implode("\r\n", [
    'From: SRCI <admin@echo3dlaser.com.ar>',
    'MIME-Version: 1.0',
    "Content-Type: multipart/alternative; boundary=\"{$limite}\"",
  ]);

  $cuerpo = "--{$limite}\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($texto)) . "\r\n"
          . "--{$limite}\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($html)) . "\r\n"
          . "--{$limite}--\r\n";

  // Envelope sender alineado con el From (evita DMARC:Quarantine / spam de Gmail)
  return @mail((string)$inc['creador_email'], $asunto, $cuerpo, $headers, '-fadmin@echo3dlaser.com.ar');
}

// Avisa al PROPIETARIO de la red que entro un reporte pendiente de aprobacion.
// Solo se usa para reportes con aprobado = 0. Nunca interrumpe el flujo principal.
function notificar_propietario_por_email(int $incidencia_id, int $propietario_id): bool
{
  try {
    $st = db()->prepare(
      'SELECT i.id, i.aprobado, i.direccion, i.gravedad, i.estado, i.fecha_hora,
              t.nombre AS tipo_nombre, t.icono AS tipo_icono,
              b.nombre AS barrio_nombre,
              u.nombre AS autor_nombre,
              p.email AS propietario_email, p.nombre AS propietario_nombre
         FROM incidencias i
         JOIN tipos_incidencia t ON t.id = i.tipo_id
         LEFT JOIN barrios b     ON b.id = i.barrio_id
         JOIN usuarios u         ON u.id = i.usuario_id
         JOIN usuarios p         ON p.id = :propietario
        WHERE i.id = :id AND i.aprobado = 0
        LIMIT 1'
    );
    $st->execute([':id' => $incidencia_id, ':propietario' => $propietario_id]);
    $inc = $st->fetch();
  } catch (Throwable $e) {
    return false;
  }
  if (!$inc || !email_ruteable((string)($inc['propietario_email'] ?? ''))) {
    return false;
  }

  $destinatario = (string)$inc['propietario_nombre'];
  $url_panel    = 'https://echo3dlaser.com.ar/srci/perfil.php#pendientes-aprobacion';
  $url_detalle  = 'https://echo3dlaser.com.ar/srci/detalle.php?id=' . $incidencia_id;
  $asunto       = '=?UTF-8?B?' . base64_encode('Reporte pendiente de aprobación #'.$incidencia_id.' — SRCI') . '?=';

  $lineas = [
    'Tipo'      => trim(($inc['tipo_icono'] ?? '') . ' ' . ($inc['tipo_nombre'] ?? '')),
    'Dirección' => ($inc['direccion'] ?? '') !== '' ? (string)$inc['direccion'] : '—',
    'Barrio'    => ($inc['barrio_nombre'] ?? '') !== '' ? (string)$inc['barrio_nombre'] : '—',
    'Gravedad'  => nombre_gravedad(isset($inc['gravedad']) ? (string)$inc['gravedad'] : null),
    'Reportado por' => (string)$inc['autor_nombre'],
    'Fecha'     => fecha_legible((string)$inc['fecha_hora']),
  ];

  $texto = "Hola {$destinatario},\n\n"
         . "Entró un reporte de tu red de colaboradores que todavía no fue publicado.\n"
         . "Tenés que aprobarlo o rechazarlo para que se vea en el mapa.\n\n";
  foreach ($lineas as $k => $v) {
    $texto .= "- {$k}: {$v}\n";
  }
  $texto .= "\nRevisar pendientes: {$url_panel}\n"
         . "Ver el reporte: {$url_detalle}\n\n"
         . "Mensaje automático del SRCI — no respondas este email.\n";

  $filas_html = '';
  foreach ($lineas as $k => $v) {
    $filas_html .= '<tr>'
      . '<td style="padding:8px 12px;color:#64748b;font-size:13px;font-weight:700;border-bottom:1px solid #f1f5f9;white-space:nowrap;">' . esc($k) . '</td>'
      . '<td style="padding:8px 12px;color:#0f172a;font-size:13px;border-bottom:1px solid #f1f5f9;">' . esc($v) . '</td>'
      . '</tr>';
  }
  $dest_html = esc($destinatario);
  $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">
        <tr><td style="background:#4f46e5;padding:26px 32px;text-align:center;">
          <div style="font-size:30px;line-height:1;">🗺️</div>
          <div style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:.5px;margin-top:6px;">SRCI</div>
          <div style="color:#c7d2fe;font-size:13px;margin-top:2px;">Sistema de Reporte Ciudadano</div>
        </td></tr>
        <tr><td style="padding:30px 32px;">
          <p style="margin:0 0 8px;color:#0f172a;font-size:17px;font-weight:700;">Hola {$dest_html} 👋</p>
          <p style="margin:0 0 16px;color:#475569;font-size:14px;line-height:1.6;">Entró un reporte de tu red de colaboradores que todavía no fue publicado. Aprobálo o rechazalo para que se vea en el mapa.</p>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;margin-bottom:20px;">
            {$filas_html}
          </table>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr><td align="center">
              <a href="{$url_panel}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:13px 34px;border-radius:10px;">Revisar pendientes</a>
            </td></tr>
          </table>
          <p style="margin:18px 0 0;color:#64748b;font-size:12px;line-height:1.6;">Si el botón no funciona, copiá este enlace:<br><a href="{$url_panel}" style="color:#4f46e5;">{$url_panel}</a></p>
        </td></tr>
        <tr><td style="padding:16px 32px;border-top:1px solid #e2e8f0;">
          <p style="margin:0;color:#94a3b8;font-size:11px;line-height:1.6;">Mensaje automático del Sistema de Reporte Ciudadano — echo3dlaser.com.ar. No respondas este email.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

  $limite = md5(uniqid((string)random_int(0, mt_getrandmax()), true));
  $headers = implode("\r\n", [
    'From: SRCI <admin@echo3dlaser.com.ar>',
    'MIME-Version: 1.0',
    "Content-Type: multipart/alternative; boundary=\"{$limite}\"",
  ]);

  $cuerpo = "--{$limite}\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($texto)) . "\r\n"
          . "--{$limite}\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($html)) . "\r\n"
          . "--{$limite}--\r\n";

  return @mail((string)$inc['propietario_email'], $asunto, $cuerpo, $headers, '-fadmin@echo3dlaser.com.ar');
}

// --- Red de colaboradores (tabla `colaboradores`) ---
// Modo actual (2026-10-29): la red la gestionan SOLO los admins (designan
// colaboradores y aprueban/rechazan sus reportes). El flag deja la puerta
// abierta a abrirla a todos los usuarios (cada uno con su propia red) sin
// tocar el codigo: basta con ponerlo en false.
if (!defined('SRCI_COLABORADORES_SOLO_ADMIN')) {
  define('SRCI_COLABORADORES_SOLO_ADMIN', true);
}

// True si el usuario puede gestionar la red de colaboradores (designar o quitar)
function puede_gestionar_colaboradores(?int $usuario_id = null): bool
{
  if (SRCI_COLABORADORES_SOLO_ADMIN) {
    return function_exists('es_admin') && es_admin();
  }
  return $usuario_id !== null && $usuario_id > 0;
}

// True si el usuario puede aprobar/rechazar reportes
function puede_gestionar_aprobaciones(?int $usuario_id = null): bool
{
  return puede_gestionar_colaboradores($usuario_id);
}

// True si el usuario pertenece a una red como colaborador (vinculo aprobado)
function es_colaborador(int $usuario_id): bool
{
  if (!isset($GLOBALS['srci_cache_colaboradores'])) {
    $GLOBALS['srci_cache_colaboradores'] = [];
  }
  if (isset($GLOBALS['srci_cache_colaboradores'][$usuario_id])) {
    return $GLOBALS['srci_cache_colaboradores'][$usuario_id];
  }
  try {
    $st = db()->prepare("SELECT 1 FROM colaboradores WHERE colaborador_id = :id AND estado = 'aprobado' LIMIT 1");
    $st->execute([':id' => $usuario_id]);
    $GLOBALS['srci_cache_colaboradores'][$usuario_id] = (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    $GLOBALS['srci_cache_colaboradores'][$usuario_id] = false;
  }
  return $GLOBALS['srci_cache_colaboradores'][$usuario_id];
}

// Invalida la cache de es_colaborador() (tras alta o baja de un vinculo)
function _invalidar_cache_colaboradores(?int $usuario_id = null): void
{
  if (!isset($GLOBALS['srci_cache_colaboradores'])) {
    return;
  }
  if ($usuario_id === null) {
    $GLOBALS['srci_cache_colaboradores'] = [];
    return;
  }
  unset($GLOBALS['srci_cache_colaboradores'][$usuario_id]);
}

// Propietario de la red a la que pertenece el colaborador (null si no es colaborador)
function propietario_de_colaborador(int $colaborador_id): ?int
{
  try {
    $st = db()->prepare("SELECT propietario_id FROM colaboradores WHERE colaborador_id = :id AND estado = 'aprobado' ORDER BY id DESC LIMIT 1");
    $st->execute([':id' => $colaborador_id]);
    $pid = $st->fetchColumn();
  } catch (Throwable $e) {
    return null;
  }
  return $pid === false ? null : (int)$pid;
}

// Miembros del grupo: el propietario + sus colaboradores con vinculo aprobado
function ids_grupo_propietario(int $propietario_id): array
{
  try {
    $st = db()->prepare("SELECT colaborador_id FROM colaboradores WHERE propietario_id = :id AND estado = 'aprobado'");
    $st->execute([':id' => $propietario_id]);
    $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
  } catch (Throwable $e) {
    $ids = [];
  }
  $ids[] = $propietario_id;
  return array_values(array_unique($ids));
}

// Colaboradores de un propietario (con datos del usuario)
function colaboradores_de(int $propietario_id): array
{
  $st = db()->prepare(
    "SELECT c.id, c.colaborador_id, c.estado, c.creado_en,
            u.nombre, u.email, u.activo, u.rol
       FROM colaboradores c
       JOIN usuarios u ON u.id = c.colaborador_id
      WHERE c.propietario_id = :id
      ORDER BY c.estado ASC, u.nombre ASC"
  );
  $st->execute([':id' => $propietario_id]);
  return $st->fetchAll();
}

// Reportes pendientes de aprobacion.
// Admin (modo actual): todos los pendientes. Propietario de red: los de su grupo.
function incidencias_pendientes_de_aprobacion(int $usuario_id, bool $es_admin): array
{
  if ($es_admin) {
    $st = db()->prepare(
      "SELECT i.id, i.direccion, i.gravedad, i.estado, i.fecha_hora, i.aprobado,
              t.nombre AS tipo_nombre, t.icono AS tipo_icono,
              b.nombre AS barrio_nombre, u.nombre AS usuario_nombre
         FROM incidencias i
         JOIN tipos_incidencia t ON t.id = i.tipo_id
         JOIN usuarios u         ON u.id = i.usuario_id
         LEFT JOIN barrios b     ON b.id = i.barrio_id
        WHERE i.aprobado = 0 AND i.oculto = 0
        ORDER BY i.fecha_hora DESC
        LIMIT 100"
    );
    $st->execute();
    return $st->fetchAll();
  }

  $ids = ids_grupo_propietario($usuario_id);
  if (!$ids) {
    return [];
  }
  $ids = array_values($ids);
  // Placeholders separados para cada IN: con prepares nativos un parametro con
  // nombre no se puede repetir en la misma consulta
  $ph_prop = [];
  $ph_usu  = [];
  foreach ($ids as $n => $id) {
    $ph_prop[':gp' . $n] = (int)$id;
    $ph_usu[':gu' . $n]  = (int)$id;
  }
  $lista_prop = implode(',', array_keys($ph_prop));
  $lista_usu  = implode(',', array_keys($ph_usu));
  $st = db()->prepare(
    "SELECT i.id, i.direccion, i.gravedad, i.estado, i.fecha_hora, i.aprobado,
            t.nombre AS tipo_nombre, t.icono AS tipo_icono,
            b.nombre AS barrio_nombre, u.nombre AS usuario_nombre
       FROM incidencias i
       JOIN tipos_incidencia t ON t.id = i.tipo_id
       JOIN usuarios u         ON u.id = i.usuario_id
       LEFT JOIN barrios b     ON b.id = i.barrio_id
      WHERE i.aprobado = 0 AND i.oculto = 0
        AND (i.propietario_id IN ({$lista_prop}) OR i.usuario_id IN ({$lista_usu}))
      ORDER BY i.fecha_hora DESC
      LIMIT 100"
  );
  $st->execute(array_merge($ph_prop, $ph_usu));
  return $st->fetchAll();
}

// Condicion SQL de visibilidad de incidencias (se aplica SIEMPRE en el servidor).
// Admin: ve todas (incluidas las pendientes). Usuario: ve las aprobadas y sus
// propias pendientes (para poder seguir su propio reporte antes de publicarse).
function condiciones_visibilidad_incidencias(int $usuario_id, bool $es_admin, string $alias = 'i'): array
{
  if ($es_admin) {
    return ['1 = 1', []];
  }
  return [
    "({$alias}.aprobado = 1 OR {$alias}.usuario_id = :uid_vis)",
    [':uid_vis' => $usuario_id],
  ];
}

// Alta de colaborador por email. El vinculo nace APROBADO (no requiere
// aprobacion de la vinculacion). Devuelve ['ok' => bool, 'mensaje' => string].
function agregar_colaborador(int $propietario_id, string $email, int $actor_id): array
{
  $email = strtolower(trim($email));
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return ['ok' => false, 'mensaje' => 'Ingresá un email válido.'];
  }

  $st = db()->prepare('SELECT id, nombre, rol FROM usuarios WHERE LOWER(email) = :email LIMIT 1');
  $st->execute([':email' => $email]);
  $u = $st->fetch();
  if (!$u) {
    return ['ok' => false, 'mensaje' => 'No hay ningún usuario con ese email.'];
  }

  $colaborador_id = (int)$u['id'];
  if ($colaborador_id === $propietario_id) {
    return ['ok' => false, 'mensaje' => 'No podés ser tu propio colaborador.'];
  }
  if (($u['rol'] ?? '') === 'admin') {
    return ['ok' => false, 'mensaje' => 'Un administrador no puede ser colaborador.'];
  }

  $st = db()->prepare('SELECT id, estado FROM colaboradores WHERE propietario_id = :p AND colaborador_id = :c LIMIT 1');
  $st->execute([':p' => $propietario_id, ':c' => $colaborador_id]);
  $existente = $st->fetch();

  if ($existente) {
    if ($existente['estado'] === 'aprobado') {
      return ['ok' => false, 'mensaje' => $u['nombre'] . ' ya es tu colaborador.'];
    }
    // Reactivar un vinculo dado de baja (no se borra: queda en el historial)
    db()->prepare("UPDATE colaboradores SET estado = 'aprobado', creado_en = NOW(), creado_por = :actor WHERE id = :id")
      ->execute([':actor' => $actor_id, ':id' => (int)$existente['id']]);
    _invalidar_cache_colaboradores($colaborador_id);
    registrar_auditoria($actor_id, 'agregar_colaborador', null, 'Reactivó como colaborador a ' . $u['nombre'] . ' (' . $email . ')');
    return ['ok' => true, 'mensaje' => $u['nombre'] . ' vuelve a ser tu colaborador.'];
  }

  // Un usuario pertenece a UNA sola red: si ya es colaborador de otro
  // propietario, no se puede sumar a otra.
  $st = db()->prepare('SELECT c.propietario_id, u.nombre AS propietario_nombre FROM colaboradores c JOIN usuarios u ON u.id = c.propietario_id WHERE c.colaborador_id = :c LIMIT 1');
  $st->execute([':c' => $colaborador_id]);
  $otra_red = $st->fetch();
  if ($otra_red) {
    return ['ok' => false, 'mensaje' => $u['nombre'] . ' ya es colaborador de ' . $otra_red['propietario_nombre'] . '. Cada usuario puede pertenecer a una sola red.'];
  }

  db()->prepare("INSERT INTO colaboradores (propietario_id, colaborador_id, estado, creado_por) VALUES (:p, :c, 'aprobado', :actor)")
    ->execute([':p' => $propietario_id, ':c' => $colaborador_id, ':actor' => $actor_id]);
  _invalidar_cache_colaboradores($colaborador_id);
  registrar_auditoria($actor_id, 'agregar_colaborador', null, 'Designó como colaborador a ' . $u['nombre'] . ' (' . $email . ')');

  return ['ok' => true, 'mensaje' => $u['nombre'] . ' es tu colaborador. Sus reportes quedan pendientes de tu aprobación.'];
}

// Baja de colaborador: el vinculo pasa a 'rechazado' (nunca se borra: queda trazado)
function quitar_colaborador(int $propietario_id, int $colaborador_id, int $actor_id): array
{
  $st = db()->prepare("SELECT u.nombre, u.email FROM colaboradores c JOIN usuarios u ON u.id = c.colaborador_id WHERE c.propietario_id = :p AND c.colaborador_id = :c LIMIT 1");
  $st->execute([':p' => $propietario_id, ':c' => $colaborador_id]);
  $fila = $st->fetch();
  if (!$fila) {
    return ['ok' => false, 'mensaje' => 'Ese usuario no es tu colaborador.'];
  }

  db()->prepare("UPDATE colaboradores SET estado = 'rechazado' WHERE propietario_id = :p AND colaborador_id = :c")
    ->execute([':p' => $propietario_id, ':c' => $colaborador_id]);
  _invalidar_cache_colaboradores($colaborador_id);
  registrar_auditoria($actor_id, 'quitar_colaborador', null, 'Quitó como colaborador a ' . $fila['nombre'] . ' (' . $fila['email'] . ')');

  return ['ok' => true, 'mensaje' => $fila['nombre'] . ' ya no es tu colaborador.'];
}
  