<?php
/**
 * Datos para enfermedades crónicas.
 * El filtro por mes ($filtroMesEnf) se aplica sobre la fecha de registro de la enfermedad.
 * Nuevas gráficas: por departamento y por rango de edad.
 */

// ============================================================
// KPIs
// ============================================================
$totalEmpleadosEnfermedad = $pdo->query("SELECT COUNT(DISTINCT ee.empleado_id)
    FROM empleado_enfermedad ee JOIN empleados e ON ee.empleado_id = e.id
    WHERE e.activo = 1 $filtroSucursalEmpleados $filtroMesEnf")->fetchColumn();

$totalEmpleadosActivos = $pdo->query("SELECT COUNT(*) FROM empleados e WHERE activo = 1 $filtroSucursalEmpleados")->fetchColumn();
$prevalencia = $totalEmpleadosActivos > 0 ? round(($totalEmpleadosEnfermedad / $totalEmpleadosActivos) * 100, 1) : 0;

$comorbilidad = $pdo->query("SELECT COUNT(*) FROM (
        SELECT ee.empleado_id FROM empleado_enfermedad ee JOIN empleados e ON ee.empleado_id = e.id
        WHERE e.activo = 1 $filtroSucursalEmpleados $filtroMesEnf
        GROUP BY ee.empleado_id HAVING COUNT(*) >= 2) AS sub")->fetchColumn();
$indiceComorbilidad = $totalEmpleadosEnfermedad > 0 ? round(($comorbilidad / $totalEmpleadosEnfermedad) * 100, 1) : 0;

$promedioEnf = $pdo->query("SELECT AVG(cnt) FROM (
        SELECT COUNT(*) as cnt FROM empleado_enfermedad ee JOIN empleados e ON ee.empleado_id = e.id
        WHERE e.activo = 1 $filtroSucursalEmpleados $filtroMesEnf GROUP BY ee.empleado_id) AS sub")->fetchColumn();
$promedioEnf = $promedioEnf ? round($promedioEnf, 1) : 0;

$nuevosMes = $pdo->query("SELECT COUNT(*) FROM empleado_enfermedad ee JOIN empleados e ON ee.empleado_id = e.id
    WHERE e.activo = 1 $filtroSucursalEmpleados
    AND MONTH(ee.fecha_registro) = MONTH(CURDATE()) AND YEAR(ee.fecha_registro) = YEAR(CURDATE())")->fetchColumn();

// ============================================================
// Top 10 enfermedades (rueda) — solo con incidencia
// ============================================================
$topEnfData = $pdo->query("SELECT ec.nombre, COUNT(ee.empleado_id) as total
    FROM enfermedades_cronicas ec
    JOIN empleado_enfermedad ee ON ec.id = ee.enfermedad_id
    JOIN empleados e ON ee.empleado_id = e.id AND e.activo = 1
    WHERE ec.activo = 1 $filtroSucursalEmpleados $filtroMesEnf
    GROUP BY ec.id HAVING total > 0 ORDER BY total DESC LIMIT 10")->fetchAll();
$catalogoLabels = array_column($topEnfData, 'nombre');
$catalogoValores = array_column($topEnfData, 'total');
if (empty($catalogoValores)) { $catalogoLabels = ['Sin datos']; $catalogoValores = [0]; }

// ============================================================
// Prevalencia por sucursal
// ============================================================
$sucPrevData = $pdo->query("SELECT s.nombre, COUNT(DISTINCT ee.empleado_id) as total
    FROM sucursales s
    LEFT JOIN empleados e ON s.id = e.sucursal_id AND e.activo = 1
    LEFT JOIN empleado_enfermedad ee ON e.id = ee.empleado_id $filtroMesEnf
    WHERE s.activo = 1 GROUP BY s.id ORDER BY total DESC")->fetchAll();
$sucPrevLabels = array_column($sucPrevData, 'nombre');
$sucPrevValores = array_column($sucPrevData, 'total');
if (empty($sucPrevValores)) { $sucPrevLabels = ['Sin datos']; $sucPrevValores = [0]; }

// ============================================================
// NUEVO: Enfermedades por departamento
// ============================================================
$enfDeptoData = $pdo->query("SELECT d.nombre, COUNT(DISTINCT ee.empleado_id) as total
    FROM departamentos d
    LEFT JOIN empleados e ON e.departamento_id = d.id AND e.activo = 1 " . ($scopeSucursalSql !== '' ? "AND e.sucursal_id IN ($scopeSucursalSql)" : "") . "
    LEFT JOIN empleado_enfermedad ee ON ee.empleado_id = e.id $filtroMesEnf
    WHERE d.activo = 1 GROUP BY d.id HAVING total > 0 ORDER BY total DESC")->fetchAll();
$enfDeptoLabels = array_column($enfDeptoData, 'nombre');
$enfDeptoValores = array_column($enfDeptoData, 'total');
if (empty($enfDeptoValores)) { $enfDeptoLabels = ['Sin datos']; $enfDeptoValores = [0]; }

// ============================================================
// NUEVO: Enfermedades por rango de edad
// ============================================================
$enfEdadLabels = ['< 25', '25-34', '35-44', '45-54', '55+'];
$enfEdadValores = [0, 0, 0, 0, 0];
$sqlEnfEdad = "SELECT CASE
                WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) < 25 THEN 0
                WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) BETWEEN 25 AND 34 THEN 1
                WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) BETWEEN 35 AND 44 THEN 2
                WHEN TIMESTAMPDIFF(YEAR, e.fecha_nacimiento, CURDATE()) BETWEEN 45 AND 54 THEN 3
                ELSE 4 END as rango, COUNT(DISTINCT e.id) as total
            FROM empleados e
            JOIN empleado_enfermedad ee ON ee.empleado_id = e.id
            WHERE e.activo = 1 AND e.fecha_nacimiento IS NOT NULL $filtroSucursalEmpleados $filtroMesEnf
            GROUP BY rango";
foreach ($pdo->query($sqlEnfEdad) as $row) $enfEdadValores[$row['rango']] = (int) $row['total'];

// ============================================================
// Tendencia mensual de nuevos registros (6 meses, sin filtro por mes)
// ============================================================
$meses = []; $reportesPorMes = [];
for ($i = 5; $i >= 0; $i--) {
    $fecha = date('Y-m-01', strtotime("-$i months"));
    $meses[] = date('M Y', strtotime($fecha));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM empleado_enfermedad ee JOIN empleados e ON ee.empleado_id = e.id
        WHERE e.activo = 1 $filtroSucursalEmpleados AND YEAR(ee.fecha_registro)=YEAR(?) AND MONTH(ee.fecha_registro)=MONTH(?)");
    $stmt->execute([$fecha, $fecha]);
    $reportesPorMes[] = (int) $stmt->fetchColumn();
}

// ============================================================
// Pareto de enfermedades (mismas que la rueda)
// ============================================================
$paretoLabels = $catalogoLabels;
$paretoData = $catalogoValores;
$totalPareto = array_sum($paretoData);
$paretoAcumulado = [];
if ($totalPareto > 0) {
    $acum = 0;
    foreach ($paretoData as $v) { $acum += $v; $paretoAcumulado[] = round(($acum / $totalPareto) * 100, 1); }
} else {
    $paretoAcumulado = array_fill(0, count($paretoData), 0);
}

// ============================================================
// Top 5 empleados con más enfermedades
// ============================================================
$topEmp = $pdo->query("SELECT e.numero_empleado, e.nombre, COUNT(ee.enfermedad_id) as total
    FROM empleados e JOIN empleado_enfermedad ee ON e.id = ee.empleado_id
    WHERE e.activo = 1 $filtroSucursalEmpleados $filtroMesEnf
    GROUP BY e.id ORDER BY total DESC LIMIT 5")->fetchAll();
