<?php
/** Tablero Cursos: KPIs, cobertura por curso, distribución de estatus, cursos por atender. */
?>
<div class="row mb-4">
    <div class="col-md-2"><div class="card bg-primary text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Cursos activos</h6><h2 class="display-6"><?= $totalCursos ?></h2></div></div></div>
    <div class="col-md-2"><div class="card bg-<?= $coberturaGlobal >= 85 ? 'success' : ($coberturaGlobal >= 60 ? 'warning' : 'danger') ?> text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Cobertura global <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Vigentes / requeridos, sobre todos los cursos"></i></h6><h2 class="display-6"><?= $coberturaGlobal ?>%</h2></div></div></div>
    <div class="col-md-2"><div class="card bg-success text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Vigentes <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Requerimientos cubiertos (incluye por vencer)"></i></h6><h2 class="display-6"><?= $sumVigentes ?></h2></div></div></div>
    <div class="col-md-2"><div class="card bg-warning text-dark h-100 kpi-card"><div class="card-body"><h6 class="card-title">Por vencer</h6><h2 class="display-6"><?= $sumPorVencer ?></h2></div></div></div>
    <div class="col-md-2"><div class="card bg-danger text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Vencidos</h6><h2 class="display-6"><?= $sumVencidos ?></h2></div></div></div>
    <div class="col-md-2"><div class="card bg-secondary text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Sin tomar</h6><h2 class="display-6"><?= $sumNoTomaron ?></h2></div></div></div>
</div>

<div class="row">
    <div class="col-md-8 mb-4"><div class="card"><div class="card-header">Cobertura por curso (15 con menor cobertura)</div><div class="card-body"><canvas id="cobCursosChart" style="height:380px;width:100%;"></canvas></div></div></div>
    <div class="col-md-4 mb-4"><div class="card h-100"><div class="card-header">Distribución de estatus</div><div class="card-body d-flex align-items-center justify-content-center"><div style="position:relative;width:100%;height:340px;"><canvas id="distCursosChart" style="display:block;width:100%;height:100%;"></canvas></div></div></div></div>
</div>

<div class="row">
    <div class="col-12 mb-4"><div class="card"><div class="card-header"><i class="fas fa-triangle-exclamation"></i> Cursos que requieren atención (vencidos / por vencer)</div>
        <div class="card-body table-responsive">
            <?php if (empty($atencion)): ?>
                <p class="text-muted mb-0">No hay cursos con vencimientos pendientes en el alcance seleccionado.</p>
            <?php else: ?>
            <table class="table table-sm table-hover">
                <thead><tr><th>Curso/Formato</th><th class="text-center">En alcance</th><th class="text-center">Vigentes</th><th class="text-center">Por vencer</th><th class="text-center">Vencidos</th><th class="text-center">Cobertura</th></tr></thead>
                <tbody>
                    <?php foreach ($atencion as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['nombre']) ?></td>
                        <td class="text-center"><?= $c['alcance'] ?></td>
                        <td class="text-center"><?= $c['vigentes'] ?></td>
                        <td class="text-center"><?php if ($c['porVencer']>0): ?><span class="badge bg-warning text-dark"><?= $c['porVencer'] ?></span><?php else: ?>0<?php endif; ?></td>
                        <td class="text-center"><?php if ($c['vencidos']>0): ?><span class="badge bg-danger"><?= $c['vencidos'] ?></span><?php else: ?>0<?php endif; ?></td>
                        <td class="text-center"><span class="badge bg-<?= $c['pct'] >= 85 ? 'success' : ($c['pct'] >= 60 ? 'warning text-dark' : 'danger') ?>"><?= $c['pct'] ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="text-muted small mb-0">Para ver el detalle por empleado de un curso, usa la <strong>cobertura</strong> en el módulo de Cursos.</p>
            <?php endif; ?>
        </div>
    </div></div>
</div>
