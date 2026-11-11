// Ô chọn khoảng ngày (components/date-range.blade.php): bấm 2 ngày trên cùng một lịch → khoảng [từ, đến].
// Giá trị lấy / ghi thẳng vào 2 thuộc tính Livewire (Y-m-d), nên bộ lọc phía server không phải đổi.

const pad = (n) => String(n).padStart(2, "0");
const toIso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const fromIso = (s) => {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || "");
    return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null;
};
const toDisplay = (s) => {
    const d = fromIso(s);
    return d ? `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}` : "";
};

document.addEventListener("alpine:init", () => {
    window.Alpine.data("dateRange", ({ fromProp, toProp, months }) => ({
        open: false,
        // Ngày bấm đầu tiên, chờ bấm ngày thứ hai
        start: null,
        hover: null,
        // Tháng đang xem (ngày 1 của tháng)
        view: new Date(new Date().getFullYear(), new Date().getMonth(), 1),

        get from() {
            return this.$wire[fromProp] || "";
        },

        get to() {
            return this.$wire[toProp] || "";
        },

        get label() {
            if (!this.from && !this.to) return "";
            if (this.from === this.to) return toDisplay(this.from);
            return `${toDisplay(this.from) || "…"} – ${toDisplay(this.to) || "…"}`;
        },

        get monthLabel() {
            return `${months[this.view.getMonth()]} ${this.view.getFullYear()}`;
        },

        // 6 tuần × 7 ngày, tuần bắt đầu từ thứ Hai
        get days() {
            const first = new Date(this.view);
            const offset = (first.getDay() + 6) % 7;
            const cursor = new Date(first.getFullYear(), first.getMonth(), 1 - offset);
            const today = toIso(new Date());

            return Array.from({ length: 42 }, () => {
                const iso = toIso(cursor);
                const day = { iso, n: cursor.getDate(), out: cursor.getMonth() !== first.getMonth(), today: iso === today };
                cursor.setDate(cursor.getDate() + 1);
                return day;
            });
        },

        toggle() {
            this.open ? this.close() : this.show();
        },

        show() {
            // Mở lịch ở tháng của ngày bắt đầu đang chọn (nếu có)
            const base = fromIso(this.from) || new Date();
            this.view = new Date(base.getFullYear(), base.getMonth(), 1);
            this.start = null;
            this.hover = null;
            this.open = true;
        },

        close() {
            this.open = false;
            this.start = null;
            this.hover = null;
        },

        shift(step) {
            this.view = new Date(this.view.getFullYear(), this.view.getMonth() + step, 1);
        },

        // Khoảng đang hiển thị: đang chọn dở (ngày đầu → ngày rê chuột) hoặc khoảng đã áp dụng
        bounds() {
            if (this.start) {
                const end = this.hover || this.start;
                return end < this.start ? [end, this.start] : [this.start, end];
            }
            return [this.from, this.to];
        },

        dayClass(day) {
            const [a, b] = this.bounds();
            return {
                "is-out": day.out,
                "is-today": day.today,
                "is-start": !!a && day.iso === a,
                "is-end": !!b && day.iso === b,
                "is-in": !!a && !!b && day.iso > a && day.iso < b,
            };
        },

        pick(iso) {
            if (!this.start) {
                this.start = iso;
                return;
            }
            const [a, b] = iso < this.start ? [iso, this.start] : [this.start, iso];
            this.apply(a, b);
        },

        apply(a, b) {
            // Ghi 2 thuộc tính trong cùng một lần gửi lên server
            this.$wire.$set(fromProp, a, false);
            this.$wire.$set(toProp, b);
            this.close();
        },

        preset(key) {
            const now = new Date();
            const y = now.getFullYear();
            const m = now.getMonth();
            const back = (days) => toIso(new Date(y, m, now.getDate() - days));
            const ranges = {
                today: [toIso(now), toIso(now)],
                last7: [back(6), toIso(now)],
                last30: [back(29), toIso(now)],
                thisMonth: [toIso(new Date(y, m, 1)), toIso(new Date(y, m + 1, 0))],
                lastMonth: [toIso(new Date(y, m - 1, 1)), toIso(new Date(y, m, 0))],
            };
            this.apply(...ranges[key]);
        },

        clear() {
            this.apply("", "");
        },
    }));
});
