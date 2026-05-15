<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$mensajes = [];
$errores = [];
$insertados = 0;
$actualizados = 0;
$omitidos = 0;

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
                    // Eliminar BOM
                    if (substr($linea, 0, 3) === "\xEF\xBB\xBF") {
                        $linea = substr($linea, 3);
                    }
                    $encabezados = str_getcsv(trim($linea));
                    $esperados = ['numero_empleado', 'nombre', 'departamento', 'sucursal', 'fecha_nacimiento'];

                    $encabezados_limpios = array_map('strtolower', array_map('trim', $encabezados));

                    if ($encabezados_limpios !== $esperados) {
                        $errores[] = 'Encabezados incorrectos. Se esperaba: ' . implode(', ', $esperados);
                    } else {
                        $pdo->beginTransaction();

                        while (($fila = fgetcsv($handle, 1000, ',')) !== false) {
                            if (count($fila) < 5) continue;

                            $numero = trim($fila[0]);
                            $nombre = trim($fila[1]);
                            $depto_nombre = trim($fila[2]);
                            $suc_nombre = trim($fila[3]);
                            $fecha_nacimiento = trim($fila[4]);

                            if (empty($numero) || empty($nombre) || empty($depto_nombre) || empty($suc_nombre)) {
                                $omitidos++;
                                $errores[] = "Fila omitida (datos incompletos): $numero - $nombre";
                                continue;
                            }

                            // Validar formato de fecha (YYYY-MM-DD) si no está vacía
                            if (!empty($fecha_nacimiento)) {
                                $d = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
                                if (!$d || $d->format('Y-m-d') !== $fecha_nacimiento) {
                                    $errores[] = "Fecha de nacimiento inválida para $numero, se omitirá la fecha.";
                                    $fecha_nacimiento = null;
                                }
                            } else {
                                $fecha_nacimiento = null;
                            }

                            // 1. Sucursal
                            $stmt = $pdo->prepare("SELECT id FROM sucursales WHERE nombre = ?");
                            $stmt->execute([$suc_nombre]);
                            $suc_id = $stmt->fetchColumn();
                            if (!$suc_id) {
                                $stmt = $pdo->prepare("INSERT INTO sucursales (nombre, activo) VALUES (?, 1)");
                                $stmt->execute([$suc_nombre]);
                                $suc_id = $pdo->lastInsertId();
                                $mensajes[] = "Nueva sucursal creada: $suc_nombre";
                            }

                            // 2. Departamento
                            $stmt = $pdo->prepare("SELECT id FROM departamentos WHERE nombre = ?");
                            $stmt->execute([$depto_nombre]);
                            $depto_id = $stmt->fetchColumn();
                            if (!$depto_id) {
                                $stmt = $pdo->prepare("INSERT INTO departamentos (nombre, activo) VALUES (?, 1)");
                                $stmt->execute([$depto_nombre]);
                                $depto_id = $pdo->lastInsertId();
                                $mensajes[] = "Nuevo departamento creado: $depto_nombre";
                            }

                            // 3. Empleado
                            $stmt = $pdo->prepare("SELECT id FROM empleados WHERE numero_empleado = ?");
                            $stmt->execute([$numero]);
                            $emp_id = $stmt->fetchColumn();

                            if ($emp_id) {
                                $stmt = $pdo->prepare("UPDATE empleados SET nombre=?, departamento_id=?, sucursal_id=?, fecha_nacimiento=?, activo=1 WHERE id=?");
                                $stmt->execute([$nombre, $depto_id, $suc_id, $fecha_nacimiento, $emp_id]);
                                $actualizados++;
                                $mensajes[] = "Empleado actualizado: $numero - $nombre";
                            } else {
                                $stmt = $pdo->prepare("INSERT INTO empleados (numero_empleado, nombre, departamento_id, sucursal_id, fecha_nacimiento, activo) VALUES (?, ?, ?, ?, ?, 1)");
                                $stmt->execute([$numero, $nombre, $depto_id, $suc_id, $fecha_nacimiento]);
                                $insertados++;
                                $mensajes[] = "Empleado insertado: $numero - $nombre";
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

<h2><i class="fas fa-upload"></i> Importar Empleados desde CSV</h2>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Instrucciones</div>
            <div class="card-body">
                <p>El archivo CSV debe tener las siguientes columnas en este orden:</p>
                <ul>
                    <li><strong>numero_empleado</strong> (texto, único)</li>
                    <li><strong>nombre</strong> (texto)</li>
                    <li><strong>departamento</strong> (texto)</li>
                    <li><strong>sucursal</strong> (texto)</li>
                    <li><strong>fecha_nacimiento</strong> (YYYY-MM-DD, opcional)</li>
                </ul>
                <p><strong>OJO: </strong>Si el departamento o sucursal no existen, se crearán automáticamente.</p>
                <p>Si el número de empleado ya existe, se actualizarán sus datos.</p>
                <p><strong>Importante:</strong> Al guardar el CSV desde Excel, elija "CSV UTF-8 (delimitado por comas)" para evitar problemas de formato.</p>
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

                <?php if ($insertados > 0 || $actualizados > 0): ?>
                    <div class="alert alert-success">
                        <strong>Resultado:</strong> 
                        <?= $insertados ?> insertados, <?= $actualizados ?> actualizados, <?= $omitidos ?> omitidos.
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