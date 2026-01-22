// Arquivo: js/estatisticas.js
document.addEventListener('DOMContentLoaded', function() {
    const colors = {
        palette: ['#007bff', '#28a745', '#dc3545', '#ffc107', '#6f42c1', '#17a2b8'],
        grid: 'rgba(255, 255, 255, 0.1)',
        text: '#ffffff',
        bar: '#28a745'
    };

    Chart.defaults.color = colors.text;

    // 1. Pizza de Formatos
    const ctxPie = document.getElementById('formatoPieChart');
    if (ctxPie) {
        new Chart(ctxPie, {
            type: 'pie',
            data: {
                labels: STATS_DATA.formatos.map(i => i.formato),
                datasets: [{
                    data: STATS_DATA.formatos.map(i => i.total),
                    backgroundColor: colors.palette
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15 } }
                }
            }
        });
    }

    // 2. Lançamentos por Ano
    const ctxAnos = document.getElementById('anosBarChart');
    if (ctxAnos) {
        new Chart(ctxAnos, {
            type: 'bar',
            data: {
                labels: STATS_DATA.anos.map(i => i.ano),
                datasets: [{
                    data: STATS_DATA.anos.map(i => i.total),
                    backgroundColor: colors.bar
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: colors.grid } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // Função Genérica para Rankings Horizontais
    const renderRanking = (id, data, labelKey, color) => {
        const el = document.getElementById(id);
        if (!el) return;
        new Chart(el, {
            type: 'bar',
            data: {
                labels: data.map(i => i[labelKey]).reverse(),
                datasets: [{
                    data: data.map(i => i.total).reverse(),
                    backgroundColor: color
                }]
            },
            options: {
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: colors.grid } },
                    y: { grid: { display: false } }
                }
            }
        });
    };

    // Renderização dos 5 Rankings Restantes
    renderRanking('generoBarChart', STATS_DATA.top_generos, 'nome', '#28a745');
    renderRanking('estiloBarChart', STATS_DATA.top_estilos, 'nome', '#17a2b8');
    renderRanking('artistaBarChart', STATS_DATA.top_artistas, 'nome', '#007bff');
    renderRanking('artistaFaixasBarChart', STATS_DATA.top_artistas_faixas, 'nome', '#dc3545');
    renderRanking('gravadoraBarChart', STATS_DATA.top_gravadoras, 'nome', '#6f42c1');
});