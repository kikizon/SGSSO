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
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre_completo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? 'usuario';
    $sucursal_id = $_POST['sucursal_id'] ?? null;
    $activo = isset($_POST['activo']) ? 1 : 0;
    $debe_cambiar = isset($_POST['debe_cambiar_password']) ? 1 : 0;

    if (!$nombre || !$email) {
        $error = 'Nombre y email son obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email no válido.';
    } else {
        if ($rol === 'admin') $sucursal_id = null;
        
        $sql = "UPDATE usuarios SET nombre_completo=?, email=?, rol=?, sucursal_id=?, activo=?, debe_cambiar_password=?";
        $params = [$nombre, $email, $rol, $sucursal_id, $activo, $debe_cambiar];
        if (!empty($password)) {
            $sql .= ", password_hash=?, ultimo_cambio_password=NOW()";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $sql .= " WHERE id=?";
        $params[] = $id;

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            // Auditoría
            $detalles = json_encode(['nombre' => $nombre, 'rol' => $rol]);
            registrar_auditoria($pdo, $usuario_id, 'UPDATE', 'usuarios', $id, $detalles);
            
            $success = 'Usuario actualizado.';
            // Recargar
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            $usuario = $stmt->fetch();
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = 'El email ya está en uso.';
            } else {
                $error = 'Error al actualizar.';
            }
        }
    }
}

include '../../includes/header.php';
?>

<h2><i class="fas fa-user-edit"></i> Editar Usuario</h2>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?> <a href="listar.php">Ver listado</a></div><?php endif; ?>

<form method="post" class="row g-3">
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
        <label for="sucursal_id" class="form-label">Sucursal <span id="sucursal_required" class="text-danger">*</span></label>
        <select name="sucursal_id" id="sucursal_id" class="form-select">
            <option value="">Seleccione...</option>
            <?php foreach ($sucursales as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $usuario['sucursal_id'] == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" name="activo" id="activo" class="form-check-input" <?= $usuario['activo'] ? 'checked' : '' ?>>
            <label for="activo" class="form-check-label">Usuario Activo</label>
        </div>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" name="debe_cambiar_password" id="debe_cambiar_password" class="form-check-input" <?= $usuario['debe_cambiar_password'] ? 'checked' : '' ?>>
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
    const select = document.getElementById('sucursal_id');
    const req = document.getElementById('sucursal_required');
    if (rol === 'supervisor') {
        div.style.display = 'block';
        select.required = true;
        req.style.display = 'inline';
    } else {
        div.style.display = 'none';
        select.required = false;
        select.value = '';
        req.style.display = 'none';
    }
}
document.addEventListener('DOMContentLoaded', toggleSucursal);
</script>

<?php include '../../includes/footer.php'; ?>