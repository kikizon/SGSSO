<?php
/** Tablero 6S: KPIs, evolución semanal, radar por categoría, por departamento, fallas. */
function color6s_prom($p) {
    if ($p === null) return 'secondary';
    if ($p >= 85) return 'success';
    if ($p >= 70) return 'info';
    if ($p >= 50) return 'warning';
    return 'danger';
}
?>
<div class="row mb-4">
    <div class="col-md-3"><div class="card bg-primary text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Auditorías finalizadas <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Total en el alcance seleccionado"></i></h6><h2 class="display-6"><?= (int)$kpi6s['total'] ?></h2></div></div></div>
    <div class="col-md-3"><div class="card bg-<?= color6s_prom($kpi6s['prom']) ?> text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Cumplimiento promedio <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Promedio de evaluación total"></i></h6><h2 class="display-6"><?= $kpi6s['prom'] !== null ? $kpi6s['prom'] . '%' : '—' ?></h2></div></div></div>
    <div class="col-md-3"><div class="card bg-info text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Esta semana <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Auditorías de la semana en curso"></i></h6><h2 class="display-6"><?= $auditoriasSemana ?></h2></div></div></div>
    <div class="col-md-3"><div class="card bg-dark text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Meta <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Objetivo de cumplimiento"></i></h6><h2 class="display-6"><?= $meta_6s ?>%</h2></div></div></div>
</div>

<!-- Evolución semanal + Radar por categoría -->
<div class="row">
    <div class="col-md-7 mb-4"><div class="card"><div class="card-header">Evolución semanal (cumplimiento %)</div><div class="card-body"><canvas id="evol6sChart" style="height:280px;width:100%;"></canvas></div></div></div>
    <div class="col-md-5 mb-4"><div class="card h-100"><div class="card-header">Cumplimiento por categoría 6S</div><div class="card-body"><canvas id="cat6sChart" style="height:280px"></canvas></div></div></div>
</div>

<!-- Por departamento + (sucursal o fallas) -->
<div class="row">
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header">Cumplimiento por departamento</div><div class="card-body"><canvas id="dep6sChart" style="height:300px"></canvas></div></div></div>
    <div class="col-md-6 mb-4">
        <?php if (!empty($suc6s)): ?>
        <div class="card h-100"><div class="card-header">Cumplimiento por sucursal</div><div class="card-body table-responsive"><table class="table table-sm"><thead><tr><th>Sucursal</th><th>Promedio</th><th>Auditorías</th></tr></thead><tbody>
            <?php foreach ($suc6s as $s): ?>
            <tr><td><?= htmlspecialchars($s['nombre']) ?></td><td><span class="badge bg-<?= color6s_prom($s['prom']) ?>"><?= $s['prom'] !== null ? $s['prom'].'%' : '—' ?></span></td><td><?= (int)$s['n'] ?></td></tr>
            <?php endforeach; ?>
        </tbody></table></div></div>
        <?php else: ?>
        <div class="card h-100"><div class="card-header">Resumen</div><div class="card-body"><p class="text-muted mb-0">Selecciona "Todas" las sucursales (como administrador) para ver el comparativo entre sucursales.</p></div></div>
        <?php endif; ?>
    </div>
</div>

<!-- Criterios con más fallas (observaciones de auditorías ya realizadas) -->
<div class="row">
    <div class="col-12 mb-4"><div class="card"><div class="card-header"><i class="fas fa-triangle-exclamation"></i> Criterios con más fallas</div>
        <div class="card-body table-responsive">
            <?php if (empty($fallas6s)): ?>
                <p class="text-muted mb-0">Sin fallas registradas en el alcance seleccionado.</p>
            <?php else: ?>
            <table class="table table-sm table-hover">
                <thead><tr><th>Categoría</th><th>Criterio</th><th class="text-center">Fallas</th><th class="text-center">Evaluado</th><th class="text-center">Prom.</th></tr></thead>
                <tbody>
                    <?php foreach ($fallas6s as $f): ?>
                    <tr>
                        <td><?= htmlspecialchars($f['categoria']) ?></td>
                        <td><?= htmlspecialchars($f['texto']) ?></td>
                        <td class="text-center"><span class="badge bg-danger"><?= (int)$f['fallas'] ?></span></td>
                        <td class="text-center"><?= (int)$f['evaluado'] ?></td>
                        <td class="text-center"><span class="badge bg-<?= color6s_prom($f['prom']) ?>"><?= $f['prom'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div></div>
</div>
