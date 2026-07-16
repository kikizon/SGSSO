<?php
/**
 * ============================================================
 * SUPERMM SYSO · Motor de doble autorización (Opción A)
 * ------------------------------------------------------------
 * Flujo: un usuario (rol 'usuario' o 'supervisor') SOLICITA editar
 * o eliminar un registro. La solicitud queda 'pendiente' y SOLO un
 * 'admin' la aprueba o rechaza. Al aprobar, este motor ejecuta la
 * acción real (UPDATE/DELETE) usando un dispatcher por tabla.
 *
 * Requiere: includes/config.php (define $pdo) y includes/functions.php
 * (registrar_auditoria). Inclúyelo después de auth.php.
 * ============================================================
 */

require_once __DIR__ . '/functions.php';

/* ------------------------------------------------------------
 * REGLAS DE ROL
 * ---------------------------------------------------------- */

/** ¿Este rol debe pasar por autorización para editar/eliminar? */
function autz_requiere_autorizacion(string $rol): bool {
    return in_array($rol, ['usuario', 'supervisor'], true);
}

/** ¿Este rol puede aprobar/rechazar solicitudes? (solo admin) */
function autz_puede_autorizar(string $rol): bool {
    return $rol === 'admin';
}

/* ------------------------------------------------------------
 * CONFIGURACIÓN POR TABLA (whitelist)
 * ------------------------------------------------------------
 * Cada tabla habilitada para el flujo define:
 *  - 'label'       : nombre legible para la cola.
 *  - 'update_cols' : columnas permitidas al re-aplicar un UPDATE.
 *  - 'delete'      : (opcional) callable($pdo,$registro_id) para borrado
 *                    con lógica especial (archivos físicos, cascada manual).
 *                    Si no se define, se usa un DELETE genérico por id.
 *
 * Los módulos de batches posteriores (reportes, auditorías, catálogos)
 * agregan aquí su entrada al conectar sus botones de editar/eliminar.
 * ---------------------------------------------------------- */
function autz_config(): array {
    return [
        'reportes' => [
            'label'       => 'Reporte',
            'update_cols' => [
                'empleado_id','departamento_id','sucursal_id','fecha','hora',
                'acto_inseguro_id','accidente_id','gravedad','atencion_medica_id',
                'observacion','dias_perdidos','st7','costo_atencion','tipo',
            ],
            'delete'      => 'autz_delete_reporte',
        ],
        'auditorias_6s' => [
            'label'       => 'Auditoría 6S',
            'update_cols' => ['sucursal_id','departamento_id','fecha'],
            'delete'      => 'autz_delete_auditoria6s',
        ],
        'departamentos' => [
            'label'       => 'Departamento',
            'update_cols' => ['nombre','activo'],
            // borrado genérico por id (FK RESTRICT protege integridad)
        ],
    ];
}

function autz_tabla_permitida(string $tabla): bool {
    return array_key_exists($tabla, autz_config());
}

/* ------------------------------------------------------------
 * CONSULTAS DE ESTADO
 * ---------------------------------------------------------- */

/** ¿Hay una solicitud pendiente para este registro? (bloqueo) */
function autz_hay_pendiente(PDO $pdo, string $tabla, int $registro_id): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM solicitudes_autorizacion
         WHERE tabla = ? AND registro_id = ? AND estado = 'pendiente' LIMIT 1"
    );
    $st->execute([$tabla, $registro_id]);
    return (bool) $st->fetchColumn();
}

/** Devuelve la solicitud pendiente (o null). */
function autz_obtener_pendiente(PDO $pdo, string $tabla, int $registro_id): ?array {
    $st = $pdo->prepare(
        "SELECT * FROM solicitudes_autorizacion
         WHERE tabla = ? AND registro_id = ? AND estado = 'pendiente'
         ORDER BY id DESC LIMIT 1"
    );
    $st->execute([$tabla, $registro_id]);
    $row = $st->fetch();
    return $row ?: null;
}

/** Conteo de pendientes (para el badge del menú; admin ve todas). */
function autz_contar_pendientes(PDO $pdo): int {
    return (int) $pdo->query(
        "SELECT COUNT(*) FROM solicitudes_autorizacion WHERE estado = 'pendiente'"
    )->fetchColumn();
}

/* ------------------------------------------------------------
 * CREAR SOLICITUD
 * ---------------------------------------------------------- */

/**
 * Registra una solicitud de autorización. Evita duplicados pendientes
 * sobre el mismo registro. Devuelve el id de la solicitud o lanza excepción.
 */
function autz_crear_solicitud(
    PDO $pdo,
    int $solicitante_id,
    string $tabla,
    int $registro_id,
    string $accion,                 // 'UPDATE' | 'DELETE'
    ?array $payload = null,
    ?string $descripcion = null,
    ?int $sucursal_id = null
): int {
    $accion = strtoupper($accion);
    if (!in_array($accion, ['UPDATE','DELETE'], true)) {
        throw new InvalidArgumentException('Acción no válida.');
    }
    if (!autz_tabla_permitida($tabla)) {
        throw new InvalidArgumentException('Tabla no habilitada para autorización.');
    }
    if (autz_hay_pendiente($pdo, $tabla, $registro_id)) {
        throw new RuntimeException('Ya existe una solicitud pendiente para este registro.');
    }

    $st = $pdo->prepare(
        "INSERT INTO solicitudes_autorizacion
            (tabla, registro_id, accion, payload, descripcion, solicitante_id, sucursal_id, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente')"
    );
    $st->execute([
        $tabla,
        $registro_id,
        $accion,
        $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        $descripcion,
        $solicitante_id,
        $sucursal_id,
    ]);
    $id = (int) $pdo->lastInsertId();

    registrar_auditoria($pdo, $solicitante_id, 'SOLICITUD', 'solicitudes_autorizacion', $id, json_encode([
        'tabla' => $tabla, 'registro_id' => $registro_id, 'accion' => $accion,
    ], JSON_UNESCAPED_UNICODE));

    return $id;
}

/* ------------------------------------------------------------
 * APROBAR / RECHAZAR
 * ---------------------------------------------------------- */

/**
 * Aprueba una solicitud y ejecuta la acción real dentro de una transacción.
 * Devuelve true si todo salió bien.
 */
function autz_aprobar(PDO $pdo, int $solicitud_id, int $aprobador_id): bool {
    $st = $pdo->prepare("SELECT * FROM solicitudes_autorizacion WHERE id = ? FOR UPDATE");
    $propia = $pdo->inTransaction();
    if (!$propia) { $pdo->beginTransaction(); }

    try {
        $st->execute([$solicitud_id]);
        $sol = $st->fetch();
        if (!$sol || $sol['estado'] !== 'pendiente') {
            if (!$propia) { $pdo->rollBack(); }
            return false;
        }

        autz_ejecutar_accion($pdo, $sol);

        $up = $pdo->prepare(
            "UPDATE solicitudes_autorizacion
             SET estado = 'aprobada', aprobador_id = ?, resuelto_en = NOW()
             WHERE id = ?"
        );
        $up->execute([$aprobador_id, $solicitud_id]);

        registrar_auditoria($pdo, $aprobador_id, 'APROBACION', 'solicitudes_autorizacion', $solicitud_id, json_encode([
            'tabla' => $sol['tabla'], 'registro_id' => $sol['registro_id'], 'accion' => $sol['accion'],
        ], JSON_UNESCAPED_UNICODE));

        if (!$propia) { $pdo->commit(); }
        return true;
    } catch (Throwable $e) {
        if (!$propia && $pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

/** Rechaza una solicitud (el registro queda intacto). */
function autz_rechazar(PDO $pdo, int $solicitud_id, int $aprobador_id, string $motivo): bool {
    $st = $pdo->prepare("SELECT estado FROM solicitudes_autorizacion WHERE id = ?");
    $st->execute([$solicitud_id]);
    if ($st->fetchColumn() !== 'pendiente') { return false; }

    $up = $pdo->prepare(
        "UPDATE solicitudes_autorizacion
         SET estado = 'rechazada', aprobador_id = ?, motivo = ?, resuelto_en = NOW()
         WHERE id = ?"
    );
    $up->execute([$aprobador_id, $motivo, $solicitud_id]);

    registrar_auditoria($pdo, $aprobador_id, 'RECHAZO', 'solicitudes_autorizacion', $solicitud_id, json_encode([
        'motivo' => $motivo,
    ], JSON_UNESCAPED_UNICODE));
    return true;
}

/* ------------------------------------------------------------
 * DISPATCHER: ejecuta la acción real al aprobar
 * ---------------------------------------------------------- */

function autz_ejecutar_accion(PDO $pdo, array $sol): void {
    $cfg = autz_config();
    $tabla = $sol['tabla'];
    if (!isset($cfg[$tabla])) {
        throw new RuntimeException("Tabla '$tabla' no habilitada.");
    }
    $rid = (int) $sol['registro_id'];

    if ($sol['accion'] === 'DELETE') {
        if (!empty($cfg[$tabla]['delete']) && is_callable($cfg[$tabla]['delete'])) {
            call_user_func($cfg[$tabla]['delete'], $pdo, $rid);
        } else {
            // Borrado genérico por id (las FK del esquema protegen la integridad)
            $pdo->prepare("DELETE FROM `$tabla` WHERE id = ?")->execute([$rid]);
        }
        return;
    }

    // UPDATE: re-aplicar solo columnas permitidas (whitelist)
    $payload = $sol['payload'] ? json_decode($sol['payload'], true) : [];
    $permitidas = $cfg[$tabla]['update_cols'] ?? [];
    $set = []; $vals = [];
    foreach ($payload as $col => $val) {
        if (in_array($col, $permitidas, true)) {
            $set[] = "`$col` = ?";
            $vals[] = $val;
        }
    }
    if (!$set) { return; } // nada que aplicar
    $vals[] = $rid;
    $sql = "UPDATE `$tabla` SET " . implode(', ', $set) . " WHERE id = ?";
    $pdo->prepare($sql)->execute($vals);
}

/* ------------------------------------------------------------
 * HANDLERS DE BORRADO ESPECÍFICOS (con archivos físicos)
 * ---------------------------------------------------------- */

/** Borra un reporte y sus evidencias (archivos + filas). */
function autz_delete_reporte(PDO $pdo, int $id): void {
    $st = $pdo->prepare("SELECT nombre_archivo FROM reportes_evidencias WHERE reporte_id = ?");
    $st->execute([$id]);
    foreach ($st->fetchAll() as $row) {
        $ruta = UPLOAD_DIR . $row['nombre_archivo'];
        if (is_file($ruta)) { @unlink($ruta); }
    }
    $pdo->prepare("DELETE FROM reportes_evidencias WHERE reporte_id = ?")->execute([$id]);

    // Documentos firmados (archivos físicos; las filas caen por ON DELETE CASCADE)
    $stf = $pdo->prepare("SELECT nombre_archivo FROM reportes_firmados WHERE reporte_id = ?");
    $stf->execute([$id]);
    foreach ($stf->fetchAll() as $rowf) {
        $rutaf = UPLOAD_DIR . $rowf['nombre_archivo'];
        if (is_file($rutaf)) { @unlink($rutaf); }
    }

    $pdo->prepare("DELETE FROM reportes WHERE id = ?")->execute([$id]);
}

/** Borra una auditoría 6S y evidencias (respuestas/evidencias caen por CASCADE). */
function autz_delete_auditoria6s(PDO $pdo, int $id): void {
    $ev = $pdo->prepare(
        "SELECT e.nombre_archivo
         FROM auditorias_6s_evidencias e
         JOIN auditorias_6s_respuestas r ON r.id = e.respuesta_id
         WHERE r.auditoria_id = ?"
    );
    $ev->execute([$id]);
    foreach ($ev->fetchAll() as $f) {
        $ruta = UPLOAD_DIR . $f['nombre_archivo'];
        if (is_file($ruta)) { @unlink($ruta); }
    }
    $pdo->prepare("DELETE FROM auditorias_6s WHERE id = ?")->execute([$id]);
}
