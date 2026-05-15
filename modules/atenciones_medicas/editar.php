<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM atenciones_medicas WHERE id = ?");
$stmt->execute([$id]);
$atencion = $stmt->fetch();
if (!$atencion) {
    header('Location: listar.php');
    exit;
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descripcion = trim($_POST['descripcion'] ?? '');
    if ($descripcion === '') {
        $error = 'La descripción es obligatoria.';
    } else {
        $stmt = $pdo->prepare("UPDATE atenciones_medicas SET descripcion = ? WHERE id = ?");
        try {
            $stmt->execute([$descripcion, $id]);
            $success = 'Atención médica actualizada.';
            $stmt = $pdo->prepare("SELECT * FROM atenciones_medicas WHERE id = ?");
            $stmt->execute([$id]);
            $atencion = $stmt->fetch();
        } catch (PDOException $e) {
            $error = ($e->errorInfo[1] == 1062) ? 'Esa descripción ya existe.' : 'Error al actualizar.';
        }
    }
}

include '../../includes/header.php';
?>

<h2><i class="fas fa-edit"></i> Editar Atención Médica</h2>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?> <a href="listar.php">Ver listado</a></div><?php endif; ?>

<form method="post">
    <div class="mb-3">
        <label>Descripción <span class="text-danger">*</span></label>
        <input type="text" name="descripcion" class="form-control" value="<?= htmlspecialchars($atencion['descripcion']) ?>" required>
    </div>
    <button type="submit" class="btn btn-primary">Actualizar</button>
    <a href="listar.php" class="btn btn-secondary">Cancelar</a>
</form>

<?php include '../../includes/footer.php'; ?>