<?php
require_once '../../includes/auth.php';
if ($usuario_rol === 'usuario') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

// Configuración de paginación
$registros_por_pagina = isset($_SESSION['empleados_por_pagina']) ? $_SESSION['empleados_por_pagina'] : 20;
$pagina_actual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Filtros
$buscar = trim($_GET['buscar'] ?? '');
$sucursal_id = $_GET['sucursal_id'] ?? '';
$departamento_id = $_GET['departamento_id'] ?? '';
$estado = $_GET['estado'] ?? '1';
$enfermedad_id = $_GET['enfermedad_id'] ?? '';
$tiene_reportes = $_GET['tiene_reportes'] ?? '';

$where = [];
$params = [];

if (!empty($buscar)) {
    $where[] = "(e.numero_empleado LIKE ? OR e.nombre LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}
if (!empty($sucursal_id)) {
    $where[] = "e.sucursal_id = ?";
    $params[] = $sucursal_id;
}
if (!empty($departamento_id)) {
    $where[] = "e.departamento_id = ?";
    $params[] = $departamento_id;
}
if ($estado !== '') {
    $where[] = "e.activo = ?";
    $params[] = $estado;
}
if (!empty($enfermedad_id)) {
    $where[] = "EXISTS (SELECT 1 FROM empleado_enfermedad ee WHERE ee.empleado_id = e.id AND ee.enfermedad_id = ?)";
    $params[] = $enfermedad_id;
}
if ($tiene_reportes !== '') {
    if ($tiene_reportes === '1') {
        $where[] = "EXISTS (SELECT 1 FROM reportes r WHERE r.empleado_id = e.id)";
    } else {
        $where[] = "NOT EXISTS (SELECT 1 FROM reportes r WHERE r.empleado_id = e.id)";
    }
}

if ($usuario_rol !== 'admin') { $where[] = "e.sucursal_id IN ($usuario_sucursales_sql)"; }
$where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Total registros
$sql_count = "SELECT COUNT(*) FROM empleados e $where_sql";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Consulta principal con contador de reportes últimos 12 meses
$sql = "SELECT e.*, d.nombre as departamento, s.nombre as sucursal,
               (SELECT COUNT(*) FROM reportes r 
                WHERE r.empleado_id = e.id 
                  AND r.fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)) as total_reportes_12m
        FROM empleados e
        JOIN departamentos d ON e.departamento_id = d.id
        JOIN sucursales s ON e.sucursal_id = s.id
        $where_sql
        GROUP BY e.id
        ORDER BY e.nombre ASC
        LIMIT $offset, $registros_por_pagina";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$empleados = $stmt->fetchAll();

// Catálogos para filtros
if ($usuario_rol === 'admin') {
    $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();
} else {
    $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE id IN ($usuario_sucursales_sql) ORDER BY nombre")->fetchAll();
}
$departamentos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre")->fetchAll();
$enfermedades = $pdo->query("SELECT id, nombre FROM enfermedades_cronicas WHERE activo = 1 ORDER BY nombre")->fetchAll();

include '../../includes/header.php';
?>

<h2><i class="fas fa-users"></i> Gestión de Empleados</h2>

<div class="row mb-3 align-items-center">
    <div class="col-md-8">
        <a href="crear.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo Empleado</a>
        <?php if ($usuario_rol === 'admin'): ?>
        <a href="plantilla.php" class="btn btn-outline-secondary no-spinner"><i class="fas fa-download"></i> Descargar Plantilla CSV</a>
        <a href="importar.php" class="btn btn-success"><i class="fas fa-upload"></i> Importar CSV</a>
        <a href="fotos_masivas.php" class="btn btn-outline-primary"><i class="fas fa-images"></i> Fotos masivas</a>
        <?php endif; ?>
    </div>
    <div class="col-md-4 text-end">
        <span class="text-muted me-2">Total registros: <?= $total_registros ?></span>
        <div class="btn-group">
            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-eye"></i> <?= $registros_por_pagina ?> por página
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach ([10, 20, 50, 100] as $opcion): ?>
                    <li><a class="dropdown-item <?= $opcion == $registros_por_pagina ? 'active' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['por_pagina'=>$opcion, 'pagina'=>1])) ?>"><?= $opcion ?> registros</a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Panel de filtros -->
<div class="card mb-3">
    <div class="card-header">
        <i class="fas fa-filter"></i> Filtros
        <button class="btn btn-sm btn-link float-end" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosCollapse">
            <i class="fas fa-chevron-down"></i>
        </button>
    </div>
    <div class="collapse show" id="filtrosCollapse">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Buscar (Número / Nombre)</label>
                    <input type="text" name="buscar" class="form-control" placeholder="Ej: E001 o Juan" value="<?= htmlspecialchars($buscar) ?>">
                </div>
                <?php if ($usuario_rol === 'admin'): ?>
                <div class="col-md-3">
                    <label class="form-label">Sucursal</label>
                    <select name="sucursal_id" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($sucursales as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $sucursal_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <label class="form-label">Departamento</label>
                    <select name="departamento_id" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($departamentos as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $departamento_id == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="1" <?= $estado === '1' ? 'selected' : '' ?>>Activos</option>
                        <option value="0" <?= $estado === '0' ? 'selected' : '' ?>>Inactivos</option>
                        <option value="" <?= $estado === '' ? 'selected' : '' ?>>Todos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Enfermedad Crónica</label>
                    <select name="enfermedad_id" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($enfermedades as $enf): ?>
                            <option value="<?= $enf['id'] ?>" <?= $enfermedad_id == $enf['id'] ? 'selected' : '' ?>><?= htmlspecialchars($enf['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tiene reportes</label>
                    <select name="tiene_reportes" class="form-select">
                        <option value="" <?= $tiene_reportes === '' ? 'selected' : '' ?>>Todos</option>
                        <option value="1" <?= $tiene_reportes === '1' ? 'selected' : '' ?>>Con reportes</option>
                        <option value="0" <?= $tiene_reportes === '0' ? 'selected' : '' ?>>Sin reportes</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Filtrar</button>
                </div>
                <div class="col-12">
                    <a href="listar.php" class="btn btn-secondary btn-sm">Limpiar filtros</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Tabla de resultados -->
<div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>Número</th>
                <th>Nombre</th>
                <th>Departamento</th>
                <th>Sucursal</th>
                <th>Estado</th>
                <th>Reportes (12m)</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($empleados)): ?>
                <tr><td colspan="7" class="text-center py-4">No se encontraron empleados.</td></tr>
            <?php else: ?>
                <?php foreach ($empleados as $e): ?>
                <tr>
                    <td><?= htmlspecialchars($e['numero_empleado']) ?></td>
                    <td>
                        <?php if (!empty($e['foto'])): ?>
                            <img src="<?= UPLOAD_URL . htmlspecialchars($e['foto']) ?>" class="rounded-circle me-1" style="width:28px;height:28px;object-fit:cover;" alt="">
                        <?php else: ?>
                            <span class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center me-1" style="width:28px;height:28px;font-size:.7rem;"><i class="fas fa-user"></i></span>
                        <?php endif; ?>
                        <?= htmlspecialchars($e['nombre']) ?>
                    </td>
                    <td><?= htmlspecialchars($e['departamento']) ?></td>
                    <td><?= htmlspecialchars($e['sucursal']) ?></td>
                    <td><?= $e['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>' ?></td>
                    <td>
                        <?php if ($e['total_reportes_12m'] > 0): ?>
                            <span class="badge bg-warning text-dark"><?= $e['total_reportes_12m'] ?> reporte(s)</span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-info ver-historial" data-id="<?= $e['id'] ?>" title="Ver historial completo"><i class="fas fa-history"></i></button>
                        <a href="editar.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-warning" title="Editar"><i class="fas fa-edit"></i></a>
                        <?php if ($usuario_rol === 'admin'): ?>
                        <a href="eliminar.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar empleado?')" title="Eliminar"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Paginación -->
<?php if ($total_paginas > 1): ?>
<nav><ul class="pagination justify-content-center">
    <?php
    $query_params = $_GET;
    unset($query_params['pagina']);
    $base_url = "listar.php?" . http_build_query($query_params) . (empty($query_params)?'':'&');
    ?>
    <li class="page-item <?= $pagina_actual<=1?'disabled':'' ?>"><a class="page-link" href="<?= $base_url ?>pagina=<?= $pagina_actual-1 ?>">&laquo;</a></li>
    <?php for($i=1; $i<=$total_paginas; $i++): ?>
        <li class="page-item <?= $i==$pagina_actual?'active':'' ?>"><a class="page-link" href="<?= $base_url ?>pagina=<?= $i ?>"><?= $i ?></a></li>
    <?php endfor; ?>
    <li class="page-item <?= $pagina_actual>=$total_paginas?'disabled':'' ?>"><a class="page-link" href="<?= $base_url ?>pagina=<?= $pagina_actual+1 ?>">&raquo;</a></li>
</ul></nav>
<?php endif; ?>

<!-- Modal unificado para Historial -->
<div class="modal fade" id="historialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" id="historialModalContent">
            <div class="text-center p-3"><div class="spinner-border"></div> Cargando...</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalHist = new bootstrap.Modal(document.getElementById('historialModal'));
    const modalHistContent = document.getElementById('historialModalContent');
    
    function cargarHistorial(id) {
        modalHistContent.innerHTML = '<div class="text-center p-3"><div class="spinner-border"></div> Cargando...</div>';
        modalHist.show();
        fetch(`historial.php?id=${id}`)
            .then(response => response.text())
            .then(html => {
                // Limpiar scripts antiguos del DOM que puedan interferir
                const oldScripts = document.querySelectorAll('script[data-historial]');
                oldScripts.forEach(s => s.remove());
                
                modalHistContent.innerHTML = html;
                
                // Extraer y ejecutar scripts de forma segura
                const scripts = modalHistContent.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    const newScript = document.createElement('script');
                    newScript.setAttribute('data-historial', 'true');
                    if (oldScript.src) {
                        newScript.src = oldScript.src;
                    } else {
                        // Envolver en IIFE para evitar conflictos de redeclaración
                        newScript.textContent = `(function() { ${oldScript.textContent} })();`;
                    }
                    document.body.appendChild(newScript);
                    oldScript.remove();
                });
            })
            .catch(err => modalHistContent.innerHTML = '<div class="alert alert-danger m-3">Error al cargar el historial.</div>');
    }
    
    document.querySelectorAll('.ver-historial').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            cargarHistorial(id);
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>