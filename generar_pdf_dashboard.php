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
    $charts = json_decode($_POST['charts'] ?? '{}', true) ?: [];
    $secciones = json_decode($_POST['secciones'] ?? '[]', true) ?: [];
    $sucursal = $_POST['sucursal'] ?? 'Todas';
    $tipo = $_POST['tipo'] ?? 'acto_inseguro';
    $tipoTexto = $tipo == 'acto_inseguro' ? 'Actos Inseguros' : 'Accidentes';

    // Inicio del HTML
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Informe SYSO</title><style>
        @page { margin: 1.5cm; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; color: #333; }
        h1 { color: #0d6efd; text-align: center; margin-bottom: 10px; }
        .subtitulo { text-align: center; color: #666; margin-bottom: 30px; border-bottom: 1px solid #ddd; padding-bottom: 15px; }
        .seccion { page-break-before: always; }
        .seccion:first-of-type { page-break-before: avoid; }
        h2 { color: #0d6efd; border-left: 5px solid #0d6efd; padding-left: 15px; margin-top: 0; }
        .descripcion { font-style: italic; color: #555; margin-bottom: 20px; }
        .kpi-container { display: flex; flex-wrap: wrap; gap: 15px; margin: 20px 0; }
        .kpi-card { flex: 1 0 180px; padding: 20px; border-radius: 12px; color: white; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .bg-primary { background-color: #0d6efd; } .bg-success { background-color: #198754; } .bg-info { background-color: #0dcaf0; } .bg-warning { background-color: #ffc107; color: #000; } .bg-danger { background-color: #dc3545; } .bg-dark { background-color: #212529; } .bg-secondary { background-color: #6c757d; }
        .kpi-title { font-size: 16px; margin-bottom: 10px; } .kpi-value { font-size: 36px; font-weight: bold; }
        .grafico-container { text-align: center; margin: 20px 0; }
        .grafico-container img { max-width: 100%; width: 90%; height: auto; border: 1px solid #eee; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        th { background-color: #f2f2f2; font-weight: bold; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .footer { text-align: center; margin-top: 40px; font-size: 12px; color: #888; border-top: 1px solid #eee; padding-top: 15px; }
    </style></head><body>
    <h1>Informe de Seguridad y Salud Ocupacional</h1>
    <div class="subtitulo">' . $tipoTexto . ' – Sucursal: ' . htmlspecialchars($sucursal) . ' – Generado el ' . date('d/m/Y H:i') . '</div>';

    $colorMap = ['Total Reportes'=>'bg-primary','Este Mes'=>'bg-success','Empleados'=>'bg-info','Prom. Diario'=>'bg-warning','DSA'=>'bg-danger','Tasa Free.'=>'bg-dark','Tasa Gravedad'=>'bg-secondary'];

    foreach ($secciones as $index => $sec) {
        $html .= '<div class="seccion">';
        $html .= '<h2>' . htmlspecialchars($sec['titulo']) . '</h2>';
        $html .= '<div class="descripcion">' . htmlspecialchars($sec['descripcion']) . '</div>';

        if ($sec['tipo'] === 'kpi') {
            $html .= '<div class="kpi-container">';
            foreach ($sec['datos'] as $kpi) {
                $title = $kpi['title'] ?? ''; $value = $kpi['value'] ?? '';
                $bg = $colorMap[$title] ?? 'bg-primary';
                $html .= '<div class="kpi-card ' . $bg . '"><div class="kpi-title">' . htmlspecialchars($title) . '</div><div class="kpi-value">' . htmlspecialchars($value) . '</div></div>';
            }
            $html .= '</div>';
        } elseif ($sec['tipo'] === 'grafico') {
            if (!empty($charts[$sec['id']])) {
                $html .= '<div class="grafico-container"><img src="' . $charts[$sec['id']] . '" alt="' . htmlspecialchars($sec['titulo']) . '"></div>';
            } else {
                $html .= '<p style="color: #999;">Gráfico no disponible.</p>';
            }
        } elseif ($sec['tipo'] === 'tabla') {
            $html .= '<table><thead><tr>';
            foreach ($sec['columnas'] as $col) $html .= '<th>' . htmlspecialchars($col) . '</th>';
            $html .= '</tr></thead><tbody>';
            foreach ($sec['datos'] as $row) {
                $html .= '<tr>';
                foreach ($row as $val) $html .= '<td>' . htmlspecialchars($val) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        $html .= '</div>';
    }

    $html .= '<div class="footer">Sistema SYSO SUPERMM – Documento generado automáticamente</div>';
    $html .= '</body></html>';

    // Generar PDF
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream('informe_syso_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo 'Error al generar PDF: ' . $e->getMessage();
    exit;
}