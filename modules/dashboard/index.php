<?php
/**
 * Dashboard SYSO — Controlador (v2, multi-tablero).
 * tablero = seguridad (default) | 6s | cursos.
 */
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$DIR = __DIR__;

// 1) Filtros comunes + (si aplica) los de Seguridad
require $DIR . '/inc/filtros.php';

// 2) Datos según tablero
if ($tablero === '6s') {
    require $DIR . '/inc/datos_6s.php';
} elseif ($tablero === 'cursos') {
    require $DIR . '/inc/datos_cursos.php';
} else {
    if ($tipo_filtro === 'enfermedad_cronica') {
        require $DIR . '/inc/datos_enfermedades.php';
    } else {
        require $DIR . '/inc/datos_reportes.php';
    }
    require $DIR . '/inc/secciones_pdf.php';
}

// 3) Render
include '../../includes/header.php';
require $DIR . '/vista/selectores.php';

if ($tablero === '6s') {
    require $DIR . '/vista/tablero_6s.php';
    require $DIR . '/vista/scripts_tableros.php';
} elseif ($tablero === 'cursos') {
    require $DIR . '/vista/tablero_cursos.php';
    require $DIR . '/vista/scripts_tableros.php';
} else {
    require $DIR . '/vista/kpis.php';
    require $DIR . '/vista/graficos.php';
    require $DIR . '/vista/scripts.php';
}

include '../../includes/footer.php';
