<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success' => false, 'error' => 'Método no permitido']); exit; }
if (!in_array($usuario_rol, ['admin', 'supervisor'], true)) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Sin permisos']); exit; }

$nota_id = (int)($_POST['nota_id'] ?? 0);
if (!$nota_id) { echo json_encode(['success' => false, 'error' => 'Nota inválida']); exit; }

// Carga la nota + sucursal del empleado para validar acceso
$q = $pdo->prepare("SELECT n.id, n.empleado_id, e.sucursal_id
                    FROM empleado_notas n JOIN empleados e ON e.id = n.empleado_id
                    WHERE n.id = ?");
$q->execute([$nota_id]);
$row = $q->fetch();
if (!$row) { echo json_encode(['success' => false, 'error' => 'La nota no existe']); exit; }
if ($usuario_rol !== 'admin' && (int)$row['sucursal_id'] !== (int)$usuario_sucursal_id) {
    http_response_code(403); echo json_encode(['success' => false, 'error' => 'Fuera de su sucursal']); exit;
}

$pdo->prepare("DELETE FROM empleado_notas WHERE id = ?")->execute([$nota_id]);
registrar_auditoria($pdo, $usuario_id, 'DELETE', 'empleado_notas', $nota_id,
    json_encode(['empleado_id' => (int)$row['empleado_id']], JSON_UNESCAPED_UNICODE));

echo json_encode(['success' => true]);
