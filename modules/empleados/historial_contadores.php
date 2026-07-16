<?php
require_once '../../includes/auth.php';
header('Content-Type: application/json');

$empleado_id = $_GET['id'] ?? 0;
if (!$empleado_id) {
    echo json_encode(['cursos' => 0, 'pendientes' => 0]);
    exit;
}

// Cursos tomados
$stmt = $pdo->prepare("SELECT COUNT(*) FROM empleado_curso WHERE empleado_id = ?");
$stmt->execute([$empleado_id]);
$cursos = $stmt->fetchColumn();

// Pendientes (misma lógica que en historial.php)
$sql = "SELECT COUNT(DISTINCT c.id)
        FROM cursos c
        JOIN curso_asignaciones ca ON c.id = ca.curso_id
        WHERE c.activo = 1
          AND (
              (ca.tipo_asignacion = 'todos')
              OR (ca.tipo_asignacion = 'sucursal' AND ca.entidad_id = (SELECT sucursal_id FROM empleados WHERE id = ?))
              OR (ca.tipo_asignacion = 'departamento' AND ca.entidad_id = (SELECT departamento_id FROM empleados WHERE id = ?))
              OR (ca.tipo_asignacion = 'empleado' AND ca.entidad_id = ?)
              OR (ca.tipo_asignacion = 'excepto_empleado' AND NOT EXISTS (
                    SELECT 1 FROM curso_asignaciones x
                    WHERE x.curso_id = c.id AND x.tipo_asignacion = 'excepto_empleado' AND x.entidad_id = ?))
          )
          AND c.id NOT IN (SELECT curso_id FROM empleado_curso WHERE empleado_id = ?)";
$stmt = $pdo->prepare($sql);
$stmt->execute([$empleado_id, $empleado_id, $empleado_id, $empleado_id]);
$pendientes = $stmt->fetchColumn();

echo json_encode(['cursos' => (int)$cursos, 'pendientes' => (int)$pendientes]);
exit;