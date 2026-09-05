<?php
/**
 * PagoModel — carp_pagos
 * El saldo se calcula SIEMPRE dinámicamente. No se guarda como campo.
 */
class PagoModel {

    private int $tenantId;

    public function __construct(private PDO $pdo) {
        $this->tenantId = tenantId();
    }

    public function listarPorEvento(int $eventoId): array {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.nombre as registrado_nombre
             FROM carp_pagos p
             LEFT JOIN usuarios u ON u.id = p.registrado_por
             WHERE p.evento_id=? AND p.tenant_id=? ORDER BY p.fecha DESC"
        );
        $stmt->execute([$eventoId, $this->tenantId]);
        return $stmt->fetchAll();
    }

    public function registrar(int $eventoId, array $d): array {
        if (empty($d['fecha']) || empty($d['monto']) || (float)$d['monto'] <= 0) {
            return ['success'=>false,'message'=>'Fecha y monto son requeridos.'];
        }

        // Verificar que el evento pertenece al tenant
        $stmt = $this->pdo->prepare("SELECT id, estado, precio_total FROM carp_eventos WHERE id=? AND tenant_id=? LIMIT 1");
        $stmt->execute([$eventoId, $this->tenantId]);
        $evento = $stmt->fetch();
        if (!$evento) return ['success'=>false,'message'=>'Evento no encontrado.'];

        $esSena = !empty($d['es_sena']) ? 1 : 0;

        $stmt = $this->pdo->prepare(
            "INSERT INTO carp_pagos (tenant_id,evento_id,fecha,monto,forma_pago,referencia,es_sena,registrado_por,observacion,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,NOW())"
        );
        $stmt->execute([
            $this->tenantId, $eventoId,
            $d['fecha'], (float)$d['monto'],
            $d['forma_pago']  ?? 'efectivo',
            $d['referencia']  ?? null,
            $esSena,
            $_SESSION['usuario_id'] ?? null,
            $d['observacion'] ?? null,
        ]);
        $pagoId = (int)$this->pdo->lastInsertId();

        // Si es seña y el evento está en estado consulta/presupuestado, cambiar a reservado_con_sena
        if ($esSena && in_array($evento['estado'], ['consulta','presupuestado'], true)) {
            $this->pdo->prepare(
                "UPDATE carp_eventos SET estado='reservado_con_sena', updated_at=NOW() WHERE id=? AND tenant_id=?"
            )->execute([$eventoId, $this->tenantId]);
            auditLog($this->pdo,'estado_cambio','carp_eventos',$eventoId,
                     ['estado'=>$evento['estado']],['estado'=>'reservado_con_sena']);
        }

        auditLog($this->pdo,'create','carp_pagos',$pagoId,null,[
            'evento_id'=>$eventoId,'monto'=>$d['monto'],'es_sena'=>$esSena
        ]);

        return ['success'=>true,'id'=>$pagoId];
    }

    public function eliminar(int $id): array {
        // Solo admin puede eliminar pagos — verificar seguridad
        $stmt = $this->pdo->prepare("SELECT * FROM carp_pagos WHERE id=? AND tenant_id=? LIMIT 1");
        $stmt->execute([$id, $this->tenantId]);
        $pago = $stmt->fetch();
        if (!$pago) return ['success'=>false,'message'=>'Pago no encontrado.'];

        $this->pdo->prepare("DELETE FROM carp_pagos WHERE id=? AND tenant_id=?")->execute([$id, $this->tenantId]);
        auditLog($this->pdo,'delete','carp_pagos',$id,$pago,null);
        return ['success'=>true];
    }

    /**
     * Resumen financiero de un evento
     */
    public function resumenEvento(int $eventoId): array {
        $stmtEvento = $this->pdo->prepare(
            "SELECT precio_total FROM carp_eventos WHERE id=? AND tenant_id=? LIMIT 1"
        );
        $stmtEvento->execute([$eventoId, $this->tenantId]);
        $evento = $stmtEvento->fetch();
        if (!$evento) return [];

        $stmtPagos = $this->pdo->prepare(
            "SELECT COALESCE(SUM(monto),0) as total,
                    COALESCE(SUM(CASE WHEN es_sena=1 THEN monto ELSE 0 END),0) as senas
             FROM carp_pagos WHERE evento_id=? AND tenant_id=?"
        );
        $stmtPagos->execute([$eventoId, $this->tenantId]);
        $pagos = $stmtPagos->fetch();

        $total   = (float)$evento['precio_total'];
        $cobrado = (float)$pagos['total'];
        return [
            'precio_total' => $total,
            'cobrado'      => $cobrado,
            'senas'        => (float)$pagos['senas'],
            'saldo'        => $total - $cobrado,
            'porcentaje'   => $total > 0 ? round(($cobrado / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Eventos con saldo pendiente y fecha próxima (para alertas)
     */
    public function eventosPendientesPago(int $diasAntes = 3): array {
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.nombre_evento, e.fecha_inicio_evento, e.estado, e.precio_total,
                    c.nombre as cliente_nombre,
                    COALESCE(SUM(p.monto),0) as cobrado,
                    (e.precio_total - COALESCE(SUM(p.monto),0)) as saldo
             FROM carp_eventos e
             LEFT JOIN carp_clientes c ON c.id=e.cliente_id AND c.tenant_id=e.tenant_id
             LEFT JOIN carp_pagos p ON p.evento_id=e.id AND p.tenant_id=e.tenant_id
             WHERE e.tenant_id=?
               AND e.estado NOT IN ('cancelado','cerrado')
               AND e.fecha_inicio_evento BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
             GROUP BY e.id
             HAVING saldo > 0
             ORDER BY e.fecha_inicio_evento"
        );
        $stmt->execute([$this->tenantId, $diasAntes]);
        return $stmt->fetchAll();
    }
}
