<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM actos_inseguros WHERE id = ?");
$stmt->execute([$id]);
$acto = $stmt->fetch();
if (!$acto) {
    header('Location: listar.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.'; }
else {
    $descripcion = trim($_POST['descripcion'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (!$descripcion) {
        $error = 'La descripción es obligatoria.';
    } else {
        $stmt = $pdo->prepare("UPDATE actos_inseguros SET descripcion=?, activo=? WHERE id=?");
        try {
            $stmt->execute([$descripcion, $activo, $id]);
            $success = 'Acto inseguro actualizado.';
            $stmt = $pdo->prepare("SELECT * FROM actos_inseguros WHERE id = ?");
            $stmt->execute([$id]);
            $acto = $stmt->fetch();
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = 'Esa descripción ya existe.';
            } else {
                $error = 'Error al actualizar.';
            }
        }
    }

}}

include '../../includes/header.php';
?>

<h2><i class="fas fa-edit"></i> Editar Acto Inseguro</h2>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?> <a href="listar.php">Ver listado</a></div><?php endif; ?>

<form method="post" class="row g-3">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <div class="col-12">
        <label for="descripcion" class="form-label">Descripción <span class="text-danger">*</span></label>
        <input type="text" name="descripcion" id="descripcion" class="form-control" value="<?= htmlspecialchars($acto['descripcion']) ?>" required>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" name="activo" id="activo" class="form-check-input" <?= $acto['activo'] ? 'checked' : '' ?>>
            <label for="activo" class="form-check-label">Activo</label>
        </div>
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Actualizar</button>
        <a href="listar.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>