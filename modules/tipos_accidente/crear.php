<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.'; }
else {
    $descripcion = trim($_POST['descripcion'] ?? '');
    if ($descripcion === '') {
        $error = 'La descripción es obligatoria.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO tipos_accidente (descripcion) VALUES (?)");
        try {
            $stmt->execute([$descripcion]);
            $success = 'Tipo de accidente creado.';
            $_POST = [];
        } catch (PDOException $e) {
            $error = ($e->errorInfo[1] == 1062) ? 'Esa descripción ya existe.' : 'Error al guardar.';
        }
    }

}}

include '../../includes/header.php';
?>

<h2><i class="fas fa-plus-circle"></i> Nuevo Tipo de Accidente</h2>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?> <a href="listar.php">Ver listado</a></div><?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <div class="mb-3">
        <label>Descripción <span class="text-danger">*</span></label>
        <input type="text" name="descripcion" class="form-control" value="<?= htmlspecialchars($_POST['descripcion'] ?? '') ?>" required>
    </div>
    <button type="submit" class="btn btn-primary">Guardar</button>
    <a href="listar.php" class="btn btn-secondary">Cancelar</a>
</form>

<?php include '../../includes/footer.php'; ?>