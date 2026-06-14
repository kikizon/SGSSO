<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$es_admin = ($usuario_rol === 'admin');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT a.*, s.nombre AS sucursal, d.nombre AS departamento, u.nombre_completo AS auditor
                       FROM auditorias_6s a
                       JOIN sucursales s ON s.id = a.sucursal_id
                       JOIN departamentos d ON d.id = a.departamento_id
                       LEFT JOIN usuarios u ON u.id = a.auditor_id
                       WHERE a.id = ?");
$stmt->execute([$id]);
$aud = $stmt->fetch();
if (!$aud) { redirect('modules/auditoria6s/listar.php'); }
if (!$es_admin && $aud['sucursal_id'] != $usuario_sucursal_id) { redirect('modules/auditoria6s/listar.php'); }

$det = $pdo->prepare("SELECT cat.id AS cat_id, cat.nombre AS categoria, cat.orden AS cat_orden,
                             cr.texto, r.calificacion, r.puntaje, r.prioridad, r.fecha_compromiso, r.comentarios
                      FROM criterios_6s cr
                      JOIN categorias_6s cat ON cat.id = cr.categoria_id
                      JOIN criterios_6s_departamento cd ON cd.criterio_id = cr.id AND cd.departamento_id = ?
                      LEFT JOIN auditorias_6s_respuestas r ON r.criterio_id = cr.id AND r.auditoria_id = ?
                      WHERE cr.activo = 1 AND cat.activo = 1
                      ORDER BY cat.orden, cr.orden, cr.id");
$det->execute([$aud['departamento_id'], $id]);
$filas = $det->fetchAll();

$grupos = [];
foreach ($filas as $f) {
    $cid = $f['cat_id'];
    if (!isset($grupos[$cid])) $grupos[$cid] = ['nombre' => $f['categoria'], 'items' => [], 'suma' => 0, 'n' => 0];
    $grupos[$cid]['items'][] = $f;
    $grupos[$cid]['suma'] += ($f['puntaje'] !== null ? $f['puntaje'] : 0);
    $grupos[$cid]['n']++;
}

// Firmantes
$fst = $pdo->prepare("SELECT e.nombre, d.nombre AS depto
                      FROM auditorias_6s_firmantes f
                      JOIN empleados e ON e.id = f.empleado_id
                      LEFT JOIN departamentos d ON d.id = e.departamento_id
                      WHERE f.auditoria_id = ? ORDER BY e.nombre");
$fst->execute([$id]);
$firmantes = $fst->fetchAll();

$labels = [1 => 'No cumple y desconoce', 2 => 'No cumple', 3 => 'Cumple, falta mejorar', 4 => 'Sí cumple'];
$clasep = [25 => 'p25', 50 => 'p50', 75 => 'p75', 100 => 'p100'];

// Logo
$logoRel = 'assets/img/logo.png';
$logoExists = file_exists(__DIR__ . '/../../' . $logoRel);
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Auditoría 6S - <?= htmlspecialchars($aud['departamento']) ?></title>
<style>
  * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 24px; font-size: 12px; }
  .head { display: flex; align-items: center; gap: 16px; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; margin-bottom: 12px; }
  .head img { height: 56px; }
  .head h1 { font-size: 18px; margin: 0; }
  .head .sub { color: #555; font-size: 11px; }
  .meta { margin: 2px 0; color: #333; }
  .total { font-size: 15px; font-weight: bold; margin: 6px 0 12px; }
  table { width: 100%; border-collapse: collapse; margin: 6px 0 14px; }
  th, td { border: 1px solid #bbb; padding: 5px 6px; text-align: left; vertical-align: top; }
  th { background: #f0f0f0; }
  .cat { background: #e8eef7; font-weight: bold; font-size: 13px; }
  .c { text-align: center; }
  /* Colores por calificación */
  .p25  { background: #f8d7da; }
  .p50  { background: #ffe69c; }
  .p75  { background: #cfe2ff; }
  .p100 { background: #d1e7dd; }
  .pna  { background: #eeeeee; color: #777; }
  .leyenda span { display: inline-block; padding: 2px 8px; border: 1px solid #bbb; margin-right: 6px; font-size: 10px; }
  /* Firmas */
  .firmas { display: flex; flex-wrap: wrap; gap: 28px; margin-top: 40px; }
  .firma { text-align: center; min-width: 200px; flex: 1; }
  .firma .linea { border-top: 1px solid #333; margin-bottom: 4px; height: 36px; }
  .firma .rol { color: #666; font-size: 10px; }
  .toolbar { margin-bottom: 16px; }
  .btn { display: inline-block; padding: 8px 16px; background: #0d6efd; color: #fff; text-decoration: none; border-radius: 4px; border: 0; font-size: 14px; cursor: pointer; }
  .btn.sec { background: #6c757d; }
  @page { size: letter; margin: 12mm; }
  @media print {
    .toolbar { display: none; }
    body { margin: 0; }
    table { page-break-inside: auto; }
    tr { page-break-inside: avoid; }
    .firmas { page-break-inside: avoid; }
  }
</style>
</head>
<body>
<div class="toolbar">
  <button class="btn" onclick="window.print()">🖨 Imprimir / ⬇ Descargar PDF</button>
  <a class="btn sec" href="ver.php?id=<?= $id ?>">Volver</a>
</div>

<div id="doc">
<div class="head">
  <?php if ($logoExists): ?><img src="<?= BASE_URL . $logoRel ?>" alt="Logo"><?php endif; ?>
  <div>
    <h1>Formato de Revisión 6S</h1>
    <div class="sub">SUPERMM SYSO · Seguridad y Salud Ocupacional</div>
  </div>
</div>

<div class="meta"><strong>Departamento:</strong> <?= htmlspecialchars($aud['departamento']) ?>
  &nbsp;·&nbsp; <strong>Sucursal:</strong> <?= htmlspecialchars($aud['sucursal']) ?>
  &nbsp;·&nbsp; <strong>Fecha:</strong> <?= format_date_es($aud['fecha']) ?></div>
<div class="meta"><strong>Auditor:</strong> <?= htmlspecialchars($aud['auditor'] ?? '—') ?>
  &nbsp;·&nbsp; <strong>Estado:</strong> <?= $aud['estado'] === 'finalizada' ? 'Finalizada' : 'Borrador' ?></div>
<div class="total">Evaluación Total: <?= $aud['evaluacion_total'] !== null ? number_format($aud['evaluacion_total'],1).'%' : '—' ?></div>

<div class="leyenda">
  <span class="p100">Sí cumple (100)</span>
  <span class="p75">Cumple, falta mejorar (75)</span>
  <span class="p50">No cumple (50)</span>
  <span class="p25">No cumple y desconoce (25)</span>
  <span class="pna">Sin contestar (0)</span>
</div>

<?php foreach ($grupos as $g): $prom = $g['n'] ? $g['suma'] / $g['n'] : 0; ?>
<table>
  <thead>
    <tr><td class="cat" colspan="5"><?= htmlspecialchars($g['nombre']) ?> — Promedio: <?= number_format($prom,1) ?>%</td></tr>
    <tr>
      <th style="width:38%">Criterio</th>
      <th style="width:20%">Calificación</th>
      <th class="c" style="width:8%">Puntaje</th>
      <th style="width:12%">Prioridad</th>
      <th style="width:22%">Compromiso / Comentarios</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($g['items'] as $f):
        $cls = $f['puntaje'] !== null && isset($clasep[(int)$f['puntaje']]) ? $clasep[(int)$f['puntaje']] : 'pna';
    ?>
    <tr>
      <td><?= htmlspecialchars($f['texto']) ?></td>
      <td class="<?= $cls ?>"><?= $f['calificacion'] ? htmlspecialchars($labels[$f['calificacion']]) : 'Sin contestar' ?></td>
      <td class="c <?= $cls ?>"><?= $f['puntaje'] !== null ? (int)$f['puntaje'] : 0 ?></td>
      <td><?= $f['prioridad'] ? htmlspecialchars($f['prioridad']) : '—' ?></td>
      <td><?= $f['fecha_compromiso'] ? format_date_es($f['fecha_compromiso']) : '' ?>
          <?= $f['comentarios'] ? '<br>'.htmlspecialchars($f['comentarios']) : '' ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endforeach; ?>

<div class="firmas">
  <div class="firma">
    <div class="linea"></div>
    <strong><?= htmlspecialchars($aud['auditor'] ?? '') ?></strong>
    <div class="rol">Auditor</div>
  </div>
  <?php foreach ($firmantes as $fm): ?>
  <div class="firma">
    <div class="linea"></div>
    <strong><?= htmlspecialchars($fm['nombre']) ?></strong>
    <div class="rol">Responsable de conformidad<?= $fm['depto'] ? ' · '.htmlspecialchars($fm['depto']) : '' ?></div>
  </div>
  <?php endforeach; ?>
</div>
</div><!-- /#doc -->
</body>
</html>