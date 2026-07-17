<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
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
    $sucursales_sel = array_values(array_unique(array_map('intval', (array)($_POST['sucursales'] ?? []))));
    $activo = isset($_POST['activo']) ? 1 : 0;
    $debe_cambiar = isset($_POST['debe_cambiar_password']) ? 1 : 0;

    if ($rol === 'admin') { $sucursales_sel = []; }
    $primaria = $sucursales_sel[0] ?? null; // compat con usuarios.sucursal_id

    if (!$nombre || !$email || !$password) {
        $error = 'Todos los campos obligatorios deben completarse.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email no válido.';
    } elseif ($rol !== 'admin' && empty($sucursales_sel)) {
        $error = 'Debe asignar al menos una sucursal a usuarios y supervisores.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre_completo, email, password_hash, rol, sucursal_id, activo, debe_cambiar_password, ultimo_cambio_password) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$nombre, $email, $hash, $rol, $primaria, $activo, $debe_cambiar]);
            $nuevo_usuario_id = $pdo->lastInsertId();

            if ($rol !== 'admin' && !empty($sucursales_sel)) {
                $ins = $pdo->prepare("INSERT IGNORE INTO usuario_sucursales (usuario_id, sucursal_id) VALUES (?, ?)");
                foreach ($sucursales_sel as $sid) { $ins->execute([$nuevo_usuario_id, $sid]); }
            }

            $detalles = json_encode(['nombre' => $nombre, 'email' => $email, 'rol' => $rol, 'sucursales' => $sucursales_sel]);
            registrar_auditoria($pdo, $usuario_id, 'INSERT', 'usuarios', $nuevo_usuario_id, $detalles);

            $pdo->commit();
            $success = 'Usuario creado exitosamente.';
            $_POST = [];
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->errorInfo[1] == 1062) {
                $error = 'El email ya está registrado.';
            } else {
                $error = 'Error al guardar.';
            }
        }
    }
}

include '../../includes/header.php';
?>

<h2><i class="fas fa-user-plus"></i> Nuevo Usuario</h2>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?> <a href="listar.php">Ver listado</a></div><?php endif; ?>

<form method="post" class="row g-3 needs-validation" novalidate>
    <div class="col-md-6">
        <label for="nombre_completo" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
        <input type="text" name="nombre_completo" id="nombre_completo" class="form-control" value="<?= htmlspecialchars($_POST['nombre_completo'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
        <label for="password" class="form-label">Contraseña <span class="text-danger">*</span></label>
        <input type="password" name="password" id="password" class="form-control" required>
    </div>
    <div class="col-md-6">
        <label for="rol" class="form-label">Rol</label>
        <select name="rol" id="rol" class="form-select" onchange="toggleSucursal()">
            <option value="usuario" <?= (($_POST['rol'] ?? '') == 'usuario') ? 'selected' : '' ?>>Usuario</option>
            <option value="supervisor" <?= (($_POST['rol'] ?? '') == 'supervisor') ? 'selected' : '' ?>>Supervisor</option>
            <option value="admin" <?= (($_POST['rol'] ?? '') == 'admin') ? 'selected' : '' ?>>Administrador</option>
        </select>
    </div>
    <div class="col-md-6" id="div_sucursal">
        <label class="form-label">Sucursales <span id="sucursal_required" class="text-danger">*</span></label>
        <div style="max-height:180px; overflow-y:auto; border:1px solid #ddd; border-radius:.375rem; padding:10px;">
            <?php foreach ($sucursales as $s): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="sucursales[]" value="<?= $s['id'] ?>" id="suc_<?= $s['id'] ?>"
                        <?= in_array($s['id'], $_POST['sucursales'] ?? []) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="suc_<?= $s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></label>
                </div>
            <?php endforeach; ?>
        </div>
        <small class="text-muted">Marca una o varias sucursales que gestionará.</small>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" name="activo" id="activo" class="form-check-input" <?= isset($_POST['activo']) ? 'checked' : 'checked' ?>>
            <label for="activo" class="form-check-label">Usuario Activo</label>
        </div>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" name="debe_cambiar_password" id="debe_cambiar_password" class="form-check-input" <?= isset($_POST['debe_cambiar_password']) ? 'checked' : '' ?>>
            <label for="debe_cambiar_password" class="form-check-label">Forzar cambio de contraseña en el primer inicio de sesión</label>
        </div>
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
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
