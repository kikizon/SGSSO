<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM alergias WHERE id = ?");
$stmt->execute([$id]);
$alergia = $stmt->fetch();
if (!$alergia) {
    header('Location: listar.php');
    exit;
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.'; }
else {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } else {
        $stmt = $pdo->prepare("UPDATE alergias SET nombre = ?, descripcion = ?, activo = ? WHERE id = ?");
        try {
            $stmt->execute([$nombre, $descripcion, $activo, $id]);
            $success = 'Alergia actualizada.';
            // Recargar
            $stmt = $pdo->prepare("SELECT * FROM alergias WHERE id = ?");
            $stmt->execute([$id]);
            $alergia = $stmt->fetch();
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = 'El nombre ya existe.';
            } else {
                $error = 'Error al actualizar.';
            }
        }
    }

}}

include '../../includes/header.php';
?>

<h2><i class="fas fa-edit"></i> Editar Alergia</h2>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="listar.php">Ver listado</a></div><?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <div class="mb-3">
        <label>Nombre <span class="text-danger">*</span></label>
        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($alergia['nombre']) ?>" required>
    </div>
    <div class="mb-3">
        <label>Descripción (opcional)</label>
        <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($alergia['descripcion'] ?? '') ?></textarea>
    </div>
    <div class="mb-3 form-check">
        <input type="checkbox" name="activo" id="activo" class="form-check-input" <?= $alergia['activo'] ? 'checked' : '' ?>>
        <label for="activo" class="form-check-label">Activo</label>
    </div>
    <button type="submit" class="btn btn-primary">Actualizar</button>
    <a href="listar.php" class="btn btn-secondary">Cancelar</a>
</form>

<?php include '../../includes/footer.php'; ?>