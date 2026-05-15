<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

// Filtros
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$usuario_filtro = $_GET['usuario_id'] ?? '';
$accion_filtro  = $_GET['accion'] ?? '';
$tabla_filtro   = $_GET['tabla'] ?? '';

$where = "1=1";
$params = [];

if ($fecha_desde) { $where .= " AND DATE(al.fecha) >= ?"; $params[] = $fecha_desde; }
if ($fecha_hasta) { $where .= " AND DATE(al.fecha) <= ?"; $params[] = $fecha_hasta; }
if ($usuario_filtro) { $where .= " AND al.usuario_id = ?"; $params[] = $usuario_filtro; }
if ($accion_filtro) { $where .= " AND al.accion = ?"; $params[] = $accion_filtro; }
if ($tabla_filtro) { $where .= " AND al.tabla = ?"; $params[] = $tabla_filtro; }

// Paginación
$por_pagina = 20;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $por_pagina;

$sql = "SELECT al.*, u.nombre_completo 
        FROM audit_logs al
        JOIN usuarios u ON al.usuario_id = u.id
        WHERE $where
        ORDER BY al.fecha DESC
        LIMIT $offset, $por_pagina";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Total para paginación
$sqlCount = "SELECT COUNT(*) FROM audit_logs al WHERE $where";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$total = $stmtCount->fetchColumn();
$total_paginas = ceil($total / $por_pagina);

// Catálogos para filtros
$usuarios = $pdo->query("SELECT id, nombre_completo FROM usuarios ORDER BY nombre_completo")->fetchAll();

include '../../includes/header.php';
?>

<h2><i class="fas fa-history"></i> Auditoría del Sistema</h2>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($fecha_desde) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($fecha_hasta) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Usuario</label>
                <select name="usuario_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $usuario_filtro == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nombre_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Acción</label>
                <select name="accion" class="form-select">
                    <option value="">Todas</option>
                    <option value="INSERT" <?= $accion_filtro=='INSERT'?'selected':'' ?>>INSERT</option>
                    <option value="UPDATE" <?= $accion_filtro=='UPDATE'?'selected':'' ?>>UPDATE</option>
                    <option value="DELETE" <?= $accion_filtro=='DELETE'?'selected':'' ?>>DELETE</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tabla</label>
                <input type="text" name="tabla" class="form-control" placeholder="reportes, empleados..." value="<?= htmlspecialchars($tabla_filtro) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Filtrar</button>
                <a href="listar.php" class="btn btn-secondary ms-1">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Usuario</th>
                <th>Acción</th>
                <th>Tabla</th>
                <th>Registro ID</th>
                <th>Detalles</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= date('d/m/Y H:i:s', strtotime($log['fecha'])) ?></td>
                <td><?= htmlspecialchars($log['nombre_completo']) ?></td>
                <td>
                    <?php
                    $badge = match($log['accion']) {
                        'INSERT' => 'bg-success',
                        'UPDATE' => 'bg-warning text-dark',
                        'DELETE' => 'bg-danger',
                        default => 'bg-secondary'
                    };
                    ?>
                    <span class="badge <?= $badge ?>"><?= $log['accion'] ?></span>
                </td>
                <td><?= htmlspecialchars($log['tabla']) ?></td>
                <td><?= $log['registro_id'] ?? '—' ?></td>
                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($log['detalles']) ?>"><?= htmlspecialchars($log['detalles']) ?></td>
                <td><?= htmlspecialchars($log['ip']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
                <tr><td colspan="7" class="text-center">No hay registros de auditoría.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($total_paginas > 1): ?>
<nav>
    <ul class="pagination justify-content-center">
        <?php for ($i=1; $i<=$total_paginas; $i++): ?>
            <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>