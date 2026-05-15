<?php
require_once '../../includes/auth.php';
if (!isset($usuario_id)) {
    http_response_code(403);
    exit;
}

$curso_id = $_GET['id'] ?? 0;
if (!$curso_id) {
    echo '<div class="alert alert-danger">ID de curso/formato no válido.</div>';
    exit;
}

// Filtros recibidos
$sucursal_id = $_GET['sucursal_id'] ?? '';
$departamento_id = $_GET['departamento_id'] ?? '';
$estado = $_GET['estado'] ?? '1';
$buscar = trim($_GET['buscar'] ?? '');

$whereEmpleados = [];
$paramsBase = [];

if (!empty($sucursal_id)) {
    $whereEmpleados[] = "e.sucursal_id = ?";
    $paramsBase[] = $sucursal_id;
}
if (!empty($departamento_id)) {
    $whereEmpleados[] = "e.departamento_id = ?";
    $paramsBase[] = $departamento_id;
}
if ($estado !== '') {
    $whereEmpleados[] = "e.activo = ?";
    $paramsBase[] = $estado;
}
if (!empty($buscar)) {
    $whereEmpleados[] = "(e.numero_empleado LIKE ? OR e.nombre LIKE ?)";
    $paramsBase[] = "%$buscar%";
    $paramsBase[] = "%$buscar%";
}

$whereSQL = '';
if (!empty($whereEmpleados)) {
    $whereSQL = ' AND ' . implode(' AND ', $whereEmpleados);
}

// Datos del curso
$stmt = $pdo->prepare("SELECT * FROM cursos WHERE id = ?");
$stmt->execute([$curso_id]);
$curso = $stmt->fetch();
if (!$curso) {
    echo '<div class="alert alert-warning">Curso no encontrado.</div>';
    exit;
}

// Empleados que YA tomaron el curso
$paramsTomaron = array_merge([$curso_id], $paramsBase);
$sqlTomaron = "SELECT e.id, e.numero_empleado, e.nombre, d.nombre as departamento, s.nombre as sucursal, MAX(ec.fecha_realizacion) as ultima_fecha
               FROM empleado_curso ec
               JOIN empleados e ON ec.empleado_id = e.id
               JOIN departamentos d ON e.departamento_id = d.id
               JOIN sucursales s ON e.sucursal_id = s.id
               WHERE ec.curso_id = ? $whereSQL
               GROUP BY e.id
               ORDER BY e.nombre";
$stmtTomaron = $pdo->prepare($sqlTomaron);
$stmtTomaron->execute($paramsTomaron);
$tomaron = $stmtTomaron->fetchAll();

// Empleados que NO han tomado el curso
$paramsNoTomaron = array_merge([$curso_id], $paramsBase);
$sqlNoTomaron = "SELECT e.id, e.numero_empleado, e.nombre, d.nombre as departamento, s.nombre as sucursal
                 FROM empleados e
                 JOIN departamentos d ON e.departamento_id = d.id
                 JOIN sucursales s ON e.sucursal_id = s.id
                 WHERE e.id NOT IN (SELECT empleado_id FROM empleado_curso WHERE curso_id = ?) $whereSQL
                 ORDER BY e.nombre";
$stmtNoTomaron = $pdo->prepare($sqlNoTomaron);
$stmtNoTomaron->execute($paramsNoTomaron);
$noTomaron = $stmtNoTomaron->fetchAll();

// Total de empleados filtrados (CORREGIDO: construcción sin doble 'AND')
$sqlTotal = "SELECT COUNT(*) FROM empleados e WHERE 1=1";
if (!empty($whereEmpleados)) {
    $sqlTotal .= ' AND ' . implode(' AND ', $whereEmpleados);
}
$stmtTotal = $pdo->prepare($sqlTotal);
$stmtTotal->execute($paramsBase);
$totalEmpleadosFiltrados = $stmtTotal->fetchColumn();

$porcentajeCobertura = $totalEmpleadosFiltrados > 0 ? round((count($tomaron) / $totalEmpleadosFiltrados) * 100, 1) : 0;

// Catálogos para filtros
$sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();
$departamentos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre")->fetchAll();
?>

<div class="modal-header bg-info text-white">
    <h5 class="modal-title"><i class="fas fa-chart-pie"></i> Cobertura del Curso/Formato: <?= htmlspecialchars($curso['nombre']) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h6>Total Empleados</h6>
                    <h3><?= $totalEmpleadosFiltrados ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h6>Han tomado el curso/formato</h6>
                    <h3><?= count($tomaron) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h6>No han tomado el curso/formato</h6>
                    <h3><?= count($noTomaron) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h6>% Cobertura</h6>
                    <h3><?= $porcentajeCobertura ?>%</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header p-2">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosCobertura">
                <i class="fas fa-filter"></i> Filtros
            </button>
            <a href="exportar_cobertura.php?id=<?= $curso_id ?>&<?= http_build_query($_GET) ?>" class="btn btn-sm btn-success ms-2 no-spinner"><i class="fas fa-file-excel"></i> Exportar</a>
        </div>
        <div class="collapse" id="filtrosCobertura">
            <div class="card-body">
                <form id="formFiltrosCobertura" data-curso-id="<?= $curso_id ?>" class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">Buscar (Nº / Nombre)</label>
                        <input type="text" name="buscar" class="form-control" placeholder="Ej: E001 o Juan" value="<?= htmlspecialchars($buscar) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sucursal</label>
                        <select name="sucursal_id" class="form-select">
                            <option value="">Todas</option>
                            <?php foreach ($sucursales as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $sucursal_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
                    <div class="col-12">
                        <button type="button" class="btn btn-primary" id="btnFiltrarCobertura"><i class="fas fa-search"></i> Filtrar</button>
                        <button type="button" class="btn btn-secondary" id="btnLimpiarCobertura">Limpiar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs" id="coberturaTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tomaron-tab" data-bs-toggle="tab" data-bs-target="#tomaron" type="button" role="tab">
                <i class="fas fa-check-circle text-success"></i> Han tomado (<?= count($tomaron) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="no-tomaron-tab" data-bs-toggle="tab" data-bs-target="#noTomaron" type="button" role="tab">
                <i class="fas fa-times-circle text-danger"></i> No han tomado (<?= count($noTomaron) ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content mt-3">
        <div class="tab-pane fade show active" id="tomaron" role="tabpanel">
            <?php if (empty($tomaron)): ?>
                <div class="alert alert-info">Ningún empleado ha tomado este curso/formato aún.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr><th>#</th><th>Nombre</th><th>Departamento</th><th>Sucursal</th><th>Última vez</th><th>Acción</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tomaron as $e): ?>
                            <tr id="emp-<?= $e['id'] ?>">
                                <td><?= htmlspecialchars($e['numero_empleado']) ?></td>
                                <td><?= htmlspecialchars($e['nombre']) ?></td>
                                <td><?= htmlspecialchars($e['departamento']) ?></td>
                                <td><?= htmlspecialchars($e['sucursal']) ?></td>
                                <td><?= date('d/m/Y', strtotime($e['ultima_fecha'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger btn-desmarcar" data-empleado-id="<?= $e['id'] ?>" data-curso-id="<?= $curso_id ?>" title="Marcar como NO tomado">
                                        <i class="fas fa-times"></i> No tomado
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="tab-pane fade" id="noTomaron" role="tabpanel">
            <?php if (empty($noTomaron)): ?>
                <div class="alert alert-success">¡Todos los empleados han tomado este curso/formato!</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr><th>#</th><th>Nombre</th><th>Departamento</th><th>Sucursal</th><th>Acción</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($noTomaron as $e): ?>
                            <tr id="emp-<?= $e['id'] ?>">
                                <td><?= htmlspecialchars($e['numero_empleado']) ?></td>
                                <td><?= htmlspecialchars($e['nombre']) ?></td>
                                <td><?= htmlspecialchars($e['departamento']) ?></td>
                                <td><?= htmlspecialchars($e['sucursal']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-success btn-marcar" 
                                        data-empleado-id="<?= $e['id'] ?>" 
                                        data-curso-id="<?= $curso_id ?>">
                                            <i class="fas fa-check"></i> Tomado
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
</div>