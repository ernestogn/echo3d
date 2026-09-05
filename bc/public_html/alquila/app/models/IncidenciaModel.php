<?php
/**
 * IncidenciaModel — gestión de problemas en eventos
 */
class IncidenciaModel {

    public function __construct(private PDO $pdo) {}

    public function listar(array $filtros = []): array {
        $tid = tenantId();
        $where = ["i.tenant_id = ?"];
        $params = [$tid];

        if (!empty($filtros['estado'])) {
            $where[] = "i.estado = ?";
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['evento_id'])) {
            $where[] = "i.evento_id = ?";
            $params[] = (int)$filtros['evento_id'];
        }

        $sql = "SELECT i.*, e.nombre_evento, u.nombre as reportado_por_nombre
                FROM carp_incidencias i
                INNER JOIN carp_eventos e ON e.id = i.evento_id AND e.tenant_id = i.tenant_id
                LEFT JOIN usuarios u ON u.id = i.reportado_por
                WHERE " . implode(' AND ', $where) . "
                ORDER BY i.fecha_hora DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $tid = tenantId();
        $stmt = $this->pdo->prepare(
            "SELECT i.*, e.nombre_evento, u.nombre as reportado_por_nombre
             FROM carp_incidencias i
             INNER JOIN carp_eventos e ON e.id = i.evento_id AND e.tenant_id = i.tenant_id
             LEFT JOIN usuarios u ON u.id = i.reportado_por
             WHERE i.id = ? AND i.tenant_id = ? LIMIT 1"
        );
        $stmt->execute([$id, $tid]);
        $inc = $stmt->fetch();
        if (!$inc) return null;

        // Cargar fotos
        $stmtFotos = $this->pdo->prepare("SELECT * FROM carp_incidencia_fotos WHERE incidencia_id = ? AND tenant_id = ?");
        $stmtFotos->execute([$id, $tid]);
        $inc['fotos'] = $stmtFotos->fetchAll();

        return $inc;
    }

    public function crear(array $datos): int {
        $tid = tenantId();
        $stmt = $this->pdo->prepare(
            "INSERT INTO carp_incidencias 
             (tenant_id, evento_id, tipo, descripcion, fecha_hora, reportado_por, estado, costo_generado)
             VALUES (?, ?, ?, ?, ?, ?, 'abierta', ?)"
        );
        $stmt->execute([
            $tid,
            $datos['evento_id'],
            $datos['tipo'],
            $datos['descripcion'],
            $datos['fecha_hora'] ?: date('Y-m-d H:i:s'),
            $_SESSION['usuario_id'] ?? null,
            $datos['costo_generado'] ?: 0
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function actualizar(int $id, array $datos): bool {
        $tid = tenantId();
        $stmt = $this->pdo->prepare(
            "UPDATE carp_incidencias 
             SET tipo = ?, descripcion = ?, estado = ?, acciones = ?, costo_generado = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?"
        );
        return $stmt->execute([
            $datos['tipo'],
            $datos['descripcion'],
            $datos['estado'],
            $datos['acciones'] ?? '',
            $datos['costo_generado'] ?: 0,
            $id,
            $tid
        ]);
    }

    public function guardarFotos(int $incidenciaId, array $fotos): void {
        $tid = tenantId();
        $stmt = $this->pdo->prepare("INSERT INTO carp_incidencia_fotos (tenant_id, incidencia_id, foto_path) VALUES (?, ?, ?)");
        foreach ($fotos as $path) {
            $stmt->execute([$tid, $incidenciaId, $path]);
        }
    }

    public function eliminarFoto(int $fotoId): bool {
        $tid = tenantId();
        // Obtener path para borrar archivo físico si fuera necesario (omitido por ahora)
        $stmt = $this->pdo->prepare("DELETE FROM carp_incidencia_fotos WHERE id = ? AND tenant_id = ?");
        return $stmt->execute([$fotoId, $tid]);
    }
}
