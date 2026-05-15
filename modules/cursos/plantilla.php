<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$filename = 'plantilla_cursos_empleados_' . date('Ymd') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

fputcsv($output, ['numero_empleado', 'nombre_empleado', 'cursos', 'fecha_realizacion']);

// Filas de ejemplo
fputcsv($output, ['1234', 'Juan Pérez', 'Curso básico de seguridad, Manejo de extintores', date('Y-m-d')]);
fputcsv($output, ['5678', 'María López', 'Trabajo en alturas', date('Y-m-d', strtotime('-15 days'))]);

fclose($output);
exit;