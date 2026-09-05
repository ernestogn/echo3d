<?php
/**
 * ClienteModel
 */
class ClienteModel {

    private int $tenantId;

    public function __construct(private PDO $pdo) {
        $this->tenantId = tenantId();
    }

    public function listar(string $busqueda = ''): array {
        $where  = ['tenant_id = ?'];
        $params = [$this->tenantId];
        if ($busqueda) {
            $where[]  = '(nombre LIKE ? OR email LIKE ? OR telefono LIKE ? OR documento LIKE ?)';
            $like     = '%' . $busqueda . '%';
            $params   = array_merge($params, [$like, $like, $like, $like]);
        }
        $stmt = $this->pdo->prepare(
            "SELECT *, (SELECT COUNT(*) FROM carp_eventos WHERE cliente_id = carp_clientes.id AND tenant_id = ?) as total_eventos
             FROM carp_clientes WHERE " . implode(' AND ', $where) . " ORDER BY nombre"
        );
        $stmt->execute(array_merge([$this->tenantId], $params));
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM carp_clientes WHERE id=? AND tenant_id=? LIMIT 1");
        $stmt->execute([$id, $this->tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $d): array {
        if (empty(trim($d['nombre'] ?? ''))) return ['success'=>false,'message'=>'El nombre es requerido.'];

        $stmt = $this->pdo->prepare(
            "INSERT INTO carp_clientes (tenant_id,nombre,tipo,telefono,email,documento,calificacion,observaciones,created_at)
             VALUES (?,?,?,?,?,?,?,?,NOW())"
        );
        $stmt->execute([
            $this->tenantId, trim($d['nombre']),
            $d['tipo']          ?? 'particular',
            $d['telefono']      ?? null,
            $d['email']         ?? null,
            $d['documento']     ?? null,
            $d['calificacion']  ?? 'normal',
            $d['observaciones'] ?? null,
        ]);
        return ['success'=>true,'id'=>(int)$this->pdo->lastInsertId()];
    }

    public function actualizar(int $id, array $d): array {
        if (empty(trim($d['nombre'] ?? ''))) return ['success'=>false,'message'=>'El nombre es requerido.'];
        $stmt = $this->pdo->prepare(
            "UPDATE carp_clientes SET nombre=?,tipo=?,telefono=?,email=?,documento=?,calificacion=?,observaciones=?,updated_at=NOW()
             WHERE id=? AND tenant_id=?"
        );
        $stmt->execute([
            trim($d['nombre']), $d['tipo']??'particular', $d['telefono']??null,
            $d['email']??null, $d['documento']??null, $d['calificacion']??'normal',
            $d['observaciones']??null, $id, $this->tenantId,
        ]);
        return ['success'=>true];
    }

    public function eliminar(int $id): array {
        // Verificar que no tenga eventos activos
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM carp_eventos WHERE cliente_id=? AND tenant_id=? AND estado NOT IN ('cancelado','cerrado')"
        );
        $stmt->execute([$id, $this->tenantId]);
        if ($stmt->fetchColumn() > 0) {
            return ['success'=>false,'message'=>'No se puede eliminar: tiene eventos activos.'];
        }
        $this->pdo->prepare("DELETE FROM carp_clientes WHERE id=? AND tenant_id=?")->execute([$id, $this->tenantId]);
        return ['success'=>true];
    }
}
