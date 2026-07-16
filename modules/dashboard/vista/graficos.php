<?php /** Filas de gráficos. Sin Mapa de Calor ni Comparativa Actos vs Accidentes. */ ?>

<?php if ($tipo_filtro == 'enfermedad_cronica'): ?>

<!-- Tendencia + Top 10 enfermedades -->
<div class="row">
    <div class="col-md-8 mb-4"><div class="card"><div class="card-header">Nuevos registros (últimos 6 meses)</div><div class="card-body"><canvas id="tendenciaChart" style="height:250px;width:100%;"></canvas></div></div></div>
    <div class="col-md-4 mb-4"><div class="card h-100"><div class="card-header">Top 10 Enfermedades</div><div class="card-body d-flex align-items-center justify-content-center"><div style="position:relative;width:100%;height:460px;"><canvas id="catalogoChart" style="display:block;width:100%;height:100%;"></canvas></div></div></div></div>
</div>

<!-- Prevalencia por sucursal + Por departamento -->
<div class="row">
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header">Prevalencia por Sucursal</div><div class="card-body"><canvas id="prevalenciaSucursalChart" style="height:250px"></canvas></div></div></div>
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header">Enfermedades por Departamento</div><div class="card-body"><canvas id="enfDeptoChart" style="height:250px"></canvas></div></div></div>
</div>

<!-- Por rango de edad + Pareto -->
<div class="row">
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header">Enfermedades por Rango de Edad</div><div class="card-body"><canvas id="enfEdadChart" style="height:250px"></canvas></div></div></div>
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header"><i class="fas fa-chart-bar"></i> Diagrama de Pareto</div><div class="card-body"><canvas id="paretoChart" style="height:300px"></canvas></div></div></div>
</div>

<!-- Top empleados -->
<div class="row">
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header">Top 5 Empleados con más enfermedades</div><div class="card-body table-responsive"><table class="table table-sm tabla-top-empleados"><thead><tr><th>#</th><th>Nombre</th><th>Total</th></tr></thead><tbody><?php foreach ($topEmp as $e): ?><tr><td><?= $e['numero_empleado'] ?></td><td><?= htmlspecialchars($e['nombre']) ?></td><td><?= $e['total'] ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div>

<?php else: ?>

<!-- Tendencia + Catálogo (rueda) -->
<div class="row">
    <div class="col-md-8 mb-4"><div class="card"><div class="card-header">Tendencia últimos 6 meses</div><div class="card-body"><canvas id="tendenciaChart" style="height:250px;width:100%;"></canvas></div></div></div>
    <div class="col-md-4 mb-4"><div class="card h-100"><div class="card-header"><?= htmlspecialchars($tituloCatalogo) ?></div><div class="card-body d-flex align-items-center justify-content-center"><div style="position:relative;width:100%;height:460px;"><canvas id="catalogoChart" style="display:block;width:100%;height:100%;"></canvas></div></div></div></div>
</div>

<!-- Comparativa anual + Severidad/Edad -->
<div class="row">
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header">Comparativa Año Actual vs Anterior</div><div class="card-body"><canvas id="comparativaAnualChart" style="height:250px"></canvas></div></div></div>
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header"><?= htmlspecialchars($tituloSeveridadEdad) ?></div><div class="card-body"><canvas id="<?= $chartIdSeveridadEdad ?>" style="height:250px"></canvas></div></div></div>
</div>

<!-- Hora del día + Día de la semana -->
<div class="row">
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header">Reportes por Hora del Día</div><div class="card-body"><canvas id="horasChart" style="height:250px"></canvas></div></div></div>
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header">Reportes por Día de la Semana</div><div class="card-body"><canvas id="diasChart" style="height:250px"></canvas></div></div></div>
</div>

<!-- Pareto + Top Departamentos -->
<div class="row">
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header"><i class="fas fa-chart-bar"></i> Diagrama de Pareto (Top 10 causas)</div><div class="card-body"><canvas id="paretoChart" style="height:300px"></canvas></div></div></div>
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header">Top 5 Departamentos</div><div class="card-body"><canvas id="deptosChart" style="height:300px"></canvas></div></div></div>
</div>

<!-- Top Sucursales + Top Empleados -->
<div class="row">
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header">Top Sucursales</div><div class="card-body table-responsive"><table class="table table-sm tabla-top-sucursales"><thead><tr><th>Sucursal</th><th>Total</th><th>Prom. Mensual</th></tr></thead><tbody><?php foreach ($topSuc as $s): ?><tr><td><?= htmlspecialchars($s['nombre']) ?></td><td><?= $s['total'] ?></td><td><?= $s['prom'] ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
    <div class="col-md-6 mb-4"><div class="card"><div class="card-header">Top 5 Empleados</div><div class="card-body table-responsive"><table class="table table-sm tabla-top-empleados"><thead><tr><th>#</th><th>Nombre</th><th>Total</th></tr></thead><tbody><?php foreach ($topEmp as $e): ?><tr><td><?= $e['numero_empleado'] ?></td><td><?= htmlspecialchars($e['nombre']) ?></td><td><?= $e['total'] ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div>

<?php endif; ?>
