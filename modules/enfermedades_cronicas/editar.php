<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM enfermedades_cronicas WHERE id = ?");
$stmt->execute([$id]);
$enfermedad = $stmt->fetch();
if (!$enfermedad) {
    header('Location: listar.php');
    exit;
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } else {
        $stmt = $pdo->prepare("UPDATE enfermedades_cronicas SET nombre = ?, descripcion = ?, activo = ? WHERE id = ?");
        try {
            $stmt->execute([$nombre, $descripcion, $activo, $id]);
            $success = 'Enfermedad actualizada.';
            // Recargar
            $stmt = $pdo->prepare("SELECT * FROM enfermedades_cronicas WHERE id = ?");
            $stmt->execute([$id]);
            $enfermedad = $stmt->fetch();
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = 'El nombre ya existe.';
            } else {
                $error = 'Error al actualizar.';
            }
        }
    }
}

include '../../includes/header.php';
?>

<h2><i class="fas fa-edit"></i> Editar Enfermedad Crónica</h2>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="listar.php">Ver listado</a></div><?php endif; ?>

<form method="post">
    <div class="mb-3">
        <label>Nombre <span class="text-danger">*</span></label>
        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($enfermedad['nombre']) ?>" required>
    </div>
    <div class="mb-3">
        <label>Descripción (opcional)</label>
        <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($enfermedad['descripcion'] ?? '') ?></textarea>
    </div>
    <div class="mb-3 form-check">
        <input type="checkbox" name="activo" id="activo" class="form-check-input" <?= $enfermedad['activo'] ? 'checked' : '' ?>>
        <label for="activo" class="form-check-label">Activo</label>
    </div>
    <button type="submit" class="btn btn-primary">Actualizar</button>
    <a href="listar.php" class="btn btn-secondary">Cancelar</a>
</form>

<?php include '../../includes/footer.php'; ?>