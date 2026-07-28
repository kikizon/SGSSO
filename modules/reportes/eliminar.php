<?php
/**
 * Eliminar reporte (acto inseguro / accidente).
 * - admin: elimina directo (evidencias + firmados + reporte).
 * - usuario/supervisor: genera una SOLICITUD de eliminación (la aprueba un admin).
 * Reemplaza al eliminar.php anterior.
 */
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/authorization.php';
require_once '../../includes/papelera.php';
exigir('reportes.eliminar');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) { redirect('modules/reportes/listar.php'); }

$stmt = $pdo->prepare("SELECT * FROM reportes WHERE id = ?");
$stmt->execute([$id]);
$reporte = $stmt->fetch();
if (!$reporte) { redirect('modules/reportes/listar.php'); }

// Alcance por sucursal (no-admin solo su sucursal)
if ($usuario_rol !== 'admin' && !in_array((int)$reporte['sucursal_id'], $usuario_sucursales, true)) {
    redirect('modules/reportes/listar.php');
}

// Si ya hay una solicitud pendiente, no permitir otra acción
if (autz_hay_pendiente($pdo, 'reportes', $id)) {
    redirect('modules/reportes/listar.php?err=' . urlencode('Este reporte ya tiene una solicitud pendiente de autorización.'));
}

// usuario / supervisor: solicitar (no borra)
if (autz_requiere_autorizacion('reportes.eliminar.directo')) {
    autz_crear_solicitud(
        $pdo, $usuario_id, 'reportes', $id, 'DELETE', null,
        'Eliminación de reporte #' . $id, (int) $reporte['sucursal_id']
    );
    redirect('modules/reportes/listar.php?msg=' . urlencode('Solicitud de eliminación enviada para autorización.'));
}

// admin: eliminación directa -> a la papelera (conserva archivos hasta purgar)
papelera_snapshot($pdo, 'reportes', $id, $usuario_id);

// Auditoría + borrado de filas (los archivos quedan para la papelera)
registrar_auditoria($pdo, $usuario_id, 'DELETE', 'reportes', $id, json_encode([
    'tipo' => $reporte['tipo'], 'empleado_id' => $reporte['empleado_id'], 'fecha' => $reporte['fecha'],
], JSON_UNESCAPED_UNICODE));

$pdo->prepare("DELETE FROM reportes_evidencias WHERE reporte_id = ?")->execute([$id]);
$pdo->prepare("DELETE FROM reportes WHERE id = ?")->execute([$id]);

redirect('modules/reportes/listar.php?msg=' . urlencode('Reporte enviado a la papelera.'));
