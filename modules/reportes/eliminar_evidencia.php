<?php
/**
 * Elimina una evidencia de un reporte (fila + archivo en disco).
 * Responde JSON. Llamado por AJAX desde editar.php (eliminarEvidencia()).
 * Acceso: admin siempre; supervisor/usuario solo en su sucursal.
 */
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido']);
    exit;
}

$evidencia_id = (int)($_POST['evidencia_id'] ?? 0);
if ($evidencia_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Evidencia inválida']);
    exit;
}

// Carga la evidencia junto con la sucursal del reporte para validar acceso
$st = $pdo->prepare("SELECT ev.id, ev.reporte_id, ev.nombre_archivo, r.sucursal_id
                     FROM reportes_evidencias ev
                     JOIN reportes r ON r.id = ev.reporte_id
                     WHERE ev.id = ?");
$st->execute([$evidencia_id]);
$ev = $st->fetch();

if (!$ev) {
    echo json_encode(['ok' => false, 'error' => 'La evidencia no existe']);
    exit;
}

$es_admin = ($usuario_rol === 'admin');
if (!$es_admin && (int)$ev['sucursal_id'] !== (int)$usuario_sucursal_id) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permisos para esta evidencia']);
    exit;
}

try {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM reportes_evidencias WHERE id = ?")->execute([$evidencia_id]);

    // Borra el archivo físico solo si ninguna otra fila lo referencia
    $dup = $pdo->prepare("SELECT COUNT(*) FROM reportes_evidencias WHERE nombre_archivo = ?");
    $dup->execute([$ev['nombre_archivo']]);
    if ((int)$dup->fetchColumn() === 0) {
        $ruta = UPLOAD_DIR . $ev['nombre_archivo'];
        if (is_file($ruta)) { @unlink($ruta); }
    }

    registrar_auditoria($pdo, $usuario_id, 'DELETE', 'reportes_evidencias', $evidencia_id,
        json_encode(['reporte_id' => (int)$ev['reporte_id'], 'archivo' => $ev['nombre_archivo']], JSON_UNESCAPED_UNICODE));

    $pdo->commit();
    echo json_encode(['ok' => true, 'id' => $evidencia_id]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al eliminar la evidencia']);
}
