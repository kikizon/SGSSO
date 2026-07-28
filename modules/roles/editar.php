<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
exigir('roles.gestionar');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
$st->execute([$id]);
$rol = $st->fetch();
if (!$rol) { header('Location: listar.php?err=' . urlencode('El rol no existe.')); exit; }
if ((int)$rol['es_sistema'] === 1) {
    header('Location: listar.php?err=' . urlencode('El rol de sistema no se puede editar: siempre tiene todos los permisos.'));
    exit;
}

$error = '';
$q = $pdo->prepare("SELECT permiso_clave FROM rol_permisos WHERE rol_id = ?");
$q->execute([$id]);
$seleccionados = $q->fetchAll(PDO::FETCH_COLUMN);

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
                $pdo->prepare("UPDATE roles SET nombre = ?, descripcion = ?, ve_todas_sucursales = ?, activo = ? WHERE id = ?")
                    ->execute([$rol['nombre'], $rol['descripcion'], $rol['ve_todas_sucursales'], $rol['activo'], $id]);
                $pdo->prepare("DELETE FROM rol_permisos WHERE rol_id = ?")->execute([$id]);
                $ins = $pdo->prepare("INSERT IGNORE INTO rol_permisos (rol_id, permiso_clave) VALUES (?, ?)");
                foreach ($seleccionados as $c) { $ins->execute([$id, $c]); }
                registrar_auditoria($pdo, $usuario_id, 'UPDATE', 'roles', $id,
                    json_encode(['nombre' => $rol['nombre'], 'permisos' => count($seleccionados)], JSON_UNESCAPED_UNICODE));
                $pdo->commit();
                header('Location: listar.php?msg=' . urlencode('Rol actualizado (' . count($seleccionados) . ' permisos).'));
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                if ($e->errorInfo[1] == 1062) { $error = 'Ya existe un rol con ese nombre.'; }
                else { error_log('roles/editar: ' . $e->getMessage()); $error = 'No se pudo actualizar el rol.'; }
            }
        }
    }
}

$titulo = 'Editar rol: ' . $rol['nombre'];
$accion = 'Guardar cambios';
include '../../includes/header.php';
include '_form.php';
include '../../includes/footer.php';
