@php
    use App\Livewire\Reports\LeadSources;
    use App\Models\Lead;

    $t = $this->totals;
    $rows = $this->tableRows;
    $kpis = $this->kpis;
    $period = $this->period;
    $active = $this->activePreset();
    $ls = fn ($key, $replace = []) => __('reports.lead_sources.'.$key, $replace);

    $num = fn ($v) => $this->fmtNum($v);
    $pct = fn ($v) => $this->fmtPct($v);
    $money = fn ($v) => trim(implode(' ', $this->shortMoney($v)));

    // Nhãn ô chọn nguồn: Tất cả (6) / tên nguồn / 3 nguồn
    $picked = array_values(array_intersect(Lead::SOURCES, $sources));
    $sourceLabel = match (count($picked)) {
        0, count(Lead::SOURCES) => $ls('all_sources', ['count' => count(Lead::SOURCES)]),
        1       => $picked[0],
        default => $ls('n_sources', ['count' => count($picked)]),
    };

    // Mức tăng / giảm của thẻ KPI: ▲ 12,4% · ▼ 0,6 điểm
    $delta = function (?array $d) {
        if (! $d) {
            return null;
        }
        $value = number_format(abs($d['value']), 1, ',', '.').($d['unit'] === 'pt' ? ' '.__('reports.lead_sources.delta_points') : '%');

        return [$d['value'] > 0 ? 'up' : ($d['value'] < 0 ? 'down' : 'flat'), ($d['value'] > 0 ? '▲ ' : ($d['value'] < 0 ? '▼ ' : '')).$value];
    };

    // Chiều cao biểu đồ: hàng trên cao bằng nhau theo số nguồn
    $n = count($rows);
    $topHeight = max(300, $n * 52 + 90);
    $outcomeHeight = max(300, $n * 54 + 80);
@endphp

<div class="vc rp ls">
    {{-- Dùng lại giao diện chung .vc-* (voucher.css) + phần riêng báo cáo (report.css); Chart.js nạp ở charts.js --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/lead.css', 'resources/css/report.css', 'resources/js/charts.js'])
    @endassets

    <header class="vc-head">
        <div>
            <h1 class="visually-hidden">{{ $ls('title') }}</h1>
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
    <section class="vc-card ls-filters" aria-label="{{ $ls('filters') }}">
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.from') }}</span>
            <input type="date" wire:model.live="from" class="vc-control" max="{{ $to ?: '' }}">
        </label>
        <label class="vc-field">
            <span class="vc-field__label">{{ __('reports.to') }}</span>
            <input type="date" wire:model.live="to" class="vc-control" min="{{ $from ?: '' }}">
        </label>
        {{-- Kỳ nhanh: chọn → đặt Từ ngày / Đến ngày; tự nhập ngày khác → hiện "Tự chọn" --}}
        <label class="vc-field">
            <span class="vc-field__label">{{ $ls('quick') }}</span>
            <select class="vc-control" wire:key="quick-{{ $active ?? 'custom' }}" wire:change="preset($event.target.value)">
                <option value="" @selected(! $active) disabled>{{ $from || $to ? $ls('preset.custom') : $ls('preset.all') }}</option>
                @foreach (LeadSources::PRESETS as $key)
                    <option value="{{ $key }}" @selected($active === $key)>{{ $ls('preset.'.$key) }}</option>
                @endforeach
            </select>
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
        {{-- Nguồn: chọn nhiều (không chọn = tất cả) --}}
        <div class="vc-field ls-pick" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
            <span class="vc-field__label">{{ $ls('source') }}</span>
            <button type="button" class="vc-control ls-pick__toggle" @click="open = ! open" :aria-expanded="open">
                {{ $sourceLabel }}
            </button>
            <div class="ls-pick__menu" x-show="open" x-cloak x-transition.opacity>
                @foreach (Lead::SOURCES as $option)
                    <label class="ls-pick__item" wire:key="src-opt-{{ md5($option) }}">
                        <input type="checkbox" value="{{ $option }}" wire:model.live="sources">
                        <span class="ls-dot" style="--c: {{ $this->sourceColor($option) }}"></span>
                        {{ $option }}
                    </label>
                @endforeach
                <button type="button" class="ls-pick__all" wire:click="$set('sources', [])" @disabled(! $picked)>
                    {{ $ls('select_all') }}
                </button>
            </div>
        </div>
        <div class="vc-field vc-field--action">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                <x-icon name="refresh" />{{ __('reports.reset') }}
            </button>
        </div>
    </section>

    {{-- ── Thẻ KPI (so với kỳ liền trước + xu hướng theo tháng) ───────── --}}
    <div class="ls-kpis">
        @foreach ($kpis as $k)
            @php($d = $delta($k['delta']))
            <div class="ls-kpi" wire:key="kpi-{{ $k['key'] }}">
                <div class="ls-kpi__head">
                    <span class="ls-kpi__icon"><x-icon :name="$k['icon']" /></span>
                    <span>{{ $ls('kpi.'.$k['key']) }}</span>
                </div>
                <div class="ls-kpi__main">
                    <div class="ls-kpi__value">
                        {{ $k['value'][0] }}@if ($k['value'][1])<small>{{ $k['value'][1] }}</small>@endif
                    </div>
                    @if ($k['spark'])
                        <svg class="ls-spark" viewBox="0 0 120 36" preserveAspectRatio="none" aria-hidden="true">
                            <polygon points="{{ $k['spark']['area'] }}" fill="{{ $k['color'] }}" fill-opacity="0.14" />
                            <polyline points="{{ $k['spark']['line'] }}" fill="none" stroke="{{ $k['color'] }}" stroke-width="2"
                                stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                        </svg>
                    @endif
                </div>
                @if ($d)
                    <div class="ls-kpi__delta is-{{ $d[0] }}" title="{{ $period['compare'] }}">
                        {{ $d[1] }}
                        @if ($loop->first && $period['compare'])
                            <span>{{ $period['compare'] }}</span>
                        @endif
                    </div>
                @endif
                <div class="ls-kpi__hint">{{ $k['hint'] }}</div>
            </div>
        @endforeach
    </div>

    @if (! $rows)
        <section class="vc-card rp-empty">{{ __('reports.no_data') }}</section>
    @else
        @php($charts = $this->charts)

        {{-- ── Cơ cấu · tỷ lệ chốt · doanh thu theo nguồn ──────────────── --}}
        <div class="ls-row ls-row--3">
            @foreach (['share', 'win_rate', 'revenue'] as $key)
                @include('livewire.reports.partials.chart-card', ['id' => 'ls-'.$key, 'spec' => $charts[$key],
                    'title' => $ls('chart.'.$key), 'sub' => $ls('chart.'.$key.'_sub'), 'height' => $topHeight])
            @endforeach
        </div>

        {{-- ── Lead mới theo tháng · kết quả xử lý ─────────────────────── --}}
        <div class="ls-row ls-row--2">
            @include('livewire.reports.partials.chart-card', ['id' => 'ls-monthly', 'spec' => $charts['monthly'],
                'title' => $ls('chart.monthly'), 'sub' => $ls('chart.monthly_sub'), 'height' => $outcomeHeight])
            @include('livewire.reports.partials.chart-card', ['id' => 'ls-outcome', 'spec' => $charts['outcome'],
                'title' => $ls('chart.outcome'), 'sub' => $ls('chart.outcome_sub'), 'height' => $outcomeHeight])
        </div>

        {{-- ── Bảng tổng hợp theo nguồn ────────────────────────────────── --}}
        <section class="vc-card rp-card">
            <h3 class="rp-card__title">{{ $ls('table.title') }}</h3>
            <p class="rp-card__sub">{{ $ls('table.sub') }}</p>
            <div class="table-responsive">
                <table class="rp-table ls-table">
                    <thead>
                        <tr>
                            <th>{{ $ls('table.source') }}</th>
                            <th class="rp-num">{{ $ls('table.leads') }}</th>
                            <th class="rp-num">{{ $ls('table.share') }}</th>
                            <th class="rp-num">{{ $ls('table.conversion') }}</th>
                            <th class="rp-num">{{ $ls('table.won') }}</th>
                            <th class="rp-num">{{ $ls('table.win_rate') }}</th>
                            <th class="rp-num">{{ $ls('table.revenue') }}</th>
                            <th class="rp-num">{{ $ls('table.per_lead') }}</th>
                            <th>{{ $ls('table.rating') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr wire:key="src-{{ md5($row['dim']) }}">
                                <th scope="row"><span class="ls-dot" style="--c: {{ $row['color'] }}"></span>{{ $row['dim'] }}</th>
                                <td class="rp-num">
                                    <span class="ls-volume">
                                        {{ $num($row['leads']) }}
                                        <span class="ls-volume__track"><span style="width: {{ $row['bar'] }}%"></span></span>
                                    </span>
                                </td>
                                <td class="rp-num">{{ $pct($row['share']) }}</td>
                                <td class="rp-num"><span class="ls-heat" style="{{ $row['heat']['conversion_rate'] }}">{{ $pct($row['conversion_rate']) }}</span></td>
                                <td class="rp-num">{{ $num($row['won']) }}</td>
                                <td class="rp-num"><span class="ls-heat" style="{{ $row['heat']['win_rate'] }}">{{ $pct($row['win_rate']) }}</span></td>
                                <td class="rp-num">{{ $money($row['revenue']) }}</td>
                                <td class="rp-num"><span class="ls-heat" style="{{ $row['heat']['revenue_per_lead'] }}">{{ $money($row['revenue_per_lead']) }}</span></td>
                                <td>
                                    <span class="ls-badge ls-badge--{{ $row['rating'] }}">
                                        @if ($row['rating'] === 'expand')▲ @endif{{ $ls('rating.'.$row['rating']) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">{{ $ls('table.total') }}</th>
                            <td class="rp-num">{{ $num($t['leads']) }}</td>
                            <td class="rp-num">100%</td>
                            <td class="rp-num"><span class="ls-heat">{{ $pct($t['conversion_rate']) }}</span></td>
                            <td class="rp-num">{{ $num($t['won']) }}</td>
                            <td class="rp-num"><span class="ls-heat">{{ $pct($t['win_rate']) }}</span></td>
                            <td class="rp-num">{{ $money($t['revenue']) }}</td>
                            <td class="rp-num"><span class="ls-heat">{{ $money($t['revenue_per_lead']) }}</span></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="ls-note">{{ $ls('table.rating_note', ['min' => LeadSources::MIN_LEADS]) }}</p>
        </section>
    @endif

    {{-- Popup xuất Excel: chọn kỳ, tiêu chí, sheet, cột, xem trước --}}
    @if ($this->canExport)
        @include('livewire.reports.partials.report-export')
    @endif
</div>
