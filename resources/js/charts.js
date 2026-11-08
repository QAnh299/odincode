
import { Chart, registerables } from "chart.js";

Chart.register(...registerables);

const locale = document.documentElement.lang === "en" ? "en-US" : "vi-VN";
const ink = "#1f2937";
const muted = "#6b7280";
const grid = "#eef1f4";


Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
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

    if (spec.type === "scatter") {
        return scatterConfig(spec);
    }

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
                // s.dash: đường tham chiếu (vd. chỉ tiêu) – nét đứt, không chấm điểm
                borderDash: s.dash ? [6, 4] : [],
                tension: s.dash ? 0 : 0.25,
                pointRadius: s.dash || spec.labels.length > 20 ? 0 : 3,
                pointHoverRadius: 5,
                pointBackgroundColor: s.color,
                pointBorderColor: "#fff",
                pointBorderWidth: 2,
            };
        }
        return {
            label: s.label,
            data: s.data,
            // s.colors: màu riêng từng cột (vd. theo nguồn); s.counts / s.valueLabels / s.labelColor: cho tooltip & nhãn giá trị
            backgroundColor: s.colors || s.color,
            hoverBackgroundColor: s.colors || s.color,
            counts: s.counts,
            valueLabels: s.valueLabels,
            labelColor: s.labelColor,
            borderColor: "#fff",
            borderWidth: stacked ? { [horizontal ? "left" : "bottom"]: 2 } : 0,
            borderRadius: 0,
            borderSkipped: "start",
            maxBarThickness: 36,
            categoryPercentage: 0.7,
            barPercentage: 0.9,
        };
    });

    const tooltip = {
        backgroundColor: "#111827",
        padding: 10,
        cornerRadius: 0,
        boxPadding: 4,
        usePointStyle: true,
        callbacks: {
            label(ctx) {
                const raw = round ? ctx.raw : (horizontal ? ctx.parsed.x : ctx.parsed.y);
                let text = `${round ? ctx.label : ctx.dataset.label}: ${format(unit, raw)}`;
                if (ctx.dataset.counts) {
                    text += ` (${format("count", ctx.dataset.counts[ctx.dataIndex])})`;
                }
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
    // spec.legend = "top": chú thích phía trên, canh trái
    const legend = {
        display: round || spec.series.length > 1,
        position: spec.legend || (round ? "right" : "bottom"),
        align: spec.legend === "top" ? "start" : "center",
        labels: { usePointStyle: true, pointStyle: "rectRounded", boxWidth: 10, boxHeight: 10, color: ink, padding: 14 },
    };

    const plugins = [];
    if (spec.dataLabels) {
        plugins.push(valueLabelPlugin(spec, horizontal));
    }
    if (spec.refLine) {
        plugins.push(refLinePlugin(spec.refLine, horizontal));
    }

    if (round) {
        return {
            type: spec.type,
            data: { labels: spec.labels, datasets },
            plugins,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: spec.type === "doughnut" ? spec.cutout || "62%" : 0,
                plugins: { legend, tooltip },
            },
        };
    }

    // spec.divisor + spec.suffix: trục tiền rút gọn (vd. 0,5 tỷ); spec.max: trục cố định (vd. 100%)
    const tick = spec.divisor
        ? (v) => (v / spec.divisor).toLocaleString(locale, { maximumFractionDigits: 1 }) + (spec.suffix || "")
        : (v) => format(unit, v, true);
    const valueAxis = {
        beginAtZero: true,
        stacked,
        grid: { color: grid },
        border: { display: false },
        ticks: { callback: tick, maxTicksLimit: 6 },
        ...(unit === "percent" && !stacked && !spec.refLine ? { suggestedMax: 100 } : {}),
        ...(spec.max ? { max: spec.max } : {}),
    };
    const categoryAxis = {
        stacked,
        grid: { display: false },
        border: { color: "#d1d5db" },
        ticks: { color: ink, autoSkip: true, maxRotation: 40 },
    };

    // Chừa chỗ cho nhãn ở đầu cột / nhãn vạch tham chiếu
    const endLabels = spec.dataLabels === "end";
    const padding = {
        top: spec.refLine ? 22 : endLabels && !horizontal ? 18 : 0,
        right: endLabels && horizontal ? 64 : 8,
    };

    return {
        type: line ? "line" : "bar",
        data: { labels: spec.labels, datasets },
        plugins,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding },
            indexAxis: horizontal ? "y" : "x",
            interaction: { mode: "index", intersect: false, axis: horizontal ? "y" : "x" },
            scales: horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis },
            plugins: { legend, tooltip },
        },
    };
}

/**
 * Nhãn giá trị vẽ trực tiếp lên biểu đồ (spec.dataLabels):
 *   "percent" - tỷ trọng % giữa mỗi miếng biểu đồ tròn (bỏ miếng < 3%)
 *   "end"     - giá trị ở đầu mỗi cột
 *   "inside"  - giá trị giữa mỗi đoạn cột chồng (bỏ đoạn quá hẹp)
 * Chữ lấy từ dataset.valueLabels nếu có, không thì định dạng theo spec.unit.
 */
function valueLabelPlugin(spec, horizontal) {
    return {
        id: "odinValueLabels",
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            ctx.save();
            ctx.font = `600 12px ${Chart.defaults.font.family}`;

            chart.data.datasets.forEach((ds, di) => {
                const meta = chart.getDatasetMeta(di);
                if (meta.hidden) {
                    return;
                }
                const total = ds.data.reduce((a, b) => a + Number(b || 0), 0);

                meta.data.forEach((el, i) => {
                    const raw = Number(ds.data[i]) || 0;
                    if (!raw || el.hidden) {
                        return;
                    }
                    const text = ds.valueLabels?.[i] ?? format(spec.unit, raw);

                    if (spec.dataLabels === "percent") {
                        const share = (raw * 100) / total;
                        if (share < 3) {
                            return;
                        }
                        const { x, y } = el.tooltipPosition();
                        ctx.fillStyle = "#fff";
                        ctx.textAlign = "center";
                        ctx.textBaseline = "middle";
                        ctx.fillText(`${Math.round(share)}%`, x, y);
                        return;
                    }

                    if (spec.dataLabels === "inside") {
                        const size = horizontal ? Math.abs(el.x - el.base) : Math.abs(el.y - el.base);
                        if (ctx.measureText(text).width + 8 > size) {
                            return;
                        }
                        ctx.fillStyle = ds.labelColor || "#fff";
                        ctx.textAlign = "center";
                        ctx.textBaseline = "middle";
                        ctx.fillText(text, horizontal ? (el.x + el.base) / 2 : el.x, horizontal ? el.y : (el.y + el.base) / 2);
                        return;
                    }

                    ctx.fillStyle = ink;
                    ctx.textAlign = horizontal ? "left" : "center";
                    ctx.textBaseline = horizontal ? "middle" : "bottom";
                    ctx.fillText(text, horizontal ? el.x + 6 : el.x, horizontal ? el.y : el.y - 4);
                });
            });

            ctx.restore();
        },
    };
}

/**
 * Vạch tham chiếu nét đứt (vd. trung bình) trên trục giá trị: spec.refLine = { value, label }.
 */
function refLinePlugin(ref, horizontal) {
    return {
        id: "odinRefLine",
        afterDatasetsDraw(chart) {
            const { ctx, chartArea } = chart;
            const scale = chart.scales[horizontal ? "x" : "y"];
            const p = scale.getPixelForValue(Number(ref.value) || 0);

            ctx.save();
            ctx.strokeStyle = "#4b5563";
            ctx.lineWidth = 1.5;
            ctx.setLineDash([4, 4]);
            ctx.beginPath();
            if (horizontal) {
                ctx.moveTo(p, chartArea.top - 4);
                ctx.lineTo(p, chartArea.bottom);
            } else {
                ctx.moveTo(chartArea.left, p);
                ctx.lineTo(chartArea.right, p);
            }
            ctx.stroke();

            if (ref.label) {
                ctx.setLineDash([]);
                ctx.font = `600 12px ${Chart.defaults.font.family}`;
                ctx.fillStyle = ink;
                ctx.textAlign = horizontal ? "center" : "right";
                ctx.textBaseline = "bottom";
                ctx.fillText(ref.label, horizontal ? p : chartArea.right, horizontal ? chartArea.top - 8 : p - 4);
            }
            ctx.restore();
        },
    };
}

function scatterConfig(spec) {
    const axis = (unit, label) => ({
        beginAtZero: true,
        grid: { color: grid },
        border: { display: false },
        ticks: { callback: (v) => format(unit, v, true), maxTicksLimit: 6 },
        title: { display: Boolean(label), text: label, color: muted },
    });

    return {
        type: "scatter",
        data: {
            datasets: spec.series.map((s) => ({
                label: s.label,
                data: s.data,
                backgroundColor: s.color,
                borderColor: "#fff",
                borderWidth: 1.5,
                pointRadius: 7,
                pointHoverRadius: 9,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: axis(spec.xUnit, spec.xLabel),
                y: { ...axis(spec.unit, spec.yLabel), ...(spec.unit === "percent" ? { suggestedMax: 100 } : {}) },
            },
            plugins: {
                legend: {
                    display: spec.series.length > 1,
                    position: "bottom",
                    labels: { usePointStyle: true, boxWidth: 10, boxHeight: 10, color: ink, padding: 14 },
                },
                tooltip: {
                    backgroundColor: "#111827",
                    padding: 10,
                    cornerRadius: 0,
                    boxPadding: 4,
                    usePointStyle: true,
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ${spec.xLabel} ${format(spec.xUnit, ctx.parsed.x)} · `
                            + `${spec.yLabel} ${format(spec.unit, ctx.parsed.y)}`,
                    },
                },
            },
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
