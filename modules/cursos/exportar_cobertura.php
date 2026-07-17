<?php
require_once '../../includes/auth.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$curso_id = $_GET['id'] ?? 0;
if (!$curso_id) {
    header('Location: listar.php');
    exit;
}

$sucursal_id = $_GET['sucursal_id'] ?? '';
$departamento_id = $_GET['departamento_id'] ?? '';
$estado = $_GET['estado'] ?? '1';
$buscar = trim($_GET['buscar'] ?? '');
$solo_pendientes = isset($_GET['pendientes']) ? true : false;

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

// Multisucursal: no-admin solo su sucursal
if ($usuario_rol !== 'admin') {
    $whereEmpleados[] = "e.sucursal_id IN ($usuario_sucursales_sql)";
}

$whereSQL = '';
if (!empty($whereEmpleados)) {
    $whereSQL = ' AND ' . implode(' AND ', $whereEmpleados);
}

$stmt = $pdo->prepare("SELECT * FROM cursos WHERE id = ?");
$stmt->execute([$curso_id]);
$curso = $stmt->fetch();
if (!$curso) {
    header('Location: listar.php');
    exit;
}

$asignaciones = $pdo->prepare("SELECT * FROM curso_asignaciones WHERE curso_id = ?");
$asignaciones->execute([$curso_id]);
$asigs = $asignaciones->fetchAll();
$tipo_asignacion = $asigs[0]['tipo_asignacion'] ?? 'todos';
$entidades_asignadas = array_column($asigs, 'entidad_id');

$condicionDeben = '';
if ($tipo_asignacion === 'todos') {
    $condicionDeben = '1=1';
} elseif ($tipo_asignacion === 'sucursal') {
    $ids = implode(',', array_map('intval', $entidades_asignadas));
    $condicionDeben = "e.sucursal_id IN ($ids)";
} elseif ($tipo_asignacion === 'departamento') {
    $ids = implode(',', array_map('intval', $entidades_asignadas));
    $condicionDeben = "e.departamento_id IN ($ids)";
} elseif ($tipo_asignacion === 'excepto_empleado') {
    $ids = implode(',', array_map('intval', array_filter($entidades_asignadas, fn($v) => $v !== null)));
    $condicionDeben = $ids !== '' ? "e.id NOT IN ($ids)" : '1=1';
} else {
    $ids = implode(',', array_map('intval', $entidades_asignadas));
    $condicionDeben = $ids !== '' ? "e.id IN ($ids)" : '0=1';
}

// Sucursal a nivel de curso: el alcance se limita a la sucursal del curso (si tiene)
$condSucCurso = !empty($curso['sucursal_id']) ? "e.sucursal_id = " . (int)$curso['sucursal_id'] : "1=1";
$condicionDeben = "($condSucCurso) AND ($condicionDeben)";

$wherePendientes = '';
$paramsTomaron = array_merge([$curso_id], $paramsBase);
$paramsNoTomaron = array_merge([$curso_id], $paramsBase);
if ($solo_pendientes) {
    $wherePendientes = " AND e.id NOT IN (SELECT empleado_id FROM empleado_curso WHERE curso_id = ?)";
    $paramsNoTomaron[] = $curso_id;
}

// Han tomado
$sqlTomaron = "SELECT e.numero_empleado, e.nombre, d.nombre as departamento, s.nombre as sucursal, MAX(ec.fecha_realizacion) as ultima_fecha,
                      ($condicionDeben) as requerido
               FROM empleado_curso ec
               JOIN empleados e ON ec.empleado_id = e.id
               JOIN departamentos d ON e.departamento_id = d.id
               JOIN sucursales s ON e.sucursal_id = s.id
               WHERE ec.curso_id = ? $whereSQL
               GROUP BY e.id
               ORDER BY e.nombre";
$stmtTomaron = $pdo->prepare($sqlTomaron);
$stmtTomaron->execute($paramsTomaron);
$tomaron = $stmtTomaron->fetchAll();

// No han tomado
$sqlNoTomaron = "SELECT e.numero_empleado, e.nombre, d.nombre as departamento, s.nombre as sucursal,
                        ($condicionDeben) as requerido
                 FROM empleados e
                 JOIN departamentos d ON e.departamento_id = d.id
                 JOIN sucursales s ON e.sucursal_id = s.id
                 WHERE e.id NOT IN (SELECT empleado_id FROM empleado_curso WHERE curso_id = ?)
                   AND ($condicionDeben) $whereSQL $wherePendientes
                 ORDER BY e.nombre";
$stmtNoTomaron = $pdo->prepare($sqlNoTomaron);
$stmtNoTomaron->execute($paramsNoTomaron);
$noTomaron = $stmtNoTomaron->fetchAll();

$spreadsheet = new Spreadsheet();

$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Han tomado');
$sheet1->setCellValue('A1', 'Curso/Formato: ' . $curso['nombre']);
$sheet1->setCellValue('A2', 'Empleados que han tomado el curso/formato (filtros aplicados)');
$sheet1->setCellValue('A4', 'Número');
$sheet1->setCellValue('B4', 'Nombre');
$sheet1->setCellValue('C4', 'Departamento');
$sheet1->setCellValue('D4', 'Sucursal');
$sheet1->setCellValue('E4', 'Última realización');
$sheet1->setCellValue('F4', 'Requerido');
$row = 5;
foreach ($tomaron as $e) {
    $sheet1->setCellValue('A'.$row, $e['numero_empleado']);
    $sheet1->setCellValue('B'.$row, $e['nombre']);
    $sheet1->setCellValue('C'.$row, $e['departamento']);
    $sheet1->setCellValue('D'.$row, $e['sucursal']);
    $sheet1->setCellValue('E'.$row, $e['ultima_fecha']);
    $sheet1->setCellValue('F'.$row, $e['requerido'] ? 'Sí' : 'No');
    $row++;
}
foreach (range('A','F') as $col) $sheet1->getColumnDimension($col)->setAutoSize(true);

$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('NO han tomado');
$sheet2->setCellValue('A1', 'Curso/Formato: ' . $curso['nombre']);
$sheet2->setCellValue('A2', 'Empleados que NO han tomado el curso/formato (filtros aplicados)');
$sheet2->setCellValue('A4', 'Número');
$sheet2->setCellValue('B4', 'Nombre');
$sheet2->setCellValue('C4', 'Departamento');
$sheet2->setCellValue('D4', 'Sucursal');
$sheet2->setCellValue('E4', 'Requerido');
$row = 5;
foreach ($noTomaron as $e) {
    $sheet2->setCellValue('A'.$row, $e['numero_empleado']);
    $sheet2->setCellValue('B'.$row, $e['nombre']);
    $sheet2->setCellValue('C'.$row, $e['departamento']);
    $sheet2->setCellValue('D'.$row, $e['sucursal']);
    $sheet2->setCellValue('E'.$row, $e['requerido'] ? 'Sí' : 'No');
    $row++;
}
foreach (range('A','E') as $col) $sheet2->getColumnDimension($col)->setAutoSize(true);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="cobertura_' . preg_replace('/[^a-zA-Z0-9]/', '_', $curso['nombre']) . '_' . date('Ymd_His') . '.xlsx"');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;