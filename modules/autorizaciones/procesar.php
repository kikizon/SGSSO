<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/authorization.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/autorizaciones/listar.php');
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Token CSRF inválido.');
}
// Solo administradores pueden resolver solicitudes
if (!autz_puede_autorizar($usuario_rol)) {
    redirect('modules/autorizaciones/listar.php?err=' . urlencode('No tienes permiso para autorizar.'));
}

$accion       = $_POST['accion'] ?? '';
$solicitud_id = (int) ($_POST['solicitud_id'] ?? 0);

try {
    if ($accion === 'aprobar') {
        $ok = autz_aprobar($pdo, $solicitud_id, $usuario_id);
        $qs = $ok
            ? 'msg=' . urlencode('Solicitud aprobada y aplicada.')
            : 'err=' . urlencode('La solicitud ya no estaba pendiente.');
    } elseif ($accion === 'rechazar') {
        $motivo = trim($_POST['motivo'] ?? '');
        if ($motivo === '') {
            $qs = 'err=' . urlencode('El motivo del rechazo es obligatorio.');
        } else {
            $ok = autz_rechazar($pdo, $solicitud_id, $usuario_id, $motivo);
            $qs = $ok
                ? 'msg=' . urlencode('Solicitud rechazada.')
                : 'err=' . urlencode('La solicitud ya no estaba pendiente.');
        }
    } else {
        $qs = 'err=' . urlencode('Acción no reconocida.');
    }
} catch (Throwable $e) {
    $qs = 'err=' . urlencode('No se pudo procesar: ' . $e->getMessage());
}

redirect('modules/autorizaciones/listar.php?' . $qs);
