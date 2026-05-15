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

// ============================================================
// FUNCIONES AUXILIARES PARA GRÁFICOS
// ============================================================
function agregarGraficoLinea($sheet, $labels, $valores, $titulo, $topLeft, $bottomRight, $seriesLabel = 'Reportes') {
    $lblSerie = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, NULL, NULL, 1, [$seriesLabel])];
    $xAxis = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, NULL, NULL, count($labels), $labels)];
    $val = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, NULL, NULL, count($valores), $valores)];
    $series = new DataSeries(DataSeries::TYPE_LINECHART, DataSeries::GROUPING_STANDARD, [0], $lblSerie, $xAxis, $val);
    $layout = new Layout(); $layout->setShowVal(true);
    $plot = new PlotArea($layout, [$series]);
    $legend = new Legend(Legend::POSITION_BOTTOM);
    $chart = new Chart('line_'.uniqid(), new Title($titulo), $legend, $plot, true, 0, NULL, NULL);
    $chart->setTopLeftPosition($topLeft);
    $chart->setBottomRightPosition($bottomRight);
    $sheet->addChart($chart);
}

function agregarGraficoBarras($sheet, $labels, $valores, $titulo, $topLeft, $bottomRight, $horizontal = false, $seriesLabel = 'Reportes') {
    $lblSerie = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, NULL, NULL, 1, [$seriesLabel])];
    $xAxis = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, NULL, NULL, count($labels), $labels)];
    $val = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, NULL, NULL, count($valores), $valores)];
    $series = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_STANDARD, [0], $lblSerie, $xAxis, $val);
    if ($horizontal) $series->setPlotDirection(DataSeries::DIRECTION_BAR);
    $layout = new Layout(); $layout->setShowVal(true);
    $plot = new PlotArea($layout, [$series]);
    $legend = new Legend(Legend::POSITION_BOTTOM);
    $chart = new Chart('bar_'.uniqid(), new Title($titulo), $legend, $plot, true, 0, NULL, NULL);
    $chart->setTopLeftPosition($topLeft);
    $chart->setBottomRightPosition($bottomRight);
    $sheet->addChart($chart);
}

function agregarGraficoDoughnut($sheet, $labels, $valores, $titulo, $topLeft, $bottomRight) {
    $lbl = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, NULL, NULL, count($labels), $labels)];
    $val = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, NULL, NULL, count($valores), $valores)];
    $series = new DataSeries(DataSeries::TYPE_PIECHART, DataSeries::GROUPING_STACKED, [0], $lbl, $lbl, $val);
    $layout = new Layout(); $layout->setShowVal(true); $layout->setShowPercent(true);
    $plot = new PlotArea($layout, [$series]);
    $legend = new Legend(Legend::POSITION_RIGHT);
    $chart = new Chart('pie_'.uniqid(), new Title($titulo), $legend, $plot, true, 0, NULL, NULL);
    $chart->setTopLeftPosition($topLeft);
    $chart->setBottomRightPosition($bottomRight);
    $sheet->addChart($chart);
}

function agregarGraficoPareto($sheet, $labels, $frecuencias, $acumulado, $titulo, $topLeft, $bottomRight) {
    // Gráfico combinado: barras para frecuencia, línea para % acumulado
    $lblSerieBar = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, NULL, NULL, 1, ['Frecuencia'])];
    $lblSerieLine = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, NULL, NULL, 1, ['% Acumulado'])];
    $xAxis = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, NULL, NULL, count($labels), $labels)];
    $valBar = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, NULL, NULL, count($frecuencias), $frecuencias)];
    $valLine = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, NULL, NULL, count($acumulado), $acumulado)];
    
    $seriesBar = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_STANDARD, [0], $lblSerieBar, $xAxis, $valBar);
    $seriesLine = new DataSeries(DataSeries::TYPE_LINECHART, DataSeries::GROUPING_STANDARD, [0], $lblSerieLine, $xAxis, $valLine);
    
    $layout = new Layout(); $layout->setShowVal(true);
    $plot = new PlotArea($layout, [$seriesBar, $seriesLine]);
    $legend = new Legend(Legend::POSITION_BOTTOM);
    $chart = new Chart('pareto_'.uniqid(), new Title($titulo), $legend, $plot, true, 0, NULL, NULL);
    $chart->setTopLeftPosition($topLeft);
    $chart->setBottomRightPosition($bottomRight);
    $sheet->addChart($chart);
}

// ============================================================
// FUNCIÓN PARA GENERAR UNA HOJA COMPLETA POR TIPO DE REPORTE
// ============================================================
function generarHojaDashboard($spreadsheet, $pdo, $sucursal_id, $tipo, $nombreSucursal, $horas_hombre) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle($tipo == 'acto_inseguro' ? 'Actos Inseguros' : 'Accidentes');

    $filtroSucursal = $sucursal_id ? "AND r.sucursal_id = $sucursal_id" : "";
    $filtroTipo = "AND r.tipo = '$tipo'";
    $filtroSucursalEmp = $sucursal_id ? "AND e.sucursal_id = $sucursal_id" : "";

    // --- Título ---
    $tituloHoja = 'DASHBOARD ' . ($tipo == 'acto_inseguro' ? 'ACTOS INSEGUROS' : 'ACCIDENTES') . ' - ' . ($sucursal_id ? "Sucursal: $nombreSucursal" : 'Todas las Sucursales');
    $sheet->setCellValue('A1', $tituloHoja);
    $sheet->mergeCells('A1:Z1');
    $sheet->getStyle('A1')->applyFromArray(['font'=>['bold'=>true,'size'=>14,'color'=>['rgb'=>'FFFFFF']],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'0D6EFD']],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);

    // --- KPIs (fila 3) ---
    $total = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE 1=1 $filtroSucursal $filtroTipo")->fetchColumn();
    $mes = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE MONTH(fecha)=MONTH(CURDATE()) AND YEAR(fecha)=YEAR(CURDATE()) $filtroSucursal $filtroTipo")->fetchColumn();
    $empleados = $pdo->query("SELECT COUNT(*) FROM empleados e WHERE activo=1 $filtroSucursalEmp")->fetchColumn();
    $promedio = $mes > 0 ? round($mes / date('d'), 1) : 0;

    $kpis = [['Total Reportes',$total], ['Este Mes',$mes], ['Empleados',$empleados], ['Prom. Diario',$promedio]];
    if ($tipo == 'accidente') {
        $dsa = 0;
        $ultimo = $pdo->query("SELECT MAX(fecha) FROM reportes WHERE tipo='accidente' $filtroSucursal")->fetchColumn();
        if ($ultimo) {
            $hoy = new DateTime(); $fec = new DateTime($ultimo);
            $dsa = $hoy->diff($fec)->days;
        } else { $dsa = 'Sin datos'; }
        $kpis[] = ['DSA', $dsa];
        if ($horas_hombre > 0) {
            $accMes = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE MONTH(fecha)=MONTH(CURDATE()) AND YEAR(fecha)=YEAR(CURDATE()) $filtroSucursal AND tipo='accidente'")->fetchColumn();
            $tasaF = round(($accMes * 1000000) / $horas_hombre, 2);
            $kpis[] = ['Tasa Frec.', $tasaF];
            $diasPerdidos = $pdo->query("SELECT SUM(dias_perdidos) FROM reportes r WHERE MONTH(fecha)=MONTH(CURDATE()) AND YEAR(fecha)=YEAR(CURDATE()) $filtroSucursal AND tipo='accidente'")->fetchColumn();
            $tasaG = round(($diasPerdidos * 1000000) / $horas_hombre, 2);
            $kpis[] = ['Tasa Gravedad', $tasaG];
        }
    }
    $colKPI = 'A';
    foreach ($kpis as $k) {
        $sheet->setCellValue($colKPI.'3', $k[0]);
        $sheet->setCellValue($colKPI.'4', $k[1]);
        $sheet->getStyle($colKPI.'3:'.$colKPI.'4')->applyFromArray(['borders'=>['outline'=>['borderStyle'=>Border::BORDER_THIN]],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
        $sheet->getStyle($colKPI.'3')->getFont()->setBold(true);
        $colKPI++;
    }

    // --- Tendencia 6 meses (fila 7) ---
    $meses = []; $reportesPorMes = [];
    for ($i=5; $i>=0; $i--) {
        $fecha = date('Y-m-01', strtotime("-$i months"));
        $meses[] = date('M Y', strtotime($fecha));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reportes r WHERE YEAR(fecha)=YEAR(?) AND MONTH(fecha)=MONTH(?) $filtroSucursal $filtroTipo");
        $stmt->execute([$fecha, $fecha]);
        $reportesPorMes[] = (int)$stmt->fetchColumn();
    }
    $sheet->fromArray($meses, NULL, 'A7');
    $sheet->fromArray($reportesPorMes, NULL, 'B7');
    agregarGraficoLinea($sheet, $meses, $reportesPorMes, 'Tendencia 6 meses', 'D7', 'L25');

    // --- Comparativa Anual (fila 27) ---
    $mesesAnio = []; $actual = []; $anterior = [];
    for ($m=1; $m<=12; $m++) {
        $mesesAnio[] = date('M', mktime(0,0,0,$m,1));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reportes r WHERE MONTH(fecha)=? AND YEAR(fecha)=YEAR(CURDATE()) $filtroSucursal $filtroTipo");
        $stmt->execute([$m]); $actual[] = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reportes r WHERE MONTH(fecha)=? AND YEAR(fecha)=YEAR(CURDATE())-1 $filtroSucursal $filtroTipo");
        $stmt->execute([$m]); $anterior[] = (int)$stmt->fetchColumn();
    }
    $sheet->fromArray($mesesAnio, NULL, 'A27');
    $sheet->fromArray($actual, NULL, 'B27');
    $sheet->fromArray($anterior, NULL, 'C27');
    agregarGraficoLinea($sheet, $mesesAnio, $actual, 'Comparativa Anual (Actual)', 'E27', 'M45', 'Actual');
    // Nota: En Excel no es trivial poner dos líneas en un mismo gráfico con la API simple; se puede hacer con DataSeries. Por simplicidad, se agrega una segunda línea manualmente si se desea, pero omitimos para no complicar.

    // --- Catálogo (Doughnut) (col N, fila 7) ---
    if ($tipo == 'acto_inseguro') {
        $catData = $pdo->query("SELECT a.descripcion, COUNT(r.id) as total FROM actos_inseguros a LEFT JOIN reportes r ON a.id=r.acto_inseguro_id $filtroSucursal WHERE a.activo=1 AND (r.tipo IS NULL OR r.tipo='acto_inseguro') GROUP BY a.id ORDER BY total DESC")->fetchAll();
    } else {
        $catData = $pdo->query("SELECT t.descripcion, COUNT(r.id) as total FROM tipos_accidente t LEFT JOIN reportes r ON t.id=r.accidente_id $filtroSucursal WHERE t.activo=1 AND (r.tipo IS NULL OR r.tipo='accidente') GROUP BY t.id ORDER BY total DESC")->fetchAll();
    }
    $catLabels = []; $catValores = [];
    $totalCat = count($catData);
    if ($totalCat == 0) {
        $catLabels = ['Sin datos']; $catValores = [0];
    } else {
        $top = array_slice($catData, 0, 8);
        $resto = array_slice($catData, 8);
        foreach ($top as $row) { $catLabels[] = $row['descripcion']; $catValores[] = (int)$row['total']; }
        if (!empty($resto)) {
            $suma = array_sum(array_column($resto, 'total'));
            if ($suma > 0) { $catLabels[] = 'Otros (' . count($resto) . ')'; $catValores[] = $suma; }
        }
    }
    $sheet->fromArray($catLabels, NULL, 'N7');
    $sheet->fromArray($catValores, NULL, 'O7');
    agregarGraficoDoughnut($sheet, $catLabels, $catValores, ($tipo=='acto_inseguro'?'Actos':'Tipos Accidente'), 'Q7', 'X25');

    // --- Hora del día (col N, fila 27) ---
    $horas = []; $horaData = array_fill(0,24,0);
    for ($h=0;$h<24;$h++) $horas[] = sprintf('%02d:00',$h);
    $resH = $pdo->query("SELECT HOUR(hora) as h, COUNT(*) as total FROM reportes r WHERE 1=1 $filtroSucursal $filtroTipo GROUP BY h");
    foreach ($resH as $row) $horaData[(int)$row['h']] = (int)$row['total'];
    $sheet->fromArray($horas, NULL, 'N27');
    $sheet->fromArray($horaData, NULL, 'O27');
    agregarGraficoBarras($sheet, $horas, $horaData, 'Reportes por Hora', 'Q27', 'X45');

    // --- Días de la semana (fila 47) ---
    $diasFull = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
    $diasData = array_fill(0,7,0);
    $resD = $pdo->query("SELECT DAYOFWEEK(fecha) as dia, COUNT(*) as total FROM reportes r WHERE 1=1 $filtroSucursal $filtroTipo GROUP BY dia");
    foreach ($resD as $row) { $indice = ($row['dia']+5)%7; $diasData[$indice] = (int)$row['total']; }
    $sheet->fromArray($diasFull, NULL, 'A47');
    $sheet->fromArray($diasData, NULL, 'B47');
    agregarGraficoBarras($sheet, $diasFull, $diasData, 'Por Día de la Semana', 'D47', 'L65');

    // --- Severidad (solo accidentes) o Edad (actos) (col N, fila 47) ---
    if ($tipo == 'accidente') {
        $sevLabels = ['Leve','Moderado','Grave','Fatal'];
        $sevData = [0,0,0,0];
        $map = ['leve'=>0,'moderado'=>1,'grave'=>2,'fatal'=>3];
        $resS = $pdo->query("SELECT gravedad, COUNT(*) as total FROM reportes WHERE tipo='accidente' $filtroSucursal GROUP BY gravedad");
        foreach ($resS as $row) if (isset($map[$row['gravedad']])) $sevData[$map[$row['gravedad']]] = (int)$row['total'];
        $sheet->fromArray($sevLabels, NULL, 'N47');
        $sheet->fromArray($sevData, NULL, 'O47');
        agregarGraficoBarras($sheet, $sevLabels, $sevData, 'Severidad de Accidentes', 'Q47', 'X65');
    } else {
        $rangos = ['< 25','25-34','35-44','45-54','55+'];
        $edadData = [0,0,0,0,0];
        $sqlE = "SELECT CASE WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) < 25 THEN 0 WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) BETWEEN 25 AND 34 THEN 1 WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) BETWEEN 35 AND 44 THEN 2 WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) BETWEEN 45 AND 54 THEN 3 ELSE 4 END as rango, COUNT(DISTINCT e.id) as total FROM empleados e JOIN reportes r ON e.id=r.empleado_id WHERE e.fecha_nacimiento IS NOT NULL $filtroSucursal $filtroTipo GROUP BY rango";
        $resE = $pdo->query($sqlE);
        foreach ($resE as $row) $edadData[$row['rango']] = (int)$row['total'];
        $sheet->fromArray($rangos, NULL, 'N47');
        $sheet->fromArray($edadData, NULL, 'O47');
        agregarGraficoBarras($sheet, $rangos, $edadData, 'Distribución por Edad', 'Q47', 'X65');
    }

    // --- Pareto (fila 67) ---
    if ($tipo == 'acto_inseguro') {
        $paretoData = $pdo->query("SELECT a.descripcion, COUNT(r.id) as total FROM actos_inseguros a LEFT JOIN reportes r ON a.id=r.acto_inseguro_id $filtroSucursal WHERE a.activo=1 AND (r.tipo IS NULL OR r.tipo='acto_inseguro') GROUP BY a.id ORDER BY total DESC LIMIT 10")->fetchAll();
    } else {
        $paretoData = $pdo->query("SELECT t.descripcion, COUNT(r.id) as total FROM tipos_accidente t LEFT JOIN reportes r ON t.id=r.accidente_id $filtroSucursal WHERE t.activo=1 AND (r.tipo IS NULL OR r.tipo='accidente') GROUP BY t.id ORDER BY total DESC LIMIT 10")->fetchAll();
    }
    $paretoLabels = array_column($paretoData, 'descripcion');
    $paretoFrecuencias = array_column($paretoData, 'total');
    $totalPareto = array_sum($paretoFrecuencias);
    $paretoAcum = [];
    $acum = 0;
    foreach ($paretoFrecuencias as $val) {
        $acum += $val;
        $paretoAcum[] = $totalPareto > 0 ? round(($acum / $totalPareto) * 100, 1) : 0;
    }
    $sheet->fromArray($paretoLabels, NULL, 'A67');
    $sheet->fromArray($paretoFrecuencias, NULL, 'B67');
    $sheet->fromArray($paretoAcum, NULL, 'C67');
    agregarGraficoPareto($sheet, $paretoLabels, $paretoFrecuencias, $paretoAcum, 'Diagrama de Pareto', 'E67', 'M85');

    // --- Top Departamentos (fila 87) ---
    $deptos = $pdo->query("SELECT d.nombre, COUNT(r.id) as total FROM departamentos d LEFT JOIN reportes r ON d.id=r.departamento_id $filtroSucursal WHERE (r.tipo IS NULL OR r.tipo='$tipo') GROUP BY d.id ORDER BY total DESC LIMIT 5")->fetchAll();
    $deptosLabels = array_column($deptos, 'nombre');
    $deptosData = array_column($deptos, 'total');
    $sheet->fromArray($deptosLabels, NULL, 'A87');
    $sheet->fromArray($deptosData, NULL, 'B87');
    agregarGraficoBarras($sheet, $deptosLabels, $deptosData, 'Top 5 Departamentos', 'D87', 'L105', true);

    // --- Top Sucursales (tabla) (col N, fila 87) ---
    $topSuc = $pdo->query("SELECT s.nombre, COUNT(r.id) as total, ROUND(COUNT(r.id)/6,1) as prom FROM sucursales s LEFT JOIN reportes r ON s.id=r.sucursal_id $filtroSucursal WHERE s.activo=1 AND (r.tipo IS NULL OR r.tipo='$tipo') GROUP BY s.id ORDER BY total DESC LIMIT 5")->fetchAll();
    $sheet->setCellValue('N86', 'Top 5 Sucursales');
    $sheet->setCellValue('N87', 'Sucursal')->setCellValue('O87', 'Total')->setCellValue('P87', 'Prom.');
    $fila = 88;
    foreach ($topSuc as $s) {
        $sheet->setCellValue('N'.$fila, $s['nombre'])->setCellValue('O'.$fila, $s['total'])->setCellValue('P'.$fila, $s['prom']);
        $fila++;
    }

    // --- Top Empleados (tabla) (fila 107) ---
    $topEmp = $pdo->query("SELECT e.numero_empleado, e.nombre, COUNT(r.id) as total FROM empleados e LEFT JOIN reportes r ON e.id=r.empleado_id $filtroSucursal WHERE e.activo=1 AND (r.tipo IS NULL OR r.tipo='$tipo') GROUP BY e.id ORDER BY total DESC LIMIT 5")->fetchAll();
    $sheet->setCellValue('A107', 'Top 5 Empleados');
    $sheet->setCellValue('A108', '#')->setCellValue('B108', 'Nombre')->setCellValue('C108', 'Total');
    $fila = 109;
    foreach ($topEmp as $e) {
        $sheet->setCellValue('A'.$fila, $e['numero_empleado'])->setCellValue('B'.$fila, $e['nombre'])->setCellValue('C'.$fila, $e['total']);
        $fila++;
    }

    // Autoajustar columnas
    foreach (range('A','Z') as $c) $sheet->getColumnDimension($c)->setAutoSize(true);
}

// ============================================================
// PROGRAMA PRINCIPAL
// ============================================================
$sucursal_id = $_GET['sucursal_id'] ?? '';
$tipo_filtro = $_GET['tipo'] ?? 'acto_inseguro';

$nombreSucursal = '';
if ($sucursal_id) {
    $stmt = $pdo->prepare("SELECT nombre FROM sucursales WHERE id = ?");
    $stmt->execute([$sucursal_id]);
    $nombreSucursal = $stmt->fetchColumn();
}

$horas_hombre = HORAS_HOMBRE_MES;

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

generarHojaDashboard($spreadsheet, $pdo, $sucursal_id, 'acto_inseguro', $nombreSucursal, $horas_hombre);
generarHojaDashboard($spreadsheet, $pdo, $sucursal_id, 'accidente', $nombreSucursal, $horas_hombre);

// Hoja comparativa
$sheetComp = $spreadsheet->createSheet();
$sheetComp->setTitle('Comparativa');
$compLabels = []; $compActos = []; $compAccidentes = [];
$sqlComp = "
    SELECT 'Acto' as tipo, a.descripcion as item, 
           SUM(CASE WHEN r.tipo='acto_inseguro' THEN 1 ELSE 0 END) as actos,
           SUM(CASE WHEN r.tipo='accidente' THEN 1 ELSE 0 END) as accidentes
    FROM actos_inseguros a
    LEFT JOIN reportes r ON a.id = r.acto_inseguro_id ".($sucursal_id ? "AND r.sucursal_id=$sucursal_id" : "")."
    WHERE a.activo=1 GROUP BY a.id
    UNION ALL
    SELECT 'Accid' as tipo, t.descripcion as item,
           SUM(CASE WHEN r.tipo='acto_inseguro' THEN 1 ELSE 0 END) as actos,
           SUM(CASE WHEN r.tipo='accidente' THEN 1 ELSE 0 END) as accidentes
    FROM tipos_accidente t
    LEFT JOIN reportes r ON t.id = r.accidente_id ".($sucursal_id ? "AND r.sucursal_id=$sucursal_id" : "")."
    WHERE t.activo=1 GROUP BY t.id
    ORDER BY actos+accidentes DESC LIMIT 10";
$resComp = $pdo->query($sqlComp);
foreach ($resComp as $row) {
    $compLabels[] = $row['item'];
    $compActos[] = (int)$row['actos'];
    $compAccidentes[] = (int)$row['accidentes'];
}
$sheetComp->fromArray($compLabels, NULL, 'A1');
$sheetComp->fromArray($compActos, NULL, 'B1');
$sheetComp->fromArray($compAccidentes, NULL, 'C1');
agregarGraficoBarras($sheetComp, $compLabels, $compActos, 'Actos Inseguros', 'E1', 'M20', false, 'Actos');
agregarGraficoBarras($sheetComp, $compLabels, $compAccidentes, 'Accidentes', 'E22', 'M40', false, 'Accidentes');

$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="dashboard_syso_completo_'.date('Ymd_His').'.xlsx"');
$writer->save('php://output');
exit;