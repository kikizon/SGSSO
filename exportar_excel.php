<?php
require_once 'includes/auth.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$tipo = $_GET['tipo'] ?? 'acto_inseguro';
if (!in_array($tipo, ['acto_inseguro','accidente'])) $tipo = 'acto_inseguro';

$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$departamento_id = $_GET['departamento_id'] ?? '';
$sucursal_id = $_GET['sucursal_id'] ?? '';
$catalogo_id = $_GET['catalogo_id'] ?? '';

$where = ["r.tipo = ?"];
$params = [$tipo];
if ($fecha_desde) { $where[] = "r.fecha >= ?"; $params[] = $fecha_desde; }
if ($fecha_hasta) { $where[] = "r.fecha <= ?"; $params[] = $fecha_hasta; }
if ($departamento_id) { $where[] = "r.departamento_id = ?"; $params[] = $departamento_id; }
if ($sucursal_id) { $where[] = "r.sucursal_id = ?"; $params[] = $sucursal_id; }
if ($catalogo_id) {
    $where[] = ($tipo == 'acto_inseguro') ? "r.acto_inseguro_id = ?" : "r.accidente_id = ?";
    $params[] = $catalogo_id;
}

$joinCatalogo = ($tipo == 'acto_inseguro') 
    ? "JOIN actos_inseguros a ON r.acto_inseguro_id = a.id"
    : "JOIN tipos_accidente a ON r.accidente_id = a.id";

$sql = "SELECT r.fecha, r.hora, e.nombre as empleado, d.nombre as departamento, s.nombre as sucursal,
               a.descripcion as catalogo, r.gravedad, am.descripcion as atencion_medica,
               r.observacion, r.evidencia_foto, u.nombre_completo as reportado_por
        FROM reportes r
        JOIN empleados e ON r.empleado_id = e.id
        JOIN departamentos d ON r.departamento_id = d.id
        JOIN sucursales s ON r.sucursal_id = s.id
        $joinCatalogo
        JOIN usuarios u ON r.reportado_por = u.id
        LEFT JOIN atenciones_medicas am ON r.atencion_medica_id = am.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY r.fecha DESC, r.hora DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reportes = $stmt->fetchAll();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Reportes '. ($tipo=='acto_inseguro'?'Actos':'Accidentes'));

$sheet->setCellValue('A1', 'Fecha');
$sheet->setCellValue('B1', 'Hora');
$sheet->setCellValue('C1', 'Empleado');
$sheet->setCellValue('D1', 'Sucursal');
$sheet->setCellValue('E1', 'Departamento');
$sheet->setCellValue('F1', $tipo=='acto_inseguro'?'Acto Inseguro':'Tipo de Accidente');
$sheet->setCellValue('G1', 'Gravedad');
$sheet->setCellValue('H1', 'Atención Médica');
$sheet->setCellValue('I1', 'Observación');
$sheet->setCellValue('J1', 'Evidencia (URL)');
$sheet->setCellValue('K1', 'Reportado por');

$row = 2;
foreach ($reportes as $r) {
    $sheet->setCellValue('A'.$row, $r['fecha']);
    $sheet->setCellValue('B'.$row, $r['hora']);
    $sheet->setCellValue('C'.$row, $r['empleado']);
    $sheet->setCellValue('D'.$row, $r['sucursal']);
    $sheet->setCellValue('E'.$row, $r['departamento']);
    $sheet->setCellValue('F'.$row, $r['catalogo']);
    $sheet->setCellValue('G'.$row, $r['gravedad'] ?? '');
    $sheet->setCellValue('H'.$row, $r['atencion_medica'] ?? '');
    $sheet->setCellValue('I'.$row, $r['observacion']);
    $url = $r['evidencia_foto'] ? UPLOAD_URL . $r['evidencia_foto'] : '';
    $sheet->setCellValue('J'.$row, $url);
    $sheet->setCellValue('K'.$row, $r['reportado_por']);
    $row++;
}

foreach (range('A','K') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="reportes_'.$tipo.'_'.date('Ymd_His').'.xlsx"');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;