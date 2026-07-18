<?php
/**
 * Filtros del dashboard (v2 — soporta tableros: seguridad / 6s / cursos).
 *
 * Comunes a todos los tableros: $tablero, $sucursal_id, $sucursales.
 * Específicos de Seguridad: tipo, mes, cadenas de filtro y $rango_datos.
 *
 * Seguridad: sucursal y mes se castean a entero antes de interpolar.
 */

$horas_hombre = HORAS_HOMBRE_MES;

// --- Tablero (whitelist) ---
$tablero = $_GET['tablero'] ?? 'seguridad';
if (!in_array($tablero, ['seguridad', '6s', 'cursos'], true)) {
    $tablero = 'seguridad';
}

// --- Sucursal (común) ---
if ($usuario_rol !== 'admin') {
    $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 AND id IN ($usuario_sucursales_sql) ORDER BY nombre")->fetchAll();
    $permitidas = array_map(fn($s) => (int)$s['id'], $sucursales);
    $sel = ($_GET['sucursal_id'] ?? '') !== '' ? (int) $_GET['sucursal_id'] : 0;
    $sucursal_id = ($sel !== 0 && in_array($sel, $permitidas, true)) ? $sel : ''; // '' = todas mis sucursales
} else {
    $sucursal_id = ($_GET['sucursal_id'] ?? '') !== '' ? (int) $_GET['sucursal_id'] : '';
    $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();
}

$nombreSucursalSeleccionada = $sucursal_id
    ? ($pdo->query("SELECT nombre FROM sucursales WHERE id = " . (int)$sucursal_id)->fetchColumn() ?: 'Todas')
    : ($usuario_rol === 'admin' ? 'Todas' : 'Todas mis sucursales');

// Ámbito de sucursales para las consultas del dashboard (multi-sucursal)
if ($usuario_rol === 'admin') {
    $scopeSucursalSql = $sucursal_id !== '' ? (string)(int)$sucursal_id : '';           // '' = todas
} else {
    $scopeSucursalSql = $sucursal_id !== '' ? (string)(int)$sucursal_id : $usuario_sucursales_sql; // '' = todas las mías
}

$meses_es = ['', 'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

// ============================================================
// A PARTIR DE AQUÍ: SOLO PARA EL TABLERO DE SEGURIDAD
// ============================================================
if ($tablero === 'seguridad') {

    // Tipo (whitelist)
    $tipo_filtro = $_GET['tipo'] ?? 'acto_inseguro';
    if (!in_array($tipo_filtro, ['acto_inseguro', 'accidente', 'enfermedad_cronica'], true)) {
        $tipo_filtro = 'acto_inseguro';
    }

    // Mes (YYYY-MM, opcional)
    $mes = $_GET['mes'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) { $mes = ''; }
    $mes_anio = $mes ? (int) substr($mes, 0, 4) : null;
    $mes_num  = $mes ? (int) substr($mes, 5, 2) : null;

    $filtroSucursalReportes  = $scopeSucursalSql !== '' ? "AND r.sucursal_id IN ($scopeSucursalSql)" : "";
    $filtroSucursalEmpleados = $scopeSucursalSql !== '' ? "AND e.sucursal_id IN ($scopeSucursalSql)" : "";
    $filtroTipo = ($tipo_filtro !== 'enfermedad_cronica') ? "AND r.tipo = '$tipo_filtro'" : "";
    $filtroMesReportes = $mes ? "AND YEAR(r.fecha) = $mes_anio AND MONTH(r.fecha) = $mes_num" : "";
    $filtroMesEnf      = $mes ? "AND YEAR(ee.fecha_registro) = $mes_anio AND MONTH(ee.fecha_registro) = $mes_num" : "";

    if ($tipo_filtro === 'enfermedad_cronica') {
        $sqlRango = "SELECT MIN(ee.fecha_registro) AS mn, MAX(ee.fecha_registro) AS mx
                     FROM empleado_enfermedad ee
                     JOIN empleados e ON ee.empleado_id = e.id AND e.activo = 1
                     WHERE 1=1 $filtroSucursalEmpleados $filtroMesEnf";
    } else {
        $sqlRango = "SELECT MIN(r.fecha) AS mn, MAX(r.fecha) AS mx
                     FROM reportes r
                     WHERE 1=1 $filtroSucursalReportes $filtroTipo $filtroMesReportes";
    }
    $rango_datos = $pdo->query($sqlRango)->fetch() ?: ['mn' => null, 'mx' => null];
    $etiquetaMes = $mes ? ($meses_es[$mes_num] . ' ' . $mes_anio) : 'Todo el histórico';
} else {
    // Para 6s/cursos el tipo no aplica; se deja definido por compatibilidad.
    $tipo_filtro = '';
}
