<?php
/**
 * UsuarioModel — acceso a tabla usuarios
 * Siempre filtra por tenant_id
 */
class UsuarioModel {

    public function __construct(private PDO $pdo) {}

    public function findByEmailAndTenant(string $email, ?int $tenantId): ?array {
        if ($tenantId) {
            // Buscar usuario del tenant (admin, operador, tecnico, cliente)
            $stmt = $this->pdo->prepare(
                "SELECT * FROM usuarios WHERE email = ? AND tenant_id = ? AND activo = 1 LIMIT 1"
            );
            $stmt->execute([$email, $tenantId]);
        } else {
            // Buscar superadmin (tenant_id IS NULL)
            $stmt = $this->pdo->prepare(
                "SELECT * FROM usuarios WHERE email = ? AND (tenant_id IS NULL OR rol = 'superadmin') AND activo = 1 LIMIT 1"
            );
            $stmt->execute([$email]);
        }
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id, ?int $tenantId = null): ?array {
        if ($tenantId) {
            $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND tenant_id = ? LIMIT 1");
            $stmt->execute([$id, $tenantId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
        }
        return $stmt->fetch() ?: null;
    }

    public function listarPorTenant(int $tenantId): array {
        $stmt = $this->pdo->prepare(
            "SELECT id, nombre, email, rol, activo, ultimo_login, created_at
             FROM usuarios WHERE tenant_id = ? ORDER BY nombre"
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    public function crear(int $tenantId, string $nombre, string $email, string $password, string $rol): array {
        // Verificar email único en el tenant
        $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND tenant_id = ?");
        $stmt->execute([$email, $tenantId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'El email ya está registrado en este tenant.'];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->pdo->prepare(
            "INSERT INTO usuarios (tenant_id, nombre, email, password, rol, activo, created_at)
             VALUES (?, ?, ?, ?, ?, 1, NOW())"
        );
        $stmt->execute([$tenantId, $nombre, $email, $hash, $rol]);
        return ['success' => true, 'id' => (int)$this->pdo->lastInsertId()];
    }

    public function actualizar(int $id, int $tenantId, string $nombre, string $email, string $rol, int $activo): array {
        // Verificar email único (excluir al propio usuario)
        $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND tenant_id = ? AND id != ?");
        $stmt->execute([$email, $tenantId, $id]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'El email ya pertenece a otro usuario.'];
        }

        $stmt = $this->pdo->prepare(
            "UPDATE usuarios SET nombre=?, email=?, rol=?, activo=? WHERE id=? AND tenant_id=?"
        );
        $stmt->execute([$nombre, $email, $rol, $activo, $id, $tenantId]);
        return ['success' => true];
    }

    public function actualizarPassword(int $id, int $tenantId, string $nuevaPassword): bool {
        $hash = password_hash($nuevaPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->pdo->prepare("UPDATE usuarios SET password=? WHERE id=? AND tenant_id=?");
        $stmt->execute([$hash, $id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    public function eliminar(int $id, int $tenantId, int $usuarioActualId): array {
        if ($id === $usuarioActualId) {
            return ['success' => false, 'message' => 'No podés eliminar tu propia cuenta.'];
        }
        $stmt = $this->pdo->prepare("DELETE FROM usuarios WHERE id=? AND tenant_id=?");
        $stmt->execute([$id, $tenantId]);
        return ['success' => $stmt->rowCount() > 0];
    }

    // ─── Rate limiting ────────────────────────────────────────────────────────

    public function registrarIntentoFallido(string $email, string $ip): void {
        // Guardar en sesión para control simple de intentos por IP+email
        $clave = 'login_attempts_' . md5($email . $ip);
        $_SESSION[$clave] = ($_SESSION[$clave] ?? 0) + 1;
        $_SESSION[$clave . '_time'] = time();
    }

    public function verificarBloqueo(string $email, string $ip): array {
        $clave    = 'login_attempts_' . md5($email . $ip);
        $intentos = $_SESSION[$clave] ?? 0;
        $tiempo   = $_SESSION[$clave . '_time'] ?? 0;

        if ($intentos >= MAX_LOGIN_ATTEMPTS) {
            $segundosTranscurridos = time() - $tiempo;
            $segundosBloqueo       = LOGIN_BLOCK_MINUTES * 60;
            if ($segundosTranscurridos < $segundosBloqueo) {
                $minutosRestantes = (int)ceil(($segundosBloqueo - $segundosTranscurridos) / 60);
                return ['bloqueado' => true, 'minutos' => $minutosRestantes];
            } else {
                // Expiró el bloqueo, limpiar
                $this->limpiarIntentos($email);
            }
        }

        return ['bloqueado' => false];
    }

    public function limpiarIntentos(string $email): void {
        $ip   = clienteIp();
        $clave = 'login_attempts_' . md5($email . $ip);
        unset($_SESSION[$clave], $_SESSION[$clave . '_time']);
    }

    public function actualizarUltimoLogin(int $id): void {
        $stmt = $this->pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
        $stmt->execute([$id]);
    }
}
