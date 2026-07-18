<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM departamentos WHERE id = ?");
$stmt->execute([$id]);
$departamento = $stmt->fetch();

if (!$departamento) {
    header('Location: listar.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.'; }
else {
    $nombre = trim($_POST['nombre'] ?? '');

    if ($nombre === '') {
        $error = 'El nombre del departamento es obligatorio.';
    } else {
        $stmt = $pdo->prepare("UPDATE departamentos SET nombre = ? WHERE id = ?");
        try {
            $stmt->execute([$nombre, $id]);
            $success = 'Departamento actualizado exitosamente.';
            // Recargar datos
            $stmt = $pdo->prepare("SELECT * FROM departamentos WHERE id = ?");
            $stmt->execute([$id]);
            $departamento = $stmt->fetch();
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = 'El nombre del departamento ya existe.';
            } else {
                $error = 'Error al actualizar.';
            }
        }
    }

}}

include '../../includes/header.php';
?>

<h2><i class="fas fa-edit"></i> Editar Departamento</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="listar.php">Ver listado</a></div>
<?php endif; ?>

<form method="post" class="row g-3">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <div class="col-12">
        <label for="nombre" class="form-label">Nombre del Departamento <span class="text-danger">*</span></label>
        <input type="text" name="nombre" id="nombre" class="form-control" value="<?= htmlspecialchars($departamento['nombre']) ?>" required>
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Actualizar</button>
        <a href="listar.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>