<?php
require_once 'verificar_token.php';
$tokenData = verificarToken($pdo);

// Filtros recibidos por GET
$sucursal_id = $_GET['sucursal_id'] ?? '';
$tipo_filtro = $_GET['tipo'] ?? 'acto_inseguro';
if (!in_array($tipo_filtro, ['acto_inseguro', 'accidente'])) {
    $tipo_filtro = 'acto_inseguro';
}

// Construir filtros SQL
$filtroSucursalEmp = $sucursal_id ? "AND e.sucursal_id = " . intval($sucursal_id) : "";
$filtroSucursalRep = $sucursal_id ? "AND r.sucursal_id = " . intval($sucursal_id) : "";
$filtroTipo = "AND r.tipo = '$tipo_filtro'";

// Obtener horas hombre desde configuración
$stmt = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'horas_hombre_mes'");
$horas_hombre = (int)($stmt->fetchColumn() ?: 0);

// --- KPIs ---
$totalReportes = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE 1=1 $filtroSucursalRep $filtroTipo")->fetchColumn();
$reportesMes = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE MONTH(fecha)=MONTH(CURDATE()) AND YEAR(fecha)=YEAR(CURDATE()) $filtroSucursalRep $filtroTipo")->fetchColumn();
$totalEmpleados = $pdo->query("SELECT COUNT(*) FROM empleados e WHERE activo=1 $filtroSucursalEmp")->fetchColumn();
$promedioDiario = $reportesMes > 0 ? round($reportesMes / date('d'), 1) : 0;

// DSA / Tasa Frecuencia / Tasa Gravedad (solo accidentes)
$dsa = 0;
$tasaFrecuencia = 0;
$tasaGravedad = 0;
if ($tipo_filtro == 'accidente') {
    $ultimo = $pdo->query("SELECT MAX(fecha) FROM reportes r WHERE tipo='accidente' $filtroSucursalRep")->fetchColumn();
    if ($ultimo) {
        $hoy = new DateTime();
        $fechaUltimo = new DateTime($ultimo);
        $dsa = $hoy->diff($fechaUltimo)->days;
    } else {
        $dsa = -1; // sin accidentes
    }
    if ($horas_hombre > 0) {
        $accidentesMes = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE MONTH(fecha)=MONTH(CURDATE()) AND YEAR(fecha)=YEAR(CURDATE()) $filtroSucursalRep AND tipo='accidente'")->fetchColumn();
        $tasaFrecuencia = round(($accidentesMes * 1000000) / $horas_hombre, 2);
        $diasPerdidosMes = $pdo->query("SELECT SUM(dias_perdidos) FROM reportes r WHERE MONTH(fecha)=MONTH(CURDATE()) AND YEAR(fecha)=YEAR(CURDATE()) $filtroSucursalRep AND tipo='accidente'")->fetchColumn();
        $tasaGravedad = round(($diasPerdidosMes * 1000000) / $horas_hombre, 2);
    }
}

// --- Tendencia 6 meses ---
$meses = [];
$reportesPorMes = [];
for ($i = 5; $i >= 0; $i--) {
    $fecha = date('Y-m-01', strtotime("-$i months"));
    $meses[] = date('M Y', strtotime($fecha));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reportes r WHERE YEAR(fecha)=YEAR(?) AND MONTH(fecha)=MONTH(?) $filtroSucursalRep $filtroTipo");
    $stmt->execute([$fecha, $fecha]);
    $reportesPorMes[] = (int)$stmt->fetchColumn();
}

// --- Distribución por tipo (catalogo) ---
$catalogoLabels = [];
$catalogoValores = [];
if ($tipo_filtro == 'acto_inseguro') {
    $sql = "SELECT a.descripcion, COUNT(r.id) as total FROM actos_inseguros a LEFT JOIN reportes r ON a.id = r.acto_inseguro_id " . ($sucursal_id ? "AND r.sucursal_id = $sucursal_id" : "") . " WHERE a.activo = 1 AND (r.tipo IS NULL OR r.tipo = 'acto_inseguro') GROUP BY a.id ORDER BY total DESC";
} else {
    $sql = "SELECT t.descripcion, COUNT(r.id) as total FROM tipos_accidente t LEFT JOIN reportes r ON t.id = r.accidente_id " . ($sucursal_id ? "AND r.sucursal_id = $sucursal_id" : "") . " WHERE t.activo = 1 AND (r.tipo IS NULL OR r.tipo = 'accidente') GROUP BY t.id ORDER BY total DESC";
}
foreach ($pdo->query($sql) as $row) {
    $catalogoLabels[] = $row['descripcion'];
    $catalogoValores[] = (int)$row['total'];
}

// --- Severidad / Edad ---
$severidadLabels = ['Leve','Moderado','Grave','Fatal'];
$severidadData = [0,0,0,0];
$rangosEdad = ['< 25','25-34','35-44','45-54','55+'];
$conteoEdad = [0,0,0,0,0];

if ($tipo_filtro == 'accidente') {
    $res = $pdo->query("SELECT gravedad, COUNT(*) as total FROM reportes WHERE tipo='accidente' $filtroSucursalRep GROUP BY gravedad");
    $map = ['leve'=>0,'moderado'=>1,'grave'=>2,'fatal'=>3];
    foreach ($res as $row) {
        if (isset($map[$row['gravedad']])) $severidadData[$map[$row['gravedad']]] = (int)$row['total'];
    }
} else {
    $sqlEdad = "SELECT CASE WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) < 25 THEN 0 WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) BETWEEN 25 AND 34 THEN 1 WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) BETWEEN 35 AND 44 THEN 2 WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) BETWEEN 45 AND 54 THEN 3 ELSE 4 END as rango, COUNT(DISTINCT e.id) as total FROM empleados e JOIN reportes r ON e.id=r.empleado_id WHERE e.fecha_nacimiento IS NOT NULL $filtroSucursalRep $filtroTipo GROUP BY rango";
    foreach ($pdo->query($sqlEdad) as $row) $conteoEdad[$row['rango']] = (int)$row['total'];
}

// --- Horas del día ---
$horasLabels = [];
for ($h=0; $h<24; $h++) $horasLabels[] = sprintf('%02d:00', $h);
$horasData = array_fill(0,24,0);
$res = $pdo->query("SELECT HOUR(hora) as h, COUNT(*) as total FROM reportes r WHERE 1=1 $filtroSucursalRep $filtroTipo GROUP BY h");
foreach ($res as $row) $horasData[(int)$row['h']] = (int)$row['total'];

// --- Días de la semana ---
$diasSemana = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
$diasData = array_fill(0,7,0);
$res = $pdo->query("SELECT DAYOFWEEK(fecha) as dia, COUNT(*) as total FROM reportes r WHERE 1=1 $filtroSucursalRep $filtroTipo GROUP BY dia");
foreach ($res as $row) { $indice = ($row['dia']+5)%7; $diasData[$indice] = (int)$row['total']; }

// --- Top departamentos ---
$deptosLabels = [];
$deptosData = [];
$sql = "SELECT d.nombre, COUNT(r.id) as total FROM departamentos d LEFT JOIN reportes r ON d.id=r.departamento_id " . ($sucursal_id ? "AND r.sucursal_id=$sucursal_id" : "") . " WHERE (r.tipo IS NULL OR r.tipo='$tipo_filtro') GROUP BY d.id ORDER BY total DESC LIMIT 5";
foreach ($pdo->query($sql) as $row) { $deptosLabels[] = $row['nombre']; $deptosData[] = (int)$row['total']; }

// --- Datos para Pareto ---
$paretoLabels = [];
$paretoData = [];
if ($tipo_filtro == 'acto_inseguro') {
    $sql = "SELECT a.descripcion, COUNT(r.id) as total FROM actos_inseguros a LEFT JOIN reportes r ON a.id = r.acto_inseguro_id " . ($sucursal_id ? "AND r.sucursal_id=$sucursal_id" : "") . " WHERE a.activo = 1 AND (r.tipo IS NULL OR r.tipo='acto_inseguro') GROUP BY a.id ORDER BY total DESC LIMIT 10";
} else {
    $sql = "SELECT t.descripcion, COUNT(r.id) as total FROM tipos_accidente t LEFT JOIN reportes r ON t.id = r.accidente_id " . ($sucursal_id ? "AND r.sucursal_id=$sucursal_id" : "") . " WHERE t.activo = 1 AND (r.tipo IS NULL OR r.tipo='accidente') GROUP BY t.id ORDER BY total DESC LIMIT 10";
}
foreach ($pdo->query($sql) as $row) { $paretoLabels[] = $row['descripcion']; $paretoData[] = (int)$row['total']; }
$totalPareto = array_sum($paretoData);
$paretoAcumulado = [];
$acum = 0;
foreach ($paretoData as $val) { $acum += $val; $paretoAcumulado[] = round(($acum/$totalPareto)*100, 1); }

// --- Top sucursales ---
$topSuc = [];
if (!$sucursal_id) { // Solo si no se filtra por sucursal
    $sql = "SELECT s.nombre, COUNT(r.id) as total, ROUND(COUNT(r.id)/6,1) as prom FROM sucursales s LEFT JOIN reportes r ON s.id=r.sucursal_id " . ($sucursal_id?"AND r.sucursal_id=$sucursal_id":"") . " WHERE s.activo=1 AND (r.tipo IS NULL OR r.tipo='$tipo_filtro') GROUP BY s.id ORDER BY total DESC LIMIT 5";
    $topSuc = $pdo->query($sql)->fetchAll();
}

// --- Top empleados ---
$topEmp = $pdo->query("SELECT e.numero_empleado, e.nombre, COUNT(r.id) as total FROM empleados e LEFT JOIN reportes r ON e.id=r.empleado_id " . ($sucursal_id?"AND r.sucursal_id=$sucursal_id":"") . " WHERE e.activo=1 AND (r.tipo IS NULL OR r.tipo='$tipo_filtro') GROUP BY e.id ORDER BY total DESC LIMIT 5")->fetchAll();

// --- Respuesta JSON ---
echo json_encode([
    'success' => true,
    'kpis' => [
        'total_reportes'     => (int)$totalReportes,
        'reportes_mes'       => (int)$reportesMes,
        'total_empleados'    => (int)$totalEmpleados,
        'promedio_diario'    => $promedioDiario,
        'dsa'                => $dsa,
        'tasa_frecuencia'    => $tasaFrecuencia,
        'tasa_gravedad'      => $tasaGravedad,
        'horas_hombre'       => $horas_hombre
    ],
    'tendencia' => [
        'labels' => $meses,
        'data'   => $reportesPorMes
    ],
    'distribucion' => [
        'labels' => $catalogoLabels,
        'data'   => $catalogoValores
    ],
    'severidad' => ($tipo_filtro == 'accidente') ? [
        'labels' => $severidadLabels,
        'data'   => $severidadData
    ] : null,
    'edad' => ($tipo_filtro == 'acto_inseguro') ? [
        'labels' => $rangosEdad,
        'data'   => $conteoEdad
    ] : null,
    'horas' => [
        'labels' => $horasLabels,
        'data'   => $horasData
    ],
    'dias_semana' => [
        'labels' => $diasSemana,
        'data'   => $diasData
    ],
    'top_departamentos' => [
        'labels' => $deptosLabels,
        'data'   => $deptosData
    ],
    'pareto' => [
        'labels'    => $paretoLabels,
        'data'      => $paretoData,
        'acumulado' => $paretoAcumulado
    ],
    'top_sucursales' => $topSuc,
    'top_empleados'   => $topEmp
]);
exit;