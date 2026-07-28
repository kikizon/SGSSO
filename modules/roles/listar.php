<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
exigir('roles.gestionar');

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';
$total = count(permisos_todos());

$roles = $pdo->query("SELECT r.*,
                        (SELECT COUNT(*) FROM rol_permisos rp WHERE rp.rol_id = r.id) AS n_permisos,
                        (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = r.id) AS n_usuarios
                      FROM roles r
                      ORDER BY r.es_sistema DESC, r.nombre")->fetchAll();

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="mb-0"><i class="fas fa-user-shield"></i> Roles y permisos</h2>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>modules/usuarios/diagnostico_permisos.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-stethoscope"></i> Diagnóstico
        </a>
        <a href="crear.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nuevo rol</a>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-warning"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<?php if (PERMISOS_FUENTE !== 'bd'): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> El sistema está en <strong>modo compatibilidad</strong>
    (<code>PERMISOS_FUENTE = 'mapa'</code>): lo que edites aquí <strong>todavía no surte efecto</strong>.
    Completa la Fase 4 para activar la base de datos como fuente.
</div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead>
            <tr><th>Rol</th><th>Descripción</th><th class="text-center">Permisos</th><th class="text-center">Usuarios</th><th class="text-end">Acciones</th></tr>
        </thead>
        <tbody>
            <?php foreach ($roles as $r): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($r['nombre']) ?></strong>
                    <?php if ((int)$r['es_sistema'] === 1): ?>
                        <span class="badge bg-danger ms-1" title="Acceso total; no se puede limitar ni eliminar">Sistema</span>
                    <?php endif; ?>
                    <?php if (!$r['activo']): ?><span class="badge bg-secondary ms-1">Inactivo</span><?php endif; ?>
                    <?php if (!empty($r['ve_todas_sucursales'])): ?><span class="badge bg-warning text-dark ms-1" title="Ve los datos de todas las sucursales">Todas las sucursales</span><?php endif; ?>
                </td>
                <td class="text-muted small"><?= htmlspecialchars($r['descripcion'] ?? '—') ?></td>
                <td class="text-center">
                    <?php if ((int)$r['es_sistema'] === 1): ?>
                        <span class="badge bg-danger">Todos (<?= $total ?>)</span>
                    <?php else: ?>
                        <span class="badge bg-info"><?= (int)$r['n_permisos'] ?> / <?= $total ?></span>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?= (int)$r['n_usuarios'] ?></td>
                <td class="text-end">
                    <?php if ((int)$r['es_sistema'] === 1): ?>
                        <span class="text-muted small">No editable</span>
                    <?php else: ?>
                        <a href="editar.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-warning" title="Editar permisos"><i class="fas fa-edit"></i></a>
                        <?php if ((int)$r['n_usuarios'] === 0): ?>
                        <form action="eliminar.php" method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este rol?');">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-sm btn-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php else: ?>
                            <button class="btn btn-sm btn-danger" disabled title="Tiene usuarios asignados"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="alert alert-secondary small mb-0">
    El rol marcado como <strong>Sistema</strong> (Administrador) siempre tiene todos los permisos, incluidos los que se agreguen en el futuro. No se puede limitar ni eliminar: es lo que garantiza que nunca te quedes fuera del sistema.
</div>

<?php include '../../includes/footer.php'; ?>
