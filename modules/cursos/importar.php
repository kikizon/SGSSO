<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$mensajes = [];
$errores = [];
$insertadas = 0;
$cursos_creados = 0;
$omitidas = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv'])) {
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
                    if (substr($linea, 0, 3) === "\xEF\xBB\xBF") {
                        $linea = substr($linea, 3);
                    }
                    $encabezados = str_getcsv(trim($linea));
                    $esperados = ['numero_empleado', 'nombre_empleado', 'cursos', 'fecha_realizacion'];

                    $encabezados_limpios = array_map('strtolower', array_map('trim', $encabezados));

                    if ($encabezados_limpios !== $esperados) {
                        $errores[] = 'Encabezados incorrectos. Se esperaba: ' . implode(', ', $esperados);
                    } else {
                        $pdo->beginTransaction();

                        while (($fila = fgetcsv($handle, 1000, ',')) !== false) {
                            if (count($fila) < 4) continue;

                            $numero_empleado = trim($fila[0]);
                            $nombre_empleado = trim($fila[1]); // Solo referencia
                            $cursos_str = trim($fila[2]);
                            $fecha_realizacion = trim($fila[3]);

                            if (empty($numero_empleado) || empty($cursos_str) || empty($fecha_realizacion)) {
                                $omitidas++;
                                $errores[] = "Fila omitida (datos incompletos): $numero_empleado";
                                continue;
                            }

                            // Validar fecha
                            $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_realizacion);
                            if (!$fecha_obj || $fecha_obj->format('Y-m-d') !== $fecha_realizacion) {
                                $omitidas++;
                                $errores[] = "Fecha inválida para $numero_empleado: $fecha_realizacion (use YYYY-MM-DD)";
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

                            // Separar cursos por coma y procesar cada uno
                            $cursos = array_map('trim', explode(',', $cursos_str));
                            foreach ($cursos as $nombre_curso) {
                                if (empty($nombre_curso)) continue;

                                // Buscar o crear curso
                                $stmt = $pdo->prepare("SELECT id FROM cursos WHERE nombre = ?");
                                $stmt->execute([$nombre_curso]);
                                $curso_id = $stmt->fetchColumn();
                                if (!$curso_id) {
                                    $stmt = $pdo->prepare("INSERT INTO cursos (nombre) VALUES (?)");
                                    $stmt->execute([$nombre_curso]);
                                    $curso_id = $pdo->lastInsertId();
                                    $cursos_creados++;
                                    $mensajes[] = "Nuevo curso creado: $nombre_curso";
                                }

                                // Insertar relación (ignorar duplicados)
                                $stmt = $pdo->prepare("INSERT IGNORE INTO empleado_curso (empleado_id, curso_id, fecha_realizacion) VALUES (?, ?, ?)");
                                $stmt->execute([$empleado_id, $curso_id, $fecha_realizacion]);
                                if ($stmt->rowCount() > 0) {
                                    $insertadas++;
                                    $mensajes[] = "Asignado: $numero_empleado -> $nombre_curso ($fecha_realizacion)";
                                } else {
                                    $omitidas++;
                                    $errores[] = "Relación ya existente: $numero_empleado - $nombre_curso en esa fecha";
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
}

include '../../includes/header.php';
?>

<h2><i class="fas fa-upload"></i> Importar Cursos/Formatos de Empleados desde CSV</h2>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Instrucciones</div>
            <div class="card-body">
                <p>El archivo CSV debe tener las siguientes columnas en este orden:</p>
                <ul>
                    <li><strong>numero_empleado</strong> (debe existir en el sistema)</li>
                    <li><strong>nombre_empleado</strong> (solo para referencia, no se valida)</li>
                    <li><strong>cursos</strong> (lista de cursos o formatos separados por coma)</li>
                    <li><strong>fecha_realizacion</strong> (formato YYYY-MM-DD)</li>
                </ul>
                <p>Si un curso/formato no existe, se creará automáticamente. Las relaciones ya existentes se ignoran.</p>
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

                <?php if ($insertadas > 0 || $cursos_creados > 0): ?>
                    <div class="alert alert-success">
                        <strong>Resultado:</strong> 
                        <?= $insertadas ?> relaciones creadas, <?= $cursos_creados ?> nuevos cursos, <?= $omitidas ?> omitidas.
                    </div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data">
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