<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch();
if (!$usuario) {
    header('Location: listar.php');
    exit;
}

$sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Sucursales actualmente asignadas
$asignadas = [];
try {
    $qa = $pdo->prepare("SELECT sucursal_id FROM usuario_sucursales WHERE usuario_id = ?");
    $qa->execute([$id]);
    $asignadas = array_map('intval', array_column($qa->fetchAll(), 'sucursal_id'));
} catch (Throwable $e) { $asignadas = []; }
if (empty($asignadas) && $usuario['sucursal_id']) { $asignadas = [(int)$usuario['sucursal_id']]; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre_completo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? 'usuario';
    $sucursales_sel = array_values(array_unique(array_map('intval', (array)($_POST['sucursales'] ?? []))));
    $activo = isset($_POST['activo']) ? 1 : 0;
    $debe_cambiar = isset($_POST['debe_cambiar_password']) ? 1 : 0;

    if ($rol === 'admin') { $sucursales_sel = []; }
    $primaria = $sucursales_sel[0] ?? null;

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.';
    } elseif (!$nombre || !$email) {
        $error = 'Nombre y email son obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email no válido.';
    } elseif ($rol !== 'admin' && empty($sucursales_sel)) {
        $error = 'Debe asignar al menos una sucursal a usuarios y supervisores.';
    } else {
        $pdo->beginTransaction();
        try {
            $sql = "UPDATE usuarios SET nombre_completo=?, email=?, rol=?, sucursal_id=?, activo=?, password_change_required=?";
            $params = [$nombre, $email, $rol, $primaria, $activo, $debe_cambiar];
            if (!empty($password)) {
                $sql .= ", password_hash=?, password_last_change=NOW()";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id=?";
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);

            // Reemplazar sucursales asignadas
            $pdo->prepare("DELETE FROM usuario_sucursales WHERE usuario_id = ?")->execute([$id]);
            if ($rol !== 'admin' && !empty($sucursales_sel)) {
                $ins = $pdo->prepare("INSERT IGNORE INTO usuario_sucursales (usuario_id, sucursal_id) VALUES (?, ?)");
                foreach ($sucursales_sel as $sid) { $ins->execute([$id, $sid]); }
            }

            registrar_auditoria($pdo, $usuario_id, 'UPDATE', 'usuarios', $id, json_encode(['nombre' => $nombre, 'rol' => $rol, 'sucursales' => $sucursales_sel]));
            $pdo->commit();

            $success = 'Usuario actualizado.';
            // Recargar
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            $usuario = $stmt->fetch();
            $asignadas = $sucursales_sel;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->errorInfo[1] == 1062) {
                $error = 'El email ya está en uso.';
            } else {
                $error = 'Error al actualizar.';
            }
        }
    }
}

// Para repoblar checkboxes tras error de validación
$marcadas = ($_SERVER['REQUEST_METHOD'] === 'POST' && $error) ? array_map('intval', (array)($_POST['sucursales'] ?? [])) : $asignadas;

include '../../includes/header.php';
?>

<h2><i class="fas fa-user-edit"></i> Editar Usuario</h2>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?> <a href="listar.php">Ver listado</a></div><?php endif; ?>

<form method="post" class="row g-3">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <div class="col-md-6">
        <label for="nombre_completo" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
        <input type="text" name="nombre_completo" id="nombre_completo" class="form-control" value="<?= htmlspecialchars($usuario['nombre_completo']) ?>" required>
    </div>
    <div class="col-md-6">
        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($usuario['email']) ?>" required>
    </div>
    <div class="col-md-6">
        <label for="password" class="form-label">Contraseña (dejar en blanco para no cambiar)</label>
        <input type="password" name="password" id="password" class="form-control">
        <small class="text-muted">Si cambia la contraseña, se actualizará la fecha de último cambio.</small>
    </div>
    <div class="col-md-6">
        <label for="rol" class="form-label">Rol</label>
        <select name="rol" id="rol" class="form-select" onchange="toggleSucursal()">
            <option value="usuario" <?= $usuario['rol'] == 'usuario' ? 'selected' : '' ?>>Usuario</option>
            <option value="supervisor" <?= $usuario['rol'] == 'supervisor' ? 'selected' : '' ?>>Supervisor</option>
            <option value="admin" <?= $usuario['rol'] == 'admin' ? 'selected' : '' ?>>Administrador</option>
        </select>
    </div>
    <div class="col-md-6" id="div_sucursal">
        <label class="form-label">Sucursales <span id="sucursal_required" class="text-danger">*</span></label>
        <div style="max-height:180px; overflow-y:auto; border:1px solid #ddd; border-radius:.375rem; padding:10px;">
            <?php foreach ($sucursales as $s): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="sucursales[]" value="<?= $s['id'] ?>" id="suc_<?= $s['id'] ?>"
                        <?= in_array((int)$s['id'], $marcadas, true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="suc_<?= $s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></label>
                </div>
            <?php endforeach; ?>
        </div>
        <small class="text-muted">Marca una o varias sucursales que gestionará.</small>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" name="activo" id="activo" class="form-check-input" <?= $usuario['activo'] ? 'checked' : '' ?>>
            <label for="activo" class="form-check-label">Usuario Activo</label>
        </div>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" name="debe_cambiar_password" id="debe_cambiar_password" class="form-check-input" <?= $usuario['password_change_required'] ? 'checked' : '' ?>>
            <label for="debe_cambiar_password" class="form-check-label">Forzar cambio de contraseña en el próximo inicio de sesión</label>
        </div>
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Actualizar</button>
        <a href="listar.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<script>
function toggleSucursal() {
    const rol = document.getElementById('rol').value;
    const div = document.getElementById('div_sucursal');
    const req = document.getElementById('sucursal_required');
    if (rol === 'admin') {
        div.style.display = 'none';
        req.style.display = 'none';
    } else {
        div.style.display = 'block';
        req.style.display = 'inline';
    }
}
document.addEventListener('DOMContentLoaded', toggleSucursal);
</script>

<?php include '../../includes/footer.php'; ?>
