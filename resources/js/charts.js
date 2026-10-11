// Vẽ biểu đồ cho trang báo cáo bằng Chart.js.
// Dùng: <div x-data="odinChart(@js($spec))"><canvas x-ref="canvas"></canvas></div>
// spec (dựng sẵn ở PHP): { type, unit, labels, series: [{ label, data, color | colors }] }
//   type: bar | bar_h | stacked | stacked_h | line | area | pie | doughnut
//   unit: count | percent | money
// Đổi dữ liệu → đổi wire:key của khối chứa để Alpine dựng lại biểu đồ.

import { Chart, registerables } from "chart.js";

Chart.register(...registerables);

const locale = document.documentElement.lang === "en" ? "en-US" : "vi-VN";
const ink = "#1f2937";
const muted = "#6b7280";
const grid = "#eef1f4";

Chart.defaults.font.family = "'Be Vietnam Pro', system-ui, sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = muted;

function format(unit, value, compact = false) {
    const n = Number(value) || 0;
    if (unit === "percent") {
        return n.toLocaleString(locale, { maximumFractionDigits: 1 }) + "%";
    }
    if (unit === "money") {
        return (
            n.toLocaleString(locale, compact
                ? { notation: "compact", maximumFractionDigits: 1 }
                : { maximumFractionDigits: 0 }) + " ₫"
        );
    }
    return n.toLocaleString(locale, { maximumFractionDigits: 0 });
}

function buildConfig(spec) {
    const round = spec.type === "pie" || spec.type === "doughnut";
    const horizontal = spec.type === "bar_h" || spec.type === "stacked_h";
    const stacked = spec.type === "stacked" || spec.type === "stacked_h";
    const line = spec.type === "line" || spec.type === "area";
    const unit = spec.unit;

    const datasets = spec.series.map((s) => {
        if (round) {
            return {
                label: s.label,
                data: s.data,
                backgroundColor: s.colors,
                borderColor: "#fff",
                borderWidth: 2,
                hoverOffset: 6,
            };
        }
        if (line) {
            return {
                label: s.label,
                data: s.data,
                borderColor: s.color,
                backgroundColor: s.color + (spec.type === "area" ? "33" : "ff"),
                fill: spec.type === "area" ? "origin" : false,
                borderWidth: 2,
                tension: 0.25,
                pointRadius: spec.labels.length > 20 ? 0 : 3,
                pointHoverRadius: 5,
                pointBackgroundColor: s.color,
                pointBorderColor: "#fff",
                pointBorderWidth: 2,
            };
        }
        return {
            label: s.label,
            data: s.data,
            backgroundColor: s.color,
            hoverBackgroundColor: s.color,
            borderColor: "#fff",
            borderWidth: stacked ? { [horizontal ? "left" : "bottom"]: 2 } : 0,
            borderRadius: stacked ? 0 : 4,
            borderSkipped: "start",
            maxBarThickness: 36,
            categoryPercentage: 0.7,
            barPercentage: 0.9,
        };
    });

    const tooltip = {
        backgroundColor: "#111827",
        padding: 10,
        cornerRadius: 8,
        boxPadding: 4,
        usePointStyle: true,
        callbacks: {
            label(ctx) {
                const raw = round ? ctx.raw : (horizontal ? ctx.parsed.x : ctx.parsed.y);
                let text = `${round ? ctx.label : ctx.dataset.label}: ${format(unit, raw)}`;
                if (round) {
                    const total = ctx.dataset.data.reduce((a, b) => a + Number(b || 0), 0);
                    if (total) {
                        text += ` (${format("percent", (raw * 100) / total)})`;
                    }
                }
                return text;
            },
        },
    };

    // Chú thích chỉ hiện khi có từ 2 dãy trở lên (hoặc biểu đồ tròn)
    const legend = {
        display: round || spec.series.length > 1,
        position: round ? "right" : "bottom",
        labels: { usePointStyle: true, pointStyle: "rectRounded", boxWidth: 10, boxHeight: 10, color: ink, padding: 14 },
    };

    if (round) {
        return {
            type: spec.type,
            data: { labels: spec.labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: spec.type === "doughnut" ? "62%" : 0,
                plugins: { legend, tooltip },
            },
        };
    }

    const valueAxis = {
        beginAtZero: true,
        stacked,
        grid: { color: grid },
        border: { display: false },
        ticks: { callback: (v) => format(unit, v, true), maxTicksLimit: 6 },
        ...(unit === "percent" && !stacked ? { suggestedMax: 100 } : {}),
    };
    const categoryAxis = {
        stacked,
        grid: { display: false },
        border: { color: "#d1d5db" },
        ticks: { color: ink, autoSkip: true, maxRotation: 40 },
    };

    return {
        type: line ? "line" : "bar",
        data: { labels: spec.labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: horizontal ? "y" : "x",
            interaction: { mode: "index", intersect: false, axis: horizontal ? "y" : "x" },
            scales: horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis },
            plugins: { legend, tooltip },
        },
    };
}

document.addEventListener("alpine:init", () => {
    window.Alpine.data("odinChart", (spec) => {
        // Giữ instance ngoài dữ liệu reactive của Alpine (proxy làm hỏng Chart.js)
        let chart = null;

        return {
            init() {
                chart = new Chart(this.$refs.canvas, buildConfig(spec));
            },
            destroy() {
                chart?.destroy();
                chart = null;
            },
        };
    });
});
