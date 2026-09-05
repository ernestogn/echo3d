<?php
/**
 * InventarioModel — carp_inventario_equipos
 */
class InventarioModel {

    private int $tenantId;

    public function __construct(private PDO $pdo) {
        $this->tenantId = tenantId();
    }

    public function listar(): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM carp_inventario_equipos WHERE tenant_id=? ORDER BY tipo, nombre"
        );
        $stmt->execute([$this->tenantId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM carp_inventario_equipos WHERE id=? AND tenant_id=? LIMIT 1"
        );
        $stmt->execute([$id, $this->tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $d): array {
        if (empty(trim($d['nombre'] ?? ''))) return ['success'=>false,'message'=>'El nombre es requerido.'];
        $stmt = $this->pdo->prepare(
            "INSERT INTO carp_inventario_equipos
             (tenant_id,nombre,tipo,cantidad_total,estado,costo_adquisicion,descripcion,foto_path,created_at)
             VALUES (?,?,?,?,?,?,?,?,NOW())"
        );
        $stmt->execute([
            $this->tenantId, trim($d['nombre']),
            $d['tipo']              ?? 'otro',
            max(1, (int)($d['cantidad_total'] ?? 1)),
            $d['estado']            ?? 'activo',
            $d['costo_adquisicion'] ?: null,
            $d['descripcion']       ?? null,
            $d['foto_path']         ?? null,
        ]);
        return ['success'=>true,'id'=>(int)$this->pdo->lastInsertId()];
    }

    public function actualizar(int $id, array $d): array {
        if (empty(trim($d['nombre'] ?? ''))) return ['success'=>false,'message'=>'El nombre es requerido.'];
        $stmt = $this->pdo->prepare(
            "UPDATE carp_inventario_equipos
             SET nombre=?,tipo=?,cantidad_total=?,estado=?,costo_adquisicion=?,descripcion=?,foto_path=?,updated_at=NOW()
             WHERE id=? AND tenant_id=?"
        );
        $stmt->execute([
            trim($d['nombre']), $d['tipo']??'otro', max(1,(int)($d['cantidad_total']??1)),
            $d['estado']??'activo', $d['costo_adquisicion']?:null,
            $d['descripcion']??null, $d['foto_path']??null,
            $id, $this->tenantId,
        ]);
        return ['success'=>true];
    }

    public function eliminar(int $id): array {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM carp_evento_equipos WHERE equipo_id=? AND tenant_id=?"
        );
        $stmt->execute([$id, $this->tenantId]);
        if ($stmt->fetchColumn() > 0) {
            return ['success'=>false,'message'=>'No se puede eliminar: está asignado a eventos.'];
        }
        $this->pdo->prepare("DELETE FROM carp_inventario_equipos WHERE id=? AND tenant_id=?")->execute([$id, $this->tenantId]);
        return ['success'=>true];
    }
}
