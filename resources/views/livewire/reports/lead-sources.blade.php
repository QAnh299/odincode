@php
    use App\Models\Lead;

    $t = $this->totals;
    $rows = $this->bySource;
    $top = $this->highlights;
    $charts = $this->charts;
    $ls = fn ($key, $replace = []) => __('reports.lead_sources.'.$key, $replace);

    $money = fn ($v) => number_format((float) $v, 0, ',', '.').' ₫';
    $num = fn ($v) => number_format((float) $v, 0, ',', '.');
    $pct = fn ($v) => number_format((float) $v, 1, ',', '.').'%';

    // Tiền rút gọn cho thẻ KPI: 1,39 tỷ ₫ / 390,6 triệu ₫
    $short = function ($v) use ($money) {
        $v = (float) $v;
        foreach ([[1e9, 'billion'], [1e6, 'million']] as [$base, $unit]) {
            if (abs($v) >= $base) {
                return number_format($v / $base, 2, ',', '.').' '.__('reports.money_'.$unit).' ₫';
            }
        }

        return $money($v);
    };

    // Thẻ KPI: [khoá, icon, số chính, chú thích]
    $kpis = [
        ['leads', 'layers', $num($t['leads']), $ls('kpi.leads_hint', ['count' => count($rows)])],
        ['top_leads', 'users', $top['leads']['dim'] ?? '—',
            $top['leads'] ? $ls('kpi.top_leads_hint', ['count' => $num($top['leads']['leads'])]) : ''],
        ['top_win', 'check-circle', $top['win']['dim'] ?? '—',
            $top['win'] ? $ls('kpi.top_win_hint', ['rate' => $pct($top['win']['win_rate']), 'avg' => $pct($t['win_rate'])]) : ''],
        ['top_revenue', 'cash', $top['revenue']['dim'] ?? '—',
            $top['revenue'] ? $short($top['revenue']['revenue']) : $ls('kpi.no_revenue')],
    ];

    // Chiều cao biểu đồ thanh ngang theo số nguồn
    $height = fn (array $spec) => max(200, count($spec['labels']) * 22 * count($spec['series']) + 80);
@endphp

<div class="vc rp">
    {{-- Dùng lại giao diện chung .vc-* (voucher.css) + phần riêng báo cáo (report.css); Chart.js nạp ở charts.js --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/lead.css', 'resources/css/report.css', 'resources/js/charts.js'])
    @endassets

    <header class="vc-head">
        <div>
            <h1 class="vc-head__title">{{ $ls('title') }}</h1>
            <p class="vc-head__sub">{{ $ls('subtitle') }}</p>
        </div>
        <div class="rp-head__actions">
            <span class="vc-results__loading" wire:loading.delay wire:target.except="export">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                {{ __('reports.loading') }}
            </span>
            {{-- Xuất báo cáo tổng hợp dạng văn bản (Giám đốc, Sale Admin) --}}
            @if ($this->canExport)
                <button type="button" class="vc-btn vc-btn--primary" wire:click="openExport">
                    <x-icon name="file" />
                    {{ $ls('export.button') }}
                </button>
            @endif
        </div>
    </header>

    {{-- ── Bộ lọc ──────────────────────────────────────────────────────── --}}
    <section class="vc-card rp-filters" aria-label="{{ $ls('filters') }}">
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.from') }}</span>
            <input type="date" wire:model.live="from" class="vc-control" max="{{ $to ?: '' }}">
        </label>
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.to') }}</span>
            <input type="date" wire:model.live="to" class="vc-control" min="{{ $from ?: '' }}">
        </label>
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.branch') }}</span>
            <select wire:model.live="branch" class="vc-control">
                <option value="">{{ __('reports.all_branches') }}</option>
                @foreach ($this->branches as $option)
                    <option value="{{ $option->branch_id }}">{{ $option->branch_name }}</option>
                @endforeach
            </select>
        </label>
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.method') }}</span>
            <select wire:model.live="method" class="vc-control">
                <option value="">{{ __('reports.all') }}</option>
                @foreach (Lead::CONTACT_METHODS as $option)
                    <option value="{{ $option }}">{{ __('leads.method.'.$option) }}</option>
                @endforeach
            </select>
        </label>
        <div class="vc-field vc-field--action">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                <x-icon name="refresh" />{{ __('reports.reset') }}
            </button>
        </div>
    </section>

    {{-- ── Thẻ KPI ─────────────────────────────────────────────────────── --}}
    <div class="rp-kpis rp-kpis--fit" style="--n: {{ count($kpis) }}">
        @foreach ($kpis as [$key, $icon, $value, $hint])
            <div class="rp-stat">
                <div class="rp-stat__head">
                    <span class="rp-stat__icon"><x-icon :name="$icon" /></span>
                    <span>{{ $ls('kpi.'.$key) }}</span>
                </div>
                <div class="rp-stat__value rp-stat__value--fit">{{ $value }}</div>
                <div class="rp-stat__hint">{{ $hint }}</div>
            </div>
        @endforeach
    </div>

    @if (! $rows)
        <section class="vc-card rp-empty">{{ __('reports.no_data') }}</section>
    @else
        {{-- ── 4 biểu đồ theo nguồn ────────────────────────────────────── --}}
        <div class="rp-grid">
            @foreach ($charts as $key => $spec)
                @include('livewire.reports.partials.chart-card', ['id' => 'ls-'.$key, 'spec' => $spec,
                    'title' => $ls('chart.'.$key), 'sub' => $ls('chart.'.$key.'_sub'), 'height' => $height($spec)])
            @endforeach
        </div>

        {{-- ── Bảng tóm tắt theo nguồn ─────────────────────────────────── --}}
        <section class="vc-card rp-card">
            <h3 class="rp-card__title">{{ $ls('table.title') }}</h3>
            <p class="rp-card__sub">{{ $ls('table.sub') }}</p>
            <div class="table-responsive">
                <table class="rp-table">
                    <thead>
                        <tr>
                            <th>{{ $ls('table.source') }}</th>
                            @foreach (['leads', 'won', 'win_rate', 'revenue', 'revenue_per_lead'] as $m)
                                <th class="rp-num">{{ __('reports.metric.'.$m) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr wire:key="src-{{ md5($row['dim']) }}">
                                <th scope="row">{{ $row['dim'] }}</th>
                                <td class="rp-num">{{ $num($row['leads']) }}</td>
                                <td class="rp-num">{{ $num($row['won']) }}</td>
                                <td class="rp-num">
                                    <span class="rp-bar" style="--w: {{ min(100, $row['win_rate']) }}%">{{ $pct($row['win_rate']) }}</span>
                                </td>
                                <td class="rp-num">{{ $money($row['revenue']) }}</td>
                                <td class="rp-num">{{ $money($row['revenue_per_lead']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">{{ $ls('table.total') }}</th>
                            <td class="rp-num">{{ $num($t['leads']) }}</td>
                            <td class="rp-num">{{ $num($t['won']) }}</td>
                            <td class="rp-num">{{ $pct($t['win_rate']) }}</td>
                            <td class="rp-num">{{ $money($t['revenue']) }}</td>
                            <td class="rp-num">{{ $money($t['revenue_per_lead']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    @endif

    {{-- Popup xuất Excel: chọn kỳ, tiêu chí, sheet, cột, xem trước --}}
    @if ($this->canExport)
        @include('livewire.reports.partials.report-export')
    @endif
</div>
