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
 * Draws a vertical guide line at the hovered x position, alongside Chart.js's
 * own default tooltip. Opt in via `options.plugins.crosshair` (chartTile
 * enables it automatically for line charts) - bar/doughnut charts don't get
 * it, since a vertical line across discrete bars/slices isn't meaningful.
 */
const crosshairPlugin = {
    id: 'crosshair',
    afterEvent(chart, args) {
        if (!chart.config.options?.plugins?.crosshair) {
            return;
        }

        if (args.event.type === 'mousemove') {
            const points = chart.getElementsAtEventForMode(args.event, 'index', { intersect: false }, false);
            chart.$crosshairX = points.length ? points[0].element.x : null;
        } else if (args.event.type === 'mouseout') {
            chart.$crosshairX = null;
        }

        args.changed = true;
    },
    afterDraw(chart) {
        if (!chart.config.options?.plugins?.crosshair || chart.$crosshairX == null) {
            return;
        }

        const { ctx, chartArea } = chart;
        ctx.save();
        ctx.beginPath();
        ctx.moveTo(chart.$crosshairX, chartArea.top);
        ctx.lineTo(chart.$crosshairX, chartArea.bottom);
        ctx.lineWidth = 1;
        ctx.setLineDash([4, 4]);
        ctx.strokeStyle = 'rgba(100, 116, 139, 0.5)';
        ctx.stroke();
        ctx.restore();
    },
};

Chart.register(crosshairPlugin);

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
                        crosshair: type === 'line' ? {} : undefined,
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

/**
 * The listing page's photo display and full-screen viewer, Facebook
 * Marketplace style. Lives entirely in Alpine inside a wire:ignore
 * wrapper (see listing-show.blade.php) - a Livewire re-render anywhere
 * else on the page (reserving, messaging, choosing a buyer) must never
 * reset which photo is showing or silently close an open viewer, which
 * only wire:ignore reliably guarantees.
 */
function listingGallery({ images }) {
    return {
        images,
        activeIndex: 0,
        viewerOpen: false,
        zoomed: false,
        touch: { x: 0, y: 0, distance: 0, lastTap: 0 },
        triggerEl: null,

        get current() {
            return this.images[this.activeIndex] ?? null;
        },

        show(index) {
            if (index < 0 || index >= this.images.length) {
                return;
            }

            this.activeIndex = index;
            this.zoomed = false;
        },

        prev() {
            this.show((this.activeIndex - 1 + this.images.length) % this.images.length);
        },

        next() {
            this.show((this.activeIndex + 1) % this.images.length);
        },

        openViewer(index) {
            this.triggerEl = document.activeElement;
            this.show(index);
            this.viewerOpen = true;
            document.documentElement.style.overflow = 'hidden';
            this.$nextTick(() => this.$refs.viewerClose?.focus());
        },

        closeViewer() {
            this.viewerOpen = false;
            this.zoomed = false;
            document.documentElement.style.overflow = '';
            this.triggerEl?.focus?.();
        },

        toggleZoom() {
            this.zoomed = !this.zoomed;
        },

        /** Keeps Tab cycling inside the viewer instead of leaking out to the page behind it. */
        trapFocus(event) {
            const focusable = this.$refs.viewer?.querySelectorAll('button, [href], [tabindex]:not([tabindex="-1"])');
            if (!focusable || focusable.length === 0) {
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },

        onTouchStart(event) {
            if (event.touches.length === 2) {
                this.touch.distance = this.pinchDistance(event.touches);

                return;
            }

            this.touch.x = event.touches[0].clientX;
            this.touch.y = event.touches[0].clientY;
        },

        onTouchMove(event) {
            if (event.touches.length !== 2 || !this.touch.distance) {
                return;
            }

            const distance = this.pinchDistance(event.touches);

            if (distance - this.touch.distance > 40) {
                this.zoomed = true;
            } else if (this.touch.distance - distance > 40) {
                this.zoomed = false;
            }
        },

        onTouchEnd(event) {
            if (this.zoomed || event.changedTouches.length !== 1) {
                return;
            }

            const deltaX = event.changedTouches[0].clientX - this.touch.x;
            const deltaY = event.changedTouches[0].clientY - this.touch.y;

            if (Math.abs(deltaX) > 50 && Math.abs(deltaX) > Math.abs(deltaY) * 1.5) {
                deltaX < 0 ? this.next() : this.prev();

                return;
            }

            const now = Date.now();

            if (now - this.touch.lastTap < 300) {
                this.toggleZoom();
            }

            this.touch.lastTap = now;
        },

        pinchDistance(touches) {
            const dx = touches[0].clientX - touches[1].clientX;
            const dy = touches[0].clientY - touches[1].clientY;

            return Math.sqrt((dx * dx) + (dy * dy));
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('chartTile', chartTile);
    window.Alpine.data('countUp', countUp);
    window.Alpine.data('listingGallery', listingGallery);
});
