<?php
/**
 * Secciones para el informe PDF.
 * Se eliminaron: "Comparativa Actos vs Accidentes" y el "Mapa de Calor".
 * Para enfermedades se agregan "Por departamento" y "Por rango de edad".
 */

if ($tipo_filtro === 'enfermedad_cronica') {
    $seccionesPdf = [
        ['tipo' => 'kpi',     'titulo' => 'Indicadores Clave (KPIs)', 'descripcion' => 'Resumen de prevalencia y comorbilidad.'],
        ['tipo' => 'grafico', 'id' => 'tendenciaChart', 'titulo' => 'Nuevos registros (últimos 6 meses)', 'descripcion' => 'Evolución mensual de nuevos registros.'],
        ['tipo' => 'grafico', 'id' => 'catalogoChart', 'titulo' => 'Top 10 Enfermedades', 'descripcion' => 'Enfermedades con mayor incidencia.'],
        ['tipo' => 'grafico', 'id' => 'prevalenciaSucursalChart', 'titulo' => 'Prevalencia por Sucursal', 'descripcion' => 'Empleados con enfermedades por sucursal.'],
        ['tipo' => 'grafico', 'id' => 'enfDeptoChart', 'titulo' => 'Enfermedades por Departamento', 'descripcion' => 'Empleados con enfermedades por departamento.'],
        ['tipo' => 'grafico', 'id' => 'enfEdadChart', 'titulo' => 'Enfermedades por Rango de Edad', 'descripcion' => 'Empleados con enfermedades por edad.'],
        ['tipo' => 'grafico', 'id' => 'paretoChart', 'titulo' => 'Diagrama de Pareto', 'descripcion' => 'Principales enfermedades y porcentaje acumulado.'],
        ['tipo' => 'tabla',   'titulo' => 'Top 5 Empleados con más enfermedades', 'descripcion' => ''],
    ];
} else {
    $seccionesPdf = [
        ['tipo' => 'kpi',     'titulo' => 'Indicadores Clave (KPIs)', 'descripcion' => 'Resumen ejecutivo de los principales indicadores.'],
        ['tipo' => 'grafico', 'id' => 'tendenciaChart', 'titulo' => 'Tendencia de Reportes (Últimos 6 meses)', 'descripcion' => 'Evolución mensual.'],
        ['tipo' => 'grafico', 'id' => 'catalogoChart', 'titulo' => 'Distribución por ' . $tituloCatalogo, 'descripcion' => 'Proporción de cada tipo (solo con incidencia).'],
        ['tipo' => 'grafico', 'id' => 'comparativaAnualChart', 'titulo' => 'Comparativa Anual', 'descripcion' => 'Contraste con el año anterior.'],
        ['tipo' => 'grafico', 'id' => $chartIdSeveridadEdad, 'titulo' => $tituloSeveridadEdad, 'descripcion' => ''],
        ['tipo' => 'grafico', 'id' => 'horasChart', 'titulo' => 'Reportes por Hora del Día', 'descripcion' => 'Franjas horarias con mayor incidencia.'],
        ['tipo' => 'grafico', 'id' => 'diasChart', 'titulo' => 'Reportes por Día de la Semana', 'descripcion' => 'Distribución semanal.'],
        ['tipo' => 'grafico', 'id' => 'paretoChart', 'titulo' => 'Diagrama de Pareto (Top 10 causas)', 'descripcion' => 'Principales causas y porcentaje acumulado.'],
        ['tipo' => 'grafico', 'id' => 'deptosChart', 'titulo' => 'Top 5 Departamentos', 'descripcion' => ''],
        ['tipo' => 'tabla',   'titulo' => 'Top 5 Sucursales', 'descripcion' => 'Sucursales con mayor cantidad.'],
        ['tipo' => 'tabla',   'titulo' => 'Top 5 Empleados', 'descripcion' => ''],
    ];
}
$seccionesJson = json_encode($seccionesPdf);
