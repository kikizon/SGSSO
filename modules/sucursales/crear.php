<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');

    if ($nombre === '') {
        $error = 'El nombre de la sucursal es obligatorio.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO sucursales (nombre, direccion) VALUES (?, ?)");
        try {
            $stmt->execute([$nombre, $direccion]);
            $success = 'Sucursal creada exitosamente.';
            $_POST = [];
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = 'El nombre de la sucursal ya existe.';
            } else {
                $error = 'Error al guardar: ' . $e->getMessage();
            }
        }
    }
}

include '../../includes/header.php';
?>

<h2><i class="fas fa-plus-circle"></i> Nueva Sucursal</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="listar.php">Ver listado</a></div>
<?php endif; ?>

<form method="post" class="row g-3">
    <div class="col-md-6">
        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
        <input type="text" name="nombre" id="nombre" class="form-control" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
        <label for="direccion" class="form-label">Dirección (opcional)</label>
        <input type="text" name="direccion" id="direccion" class="form-control" value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>">
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
        <a href="listar.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>