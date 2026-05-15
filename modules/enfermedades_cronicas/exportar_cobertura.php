<?php
require_once '../../includes/auth.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$enfermedad_id = $_GET['id'] ?? 0;
if (!$enfermedad_id) {
    header('Location: listar.php');
    exit;
}

$sucursal_id = $_GET['sucursal_id'] ?? '';
$departamento_id = $_GET['departamento_id'] ?? '';
$estado = $_GET['estado'] ?? '1';
$buscar = trim($_GET['buscar'] ?? '');

$whereEmpleados = [];
$paramsBase = [];

if (!empty($sucursal_id)) {
    $whereEmpleados[] = "e.sucursal_id = ?";
    $paramsBase[] = $sucursal_id;
}
if (!empty($departamento_id)) {
    $whereEmpleados[] = "e.departamento_id = ?";
    $paramsBase[] = $departamento_id;
}
if ($estado !== '') {
    $whereEmpleados[] = "e.activo = ?";
    $paramsBase[] = $estado;
}
if (!empty($buscar)) {
    $whereEmpleados[] = "(e.numero_empleado LIKE ? OR e.nombre LIKE ?)";
    $paramsBase[] = "%$buscar%";
    $paramsBase[] = "%$buscar%";
}

$whereSQL = '';
if (!empty($whereEmpleados)) {
    $whereSQL = ' AND ' . implode(' AND ', $whereEmpleados);
}

$stmt = $pdo->prepare("SELECT nombre FROM enfermedades_cronicas WHERE id = ?");
$stmt->execute([$enfermedad_id]);
$enfermedad = $stmt->fetch();
if (!$enfermedad) {
    header('Location: listar.php');
    exit;
}

// Tienen
$paramsTienen = array_merge([$enfermedad_id], $paramsBase);
$sqlTienen = "SELECT e.numero_empleado, e.nombre, d.nombre as departamento, s.nombre as sucursal, ee.fecha_registro
              FROM empleado_enfermedad ee
              JOIN empleados e ON ee.empleado_id = e.id
              JOIN departamentos d ON e.departamento_id = d.id
              JOIN sucursales s ON e.sucursal_id = s.id
              WHERE ee.enfermedad_id = ? $whereSQL
              ORDER BY e.nombre";
$stmtTienen = $pdo->prepare($sqlTienen);
$stmtTienen->execute($paramsTienen);
$tienen = $stmtTienen->fetchAll();

// No tienen
$paramsNoTienen = array_merge([$enfermedad_id], $paramsBase);
$sqlNoTienen = "SELECT e.numero_empleado, e.nombre, d.nombre as departamento, s.nombre as sucursal
                FROM empleados e
                JOIN departamentos d ON e.departamento_id = d.id
                JOIN sucursales s ON e.sucursal_id = s.id
                WHERE e.id NOT IN (SELECT empleado_id FROM empleado_enfermedad WHERE enfermedad_id = ?) $whereSQL
                ORDER BY e.nombre";
$stmtNoTienen = $pdo->prepare($sqlNoTienen);
$stmtNoTienen->execute($paramsNoTienen);
$noTienen = $stmtNoTienen->fetchAll();

$spreadsheet = new Spreadsheet();

$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Tienen la enfermedad');
$sheet1->setCellValue('A1', 'Enfermedad: ' . $enfermedad['nombre']);
$sheet1->setCellValue('A2', 'Empleados que TIENEN la enfermedad (filtros aplicados)');
$sheet1->setCellValue('A4', 'Número');
$sheet1->setCellValue('B4', 'Nombre');
$sheet1->setCellValue('C4', 'Departamento');
$sheet1->setCellValue('D4', 'Sucursal');
$sheet1->setCellValue('E4', 'Fecha registro');
$row = 5;
foreach ($tienen as $e) {
    $sheet1->setCellValue('A'.$row, $e['numero_empleado']);
    $sheet1->setCellValue('B'.$row, $e['nombre']);
    $sheet1->setCellValue('C'.$row, $e['departamento']);
    $sheet1->setCellValue('D'.$row, $e['sucursal']);
    $sheet1->setCellValue('E'.$row, $e['fecha_registro']);
    $row++;
}
foreach (range('A','E') as $col) $sheet1->getColumnDimension($col)->setAutoSize(true);

$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('NO tienen la enfermedad');
$sheet2->setCellValue('A1', 'Enfermedad: ' . $enfermedad['nombre']);
$sheet2->setCellValue('A2', 'Empleados que NO tienen la enfermedad (filtros aplicados)');
$sheet2->setCellValue('A4', 'Número');
$sheet2->setCellValue('B4', 'Nombre');
$sheet2->setCellValue('C4', 'Departamento');
$sheet2->setCellValue('D4', 'Sucursal');
$row = 5;
foreach ($noTienen as $e) {
    $sheet2->setCellValue('A'.$row, $e['numero_empleado']);
    $sheet2->setCellValue('B'.$row, $e['nombre']);
    $sheet2->setCellValue('C'.$row, $e['departamento']);
    $sheet2->setCellValue('D'.$row, $e['sucursal']);
    $row++;
}
foreach (range('A','D') as $col) $sheet2->getColumnDimension($col)->setAutoSize(true);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="cobertura_' . preg_replace('/[^a-zA-Z0-9]/', '_', $enfermedad['nombre']) . '_' . date('Ymd_His') . '.xlsx"');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;