<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
exigir('roles.gestionar');

$error = '';
$rol = ['nombre' => '', 'descripcion' => '', 'activo' => 1, 've_todas_sucursales' => 0];
$seleccionados = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.';
    } else {
        $rol['nombre']      = trim($_POST['nombre'] ?? '');
        $rol['descripcion'] = trim($_POST['descripcion'] ?? '');
        $rol['activo']      = isset($_POST['activo']) ? 1 : 0;
        $rol['ve_todas_sucursales'] = isset($_POST['ve_todas_sucursales']) ? 1 : 0;
        $validos = permisos_todos();
        $seleccionados = array_values(array_intersect((array)($_POST['permisos'] ?? []), $validos));

        if ($rol['nombre'] === '') {
            $error = 'El nombre del rol es obligatorio.';
        } else {
            try {
                $pdo->beginTransaction();
                $st = $pdo->prepare("INSERT INTO roles (nombre, descripcion, es_sistema, ve_todas_sucursales, activo) VALUES (?, ?, 0, ?, ?)");
                $st->execute([$rol['nombre'], $rol['descripcion'], $rol['ve_todas_sucursales'], $rol['activo']]);
                $nuevo = (int)$pdo->lastInsertId();
                $ins = $pdo->prepare("INSERT IGNORE INTO rol_permisos (rol_id, permiso_clave) VALUES (?, ?)");
                foreach ($seleccionados as $c) { $ins->execute([$nuevo, $c]); }
                registrar_auditoria($pdo, $usuario_id, 'INSERT', 'roles', $nuevo,
                    json_encode(['nombre' => $rol['nombre'], 'permisos' => count($seleccionados)], JSON_UNESCAPED_UNICODE));
                $pdo->commit();
                header('Location: listar.php?msg=' . urlencode('Rol creado con ' . count($seleccionados) . ' permiso(s).'));
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                if ($e->errorInfo[1] == 1062) { $error = 'Ya existe un rol con ese nombre.'; }
                else { error_log('roles/crear: ' . $e->getMessage()); $error = 'No se pudo crear el rol.'; }
            }
        }
    }
}

$titulo = 'Nuevo rol';
$accion = 'Crear rol';
include '../../includes/header.php';
include '_form.php';
include '../../includes/footer.php';
