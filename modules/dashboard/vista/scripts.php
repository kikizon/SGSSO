<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tips.map(el => new bootstrap.Tooltip(el));

    const totalHoras = <?= isset($horasData) ? array_sum($horasData) : 0 ?>,
          totalDias  = <?= isset($reportesPorDia) ? array_sum($reportesPorDia) : 0 ?>,
          totalDeptos = <?= isset($deptosData) ? array_sum($deptosData) : 0 ?>,
          totalEdad = <?= isset($conteoEdad) ? array_sum($conteoEdad) : 0 ?>,
          totalSeveridad = <?= isset($severidadData) ? array_sum($severidadData) : 0 ?>,
          totalSucPrev = <?= isset($sucPrevValores) ? array_sum($sucPrevValores) : 0 ?>,
          totalEnfDepto = <?= isset($enfDeptoValores) ? array_sum($enfDeptoValores) : 0 ?>,
          totalEnfEdad = <?= isset($enfEdadValores) ? array_sum($enfEdadValores) : 0 ?>;

    function crearGrafico(id, config) {
        const canvas = document.getElementById(id);
        if (!canvas) return null;
        const ex = Chart.getChart(canvas);
        if (ex) ex.destroy();
        return new Chart(canvas, config);
    }

    // Tendencia (común)
    crearGrafico('tendenciaChart', {
        type: 'line',
        data: { labels: <?= json_encode($meses) ?>, datasets: [{ label: 'Registros', data: <?= json_encode($reportesPorMes) ?>, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.1)', tension: 0.2, fill: true }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });

    // Rueda / Catálogo (común)
    (function renderDona() {
        const canvas = document.getElementById('catalogoChart');
        if (!canvas) return;
        if (canvas.offsetWidth > 0 && canvas.offsetHeight > 0) {
            const totalDona = <?= json_encode(array_sum($catalogoValores)) ?>;
            crearGrafico('catalogoChart', {
                type: 'doughnut',
                data: { labels: <?= json_encode($catalogoLabels) ?>, datasets: [{ data: <?= json_encode($catalogoValores) ?>, backgroundColor: ['#0d6efd','#6610f2','#6f42c1','#d63384','#4caf50','#ff9800','#9c27b0','#00bcd4','#ffeb3b','#795548'], borderWidth: 1, borderColor: '#fff' }] },
                options: { responsive: true, maintainAspectRatio: true, cutout: '40%', layout: { padding: 2 }, plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, font: { size: 9 }, padding: 6 } }, tooltip: { callbacks: { label: (c) => { let v = c.raw, p = totalDona > 0 ? ((v / totalDona) * 100).toFixed(1) : 0; return `${c.label}: ${v} (${p}%)`; } } } } }
            });
        } else { requestAnimationFrame(renderDona); }
    })();

    // Pareto (común)
    crearGrafico('paretoChart', {
        type: 'bar',
        data: { labels: <?= json_encode($paretoLabels) ?>, datasets: [
            { label: 'Frecuencia', data: <?= json_encode($paretoData) ?>, backgroundColor: '#36a2eb', yAxisID: 'y' },
            { label: '% Acumulado', data: <?= json_encode($paretoAcumulado) ?>, type: 'line', borderColor: '#ff6384', borderWidth: 2, pointRadius: 4, fill: false, tension: 0.3, yAxisID: 'y1' }
        ]},
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, title: { display: true, text: 'Frecuencia' } }, y1: { position: 'right', beginAtZero: true, max: 100, grid: { drawOnChartArea: false }, title: { display: true, text: '% acumulado' } } } }
    });

<?php if ($tipo_filtro == 'enfermedad_cronica'): ?>

    // Prevalencia por sucursal
    setTimeout(function () {
        crearGrafico('prevalenciaSucursalChart', { type: 'bar', data: { labels: <?= json_encode($sucPrevLabels) ?>, datasets: [{ label: 'Empleados con enfermedad', data: <?= json_encode($sucPrevValores) ?>, backgroundColor: '#198754' }] }, options: { indexAxis: 'y', plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => { let v = c.raw, p = totalSucPrev > 0 ? ((v / totalSucPrev) * 100).toFixed(1) : 0; return `Empleados: ${v} (${p}%)`; } } } } } });
    }, 100);

    // NUEVO: por departamento
    crearGrafico('enfDeptoChart', { type: 'bar', data: { labels: <?= json_encode($enfDeptoLabels) ?>, datasets: [{ label: 'Empleados', data: <?= json_encode($enfDeptoValores) ?>, backgroundColor: '#0dcaf0' }] }, options: { indexAxis: 'y', plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => { let v = c.raw, p = totalEnfDepto > 0 ? ((v / totalEnfDepto) * 100).toFixed(1) : 0; return `Empleados: ${v} (${p}%)`; } } } } } });

    // NUEVO: por rango de edad
    crearGrafico('enfEdadChart', { type: 'bar', data: { labels: <?= json_encode($enfEdadLabels) ?>, datasets: [{ label: 'Empleados', data: <?= json_encode($enfEdadValores) ?>, backgroundColor: '#ff6384' }] }, options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => { let v = c.raw, p = totalEnfEdad > 0 ? ((v / totalEnfEdad) * 100).toFixed(1) : 0; return `Empleados: ${v} (${p}%)`; } } } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } });

<?php else: ?>

    // Comparativa anual
    crearGrafico('comparativaAnualChart', { type: 'bar', data: { labels: <?= json_encode($mesesAnio) ?>, datasets: [{ label: 'Actual', data: <?= json_encode($actualAnio) ?>, backgroundColor: '#198754' }, { label: 'Anterior', data: <?= json_encode($anteriorAnio) ?>, backgroundColor: '#6c757d' }] }, options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } });

    <?php if ($tipo_filtro == 'accidente'): ?>
    crearGrafico('severidadChart', { type: 'bar', data: { labels: <?= json_encode($severidadLabels) ?>, datasets: [{ label: 'Accidentes', data: <?= json_encode($severidadData) ?>, backgroundColor: ['#ffc107','#fd7e14','#dc3545','#212529'] }] }, options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => { let v = c.raw, p = totalSeveridad > 0 ? ((v / totalSeveridad) * 100).toFixed(1) : 0; return `Accidentes: ${v} (${p}%)`; } } } } } });
    <?php else: ?>
    crearGrafico('edadChart', { type: 'bar', data: { labels: <?= json_encode($rangosEdad) ?>, datasets: [{ label: 'Empleados', data: <?= json_encode($conteoEdad) ?>, backgroundColor: '#ff6384' }] }, options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => { let v = c.raw, p = totalEdad > 0 ? ((v / totalEdad) * 100).toFixed(1) : 0; return `Empleados: ${v} (${p}%)`; } } } } } });
    <?php endif; ?>

    // Hora del día y día de la semana
    crearGrafico('horasChart', { type: 'bar', data: { labels: <?= json_encode($horasLabels) ?>, datasets: [{ label: 'Reportes', data: <?= json_encode($horasData) ?>, backgroundColor: '#0dcaf0' }] }, options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => { let v = c.raw, p = totalHoras > 0 ? ((v / totalHoras) * 100).toFixed(1) : 0; return `Reportes: ${v} (${p}%)`; } } } }, scales: { y: { beginAtZero: true } } } });
    crearGrafico('diasChart', { type: 'bar', data: { labels: <?= json_encode($diasSemanaFull) ?>, datasets: [{ data: <?= json_encode($reportesPorDia) ?>, backgroundColor: '#6f42c1' }] }, options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => { let v = c.raw, p = totalDias > 0 ? ((v / totalDias) * 100).toFixed(1) : 0; return `Reportes: ${v} (${p}%)`; } } } } } });

    // Top departamentos
    crearGrafico('deptosChart', { type: 'bar', data: { labels: <?= json_encode($deptosLabels) ?>, datasets: [{ data: <?= json_encode($deptosData) ?>, backgroundColor: '#198754' }] }, options: { indexAxis: 'y', plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => { let v = c.raw, p = totalDeptos > 0 ? ((v / totalDeptos) * 100).toFixed(1) : 0; return `Reportes: ${v} (${p}%)`; } } } } } });

<?php endif; ?>

    // ============================================================
    // EXPORTAR A PDF
    // ============================================================
    const seccionesBase = <?= $seccionesJson ?>;
    document.getElementById('btnExportarPDF').addEventListener('click', async function () {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generando…';
        try {
            await new Promise(r => setTimeout(r, 300));
            const charts = {};
            const canvasIds = ['tendenciaChart','catalogoChart','comparativaAnualChart','severidadChart','edadChart','horasChart','diasChart','paretoChart','deptosChart','prevalenciaSucursalChart','enfDeptoChart','enfEdadChart'];
            for (let id of canvasIds) {
                const c = document.getElementById(id);
                if (c && c.offsetWidth > 0) charts[id] = c.toDataURL('image/png');
            }
            const kpis = [];
            document.querySelectorAll('.kpi-card').forEach(card => {
                const title = card.querySelector('.card-title')?.childNodes[0]?.nodeValue?.trim() || '';
                const value = card.querySelector('h2')?.innerText?.trim() || '';
                if (title && value) kpis.push({ title, value });
            });
            const topSucursales = [];
            document.querySelectorAll('.tabla-top-sucursales tbody tr').forEach(row => {
                const c = row.querySelectorAll('td');
                if (c.length >= 3) topSucursales.push({ sucursal: c[0].innerText, total: c[1].innerText, prom: c[2].innerText });
            });
            const topEmpleados = [];
            document.querySelectorAll('.tabla-top-empleados tbody tr').forEach(row => {
                const c = row.querySelectorAll('td');
                if (c.length >= 3) topEmpleados.push({ num: c[0].innerText, nombre: c[1].innerText, total: c[2].innerText });
            });
            const secciones = seccionesBase.map(sec => {
                if (sec.tipo === 'kpi') return { ...sec, datos: kpis };
                if (sec.titulo === 'Top 5 Sucursales') return { ...sec, datos: topSucursales, columnas: ['Sucursal','Total','Prom. Mensual'] };
                if (sec.titulo === 'Top 5 Empleados' || sec.titulo === 'Top 5 Empleados con más enfermedades') return { ...sec, datos: topEmpleados, columnas: ['#','Nombre','Total'] };
                return sec;
            });
            const fd = new FormData();
            fd.append('charts', JSON.stringify(charts));
            fd.append('secciones', JSON.stringify(secciones));
            fd.append('sucursal', '<?= htmlspecialchars($nombreSucursalSeleccionada, ENT_QUOTES) ?>');
            fd.append('tipo', '<?= $tipo_filtro ?>');
            fd.append('periodo', '<?= htmlspecialchars($etiquetaMes, ENT_QUOTES) ?>');
            const resp = await fetch('<?= BASE_URL ?>generar_pdf_dashboard.php', { method: 'POST', body: fd });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const blob = await resp.blob();
            if (blob.size === 0) throw new Error('PDF vacío');
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = 'dashboard_syso_<?= date('Ymd_His') ?>.pdf';
            document.body.appendChild(a); a.click(); window.URL.revokeObjectURL(url); a.remove();
        } catch (e) {
            console.error('Error PDF:', e);
            alert('Error al generar el informe PDF.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-file-pdf"></i> PDF';
        }
    });
});
</script>
