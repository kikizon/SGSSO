<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/authorization.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$usuarios = $pdo->query("SELECT u.id, u.nombre_completo, u.email, u.rol, u.activo, u.password_change_required AS debe_cambiar_password,
                                (SELECT GROUP_CONCAT(s.nombre ORDER BY s.nombre SEPARATOR ', ')
                                 FROM usuario_sucursales us JOIN sucursales s ON s.id = us.sucursal_id
                                 WHERE us.usuario_id = u.id) AS sucursales
                         FROM usuarios u
                         ORDER BY u.nombre_completo")->fetchAll();

$pendientes = autz_contar_pendientes($pdo);

include '../../includes/header.php';
?>

<h2><i class="fas fa-user-cog"></i> Gestión de Usuarios</h2>

<div class="d-flex gap-2 mb-3">
  <a href="crear.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo Usuario</a>
  <a href="<?= BASE_URL ?>modules/autorizaciones/listar.php" class="btn btn-outline-secondary">
    <i class="fas fa-user-shield"></i> Autorizaciones
    <?php if ($pendientes > 0): ?><span class="badge bg-danger"><?= $pendientes ?></span><?php endif; ?>
  </a>
  <button class="btn btn-outline-info ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#panelPermisos">
    <i class="fas fa-shield-alt"></i> Ver permisos por rol
  </button>
</div>

<!-- ============================================================
     MATRIZ DE PERMISOS POR ROL (derivada del comportamiento real)
     ============================================================ -->
<div class="collapse mb-4" id="panelPermisos">
  <div class="card">
    <div class="card-header bg-light"><i class="fas fa-shield-alt"></i> ¿Qué puede hacer cada rol?</div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-2">
          <thead>
            <tr>
              <th style="min-width:220px">Acción</th>
              <th class="text-center"><span class="badge bg-danger">Administrador</span></th>
              <th class="text-center"><span class="badge bg-warning text-dark">Supervisor</span></th>
              <th class="text-center"><span class="badge bg-secondary">Usuario</span></th>
            </tr>
          </thead>
          <tbody>
            <?php
            $si  = '<span class="text-success fw-bold">Sí</span>';
            $no  = '<span class="text-danger">No</span>';
            $suc = '<span class="text-primary">Sus sucursales</span>';
            $aut = '<span class="text-warning">Con autorización</span>';
            $rows = [
              ['Ver dashboards',                 $si,  $suc, $suc],
              ['Crear reportes (actos/accidentes)', $si, $suc, $suc],
              ['Editar / eliminar reportes',     $si,  $aut, $aut],
              ['Realizar auditorías 6S',         $si,  $suc, $suc],
              ['Editar / eliminar auditorías 6S',$si,  $aut, $aut],
              ['Gestionar catálogos (deptos, sucursales, etc.)', $si, $no, $no],
              ['Editar / eliminar en catálogos', $si,  $no,  $no],
              ['Gestionar usuarios',             $si,  $no,  $no],
              ['Configuración del sistema',      $si,  $no,  $no],
              ['Aprobar / rechazar solicitudes', $si,  $no,  $no],
            ];
            foreach ($rows as $r): ?>
              <tr>
                <td><?= $r[0] ?></td>
                <td class="text-center"><?= $r[1] ?></td>
                <td class="text-center"><?= $r[2] ?></td>
                <td class="text-center"><?= $r[3] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="text-muted small mb-0">
        <strong>Con autorización</strong>: la acción genera una solicitud que un <strong>Administrador</strong> debe aprobar
        antes de aplicarse (doble autorización). <strong>Sus sucursales</strong>: el rol solo ve y actúa sobre registros de las
        sucursales que tiene asignadas.
      </p>
    </div>
  </div>
</div>

<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Sucursales</th>
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
                <td>
                    <?php if (!empty($u['sucursales'])): ?>
                        <?php foreach (explode(', ', $u['sucursales']) as $sn): ?><span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($sn) ?></span><?php endforeach; ?>
                    <?php else: ?>—<?php endif; ?>
                </td>
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
