<?php
require_once '../../includes/auth.php';
if (!isset($usuario_id)) exit;

$empleado_id = $_GET['id'] ?? 0;
$tipo_filtro = $_GET['tipo'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

$where = ["r.empleado_id = ?"];
$params = [$empleado_id];
if ($tipo_filtro) { $where[] = "r.tipo = ?"; $params[] = $tipo_filtro; }
if ($fecha_desde) { $where[] = "r.fecha >= ?"; $params[] = $fecha_desde; }
if ($fecha_hasta) { $where[] = "r.fecha <= ?"; $params[] = $fecha_hasta; }

$sql = "SELECT r.*, 
               CASE WHEN r.tipo = 'acto_inseguro' THEN a.descripcion ELSE ta.descripcion END as catalogo_descripcion,
               am.descripcion as atencion_medica,
               u.nombre_completo as reportado_por
        FROM reportes r
        LEFT JOIN actos_inseguros a ON r.acto_inseguro_id = a.id AND r.tipo = 'acto_inseguro'
        LEFT JOIN tipos_accidente ta ON r.accidente_id = ta.id AND r.tipo = 'accidente'
        LEFT JOIN atenciones_medicas am ON r.atencion_medica_id = am.id
        JOIN usuarios u ON r.reportado_por = u.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY r.fecha DESC, r.hora DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reportes = $stmt->fetchAll();
?>

<div class="d-flex justify-content-end mb-2">
    <a href="<?= BASE_URL ?>modules/reportes/crear.php?empleado_id=<?= $empleado_id ?>" class="btn btn-sm btn-primary" id="btnNuevoReporte">
        <i class="fas fa-plus"></i> Nuevo Reporte
    </a>
</div>

<div class="card mb-3">
    <div class="card-header p-2">
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosReportes">
            <i class="fas fa-filter"></i> Filtros
        </button>
        <a href="exportar_historial.php?id=<?= $empleado_id ?>&<?= http_build_query($_GET) ?>" class="btn btn-sm btn-success ms-2 no-spinner"><i class="fas fa-file-excel"></i> Exportar</a>
    </div>
    <div class="collapse" id="filtrosReportes">
        <div class="card-body">
            <form id="formFiltrosReportes" class="row g-2">
                <input type="hidden" name="id" value="<?= $empleado_id ?>">
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-select">
                        <option value="">Todos</option>
                        <option value="acto_inseguro" <?= $tipo_filtro=='acto_inseguro'?'selected':'' ?>>Acto Inseguro</option>
                        <option value="accidente" <?= $tipo_filtro=='accidente'?'selected':'' ?>>Accidente</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha desde</label>
                    <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($fecha_desde) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($fecha_hasta) ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search"></i></button>
                    <button type="button" class="btn btn-secondary" onclick="limpiarFiltrosReportes()">Limpiar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (empty($reportes)): ?>
    <div class="alert alert-info">No se encontraron reportes.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead class="table-light">
                <tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Gravedad</th><th>Atención</th><th>Evidencia</th></tr>
            </thead>
            <tbody>
                <?php foreach ($reportes as $r): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($r['fecha'].' '.$r['hora'])) ?></td>
                    <td><?= $r['tipo']=='acto_inseguro'?'<span class="badge bg-warning">Acto</span>':'<span class="badge bg-danger">Accidente</span>' ?></td>
                    <td><?= htmlspecialchars($r['catalogo_descripcion']??'—') ?></td>
                    <td><?= $r['gravedad'] ? ucfirst($r['gravedad']) : '—' ?></td>
                    <td><?= htmlspecialchars($r['atencion_medica']??'—') ?></td>
                    <td class="text-center"><?= $r['evidencia_foto'] ? "<a href='".UPLOAD_URL.$r['evidencia_foto']."' class='lightbox-trigger'><i class='fas fa-image'></i></a>" : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
document.getElementById('formFiltrosReportes')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const params = new URLSearchParams(formData);
    fetch(`historial_reporte.php?${params.toString()}`)
        .then(r => r.text())
        .then(html => document.getElementById('reportes').innerHTML = html);
});
function limpiarFiltrosReportes() {
    const form = document.getElementById('formFiltrosReportes');
    form.tipo.value = '';
    form.fecha_desde.value = '';
    form.fecha_hasta.value = '';
    form.dispatchEvent(new Event('submit'));
}
</script>