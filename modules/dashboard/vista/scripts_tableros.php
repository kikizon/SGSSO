<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).map(el => new bootstrap.Tooltip(el));

    function crearGrafico(id, config) {
        const c = document.getElementById(id);
        if (!c) return null;
        const ex = Chart.getChart(c);
        if (ex) ex.destroy();
        return new Chart(c, config);
    }
    const colorPorValor = v => v >= 85 ? '#198754' : (v >= 70 ? '#0dcaf0' : (v >= 50 ? '#ffc107' : '#dc3545'));

<?php if ($tablero === '6s'): ?>

    // Evolución semanal con línea de meta
    (function () {
        const data = <?= json_encode($datEvo6s) ?>;
        const meta = <?= (int)$meta_6s ?>;
        crearGrafico('evol6sChart', {
            type: 'bar',
            data: {
                labels: <?= json_encode($lblEvo6s) ?>,
                datasets: [
                    { label: 'Cumplimiento %', data: data, backgroundColor: data.map(colorPorValor), order: 2 },
                    { label: 'Meta', data: data.map(() => meta), type: 'line', borderColor: '#212529', borderDash: [6,4], borderWidth: 2, pointRadius: 0, order: 1 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } } }
        });
    })();

    // Radar por categoría
    crearGrafico('cat6sChart', {
        type: 'radar',
        data: { labels: <?= json_encode($lblCat6s) ?>, datasets: [{ label: 'Cumplimiento %', data: <?= json_encode($datCat6s) ?>, backgroundColor: 'rgba(13,110,253,0.2)', borderColor: '#0d6efd', borderWidth: 2 }] },
        options: { responsive: true, maintainAspectRatio: false, scales: { r: { min: 60, max: 100, ticks: { stepSize: 10 } } }, plugins: { legend: { display: false } } }
    });

    // Por departamento (radar, igual que por categoría)
    crearGrafico('dep6sChart', {
        type: 'radar',
        data: { labels: <?= json_encode($lblDep6s) ?>, datasets: [{ label: 'Cumplimiento %', data: <?= json_encode($datDep6s) ?>, backgroundColor: 'rgba(25,135,84,0.2)', borderColor: '#198754', borderWidth: 2 }] },
        options: { responsive: true, maintainAspectRatio: false, scales: { r: { min: 60, max: 100, ticks: { stepSize: 10 } } }, plugins: { legend: { display: false } } }
    });

<?php elseif ($tablero === 'cursos'): ?>

    // Cobertura por curso (barras horizontales)
    (function () {
        const data = <?= json_encode($datCursos) ?>;
        crearGrafico('cobCursosChart', {
            type: 'bar',
            data: { labels: <?= json_encode($lblCursos) ?>, datasets: [{ label: 'Cobertura %', data: data, backgroundColor: data.map(colorPorValor) }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, scales: { x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => 'Cobertura: ' + c.raw + '%' } } } }
        });
    })();

    // Distribución de estatus (dona)
    (function () {
        const data = <?= json_encode($distData) ?>;
        const total = data.reduce((a, b) => a + b, 0);
        crearGrafico('distCursosChart', {
            type: 'doughnut',
            data: { labels: <?= json_encode($distLabels) ?>, datasets: [{ data: data, backgroundColor: ['#198754', '#ffc107', '#dc3545', '#6c757d'], borderWidth: 1, borderColor: '#fff' }] },
            options: { responsive: true, maintainAspectRatio: true, cutout: '45%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } }, tooltip: { callbacks: { label: c => { const p = total > 0 ? ((c.raw / total) * 100).toFixed(1) : 0; return `${c.label}: ${c.raw} (${p}%)`; } } } } }
        });
    })();

<?php endif; ?>
});
</script>
