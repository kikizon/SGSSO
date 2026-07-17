<?php
/**
 * Eliminar auditoría 6S.
 * - admin: elimina directo (evidencias + cabecera; respuestas caen por CASCADE).
 * - supervisor: genera una SOLICITUD de eliminación (la aprueba un admin).
 * Reemplaza al eliminar.php anterior.
 */
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/authorization.php';

$es_admin = ($usuario_rol === 'admin');
if (!$es_admin && $usuario_rol !== 'supervisor') {
    redirect('modules/auditoria6s/listar.php');
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) { redirect('modules/auditoria6s/listar.php'); }

$stmt = $pdo->prepare("SELECT * FROM auditorias_6s WHERE id = ?");
$stmt->execute([$id]);
$aud = $stmt->fetch();
if (!$aud) { redirect('modules/auditoria6s/listar.php'); }

// Alcance por sucursal (supervisor)
if (!$es_admin && !in_array((int)$aud['sucursal_id'], $usuario_sucursales, true)) {
    redirect('modules/auditoria6s/listar.php');
}

// Si ya hay una solicitud pendiente, no permitir otra acción
if (autz_hay_pendiente($pdo, 'auditorias_6s', $id)) {
    redirect('modules/auditoria6s/listar.php?err=' . urlencode('Esta auditoría ya tiene una solicitud pendiente de autorización.'));
}

// Supervisor: solicitar (no borra)
if (!$es_admin) {
    autz_crear_solicitud(
        $pdo, $usuario_id, 'auditorias_6s', $id, 'DELETE', null,
        'Eliminación de auditoría 6S #' . $id, (int) $aud['sucursal_id']
    );
    redirect('modules/auditoria6s/listar.php?msg=' . urlencode('Solicitud de eliminación enviada para autorización.'));
}

// Admin: eliminación directa
// Borrar archivos físicos de evidencias
$ev = $pdo->prepare("SELECT e.nombre_archivo
                     FROM auditorias_6s_evidencias e
                     JOIN auditorias_6s_respuestas r ON r.id = e.respuesta_id
                     WHERE r.auditoria_id = ?");
$ev->execute([$id]);
foreach ($ev->fetchAll() as $f) {
    $ruta = UPLOAD_DIR . $f['nombre_archivo'];
    if (is_file($ruta)) { @unlink($ruta); }
}

registrar_auditoria($pdo, $usuario_id, 'DELETE', 'auditorias_6s', $id, json_encode([
    'sucursal_id' => $aud['sucursal_id'], 'departamento_id' => $aud['departamento_id'], 'fecha' => $aud['fecha']
], JSON_UNESCAPED_UNICODE));

$pdo->prepare("DELETE FROM auditorias_6s WHERE id = ?")->execute([$id]);

redirect('modules/auditoria6s/listar.php?msg=deleted');
