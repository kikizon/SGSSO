<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once __DIR__ . '/_semanas.php';

$es_admin = ($usuario_rol === 'admin');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT a.*, s.nombre AS sucursal, u.nombre_completo AS auditor
                       FROM auditorias_6s a
                       JOIN sucursales s ON s.id = a.sucursal_id
                       LEFT JOIN usuarios u ON u.id = a.auditor_id
                       WHERE a.id = ?");
$stmt->execute([$id]);
$aud = $stmt->fetch();
if (!$aud) { redirect('modules/auditoria6s/listar.php'); }
if (!$es_admin && !in_array((int)$aud['sucursal_id'], $usuario_sucursales, true)) { redirect('modules/auditoria6s/listar.php'); }

// Departamentos evaluados (con puntaje)
$dstmt = $pdo->prepare("SELECT ad.departamento_id, ad.evaluacion_total, d.nombre
                        FROM auditorias_6s_departamentos ad JOIN departamentos d ON d.id = ad.departamento_id
                        WHERE ad.auditoria_id = ? ORDER BY d.nombre");
$dstmt->execute([$id]);
$deptos = $dstmt->fetchAll();

// Respuestas por departamento
$det = $pdo->prepare("SELECT r.departamento_id, cat.id AS cat_id, cat.nombre AS categoria, cat.orden AS cat_orden,
                             cr.texto, r.calificacion, r.puntaje, r.prioridad, r.fecha_compromiso, r.comentarios
                      FROM auditorias_6s_respuestas r
                      JOIN criterios_6s cr ON cr.id = r.criterio_id
                      JOIN categorias_6s cat ON cat.id = cr.categoria_id
                      WHERE r.auditoria_id = ? ORDER BY cat.orden, cr.orden, cr.id");
$det->execute([$id]);
$detPorDep = [];
foreach ($det->fetchAll() as $f) { $detPorDep[$f['departamento_id']][] = $f; }

// Firmantes por departamento
$fst = $pdo->prepare("SELECT f.departamento_id, e.nombre FROM auditorias_6s_firmantes f
                      JOIN empleados e ON e.id = f.empleado_id WHERE f.auditoria_id = ? ORDER BY e.nombre");
$fst->execute([$id]);
$firmPorDep = [];
foreach ($fst->fetchAll() as $row) { $firmPorDep[$row['departamento_id']][] = $row['nombre']; }

$labels = [1 => 'No cumple y desconoce', 2 => 'No cumple', 3 => 'Cumple, falta mejorar', 4 => 'Sí cumple'];
$clasep = [25 => 'p25', 50 => 'p50', 75 => 'p75', 100 => 'p100'];
$semanaLabel = ($aud['anio'] && $aud['semana']) ? s6_label_semana((int)$aud['anio'], (int)$aud['semana']) : format_date_es($aud['fecha']);

$logoRel = 'assets/img/logo.png';
$logoExists = file_exists(__DIR__ . '/../../' . $logoRel);
?><!DOCTYPE html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Auditoría 6S - <?= htmlspecialchars($aud['sucursal']) ?></title>
<style>
  * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 24px; font-size: 12px; }
  .head { display: flex; align-items: center; gap: 16px; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; margin-bottom: 12px; }
  .head img { height: 56px; }
  .head h1 { font-size: 18px; margin: 0; }
  .head .sub { color: #555; font-size: 11px; }
  .meta { margin: 2px 0; color: #333; }
  .total { font-size: 15px; font-weight: bold; margin: 6px 0 12px; }
  .depthead { background:#e8eef7; padding:6px 8px; font-weight:bold; font-size:14px; margin:18px 0 4px; border-left:4px solid #0d6efd; }
  table { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
  th, td { border: 1px solid #bbb; padding: 5px 6px; text-align: left; vertical-align: top; }
  th { background: #f0f0f0; }
  .cat { background: #eef1f5; font-weight: bold; }
  .c { text-align: center; }
  .p25 { background:#f8d7da; } .p50 { background:#ffe69c; } .p75 { background:#cfe2ff; } .p100 { background:#d1e7dd; } .pna { background:#eee; color:#777; }
  .leyenda span { display:inline-block; padding:2px 8px; border:1px solid #bbb; margin-right:6px; font-size:10px; }
  .firmas { display:flex; flex-wrap:wrap; gap:24px; margin:14px 0 8px; }
  .firma { text-align:center; min-width:180px; flex:1; }
  .firma .linea { border-top:1px solid #333; margin-bottom:4px; height:30px; }
  .firma .rol { color:#666; font-size:10px; }
  .toolbar { margin-bottom:16px; }
  .btn { display:inline-block; padding:8px 16px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:4px; border:0; font-size:14px; cursor:pointer; }
  .btn.sec { background:#6c757d; }
  @page { size: letter; margin: 12mm; }
  @media print { .toolbar { display:none; } body { margin:0; } tr { page-break-inside: avoid; } .dept { page-break-inside: avoid; } }
</style>
</head><body>
<div class="toolbar">
  <button class="btn" onclick="window.print()">🖨 Imprimir / ⬇ Descargar PDF</button>
  <a class="btn sec" href="ver.php?id=<?= $id ?>">Volver</a>
</div>

<div class="head">
  <?php if ($logoExists): ?><img src="<?= BASE_URL . $logoRel ?>" alt="Logo"><?php endif; ?>
  <div><h1>Formato de Revisión 6S</h1><div class="sub">SUPERMM SYSO · Seguridad y Salud Ocupacional</div></div>
</div>

<div class="meta"><strong>Sucursal:</strong> <?= htmlspecialchars($aud['sucursal']) ?>
  &nbsp;·&nbsp; <strong><?= htmlspecialchars($semanaLabel) ?></strong>
  &nbsp;·&nbsp; <strong>Estado:</strong> <?= $aud['estado'] === 'finalizada' ? 'Finalizada' : 'Borrador' ?></div>
<div class="meta"><strong>Auditor:</strong> <?= htmlspecialchars($aud['auditor'] ?? '—') ?>
  <?php if ($aud['fecha_inicio']): ?>&nbsp;·&nbsp; <strong>Inicio:</strong> <?= date('d/m/Y H:i', strtotime($aud['fecha_inicio'])) ?><?php endif; ?>
  <?php if ($aud['fecha_fin']): ?>&nbsp;·&nbsp; <strong>Fin:</strong> <?= date('d/m/Y H:i', strtotime($aud['fecha_fin'])) ?><?php endif; ?></div>
<div class="total">Evaluación Global (promedio de áreas): <?= $aud['evaluacion_total'] !== null ? number_format($aud['evaluacion_total'],1).'%' : '—' ?></div>

<div class="leyenda">
  <span class="p100">Sí cumple (100)</span><span class="p75">Cumple, falta mejorar (75)</span>
  <span class="p50">No cumple (50)</span><span class="p25">No cumple y desconoce (25)</span><span class="pna">Sin contestar (0)</span>
</div>

<?php foreach ($deptos as $d): $depId = (int)$d['departamento_id']; $filas = $detPorDep[$depId] ?? [];
  $grupos = [];
  foreach ($filas as $f) { $grupos[$f['cat_id']]['nombre'] = $f['categoria']; $grupos[$f['cat_id']]['items'][] = $f; $grupos[$f['cat_id']]['suma'] = ($grupos[$f['cat_id']]['suma'] ?? 0) + ($f['puntaje'] !== null ? $f['puntaje'] : 0); $grupos[$f['cat_id']]['n'] = ($grupos[$f['cat_id']]['n'] ?? 0) + 1; }
?>
<div class="dept">
  <div class="depthead"><?= htmlspecialchars($d['nombre']) ?> — <?= $d['evaluacion_total'] !== null ? number_format($d['evaluacion_total'],1).'%' : '—' ?></div>
  <?php foreach ($grupos as $g): $prom = $g['n'] ? $g['suma'] / $g['n'] : 0; ?>
  <table>
    <thead>
      <tr><td class="cat" colspan="5"><?= htmlspecialchars($g['nombre']) ?> — Promedio: <?= number_format($prom,1) ?>%</td></tr>
      <tr><th style="width:38%">Criterio</th><th style="width:20%">Calificación</th><th class="c" style="width:8%">Puntaje</th><th style="width:12%">Prioridad</th><th style="width:22%">Compromiso / Comentarios</th></tr>
    </thead>
    <tbody>
      <?php foreach ($g['items'] as $f): $cls = $f['puntaje'] !== null && isset($clasep[(int)$f['puntaje']]) ? $clasep[(int)$f['puntaje']] : 'pna'; ?>
      <tr>
        <td><?= htmlspecialchars($f['texto']) ?></td>
        <td class="<?= $cls ?>"><?= $f['calificacion'] ? htmlspecialchars($labels[$f['calificacion']]) : 'Sin contestar' ?></td>
        <td class="c <?= $cls ?>"><?= $f['puntaje'] !== null ? (int)$f['puntaje'] : 0 ?></td>
        <td><?= $f['prioridad'] ? htmlspecialchars($f['prioridad']) : '—' ?></td>
        <td><?= $f['fecha_compromiso'] ? format_date_es($f['fecha_compromiso']) : '' ?><?= $f['comentarios'] ? '<br>'.htmlspecialchars($f['comentarios']) : '' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endforeach; ?>
  <div class="firmas">
    <?php if (!empty($firmPorDep[$depId])): foreach ($firmPorDep[$depId] as $fn): ?>
      <div class="firma"><div class="linea"></div><strong><?= htmlspecialchars($fn) ?></strong><div class="rol">Responsable · <?= htmlspecialchars($d['nombre']) ?></div></div>
    <?php endforeach; else: ?>
      <div class="firma"><div class="linea"></div><div class="rol">Responsable · <?= htmlspecialchars($d['nombre']) ?></div></div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<div class="firmas" style="margin-top:30px;">
  <div class="firma"><div class="linea"></div><strong><?= htmlspecialchars($aud['auditor'] ?? '') ?></strong><div class="rol">Auditor</div></div>
  <div class="firma"><div class="linea"></div><strong>SYSO</strong><div class="rol">Seguridad y Salud Ocupacional</div></div>
  <div class="firma"><div class="linea"></div><strong>Gerencia</strong><div class="rol">Vo. Bo.</div></div>
</div>
</body></html>
