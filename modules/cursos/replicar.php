<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit;
}

$curso_id = (int)($_POST['curso_id'] ?? 0);
$sucursal_destino = (int)($_POST['sucursal_destino'] ?? 0);
if (!$curso_id || !$sucursal_destino) {
    header('Location: listar.php?rep=err');
    exit;
}

// Curso origen
$st = $pdo->prepare("SELECT * FROM cursos WHERE id = ?");
$st->execute([$curso_id]);
$curso = $st->fetch();
if (!$curso) { header('Location: listar.php?rep=err'); exit; }

// Sucursal destino válida
$sv = $pdo->prepare("SELECT COUNT(*) FROM sucursales WHERE id = ? AND activo = 1");
$sv->execute([$sucursal_destino]);
if (!$sv->fetchColumn()) { header('Location: listar.php?rep=err'); exit; }

// Asignaciones origen
$asg = $pdo->prepare("SELECT * FROM curso_asignaciones WHERE curso_id = ?");
$asg->execute([$curso_id]);
$asigs = $asg->fetchAll();
$tipo = $asigs[0]['tipo_asignacion'] ?? 'todos';
$obligatorio = (int)($asigs[0]['obligatorio'] ?? 0);

try {
    $pdo->beginTransaction();

    $ins = $pdo->prepare("INSERT INTO cursos (nombre, descripcion, sucursal_id, vigencia_meses, activo) VALUES (?, ?, ?, ?, 1)");
    $ins->execute([$curso['nombre'], $curso['descripcion'] ?? null, $sucursal_destino, $curso['vigencia_meses']]);
    $nuevo = (int)$pdo->lastInsertId();

    // Alcance: solo se conserva lo que tiene sentido en otra sucursal.
    if ($tipo === 'departamento') {
        $deps = array_filter(array_column($asigs, 'entidad_id'), fn($v) => $v !== null);
        $q = $pdo->prepare("INSERT INTO curso_asignaciones (curso_id, tipo_asignacion, entidad_id, obligatorio) VALUES (?, 'departamento', ?, ?)");
        foreach ($deps as $d) { $q->execute([$nuevo, (int)$d, $obligatorio]); }
        if (empty($deps)) {
            $pdo->prepare("INSERT INTO curso_asignaciones (curso_id, tipo_asignacion, obligatorio) VALUES (?, 'todos', ?)")->execute([$nuevo, $obligatorio]);
        }
    } else {
        // todos / sucursal / empleado / excepto_empleado -> en la copia queda "todos" dentro de la sucursal destino
        $pdo->prepare("INSERT INTO curso_asignaciones (curso_id, tipo_asignacion, obligatorio) VALUES (?, 'todos', ?)")->execute([$nuevo, $obligatorio]);
    }

    registrar_auditoria($pdo, $usuario_id, 'INSERT', 'cursos', $nuevo,
        json_encode(['replicado_de' => $curso_id, 'sucursal_id' => $sucursal_destino], JSON_UNESCAPED_UNICODE));

    $pdo->commit();
    header('Location: editar.php?id=' . $nuevo . '&rep=ok');
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // Índice único (nombre, sucursal_id): ya existe ese curso en la sucursal destino
    if ($e->getCode() == 23000) { header('Location: listar.php?rep=dup'); exit; }
    header('Location: listar.php?rep=err');
    exit;
}
