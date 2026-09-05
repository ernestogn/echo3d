<?php
/**
 * TrasladoModel — gestión de logística y viajes
 */
class TrasladoModel {

    public function __construct(private PDO $pdo) {}

    public function listar(array $filtros = []): array {
        $tid = tenantId();
        $where = ["t.tenant_id = ?"];
        $params = [$tid];

        if (!empty($filtros['evento_id'])) {
            $where[] = "t.evento_id = ?";
            $params[] = (int)$filtros['evento_id'];
        }
        if (!empty($filtros['fecha'])) {
            $where[] = "DATE(t.fecha_hora) = ?";
            $params[] = $filtros['fecha'];
        }

        $sql = "SELECT t.*, e.nombre_evento, em.nombre as conductor_nombre
                FROM carp_traslados t
                INNER JOIN carp_eventos e ON e.id = t.evento_id AND e.tenant_id = t.tenant_id
                LEFT JOIN carp_empleados em ON em.id = t.conductor_id AND em.tenant_id = t.tenant_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY t.fecha_hora ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $tid = tenantId();
        $stmt = $this->pdo->prepare(
            "SELECT t.*, e.nombre_evento, em.nombre as conductor_nombre
             FROM carp_traslados t
             INNER JOIN carp_eventos e ON e.id = t.evento_id AND e.tenant_id = t.tenant_id
             LEFT JOIN carp_empleados em ON em.id = t.conductor_id AND em.tenant_id = t.tenant_id
             WHERE t.id = ? AND t.tenant_id = ? LIMIT 1"
        );
        $stmt->execute([$id, $tid]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos): int {
        $tid = tenantId();
        $stmt = $this->pdo->prepare(
            "INSERT INTO carp_traslados 
             (tenant_id, evento_id, tipo, fecha_hora, origen, destino, vehiculo, conductor_id, km_distancia, observaciones)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $tid,
            $datos['evento_id'],
            $datos['tipo'],
            $datos['fecha_hora'],
            $datos['origen'] ?? null,
            $datos['destino'] ?? null,
            $datos['vehiculo'] ?? null,
            $datos['conductor_id'] ?: null,
            $datos['km_distancia'] ?: null,
            $datos['observaciones'] ?? null
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function actualizar(int $id, array $datos): bool {
        $tid = tenantId();
        $stmt = $this->pdo->prepare(
            "UPDATE carp_traslados 
             SET tipo = ?, fecha_hora = ?, origen = ?, destino = ?, 
                 vehiculo = ?, conductor_id = ?, km_distancia = ?, observaciones = ?
             WHERE id = ? AND tenant_id = ?"
        );
        return $stmt->execute([
            $datos['tipo'],
            $datos['fecha_hora'],
            $datos['origen'] ?? null,
            $datos['destino'] ?? null,
            $datos['vehiculo'] ?? null,
            $datos['conductor_id'] ?: null,
            $datos['km_distancia'] ?: null,
            $datos['observaciones'] ?? null,
            $id,
            $tid
        ]);
    }

    public function eliminar(int $id): bool {
        $tid = tenantId();
        $stmt = $this->pdo->prepare("DELETE FROM carp_traslados WHERE id = ? AND tenant_id = ?");
        return $stmt->execute([$id, $tid]);
    }
}
