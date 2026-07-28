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
require_once '../../includes/papelera.php';

exigir('6s.eliminar');
$es_admin = ($usuario_rol === 'admin');

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
if (!puede('6s.eliminar.directo')) {
    autz_crear_solicitud(
        $pdo, $usuario_id, 'auditorias_6s', $id, 'DELETE', null,
        'Eliminación de auditoría 6S #' . $id, (int) $aud['sucursal_id']
    );
    redirect('modules/auditoria6s/listar.php?msg=' . urlencode('Solicitud de eliminación enviada para autorización.'));
}

// Admin: eliminación directa -> a la papelera (conserva archivos hasta purgar)
papelera_snapshot($pdo, 'auditorias_6s', $id, $usuario_id);

registrar_auditoria($pdo, $usuario_id, 'DELETE', 'auditorias_6s', $id, json_encode([
    'sucursal_id' => $aud['sucursal_id'], 'departamento_id' => $aud['departamento_id'], 'fecha' => $aud['fecha']
], JSON_UNESCAPED_UNICODE));

$pdo->prepare("DELETE FROM auditorias_6s WHERE id = ?")->execute([$id]);

redirect('modules/auditoria6s/listar.php?msg=' . urlencode('Auditoría enviada a la papelera.'));
