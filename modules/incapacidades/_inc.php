<?php
/** Helpers del seguimiento de incapacidades. */

/** Recalcula reportes.dias_perdidos como la suma de los tramos. Devuelve el total. */
function recompute_dias_incapacidad(PDO $pdo, int $reporte_id): int {
    $st = $pdo->prepare("SELECT COALESCE(SUM(dias),0) FROM incapacidad_tramos WHERE reporte_id = ?");
    $st->execute([$reporte_id]);
    $sum = (int) $st->fetchColumn();
    $pdo->prepare("UPDATE reportes SET dias_perdidos = ? WHERE id = ?")->execute([$sum, $reporte_id]);
    return $sum;
}

/** Estado del seguimiento de un reporte (array con fecha_regreso, n_tramos). */
function estado_incapacidad(array $reporte, int $n_tramos): string {
    if (!empty($reporte['fecha_regreso'])) return 'cerrado';
    if ($n_tramos > 0 || (int)($reporte['dias_perdidos'] ?? 0) > 0) return 'abierto';
    return 'sin_seguimiento';
}

/** Carga un reporte de accidente y valida acceso. Redirige si no procede. */
function cargar_reporte_incapacidad(PDO $pdo, int $reporte_id, string $usuario_rol, $usuario_sucursal_id): array {
    $st = $pdo->prepare("SELECT r.*, e.numero_empleado, e.nombre AS empleado_nombre,
                                d.nombre AS departamento, s.nombre AS sucursal
                         FROM reportes r
                         JOIN empleados e ON e.id = r.empleado_id
                         JOIN departamentos d ON d.id = r.departamento_id
                         JOIN sucursales s ON s.id = r.sucursal_id
                         WHERE r.id = ? AND r.tipo = 'accidente'");
    $st->execute([$reporte_id]);
    $rep = $st->fetch();
    if (!$rep) { redirect('modules/incapacidades/listar.php'); }
    if ($usuario_rol !== 'admin' && $rep['sucursal_id'] != $usuario_sucursal_id) {
        redirect('modules/incapacidades/listar.php');
    }
    return $rep;
}
