<?php
require_once '../../includes/auth.php';
header('Content-Type: application/json');

if (!isset($usuario_id)) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$empleado_id = $_POST['empleado_id'] ?? 0;
$curso_id = $_POST['curso_id'] ?? 0;
$accion = $_POST['accion'] ?? '';
$fecha = $_POST['fecha'] ?? date('Y-m-d');

if (!$empleado_id || !$curso_id || !in_array($accion, ['tomado', 'no_tomado'])) {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

$fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$fecha_obj || $fecha_obj->format('Y-m-d') !== $fecha) {
    $fecha = date('Y-m-d');
}

try {
    if ($accion === 'tomado') {
        $stmt = $pdo->prepare("INSERT INTO empleado_curso (empleado_id, curso_id, fecha_realizacion) VALUES (?, ?, ?)");
        $stmt->execute([$empleado_id, $curso_id, $fecha]);
        echo json_encode(['success' => true, 'action' => 'inserted']);
    } else {
        $stmt = $pdo->prepare("DELETE FROM empleado_curso WHERE empleado_id = ? AND curso_id = ?");
        $stmt->execute([$empleado_id, $curso_id]);
        echo json_encode(['success' => true, 'action' => 'deleted']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;