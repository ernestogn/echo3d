<?php
/**
 * EventoModel — acceso a carp_eventos y carp_evento_equipos
 * CRÍTICO: toda query usa tenant_id de sesión
 */
class EventoModel {

    // Estados válidos y transiciones permitidas
    public const ESTADOS = [
        'consulta', 'presupuestado', 'reservado_con_sena', 'confirmado',
        'en_montaje', 'en_curso', 'en_desmontaje',
        'finalizado', 'cerrado', 'cancelado', 'postergado',
    ];

    public const TRANSICIONES_VALIDAS = [
        'consulta'          => ['presupuestado', 'cancelado'],
        'presupuestado'     => ['reservado_con_sena', 'cancelado', 'consulta'],
        'reservado_con_sena'=> ['confirmado', 'cancelado', 'postergado'],
        'confirmado'        => ['en_montaje', 'cancelado', 'postergado'],
        'en_montaje'        => ['en_curso', 'cancelado'],
        'en_curso'          => ['en_desmontaje', 'cancelado'],
        'en_desmontaje'     => ['finalizado'],
        'finalizado'        => ['cerrado'],
        'postergado'        => ['confirmado', 'cancelado'],
        'cancelado'         => [],
        'cerrado'           => [],
    ];

    private int $tenantId;

    public function __construct(private PDO $pdo) {
        $this->tenantId = tenantId();
    }

    public function listar(array $filtros = []): array {
        $where = ['e.tenant_id = ?'];
        $params = [$this->tenantId];

        if (!empty($filtros['estado'])) {
            $where[] = 'e.estado = ?';
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where[] = 'DATE(e.fecha_inicio_evento) >= ?';
            $params[] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[] = 'DATE(e.fecha_inicio_evento) <= ?';
            $params[] = $filtros['fecha_hasta'];
        }

        $sql = "SELECT e.*, c.nombre as cliente_nombre,
                    COALESCE(SUM(p.monto), 0) as cobrado
                FROM carp_eventos e
                LEFT JOIN carp_clientes c ON c.id = e.cliente_id AND c.tenant_id = e.tenant_id
                LEFT JOIN carp_pagos p ON p.evento_id = e.id AND p.tenant_id = e.tenant_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY e.id
                ORDER BY e.fecha_inicio_evento DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT e.*, c.nombre as cliente_nombre
             FROM carp_eventos e
             LEFT JOIN carp_clientes c ON c.id = e.cliente_id AND c.tenant_id = e.tenant_id
             WHERE e.id = ? AND e.tenant_id = ? LIMIT 1"
        );
        $stmt->execute([$id, $this->tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos): array {
        $errors = $this->validar($datos);
        if ($errors) return ['success' => false, 'message' => implode(' ', $errors)];

        $stmt = $this->pdo->prepare(
            "INSERT INTO carp_eventos
             (tenant_id, cliente_id, nombre_evento, tipo_evento, fecha_montaje,
              fecha_inicio_evento, fecha_fin_evento, fecha_desmontaje,
              direccion, localidad, distancia_km, capacidad_personas,
              precio_total, estado, observaciones, creado_por, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'consulta',?,?,NOW(),NOW())"
        );
        $stmt->execute([
            $this->tenantId,
            $datos['cliente_id'] ?: null,
            $datos['nombre_evento'],
            $datos['tipo_evento'] ?? 'otro',
            $datos['fecha_montaje'],
            $datos['fecha_inicio_evento'],
            $datos['fecha_fin_evento'],
            $datos['fecha_desmontaje'],
            $datos['direccion'] ?? null,
            $datos['localidad'] ?? null,
            $datos['distancia_km'] ?: null,
            $datos['capacidad_personas'] ?: null,
            $datos['precio_total'] ?? 0,
            $datos['observaciones'] ?? null,
            $_SESSION['usuario_id'] ?? null,
        ]);
        return ['success' => true, 'id' => (int)$this->pdo->lastInsertId()];
    }

    public function actualizar(int $id, array $datos): array {
        $evento = $this->findById($id);
        if (!$evento) return ['success' => false, 'message' => 'Evento no encontrado.'];

        $errors = $this->validar($datos);
        if ($errors) return ['success' => false, 'message' => implode(' ', $errors)];

        $stmt = $this->pdo->prepare(
            "UPDATE carp_eventos
             SET cliente_id=?, nombre_evento=?, tipo_evento=?, fecha_montaje=?,
                 fecha_inicio_evento=?, fecha_fin_evento=?, fecha_desmontaje=?,
                 direccion=?, localidad=?, distancia_km=?, capacidad_personas=?,
                 precio_total=?, observaciones=?, updated_at=NOW()
             WHERE id=? AND tenant_id=?"
        );
        $stmt->execute([
            $datos['cliente_id'] ?: null,
            $datos['nombre_evento'],
            $datos['tipo_evento'] ?? 'otro',
            $datos['fecha_montaje'],
            $datos['fecha_inicio_evento'],
            $datos['fecha_fin_evento'],
            $datos['fecha_desmontaje'],
            $datos['direccion'] ?? null,
            $datos['localidad'] ?? null,
            $datos['distancia_km'] ?: null,
            $datos['capacidad_personas'] ?: null,
            $datos['precio_total'] ?? 0,
            $datos['observaciones'] ?? null,
            $id, $this->tenantId,
        ]);
        return ['success' => true];
    }

    /**
     * Cambiar estado con validación de transiciones permitidas
     */
    public function cambiarEstado(int $id, string $nuevoEstado, ?string $motivo = null): array {
        $evento = $this->findById($id);
        if (!$evento) return ['success' => false, 'message' => 'Evento no encontrado.'];

        $estadoActual    = $evento['estado'];
        $transicionesOk  = self::TRANSICIONES_VALIDAS[$estadoActual] ?? [];

        if (!in_array($nuevoEstado, $transicionesOk, true)) {
            return ['success' => false, 'message' => "No se puede pasar de '$estadoActual' a '$nuevoEstado'."];
        }

        $campos = ['estado = ?', 'updated_at = NOW()'];
        $params = [$nuevoEstado];

        if ($nuevoEstado === 'cancelado' && $motivo) {
            $campos[] = 'motivo_cancelacion = ?';
            $params[] = $motivo;
        }

        $params[] = $id;
        $params[] = $this->tenantId;

        $stmt = $this->pdo->prepare(
            "UPDATE carp_eventos SET " . implode(', ', $campos) . " WHERE id=? AND tenant_id=?"
        );
        $stmt->execute($params);

        auditLog($this->pdo, 'estado_cambio', 'carp_eventos', $id,
                 ['estado' => $estadoActual], ['estado' => $nuevoEstado]);

        return ['success' => true];
    }

    // ── Equipos y disponibilidad ──────────────────────────────────────────────

    /**
     * Verificar disponibilidad de equipos para un evento en un rango de fechas.
     * Incluye días de montaje y desmontaje en la comparación.
     *
     * @return array Lista de equipos con conflicto (vacío = todos disponibles)
     */
    public function verificarDisponibilidad(
        array $equiposConCantidad,   // [equipo_id => cantidad_requerida]
        string $fechaMontaje,
        string $fechaDesmontaje,
        ?int $excluirEventoId = null
    ): array {
        $conflictos = [];

        foreach ($equiposConCantidad as $equipoId => $cantidadRequerida) {
            $equipoId = (int)$equipoId;

            // Total disponible del equipo
            $stmtEq = $this->pdo->prepare(
                "SELECT nombre, cantidad_total FROM carp_inventario_equipos
                 WHERE id=? AND tenant_id=? AND estado='activo'"
            );
            $stmtEq->execute([$equipoId, $this->tenantId]);
            $equipo = $stmtEq->fetch();
            if (!$equipo) continue;

            // Cantidad ya comprometida en eventos activos con rango solapado
            $excluirSql = $excluirEventoId ? 'AND ee.evento_id != ?' : '';
            $params = [
                $this->tenantId, $equipoId,
                $fechaDesmontaje, $fechaMontaje,   // solapamiento: A.inicio < B.fin AND A.fin > B.inicio
            ];
            if ($excluirEventoId) $params[] = $excluirEventoId;

            $stmtComprometido = $this->pdo->prepare(
                "SELECT COALESCE(SUM(ee.cantidad), 0)
                 FROM carp_evento_equipos ee
                 INNER JOIN carp_eventos ev ON ev.id = ee.evento_id
                 WHERE ee.tenant_id = ? AND ee.equipo_id = ?
                   AND ev.estado NOT IN ('cancelado','cerrado')
                   AND ev.fecha_montaje < ?
                   AND ev.fecha_desmontaje > ?
                   $excluirSql"
            );
            $stmtComprometido->execute($params);
            $comprometida = (int)$stmtComprometido->fetchColumn();

            $disponible = $equipo['cantidad_total'] - $comprometida;
            if ($disponible < $cantidadRequerida) {
                $conflictos[] = [
                    'equipo_id'    => $equipoId,
                    'nombre'       => $equipo['nombre'],
                    'requerida'    => $cantidadRequerida,
                    'disponible'   => max(0, $disponible),
                ];
            }
        }

        return $conflictos;
    }

    public function equiposDeEvento(int $eventoId): array {
        $stmt = $this->pdo->prepare(
            "SELECT ee.*, eq.nombre, eq.tipo
             FROM carp_evento_equipos ee
             INNER JOIN carp_inventario_equipos eq ON eq.id = ee.equipo_id
             WHERE ee.evento_id=? AND ee.tenant_id=?"
        );
        $stmt->execute([$eventoId, $this->tenantId]);
        return $stmt->fetchAll();
    }

    public function guardarEquipos(int $eventoId, array $equipos, string $fechaMontaje, string $fechaDesmontaje): array {
        $conflictos = $this->verificarDisponibilidad($equipos, $fechaMontaje, $fechaDesmontaje, $eventoId);
        if ($conflictos) {
            $nombres = array_column($conflictos, 'nombre');
            return ['success' => false, 'conflictos' => $conflictos,
                    'message' => 'Sin stock suficiente para: ' . implode(', ', $nombres)];
        }

        // Borrar asignaciones anteriores y reinsertar
        $this->pdo->prepare(
            "DELETE FROM carp_evento_equipos WHERE evento_id=? AND tenant_id=?"
        )->execute([$eventoId, $this->tenantId]);

        $stmt = $this->pdo->prepare(
            "INSERT INTO carp_evento_equipos (tenant_id, evento_id, equipo_id, cantidad) VALUES (?,?,?,?)"
        );
        foreach ($equipos as $equipoId => $cantidad) {
            if ((int)$cantidad > 0) {
                $stmt->execute([$this->tenantId, $eventoId, (int)$equipoId, (int)$cantidad]);
            }
        }
        return ['success' => true];
    }

    /**
     * Datos para FullCalendar.js (endpoint JSON)
     */
    public function paraCalendario(): array {
        $colores = [
            'consulta'          => '#94a3b8',
            'presupuestado'     => '#64748b',
            'reservado_con_sena'=> '#0ea5e9',
            'confirmado'        => '#6366f1',
            'en_montaje'        => '#f59e0b',
            'en_curso'          => '#10b981',
            'en_desmontaje'     => '#f97316',
            'finalizado'        => '#8b5cf6',
            'cerrado'           => '#22c55e',
            'cancelado'         => '#ef4444',
            'postergado'        => '#f59e0b',
        ];

        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.nombre_evento as title, e.estado,
                    DATE(e.fecha_montaje) as start,
                    DATE(DATE_ADD(e.fecha_desmontaje, INTERVAL 1 DAY)) as end,
                    c.nombre as cliente
             FROM carp_eventos e
             LEFT JOIN carp_clientes c ON c.id = e.cliente_id AND c.tenant_id = e.tenant_id
             WHERE e.tenant_id = ? AND e.estado NOT IN ('cancelado')
             ORDER BY e.fecha_montaje"
        );
        $stmt->execute([$this->tenantId]);
        $eventos = $stmt->fetchAll();

        return array_map(fn($ev) => [
            'id'              => $ev['id'],
            'title'           => $ev['title'],
            'start'           => $ev['start'],
            'end'             => $ev['end'],
            'backgroundColor' => $colores[$ev['estado']] ?? '#6366f1',
            'borderColor'     => $colores[$ev['estado']] ?? '#6366f1',
            'extendedProps'   => ['estado' => $ev['estado'], 'cliente' => $ev['cliente']],
        ], $eventos);
    }

    private function validar(array $d): array {
        $errors = [];
        if (empty($d['nombre_evento'])) $errors[] = 'El nombre del evento es requerido.';
        if (empty($d['fecha_montaje'])) $errors[] = 'La fecha de montaje es requerida.';
        if (empty($d['fecha_inicio_evento'])) $errors[] = 'La fecha de inicio es requerida.';
        if (empty($d['fecha_fin_evento']))    $errors[] = 'La fecha de fin es requerida.';
        if (empty($d['fecha_desmontaje']))    $errors[] = 'La fecha de desmontaje es requerida.';
        if (!empty($d['fecha_desmontaje']) && !empty($d['fecha_montaje'])) {
            if ($d['fecha_desmontaje'] < $d['fecha_montaje']) {
                $errors[] = 'La fecha de desmontaje debe ser posterior al montaje.';
            }
        }
        return $errors;
    }
}
