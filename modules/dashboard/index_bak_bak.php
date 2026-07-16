<?php
/**
 * Dashboard SYSO — Controlador
 * Orquesta: filtros -> datos (según tipo) -> secciones PDF -> vistas.
 * La lógica pesada vive en inc/ y la presentación en vista/.
 */
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$DIR = __DIR__;

// 1) Filtros (sucursal, tipo, mes) + rango de datos "desde / hasta"
require $DIR . '/inc/filtros.php';

// 2) Datos según el tipo seleccionado
if ($tipo_filtro === 'enfermedad_cronica') {
    require $DIR . '/inc/datos_enfermedades.php';
} else {
    require $DIR . '/inc/datos_reportes.php';
}

// 3) Secciones para el informe PDF
require $DIR . '/inc/secciones_pdf.php';

// 4) Render
include '../../includes/header.php';
require $DIR . '/vista/selectores.php';
require $DIR . '/vista/kpis.php';
require $DIR . '/vista/graficos.php';
require $DIR . '/vista/scripts.php';
include '../../includes/footer.php';
