<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$usuarios = $pdo->query("SELECT u.id, u.nombre_completo, u.email, u.rol, u.activo, u.debe_cambiar_password, s.nombre as sucursal 
                         FROM usuarios u 
                         LEFT JOIN sucursales s ON u.sucursal_id = s.id 
                         ORDER BY u.nombre_completo")->fetchAll();

include '../../includes/header.php';
?>

<h2><i class="fas fa-user-cog"></i> Gestión de Usuarios</h2>
<a href="crear.php" class="btn btn-primary mb-3"><i class="fas fa-plus"></i> Nuevo Usuario</a>

<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Sucursal</th>
                <th>Estado</th>
                <th>Debe cambiar password</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['nombre_completo']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= $u['rol'] === 'admin' ? '<span class="badge bg-danger">Admin</span>' : ($u['rol'] === 'supervisor' ? '<span class="badge bg-warning text-dark">Supervisor</span>' : '<span class="badge bg-secondary">Usuario</span>') ?></td>
                <td><?= htmlspecialchars($u['sucursal'] ?? '—') ?></td>
                <td><?= $u['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>' ?></td>
                <td><?= $u['debe_cambiar_password'] ? '<span class="badge bg-warning text-dark">Sí</span>' : '<span class="badge bg-success">No</span>' ?></td>
                <td>
                    <a href="editar.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                    <?php if ($u['id'] != $usuario_id): ?>
                    <a href="eliminar.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar usuario?')"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../../includes/footer.php'; ?>