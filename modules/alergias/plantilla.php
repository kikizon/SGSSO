<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$filename = 'plantilla_alergias_empleados_' . date('Ymd') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

fputcsv($output, ['numero_empleado', 'nombre_empleado', 'alergias']);

fputcsv($output, ['1234', 'Juan Pérez', 'Polen, Ácaros del polvo']);
fputcsv($output, ['5678', 'María López', 'Mariscos']);

fclose($output);
exit;