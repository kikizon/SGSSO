<?php
/**
 * Excepciones de permisos para un usuario concreto.
 * Conceder = darle un permiso que su rol no tiene.
 * Revocar  = quitarle un permiso que su rol sí tiene.
 */
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
exigir('usuarios.permisos_individuales');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$st = $pdo->prepare("SELECT u.*, r.nombre AS rol_nombre, r.es_sistema
                     FROM usuarios u LEFT JOIN roles r ON r.id = u.rol_id
                     WHERE u.id = ?");
$st->execute([$id]);
$u = $st->fetch();
if (!$u) { header('Location: listar.php'); exit; }

$error = $msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.';
    } else {
        $validos = permisos_todos();
        $excepciones = (array)($_POST['excepcion'] ?? []);   // clave => 'conceder'|'revocar'
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM usuario_permisos WHERE usuario_id = ?")->execute([$id]);
            $ins = $pdo->prepare("INSERT INTO usuario_permisos (usuario_id, permiso_clave, concedido) VALUES (?, ?, ?)");
            $n = 0;
            foreach ($excepciones as $clave => $tipo) {
                if (!in_array($clave, $validos, true)) { continue; }
                if ($tipo === 'conceder') { $ins->execute([$id, $clave, 1]); $n++; }
                elseif ($tipo === 'revocar') { $ins->execute([$id, $clave, 0]); $n++; }
            }
            registrar_auditoria($pdo, $usuario_id, 'UPDATE', 'usuario_permisos', $id,
                json_encode(['excepciones' => $n], JSON_UNESCAPED_UNICODE));
            $pdo->commit();
            $msg = "Se guardaron $n excepción(es).";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('usuarios/permisos: ' . $e->getMessage());
            $error = 'No se pudieron guardar las excepciones.';
        }
    }
}

// Permisos que da su rol
$delRol = $u['rol_id'] ? permisos_de_rol_bd($pdo, (string)$u['rol_nombre']) : [];
// Excepciones actuales
$exc = [];
$q = $pdo->prepare("SELECT permiso_clave, concedido FROM usuario_permisos WHERE usuario_id = ?");
$q->execute([$id]);
foreach ($q->fetchAll() as $r) { $exc[$r['permiso_clave']] = (int)$r['concedido']; }

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="mb-0"><i class="fas fa-user-lock"></i> Permisos individuales</h2>
    <a href="listar.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Volver a usuarios</a>
</div>

<div class="alert alert-info">
    Usuario: <strong><?= htmlspecialchars($u['nombre_completo']) ?></strong>
    · Rol: <strong><?= htmlspecialchars($u['rol_nombre'] ?? '—') ?></strong>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<?php if ((int)($u['es_sistema'] ?? 0) === 1): ?>
    <div class="alert alert-warning">
        Este usuario tiene un rol de <strong>sistema</strong>: siempre cuenta con todos los permisos, así que las excepciones no le aplican.
    </div>
<?php else: ?>

<div class="alert alert-secondary small">
    <strong>Hereda</strong> = lo que define su rol. <strong>Conceder</strong> = darle un permiso extra. <strong>Revocar</strong> = quitárselo aunque su rol lo tenga.
</div>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <?php foreach (permisos_catalogo() as $grupo => $perms): ?>
    <div class="card mb-3">
        <div class="card-header bg-light fw-bold py-2"><?= htmlspecialchars($grupo) ?></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Permiso</th><th class="text-center" style="width:110px">Su rol</th><th class="text-center" style="width:320px">Excepción</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($perms as $clave => $def):
                        $tieneRol = in_array($clave, $delRol, true);
                        $estado = array_key_exists($clave, $exc) ? ($exc[$clave] === 1 ? 'conceder' : 'revocar') : '';
                        $n = md5($clave); ?>
                    <tr>
                        <td><?= htmlspecialchars($def['label']) ?> <code class="text-muted" style="font-size:.75em"><?= htmlspecialchars($clave) ?></code></td>
                        <td class="text-center">
                            <?= $tieneRol ? '<span class="text-success fw-bold">&#10003;</span>' : '<span class="text-muted">&mdash;</span>' ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="excepcion[<?= htmlspecialchars($clave) ?>]" value="" id="h<?= $n ?>" <?= $estado === '' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-secondary" for="h<?= $n ?>">Hereda</label>

                                <input type="radio" class="btn-check" name="excepcion[<?= htmlspecialchars($clave) ?>]" value="conceder" id="c<?= $n ?>" <?= $estado === 'conceder' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-success" for="c<?= $n ?>">Conceder</label>

                                <input type="radio" class="btn-check" name="excepcion[<?= htmlspecialchars($clave) ?>]" value="revocar" id="r<?= $n ?>" <?= $estado === 'revocar' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-danger" for="r<?= $n ?>">Revocar</label>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="mb-5">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar excepciones</button>
        <a href="listar.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
