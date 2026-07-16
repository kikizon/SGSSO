<?php
require_once '../../includes/auth.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

/* ============================================================
 * Helpers de consulta parametrizada
 * ============================================================ */
function q(PDO $pdo, string $sql, array $p = []): array {
    $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll();
}
function qcol(PDO $pdo, string $sql, array $p = []) {
    $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchColumn();
}

/* ============================================================
 * Helpers de gráficos (con guarda de datos vacíos)
 * ============================================================ */
function graficoBarras($sheet, $labels, $valores, $titulo, $tl, $br, $horizontal = false, $serie = 'Total') {
    if (empty($labels) || array_sum($valores) == 0) return;
    $lblSerie = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, NULL, NULL, 1, [$serie])];
    $xAxis = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, NULL, NULL, count($labels), $labels)];
    $val = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, NULL, NULL, count($valores), $valores)];
    $series = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_STANDARD, [0], $lblSerie, $xAxis, $val);
    if ($horizontal) $series->setPlotDirection(DataSeries::DIRECTION_BAR);
    $layout = new Layout(); $layout->setShowVal(true);
    $chart = new Chart('bar_' . uniqid(), new Title($titulo), new Legend(Legend::POSITION_BOTTOM), new PlotArea($layout, [$series]), true, 0, NULL, NULL);
    $chart->setTopLeftPosition($tl); $chart->setBottomRightPosition($br);
    $sheet->addChart($chart);
}
function graficoPareto($sheet, $labels, $frec, $acum, $titulo, $tl, $br) {
    if (empty($labels) || array_sum($frec) == 0) return;
    $lblBar = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, NULL, NULL, 1, ['Frecuencia'])];
    $lblLine = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, NULL, NULL, 1, ['% Acumulado'])];
    $xAxis = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, NULL, NULL, count($labels), $labels)];
    $valBar = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, NULL, NULL, count($frec), $frec)];
    $valLine = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, NULL, NULL, count($acum), $acum)];
    $sBar = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_STANDARD, [0], $lblBar, $xAxis, $valBar);
    $sLine = new DataSeries(DataSeries::TYPE_LINECHART, DataSeries::GROUPING_STANDARD, [0], $lblLine, $xAxis, $valLine);
    $layout = new Layout(); $layout->setShowVal(true);
    $chart = new Chart('par_' . uniqid(), new Title($titulo), new Legend(Legend::POSITION_BOTTOM), new PlotArea($layout, [$sBar, $sLine]), true, 0, NULL, NULL);
    $chart->setTopLeftPosition($tl); $chart->setBottomRightPosition($br);
    $sheet->addChart($chart);
}

/* ============================================================
 * Construcción de filtros (parametrizados)
 * ============================================================ */
$sucursal_id = ($_GET['sucursal_id'] ?? '') !== '' ? (int) $_GET['sucursal_id'] : null;
$tipo_filtro = $_GET['tipo'] ?? 'acto_inseguro';
if (!in_array($tipo_filtro, ['acto_inseguro', 'accidente', 'enfermedad_cronica'], true)) $tipo_filtro = 'acto_inseguro';

$mes = $_GET['mes'] ?? '';
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = '';
$mesAnio = $mes ? (int) substr($mes, 0, 4) : null;
$mesNum  = $mes ? (int) substr($mes, 5, 2) : null;

$nombreSucursal = $sucursal_id ? (qcol($pdo, "SELECT nombre FROM sucursales WHERE id = ?", [$sucursal_id]) ?: '') : '';
$horas_hombre = HORAS_HOMBRE_MES;

/* Devuelve [sqlFragment, params] para sucursal + mes sobre alias r (reportes) */
function filtroR(?int $suc, ?int $anio, ?int $num): array {
    $sql = ''; $p = [];
    if ($suc) { $sql .= " AND r.sucursal_id = ?"; $p[] = $suc; }
    if ($anio) { $sql .= " AND YEAR(r.fecha) = ? AND MONTH(r.fecha) = ?"; $p[] = $anio; $p[] = $num; }
    return [$sql, $p];
}

/* ============================================================
 * HOJA: Actos / Accidentes
 * ============================================================ */
function hojaReportes($spreadsheet, PDO $pdo, ?int $suc, string $tipo, string $nombreSuc, $horas, ?int $anio, ?int $num) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle($tipo == 'acto_inseguro' ? 'Actos Inseguros' : 'Accidentes');
    [$fR, $pR] = filtroR($suc, $anio, $num);
    $pTipo = [$tipo];

    $titulo = 'DASHBOARD ' . ($tipo == 'acto_inseguro' ? 'ACTOS INSEGUROS' : 'ACCIDENTES') . ' - ' . ($suc ? "Sucursal: $nombreSuc" : 'Todas las Sucursales');
    if ($anio) $titulo .= sprintf(' - %04d-%02d', $anio, $num);
    $sheet->setCellValue('A1', $titulo);
    $sheet->mergeCells('A1:Z1');
    $sheet->getStyle('A1')->applyFromArray(['font'=>['bold'=>true,'size'=>14,'color'=>['rgb'=>'FFFFFF']],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'0D6EFD']],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);

    // KPIs
    $total = qcol($pdo, "SELECT COUNT(*) FROM reportes r WHERE r.tipo = ?$fR", array_merge($pTipo, $pR));
    $empleados = $suc ? qcol($pdo, "SELECT COUNT(*) FROM empleados e WHERE activo=1 AND e.sucursal_id = ?", [$suc]) : qcol($pdo, "SELECT COUNT(*) FROM empleados WHERE activo=1");
    $kpis = [['Total Reportes', $total], ['Empleados', $empleados]];
    if ($tipo == 'accidente') {
        $ultimo = qcol($pdo, "SELECT MAX(fecha) FROM reportes r WHERE r.tipo='accidente'" . ($suc ? " AND r.sucursal_id = ?" : ""), $suc ? [$suc] : []);
        $dsa = 'Sin datos';
        if ($ultimo) { $dsa = (new DateTime())->diff(new DateTime($ultimo))->days; }
        $kpis[] = ['DSA', $dsa];
        $diasIncap = qcol($pdo, "SELECT SUM(dias_perdidos) FROM reportes r WHERE r.tipo='accidente'$fR", $pR) ?: 0;
        $kpis[] = ['Días Incap. (periodo)', $diasIncap];
    }
    $col = 'A';
    foreach ($kpis as $k) {
        $sheet->setCellValue($col . '3', $k[0])->setCellValue($col . '4', $k[1]);
        $sheet->getStyle($col . '3:' . $col . '4')->applyFromArray(['borders'=>['outline'=>['borderStyle'=>Border::BORDER_THIN]],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
        $col++;
    }

    // Catálogo (solo con incidencia)
    if ($tipo == 'acto_inseguro') {
        $cat = q($pdo, "SELECT a.descripcion, COUNT(r.id) total FROM actos_inseguros a LEFT JOIN reportes r ON a.id=r.acto_inseguro_id$fR AND r.tipo='acto_inseguro' WHERE a.activo=1 GROUP BY a.id HAVING total>0 ORDER BY total DESC", $pR);
    } else {
        $cat = q($pdo, "SELECT t.descripcion, COUNT(r.id) total FROM tipos_accidente t LEFT JOIN reportes r ON t.id=r.accidente_id$fR AND r.tipo='accidente' WHERE t.activo=1 GROUP BY t.id HAVING total>0 ORDER BY total DESC", $pR);
    }
    $catLabels = array_column($cat, 'descripcion');
    $catData = array_map('intval', array_column($cat, 'total'));
    $sheet->fromArray(['Concepto', 'Total'], NULL, 'A6');
    $row = 7;
    foreach ($cat as $c) { $sheet->setCellValue('A' . $row, $c['descripcion'])->setCellValue('B' . $row, (int)$c['total']); $row++; }
    graficoBarras($sheet, $catLabels, $catData, ($tipo == 'acto_inseguro' ? 'Actos Inseguros' : 'Tipos de Accidente'), 'D6', 'L26', true);

    // Severidad / Edad
    if ($tipo == 'accidente') {
        $sevData = [0, 0, 0, 0]; $map = ['leve'=>0,'moderado'=>1,'grave'=>2,'fatal'=>3];
        foreach (q($pdo, "SELECT gravedad, COUNT(*) total FROM reportes r WHERE r.tipo='accidente'$fR GROUP BY gravedad", $pR) as $r) {
            if (isset($map[$r['gravedad']])) $sevData[$map[$r['gravedad']]] = (int)$r['total'];
        }
        graficoBarras($sheet, ['Leve','Moderado','Grave','Fatal'], $sevData, 'Severidad', 'N6', 'V26');
    } else {
        $edadData = [0,0,0,0,0];
        $sqlE = "SELECT CASE WHEN TIMESTAMPDIFF(YEAR,e.fecha_nacimiento,CURDATE())<25 THEN 0 WHEN TIMESTAMPDIFF(YEAR,e.fecha_nacimiento,CURDATE()) BETWEEN 25 AND 34 THEN 1 WHEN TIMESTAMPDIFF(YEAR,e.fecha_nacimiento,CURDATE()) BETWEEN 35 AND 44 THEN 2 WHEN TIMESTAMPDIFF(YEAR,e.fecha_nacimiento,CURDATE()) BETWEEN 45 AND 54 THEN 3 ELSE 4 END rango, COUNT(DISTINCT e.id) total FROM empleados e JOIN reportes r ON e.id=r.empleado_id WHERE e.fecha_nacimiento IS NOT NULL AND r.tipo='acto_inseguro'$fR GROUP BY rango";
        foreach (q($pdo, $sqlE, $pR) as $r) $edadData[$r['rango']] = (int)$r['total'];
        graficoBarras($sheet, ['< 25','25-34','35-44','45-54','55+'], $edadData, 'Distribución por Edad', 'N6', 'V26');
    }

    // Pareto
    if ($tipo == 'acto_inseguro') {
        $par = q($pdo, "SELECT a.descripcion, COUNT(r.id) total FROM actos_inseguros a LEFT JOIN reportes r ON a.id=r.acto_inseguro_id$fR AND r.tipo='acto_inseguro' WHERE a.activo=1 GROUP BY a.id HAVING total>0 ORDER BY total DESC LIMIT 10", $pR);
    } else {
        $par = q($pdo, "SELECT t.descripcion, COUNT(r.id) total FROM tipos_accidente t LEFT JOIN reportes r ON t.id=r.accidente_id$fR AND r.tipo='accidente' WHERE t.activo=1 GROUP BY t.id HAVING total>0 ORDER BY total DESC LIMIT 10", $pR);
    }
    $parLabels = array_column($par, 'descripcion');
    $parFrec = array_map('intval', array_column($par, 'total'));
    $tot = array_sum($parFrec); $acum = 0; $parAcum = [];
    foreach ($parFrec as $v) { $acum += $v; $parAcum[] = $tot > 0 ? round($acum / $tot * 100, 1) : 0; }
    graficoPareto($sheet, $parLabels, $parFrec, $parAcum, 'Diagrama de Pareto', 'A28', 'L48');

    // Top Departamentos
    $dep = q($pdo, "SELECT d.nombre, COUNT(r.id) total FROM departamentos d LEFT JOIN reportes r ON d.id=r.departamento_id$fR AND r.tipo=? GROUP BY d.id ORDER BY total DESC LIMIT 5", array_merge($pR, $pTipo));
    graficoBarras($sheet, array_column($dep, 'nombre'), array_map('intval', array_column($dep, 'total')), 'Top 5 Departamentos', 'N28', 'V48', true);

    foreach (range('A', 'V') as $c) $sheet->getColumnDimension($c)->setAutoSize(true);
}

/* ============================================================
 * HOJA: Enfermedades Crónicas
 * ============================================================ */
function hojaEnfermedades($spreadsheet, PDO $pdo, ?int $suc, string $nombreSuc, ?int $anio, ?int $num) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle('Enfermedades Crónicas');

    $fE = ''; $pE = [];
    if ($suc) { $fE .= " AND e.sucursal_id = ?"; $pE[] = $suc; }
    $fMes = ''; $pMes = [];
    if ($anio) { $fMes .= " AND YEAR(ee.fecha_registro) = ? AND MONTH(ee.fecha_registro) = ?"; $pMes[] = $anio; $pMes[] = $num; }

    $titulo = 'DASHBOARD ENFERMEDADES CRÓNICAS - ' . ($suc ? "Sucursal: $nombreSuc" : 'Todas las Sucursales');
    if ($anio) $titulo .= sprintf(' - %04d-%02d', $anio, $num);
    $sheet->setCellValue('A1', $titulo);
    $sheet->mergeCells('A1:V1');
    $sheet->getStyle('A1')->applyFromArray(['font'=>['bold'=>true,'size'=>14,'color'=>['rgb'=>'FFFFFF']],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'198754']],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);

    // KPIs
    $totalEnf = qcol($pdo, "SELECT COUNT(DISTINCT ee.empleado_id) FROM empleado_enfermedad ee JOIN empleados e ON ee.empleado_id=e.id WHERE e.activo=1$fE$fMes", array_merge($pE, $pMes));
    $activos = $suc ? qcol($pdo, "SELECT COUNT(*) FROM empleados e WHERE activo=1 AND e.sucursal_id=?", [$suc]) : qcol($pdo, "SELECT COUNT(*) FROM empleados WHERE activo=1");
    $prev = $activos > 0 ? round($totalEnf / $activos * 100, 1) : 0;
    foreach ([['Empleados c/ enfermedad', $totalEnf], ['Prevalencia %', $prev]] as $i => $k) {
        $col = chr(65 + $i);
        $sheet->setCellValue($col . '3', $k[0])->setCellValue($col . '4', $k[1]);
        $sheet->getStyle($col . '3:' . $col . '4')->applyFromArray(['borders'=>['outline'=>['borderStyle'=>Border::BORDER_THIN]],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
    }

    // Top 10 enfermedades
    $top = q($pdo, "SELECT ec.nombre, COUNT(ee.empleado_id) total FROM enfermedades_cronicas ec JOIN empleado_enfermedad ee ON ec.id=ee.enfermedad_id JOIN empleados e ON ee.empleado_id=e.id AND e.activo=1 WHERE ec.activo=1$fE$fMes GROUP BY ec.id HAVING total>0 ORDER BY total DESC LIMIT 10", array_merge($pE, $pMes));
    $sheet->fromArray(['Enfermedad', 'Empleados'], NULL, 'A6');
    $row = 7;
    foreach ($top as $t) { $sheet->setCellValue('A' . $row, $t['nombre'])->setCellValue('B' . $row, (int)$t['total']); $row++; }
    graficoBarras($sheet, array_column($top, 'nombre'), array_map('intval', array_column($top, 'total')), 'Top 10 Enfermedades', 'D6', 'L26', true, 'Empleados');

    // Por departamento
    $dep = q($pdo, "SELECT d.nombre, COUNT(DISTINCT ee.empleado_id) total FROM departamentos d LEFT JOIN empleados e ON e.departamento_id=d.id AND e.activo=1" . ($suc ? " AND e.sucursal_id=$suc" : "") . " LEFT JOIN empleado_enfermedad ee ON ee.empleado_id=e.id$fMes WHERE d.activo=1 GROUP BY d.id HAVING total>0 ORDER BY total DESC", $pMes);
    graficoBarras($sheet, array_column($dep, 'nombre'), array_map('intval', array_column($dep, 'total')), 'Enfermedades por Departamento', 'N6', 'V26', true, 'Empleados');

    // Por rango de edad
    $edadData = [0,0,0,0,0];
    $sqlEE = "SELECT CASE WHEN TIMESTAMPDIFF(YEAR,e.fecha_nacimiento,CURDATE())<25 THEN 0 WHEN TIMESTAMPDIFF(YEAR,e.fecha_nacimiento,CURDATE()) BETWEEN 25 AND 34 THEN 1 WHEN TIMESTAMPDIFF(YEAR,e.fecha_nacimiento,CURDATE()) BETWEEN 35 AND 44 THEN 2 WHEN TIMESTAMPDIFF(YEAR,e.fecha_nacimiento,CURDATE()) BETWEEN 45 AND 54 THEN 3 ELSE 4 END rango, COUNT(DISTINCT e.id) total FROM empleados e JOIN empleado_enfermedad ee ON ee.empleado_id=e.id WHERE e.activo=1 AND e.fecha_nacimiento IS NOT NULL$fE$fMes GROUP BY rango";
    foreach (q($pdo, $sqlEE, array_merge($pE, $pMes)) as $r) $edadData[$r['rango']] = (int)$r['total'];
    graficoBarras($sheet, ['< 25','25-34','35-44','45-54','55+'], $edadData, 'Enfermedades por Rango de Edad', 'A28', 'L48');

    foreach (range('A', 'V') as $c) $sheet->getColumnDimension($c)->setAutoSize(true);
}

/* ============================================================
 * PROGRAMA PRINCIPAL — genera solo la hoja del tipo seleccionado
 * ============================================================ */
$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

if ($tipo_filtro === 'enfermedad_cronica') {
    hojaEnfermedades($spreadsheet, $pdo, $sucursal_id, $nombreSucursal, $mesAnio, $mesNum);
} else {
    hojaReportes($spreadsheet, $pdo, $sucursal_id, $tipo_filtro, $nombreSucursal, $horas_hombre, $mesAnio, $mesNum);
}

$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="dashboard_syso_' . $tipo_filtro . '_' . date('Ymd_His') . '.xlsx"');
$writer->save('php://output');
exit;
