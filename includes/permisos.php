<?php
/**
 * Motor de permisos granulares — SUPERMM SYSO
 *
 * FASE 1 (modo compatibilidad):
 *   La fuente de verdad es el MAPA de abajo, que replica EXACTAMENTE el
 *   comportamiento de los roles actuales (admin / supervisor / usuario).
 *   Las tablas de BD ya existen y están sembradas, pero todavía no se leen.
 *
 * FASE 4 (cambio de fuente):
 *   Cambiar PERMISOS_FUENTE a 'bd'. Si algo falla, se regresa a 'mapa'
 *   sin tocar más código.
 *
 * USO:
 *   exigir('cursos.crear');            // corta la ejecución si no tiene el permiso
 *   if (puede('reportes.eliminar')) {  // para mostrar/ocultar botones
 *   if (puede_alguno(['a','b'])) {
 */

if (!defined('PERMISOS_FUENTE')) {
    define('PERMISOS_FUENTE', 'mapa');   // 'mapa' (Fases 1-3)  |  'bd' (Fase 4+)
}

/* ------------------------------------------------------------------
 * CATÁLOGO
 * Cada permiso: 'label' (para la UI) y 'roles' (quiénes lo tienen HOY,
 * además de admin, que siempre los tiene todos).
 * ------------------------------------------------------------------ */
function permisos_catalogo(): array {
    $TS = ['supervisor', 'usuario'];   // todos los no-admin
    $S  = ['supervisor'];              // solo supervisor
    $A  = [];                          // solo admin

    return [
        'Tablero' => [
            'dashboard.ver'            => ['label' => 'Entrar al tablero',        'roles' => $TS],
            'dashboard.ver_seguridad'  => ['label' => 'Ver pestaña Seguridad',    'roles' => $TS],
            'dashboard.ver_salud'      => ['label' => 'Ver pestaña Salud',        'roles' => $TS],
            'dashboard.ver_cursos'     => ['label' => 'Ver pestaña Cursos',       'roles' => $TS],
            'dashboard.exportar'       => ['label' => 'Exportar tablero',         'roles' => $TS],
        ],
        'Reportes' => [
            'reportes.ver'                 => ['label' => 'Ver reportes',                    'roles' => $TS],
            'reportes.crear'               => ['label' => 'Crear reportes',                  'roles' => $TS],
            'reportes.editar'              => ['label' => 'Editar reportes',                 'roles' => $TS],
            'reportes.eliminar'            => ['label' => 'Eliminar reportes',               'roles' => $TS],
            'reportes.editar.directo'      => ['label' => 'Editar SIN autorización',         'roles' => $A],
            'reportes.eliminar.directo'    => ['label' => 'Eliminar SIN autorización',       'roles' => $A],
            'reportes.evidencias.subir'    => ['label' => 'Adjuntar evidencias',             'roles' => $TS],
            'reportes.evidencias.eliminar' => ['label' => 'Eliminar evidencias',             'roles' => $TS],
            'reportes.firmados.subir'      => ['label' => 'Subir documento firmado',         'roles' => $TS],
            'reportes.firmados.eliminar'   => ['label' => 'Eliminar documento firmado',      'roles' => $TS],
            'reportes.pdf'                 => ['label' => 'Generar PDF del reporte',         'roles' => $TS],
            'reportes.exportar'            => ['label' => 'Exportar listado de reportes',    'roles' => $TS],
        ],
        'Incapacidades' => [
            'incapacidades.ver'            => ['label' => 'Ver incapacidades',        'roles' => $S],
            'incapacidades.tramo.agregar'  => ['label' => 'Agregar tramo',            'roles' => $S],
            'incapacidades.tramo.eliminar' => ['label' => 'Eliminar tramo',           'roles' => $S],
            'incapacidades.cerrar'         => ['label' => 'Cerrar seguimiento',       'roles' => $S],
            'incapacidades.reabrir'        => ['label' => 'Reabrir seguimiento',      'roles' => $S],
        ],
        'Auditorías 6S' => [
            '6s.ver'                    => ['label' => 'Ver auditorías',              'roles' => $TS],
            '6s.realizar'               => ['label' => 'Realizar auditoría',          'roles' => $TS],
            '6s.editar'                 => ['label' => 'Editar auditoría guardada',   'roles' => $TS],
            '6s.eliminar'               => ['label' => 'Eliminar auditoría',          'roles' => $S],
            '6s.eliminar.directo'       => ['label' => 'Eliminar SIN autorización',   'roles' => $A],
            '6s.imprimir'               => ['label' => 'Imprimir / PDF',              'roles' => $TS],
            '6s.tendencias'             => ['label' => 'Ver tendencias',              'roles' => $TS],
            '6s.resumen'                => ['label' => 'Ver hoja resumen',            'roles' => $TS],
            '6s.criterios.gestionar'    => ['label' => 'Gestionar criterios 6S',      'roles' => $A],
            '6s.responsables.gestionar' => ['label' => 'Gestionar responsables 6S',   'roles' => $A],
        ],
        'Empleados' => [
            'empleados.ver'                  => ['label' => 'Ver empleados',            'roles' => $A],
            'empleados.crear'                => ['label' => 'Crear empleado',           'roles' => $S],
            'empleados.editar'               => ['label' => 'Editar empleado',          'roles' => $S],
            'empleados.eliminar'             => ['label' => 'Eliminar empleado',        'roles' => $A],
            'empleados.importar'             => ['label' => 'Importar empleados (CSV)', 'roles' => $A],
            'empleados.fotos_masivas'        => ['label' => 'Carga masiva de fotos',    'roles' => $A],
            'empleados.historial.ver'        => ['label' => 'Ver expediente',           'roles' => $TS],
            'empleados.historial.exportar'   => ['label' => 'Exportar expediente',      'roles' => $TS],
            'empleados.notas.ver'            => ['label' => 'Ver notas',                'roles' => $TS],
            'empleados.notas.agregar'        => ['label' => 'Agregar nota',             'roles' => $S],
            'empleados.notas.eliminar'       => ['label' => 'Eliminar nota',            'roles' => $S],
            'empleados.alergias.asignar'     => ['label' => 'Asignar alergias',         'roles' => $TS],
            'empleados.alergias.editar'      => ['label' => 'Editar obs. de alergias',  'roles' => $TS],
            'empleados.enfermedades.asignar' => ['label' => 'Asignar enfermedades',     'roles' => $TS],
            'empleados.enfermedades.editar'  => ['label' => 'Editar obs. de enferm.',   'roles' => $TS],
            'empleados.cursos.marcar'        => ['label' => 'Marcar curso como tomado', 'roles' => $TS],
        ],
        'Cursos' => [
            'cursos.ver'                => ['label' => 'Ver catálogo de cursos',   'roles' => $A],
            'cursos.crear'              => ['label' => 'Crear curso',              'roles' => $A],
            'cursos.editar'             => ['label' => 'Editar curso',             'roles' => $A],
            'cursos.eliminar'           => ['label' => 'Eliminar curso',           'roles' => $A],
            'cursos.importar'           => ['label' => 'Importar cursos (CSV)',    'roles' => $A],
            'cursos.replicar'           => ['label' => 'Replicar en otra sucursal','roles' => $A],
            'cursos.cobertura.ver'      => ['label' => 'Ver cobertura',            'roles' => $A],
            'cursos.cobertura.exportar' => ['label' => 'Exportar cobertura',       'roles' => $A],
        ],
        'Catálogo: Sucursales' => [
            'sucursales.ver'      => ['label' => 'Ver sucursales',    'roles' => $A],
            'sucursales.crear'    => ['label' => 'Crear sucursal',    'roles' => $A],
            'sucursales.editar'   => ['label' => 'Editar sucursal',   'roles' => $A],
            'sucursales.eliminar' => ['label' => 'Eliminar sucursal', 'roles' => $A],
        ],
        'Catálogo: Departamentos' => [
            'departamentos.ver'             => ['label' => 'Ver departamentos',       'roles' => $A],
            'departamentos.crear'           => ['label' => 'Crear departamento',      'roles' => $A],
            'departamentos.editar'          => ['label' => 'Editar departamento',     'roles' => $A],
            'departamentos.eliminar'        => ['label' => 'Eliminar departamento',   'roles' => $A],
            'departamentos.eliminar_bloque' => ['label' => 'Eliminar en bloque',      'roles' => $A],
        ],
        'Catálogo: Actos inseguros' => [
            'actos.ver'      => ['label' => 'Ver actos inseguros',    'roles' => $A],
            'actos.crear'    => ['label' => 'Crear acto inseguro',    'roles' => $A],
            'actos.editar'   => ['label' => 'Editar acto inseguro',   'roles' => $A],
            'actos.eliminar' => ['label' => 'Eliminar acto inseguro', 'roles' => $A],
        ],
        'Catálogo: Tipos de accidente' => [
            'tipos_accidente.ver'      => ['label' => 'Ver tipos de accidente',    'roles' => $A],
            'tipos_accidente.crear'    => ['label' => 'Crear tipo de accidente',   'roles' => $A],
            'tipos_accidente.editar'   => ['label' => 'Editar tipo de accidente',  'roles' => $A],
            'tipos_accidente.eliminar' => ['label' => 'Eliminar tipo de accidente','roles' => $A],
        ],
        'Catálogo: Atenciones médicas' => [
            'atenciones_medicas.ver'      => ['label' => 'Ver atenciones médicas',    'roles' => $A],
            'atenciones_medicas.crear'    => ['label' => 'Crear atención médica',     'roles' => $A],
            'atenciones_medicas.editar'   => ['label' => 'Editar atención médica',    'roles' => $A],
            'atenciones_medicas.eliminar' => ['label' => 'Eliminar atención médica',  'roles' => $A],
        ],
        'Catálogo: Enfermedades crónicas' => [
            'enfermedades.ver'      => ['label' => 'Ver enfermedades',      'roles' => $A],
            'enfermedades.crear'    => ['label' => 'Crear enfermedad',      'roles' => $A],
            'enfermedades.editar'   => ['label' => 'Editar enfermedad',     'roles' => $A],
            'enfermedades.eliminar' => ['label' => 'Eliminar enfermedad',   'roles' => $A],
            'enfermedades.importar' => ['label' => 'Importar enfermedades', 'roles' => $A],
        ],
        'Catálogo: Alergias' => [
            'alergias.ver'      => ['label' => 'Ver alergias',      'roles' => $A],
            'alergias.crear'    => ['label' => 'Crear alergia',     'roles' => $A],
            'alergias.editar'   => ['label' => 'Editar alergia',    'roles' => $A],
            'alergias.eliminar' => ['label' => 'Eliminar alergia',  'roles' => $A],
            'alergias.importar' => ['label' => 'Importar alergias', 'roles' => $A],
        ],
        'Administración' => [
            'usuarios.ver'                  => ['label' => 'Ver usuarios',                  'roles' => $A],
            'usuarios.crear'                => ['label' => 'Crear usuario',                 'roles' => $A],
            'usuarios.editar'               => ['label' => 'Editar usuario',                'roles' => $A],
            'usuarios.eliminar'             => ['label' => 'Eliminar usuario',              'roles' => $A],
            'usuarios.permisos_individuales'=> ['label' => 'Dar permisos individuales',     'roles' => $A],
            'roles.gestionar'               => ['label' => 'Gestionar roles y permisos',    'roles' => $A],
            'configuracion.ver'             => ['label' => 'Ver configuración',             'roles' => $A],
            'configuracion.editar'          => ['label' => 'Editar configuración',          'roles' => $A],
            'bitacora.ver'                  => ['label' => 'Ver bitácora de auditoría',     'roles' => $A],
        ],
        'Autorizaciones y Papelera' => [
            'autorizaciones.ver'     => ['label' => 'Ver solicitudes (propias)',        'roles' => $TS],
            'autorizaciones.aprobar' => ['label' => 'Aprobar / rechazar',     'roles' => $A],
            'papelera.ver'           => ['label' => 'Ver papelera',           'roles' => $A],
            'papelera.restaurar'     => ['label' => 'Restaurar registros',    'roles' => $A],
            'papelera.purgar'        => ['label' => 'Eliminar definitivo',    'roles' => $A],
        ],
    ];
}

/** Lista plana de todas las claves de permiso. */
function permisos_todos(): array {
    $out = [];
    foreach (permisos_catalogo() as $grupo => $perms) {
        foreach ($perms as $clave => $_) { $out[] = $clave; }
    }
    return $out;
}

/** Permisos que corresponden a un rol según el MAPA (comportamiento actual). */
function permisos_de_rol(string $rol): array {
    if ($rol === 'admin') { return permisos_todos(); }
    $out = [];
    foreach (permisos_catalogo() as $grupo => $perms) {
        foreach ($perms as $clave => $def) {
            if (in_array($rol, $def['roles'], true)) { $out[] = $clave; }
        }
    }
    return $out;
}

/** Permisos leídos de la BD (rol + excepciones individuales). Se usa en Fase 4. */
function permisos_de_bd(PDO $pdo, int $usuario_id): array {
    $out = [];
    try {
        // ¿El rol es de sistema (superadministrador)? -> todos
        $q = $pdo->prepare("SELECT r.id, r.es_sistema FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = ?");
        $q->execute([$usuario_id]);
        $rol = $q->fetch();
        if ($rol && (int)$rol['es_sistema'] === 1) { return permisos_todos(); }

        if ($rol) {
            $q = $pdo->prepare("SELECT permiso_clave FROM rol_permisos WHERE rol_id = ?");
            $q->execute([(int)$rol['id']]);
            $out = $q->fetchAll(PDO::FETCH_COLUMN);
        }
        // Excepciones individuales: conceder (1) o revocar (0)
        $q = $pdo->prepare("SELECT permiso_clave, concedido FROM usuario_permisos WHERE usuario_id = ?");
        $q->execute([$usuario_id]);
        foreach ($q->fetchAll() as $r) {
            if ((int)$r['concedido'] === 1) { $out[] = $r['permiso_clave']; }
            else { $out = array_diff($out, [$r['permiso_clave']]); }
        }
    } catch (Throwable $e) {
        error_log('permisos_de_bd: ' . $e->getMessage());
        return [];
    }
    return array_values(array_unique($out));
}

/** Permisos de un rol leídos de la BD, por nombre de rol. Solo para verificación. */
function permisos_de_rol_bd(PDO $pdo, string $nombreRol): array {
    try {
        $q = $pdo->prepare("SELECT id, es_sistema FROM roles WHERE nombre = ?");
        $q->execute([$nombreRol]);
        $r = $q->fetch();
        if (!$r) { return []; }
        if ((int)$r['es_sistema'] === 1) { return permisos_todos(); }
        $q = $pdo->prepare("SELECT permiso_clave FROM rol_permisos WHERE rol_id = ?");
        $q->execute([(int)$r['id']]);
        return $q->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        error_log('permisos_de_rol_bd: ' . $e->getMessage());
        return [];
    }
}

/** ¿El usuario tiene privilegio total (rol de sistema)? */
function permisos_es_superadmin(PDO $pdo, int $usuario_id, string $rol): bool {
    if (PERMISOS_FUENTE !== 'bd') { return $rol === 'admin'; }
    try {
        $q = $pdo->prepare("SELECT r.es_sistema FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = ?");
        $q->execute([$usuario_id]);
        $v = $q->fetchColumn();
        if ($v === false) { return $rol === 'admin'; }   // sin rol_id: se respeta el rol viejo
        return (int)$v === 1;
    } catch (Throwable $e) {
        error_log('permisos_es_superadmin: ' . $e->getMessage());
        return $rol === 'admin';
    }
}

/** Carga los permisos del usuario en sesión. La llama auth.php. */
function permisos_cargar(PDO $pdo, int $usuario_id, string $rol): array {
    $GLOBALS['PERMISOS_SUPERADMIN'] = permisos_es_superadmin($pdo, $usuario_id, $rol);
    if (PERMISOS_FUENTE === 'bd') {
        $p = permisos_de_bd($pdo, $usuario_id);
        // Red de seguridad: si la BD no devuelve nada, se cae al mapa.
        if (empty($p)) {
            error_log("permisos: usuario $usuario_id sin permisos en BD; usando mapa por rol.");
            $p = permisos_de_rol($rol);
        }
        return $p;
    }
    return permisos_de_rol($rol);
}

/** ¿El usuario en sesión tiene este permiso? */
function puede(string $clave): bool {
    global $PERMISOS_USUARIO, $usuario_rol;
    // Privilegio total: rol marcado es_sistema (o rol 'admin' mientras la fuente sea el mapa)
    if (!empty($GLOBALS['PERMISOS_SUPERADMIN'])) { return true; }
    if (PERMISOS_FUENTE !== 'bd' && ($usuario_rol ?? '') === 'admin') { return true; }
    return in_array($clave, $PERMISOS_USUARIO ?? [], true);
}

/** ¿Tiene al menos uno de estos permisos? (útil para menús) */
function puede_alguno(array $claves): bool {
    foreach ($claves as $c) { if (puede($c)) { return true; } }
    return false;
}

/**
 * Puerta: corta la ejecución si no tiene el permiso.
 * En peticiones AJAX responde 403 JSON; en pantallas redirige al tablero.
 * Si el endpoint devuelve JSON, llame a exigir() DESPUÉS de fijar el
 * Content-Type, o pase $forzar_json = true.
 */
function exigir(string $clave, ?string $redirect = null, bool $forzar_json = false): void {
    if (puede($clave)) { return; }

    // ¿La respuesta es para JavaScript? Se detecta por cabecera de la petición,
    // por el Accept, o porque el script ya declaró que devuelve JSON.
    $esAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || $forzar_json;
    if (!$esAjax) {
        foreach (headers_list() as $h) {
            if (stripos($h, 'content-type:') === 0 && stripos($h, 'json') !== false) { $esAjax = true; break; }
        }
    }

    error_log('permiso denegado: ' . $clave . ' (usuario ' . ($_SESSION['usuario_id'] ?? '?') . ')');

    if ($esAjax) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No tiene permiso para realizar esta acción.']);
        exit;
    }
    $destino = $redirect ?: (BASE_URL . 'modules/dashboard/?err=' . urlencode('No tiene permiso para acceder a esa sección.'));
    header('Location: ' . $destino);
    exit;
}
