<?php
/**
 * Datos del tablero de Cursos.
 * Calcula cobertura y vigencia con la MISMA lógica de info_curso.php, pero
 * agregada a nivel sistema. Respeta el alcance (curso_asignaciones) de cada
 * curso y el filtro de sucursal. Vigencia: cursos.vigencia_meses + umbral
 * configurable (cursos_aviso_vencimiento_dias).
 */

// Umbral de aviso (días)
$aviso_dias = 30;
try {
    $c = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'cursos_aviso_vencimiento_dias'")->fetchColumn();
    if ($c !== false && (int)$c > 0) $aviso_dias = (int)$c;
} catch (Throwable $e) { /* default */ }

$hoy = new DateTime('today');

// Filtro de sucursal sobre el alcance de empleados (parametrizado)
$sucSqlEmp = $sucursal_id ? " AND e.sucursal_id = ?" : "";
$sucParam  = $sucursal_id ? [$sucursal_id] : [];

/** Construye la condición de alcance (quiénes DEBEN tomar el curso). */
function condicionAlcance(array $asigs): string {
    $tipo = $asigs[0]['tipo_asignacion'] ?? 'todos';
    $ids = implode(',', array_map('intval', array_filter(array_column($asigs, 'entidad_id'), fn($v) => $v !== null)));
    if ($tipo === 'todos')        return '1=1';
    if ($tipo === 'sucursal')     return $ids !== '' ? "e.sucursal_id IN ($ids)" : '0=1';
    if ($tipo === 'departamento') return $ids !== '' ? "e.departamento_id IN ($ids)" : '0=1';
    return $ids !== '' ? "e.id IN ($ids)" : '0=1';
}

// Cursos activos
$cursos = $pdo->query("SELECT * FROM cursos WHERE activo = 1 ORDER BY nombre")->fetchAll();

$coberturaCursos = [];          // por curso: nombre, alcance, vigentes, porVencer, vencidos, noTomaron, pct
$sumAlcance = $sumVigentes = $sumPorVencer = $sumVencidos = $sumNoTomaron = 0;
$cursosConVencidos = 0; $cursosNoVencen = 0;

foreach ($cursos as $curso) {
    $asigs = $pdo->prepare("SELECT * FROM curso_asignaciones WHERE curso_id = ?");
    $asigs->execute([$curso['id']]);
    $asigs = $asigs->fetchAll();
    $cond = condicionAlcance($asigs);

    $vig = (array_key_exists('vigencia_meses', $curso) && $curso['vigencia_meses'] !== null)
        ? (int) $curso['vigencia_meses'] : null;
    if ($vig === null) $cursosNoVencen++;

    // Alcance (empleados activos que deben tomarlo)
    $alcance = (int) (function () use ($pdo, $cond, $sucSqlEmp, $sucParam) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM empleados e WHERE e.activo = 1 AND ($cond)$sucSqlEmp");
        $st->execute($sucParam);
        return $st->fetchColumn();
    })();

    // Empleados en alcance que YA lo tomaron, con su última fecha
    $st = $pdo->prepare("SELECT MAX(ec.fecha_realizacion) AS ultima
                         FROM empleado_curso ec
                         JOIN empleados e ON ec.empleado_id = e.id
                         WHERE ec.curso_id = ? AND e.activo = 1 AND ($cond)$sucSqlEmp
                         GROUP BY e.id");
    $st->execute(array_merge([$curso['id']], $sucParam));
    $ultimas = $st->fetchAll(PDO::FETCH_COLUMN);

    $vigentes = 0; $porVencer = 0; $vencidos = 0;
    foreach ($ultimas as $ultima) {
        if ($vig !== null && $ultima) {
            $fv = (new DateTime($ultima))->modify("+{$vig} months");
            if ($fv < $hoy) { $vencidos++; }
            elseif ((clone $hoy)->modify("+{$aviso_dias} days") >= $fv) { $porVencer++; $vigentes++; }
            else { $vigentes++; }
        } else {
            $vigentes++; // sin vigencia = no vence
        }
    }
    $tomaron = count($ultimas);
    $noTomaron = max(0, $alcance - $tomaron);
    $pct = $alcance > 0 ? round(($vigentes / $alcance) * 100, 1) : 0;
    if ($vencidos > 0) $cursosConVencidos++;

    $coberturaCursos[] = [
        'nombre' => $curso['nombre'], 'alcance' => $alcance, 'vigentes' => $vigentes,
        'porVencer' => $porVencer, 'vencidos' => $vencidos, 'noTomaron' => $noTomaron, 'pct' => $pct,
        'vence' => $vig !== null,
    ];
    $sumAlcance += $alcance; $sumVigentes += $vigentes; $sumPorVencer += $porVencer;
    $sumVencidos += $vencidos; $sumNoTomaron += $noTomaron;
}

// KPIs globales
$totalCursos       = count($cursos);
$coberturaGlobal   = $sumAlcance > 0 ? round(($sumVigentes / $sumAlcance) * 100, 1) : 0;

// Orden para gráfica: peor cobertura primero (más accionable)
$ordenCob = $coberturaCursos;
usort($ordenCob, fn($a, $b) => $a['pct'] <=> $b['pct']);
$ordenCob = array_slice($ordenCob, 0, 15);
$lblCursos = array_column($ordenCob, 'nombre');
$datCursos = array_column($ordenCob, 'pct');
if (empty($lblCursos)) { $lblCursos = ['Sin cursos']; $datCursos = [0]; }

// Distribución global de estatus (para dona)
$vigentesEstrictos = max(0, $sumVigentes - $sumPorVencer);
$distLabels = ['Vigentes', 'Por vencer', 'Vencidos', 'Sin tomar'];
$distData   = [$vigentesEstrictos, $sumPorVencer, $sumVencidos, $sumNoTomaron];

// Cursos que requieren atención (vencidos o por vencer), ordenados
$atencion = array_filter($coberturaCursos, fn($c) => $c['vencidos'] > 0 || $c['porVencer'] > 0);
usort($atencion, fn($a, $b) => ($b['vencidos'] <=> $a['vencidos']) ?: ($b['porVencer'] <=> $a['porVencer']));
$atencion = array_slice($atencion, 0, 10);
