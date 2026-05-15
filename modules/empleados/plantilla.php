<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$filename = 'plantilla_empleados_supermm_' . date('Ymd') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
// BOM para UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Encabezados actualizados con fecha_nacimiento
fputcsv($output, ['numero_empleado', 'nombre', 'departamento', 'sucursal', 'fecha_nacimiento']);

// Filas de ejemplo
fputcsv($output, ['1', 'Juan Pérez', 'Cajas', 'SuperMM', '1985-04-12']);
fputcsv($output, ['2', 'María López', 'Mantenimiento', 'Valle Dorado', '1990-08-23']);

fclose($output);
exit;