(function (window) {
    'use strict';

    const ChartFactory = {
        createChart(canvas) {
            if (!canvas) {
                return null;
            }

            const ctx = canvas.getContext('2d');
            return new window.Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Eklenen',
                            data: [],
                            borderColor: '#4CAF50',
                            backgroundColor: 'rgba(76, 175, 80, 0.2)',
                            tension: 0.3,
                            fill: true,
                        },
                        {
                            label: 'Güncellenen',
                            data: [],
                            borderColor: '#2196F3',
                            backgroundColor: 'rgba(33, 150, 243, 0.2)',
                            tension: 0.3,
                            fill: true,
                        },
                        {
                            label: 'Hata',
                            data: [],
                            borderColor: '#F44336',
                            backgroundColor: 'rgba(244, 67, 54, 0.2)',
                            tension: 0.3,
                            fill: true,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                        },
                        tooltip: {
                            enabled: true,
                        },
                    },
                    scales: {
                        x: {
                            display: true,
                        },
                        y: {
                            beginAtZero: true,
                        },
                    },
                },
            });
        },
        updateChart(chart, stats) {
            if (!chart) {
                return;
            }

            const labels = [];
            const created = [];
            const updated = [];
            const errors = [];

            stats.slice().reverse().forEach(function (item) {
                let label = item.stat_period || item.stat_date;
                if (item.stat_date && !item.stat_period) {
                    const parsed = new Date(item.stat_date);
                    if (!isNaN(parsed.getTime())) {
                        label = parsed.toLocaleString();
                    }
                }
                labels.push(label);
                created.push(parseInt(item.created_products, 10) || 0);
                updated.push(parseInt(item.updated_products, 10) || 0);
                errors.push(parseInt(item.error_count, 10) || 0);
            });

            chart.data.labels = labels;
            chart.data.datasets[0].data = created;
            chart.data.datasets[1].data = updated;
            chart.data.datasets[2].data = errors;
            chart.update();
        },
    };

    window.InovapinCharts = ChartFactory;
})(window);
