<?php
require_once '../../includes/auth.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$id = $_GET['id'] ?? 0;
if (!$id) {
    header('Location: listar.php');
    exit;
}

// Obtener empleado
$stmt = $pdo->prepare("SELECT numero_empleado, nombre FROM empleados WHERE id = ?");
$stmt->execute([$id]);
$empleado = $stmt->fetch();

// Filtros
$tipo_filtro = $_GET['tipo'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

$where = ["r.empleado_id = ?"];
$params = [$id];

if ($tipo_filtro) { $where[] = "r.tipo = ?"; $params[] = $tipo_filtro; }
if ($fecha_desde) { $where[] = "r.fecha >= ?"; $params[] = $fecha_desde; }
if ($fecha_hasta) { $where[] = "r.fecha <= ?"; $params[] = $fecha_hasta; }

$sql = "SELECT r.fecha, r.hora, r.tipo, r.gravedad,
               COALESCE(a.descripcion, t.descripcion) as descripcion,
               am.descripcion as atencion_medica,
               r.observacion, r.evidencia_foto,
               u.nombre_completo as reportado_por
        FROM reportes r
        LEFT JOIN actos_inseguros a ON r.acto_inseguro_id = a.id
        LEFT JOIN tipos_accidente t ON r.accidente_id = t.id
        LEFT JOIN atenciones_medicas am ON r.atencion_medica_id = am.id
        JOIN usuarios u ON r.reportado_por = u.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY r.fecha DESC, r.hora DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reportes = $stmt->fetchAll();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Historial ' . $empleado['numero_empleado']);

$sheet->setCellValue('A1', 'Historial de Reportes');
$sheet->mergeCells('A1:I1');
$sheet->setCellValue('A2', 'Empleado: ' . $empleado['numero_empleado'] . ' - ' . $empleado['nombre']);
$sheet->mergeCells('A2:I2');

$sheet->setCellValue('A4', 'Fecha');
$sheet->setCellValue('B4', 'Hora');
$sheet->setCellValue('C4', 'Tipo');
$sheet->setCellValue('D4', 'Descripción');
$sheet->setCellValue('E4', 'Gravedad');
$sheet->setCellValue('F4', 'Atención Médica');
$sheet->setCellValue('G4', 'Observación');
$sheet->setCellValue('H4', 'Evidencia (URL)');
$sheet->setCellValue('I4', 'Reportado por');

$row = 5;
foreach ($reportes as $r) {
    $sheet->setCellValue('A'.$row, $r['fecha']);
    $sheet->setCellValue('B'.$row, $r['hora']);
    $sheet->setCellValue('C'.$row, $r['tipo'] == 'acto_inseguro' ? 'Acto Inseguro' : 'Accidente');
    $sheet->setCellValue('D'.$row, $r['descripcion'] ?? '');
    $sheet->setCellValue('E'.$row, $r['gravedad'] ?? '');
    $sheet->setCellValue('F'.$row, $r['atencion_medica'] ?? '');
    $sheet->setCellValue('G'.$row, $r['observacion'] ?? '');
    $url = $r['evidencia_foto'] ? UPLOAD_URL . $r['evidencia_foto'] : '';
    $sheet->setCellValue('H'.$row, $url);
    $sheet->setCellValue('I'.$row, $r['reportado_por']);
    $row++;
}

foreach (range('A','I') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="historial_' . $empleado['numero_empleado'] . '_' . date('Ymd_His') . '.xlsx"');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;