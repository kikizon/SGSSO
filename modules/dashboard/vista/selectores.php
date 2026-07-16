<?php
/** Barra de filtros multi-tablero (seguridad / 6s / cursos). */
?>
<div class="d-flex justify-content-between flex-wrap mb-2 border-bottom pb-2">
    <h1 class="mb-0">Dashboard SYSO</h1>
    <form method="get" class="row g-2 align-items-end">

        <!-- Selector de TABLERO -->
        <div class="col-auto">
            <label class="form-label mb-0 small">Tablero</label>
            <select name="tablero" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="seguridad" <?= $tablero == 'seguridad' ? 'selected' : '' ?>>Seguridad</option>
                <option value="6s"        <?= $tablero == '6s' ? 'selected' : '' ?>>Auditorías 6S</option>
                <option value="cursos"    <?= $tablero == 'cursos' ? 'selected' : '' ?>>Cursos</option>
            </select>
        </div>

        <!-- Sucursal (común, salvo supervisor) -->
        <?php if ($usuario_rol !== 'supervisor'): ?>
        <div class="col-auto">
            <label class="form-label mb-0 small">Sucursal</label>
            <select name="sucursal_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todas</option>
                <?php foreach ($sucursales as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $sucursal_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php else: ?><input type="hidden" name="sucursal_id" value="<?= $sucursal_id ?>"><?php endif; ?>

        <?php if ($tablero === 'seguridad'): ?>
            <div class="col-auto">
                <label class="form-label mb-0 small">Tipo</label>
                <select name="tipo" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="acto_inseguro"     <?= $tipo_filtro == 'acto_inseguro' ? 'selected' : '' ?>>Actos Inseguros</option>
                    <option value="accidente"         <?= $tipo_filtro == 'accidente' ? 'selected' : '' ?>>Accidentes</option>
                    <option value="enfermedad_cronica"<?= $tipo_filtro == 'enfermedad_cronica' ? 'selected' : '' ?>>Enfermedades Crónicas</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-0 small">Mes</label>
                <input type="month" name="mes" value="<?= htmlspecialchars($mes) ?>" class="form-control form-control-sm" onchange="this.form.submit()">
            </div>
            <?php if ($mes): ?>
            <div class="col-auto">
                <label class="form-label mb-0 small d-block">&nbsp;</label>
                <a href="?tablero=seguridad&sucursal_id=<?= $sucursal_id ?>&tipo=<?= $tipo_filtro ?>" class="btn btn-outline-secondary btn-sm">Quitar mes</a>
            </div>
            <?php endif; ?>
            <div class="col-auto">
                <label class="form-label mb-0 small d-block">&nbsp;</label>
                <a href="exportar_dashboard_excel.php?sucursal_id=<?= $sucursal_id ?>&tipo=<?= $tipo_filtro ?>&mes=<?= $mes ?>" class="btn btn-success btn-sm no-spinner"><i class="fas fa-file-excel"></i> Excel</a>
                <button type="button" id="btnExportarPDF" class="btn btn-danger btn-sm"><i class="fas fa-file-pdf"></i> PDF</button>
            </div>

        <?php elseif ($tablero === '6s'): ?>
            <div class="col-auto">
                <label class="form-label mb-0 small">Departamento</label>
                <select name="departamento_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($departamentos6s as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $f_depto_6s == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Leyenda por tablero -->
<?php if ($tablero === 'seguridad'): ?>
<p class="text-muted small mb-3">
    <i class="fas fa-circle-info"></i>
    Periodo: <strong><?= htmlspecialchars($etiquetaMes) ?></strong>.
    <?php if (!empty($rango_datos['mn'])): ?>
        Datos desde <strong><?= format_date_es($rango_datos['mn']) ?></strong> hasta <strong><?= format_date_es($rango_datos['mx']) ?></strong>.
    <?php else: ?>
        Sin registros en el periodo seleccionado.
    <?php endif; ?>
</p>
<?php elseif ($tablero === '6s'): ?>
<p class="text-muted small mb-3">
    <i class="fas fa-circle-info"></i>
    Solo auditorías <strong>finalizadas</strong>; criterios sin contestar cuentan como 0. Comparación <strong>semanal</strong> (últimas 12 semanas).
    <?php if (!empty($rango6s['mn'])): ?>
        Datos desde <strong><?= format_date_es($rango6s['mn']) ?></strong> hasta <strong><?= format_date_es($rango6s['mx']) ?></strong>.
    <?php else: ?>
        Aún no hay auditorías finalizadas en el alcance seleccionado.
    <?php endif; ?>
</p>
<?php else: ?>
<p class="text-muted small mb-3">
    <i class="fas fa-circle-info"></i>
    Cobertura calculada sobre el <strong>alcance</strong> de cada curso (quiénes deben tomarlo). Un curso cuenta como cubierto si está vigente o por vencer; se avisa <strong><?= $aviso_dias ?> días</strong> antes del vencimiento.
</p>
<?php endif; ?>
