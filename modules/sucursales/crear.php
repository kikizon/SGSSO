<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.'; }
else {
    $nombre = trim($_POST['nombre'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#6c757d';

    if ($nombre === '') {
        $error = 'El nombre de la sucursal es obligatorio.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO sucursales (nombre, direccion, color) VALUES (?, ?, ?)");
        try {
            $stmt->execute([$nombre, $direccion, $color]);
            $success = 'Sucursal creada exitosamente.';
            $_POST = [];
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = 'El nombre de la sucursal ya existe.';
            } else {
                error_log($e->getMessage()); $error = 'Ocurrió un error. Intenta de nuevo.';
            }
        }
    }

}}

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
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <div class="col-md-6">
        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
        <input type="text" name="nombre" id="nombre" class="form-control" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
        <label for="direccion" class="form-label">Dirección (opcional)</label>
        <input type="text" name="direccion" id="direccion" class="form-control" value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>">
    </div>
    <div class="col-md-6">
        <label for="color" class="form-label">Color del badge</label>
        <div class="d-flex align-items-center gap-2">
            <input type="color" name="color" id="color" class="form-control form-control-color" value="<?= htmlspecialchars($_POST['color'] ?? '#0d6efd') ?>" title="Elige un color">
            <span class="badge" id="previewBadge" style="background-color: <?= htmlspecialchars($_POST['color'] ?? '#0d6efd') ?>; color:#fff;">Vista previa</span>
        </div>
        <small class="text-muted">Se usa para identificar la sucursal en listados y tableros.</small>
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
        <a href="listar.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<script>
document.getElementById('color').addEventListener('input', function () {
    document.getElementById('previewBadge').style.backgroundColor = this.value;
});
</script>

<?php include '../../includes/footer.php'; ?>
