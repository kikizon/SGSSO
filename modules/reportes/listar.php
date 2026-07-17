<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Filtros
$tipo_filtro = $_GET['tipo'] ?? 'acto_inseguro';
if (!in_array($tipo_filtro, ['acto_inseguro', 'accidente'])) $tipo_filtro = 'acto_inseguro';

$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$departamento_id = $_GET['departamento_id'] ?? '';
$sucursal_id = $_GET['sucursal_id'] ?? '';
$catalogo_id = $_GET['catalogo_id'] ?? '';

$where = ["r.tipo = ?"];
$params = [$tipo_filtro];

if ($fecha_desde) { $where[] = "r.fecha >= ?"; $params[] = $fecha_desde; }
if ($fecha_hasta) { $where[] = "r.fecha <= ?"; $params[] = $fecha_hasta; }
if ($departamento_id) { $where[] = "r.departamento_id = ?"; $params[] = $departamento_id; }
if ($sucursal_id) { $where[] = "r.sucursal_id = ?"; $params[] = $sucursal_id; }
if ($catalogo_id) {
    $where[] = ($tipo_filtro == 'acto_inseguro') ? "r.acto_inseguro_id = ?" : "r.accidente_id = ?";
    $params[] = $catalogo_id;
}

// Multi-sucursal: no-admin acotado a sus sucursales
if ($usuario_rol !== 'admin') { $where[] = "r.sucursal_id IN ($usuario_sucursales_sql)"; }

$joinCatalogo = ($tipo_filtro == 'acto_inseguro') 
    ? "JOIN actos_inseguros a ON r.acto_inseguro_id = a.id"
    : "JOIN tipos_accidente a ON r.accidente_id = a.id";

$sql = "SELECT r.*, e.nombre as empleado_nombre, d.nombre as departamento_nombre, s.nombre as sucursal_nombre,
               a.descripcion as catalogo_descripcion, u.nombre_completo as reportado_por_nombre
        FROM reportes r
        JOIN empleados e ON r.empleado_id = e.id
        JOIN departamentos d ON r.departamento_id = d.id
        JOIN sucursales s ON r.sucursal_id = s.id
        $joinCatalogo
        JOIN usuarios u ON r.reportado_por = u.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY r.fecha DESC, r.hora DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reportes = $stmt->fetchAll();

// Catálogos para filtros
if ($usuario_rol === 'admin') {
    $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo=1 ORDER BY nombre")->fetchAll();
} else {
    $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE id IN ($usuario_sucursales_sql) ORDER BY nombre")->fetchAll();
}
$departamentos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo=1 ORDER BY nombre")->fetchAll();
if ($tipo_filtro == 'acto_inseguro') {
    $catalogo_filtro = $pdo->query("SELECT id, descripcion FROM actos_inseguros WHERE activo=1 ORDER BY descripcion")->fetchAll();
    $labelCatalogo = 'Acto Inseguro';
} else {
    $catalogo_filtro = $pdo->query("SELECT id, descripcion FROM tipos_accidente WHERE activo=1 ORDER BY descripcion")->fetchAll();
    $labelCatalogo = 'Tipo de Accidente';
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fas <?= $tipo_filtro=='acto_inseguro' ? 'fa-skull-crossbones' : 'fa-car-crash' ?>"></i> 
        Reportes de <?= $tipo_filtro=='acto_inseguro' ? 'Actos Inseguros' : 'Accidentes' ?></h2>
    <div>
        <a href="crear.php?tipo=<?= $tipo_filtro ?>" class="btn btn-success"><i class="fas fa-plus"></i> Nuevo</a>
        <button class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#filtrosCollapse"><i class="fas fa-filter"></i> Filtros</button>
        <a href="<?= BASE_URL ?>exportar_excel.php?<?= http_build_query(array_merge($_GET, ['tipo'=>$tipo_filtro])) ?>" class="btn btn-success no-spinner"><i class="fas fa-file-excel"></i> Exportar</a>
    </div>
</div>

<div class="collapse mb-3" id="filtrosCollapse">
    <div class="card card-body">
        <form method="get" class="row g-3">
            <input type="hidden" name="tipo" value="<?= $tipo_filtro ?>">
            <div class="col-md-3"><label>Fecha desde</label><input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($fecha_desde) ?>"></div>
            <div class="col-md-3"><label>Fecha hasta</label><input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($fecha_hasta) ?>"></div>
            <?php if ($usuario_rol === 'admin'): ?>
            <div class="col-md-3"><label>Sucursal</label><select name="sucursal_id" class="form-select"><option value="">Todas</option><?php foreach($sucursales as $s): ?><option value="<?=$s['id']?>" <?=$sucursal_id==$s['id']?'selected':''?>><?=htmlspecialchars($s['nombre'])?></option><?php endforeach; ?></select></div>
            <?php endif; ?>
            <div class="col-md-3"><label>Departamento</label><select name="departamento_id" class="form-select"><option value="">Todos</option><?php foreach($departamentos as $d): ?><option value="<?=$d['id']?>" <?=$departamento_id==$d['id']?'selected':''?>><?=htmlspecialchars($d['nombre'])?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label><?=$labelCatalogo?></label><select name="catalogo_id" class="form-select"><option value="">Todos</option><?php foreach($catalogo_filtro as $c): ?><option value="<?=$c['id']?>" <?=$catalogo_id==$c['id']?'selected':''?>><?=htmlspecialchars($c['descripcion'])?></option><?php endforeach; ?></select></div>
            <div class="col-12"><button type="submit" class="btn btn-primary">Filtrar</button> <a href="listar.php?tipo=<?=$tipo_filtro?>" class="btn btn-secondary">Limpiar</a></div>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Empleado</th>
                <th>Sucursal</th>
                <th>Depto</th>
                <th><?=$labelCatalogo?></th>
                <?php if ($tipo_filtro == 'accidente'): ?><th class="text-center">Días incap.</th><?php endif; ?>
                <th>Reportado por</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reportes)): ?>
                <tr><td colspan="<?= $tipo_filtro == 'accidente' ? 9 : 8 ?>" class="text-center">No hay reportes.</td></tr>
            <?php else: ?>
                <?php foreach ($reportes as $r): 
                    $rowClass = '';
                    if ($r['tipo'] == 'accidente' && $r['gravedad']) {
                        $rowClass = match($r['gravedad']) {
                            'leve' => 'table-light',
                            'moderado' => 'table-warning',
                            'grave' => 'table-danger',
                            'fatal' => 'table-dark text-white',
                            default => ''
                        };
                    }
                ?>
                <tr class="<?= $rowClass ?>">
                    <td><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
                    <td><?= date('H:i', strtotime($r['hora'])) ?></td>
                    <td><?= htmlspecialchars($r['empleado_nombre']) ?></td>
                    <td><?= htmlspecialchars($r['sucursal_nombre']) ?></td>
                    <td><?= htmlspecialchars($r['departamento_nombre']) ?></td>
                    <td><?= htmlspecialchars($r['catalogo_descripcion']) ?></td>
                    <?php if ($tipo_filtro == 'accidente'): ?><td class="text-center"><?= (int)$r['dias_perdidos'] ?></td><?php endif; ?>
                    <td><?= htmlspecialchars($r['reportado_por_nombre']) ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary ver-reporte" data-id="<?= $r['id'] ?>" title="Ver detalles"><i class="fas fa-eye"></i></button>
                        <a href="editar.php?id=<?=$r['id']?>" class="btn btn-sm btn-warning" title="Editar"><i class="fas fa-edit"></i></a>
                        <?php if ($usuario_rol === 'admin'): ?>
                        <a href="eliminar.php?id=<?=$r['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar?')" title="Eliminar"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal para Ver Reporte -->
<div class="modal fade" id="verReporteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" id="verReporteModalContent">
            <div class="text-center p-3"><div class="spinner-border"></div> Cargando...</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal para ver detalles del reporte
    const modalVer = new bootstrap.Modal(document.getElementById('verReporteModal'));
    const modalVerContent = document.getElementById('verReporteModalContent');

    document.querySelectorAll('.ver-reporte').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            modalVerContent.innerHTML = '<div class="text-center p-3"><div class="spinner-border"></div> Cargando...</div>';
            modalVer.show();
            fetch(`ver_reporte.php?id=${id}`)
                .then(response => response.text())
                .then(html => { modalVerContent.innerHTML = html; })
                .catch(err => modalVerContent.innerHTML = '<div class="alert alert-danger m-3">Error al cargar.</div>');
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>