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

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) { redirect('modules/reportes/listar.php'); }

$stmt = $pdo->prepare("SELECT * FROM reportes WHERE id = ?");
$stmt->execute([$id]);
$reporte = $stmt->fetch();
if (!$reporte) { redirect('modules/reportes/listar.php'); }

// Alcance por sucursal (no-admin solo su sucursal)
if ($usuario_rol !== 'admin' && isset($usuario_sucursal_id) && $reporte['sucursal_id'] != $usuario_sucursal_id) {
    redirect('modules/reportes/listar.php');
}

// Si ya hay una solicitud pendiente, no permitir otra acción
if (autz_hay_pendiente($pdo, 'reportes', $id)) {
    redirect('modules/reportes/listar.php?err=' . urlencode('Este reporte ya tiene una solicitud pendiente de autorización.'));
}

// usuario / supervisor: solicitar (no borra)
if (autz_requiere_autorizacion($usuario_rol)) {
    autz_crear_solicitud(
        $pdo, $usuario_id, 'reportes', $id, 'DELETE', null,
        'Eliminación de reporte #' . $id, (int) $reporte['sucursal_id']
    );
    redirect('modules/reportes/listar.php?msg=' . urlencode('Solicitud de eliminación enviada para autorización.'));
}

// admin: eliminación directa
// 1) Evidencias (archivos + filas)
$ev = $pdo->prepare("SELECT nombre_archivo FROM reportes_evidencias WHERE reporte_id = ?");
$ev->execute([$id]);
foreach ($ev->fetchAll() as $row) {
    $ruta = UPLOAD_DIR . $row['nombre_archivo'];
    if (is_file($ruta)) { @unlink($ruta); }
}
$pdo->prepare("DELETE FROM reportes_evidencias WHERE reporte_id = ?")->execute([$id]);

// 2) Documentos firmados (archivos; las filas caen por ON DELETE CASCADE)
$ff = $pdo->prepare("SELECT nombre_archivo FROM reportes_firmados WHERE reporte_id = ?");
$ff->execute([$id]);
foreach ($ff->fetchAll() as $row) {
    $ruta = UPLOAD_DIR . $row['nombre_archivo'];
    if (is_file($ruta)) { @unlink($ruta); }
}

// 3) Auditoría + borrado de la cabecera
registrar_auditoria($pdo, $usuario_id, 'DELETE', 'reportes', $id, json_encode([
    'tipo' => $reporte['tipo'], 'empleado_id' => $reporte['empleado_id'], 'fecha' => $reporte['fecha'],
], JSON_UNESCAPED_UNICODE));

$pdo->prepare("DELETE FROM reportes WHERE id = ?")->execute([$id]);

redirect('modules/reportes/listar.php?msg=' . urlencode('Reporte eliminado.'));
