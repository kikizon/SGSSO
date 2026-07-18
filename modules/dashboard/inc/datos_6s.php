<?php
/**
 * Datos del tablero 6S (dashboard) · modelo por sucursal.
 * "por departamento" se lee de auditorias_6s_departamentos.
 * La cuenta de auditorías es por sucursal+semana (cabecera).
 * Conserva los nombres de variables que usa vista/tablero_6s.php.
 */

$es_admin   = ($usuario_rol === 'admin');
$f_depto_6s = ($_GET['departamento_id'] ?? '') !== '' ? (int) $_GET['departamento_id'] : '';
$meta_6s    = 85;

$where = ["a.estado = 'finalizada'"]; $params = [];
if ($scopeSucursalSql !== '') { $where[] = "a.sucursal_id IN ($scopeSucursalSql)"; }
$where_sql = 'WHERE ' . implode(' AND ', $where);

$dep = $f_depto_6s ? (int)$f_depto_6s : 0;

// KPIs (si hay depto: desde su puntaje; si no: global de cabecera)
if ($dep) {
    $st = $pdo->prepare("SELECT COUNT(*) AS total, ROUND(AVG(ad.evaluacion_total),1) AS prom, MIN(a.fecha) AS mn, MAX(a.fecha) AS mx
                         FROM auditorias_6s a JOIN auditorias_6s_departamentos ad ON ad.auditoria_id = a.id AND ad.departamento_id = ? $where_sql");
    $st->execute(array_merge([$dep], $params));
} else {
    $st = $pdo->prepare("SELECT COUNT(*) AS total, ROUND(AVG(a.evaluacion_total),1) AS prom, MIN(a.fecha) AS mn, MAX(a.fecha) AS mx
                         FROM auditorias_6s a $where_sql");
    $st->execute($params);
}
$kpi6s = $st->fetch() ?: ['total'=>0,'prom'=>null,'mn'=>null,'mx'=>null];

$stSem = $pdo->prepare("SELECT COUNT(*) FROM auditorias_6s a $where_sql AND YEARWEEK(a.fecha,3) = YEARWEEK(CURDATE(),3)");
$stSem->execute($params);
$auditoriasSemana = (int) $stSem->fetchColumn();

// Evolución semanal (últimas 12 semanas)
if ($dep) {
    $st = $pdo->prepare("SELECT YEARWEEK(a.fecha,3) AS g, MIN(a.fecha) AS inicio, ROUND(AVG(ad.evaluacion_total),1) AS prom
                         FROM auditorias_6s a JOIN auditorias_6s_departamentos ad ON ad.auditoria_id = a.id AND ad.departamento_id = ? $where_sql
                         GROUP BY g ORDER BY MIN(a.fecha)");
    $st->execute(array_merge([$dep], $params));
} else {
    $st = $pdo->prepare("SELECT YEARWEEK(a.fecha,3) AS g, MIN(a.fecha) AS inicio, ROUND(AVG(a.evaluacion_total),1) AS prom
                         FROM auditorias_6s a $where_sql GROUP BY g ORDER BY MIN(a.fecha)");
    $st->execute($params);
}
$evol = array_slice($st->fetchAll(), -12);
$lblEvo6s = []; $datEvo6s = [];
foreach ($evol as $r) { $lblEvo6s[] = 'Sem ' . date('W', strtotime($r['inicio'])); $datEvo6s[] = $r['prom'] !== null ? (float)$r['prom'] : 0; }
if (empty($lblEvo6s)) { $lblEvo6s = ['Sin datos']; $datEvo6s = [0]; }

// Radar por categoría
$catWhere = $where_sql . ($dep ? ' AND r.departamento_id = ?' : '');
$catParams = $dep ? array_merge($params, [$dep]) : $params;
$st = $pdo->prepare("SELECT cat.nombre, ROUND(AVG(COALESCE(r.puntaje,0)),1) AS prom
                     FROM auditorias_6s a
                     JOIN auditorias_6s_respuestas r ON r.auditoria_id = a.id
                     JOIN criterios_6s cr ON cr.id = r.criterio_id
                     JOIN categorias_6s cat ON cat.id = cr.categoria_id
                     $catWhere GROUP BY cat.id ORDER BY cat.orden");
$st->execute($catParams);
$cat6s = $st->fetchAll();
$lblCat6s = array_column($cat6s, 'nombre');
$datCat6s = array_map(fn($r) => (float)$r['prom'], $cat6s);
if (empty($lblCat6s)) { $lblCat6s = ['Sin datos']; $datCat6s = [0]; }

// Por departamento (desde tabla de departamentos de la auditoría)
$st = $pdo->prepare("SELECT d.nombre, ROUND(AVG(ad.evaluacion_total),1) AS prom, COUNT(*) AS n
                     FROM auditorias_6s a
                     JOIN auditorias_6s_departamentos ad ON ad.auditoria_id = a.id
                     JOIN departamentos d ON d.id = ad.departamento_id
                     $where_sql " . ($dep ? ' AND ad.departamento_id = ?' : '') . "
                     GROUP BY d.id ORDER BY prom DESC");
$st->execute($dep ? array_merge($params, [$dep]) : $params);
$dep6s = $st->fetchAll();
$lblDep6s = array_column($dep6s, 'nombre');
$datDep6s = array_map(fn($r) => $r['prom'] !== null ? (float)$r['prom'] : 0, $dep6s);
if (empty($lblDep6s)) { $lblDep6s = ['Sin datos']; $datDep6s = [0]; }

// Por sucursal (admin sin filtro)
$suc6s = [];
if (!$sucursal_id && ($es_admin || count($usuario_sucursales) > 1)) {
    $st = $pdo->prepare("SELECT s.nombre, ROUND(AVG(a.evaluacion_total),1) AS prom, COUNT(*) AS n
                         FROM auditorias_6s a JOIN sucursales s ON s.id = a.sucursal_id
                         $where_sql GROUP BY s.id ORDER BY prom DESC");
    $st->execute($params);
    $suc6s = $st->fetchAll();
}

// Criterios con más fallas
$fallasWhere = $where_sql . ($dep ? ' AND r.departamento_id = ?' : '');
$fallasParams = $dep ? array_merge($params, [$dep]) : $params;
$st = $pdo->prepare("SELECT cat.nombre AS categoria, cr.texto,
                            SUM(CASE WHEN r.puntaje < 100 THEN 1 ELSE 0 END) AS fallas,
                            COUNT(*) AS evaluado, ROUND(AVG(r.puntaje),1) AS prom
                     FROM auditorias_6s a
                     JOIN auditorias_6s_respuestas r ON r.auditoria_id = a.id AND r.calificacion IS NOT NULL
                     JOIN criterios_6s cr ON cr.id = r.criterio_id
                     JOIN categorias_6s cat ON cat.id = cr.categoria_id
                     $fallasWhere GROUP BY cr.id HAVING fallas > 0 ORDER BY fallas DESC, prom ASC LIMIT 10");
$st->execute($fallasParams);
$fallas6s = $st->fetchAll();

$departamentos6s = $pdo->query("SELECT DISTINCT d.id, d.nombre FROM departamentos d
                                JOIN criterios_6s_departamento cd ON cd.departamento_id = d.id
                                JOIN criterios_6s cr ON cr.id = cd.criterio_id AND cr.activo = 1
                                WHERE d.activo = 1 ORDER BY d.nombre")->fetchAll();

$rango6s = ['mn' => $kpi6s['mn'] ?? null, 'mx' => $kpi6s['mx'] ?? null];
