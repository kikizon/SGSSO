<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once __DIR__ . '/_inc.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/incapacidades/listar.php'); }
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Token CSRF inválido.'); }
if ($usuario_rol !== 'admin' && $usuario_rol !== 'supervisor') { redirect('modules/dashboard/'); }

$tramo_id = (int) ($_POST['tramo_id'] ?? 0);

$st = $pdo->prepare("SELECT t.*, r.sucursal_id, r.fecha_regreso
                     FROM incapacidad_tramos t JOIN reportes r ON r.id = t.reporte_id
                     WHERE t.id = ?");
$st->execute([$tramo_id]);
$t = $st->fetch();
if (!$t) { redirect('modules/incapacidades/listar.php'); }

if ($usuario_rol !== 'admin' && $t['sucursal_id'] != ($usuario_sucursal_id ?? null)) {
    redirect('modules/incapacidades/listar.php');
}
$volver = 'modules/incapacidades/seguimiento.php?reporte_id=' . (int)$t['reporte_id'];
if (!empty($t['fecha_regreso'])) {
    redirect($volver . '&err=' . urlencode('El seguimiento está cerrado. Reábrelo para modificar tramos.'));
}

$ruta = UPLOAD_DIR . $t['nombre_archivo'];
if (is_file($ruta)) { @unlink($ruta); }
$pdo->prepare("DELETE FROM incapacidad_tramos WHERE id = ?")->execute([$tramo_id]);

$total = recompute_dias_incapacidad($pdo, (int)$t['reporte_id']);
registrar_auditoria($pdo, $usuario_id, 'DELETE', 'incapacidad_tramos', $tramo_id,
    json_encode(['reporte_id' => (int)$t['reporte_id'], 'total' => $total], JSON_UNESCAPED_UNICODE));

redirect($volver . '&msg=' . urlencode("Tramo eliminado. Total de días: $total."));
