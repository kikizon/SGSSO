<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success' => false, 'error' => 'Método no permitido']); exit; }
if (!in_array($usuario_rol, ['admin', 'supervisor'], true)) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Sin permisos']); exit; }

$empleado_id = (int)($_POST['empleado_id'] ?? 0);
$nota = trim($_POST['nota'] ?? '');
if (!$empleado_id || $nota === '') { echo json_encode(['success' => false, 'error' => 'Datos incompletos']); exit; }
if (mb_strlen($nota) > 1000) $nota = mb_substr($nota, 0, 1000);

// Empleado + candado multisucursal
$q = $pdo->prepare("SELECT sucursal_id FROM empleados WHERE id = ?");
$q->execute([$empleado_id]);
$suc = $q->fetchColumn();
if ($suc === false) { echo json_encode(['success' => false, 'error' => 'Empleado no existe']); exit; }
if ($usuario_rol !== 'admin' && (int)$suc !== (int)$usuario_sucursal_id) {
    http_response_code(403); echo json_encode(['success' => false, 'error' => 'Fuera de su sucursal']); exit;
}

// Nombre del autor (defensivo por si la columna difiere)
$autor = 'Usuario';
try {
    $a = $pdo->prepare("SELECT nombre FROM usuarios WHERE id = ?");
    $a->execute([$usuario_id]);
    $n = $a->fetchColumn();
    if ($n) $autor = $n;
} catch (Throwable $e) { /* usa 'Usuario' */ }

$ins = $pdo->prepare("INSERT INTO empleado_notas (empleado_id, nota, autor_id, autor_nombre) VALUES (?, ?, ?, ?)");
$ins->execute([$empleado_id, $nota, $usuario_id, $autor]);
registrar_auditoria($pdo, $usuario_id, 'INSERT', 'empleado_notas', $pdo->lastInsertId(),
    json_encode(['empleado_id' => $empleado_id], JSON_UNESCAPED_UNICODE));

echo json_encode(['success' => true]);
