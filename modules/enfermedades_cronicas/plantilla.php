<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$filename = 'plantilla_enfermedades_empleados_' . date('Ymd') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

// Nuevos encabezados: número de empleado, nombre (referencia) y enfermedades separadas por coma
fputcsv($output, ['numero_empleado', 'nombre_empleado', 'enfermedades']);

// Ejemplos
fputcsv($output, ['1234', 'Juan Pérez', 'Diabetes tipo 2, Hipertensión arterial']);
fputcsv($output, ['5678', 'María López', 'Asma, Obesidad, Artritis']);
fputcsv($output, ['9012', 'Carlos Sánchez', 'Diabetes tipo 2']);

fclose($output);
exit;