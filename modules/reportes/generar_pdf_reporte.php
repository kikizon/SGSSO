<?php
/**
 * PDF institucional de un reporte (acto inseguro / accidente).
 * Flujo: el usuario lo descarga, lo imprime, lo firman, lo escanean y lo
 * vuelven a subir con subir_firmado.php.
 *
 * Bloques de firma: Empleado reportado (Enterado) · SYSO · Gerencia.
 */
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) { redirect('modules/reportes/listar.php'); }

// Datos del reporte
$st = $pdo->prepare("SELECT r.*, e.numero_empleado, e.nombre AS empleado_nombre,
                            d.nombre AS departamento, s.nombre AS sucursal,
                            COALESCE(ai.descripcion, ta.descripcion) AS catalogo,
                            am.descripcion AS atencion_medica,
                            u.nombre_completo AS reportado_por_nombre
                     FROM reportes r
                     JOIN empleados e ON e.id = r.empleado_id
                     JOIN departamentos d ON d.id = r.departamento_id
                     JOIN sucursales s ON s.id = r.sucursal_id
                     LEFT JOIN actos_inseguros ai ON ai.id = r.acto_inseguro_id
                     LEFT JOIN tipos_accidente ta ON ta.id = r.accidente_id
                     LEFT JOIN atenciones_medicas am ON am.id = r.atencion_medica_id
                     LEFT JOIN usuarios u ON u.id = r.reportado_por
                     WHERE r.id = ?");
$st->execute([$id]);
$r = $st->fetch();
if (!$r) { redirect('modules/reportes/listar.php'); }

// Alcance por sucursal (supervisor)
if ($usuario_rol !== 'admin' && !in_array((int)$r['sucursal_id'], $usuario_sucursales, true)) {
    redirect('modules/reportes/listar.php');
}

$esActo = ($r['tipo'] === 'acto_inseguro');
$titulo = $esActo ? 'REPORTE DE ACTO INSEGURO' : 'REPORTE DE ACCIDENTE';

// Logo (si existe) embebido como data URI
$logoTag = '';
foreach ([__DIR__.'/../../assets/img/logo.png', __DIR__.'/../../assets/images/logo.png', __DIR__.'/../../img/logo.png'] as $lp) {
    if (is_file($lp)) {
        $logoTag = '<img src="data:image/png;base64,' . base64_encode(file_get_contents($lp)) . '" style="max-height:60px;">';
        break;
    }
}

$h = fn($v) => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
$fechaTxt = $r['fecha'] ? date('d/m/Y', strtotime($r['fecha'])) : '—';
$horaTxt  = $r['hora'] ? date('H:i', strtotime($r['hora'])) : '—';

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
    @page { margin: 1.6cm; }
    body { font-family: Arial, sans-serif; color:#222; font-size:12px; }
    .head { width:100%; border-bottom:2px solid #0d6efd; padding-bottom:8px; margin-bottom:14px; }
    .head td { vertical-align:middle; }
    h1 { font-size:16px; color:#0d6efd; margin:0; text-align:center; }
    .folio { text-align:right; font-size:11px; color:#555; }
    table.datos { width:100%; border-collapse:collapse; margin-bottom:12px; }
    table.datos td { border:1px solid #ddd; padding:7px; }
    table.datos .lbl { background:#f4f6f9; font-weight:bold; width:22%; }
    .obs { border:1px solid #ddd; padding:10px; min-height:70px; margin-bottom:12px; }
    .obs .t { font-weight:bold; color:#0d6efd; margin-bottom:4px; }
    .firmas { width:100%; margin-top:48px; border-collapse:collapse; }
    .firmas td { width:33%; text-align:center; padding:0 10px; vertical-align:bottom; }
    .linea { border-top:1px solid #333; margin-top:40px; padding-top:6px; font-size:11px; }
    .rol { font-weight:bold; } .sub { color:#666; font-size:10px; }
    .foot { margin-top:24px; font-size:9px; color:#999; text-align:center; border-top:1px solid #eee; padding-top:6px; }
</style></head><body>';

$html .= '<table class="head"><tr>
    <td style="width:25%">' . $logoTag . '</td>
    <td style="width:50%"><h1>' . $titulo . '</h1><div style="text-align:center;font-size:11px;color:#555;">Seguridad y Salud Ocupacional</div></td>
    <td style="width:25%" class="folio">Folio: <strong>#' . (int)$r['id'] . '</strong><br>Sucursal: ' . $h($r['sucursal']) . '</td>
</tr></table>';

$html .= '<table class="datos">
    <tr><td class="lbl">Empleado</td><td>' . $h($r['numero_empleado'] . ' - ' . $r['empleado_nombre']) . '</td>
        <td class="lbl">Departamento</td><td>' . $h($r['departamento']) . '</td></tr>
    <tr><td class="lbl">Fecha</td><td>' . $fechaTxt . '</td>
        <td class="lbl">Hora</td><td>' . $horaTxt . '</td></tr>
    <tr><td class="lbl">' . ($esActo ? 'Acto inseguro' : 'Tipo de accidente') . '</td><td colspan="3">' . $h($r['catalogo']) . '</td></tr>';

if (!$esActo) {
    $html .= '<tr><td class="lbl">Gravedad</td><td>' . $h(ucfirst((string)$r['gravedad'])) . '</td>
                  <td class="lbl">Días de incapacidad</td><td>' . (int)$r['dias_perdidos'] . '</td></tr>
              <tr><td class="lbl">Atención médica</td><td>' . $h($r['atencion_medica']) . '</td>
                  <td class="lbl">ST7 / Costo</td><td>' . ($r['st7'] ? 'ST7: Sí' : ('Costo: $' . number_format((float)$r['costo_atencion'], 2))) . '</td></tr>';
}
$html .= '</table>';

$html .= '<div class="obs"><div class="t">Descripción / Observaciones</div>' . nl2br($h($r['observacion'] ?: 'Sin observaciones')) . '</div>';

$html .= '<div style="font-size:10px;color:#666;">Reportado por: ' . $h($r['reportado_por_nombre']) .
         ($r['creado_en'] ? ' · ' . date('d/m/Y H:i', strtotime($r['creado_en'])) : '') . '</div>';

// Bloques de firma
$html .= '<table class="firmas"><tr>
    <td><div class="linea"><span class="rol">' . $h($r['empleado_nombre']) . '</span><br><span class="sub">Empleado reportado — Enterado</span></div></td>
    <td><div class="linea"><span class="rol">SYSO</span><br><span class="sub">Seguridad y Salud Ocupacional</span></div></td>
    <td><div class="linea"><span class="rol">Gerencia</span><br><span class="sub">Vo. Bo.</span></div></td>
</tr></table>';

$html .= '<div class="foot">Documento generado por SUPERMM SYSO el ' . date('d/m/Y H:i') . '</div>';
$html .= '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('reporte_' . (int)$r['id'] . '_' . date('Ymd') . '.pdf', ['Attachment' => true]);
exit;
