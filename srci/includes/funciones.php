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
      if ($dentro) return ['id' => $p['id'], 'nombre' => $p['nombre']];
    }
  }
  return null;
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
// Nunca rompe el flujo principal si falla
function registrar_auditoria(int $usuario_id, string $accion, ?int $incidencia_id, string $detalle = ''): void
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
  