<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

// Filtro de estado (por defecto solo activos)
$estado = $_GET['estado'] ?? '1';

$whereEstado = '';
$params = [];
if ($estado !== '') {
    $whereEstado = "WHERE activo = ?";
    $params[] = $estado;
}

$sql = "SELECT * FROM tipos_accidente $whereEstado ORDER BY descripcion";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tipos = $stmt->fetchAll();

include '../../includes/header.php';
?>

<h2><i class="fas fa-car-crash"></i> Tipos de Accidente</h2>
<a href="crear.php" class="btn btn-primary mb-3"><i class="fas fa-plus"></i> Nuevo Tipo</a>

<!-- Filtros compactos alineados a la derecha -->
<div class="d-flex justify-content-end mb-3">
    <form method="get" class="row g-2 align-items-center">
        <div class="col-auto">
            <label class="col-form-label">Estado:</label>
        </div>
        <div class="col-auto">
            <select name="estado" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="1" <?= $estado === '1' ? 'selected' : '' ?>>Activos</option>
                <option value="0" <?= $estado === '0' ? 'selected' : '' ?>>Inactivos</option>
                <option value="" <?= $estado === '' ? 'selected' : '' ?>>Todos</option>
            </select>
        </div>
        <div class="col-auto">
            <a href="listar.php" class="btn btn-outline-secondary btn-sm">Limpiar</a>
        </div>
    </form>
</div>

<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr><th>Descripción</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
            <?php foreach ($tipos as $t): ?>
            <tr>
                <td><?= htmlspecialchars($t['descripcion']) ?></td>
                <td><?= $t['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>' ?></td>
                <td>
                    <a href="editar.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                    <a href="eliminar.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este tipo?')"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../../includes/footer.php'; ?>