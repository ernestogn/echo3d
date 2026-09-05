<?php
/**
 * TenantModel — CRUD de tenants (uso exclusivo superadmin)
 */
class TenantModel {

    public function __construct(private PDO $pdo) {}

    public function listarTodos(): array {
        return $this->pdo->query(
            "SELECT t.*, 
                    (SELECT COUNT(*) FROM usuarios WHERE tenant_id = t.id) as total_usuarios,
                    (SELECT COUNT(*) FROM carp_eventos WHERE tenant_id = t.id) as total_eventos
             FROM tenants t ORDER BY t.nombre"
        )->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM tenants WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findBySlug(string $slug): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM tenants WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos): array {
        $slug = sanitizarSlug($datos['slug'] ?? $datos['nombre'] ?? '');
        if (empty($slug)) {
            return ['success' => false, 'message' => 'El slug no puede estar vacío.'];
        }
        if ($this->findBySlug($slug)) {
            return ['success' => false, 'message' => 'Ya existe una empresa con ese slug.'];
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO tenants (nombre, slug, email_contacto, moneda, zona_horaria, estado, created_at)
             VALUES (?, ?, ?, ?, ?, 'activo', NOW())"
        );
        $stmt->execute([
            $datos['nombre'],
            $slug,
            $datos['email_contacto'] ?? null,
            $datos['moneda'] ?? 'ARS',
            $datos['zona_horaria'] ?? 'America/Argentina/Buenos_Aires',
        ]);
        return ['success' => true, 'id' => (int)$this->pdo->lastInsertId(), 'slug' => $slug];
    }

    public function actualizar(int $id, array $datos): array {
        $tenant = $this->findById($id);
        if (!$tenant) return ['success' => false, 'message' => 'Empresa no encontrada.'];

        // Si cambió el slug, verificar unicidad
        $nuevoSlug = sanitizarSlug($datos['slug'] ?? $tenant['slug']);
        if ($nuevoSlug !== $tenant['slug']) {
            if ($this->findBySlug($nuevoSlug)) {
                return ['success' => false, 'message' => 'El slug ya está en uso.'];
            }
        }

        $stmt = $this->pdo->prepare(
            "UPDATE tenants SET nombre=?, slug=?, email_contacto=?, moneda=?,
             zona_horaria=?, costo_km=?, alerta_saldo_dias=?
             WHERE id=?"
        );
        $stmt->execute([
            $datos['nombre'],
            $nuevoSlug,
            $datos['email_contacto'] ?? null,
            $datos['moneda'] ?? 'ARS',
            $datos['zona_horaria'] ?? 'America/Argentina/Buenos_Aires',
            $datos['costo_km'] ?? 0,
            $datos['alerta_saldo_dias'] ?? 3,
            $id,
        ]);
        return ['success' => true, 'slug' => $nuevoSlug];
    }

    public function cambiarEstado(int $id, string $estado): bool {
        $estados = ['activo', 'suspendido', 'prueba'];
        if (!in_array($estado, $estados, true)) return false;

        $stmt = $this->pdo->prepare("UPDATE tenants SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $id]);
        return $stmt->rowCount() > 0;
    }

    public function guardarLogo(int $id, string $path): bool {
        $stmt = $this->pdo->prepare("UPDATE tenants SET logo_path = ? WHERE id = ?");
        $stmt->execute([$path, $id]);
        return $stmt->rowCount() > 0;
    }
}
