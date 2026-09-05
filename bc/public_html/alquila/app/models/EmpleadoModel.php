<?php
/**
 * EmpleadoModel — gestión de personal y operarios
 */
class EmpleadoModel {

    public function __construct(private PDO $pdo) {}

    public function listar(array $filtros = []): array {
        $tid = tenantId();
        $where = ["tenant_id = ?"];
        $params = [$tid];

        if (isset($filtros['activo'])) {
            $where[] = "activo = ?";
            $params[] = (int)$filtros['activo'];
        }

        $sql = "SELECT * FROM carp_empleados 
                WHERE " . implode(' AND ', $where) . "
                ORDER BY nombre ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $tid = tenantId();
        $stmt = $this->pdo->prepare("SELECT * FROM carp_empleados WHERE id = ? AND tenant_id = ? LIMIT 1");
        $stmt->execute([$id, $tid]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos): int {
        $tid = tenantId();
        $stmt = $this->pdo->prepare(
            "INSERT INTO carp_empleados 
             (tenant_id, nombre, rol_habitual, telefono, email, tarifa_jornal, tarifa_evento, usuario_id, activo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $tid,
            $datos['nombre'],
            $datos['rol_habitual'] ?? null,
            $datos['telefono'] ?? null,
            $datos['email'] ?? null,
            $datos['tarifa_jornal'] ?: null,
            $datos['tarifa_evento'] ?: null,
            $datos['usuario_id'] ?: null,
            isset($datos['activo']) ? 1 : 0
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function actualizar(int $id, array $datos): bool {
        $tid = tenantId();
        $stmt = $this->pdo->prepare(
            "UPDATE carp_empleados 
             SET nombre = ?, rol_habitual = ?, telefono = ?, email = ?, 
                 tarifa_jornal = ?, tarifa_evento = ?, usuario_id = ?, activo = ?
             WHERE id = ? AND tenant_id = ?"
        );
        return $stmt->execute([
            $datos['nombre'],
            $datos['rol_habitual'] ?? null,
            $datos['telefono'] ?? null,
            $datos['email'] ?? null,
            $datos['tarifa_jornal'] ?: null,
            $datos['tarifa_evento'] ?: null,
            $datos['usuario_id'] ?: null,
            isset($datos['activo']) ? 1 : 0,
            $id,
            $tid
        ]);
    }
}
