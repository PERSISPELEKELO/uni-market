import Chart from 'chart.js/auto';

/**
 * Draws the total in the centre of a doughnut chart. Opt in per-chart via
 * `options.plugins.centerText.text`, so bar/line charts are never affected.
 */
const centerTextPlugin = {
    id: 'centerText',
    afterDraw(chart) {
        const config = chart.config.options?.plugins?.centerText;
        if (!config?.text) {
            return;
        }

        const { ctx, chartArea } = chart;
        const centerX = (chartArea.left + chartArea.right) / 2;
        const centerY = (chartArea.top + chartArea.bottom) / 2;

        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.font = '600 1.1rem "Google Sans", ui-sans-serif, system-ui, sans-serif';
        ctx.fillStyle = config.color || '#0f172a';
        ctx.fillText(config.text, centerX, centerY - (config.subtext ? 10 : 0));

        if (config.subtext) {
            ctx.font = '400 0.7rem "Google Sans", ui-sans-serif, system-ui, sans-serif';
            ctx.fillStyle = '#64748b';
            ctx.fillText(config.subtext, centerX, centerY + 12);
        }

        ctx.restore();
    },
};

Chart.register(centerTextPlugin);

/**
 * Renders one Chart.js chart inside a Livewire-managed page. The canvas's
 * wrapper carries `wire:ignore` so Livewire's own DOM morphing never
 * touches it; all data updates instead flow through a single dispatched
 * browser event (see MyActivity/MarketInsights's `charts-updated` dispatch)
 * so the server (where every privacy rule lives) stays the only source of
 * truth for what a chart is allowed to show.
 */
function chartTile({ chartId, type, data, options, clickDimension }) {
    return {
        chart: null,

        init() {
            const canvas = this.$refs.canvas;
            const { centerText, ...chartData } = data;

            this.chart = new Chart(canvas, {
                type: type === 'bar-line' ? 'bar' : type,
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 500 },
                    plugins: {
                        centerText: centerText ? { text: centerText } : undefined,
                        legend: { display: (chartData.datasets || []).length > 1 },
                        tooltip: {
                            callbacks: {
                                label(context) {
                                    const value = context.parsed.y ?? context.parsed ?? context.raw;
                                    const total = context.dataset.data.reduce((sum, v) => sum + (Number(v) || 0), 0);
                                    const pct = total > 0 ? ` (${((value / total) * 100).toFixed(1)}%)` : '';
                                    return `${context.dataset.label ?? context.label}: ${value}${context.chart.config.type === 'doughnut' ? pct : ''}`;
                                },
                            },
                        },
                    },
                    onHover: (event, elements) => {
                        event.native.target.style.cursor = clickDimension && elements.length ? 'pointer' : 'default';
                    },
                    ...options,
                },
            });

            if (clickDimension) {
                canvas.onclick = (event) => {
                    const points = this.chart.getElementsAtEventForMode(event, 'nearest', { intersect: true }, true);
                    if (points.length === 0) {
                        return;
                    }

                    const label = this.chart.data.labels[points[0].index];
                    this.$wire.applyChartFilter(clickDimension, label);
                };
            }

            window.addEventListener('charts-updated', (event) => {
                const fresh = event.detail.charts?.[chartId];
                if (!fresh) {
                    return;
                }

                const { centerText: freshCenterText, ...freshData } = fresh;
                this.chart.data = freshData;
                if (this.chart.options.plugins.centerText) {
                    this.chart.options.plugins.centerText.text = freshCenterText;
                }
                this.chart.update();
            });
        },

        destroy() {
            this.chart?.destroy();
        },
    };
}

/**
 * Animates a KPI tile's number counting up from 0 on first paint only - see
 * x-stat-card's docblock for why it deliberately does not re-animate on
 * every later Livewire filter change. prefix/suffix are baked into the same
 * text node as the number (rather than left as separate static Blade text
 * either side of it) so the tile's full text - e.g. "K1,234.00" - is always
 * one contiguous string, matching exactly what the server rendered before
 * Alpine ever ran.
 */
function countUp(target, decimals, prefix = '', suffix = '') {
    const format = (n) => prefix + n.toLocaleString(undefined, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }) + suffix;

    return {
        displayValue: format(0),

        init() {
            const duration = 700;
            const start = performance.now();

            const step = (now) => {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                this.displayValue = format(target * eased);

                if (progress < 1) {
                    requestAnimationFrame(step);
                }
            };

            requestAnimationFrame(step);
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('chartTile', chartTile);
    window.Alpine.data('countUp', countUp);
});
