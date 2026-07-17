<?php
/** Elimina un documento firmado de un reporte. PRG + CSRF. */
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/reportes/listar.php');
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Token CSRF inválido.');
}

$firmado_id = (int) ($_POST['firmado_id'] ?? 0);

// Trae el firmado + sucursal del reporte para validar alcance
$st = $pdo->prepare("SELECT f.id, f.reporte_id, f.nombre_archivo, r.sucursal_id
                     FROM reportes_firmados f
                     JOIN reportes r ON r.id = f.reporte_id
                     WHERE f.id = ?");
$st->execute([$firmado_id]);
$f = $st->fetch();
if (!$f) { redirect('modules/reportes/listar.php'); }

if ($usuario_rol !== 'admin' && !in_array((int)$f['sucursal_id'], $usuario_sucursales, true)) {
    redirect('modules/reportes/listar.php');
}

// Borra archivo físico y fila
$ruta = UPLOAD_DIR . $f['nombre_archivo'];
if (is_file($ruta)) { @unlink($ruta); }
$pdo->prepare("DELETE FROM reportes_firmados WHERE id = ?")->execute([$firmado_id]);

registrar_auditoria($pdo, $usuario_id, 'DELETE', 'reportes_firmados', $firmado_id,
    json_encode(['reporte_id' => $f['reporte_id']], JSON_UNESCAPED_UNICODE));

redirect('modules/reportes/ver_reporte.php?id=' . (int)$f['reporte_id'] . '&msg=' . urlencode('Documento firmado eliminado.'));
