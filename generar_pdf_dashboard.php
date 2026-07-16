<?php
require_once 'includes/auth.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

try {
    $charts    = json_decode($_POST['charts'] ?? '{}', true) ?: [];
    $secciones = json_decode($_POST['secciones'] ?? '[]', true) ?: [];
    $sucursal  = $_POST['sucursal'] ?? 'Todas';
    $tipo      = $_POST['tipo'] ?? 'acto_inseguro';
    $periodo   = $_POST['periodo'] ?? 'Todo el histórico';
    $tipoTexto = $tipo == 'acto_inseguro' ? 'Actos Inseguros' : ($tipo == 'accidente' ? 'Accidentes' : 'Enfermedades Crónicas');

    // CSS compacto: SIN salto de página por sección. Los bloques fluyen y solo
    // se evita cortar un bloque a la mitad (page-break-inside: avoid).
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Informe SYSO</title><style>
        @page { margin: 1.2cm; }
        body { font-family: Arial, sans-serif; margin: 0; color: #333; font-size: 12px; }
        h1 { color: #0d6efd; text-align: center; margin: 0 0 4px; font-size: 20px; }
        .subtitulo { text-align: center; color: #666; margin-bottom: 12px; border-bottom: 1px solid #ddd; padding-bottom: 8px; font-size: 11px; }
        .bloque { page-break-inside: avoid; margin-bottom: 14px; }
        h2 { color: #0d6efd; border-left: 4px solid #0d6efd; padding-left: 10px; margin: 0 0 4px; font-size: 14px; }
        .descripcion { font-style: italic; color: #777; margin-bottom: 6px; font-size: 10px; }
        .kpi-container { width: 100%; }
        .kpi-card { display: inline-block; width: 30%; margin: 1%; padding: 10px; border-radius: 8px; color: #fff; text-align: center; vertical-align: top; box-sizing: border-box; }
        .bg-primary{background:#0d6efd}.bg-success{background:#198754}.bg-info{background:#0dcaf0}.bg-warning{background:#ffc107;color:#000}.bg-danger{background:#dc3545}.bg-dark{background:#212529}.bg-secondary{background:#6c757d}
        .kpi-title { font-size: 11px; margin-bottom: 4px; } .kpi-value { font-size: 22px; font-weight: bold; }
        .grafico-container { text-align: center; margin: 4px 0; }
        .grafico-container img { max-width: 70%; height: auto; border: 1px solid #eee; border-radius: 6px; }
        table { width: 100%; border-collapse: collapse; margin: 4px 0; font-size: 11px; }
        th { background: #f2f2f2; } th, td { border: 1px solid #ddd; padding: 5px; text-align: left; }
        .footer { text-align: center; margin-top: 16px; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 8px; }
    </style></head><body>
    <h1>Informe de Seguridad y Salud Ocupacional</h1>
    <div class="subtitulo">' . htmlspecialchars($tipoTexto) . ' &nbsp;·&nbsp; Sucursal: ' . htmlspecialchars($sucursal) . ' &nbsp;·&nbsp; Periodo: ' . htmlspecialchars($periodo) . ' &nbsp;·&nbsp; Generado el ' . date('d/m/Y H:i') . '</div>';

    $colorMap = ['Total Reportes'=>'bg-primary','Total Empleados'=>'bg-primary','Este Mes'=>'bg-success','Empleados'=>'bg-info','Prevalencia'=>'bg-success','Comorbilidad'=>'bg-info','DSA'=>'bg-danger','Tasa Frec.'=>'bg-dark','Tasa Gravedad'=>'bg-secondary','Costo Atenciones'=>'bg-success','% ST7'=>'bg-info'];

    foreach ($secciones as $sec) {
        $html .= '<div class="bloque">';
        $html .= '<h2>' . htmlspecialchars($sec['titulo']) . '</h2>';
        if (!empty($sec['descripcion'])) $html .= '<div class="descripcion">' . htmlspecialchars($sec['descripcion']) . '</div>';

        if ($sec['tipo'] === 'kpi') {
            $html .= '<div class="kpi-container">';
            foreach (($sec['datos'] ?? []) as $kpi) {
                $title = $kpi['title'] ?? ''; $value = $kpi['value'] ?? '';
                $bg = $colorMap[$title] ?? 'bg-primary';
                $html .= '<div class="kpi-card ' . $bg . '"><div class="kpi-title">' . htmlspecialchars($title) . '</div><div class="kpi-value">' . htmlspecialchars($value) . '</div></div>';
            }
            $html .= '</div>';
        } elseif ($sec['tipo'] === 'grafico') {
            if (!empty($charts[$sec['id']])) {
                $html .= '<div class="grafico-container"><img src="' . $charts[$sec['id']] . '"></div>';
            } else {
                $html .= '<p style="color:#999;font-size:10px;">Sin datos para graficar.</p>';
            }
        } elseif ($sec['tipo'] === 'tabla') {
            $html .= '<table><thead><tr>';
            foreach (($sec['columnas'] ?? []) as $col) $html .= '<th>' . htmlspecialchars($col) . '</th>';
            $html .= '</tr></thead><tbody>';
            foreach (($sec['datos'] ?? []) as $row) {
                $html .= '<tr>';
                foreach ($row as $val) $html .= '<td>' . htmlspecialchars($val) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }
        $html .= '</div>';
    }

    $html .= '<div class="footer">Sistema SYSO SUPERMM · Documento generado automáticamente</div></body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait'); // vertical: más bloques por hoja
    $dompdf->render();
    $dompdf->stream('informe_syso_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo 'Error al generar PDF: ' . $e->getMessage();
    exit;
}
