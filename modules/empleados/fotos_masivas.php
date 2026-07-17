<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
@include_once '../../includes/image_optim.php';

if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

/** Tokeniza y normaliza (minúsculas, sin acentos, sin signos). Devuelve tokens ordenados. */
function fm_tokens($s) {
    $s = mb_strtolower(trim((string)$s), 'UTF-8');
    $from = ['á','é','í','ó','ú','ü','ñ','à','è','ì','ò','ù','â','ê','î','ô','û','ä','ë','ï','ö','ç'];
    $to   = ['a','e','i','o','u','u','n','a','e','i','o','u','a','e','i','o','u','a','e','i','o','c'];
    $s = str_replace($from, $to, $s);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    $s = trim(preg_replace('/\s+/', ' ', $s));
    return $s === '' ? [] : explode(' ', $s);
}
/** Clave por nombre: conjunto de palabras (sin numéricas), ordenado. */
function fm_name_key($s) {
    $t = array_values(array_filter(fm_tokens($s), fn($w) => !ctype_digit($w)));
    sort($t);
    return implode(' ', $t);
}
/** Guarda un archivo subido como foto y devuelve el nombre, o null. */
function fm_guardar_foto($tmp, $err, $size) {
    if ($err !== UPLOAD_ERR_OK || $size <= 0 || $size > 8 * 1024 * 1024) return null;
    if (!is_uploaded_file($tmp)) return null;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) return null;
    $nombre = 'emp_' . uniqid('', true) . '.' . $ext;
    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
    if (!move_uploaded_file($tmp, UPLOAD_DIR . $nombre)) return null;
    if (function_exists('optimizar_imagen')) { optimizar_imagen(UPLOAD_DIR . $nombre, 600, 80); }
    return $nombre;
}

$reporte = null;
$modo = $_POST['modo'] ?? 'nombre';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Token CSRF inválido.');
    }

    // Índices de empleados
    $empleados = $pdo->query("SELECT id, numero_empleado, nombre, sucursal_id, foto FROM empleados WHERE activo = 1")->fetchAll();
    $porNombre = [];  // name_key => [emp,...]
    $porNumero = [];  // numero(lower) => emp
    foreach ($empleados as $e) {
        $porNombre[fm_name_key($e['nombre'])][] = $e;
        $porNumero[strtolower(trim($e['numero_empleado']))] = $e;
    }

    $asignadas = []; $ambiguas = []; $sinCoincidencia = []; $invalidas = [];

    $files = $_FILES['fotos'] ?? null;
    $n = $files ? count($files['name']) : 0;

    for ($i = 0; $i < $n; $i++) {
        $orig = $files['name'][$i];
        if ($orig === '') continue;
        $base = pathinfo($orig, PATHINFO_FILENAME);

        // 1) intento por número (por si el archivo se llama con el número)
        $emp = $porNumero[strtolower(trim($base))] ?? null;

        // 2) si no, por nombre (conjunto de palabras)
        if (!$emp && $modo !== 'numero') {
            $k = fm_name_key($base);
            $cand = $porNombre[$k] ?? [];
            if (count($cand) === 1) {
                $emp = $cand[0];
            } elseif (count($cand) > 1) {
                $ambiguas[] = ['archivo' => $orig, 'detalle' => 'Coincide con ' . count($cand) . ': ' . implode(', ', array_map(fn($e) => $e['numero_empleado'], $cand))];
                continue;
            }
        }

        if (!$emp) {
            $sinCoincidencia[] = ['archivo' => $orig, 'detalle' => 'Ningún empleado con ese nombre/número'];
            continue;
        }

        // Validar y guardar
        $nombreArchivo = fm_guardar_foto($files['tmp_name'][$i], $files['error'][$i], $files['size'][$i]);
        if (!$nombreArchivo) {
            $invalidas[] = ['archivo' => $orig, 'detalle' => 'Formato no válido o archivo dañado (usa JPG/PNG/WEBP)'];
            continue;
        }

        // Reemplaza foto anterior
        if (!empty($emp['foto']) && is_file(UPLOAD_DIR . $emp['foto'])) { @unlink(UPLOAD_DIR . $emp['foto']); }
        $pdo->prepare("UPDATE empleados SET foto = ? WHERE id = ?")->execute([$nombreArchivo, $emp['id']]);
        registrar_auditoria($pdo, $usuario_id, 'UPDATE', 'empleados', $emp['id'], json_encode(['foto_masiva' => $nombreArchivo], JSON_UNESCAPED_UNICODE));

        $asignadas[] = ['archivo' => $orig, 'empleado' => $emp['numero_empleado'] . ' - ' . $emp['nombre'], 'foto' => $nombreArchivo];
    }

    $reporte = compact('asignadas', 'ambiguas', 'sinCoincidencia', 'invalidas');
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="mb-0"><i class="fas fa-images"></i> Carga masiva de fotos</h2>
    <a href="listar.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Volver a empleados</a>
</div>

<div class="alert alert-info">
    <strong>Cómo funciona:</strong> nombra cada archivo con el <strong>nombre del empleado</strong> (en cualquier orden: "Apellido Nombre" o "Nombre Apellido") o con su <strong>número</strong>.
    El emparejamiento ignora acentos, mayúsculas y el orden de las palabras. Solo se asigna cuando hay <strong>una coincidencia exacta y única</strong>; si coincide con varios o con ninguno, se te reporta para que ajustes el nombre del archivo.
    <br>Formatos: JPG, PNG, WEBP. Si tienes muchas, súbelas en tandas (el servidor suele limitar a ~20 archivos por envío).
</div>

<form method="post" enctype="multipart/form-data" class="card card-body mb-4">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Emparejar por</label>
            <select name="modo" class="form-select">
                <option value="nombre" <?= $modo === 'nombre' ? 'selected' : '' ?>>Nombre (recomendado)</option>
                <option value="numero" <?= $modo === 'numero' ? 'selected' : '' ?>>Solo número de empleado</option>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label">Fotos</label>
            <input type="file" name="fotos[]" class="form-control" accept="image/jpeg,image/png,image/webp" multiple required>
            <small class="text-muted">Selecciona varias a la vez (Ctrl/Cmd+clic o Ctrl+A dentro de la carpeta).</small>
        </div>
        <div class="col-md-3 d-grid">
            <button class="btn btn-primary"><i class="fas fa-upload"></i> Subir y asignar</button>
        </div>
    </div>
</form>

<?php if ($reporte !== null): ?>
    <div class="row g-2 mb-3">
        <div class="col"><div class="card text-center border-success"><div class="card-body py-2"><div class="h4 mb-0 text-success"><?= count($reporte['asignadas']) ?></div><small>Asignadas</small></div></div></div>
        <div class="col"><div class="card text-center border-warning"><div class="card-body py-2"><div class="h4 mb-0 text-warning"><?= count($reporte['ambiguas']) ?></div><small>Ambiguas</small></div></div></div>
        <div class="col"><div class="card text-center border-secondary"><div class="card-body py-2"><div class="h4 mb-0 text-secondary"><?= count($reporte['sinCoincidencia']) ?></div><small>Sin coincidencia</small></div></div></div>
        <div class="col"><div class="card text-center border-danger"><div class="card-body py-2"><div class="h4 mb-0 text-danger"><?= count($reporte['invalidas']) ?></div><small>Inválidas</small></div></div></div>
    </div>

    <?php if (!empty($reporte['asignadas'])): ?>
    <div class="card mb-3">
        <div class="card-header bg-success text-white"><i class="fas fa-check"></i> Asignadas (<?= count($reporte['asignadas']) ?>)</div>
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Foto</th><th>Archivo</th><th>Empleado</th></tr></thead>
                <tbody>
                    <?php foreach ($reporte['asignadas'] as $r): ?>
                    <tr>
                        <td><img src="<?= UPLOAD_URL . htmlspecialchars($r['foto']) ?>" class="rounded" style="width:36px;height:36px;object-fit:cover;"></td>
                        <td><?= htmlspecialchars($r['archivo']) ?></td>
                        <td><?= htmlspecialchars($r['empleado']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php foreach ([
        ['ambiguas', 'Ambiguas (coinciden con varios)', 'warning'],
        ['sinCoincidencia', 'Sin coincidencia', 'secondary'],
        ['invalidas', 'Inválidas', 'danger'],
    ] as [$clave, $titulo, $color]): ?>
        <?php if (!empty($reporte[$clave])): ?>
        <div class="card mb-3">
            <div class="card-header bg-<?= $color ?> <?= $color === 'warning' ? 'text-dark' : 'text-white' ?>"><?= htmlspecialchars($titulo) ?> (<?= count($reporte[$clave]) ?>)</div>
            <div class="card-body table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Archivo</th><th>Detalle</th></tr></thead>
                    <tbody>
                        <?php foreach ($reporte[$clave] as $r): ?>
                        <tr><td><?= htmlspecialchars($r['archivo']) ?></td><td><?= htmlspecialchars($r['detalle']) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
