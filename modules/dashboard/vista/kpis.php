<?php
/** Tarjetas KPI. Se eliminó "Total Días Incap.". Cuando hay mes seleccionado se oculta "Este Mes" (redundante). */
?>
<div class="row mb-4">
<?php if ($tipo_filtro == 'enfermedad_cronica'): ?>

    <div class="col-md-2"><div class="card bg-primary text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Total Empleados <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Empleados con al menos una enfermedad crónica"></i></h6><h2 class="display-6"><?= $totalEmpleadosEnfermedad ?></h2></div></div></div>
    <div class="col-md-2"><div class="card bg-success text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Prevalencia <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Porcentaje de empleados activos con alguna enfermedad"></i></h6><h2 class="display-6"><?= $prevalencia ?>%</h2></div></div></div>
    <div class="col-md-2"><div class="card bg-info text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Comorbilidad <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="% de empleados con 2 o más enfermedades"></i></h6><h2 class="display-6"><?= $indiceComorbilidad ?>%</h2></div></div></div>
    <div class="col-md-2"><div class="card bg-warning text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Prom. Enf/Empl <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Promedio de enfermedades por empleado (entre los que tienen)"></i></h6><h2 class="display-6"><?= $promedioEnf ?></h2></div></div></div>
    <div class="col-md-2"><div class="card bg-secondary text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Nuevos este mes <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Registros nuevos en el mes calendario actual"></i></h6><h2 class="display-6"><?= $nuevosMes ?></h2></div></div></div>

<?php else: ?>

    <div class="col-md-2"><div class="card bg-primary text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Total Reportes <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Reportes del tipo y periodo seleccionados"></i></h6><h2 class="display-6"><?= $totalReportes ?></h2></div></div></div>

    <?php if (!$mes): ?>
    <div class="col-md-2"><div class="card bg-success text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Este Mes <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Reportes del mes calendario actual"></i></h6><h2 class="display-6"><?= $reportesMes ?></h2></div></div></div>
    <?php endif; ?>

    <div class="col-md-2"><div class="card bg-info text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Empleados <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Número de empleados activos"></i></h6><h2 class="display-6"><?= $totalEmpleados ?></h2></div></div></div>

    <?php if ($tipo_filtro == 'accidente'): ?>
        <div class="col-md-2"><div class="card bg-danger text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">DSA <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Días Sin Accidentes (histórico)"></i></h6><h2 class="display-6"><?= $dsa ?></h2></div></div></div>
        <div class="col-md-2"><div class="card bg-success text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Costo Atenciones <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Costo total de atenciones particulares en el periodo"></i></h6><h2 class="display-6">$<?= number_format($costoTotalAtenciones, 2) ?></h2></div></div></div>
        <div class="col-md-2"><div class="card bg-info text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">% ST7 <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="% de atenciones IMSS clasificadas como ST7"></i></h6><h2 class="display-6"><?= $porcentajeST7 ?>%</h2></div></div></div>
        <div class="col-md-2"><div class="card bg-warning text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Prom. Días Incap. <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Promedio de días de incapacidad por accidente con incapacidad"></i></h6><h2 class="display-6"><?= $promedioDiasIncapacidad ?></h2></div></div></div>
        <div class="col-md-2"><div class="card bg-primary text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Costo Prom. Atención <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Costo promedio por atención particular"></i></h6><h2 class="display-6">$<?= number_format($costoPromedioAtencion, 2) ?></h2></div></div></div>
        <div class="col-md-2"><div class="card bg-danger text-white h-100 kpi-card"><div class="card-body"><h6 class="card-title">Incap. Prolongada <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="% de accidentes con más de 30 días perdidos"></i></h6><h2 class="display-6"><?= $tasaIncapacidadProlongada ?>%</h2></div></div></div>
    <?php endif; ?>

<?php endif; ?>
</div>
