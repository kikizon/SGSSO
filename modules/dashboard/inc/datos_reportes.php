<?php
/**
 * Datos para actos inseguros / accidentes.
 *
 * El filtro por mes ($filtroMesReportes) se aplica a estadísticas "instantáneas"
 * (totales, rueda, pareto, severidad, horas, días, departamentos, top emp/suc, edad).
 * NO se aplica a las gráficas de tendencia (6 meses) ni comparativa anual (12 meses),
 * porque son series de tiempo y filtrarlas a un mes no tiene sentido.
 */

// ============================================================
// KPIs
// ============================================================
$totalReportes = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE 1=1 $filtroSucursalReportes $filtroTipo $filtroMesReportes")->fetchColumn();
$reportesMes   = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE MONTH(fecha)=MONTH(CURDATE()) AND YEAR(fecha)=YEAR(CURDATE()) $filtroSucursalReportes $filtroTipo")->fetchColumn();
$totalEmpleados = $pdo->query("SELECT COUNT(*) FROM empleados e WHERE activo=1 $filtroSucursalEmpleados")->fetchColumn();

// DSA (días sin accidentes): siempre histórico, no se filtra por mes
$dsa = 0;
$tasaFrecuencia = 0;
$tasaGravedad = 0;
$costoTotalAtenciones = 0;
$porcentajeST7 = 0;
$promedioDiasIncapacidad = 0;
$costoPromedioAtencion = 0;
$tasaIncapacidadProlongada = 0;

if ($tipo_filtro == 'accidente') {
    $ultimo = $pdo->query("SELECT MAX(fecha) FROM reportes r WHERE tipo='accidente' $filtroSucursalReportes")->fetchColumn();
    if ($ultimo) {
        $hoy = new DateTime();
        $fechaUltimo = new DateTime($ultimo);
        $dsa = $hoy->diff($fechaUltimo)->days;
    } else {
        $dsa = 'Sin datos';
    }

    if ($horas_hombre > 0) {
        $accidentesMes = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE MONTH(fecha)=MONTH(CURDATE()) AND YEAR(fecha)=YEAR(CURDATE()) $filtroSucursalReportes AND tipo='accidente'")->fetchColumn();
        $tasaFrecuencia = round(($accidentesMes * 1000000) / $horas_hombre, 2);
        $diasPerdidosMes = $pdo->query("SELECT SUM(dias_perdidos) FROM reportes r WHERE MONTH(fecha)=MONTH(CURDATE()) AND YEAR(fecha)=YEAR(CURDATE()) $filtroSucursalReportes AND tipo='accidente'")->fetchColumn();
        $tasaGravedad = round((($diasPerdidosMes ?: 0) * 1000000) / $horas_hombre, 2);
    }

    // Costos / incapacidad (se aplica el filtro por mes)
    $costoTotalParticulares = $pdo->query("SELECT SUM(costo_atencion) FROM reportes r WHERE tipo='accidente' AND costo_atencion IS NOT NULL $filtroSucursalReportes $filtroMesReportes")->fetchColumn() ?: 0;
    $totalAtencionesParticulares = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE tipo='accidente' AND costo_atencion IS NOT NULL $filtroSucursalReportes $filtroMesReportes")->fetchColumn();
    $totalAtencionesST7 = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE tipo='accidente' AND st7=1 $filtroSucursalReportes $filtroMesReportes")->fetchColumn();
    $totalAtencionesIMSS = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE tipo='accidente' AND atencion_medica_id IS NOT NULL AND (SELECT descripcion FROM atenciones_medicas WHERE id = r.atencion_medica_id) LIKE '%IMSS%' $filtroSucursalReportes $filtroMesReportes")->fetchColumn();

    // FIX: estas dos variables antes no se asignaban -> los KPI mostraban $0
    $costoTotalAtenciones = $costoTotalParticulares;
    $costoPromedioAtencion = ($totalAtencionesParticulares > 0) ? round($costoTotalParticulares / $totalAtencionesParticulares, 2) : 0;

    $porcentajeST7 = ($totalAtencionesIMSS > 0) ? round(($totalAtencionesST7 / $totalAtencionesIMSS) * 100, 1) : 0;

    $totalAccidentesConIncap = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE tipo='accidente' AND dias_perdidos > 0 $filtroSucursalReportes $filtroMesReportes")->fetchColumn();
    $sumaDiasIncap = $pdo->query("SELECT SUM(dias_perdidos) FROM reportes r WHERE tipo='accidente' $filtroSucursalReportes $filtroMesReportes")->fetchColumn() ?: 0;
    $promedioDiasIncapacidad = ($totalAccidentesConIncap > 0) ? round($sumaDiasIncap / $totalAccidentesConIncap, 1) : 0;

    $accidentesProlongados = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE tipo='accidente' AND dias_perdidos > 30 $filtroSucursalReportes $filtroMesReportes")->fetchColumn();
    $totalAccidentes = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE tipo='accidente' $filtroSucursalReportes $filtroMesReportes")->fetchColumn();
    $tasaIncapacidadProlongada = ($totalAccidentes > 0) ? round(($accidentesProlongados / $totalAccidentes) * 100, 1) : 0;
}

// ============================================================
// Tendencia últimos 6 meses (sin filtro por mes)
// ============================================================
$meses = []; $reportesPorMes = [];
for ($i = 5; $i >= 0; $i--) {
    $fecha = date('Y-m-01', strtotime("-$i months"));
    $meses[] = date('M Y', strtotime($fecha));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reportes r WHERE YEAR(fecha)=YEAR(?) AND MONTH(fecha)=MONTH(?) $filtroSucursalReportes $filtroTipo");
    $stmt->execute([$fecha, $fecha]);
    $reportesPorMes[] = (int) $stmt->fetchColumn();
}

// ============================================================
// Comparativa anual (sin filtro por mes)
// ============================================================
$mesesAnio = []; $actualAnio = []; $anteriorAnio = [];
for ($m = 1; $m <= 12; $m++) {
    $mesesAnio[] = date('M', mktime(0, 0, 0, $m, 1));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reportes r WHERE MONTH(fecha)=? AND YEAR(fecha)=YEAR(CURDATE()) $filtroSucursalReportes $filtroTipo");
    $stmt->execute([$m]); $actualAnio[] = (int) $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reportes r WHERE MONTH(fecha)=? AND YEAR(fecha)=YEAR(CURDATE())-1 $filtroSucursalReportes $filtroTipo");
    $stmt->execute([$m]); $anteriorAnio[] = (int) $stmt->fetchColumn();
}

// ============================================================
// Rueda / Catálogo — SOLO conceptos con incidencia
// ============================================================
if ($tipo_filtro == 'acto_inseguro') {
    $sql = "SELECT a.descripcion, COUNT(r.id) as total
            FROM actos_inseguros a
            LEFT JOIN reportes r ON a.id = r.acto_inseguro_id $filtroSucursalReportes $filtroMesReportes AND r.tipo = 'acto_inseguro'
            WHERE a.activo = 1
            GROUP BY a.id HAVING total > 0 ORDER BY total DESC";
} else {
    $sql = "SELECT t.descripcion, COUNT(r.id) as total
            FROM tipos_accidente t
            LEFT JOIN reportes r ON t.id = r.accidente_id $filtroSucursalReportes $filtroMesReportes AND r.tipo = 'accidente'
            WHERE t.activo = 1
            GROUP BY t.id HAVING total > 0 ORDER BY total DESC";
}
$catalogoData = $pdo->query($sql)->fetchAll();

$catalogoLabels = []; $catalogoValores = [];
if (empty($catalogoData)) {
    $catalogoLabels = ['Sin datos']; $catalogoValores = [0];
} else {
    $top = array_slice($catalogoData, 0, 8);
    $resto = array_slice($catalogoData, 8);
    foreach ($top as $row) { $catalogoLabels[] = $row['descripcion']; $catalogoValores[] = (int) $row['total']; }
    if (!empty($resto)) {
        $sumaOtros = array_sum(array_column($resto, 'total'));
        if ($sumaOtros > 0) { $catalogoLabels[] = 'Otros (' . count($resto) . ')'; $catalogoValores[] = $sumaOtros; }
    }
}

// ============================================================
// Severidad (accidentes)
// ============================================================
$severidadLabels = ['Leve', 'Moderado', 'Grave', 'Fatal'];
$severidadData = [0, 0, 0, 0];
if ($tipo_filtro == 'accidente') {
    $res = $pdo->query("SELECT gravedad, COUNT(*) as total FROM reportes r WHERE tipo='accidente' $filtroSucursalReportes $filtroMesReportes GROUP BY gravedad");
    $map = ['leve' => 0, 'moderado' => 1, 'grave' => 2, 'fatal' => 3];
    foreach ($res as $row) { if (isset($map[$row['gravedad']])) $severidadData[$map[$row['gravedad']]] = (int) $row['total']; }
}

// ============================================================
// Hora del día
// ============================================================
$horasLabels = [];
for ($h = 0; $h < 24; $h++) $horasLabels[] = sprintf('%02d:00', $h);
$horasData = array_fill(0, 24, 0);
$res = $pdo->query("SELECT HOUR(hora) as h, COUNT(*) as total FROM reportes r WHERE 1=1 $filtroSucursalReportes $filtroTipo $filtroMesReportes GROUP BY h");
foreach ($res as $row) $horasData[(int) $row['h']] = (int) $row['total'];

// ============================================================
// Día de la semana
// ============================================================
$diasSemanaFull = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$reportesPorDia = array_fill(0, 7, 0);
$res = $pdo->query("SELECT DAYOFWEEK(fecha) as dia, COUNT(*) as total FROM reportes r WHERE 1=1 $filtroSucursalReportes $filtroTipo $filtroMesReportes GROUP BY dia");
foreach ($res as $row) { $indice = ($row['dia'] + 5) % 7; $reportesPorDia[$indice] = (int) $row['total']; }

// ============================================================
// Pareto — SOLO conceptos con incidencia (top 10)
// ============================================================
if ($tipo_filtro == 'acto_inseguro') {
    $sqlPareto = "SELECT a.descripcion, COUNT(r.id) as total
                  FROM actos_inseguros a
                  LEFT JOIN reportes r ON a.id = r.acto_inseguro_id $filtroSucursalReportes $filtroMesReportes AND r.tipo = 'acto_inseguro'
                  WHERE a.activo = 1 GROUP BY a.id HAVING total > 0 ORDER BY total DESC LIMIT 10";
} else {
    $sqlPareto = "SELECT t.descripcion, COUNT(r.id) as total
                  FROM tipos_accidente t
                  LEFT JOIN reportes r ON t.id = r.accidente_id $filtroSucursalReportes $filtroMesReportes AND r.tipo = 'accidente'
                  WHERE t.activo = 1 GROUP BY t.id HAVING total > 0 ORDER BY total DESC LIMIT 10";
}
$paretoLabels = []; $paretoData = [];
foreach ($pdo->query($sqlPareto)->fetchAll() as $row) { $paretoLabels[] = $row['descripcion']; $paretoData[] = (int) $row['total']; }
$totalPareto = array_sum($paretoData);
$paretoAcumulado = [];
if ($totalPareto > 0) {
    $acum = 0;
    foreach ($paretoData as $v) { $acum += $v; $paretoAcumulado[] = round(($acum / $totalPareto) * 100, 1); }
} else {
    $paretoAcumulado = array_fill(0, count($paretoData), 0);
}
if (empty($paretoLabels)) { $paretoLabels = ['Sin datos']; $paretoData = [0]; $paretoAcumulado = [0]; }

// ============================================================
// Top 5 Departamentos (mes en el ON para no romper el LEFT JOIN)
// ============================================================
$deptos = $pdo->query("SELECT d.nombre, COUNT(r.id) as total
                       FROM departamentos d
                       LEFT JOIN reportes r ON d.id = r.departamento_id $filtroSucursalReportes $filtroMesReportes AND r.tipo = '$tipo_filtro'
                       GROUP BY d.id ORDER BY total DESC LIMIT 5")->fetchAll();
$deptosLabels = array_column($deptos, 'nombre');
$deptosData = array_column($deptos, 'total');

// ============================================================
// Top 5 Sucursales
// ============================================================
$topSuc = $pdo->query("SELECT s.nombre, COUNT(r.id) as total, ROUND(COUNT(r.id)/6,1) as prom
                       FROM sucursales s
                       LEFT JOIN reportes r ON s.id = r.sucursal_id $filtroSucursalReportes $filtroMesReportes AND r.tipo = '$tipo_filtro'
                       WHERE s.activo = 1 GROUP BY s.id ORDER BY total DESC LIMIT 5")->fetchAll();

// ============================================================
// Top 5 Empleados
// ============================================================
$topEmp = $pdo->query("SELECT e.numero_empleado, e.nombre, COUNT(r.id) as total
                       FROM empleados e
                       LEFT JOIN reportes r ON e.id = r.empleado_id $filtroSucursalReportes $filtroMesReportes AND r.tipo = '$tipo_filtro'
                       WHERE e.activo = 1 GROUP BY e.id ORDER BY total DESC LIMIT 5")->fetchAll();

// ============================================================
// Distribución por edad (actos)
// ============================================================
$rangosEdad = ['< 25', '25-34', '35-44', '45-54', '55+'];
$conteoEdad = [0, 0, 0, 0, 0];
$sqlEdad = "SELECT CASE
                WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) < 25 THEN 0
                WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) BETWEEN 25 AND 34 THEN 1
                WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) BETWEEN 35 AND 44 THEN 2
                WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) BETWEEN 45 AND 54 THEN 3
                ELSE 4 END as rango, COUNT(DISTINCT e.id) as total
            FROM empleados e
            JOIN reportes r ON e.id = r.empleado_id
            WHERE e.fecha_nacimiento IS NOT NULL $filtroSucursalReportes $filtroTipo $filtroMesReportes
            GROUP BY rango";
foreach ($pdo->query($sqlEdad) as $row) $conteoEdad[$row['rango']] = (int) $row['total'];

// ============================================================
// Títulos / ids de gráficos
// ============================================================
$tituloCatalogo = ($tipo_filtro == 'acto_inseguro') ? 'Actos Inseguros' : 'Tipos de Accidente';
$tituloSeveridadEdad = ($tipo_filtro == 'accidente') ? 'Severidad de Accidentes' : 'Distribución por Edad';
$chartIdSeveridadEdad = ($tipo_filtro == 'accidente') ? 'severidadChart' : 'edadChart';
