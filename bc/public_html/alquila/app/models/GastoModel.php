<?php
/**
 * GastoModel — gestión de egresos y costos
 */
class GastoModel {

    public function __construct(private PDO $pdo) {}

    public function listar(array $filtros = []): array {
        $tid = tenantId();
        $where = ["g.tenant_id = ?"];
        $params = [$tid];

        if (!empty($filtros['evento_id'])) {
            $where[] = "g.evento_id = ?";
            $params[] = (int)$filtros['evento_id'];
        }
        if (!empty($filtros['categoria'])) {
            $where[] = "g.categoria = ?";
            $params[] = $filtros['categoria'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where[] = "g.fecha >= ?";
            $params[] = $filtros['fecha_desde'];
        }

        $sql = "SELECT g.*, e.nombre_evento
                FROM carp_gastos g
                LEFT JOIN carp_eventos e ON e.id = g.evento_id AND e.tenant_id = g.tenant_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY g.fecha DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $tid = tenantId();
        $stmt = $this->pdo->prepare("SELECT * FROM carp_gastos WHERE id = ? AND tenant_id = ? LIMIT 1");
        $stmt->execute([$id, $tid]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos): int {
        $tid = tenantId();
        $stmt = $this->pdo->prepare(
            "INSERT INTO carp_gastos 
             (tenant_id, evento_id, categoria, descripcion, monto, fecha, comprobante, registrado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $tid,
            $datos['evento_id'] ?: null,
            $datos['categoria'],
            $datos['descripcion'],
            $datos['monto'],
            $datos['fecha'],
            $datos['comprobante'] ?? null,
            $_SESSION['usuario_id'] ?? null
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function actualizar(int $id, array $datos): bool {
        $tid = tenantId();
        $stmt = $this->pdo->prepare(
            "UPDATE carp_gastos 
             SET evento_id = ?, categoria = ?, descripcion = ?, monto = ?, 
                 fecha = ?, comprobante = ?
             WHERE id = ? AND tenant_id = ?"
        );
        return $stmt->execute([
            $datos['evento_id'] ?: null,
            $datos['categoria'],
            $datos['descripcion'],
            $datos['monto'],
            $datos['fecha'],
            $datos['comprobante'] ?? null,
            $id,
            $tid
        ]);
    }

    public function eliminar(int $id): bool {
        $tid = tenantId();
        $stmt = $this->pdo->prepare("DELETE FROM carp_gastos WHERE id = ? AND tenant_id = ?");
        return $stmt->execute([$id, $tid]);
    }
}
