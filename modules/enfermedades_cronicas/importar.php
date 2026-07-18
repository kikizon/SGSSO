<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$mensajes = [];
$errores = [];
$insertadas = 0;
$enfermedades_creadas = 0;
$omitidas = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv'])) {
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $errores[] = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.'; }
else {
    $archivo = $_FILES['archivo_csv'];

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $errores[] = 'Error al subir el archivo.';
    } else {
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            $errores[] = 'El archivo debe tener extensión .csv';
        } else {
            if (($handle = fopen($archivo['tmp_name'], 'r')) !== false) {
                $linea = fgets($handle);
                if ($linea === false) {
                    $errores[] = 'El archivo está vacío.';
                } else {
                    // Eliminar BOM si existe
                    if (substr($linea, 0, 3) === "\xEF\xBB\xBF") {
                        $linea = substr($linea, 3);
                    }
                    $encabezados = str_getcsv(trim($linea));
                    $esperados = ['numero_empleado', 'nombre_empleado', 'enfermedades'];

                    $encabezados_limpios = array_map('strtolower', array_map('trim', $encabezados));

                    if ($encabezados_limpios !== $esperados) {
                        $errores[] = 'Encabezados incorrectos. Se esperaba: ' . implode(', ', $esperados);
                    } else {
                        $pdo->beginTransaction();

                        while (($fila = fgetcsv($handle, 1000, ',')) !== false) {
                            if (count($fila) < 3) continue;

                            $numero_empleado = trim($fila[0]);
                            $nombre_empleado = trim($fila[1]); // Solo referencia, no se usa
                            $enfermedades_str = trim($fila[2]);

                            if (empty($numero_empleado) || empty($enfermedades_str)) {
                                $omitidas++;
                                $errores[] = "Fila omitida (datos incompletos): $numero_empleado";
                                continue;
                            }

                            // Buscar empleado
                            $stmt = $pdo->prepare("SELECT id FROM empleados WHERE numero_empleado = ?");
                            $stmt->execute([$numero_empleado]);
                            $empleado_id = $stmt->fetchColumn();
                            if (!$empleado_id) {
                                $omitidas++;
                                $errores[] = "Empleado no encontrado: $numero_empleado";
                                continue;
                            }

                            // Separar enfermedades por coma y procesar cada una
                            $enfermedades = array_map('trim', explode(',', $enfermedades_str));
                            foreach ($enfermedades as $nombre_enfermedad) {
                                if (empty($nombre_enfermedad)) continue;

                                // Buscar o crear enfermedad
                                $stmt = $pdo->prepare("SELECT id FROM enfermedades_cronicas WHERE nombre = ?");
                                $stmt->execute([$nombre_enfermedad]);
                                $enfermedad_id = $stmt->fetchColumn();
                                if (!$enfermedad_id) {
                                    $stmt = $pdo->prepare("INSERT INTO enfermedades_cronicas (nombre) VALUES (?)");
                                    $stmt->execute([$nombre_enfermedad]);
                                    $enfermedad_id = $pdo->lastInsertId();
                                    $enfermedades_creadas++;
                                    $mensajes[] = "Nueva enfermedad creada: $nombre_enfermedad";
                                }

                                // Insertar relación (ignorar duplicados)
                                $stmt = $pdo->prepare("INSERT IGNORE INTO empleado_enfermedad (empleado_id, enfermedad_id) VALUES (?, ?)");
                                $stmt->execute([$empleado_id, $enfermedad_id]);
                                if ($stmt->rowCount() > 0) {
                                    $insertadas++;
                                    $mensajes[] = "Asignada: $numero_empleado -> $nombre_enfermedad";
                                } else {
                                    $omitidas++;
                                    $errores[] = "Relación ya existente: $numero_empleado - $nombre_enfermedad";
                                }
                            }
                        }

                        $pdo->commit();
                    }
                }
                fclose($handle);
            } else {
                $errores[] = 'No se pudo leer el archivo.';
            }
        }
    }

}}

include '../../includes/header.php';
?>

<h2><i class="fas fa-upload"></i> Importar Enfermedades de Empleados desde CSV</h2>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Instrucciones</div>
            <div class="card-body">
                <p>El archivo CSV debe tener las siguientes columnas en este orden:</p>
                <ul>
                    <li><strong>numero_empleado</strong> (debe existir en el sistema)</li>
                    <li><strong>nombre_empleado</strong> (solo para referencia, no se valida)</li>
                    <li><strong>enfermedades</strong> (lista de enfermedades separadas por coma)</li>
                </ul>
                <p>Si una enfermedad no existe, se creará automáticamente. Las relaciones ya existentes se ignoran.</p>
                <a href="plantilla.php" class="btn btn-outline-secondary" target="_blank">
                    <i class="fas fa-download"></i> Descargar Plantilla CSV
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Subir archivo CSV</div>
            <div class="card-body">
                <?php if (!empty($errores)): ?>
                    <div class="alert alert-warning">
                        <strong>Advertencias/Errores:</strong>
                        <ul class="mb-0">
                            <?php foreach ($errores as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($insertadas > 0 || $enfermedades_creadas > 0): ?>
                    <div class="alert alert-success">
                        <strong>Resultado:</strong> 
                        <?= $insertadas ?> relaciones creadas, <?= $enfermedades_creadas ?> nuevas enfermedades, <?= $omitidas ?> omitidas.
                    </div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <div class="mb-3">
                        <label for="archivo_csv" class="form-label">Seleccione archivo CSV</label>
                        <input type="file" class="form-control" id="archivo_csv" name="archivo_csv" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Importar</button>
                    <a href="listar.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>