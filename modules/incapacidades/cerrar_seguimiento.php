<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once __DIR__ . '/_inc.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/incapacidades/listar.php'); }
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Token CSRF inválido.'); }
if ($usuario_rol !== 'admin' && $usuario_rol !== 'supervisor') { redirect('modules/dashboard/'); }

$reporte_id = (int) ($_POST['reporte_id'] ?? 0);
$rep = cargar_reporte_incapacidad($pdo, $reporte_id, $usuario_rol, $usuario_sucursal_id ?? null);
$volver = 'modules/incapacidades/seguimiento.php?reporte_id=' . $reporte_id;

$fecha = $_POST['fecha_regreso'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    redirect($volver . '&err=' . urlencode('Fecha de regreso no válida.'));
}

$pdo->prepare("UPDATE reportes SET fecha_regreso = ? WHERE id = ?")->execute([$fecha, $reporte_id]);
registrar_auditoria($pdo, $usuario_id, 'UPDATE', 'reportes', $reporte_id,
    json_encode(['fecha_regreso' => $fecha], JSON_UNESCAPED_UNICODE));

redirect($volver . '&msg=' . urlencode('Regreso a labores registrado. Seguimiento cerrado.'));
