export function bootAdminDashboardCharts() {
    const root = document.querySelector('[data-admin-dashboard-charts]');
    if (!root || root.dataset.chartsBooted === '1') {
        return;
    }

    const payload = root.dataset.charts;
    if (!payload) {
        return;
    }

    let charts;
    try {
        charts = JSON.parse(payload);
    } catch {
        return;
    }

    if (!Array.isArray(charts) || charts.length === 0) {
        return;
    }

    root.dataset.chartsBooted = '1';

    import('chart.js/auto').then(({ default: Chart }) => {
        const style = getComputedStyle(document.documentElement);
        const gridColor = style.getPropertyValue('--color-outline-variant').trim() || '#e2e8f0';
        const textColor = style.getPropertyValue('--color-on-surface-variant').trim() || '#64748b';

        charts.forEach((chartConfig) => {
            const canvas = document.getElementById(chartConfig.id);
            if (!canvas) {
                return;
            }

            const format = chartConfig.format
                || (chartConfig.id.includes('revenue') ? 'vnd' : 'number');

            const datasets = chartConfig.datasets.map((dataset) => ({
                label: dataset.label,
                data: dataset.data,
                borderColor: dataset.color,
                backgroundColor: chartConfig.type === 'bar'
                    ? `${dataset.color}33`
                    : `${dataset.color}1a`,
                borderWidth: chartConfig.type === 'line' ? 2 : 0,
                fill: chartConfig.type === 'line',
                tension: 0.35,
                pointRadius: chartConfig.type === 'line' ? 0 : undefined,
                pointHoverRadius: chartConfig.type === 'line' ? 4 : undefined,
                borderRadius: chartConfig.type === 'bar' ? 6 : 0,
            }));

            new Chart(canvas, {
                type: chartConfig.type,
                data: {
                    labels: chartConfig.labels,
                    datasets,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: textColor,
                                boxWidth: 12,
                                boxHeight: 12,
                                usePointStyle: true,
                            },
                        },
                        tooltip: {
                            callbacks: {
                                label(context) {
                                    const value = context.parsed.y ?? 0;
                                    return `${context.dataset.label}: ${formatValue(value, format)}`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: textColor,
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 8,
                            },
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: gridColor },
                            ticks: {
                                color: textColor,
                                callback(value) {
                                    return formatAxis(Number(value), format);
                                },
                            },
                        },
                    },
                },
            });
        });
    });
}

function formatNumber(value) {
    return new Intl.NumberFormat('vi-VN').format(value);
}

function formatValue(value, format) {
    if (format === 'vnd') {
        return `${formatNumber(value)}₫`;
    }
    if (format === 'percent') {
        return `${formatNumber(value)}%`;
    }

    return formatNumber(value);
}

function formatAxis(value, format) {
    if (format === 'vnd') {
        return formatCompactVnd(value);
    }
    if (format === 'percent') {
        return `${value}%`;
    }

    return formatNumber(value);
}

function formatCompactVnd(value) {
    if (value >= 1_000_000_000) {
        return `${(value / 1_000_000_000).toFixed(1)} tỷ`;
    }
    if (value >= 1_000_000) {
        return `${(value / 1_000_000).toFixed(0)} tr`;
    }

    return formatNumber(value);
}

bootAdminDashboardCharts();
