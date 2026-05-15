<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// ============================================================
// CONFIGURACIÓN Y FILTROS
// ============================================================
$horas_hombre = HORAS_HOMBRE_MES;

if ($usuario_rol === 'supervisor') 
{
    $sucursal_id = $usuario_sucursal_id;
    $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE id = $usuario_sucursal_id")->fetchAll();
} 
else 
{
    $sucursal_id = $_GET['sucursal_id'] ?? '';
    $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();
}

$tipo_filtro = $_GET['tipo'] ?? 'acto_inseguro';
if (!in_array($tipo_filtro, ['acto_inseguro', 'accidente', 'enfermedad_cronica'])) 
    $tipo_filtro = 'acto_inseguro';

$filtroSucursalEmpleados = $sucursal_id ? "AND e.sucursal_id = $sucursal_id" : "";
$filtroSucursalReportes = $sucursal_id ? "AND r.sucursal_id = $sucursal_id" : "";
$filtroTipo = ($tipo_filtro !== 'enfermedad_cronica') ? "AND r.tipo = '$tipo_filtro'" : "";

$nombreSucursalSeleccionada = $sucursal_id ? $pdo->query("SELECT nombre FROM sucursales WHERE id = $sucursal_id")->fetchColumn() : 'Todas';

// ============================================================
// 1. KPIs BÁSICOS (dependiendo del tipo)
// ============================================================
if ($tipo_filtro == 'enfermedad_cronica') 
{
    // Total de empleados con al menos una enfermedad crónica
    $totalEmpleadosEnfermedad = $pdo->query("SELECT COUNT(DISTINCT ee.empleado_id) FROM empleado_enfermedad ee JOIN empleados e ON ee.empleado_id = e.id WHERE e.activo = 1 $filtroSucursalEmpleados")->fetchColumn();
    
    // Prevalencia general (%)
    $totalEmpleadosActivos = $pdo->query("SELECT COUNT(*) FROM empleados e WHERE activo = 1 $filtroSucursalEmpleados")->fetchColumn();
    $prevalencia = $totalEmpleadosActivos > 0 ? round(($totalEmpleadosEnfermedad / $totalEmpleadosActivos) * 100, 1) : 0;
    
    // Empleados con 2+ enfermedades (comorbilidad)
    $comorbilidad = $pdo->query("SELECT COUNT(*) FROM (SELECT ee.empleado_id FROM empleado_enfermedad ee JOIN empleados e ON ee.empleado_id = e.id WHERE e.activo = 1 $filtroSucursalEmpleados GROUP BY ee.empleado_id HAVING COUNT(*) >= 2) AS sub")->fetchColumn();
    $indiceComorbilidad = $totalEmpleadosEnfermedad > 0 ? round(($comorbilidad / $totalEmpleadosEnfermedad) * 100, 1) : 0;
    
    // Promedio de enfermedades por empleado (entre los que tienen)
    $promedioEnf = $pdo->query("SELECT AVG(cnt) FROM (SELECT COUNT(*) as cnt FROM empleado_enfermedad ee JOIN empleados e ON ee.empleado_id = e.id WHERE e.activo = 1 $filtroSucursalEmpleados GROUP BY ee.empleado_id) AS sub")->fetchColumn();
    $promedioEnf = $promedioEnf ? round($promedioEnf, 1) : 0;
    
    // Nuevos registros este mes
    $nuevosMes = $pdo->query("SELECT COUNT(*) FROM empleado_enfermedad ee JOIN empleados e ON ee.empleado_id = e.id WHERE e.activo = 1 $filtroSucursalEmpleados AND MONTH(ee.fecha_registro) = MONTH(CURDATE()) AND YEAR(ee.fecha_registro) = YEAR(CURDATE())")->fetchColumn();

    // Top 5 Empleados con más enfermedades crónicas
    $topEmp = $pdo->query("SELECT e.numero_empleado, e.nombre, COUNT(ee.enfermedad_id) as total FROM empleados e JOIN empleado_enfermedad ee ON e.id = ee.empleado_id WHERE e.activo = 1 $filtroSucursalEmpleados GROUP BY e.id ORDER BY total DESC LIMIT 5")->fetchAll();
    
    if (empty($topEmp))
    {
        $topEmp = [];
    }

}
else
{
    $totalReportes = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE 1=1 $filtroSucursalReportes $filtroTipo")->fetchColumn();
    $reportesMes = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE MONTH(fecha)=MONTH(CURDATE()) AND YEAR(fecha)=YEAR(CURDATE()) $filtroSucursalReportes $filtroTipo")->fetchColumn();
    $totalEmpleados = $pdo->query("SELECT COUNT(*) FROM empleados e WHERE activo=1 $filtroSucursalEmpleados")->fetchColumn();
    
    $totalDiasIncapacidad = $pdo->query("SELECT SUM(dias_perdidos) FROM reportes r WHERE tipo='accidente' $filtroSucursalReportes")->fetchColumn();
    $totalDiasIncapacidad = $totalDiasIncapacidad ?: 0;
    
    $dsa = 0;
    if ($tipo_filtro == 'accidente')
    {
        $sqlUltimo = "SELECT MAX(fecha) FROM reportes r WHERE tipo='accidente' $filtroSucursalReportes";
        $ultimo = $pdo->query($sqlUltimo)->fetchColumn();
        if ($ultimo)
        { 
            $hoy = new DateTime(); 
            $fechaUltimo = new DateTime($ultimo); 
            $dsa = $hoy->diff($fechaUltimo)->days; 
        } 
        else 
        { 
            $dsa = 'Sin datos';
        }
    }
    
    $tasaFrecuencia = 0;
    if ($tipo_filtro == 'accidente' && $horas_hombre > 0)
    {
        $accidentesMes = $pdo->query("SELECT COUNT(*) FROM reportes r WHERE MONTH(fecha)=MONTH(CURDATE()) AND YEAR(fecha)=YEAR(CURDATE()) $filtroSucursalReportes AND tipo='accidente'")->fetchColumn();
        $tasaFrecuencia = round(($accidentesMes * 1000000) / $horas_hombre, 2);
    }
    $tasaGravedad = 0;
    if ($tipo_filtro == 'accidente' && $horas_hombre > 0)
    {
        $diasPerdidosMes = $pdo->query("SELECT SUM(dias_perdidos) FROM reportes r WHERE MONTH(fecha)=MONTH(CURDATE()) AND YEAR(fecha)=YEAR(CURDATE()) $filtroSucursalReportes AND tipo='accidente'")->fetchColumn();
        $tasaGravedad = round(($diasPerdidosMes * 1000000) / $horas_hombre, 2);
    }
}

// ============================================================
// DATOS PARA GRÁFICOS (comunes o específicos)
// ============================================================
if ($tipo_filtro == 'enfermedad_cronica')
{
    // Top 10 enfermedades más frecuentes
    $sqlTopEnf = "SELECT ec.nombre, COUNT(ee.empleado_id) as total FROM enfermedades_cronicas ec LEFT JOIN empleado_enfermedad ee ON ec.id = ee.enfermedad_id JOIN empleados e ON ee.empleado_id = e.id WHERE e.activo = 1 $filtroSucursalEmpleados AND ec.activo = 1 GROUP BY ec.id ORDER BY total DESC LIMIT 10";
    $topEnfData = $pdo->query($sqlTopEnf)->fetchAll();
    $catalogoLabels = array_column($topEnfData, 'nombre');
    $catalogoValores = array_column($topEnfData, 'total');
    
    if (empty($catalogoValores))
    { 
        $catalogoLabels = ['Sin datos'];
        $catalogoValores = [0];
    }

    // Prevalencia por Sucursal
    $sqlSucEnf = "SELECT s.nombre, COUNT(DISTINCT ee.empleado_id) as total FROM sucursales s LEFT JOIN empleados e ON s.id = e.sucursal_id AND e.activo = 1 LEFT JOIN empleado_enfermedad ee ON e.id = ee.empleado_id WHERE s.activo = 1 GROUP BY s.id ORDER BY total DESC";
    $sucPrevData = $pdo->query($sqlSucEnf)->fetchAll();
    $sucPrevLabels = array_column($sucPrevData, 'nombre');
    $sucPrevValores = array_column($sucPrevData, 'total');
    
    // Asegurar que no estén vacíos
    if (empty($sucPrevValores))
    { 
        $sucPrevLabels = ['Sin datos']; 
        $sucPrevValores = [0];
    }
    
    // Tendencia mensual de nuevos registros (últimos 6 meses)
    $meses = []; $reportesPorMes = [];
    for ($i=5; $i>=0; $i--) 
    {
        $fecha = date('Y-m-01', strtotime("-$i months"));
        $meses[] = date('M Y', strtotime($fecha));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM empleado_enfermedad ee JOIN empleados e ON ee.empleado_id = e.id WHERE e.activo = 1 $filtroSucursalEmpleados AND YEAR(ee.fecha_registro) = YEAR(?) AND MONTH(ee.fecha_registro) = MONTH(?)");
        $stmt->execute([$fecha, $fecha]);
        $reportesPorMes[] = (int)$stmt->fetchColumn();
    }

    $mesesAnio = []; $actualAnio = []; $anteriorAnio = [];
    for ($m=1; $m<=12; $m++)
    { 
        $mesesAnio[] = date('M', mktime(0,0,0,$m,1)); 
        $actualAnio[] = 0; $anteriorAnio[] = 0; 
    }

    $paretoLabels = $catalogoLabels;
    $paretoData = $catalogoValores;
    $totalPareto = array_sum($paretoData);
    $paretoAcumulado = [];
    if ($totalPareto > 0) 
    {
        $acumulado = 0;
        foreach ($paretoData as $val)
        { 
            $acumulado += $val; 
            $paretoAcumulado[] = round(($acumulado / $totalPareto) * 100, 1);
        }
    }
    else 
    { 
        $paretoAcumulado = array_fill(0, count($paretoData), 0);
    }
    
    if (empty($paretoLabels)) 
    { 
        $paretoLabels = ['Sin datos']; 
        $paretoData = [0]; 
        $paretoAcumulado = [0];
    }

    $horasLabels = []; $horasData = [];
    $diasSemanaFull = []; $reportesPorDia = [];
    $severidadLabels = []; $severidadData = [];
    $compLabels = []; $compActos = []; $compAccidentes = [];
    $heatmapLabels = []; $heatmapValues = [];
    $tituloCatalogo = 'Top 10 Enfermedades';
    $tituloSeveridadEdad = 'Prevalencia por Sucursal';
    $chartIdSeveridadEdad = 'prevalenciaSucursalChart'; // NUEVO ID EXCLUSIVO
} 
else 
{
    // Código existente para actos/accidentes (sin modificaciones)
    $meses = []; $reportesPorMes = [];
    for ($i=5; $i>=0; $i--) 
    {
        $fecha = date('Y-m-01', strtotime("-$i months"));
        $meses[] = date('M Y', strtotime($fecha));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reportes r WHERE YEAR(fecha)=YEAR(?) AND MONTH(fecha)=MONTH(?) $filtroSucursalReportes $filtroTipo");
        $stmt->execute([$fecha, $fecha]);
        $reportesPorMes[] = (int)$stmt->fetchColumn();
    }
    
    $mesesAnio = []; $actualAnio = []; $anteriorAnio = [];
    for ($m=1; $m<=12; $m++)
    {
        $mesesAnio[] = date('M', mktime(0,0,0,$m,1));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reportes r WHERE MONTH(fecha)=? AND YEAR(fecha)=YEAR(CURDATE()) $filtroSucursalReportes $filtroTipo");
        $stmt->execute([$m]); $actualAnio[] = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reportes r WHERE MONTH(fecha)=? AND YEAR(fecha)=YEAR(CURDATE())-1 $filtroSucursalReportes $filtroTipo");
        $stmt->execute([$m]); $anteriorAnio[] = (int)$stmt->fetchColumn();
    }
    
    $catalogoLabels = []; $catalogoValores = [];
    if ($tipo_filtro == 'acto_inseguro')
    {
        $sql = "SELECT a.descripcion, COUNT(r.id) as total FROM actos_inseguros a LEFT JOIN reportes r ON a.id = r.acto_inseguro_id " . ($sucursal_id ? "AND r.sucursal_id = $sucursal_id" : "") . " WHERE a.activo = 1 AND (r.tipo IS NULL OR r.tipo = 'acto_inseguro') GROUP BY a.id ORDER BY total DESC";
    }
    else
    {
        $sql = "SELECT t.descripcion, COUNT(r.id) as total FROM tipos_accidente t LEFT JOIN reportes r ON t.id = r.accidente_id " . ($sucursal_id ? "AND r.sucursal_id = $sucursal_id" : "") . " WHERE t.activo = 1 AND (r.tipo IS NULL OR r.tipo = 'accidente') GROUP BY t.id ORDER BY total DESC";
    }
    
    $catalogoData = $pdo->query($sql)->fetchAll();
    $totalCatalogo = count($catalogoData);
    if ($totalCatalogo == 0)
    { 
        $catalogoLabels = ['Sin datos'];
        $catalogoValores = [0];
    }
    else
    {
        $top = array_slice($catalogoData, 0, 8);
        $resto = array_slice($catalogoData, 8);
        foreach ($top as $row)
        { 
            $catalogoLabels[] = $row['descripcion'];
            $catalogoValores[] = (int)$row['total'];
        }
        if (!empty($resto))
        {
            $sumaOtros = array_sum(array_column($resto, 'total'));
            
            if ($sumaOtros > 0)
            { 
                $catalogoLabels[] = 'Otros (' . count($resto) . ')'; 
                $catalogoValores[] = $sumaOtros;
            }
        }
    }

    $severidadLabels = ['Leve','Moderado','Grave','Fatal']; $severidadData = [0,0,0,0];
    if ($tipo_filtro == 'accidente') 
    {
        $sqlSev = "SELECT gravedad, COUNT(*) as total FROM reportes r WHERE tipo='accidente' $filtroSucursalReportes GROUP BY gravedad";
        $res = $pdo->query($sqlSev);
        $map = ['leve'=>0, 'moderado'=>1, 'grave'=>2, 'fatal'=>3];
        foreach ($res as $row)
        { 
            if (isset($map[$row['gravedad']]))
                $severidadData[$map[$row['gravedad']]] = (int)$row['total'];
        }
    }
    $horasLabels = []; 
    
    for ($h=0; $h<24; $h++) 
        $horasLabels[] = sprintf('%02d:00', $h);
    
    $horasData = array_fill(0, 24, 0);
    $sqlHoras = "SELECT HOUR(hora) as h, COUNT(*) as total FROM reportes r WHERE 1=1 $filtroSucursalReportes $filtroTipo GROUP BY h";
    $res = $pdo->query($sqlHoras);
    
    foreach ($res as $row)
        $horasData[(int)$row['h']] = (int)$row['total'];
    
    $diasSemana = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    $heatmapData = array_fill(0, 7, array_fill(0, 24, 0));
    $sqlHeat = "SELECT DAYOFWEEK(fecha) as dia, HOUR(hora) as h, COUNT(*) as total FROM reportes r WHERE 1=1 $filtroSucursalReportes $filtroTipo GROUP BY dia, h";
    $res = $pdo->query($sqlHeat);
    
    foreach ($res as $row)
    {
        $dia = $row['dia'] - 1; 
        $hora = (int)$row['h']; 
        $heatmapData[$dia][$hora] = (int)$row['total'];
    }
    
    $heatmapLabels = []; $heatmapValues = [];
    for ($d=0; $d<7; $d++)
    { 
        for ($h=0; $h<24; $h++)
        { 
            if ($heatmapData[$d][$h] > 0)
            { 
                $heatmapLabels[] = $diasSemana[$d].' '.sprintf('%02d:00',$h); 
                $heatmapValues[] = $heatmapData[$d][$h]; 
            } 
        } 
    }
    $deptos = $pdo->query("SELECT d.nombre, COUNT(r.id) as total FROM departamentos d LEFT JOIN reportes r ON d.id=r.departamento_id " . ($sucursal_id ? "AND r.sucursal_id=$sucursal_id" : "") . " WHERE (r.tipo IS NULL OR r.tipo='$tipo_filtro') GROUP BY d.id ORDER BY total DESC LIMIT 5")->fetchAll();
    $deptosLabels = array_column($deptos, 'nombre'); $deptosData = array_column($deptos, 'total');
    $topSuc = $pdo->query("SELECT s.nombre, COUNT(r.id) as total, ROUND(COUNT(r.id)/6,1) as prom FROM sucursales s LEFT JOIN reportes r ON s.id=r.sucursal_id " . ($sucursal_id ? "AND r.sucursal_id=$sucursal_id" : "") . " WHERE s.activo=1 AND (r.tipo IS NULL OR r.tipo='$tipo_filtro') GROUP BY s.id ORDER BY total DESC LIMIT 5")->fetchAll();
    $topEmp = $pdo->query("SELECT e.numero_empleado, e.nombre, d.nombre as depto, s.nombre as suc, COUNT(r.id) as total FROM empleados e JOIN departamentos d ON e.departamento_id=d.id JOIN sucursales s ON e.sucursal_id=s.id LEFT JOIN reportes r ON e.id=r.empleado_id " . ($sucursal_id ? "AND r.sucursal_id=$sucursal_id" : "") . " WHERE e.activo=1 AND (r.tipo IS NULL OR r.tipo='$tipo_filtro') GROUP BY e.id ORDER BY total DESC LIMIT 5")->fetchAll();
    
    $rangosEdad = ['< 25', '25-34', '35-44', '45-54', '55+']; 
    $conteoEdad = [0,0,0,0,0];
    $sqlEdad = "SELECT CASE WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) < 25 THEN 0 WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) BETWEEN 25 AND 34 THEN 1 WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) BETWEEN 35 AND 44 THEN 2 WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) BETWEEN 45 AND 54 THEN 3 ELSE 4 END as rango, COUNT(DISTINCT e.id) as total FROM empleados e JOIN reportes r ON e.id=r.empleado_id WHERE e.fecha_nacimiento IS NOT NULL $filtroSucursalReportes $filtroTipo GROUP BY rango";
    
    $res = $pdo->query($sqlEdad); 
    foreach ($res as $row) 
        $conteoEdad[$row['rango']] = (int)$row['total'];

    $diasSemanaFull = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo']; 
    $reportesPorDia = array_fill(0,7,0);
    $sqlDias = "SELECT DAYOFWEEK(fecha) as dia, COUNT(*) as total FROM reportes r WHERE 1=1 $filtroSucursalReportes $filtroTipo GROUP BY dia";
    $res = $pdo->query($sqlDias); 
    
    foreach ($res as $row) 
    { 
        $indice = ($row['dia']+5)%7; 
        $reportesPorDia[$indice] = (int)$row['total']; 
    }

    $compLabels = []; 
    $compActos = []; 
    $compAccidentes = [];
    $sqlComp = "SELECT 'Acto Inseguro' as tipo_item, a.descripcion as item, SUM(CASE WHEN r.tipo='acto_inseguro' THEN 1 ELSE 0 END) as actos, SUM(CASE WHEN r.tipo='accidente' THEN 1 ELSE 0 END) as accidentes FROM actos_inseguros a LEFT JOIN reportes r ON a.id = r.acto_inseguro_id " . ($sucursal_id ? "AND r.sucursal_id=$sucursal_id" : "") . " WHERE a.activo=1 GROUP BY a.id UNION ALL SELECT 'Tipo Accidente' as tipo_item, t.descripcion as item, SUM(CASE WHEN r.tipo='acto_inseguro' THEN 1 ELSE 0 END) as actos, SUM(CASE WHEN r.tipo='accidente' THEN 1 ELSE 0 END) as accidentes FROM tipos_accidente t LEFT JOIN reportes r ON t.id = r.accidente_id " . ($sucursal_id ? "AND r.sucursal_id=$sucursal_id" : "") . " WHERE t.activo=1 GROUP BY t.id ORDER BY actos+accidentes DESC LIMIT 10";
    $resComp = $pdo->query($sqlComp);
    
    foreach ($resComp as $row)
    { 
        $compLabels[] = $row['item']; 
        $compActos[] = (int)$row['actos']; 
        $compAccidentes[] = (int)$row['accidentes'];
    }

    $paretoLabels = []; 
    $paretoData = [];
    if ($tipo_filtro == 'acto_inseguro')
    {
        $sqlPareto = "SELECT a.descripcion, COUNT(r.id) as total FROM actos_inseguros a LEFT JOIN reportes r ON a.id = r.acto_inseguro_id " . ($sucursal_id ? "AND r.sucursal_id = $sucursal_id" : "") . " WHERE a.activo = 1 AND (r.tipo IS NULL OR r.tipo = 'acto_inseguro') GROUP BY a.id ORDER BY total DESC LIMIT 10";
    }
    else 
    {
        $sqlPareto = "SELECT t.descripcion, COUNT(r.id) as total FROM tipos_accidente t LEFT JOIN reportes r ON t.id = r.accidente_id " . ($sucursal_id ? "AND r.sucursal_id = $sucursal_id" : "") . " WHERE t.activo = 1 AND (r.tipo IS NULL OR r.tipo = 'accidente') GROUP BY t.id ORDER BY total DESC LIMIT 10";
    }

    $paretoDataRaw = $pdo->query($sqlPareto)->fetchAll();
    foreach ($paretoDataRaw as $row)
    { 
        $paretoLabels[] = $row['descripcion']; 
        $paretoData[] = (int)$row['total'];
    }
    $totalPareto = array_sum($paretoData);
    $paretoAcumulado = [];

    if ($totalPareto > 0)
    { 
        $acumulado = 0; 
        foreach ($paretoData as $val)
        { 
            $acumulado += $val; 
            $paretoAcumulado[] = round(($acumulado / $totalPareto) * 100, 1); 
        } 
    }
    else
    { 
        $paretoAcumulado = array_fill(0, count($paretoData), 0);
    }
    if (empty($paretoLabels))
    {
        $paretoLabels = ['Sin datos']; 
        $paretoData = [0]; 
        $paretoAcumulado = [0];
    }
    
    $tituloCatalogo = ($tipo_filtro == 'acto_inseguro') ? 'Actos Inseguros' : 'Tipos de Accidente';
    $tituloSeveridadEdad = ($tipo_filtro == 'accidente') ? 'Severidad de Accidentes' : 'Distribución por Edad';
    $chartIdSeveridadEdad = ($tipo_filtro == 'accidente') ? 'severidadChart' : 'edadChart';
}

// ============================================================
// PREPARAR SECCIONES PARA PDF
// ============================================================
$seccionesPdf = [
    ['tipo' => 'kpi', 'titulo' => 'Indicadores Clave (KPIs)', 'descripcion' => 'Resumen ejecutivo de los principales indicadores.'],
    ['tipo' => 'grafico', 'id' => 'tendenciaChart', 'titulo' => ($tipo_filtro == 'enfermedad_cronica' ? 'Nuevos registros (últimos 6 meses)' : 'Tendencia de Reportes (Últimos 6 meses)'), 'descripcion' => 'Evolución mensual.'],
    ['tipo' => 'grafico', 'id' => 'catalogoChart', 'titulo' => ($tipo_filtro == 'enfermedad_cronica' ? 'Top 10 Enfermedades' : 'Distribución por ' . $tituloCatalogo), 'descripcion' => 'Proporción de cada tipo.'],
    ['tipo' => 'grafico', 'id' => 'comparativaAnualChart', 'titulo' => 'Comparativa Anual', 'descripcion' => 'Contraste con año anterior (no aplica para enfermedades).'],
    ['tipo' => 'grafico', 'id' => $chartIdSeveridadEdad, 'titulo' => $tituloSeveridadEdad, 'descripcion' => ($tipo_filtro == 'enfermedad_cronica' ? 'Empleados con enfermedades por sucursal.' : '')],
    ['tipo' => 'grafico', 'id' => 'horasChart', 'titulo' => 'Reportes por Hora del Día', 'descripcion' => 'Franjas horarias con mayor incidencia.'],
    ['tipo' => 'grafico', 'id' => 'diasChart', 'titulo' => 'Reportes por Día de la Semana', 'descripcion' => 'Distribución semanal de incidentes.'],
    ['tipo' => 'grafico', 'id' => 'paretoChart', 'titulo' => 'Diagrama de Pareto (Top 10 causas)', 'descripcion' => 'Principales causas y porcentaje acumulado.'],
    ['tipo' => 'tabla', 'titulo' => 'Top 5 Sucursales', 'descripcion' => 'Sucursales con mayor cantidad.'],
    ['tipo' => 'grafico', 'id' => 'comparativaTipoChart', 'titulo' => 'Comparativa Actos vs Accidentes', 'descripcion' => 'Top 10 ítems desglosado por tipo.'],
    ['tipo' => 'grafico', 'id' => 'deptosChart', 'titulo' => ($tipo_filtro == 'enfermedad_cronica' ? 'Prevalencia por Sucursal' : 'Top 5 Departamentos'), 'descripcion' => ''],
    ['tipo' => 'tabla', 'titulo' => ($tipo_filtro == 'enfermedad_cronica' ? 'Top 5 Empleados con más enfermedades' : 'Top 5 Empleados'), 'descripcion' => '']
];
$seccionesJson = json_encode($seccionesPdf);

include '../../includes/header.php';
?>

<!-- Selectores -->
<div class="d-flex justify-content-between flex-wrap mb-3 border-bottom pb-2">
    <h1>Dashboard SYSO</h1>
    <form method="get" class="row g-2">
        <?php if ($usuario_rol !== 'supervisor'): ?>
        <div class="col-auto"><label>Sucursal:</label></div>
        <div class="col-auto"><select name="sucursal_id" class="form-select" onchange="this.form.submit()"><option value="">Todas</option><?php foreach($sucursales as $s): ?><option value="<?=$s['id']?>" <?=$sucursal_id==$s['id']?'selected':''?>><?=htmlspecialchars($s['nombre'])?></option><?php endforeach; ?></select></div>
        <?php else: ?><input type="hidden" name="sucursal_id" value="<?= $sucursal_id ?>"><?php endif; ?>
        <div class="col-auto"><label>Tipo:</label></div>
        <div class="col-auto"><select name="tipo" class="form-select" onchange="this.form.submit()">
            <option value="acto_inseguro" <?=$tipo_filtro=='acto_inseguro'?'selected':''?>>Actos Inseguros</option>
            <option value="accidente" <?=$tipo_filtro=='accidente'?'selected':''?>>Accidentes</option>
            <option value="enfermedad_cronica" <?=$tipo_filtro=='enfermedad_cronica'?'selected':''?>>Enfermedades Crónicas</option>
        </select></div>
        <div class="col-auto">
            <a href="exportar_dashboard_excel.php?sucursal_id=<?=$sucursal_id?>&tipo=<?=$tipo_filtro?>" class="btn btn-success no-spinner"><i class="fas fa-file-excel"></i> Exportar Excel</a>
            <button type="button" id="btnExportarPDF" class="btn btn-danger ms-2"><i class="fas fa-file-pdf"></i> Exportar PDF</button>
        </div>
    </form>
</div>

<!-- KPIs con Tooltips -->
<div class="row mb-4">
    <?php if ($tipo_filtro == 'enfermedad_cronica'): ?>
        <div class="col-md-2"><div class="card bg-primary text-white h-100 kpi-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><h6 class="card-title">Total Empleados <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Empleados con al menos una enfermedad crónica"></i></h6></div><h2 class="display-6"><?=$totalEmpleadosEnfermedad?></h2></div></div></div>
        <div class="col-md-2"><div class="card bg-success text-white h-100 kpi-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><h6 class="card-title">Prevalencia <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Porcentaje de empleados activos con alguna enfermedad"></i></h6></div><h2 class="display-6"><?=$prevalencia?>%</h2></div></div></div>
        <div class="col-md-2"><div class="card bg-info text-white h-100 kpi-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><h6 class="card-title">Comorbilidad <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="% de empleados con 2 o más enfermedades"></i></h6></div><h2 class="display-6"><?=$indiceComorbilidad?>%</h2></div></div></div>
        <div class="col-md-2"><div class="card bg-warning text-white h-100 kpi-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><h6 class="card-title">Prom. Enf/Empl <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Promedio de enfermedades por empleado (entre los que tienen)"></i></h6></div><h2 class="display-6"><?=$promedioEnf?></h2></div></div></div>
        <div class="col-md-2"><div class="card bg-secondary text-white h-100 kpi-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><h6 class="card-title">Nuevos este mes <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Registros nuevos en el mes actual"></i></h6></div><h2 class="display-6"><?=$nuevosMes?></h2></div></div></div>
    <?php else: ?>
        <div class="col-md-2"><div class="card bg-primary text-white h-100 kpi-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><h6 class="card-title">Total Reportes <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Cantidad total de reportes del tipo seleccionado"></i></h6></div><h2 class="display-6"><?=$totalReportes?></h2></div></div></div>
        <div class="col-md-2"><div class="card bg-success text-white h-100 kpi-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><h6 class="card-title">Este Mes <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Reportes ocurridos en el mes actual."></i></h6></div><h2 class="display-6"><?=$reportesMes?></h2></div></div></div>
        <div class="col-md-2"><div class="card bg-info text-white h-100 kpi-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><h6 class="card-title">Empleados <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Número de empleados activos"></i></h6></div><h2 class="display-6"><?=$totalEmpleados?></h2></div></div></div>
        
        <div class="col-md-2"><div class="card bg-warning text-white h-100 kpi-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><h6 class="card-title">Total Incapacidad <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Suma total de días de incapacidad por accidentes"></i></h6></div><h2 class="display-6"><?=$totalDiasIncapacidad?></h2></div></div></div>
        
        <?php if ($tipo_filtro == 'accidente'): ?>
        <div class="col-md-2"><div class="card bg-danger text-white h-100 kpi-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><h6 class="card-title">DSA <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Días Sin Accidentes"></i></h6></div><h2 class="display-6"><?=$dsa?></h2></div></div></div>
        <div class="col-md-2"><div class="card bg-dark text-white h-100 kpi-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><h6 class="card-title">Tasa Free. <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Tasa de Frecuencia"></i></h6></div><h2 class="display-6"><?=$tasaFrecuencia?></h2></div></div></div>
        <div class="col-md-2"><div class="card bg-secondary text-white h-100 kpi-card"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><h6 class="card-title">Tasa Gravedad <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Tasa de Gravedad"></i></h6></div><h2 class="display-6"><?=$tasaGravedad?></h2></div></div></div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- FILA 1: Tendencia + Catálogo -->
<div class="row">
    <div class="col-md-8 mb-4"><div class="card"><div class="card-header"><?= ($tipo_filtro == 'enfermedad_cronica') ? 'Nuevos registros (últimos 6 meses)' : 'Tendencia últimos 6 meses' ?></div><div class="card-body"><canvas id="tendenciaChart" style="height:250px; width:100%;"></canvas></div></div></div>
    <div class="col-md-4 mb-4"><div class="card h-100"><div class="card-header"><?= ($tipo_filtro == 'enfermedad_cronica') ? 'Top 10 Enfermedades' : $tituloCatalogo ?></div><div class="card-body d-flex align-items-center justify-content-center"><div style="position: relative; width: 100%; height: 460px;"><canvas id="catalogoChart" style="display: block; width: 100%; height: 100%;"></canvas></div></div></div></div>
</div>

<!-- FILA 2: Comparativa Anual + Severidad/Edad/Prevalencia -->
<div class="row">
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header">Comparativa Año Actual vs Anterior</div><div class="card-body"><canvas id="comparativaAnualChart" style="height:250px"></canvas></div></div></div>
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header"><?= ($tipo_filtro == 'enfermedad_cronica') ? 'Prevalencia por Sucursal' : $tituloSeveridadEdad ?></div><div class="card-body"><canvas id="<?= $chartIdSeveridadEdad ?>" style="height:250px"></canvas></div></div></div>
</div>

<!-- FILA 3: Hora del día + Días de la semana (solo si no es enfermedad) -->
<?php if ($tipo_filtro != 'enfermedad_cronica'): ?>
<div class="row">
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header">Reportes por Hora del Día</div><div class="card-body"><canvas id="horasChart" style="height:250px"></canvas></div></div></div>
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header">Reportes por Día de la Semana</div><div class="card-body"><canvas id="diasChart" style="height:250px"></canvas></div></div></div>
</div>
<?php endif; ?>

<!-- FILA 4: Pareto + Comparativa Actos vs Accidentes (o solo Pareto para enfermedades) -->
<div class="row">
    <div class="<?= ($tipo_filtro == 'enfermedad_cronica') ? 'col-12' : 'col-md-6' ?> mb-4">
        <div class="card"><div class="card-header"><i class="fas fa-chart-bar"></i> Diagrama de Pareto (Top 10 causas)</div>
            <div class="card-body"><canvas id="paretoChart" style="height:300px"></canvas></div>
        </div>
    </div>
    <?php if ($tipo_filtro != 'enfermedad_cronica'): ?>
    <div class="col-md-6 mb-4">
        <div class="card"><div class="card-header">Comparativa Actos vs Accidentes (Top 10)</div>
            <div class="card-body"><canvas id="comparativaTipoChart" style="height:300px"></canvas></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- FILA 5: Mapa de Calor (solo si no es enfermedad) -->
<?php if ($tipo_filtro != 'enfermedad_cronica'): ?>
<div class="row">
    <div class="col-12 mb-4"><div class="card"><div class="card-header">Mapa de Calor Día/Hora (top incidencias)</div><div class="card-body table-responsive" style="max-height:300px"><table class="table table-sm table-bordered"><thead><tr><th>Día</th><th>Hora</th><th>Total</th></tr></thead><tbody><?php $cont=0; foreach ($heatmapLabels as $i=>$lbl): if($cont++>15) break; ?><tr><td><?=explode(' ',$lbl)[0]?></td><td><?=explode(' ',$lbl)[1]?></td><td><?=$heatmapValues[$i]?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div>
<?php endif; ?>

<!-- FILA 6: Top Departamentos (o Prevalencia por Sucursal para enfermedades) + Top Empleados -->
<div class="row">
    <?php if ($tipo_filtro != 'enfermedad_cronica'): ?>
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header">Top 5 Departamentos</div><div class="card-body"><canvas id="deptosChart" style="height:250px"></canvas></div></div></div>
    <?php endif; ?>
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header"><?= ($tipo_filtro == 'enfermedad_cronica') ? 'Top 5 Empleados con más enfermedades' : 'Top 5 Empleados' ?></div><div class="card-body table-responsive"><table class="table table-sm tabla-top-empleados"><thead><tr><th>#</th><th>Nombre</th><th>Total</th></tr></thead><tbody><?php foreach($topEmp as $e): ?><tr><td><?=$e['numero_empleado']?></td><td><?=htmlspecialchars($e['nombre'])?></td><td><?=$e['total']?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div>

<!-- FILA 7: Top Sucursales (solo para actos/accidentes) -->
<?php if ($tipo_filtro != 'enfermedad_cronica'): ?>
<div class="row">
    <div class="col-12 mb-4"><div class="card"><div class="card-header">Top Sucursales</div><div class="card-body table-responsive"><table class="table table-sm tabla-top-sucursales"><thead><tr><th>Sucursal</th><th>Total</th><th>Prom. Mensual</th></tr></thead><tbody><?php foreach($topSuc as $s): ?><tr><td><?=htmlspecialchars($s['nombre'])?></td><td><?=$s['total']?></td><td><?=$s['prom']?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(el => new bootstrap.Tooltip(el));

    const totalHoras = <?= isset($horasData) ? array_sum($horasData) : 0 ?>, totalDias = <?= isset($reportesPorDia) ? array_sum($reportesPorDia) : 0 ?>, totalDeptos = <?= isset($deptosData) ? array_sum($deptosData) : 0 ?>, totalEdad = <?= isset($conteoEdad) ? array_sum($conteoEdad) : 0 ?>, totalSeveridad = <?= isset($severidadData) ? array_sum($severidadData) : 0 ?>, totalComparativaActos = <?= isset($compActos) ? array_sum($compActos) : 0 ?>, totalComparativaAccidentes = <?= isset($compAccidentes) ? array_sum($compAccidentes) : 0 ?>;

    function crearGrafico(id, config) {
        const canvas = document.getElementById(id);
        if (!canvas) { console.error(`Canvas no encontrado: ${id}`); return null; }
        let existente = Chart.getChart(canvas);
        if (existente) existente.destroy();
        return new Chart(canvas, config);
    }

    // 1. Tendencia
    crearGrafico('tendenciaChart', {
        type: 'line',
        data: { labels: <?=json_encode($meses)?>, datasets: [{ label: 'Reportes', data: <?=json_encode($reportesPorMes)?>, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.1)', tension: 0.2, fill: true }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => { let v = ctx.raw, i = ctx.dataIndex, a = i > 0 ? ctx.dataset.data[i-1] : null; return `Reportes: ${v}${a !== null ? (v >= a ? ` (+${v-a})` : ` (${v-a})`) : ''}`; } } } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });

    // 2. Catálogo (DONA)
    function renderDona() {
        const canvas = document.getElementById('catalogoChart');
        if (!canvas) return;
        if (canvas.offsetWidth > 0 && canvas.offsetHeight > 0) {
            const totalDona = <?=json_encode(array_sum($catalogoValores))?>;
            crearGrafico('catalogoChart', {
                type: 'doughnut',
                data: {
                    labels: <?=json_encode($catalogoLabels)?>,
                    datasets: [{
                        data: <?=json_encode($catalogoValores)?>,
                        backgroundColor: ['#0d6efd','#6610f2','#6f42c1','#d63384','#4caf50','#ff9800','#9c27b0','#00bcd4','#ffeb3b','#795548'],
                        borderWidth: 1, borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: true, cutout: '40%', layout: { padding: 2 },
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 8, font: { size: 9 }, padding: 6 } },
                        tooltip: { callbacks: { label: (c) => { let v = c.raw, p = totalDona > 0 ? ((v / totalDona)*100).toFixed(1) : 0; return `${c.label}: ${v} (${p}%)`; } } }
                    }
                }
            });
        } else { requestAnimationFrame(renderDona); }
    }
    renderDona();

    // 3. Comparativa Anual
    crearGrafico('comparativaAnualChart', { type:'line', data:{ labels:<?=json_encode($mesesAnio)?>, datasets:[{ label:'Actual', data:<?=json_encode($actualAnio)?>, borderColor:'#198754' },{ label:'Anterior', data:<?=json_encode($anteriorAnio)?>, borderColor:'#6c757d' }] }, options:{ responsive:true, plugins:{ tooltip:{ callbacks:{ label:(c)=>{ let l=c.dataset.label||'', v=c.raw, i=c.dataIndex, o=(c.dataset.label==='Actual')?<?=json_encode($anteriorAnio)?>[i]:<?=json_encode($actualAnio)?>[i], d=v-o; return `${l}: ${v}${d!==0?` (${d>0?'+':''}${d} vs ${c.dataset.label==='Actual'?'anterior':'actual'})`:''}`; } } } } } });

    // 4. Severidad o Edad o Prevalencia
    <?php if ($tipo_filtro == 'accidente'): ?>
    crearGrafico('severidadChart', { type:'bar', data:{ labels:<?=json_encode($severidadLabels)?>, datasets:[{ label:'Accidentes', data:<?=json_encode($severidadData)?>, backgroundColor:['#ffc107','#fd7e14','#dc3545','#212529'] }] }, options:{ plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label:(c)=>{ let v=c.raw,p=totalSeveridad>0?((v/totalSeveridad)*100).toFixed(1):0; return `Accidentes: ${v} (${p}%)`; } } } } }});
    <?php elseif ($tipo_filtro == 'acto_inseguro'): ?>
    crearGrafico('edadChart', { type:'bar', data:{ labels:<?=json_encode($rangosEdad)?>, datasets:[{ label:'Empleados', data:<?=json_encode($conteoEdad)?>, backgroundColor:'#ff6384' }] }, options:{ plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label:(c)=>{ let v=c.raw,p=totalEdad>0?((v/totalEdad)*100).toFixed(1):0; return `Empleados: ${v} (${p}%)`; } } } } }});
    <?php else: ?>
    // Prevalencia por Sucursal (enfermedades) - con ID exclusivo
    setTimeout(function() 
    {
        crearGrafico('prevalenciaSucursalChart', { 
            type:'bar', data:{ 
                labels:<?=json_encode($sucPrevLabels)?>, 
                datasets:[{ 
                    label:'Empleados con enfermedad', 
                    data:<?=json_encode($sucPrevValores)?>, 
                    backgroundColor:'#198754' }] 
                }, 
                options:{ 
                    indexAxis:'y', 
                    plugins:{ 
                        legend:{ display:false }, 
                        tooltip:{ 
                            callbacks:{ 
                                label:(c)=>{ let v=c.raw, p=totalDeptos>0?((v/totalDeptos)*100).toFixed(1):0; return `Empleados: ${v} (${p}%)`; } 
                            } 
                        } 
                    } 
                }
            });
    }, 100);
    <?php endif; ?>

    // 5. Hora del día
    <?php if ($tipo_filtro != 'enfermedad_cronica'): ?>
    crearGrafico('horasChart', { type:'bar', data:{ labels:<?=json_encode($horasLabels)?>, datasets:[{ label:'Reportes', data:<?=json_encode($horasData)?>, backgroundColor:'#0dcaf0' }] }, options:{ plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label:(c)=>{ let v=c.raw,p=totalHoras>0?((v/totalHoras)*100).toFixed(1):0; return `Reportes: ${v} (${p}%)`; } } } }, scales:{ y:{ beginAtZero:true } } }});
    crearGrafico('diasChart', { type:'bar', data:{ labels:<?=json_encode($diasSemanaFull)?>, datasets:[{ data:<?=json_encode($reportesPorDia)?>, backgroundColor:'#6f42c1' }] }, options:{ plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label:(c)=>{ let v=c.raw,p=totalDias>0?((v/totalDias)*100).toFixed(1):0; return `Reportes: ${v} (${p}%)`; } } } } }});
    <?php endif; ?>

    // 7. Comparativa Actos vs Accidentes
    <?php if ($tipo_filtro != 'enfermedad_cronica'): ?>
    crearGrafico('comparativaTipoChart', { type:'bar', data:{ labels:<?=json_encode($compLabels)?>, datasets:[{ label:'Actos Inseguros', data:<?=json_encode($compActos)?>, backgroundColor:'#0d6efd' },{ label:'Accidentes', data:<?=json_encode($compAccidentes)?>, backgroundColor:'#dc3545' }] }, options:{ responsive:true, scales:{ x:{ stacked:false }, y:{ beginAtZero:true } }, plugins:{ tooltip:{ callbacks:{ label:(c)=>{ let v=c.raw,ts=(c.dataset.label==='Actos Inseguros')?totalComparativaActos:totalComparativaAccidentes,p=ts>0?((v/ts)*100).toFixed(1):0; return `${c.dataset.label}: ${v} (${p}%)`; } } } } }});
    <?php endif; ?>

    // 9. Pareto
    crearGrafico('paretoChart', {
        type: 'bar',
        data: {
            labels: <?=json_encode($paretoLabels)?>,
            datasets: [
                { label: 'Frecuencia', data: <?=json_encode($paretoData)?>, backgroundColor: '#36a2eb', yAxisID: 'y' },
                { label: '% Acumulado', data: <?=json_encode($paretoAcumulado)?>, type: 'line', borderColor: '#ff6384', borderWidth: 2, pointStyle: 'circle', pointRadius: 4, fill: false, tension: 0.3, yAxisID: 'y1' }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { tooltip: { callbacks: { label: (ctx) => { return ctx.dataset.label === 'Frecuencia' ? `Frecuencia: ${ctx.raw}` : `% Acumulado: ${ctx.raw}%`; } } } },
            scales: { y: { beginAtZero: true, title: { display: true, text: 'Frecuencia' } }, y1: { position: 'right', beginAtZero: true, max: 100, grid: { drawOnChartArea: false }, title: { display: true, text: 'Porcentaje acumulado (%)' } } }
        }
    });

    // 8. Top Departamentos (solo para actos/accidentes)
    <?php if ($tipo_filtro != 'enfermedad_cronica'): ?>
    crearGrafico('deptosChart', { type:'bar', data:{ labels:<?=json_encode($deptosLabels)?>, datasets:[{ data:<?=json_encode($deptosData)?>, backgroundColor:'#198754' }] }, options:{ indexAxis:'y', plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label:(c)=>{ let v=c.raw,p=totalDeptos>0?((v/totalDeptos)*100).toFixed(1):0; return `Reportes: ${v} (${p}% del top 5)`; } } } } }});
    <?php endif; ?>

    // ============================================================
    // EXPORTAR A PDF
    // ============================================================
    const seccionesBase = <?= $seccionesJson ?>;
    document.getElementById('btnExportarPDF').addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generando informe...';
        try {
            await new Promise(resolve => setTimeout(resolve, 300));
            const charts = {};
            const canvasIds = ['tendenciaChart', 'catalogoChart', 'comparativaAnualChart', 'severidadChart', 'edadChart', 'horasChart', 'diasChart', 'paretoChart', 'comparativaTipoChart', 'deptosChart', 'prevalenciaSucursalChart'];
            for (let id of canvasIds) {
                const canvas = document.getElementById(id);
                if (canvas && canvas.offsetWidth > 0) charts[id] = canvas.toDataURL('image/png');
            }
            const kpis = [];
            document.querySelectorAll('.kpi-card').forEach(card => {
                const title = card.querySelector('.card-title')?.childNodes[0]?.nodeValue?.trim() || '';
                const value = card.querySelector('h2')?.innerText?.trim() || '';
                if (title && value) kpis.push({title, value});
            });
            const topSucursales = [];
            document.querySelectorAll('.tabla-top-sucursales tbody tr').forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 3) topSucursales.push({ sucursal: cells[0].innerText, total: cells[1].innerText, prom: cells[2].innerText });
            });
            const topEmpleados = [];
            document.querySelectorAll('.tabla-top-empleados tbody tr').forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length >= 3) topEmpleados.push({ num: cells[0].innerText, nombre: cells[1].innerText, total: cells[2].innerText });
            });
            const secciones = seccionesBase.map(sec => {
                if (sec.tipo === 'kpi') return { ...sec, datos: kpis };
                else if (sec.titulo === 'Top 5 Sucursales') return { ...sec, datos: topSucursales, columnas: ['Sucursal', 'Total', 'Prom. Mensual'] };
                else if (sec.titulo === 'Top 5 Empleados' || sec.titulo === 'Top 5 Empleados con más enfermedades') return { ...sec, datos: topEmpleados, columnas: ['#', 'Nombre', 'Total'] };
                return sec;
            });
            const formData = new FormData();
            formData.append('charts', JSON.stringify(charts));
            formData.append('secciones', JSON.stringify(secciones));
            formData.append('sucursal', '<?= $nombreSucursalSeleccionada ?>');
            formData.append('tipo', '<?= $tipo_filtro ?>');
            const response = await fetch('<?= BASE_URL ?>generar_pdf_dashboard.php', { method: 'POST', body: formData });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const blob = await response.blob();
            if (blob.size === 0) throw new Error('PDF vacío');
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'dashboard_syso_<?= date('Ymd_His') ?>.pdf';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            a.remove();
        } catch (e) {
            console.error('Error PDF:', e);
            alert('Error al generar el informe PDF.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-file-pdf"></i> Exportar PDF';
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>