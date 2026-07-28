<?php
/**
 * Motor de Papelera (borrado lógico) para SUPERMM SYSO.
 *
 * Uso en un eliminar.php, ANTES de borrar el registro de su tabla:
 *   require_once __DIR__ . '/../../includes/papelera.php';
 *   papelera_snapshot($pdo, 'reportes', $id, $usuario_id);
 *   // ...luego tu DELETE de siempre (NO borres los archivos; los conserva la papelera)...
 *
 * Restaurar / purgar se hacen desde modules/papelera/.
 */

if (!defined('UPLOAD_DIR')) { require_once __DIR__ . '/config.php'; }

/**
 * Catálogo de entidades: define hijos (por FK, anidables) y columnas de archivos.
 * 'archivos' = columnas cuyo valor es un nombre de archivo en UPLOAD_DIR.
 */
function papelera_registry(): array {
    return [
        'reportes' => [
            'label' => 'Reporte', 'desc_col' => 'fecha',
            'hijos' => [
                'reportes_evidencias' => ['fk' => 'reporte_id', 'archivos' => ['nombre_archivo']],
                'reportes_firmados'   => ['fk' => 'reporte_id', 'archivos' => ['nombre_archivo']],
                'incapacidad_tramos'  => ['fk' => 'reporte_id', 'archivos' => ['nombre_archivo']],
            ],
        ],
        'auditorias_6s' => [
            'label' => 'Auditoría 6S', 'desc_col' => 'fecha',
            'hijos' => [
                'auditorias_6s_respuestas' => ['fk' => 'auditoria_id', 'hijos' => [
                    'auditorias_6s_evidencias' => ['fk' => 'respuesta_id', 'archivos' => ['nombre_archivo']],
                ]],
                'auditorias_6s_departamentos' => ['fk' => 'auditoria_id'],
                'auditorias_6s_firmantes'     => ['fk' => 'auditoria_id'],
            ],
        ],
        'empleados' => [
            'label' => 'Empleado', 'desc_col' => 'nombre', 'archivos' => ['foto'],
            'hijos' => [
                'empleado_curso' => ['fk' => 'empleado_id'],
                'empleado_notas' => ['fk' => 'empleado_id'],
            ],
        ],
        'cursos' => [
            'label' => 'Curso/Formato', 'desc_col' => 'nombre',
            'hijos' => [
                'curso_asignaciones' => ['fk' => 'curso_id'],
                'empleado_curso'     => ['fk' => 'curso_id'],
            ],
        ],
        'usuarios' => [
            'label' => 'Usuario', 'desc_col' => 'nombre_completo',
            'hijos' => ['usuario_sucursales' => ['fk' => 'usuario_id']],
        ],
        'sucursales'            => ['label' => 'Sucursal', 'desc_col' => 'nombre'],
        'departamentos'         => ['label' => 'Departamento', 'desc_col' => 'nombre'],
        'actos_inseguros'       => ['label' => 'Acto inseguro', 'desc_col' => 'descripcion'],
        'tipos_accidente'       => ['label' => 'Tipo de accidente', 'desc_col' => 'descripcion'],
        'alergias'              => ['label' => 'Alergia', 'desc_col' => 'nombre'],
        'enfermedades_cronicas' => ['label' => 'Enfermedad crónica', 'desc_col' => 'nombre'],
        'atenciones_medicas'    => ['label' => 'Atención médica', 'desc_col' => 'descripcion'],
    ];
}

/** Recolecta filas hijas (recursivo) de una entidad. */
function _papelera_snap_hijos(PDO $pdo, array $hijosSpec, int $parentId): array {
    $out = [];
    foreach ($hijosSpec as $tabla => $spec) {
        $fk = $spec['fk'];
        $st = $pdo->prepare("SELECT * FROM `$tabla` WHERE `$fk` = ?");
        $st->execute([$parentId]);
        $rows = $st->fetchAll();
        $items = [];
        foreach ($rows as $r) {
            $entry = ['row' => $r];
            if (!empty($spec['hijos']) && isset($r['id'])) {
                $entry['hijos'] = _papelera_snap_hijos($pdo, $spec['hijos'], (int)$r['id']);
            }
            $items[] = $entry;
        }
        $out[$tabla] = ['fk' => $fk, 'items' => $items];
    }
    return $out;
}

/**
 * Guarda un snapshot del registro (y sus hijos) en la papelera.
 * Devuelve el id de papelera, o null si la tabla no está registrada o no existe el registro.
 */
function papelera_snapshot(PDO $pdo, string $tabla, int $id, ?int $usuario_id): ?int {
    $reg = papelera_registry()[$tabla] ?? null;
    if (!$reg) return null;
    $st = $pdo->prepare("SELECT * FROM `$tabla` WHERE `id` = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) return null;

    $snap = [
        'tabla'    => $tabla,
        'registro' => $row,
        'hijos'    => !empty($reg['hijos']) ? _papelera_snap_hijos($pdo, $reg['hijos'], $id) : [],
    ];
    $desc = ($reg['label'] ?? $tabla) . ' #' . $id;
    if (!empty($reg['desc_col']) && isset($row[$reg['desc_col']]) && $row[$reg['desc_col']] !== '') {
        $desc .= ' · ' . mb_substr((string)$row[$reg['desc_col']], 0, 120);
    }

    $ins = $pdo->prepare("INSERT INTO papelera (tabla, registro_id, descripcion, datos_json, eliminado_por)
                          VALUES (?, ?, ?, ?, ?)");
    $ins->execute([$tabla, $id, $desc, json_encode($snap, JSON_UNESCAPED_UNICODE), $usuario_id]);
    return (int)$pdo->lastInsertId();
}

/** Inserta una fila asociativa en una tabla (conserva el id original). */
function _papelera_insert_row(PDO $pdo, string $tabla, array $row): void {
    if (!$row) return;
    $cols = array_keys($row);
    $ph   = implode(', ', array_fill(0, count($cols), '?'));
    $colsSql = '`' . implode('`, `', $cols) . '`';
    $st = $pdo->prepare("INSERT INTO `$tabla` ($colsSql) VALUES ($ph)");
    $st->execute(array_values($row));
}

/** Reinserta hijos (recursivo). */
function _papelera_restore_hijos(PDO $pdo, array $hijos): void {
    foreach ($hijos as $tabla => $data) {
        foreach ($data['items'] as $entry) {
            _papelera_insert_row($pdo, $tabla, $entry['row']);
            if (!empty($entry['hijos'])) {
                _papelera_restore_hijos($pdo, $entry['hijos']);
            }
        }
    }
}

/**
 * Restaura un elemento de la papelera (registro + hijos, con sus IDs originales).
 * Devuelve [true, ''] o [false, 'motivo'].
 */
function papelera_restaurar(PDO $pdo, int $papelera_id, ?int $usuario_id): array {
    $st = $pdo->prepare("SELECT * FROM papelera WHERE id = ? AND restaurado_en IS NULL");
    $st->execute([$papelera_id]);
    $p = $st->fetch();
    if (!$p) return [false, 'El elemento no existe o ya fue restaurado.'];

    $snap = json_decode($p['datos_json'], true);
    if (!$snap || empty($snap['registro'])) return [false, 'Snapshot inválido.'];

    try {
        $pdo->beginTransaction();
        _papelera_insert_row($pdo, $snap['tabla'], $snap['registro']);
        if (!empty($snap['hijos'])) { _papelera_restore_hijos($pdo, $snap['hijos']); }
        $pdo->prepare("UPDATE papelera SET restaurado_en = NOW(), restaurado_por = ? WHERE id = ?")
            ->execute([$usuario_id, $papelera_id]);
        $pdo->commit();
        return [true, ''];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('papelera_restaurar: ' . $e->getMessage());
        return [false, 'No se pudo restaurar (posible conflicto de ID o dependencia faltante).'];
    }
}

/** Recorre el snapshot y ejecuta un callback con cada nombre de archivo. */
function _papelera_walk_files(array $snap, callable $cb): void {
    $reg = papelera_registry()[$snap['tabla']] ?? [];
    // archivos del registro principal
    foreach (($reg['archivos'] ?? []) as $col) {
        if (!empty($snap['registro'][$col])) $cb($snap['registro'][$col]);
    }
    // archivos de los hijos (recursivo por spec)
    $walkHijos = function ($hijos, $spec) use (&$walkHijos, $cb) {
        foreach ($hijos as $tabla => $data) {
            $archCols = $spec[$tabla]['archivos'] ?? [];
            foreach ($data['items'] as $entry) {
                foreach ($archCols as $col) {
                    if (!empty($entry['row'][$col])) $cb($entry['row'][$col]);
                }
                if (!empty($entry['hijos']) && !empty($spec[$tabla]['hijos'])) {
                    $walkHijos($entry['hijos'], $spec[$tabla]['hijos']);
                }
            }
        }
    };
    if (!empty($snap['hijos']) && !empty($reg['hijos'])) {
        $walkHijos($snap['hijos'], $reg['hijos']);
    }
}

/** Purga definitiva: borra archivos del disco y elimina la fila de papelera. */
function papelera_purgar(PDO $pdo, int $papelera_id): bool {
    $st = $pdo->prepare("SELECT * FROM papelera WHERE id = ?");
    $st->execute([$papelera_id]);
    $p = $st->fetch();
    if (!$p) return false;
    $snap = json_decode($p['datos_json'], true);
    if ($snap) {
        _papelera_walk_files($snap, function ($nombre) {
            $ruta = UPLOAD_DIR . $nombre;
            if (is_file($ruta)) { @unlink($ruta); }
        });
    }
    $pdo->prepare("DELETE FROM papelera WHERE id = ?")->execute([$papelera_id]);
    return true;
}

/** Purga todas las no restauradas (vaciar). */
function papelera_vaciar(PDO $pdo): int {
    $ids = $pdo->query("SELECT id FROM papelera WHERE restaurado_en IS NULL")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) { papelera_purgar($pdo, (int)$id); }
    return count($ids);
}

/** Purga automática de elementos con más de N días. */
function papelera_purgar_expiradas(PDO $pdo, int $dias = 30): int {
    $st = $pdo->prepare("SELECT id FROM papelera WHERE restaurado_en IS NULL AND eliminado_en < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $st->execute([$dias]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) { papelera_purgar($pdo, (int)$id); }
    return count($ids);
}
