<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
if ($usuario_rol === 'usuario') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = ?");
$stmt->execute([$id]);
$empleado = $stmt->fetch();

if (!$empleado) {
    header('Location: listar.php');
    exit;
}

$departamentos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre")->fetchAll();
$sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero = trim($_POST['numero_empleado'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $departamento_id = $_POST['departamento_id'] ?? '';
    $sucursal_id = $_POST['sucursal_id'] ?? '';
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (!$numero || !$nombre || !$departamento_id || !$sucursal_id) {
        $error = 'Todos los campos obligatorios deben completarse.';
    } else {
        $stmt = $pdo->prepare("UPDATE empleados SET numero_empleado=?, nombre=?, departamento_id=?, sucursal_id=?, fecha_nacimiento=?, activo=? WHERE id=?");
        try {
            $stmt->execute([$numero, $nombre, $departamento_id, $sucursal_id, $fecha_nacimiento ?: null, $activo, $id]);
            $success = 'Empleado actualizado exitosamente.';
            registrar_auditoria($pdo, $usuario_id, 'UPDATE', 'empleados', $id, 'Datos actualizados');
            // Recargar datos
            $stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = ?");
            $stmt->execute([$id]);
            $empleado = $stmt->fetch();
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = 'El número de empleado ya existe.';
            } else {
                $error = 'Error al actualizar: ' . $e->getMessage();
            }
        }
    }
}

include '../../includes/header.php';
?>

<h2><i class="fas fa-user-edit"></i> Editar Empleado</h2>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="listar.php">Ver listado</a></div><?php endif; ?>

<form method="post" class="row g-3 needs-validation" novalidate>
    <div class="col-md-6">
        <label for="numero_empleado" class="form-label">Número de Empleado <span class="text-danger">*</span></label>
        <input type="text" name="numero_empleado" id="numero_empleado" class="form-control" value="<?= htmlspecialchars($empleado['numero_empleado']) ?>" required>
    </div>
    <div class="col-md-6">
        <label for="nombre" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
        <input type="text" name="nombre" id="nombre" class="form-control" value="<?= htmlspecialchars($empleado['nombre']) ?>" required>
    </div>
    <div class="col-md-6">
        <label for="departamento_id" class="form-label">Departamento <span class="text-danger">*</span></label>
        <select name="departamento_id" id="departamento_id" class="form-select" required>
            <option value="">Seleccione...</option>
            <?php foreach ($departamentos as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $empleado['departamento_id'] == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label for="sucursal_id" class="form-label">Sucursal <span class="text-danger">*</span></label>
        <select name="sucursal_id" id="sucursal_id" class="form-select" required>
            <option value="">Seleccione...</option>
            <?php foreach ($sucursales as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $empleado['sucursal_id'] == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
        <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control" value="<?= htmlspecialchars($empleado['fecha_nacimiento'] ?? '') ?>">
    </div>
    <div class="col-md-6 d-flex align-items-center">
        <div class="form-check">
            <input type="checkbox" name="activo" id="activo" class="form-check-input" <?= $empleado['activo'] ? 'checked' : '' ?>>
            <label for="activo" class="form-check-label">Empleado Activo</label>
        </div>
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Actualizar</button>
        <a href="listar.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>