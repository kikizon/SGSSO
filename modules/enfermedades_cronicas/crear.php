<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO enfermedades_cronicas (nombre, descripcion) VALUES (?, ?)");
        try {
            $stmt->execute([$nombre, $descripcion]);
            $success = 'Enfermedad creada exitosamente.';
            $_POST = [];
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = 'El nombre ya existe.';
            } else {
                $error = 'Error al guardar.';
            }
        }
    }
}

include '../../includes/header.php';
?>

<h2><i class="fas fa-plus-circle"></i> Nueva Enfermedad Crónica</h2>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="listar.php">Ver listado</a></div><?php endif; ?>

<form method="post">
    <div class="mb-3">
        <label>Nombre <span class="text-danger">*</span></label>
        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
    </div>
    <div class="mb-3">
        <label>Descripción (opcional)</label>
        <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Guardar</button>
    <a href="listar.php" class="btn btn-secondary">Cancelar</a>
</form>

<?php include '../../includes/footer.php'; ?>