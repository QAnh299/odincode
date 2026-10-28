@php
    use App\Livewire\Reports\Revenue;
    use App\Models\SalesOrder;
    use App\Services\RevenueReport;

    $tab = $this->activeTab();
    $r = $this->revenueTotals;
    $c = $this->collectionTotals;
    $prev = $this->previous;

    $L = fn (string $key, array $params = []) => __('reports.revenue.'.$key, $params);
    $num = fn ($v) => number_format((float) $v, 0, ',', '.');
    $dec = fn ($v) => number_format((float) $v, 1, ',', '.');
    $money = fn ($v) => number_format((float) $v, 0, ',', '.').' ₫';
    $pct = fn ($v) => $v === null ? '—' : $dec($v).'%';
    $date = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';

    // So với kỳ trước: [chữ, class]; $dir = 1 cao là tốt
    $delta = function ($cur, $old, int $dir = 1) use ($prev, $dec, $L) {
        if (! $prev || $old === null) {
            return null;
        }
        if ((float) $old == 0) {
            return $cur > 0 ? [$L('delta_new'), 'up'] : null;
        }
        $change = ($cur - $old) * 100 / $old;
        if (abs($change) < 0.05) {
            return [$L('delta_same'), ''];
        }

        return [($change > 0 ? '▲ ' : '▼ ').$dec(abs($change)).'%', $change * $dir > 0 ? 'up' : 'down'];
    };

    $hasData = $this->hasData();
@endphp

<div class="vc rp">
    {{-- Giao diện chung .vc-* (voucher.css) + phần riêng báo cáo (report.css); Chart.js nạp ở charts.js --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/lead.css', 'resources/css/report.css', 'resources/js/charts.js'])
    @endassets

    <header class="vc-head">
        <div>
            <h1 class="vc-head__title">{{ $L('title') }}</h1>
            <p class="vc-head__sub">{{ $L('subtitle') }} · <strong>{{ $this->scopeLabel() }}</strong></p>
        </div>
        <div class="rp-head__actions">
            <span class="vc-results__loading" wire:loading.delay wire:target.except="export">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                {{ __('reports.loading') }}
            </span>
            {{-- Xuất Excel (Giám đốc, Sale Admin, Kế toán): popup chọn kỳ, sheet, cột, xem trước – partials.report-export --}}
            @if ($this->canExport)
                <button type="button" class="vc-btn vc-btn--primary" wire:click="openExport">
                    <x-icon name="file" />{{ $L('export.button') }}
                </button>
            @endif
        </div>
    </header>

    {{-- ── Bộ lọc ──────────────────────────────────────────────────────── --}}
    <section class="vc-card rp-filters rp-filters--wide" aria-label="{{ $L('filters') }}">
        <label class="vc-field">
            <span class="vc-field__label">{{ $L('from') }}</span>
            <input type="date" wire:model.live="from" class="vc-control" max="{{ $to ?: '' }}">
        </label>
        <label class="vc-field">
            <span class="vc-field__label">{{ $L('to') }}</span>
            <input type="date" wire:model.live="to" class="vc-control" min="{{ $from ?: '' }}">
        </label>
        @if ($this->canFilterEmployee)
            <label class="vc-field">
                <span class="vc-field__label">{{ __('reports.branch') }}</span>
                <select wire:model.live="branch" class="vc-control">
                    <option value="">{{ __('reports.all_branches') }}</option>
                    @foreach ($this->branches as $option)
                        <option value="{{ $option->branch_id }}">{{ $option->branch_name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        @if ($this->canFilterTeam)
            <label class="vc-field">
                <span class="vc-field__label">{{ __('reports.sales_performance.team') }}</span>
                <select wire:model.live="team" class="vc-control">
                    <option value="">{{ __('reports.sales_performance.all_teams') }}</option>
                    @foreach ($this->teamOptions as $option)
                        <option value="{{ $option->team_id }}">{{ $option->team_name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        @if ($this->canFilterEmployee)
            <label class="vc-field">
                <span class="vc-field__label">{{ __('reports.sales_performance.employee') }}</span>
                <select wire:model.live="employee" class="vc-control">
                    <option value="">{{ __('reports.all') }}</option>
                    @foreach ($this->employeeOptions as $option)
                        <option value="{{ $option->employee_id }}">
                            {{ $option->full_name }}{{ $option->isActive() ? '' : ' ('.__('reports.sales_performance.resigned').')' }}
                        </option>
                    @endforeach
                </select>
            </label>
        @endif
        <div class="vc-field vc-field--action">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                <x-icon name="refresh" />{{ __('reports.reset') }}
            </button>
        </div>
    </section>

    {{-- ── Tab ─────────────────────────────────────────────────────────── --}}
    <nav class="rp-tabs" role="tablist">
        @foreach ($this->tabs() as $key)
            <button type="button" role="tab" wire:click="setTab('{{ $key }}')"
                class="rp-tab {{ $tab === $key ? 'is-active' : '' }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}">
                {{ $L('tab.'.$key) }}
            </button>
        @endforeach
    </nav>

    @if (! $hasData)
        <section class="vc-card rp-empty">{{ __('reports.no_data') }}</section>
    @else
        @switch($tab)
            {{-- ══ 1. TỔNG QUAN ════════════════════════════════════════════ --}}
            @case('overview')
                @php
                    $o = $this->overview;
                    $d = $this->debt;
                    $pr = $prev['revenue'] ?? null;
                    $pc = $prev['collection'] ?? null;
                    $kpis = [
                        ['revenue', 'chart-bar', $money($r['revenue']), $L('kpi.revenue_hint', ['count' => $num($r['orders'])]), $delta($r['revenue'], $pr['revenue'] ?? null)],
                        ['collected', 'wallet', $money($c['collected']), $L('kpi.collected_hint', ['count' => $num($c['invoices'])]), $delta($c['collected'], $pc['collected'] ?? null)],
                        ['debt', 'hourglass', $money($d['remaining']), $L('kpi.debt_hint', ['count' => $num($d['orders'])]), null],
                        ['overdue', 'alert', $money($d['overdue']), $L('kpi.overdue_hint', ['amount' => $money($d['uninvoiced'])]), null],
                    ];
                    $t = $o['target'];
                @endphp

                @include('livewire.reports.partials.rv-kpis', ['kpis' => $kpis, 'prevLabel' => $prev['label'] ?? null])

                {{-- Nhận định tự động trong kỳ: cùng lưới và kiểu thẻ với hàng chỉ số phía trên để thẳng hàng --}}
                @if ($this->highlights !== [])
                    <div class="rp-kpis rp-kpis--fit rv-insights" style="--n: {{ count($this->highlights) }}" aria-label="{{ $L('insight.title') }}">
                        @foreach ($this->highlights as $item)
                            <div class="rp-stat rv-insight" wire:key="insight-{{ $item['key'] }}">
                                <div class="rp-stat__head">
                                    <span class="rp-stat__icon"><x-icon :name="$item['icon']" /></span>
                                    <span>{{ $L('insight.'.$item['key'].'_label') }}</span>
                                </div>
                                <div class="rv-insight__value" title="{{ $item['value'] }}">{{ $item['value'] }}</div>
                                <div class="rp-stat__hint">{{ $item['detail'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Việc cần xử lý (tại thời điểm xem) – chỉ các vai trò có việc (Revenue::ROLE_VIEWS); Giám đốc, Sale Admin xem tổng quan --}}
                @if ($this->roleView()['todo'] !== [])
                @php $todo = $this->todo; @endphp
                <section class="vc-card rp-card rv-todo" id="rv-todo">
                    <h3 class="rp-card__title"><x-icon name="alert" />{{ $L('todo.title') }}</h3>
                    <p class="rp-card__sub">{{ $L('todo.sub') }}</p>
                    @if ($todo === [])
                        <p class="rp-empty rp-empty--inline">{{ $L('todo.none') }}</p>
                    @else
                        <div class="rv-todo__groups">
                            @foreach ($todo as $key => $group)
                                <div class="rv-todo__group rv-todo__group--{{ $key }}" wire:key="todo-{{ $key }}">
                                    <div class="rv-todo__head">
                                        <strong>{{ $L('todo.'.$key, ['count' => $num($group['count'])]) }}</strong>
                                        <span>{{ $money($group['amount']) }}</span>
                                    </div>
                                    <p class="rv-todo__hint">{{ $L('todo.'.$key.'_hint', ['days' => $key === 'cash' ? Revenue::STALE_CASH_DAYS : Revenue::STALE_ORDER_DAYS]) }}</p>
                                    <ul class="rv-todo__list">
                                        @foreach ($group['rows'] as $row)
                                            @php $id = $row['invoice_id'] ?? $row['order_id']; @endphp
                                            <li wire:key="todo-{{ $key }}-{{ $id }}">
                                                <a href="{{ isset($row['invoice_id']) ? $this->invoiceUrl($id) : route($this->routePrefix.'.orders.show', $id) }}">{{ $id }}</a>
                                                <span>{{ $row['customer'] }} · {{ $row['employee'] }}</span>
                                                <span class="rp-num">
                                                    @if ($key === 'transfer')
                                                        {{ $L('todo.received', ['received' => $money($row['received']), 'amount' => $money($row['payment_amount'])]) }}
                                                    @else
                                                        {{ $money($row[$key === 'uninvoiced' ? 'uninvoiced' : 'payment_amount']) }}
                                                    @endif
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if ($group['url'] && $group['count'] > count($group['rows']))
                                        <a href="{{ $group['url'] }}" class="rv-todo__more">{{ $L('todo.more', ['count' => $num($group['count'])]) }}</a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
                @endif

                {{-- Chỉ tiêu doanh thu của các tháng trong kỳ (TeamSalesTargets) --}}
                @if ($t)
                    <section class="vc-card rp-card rv-target">
                        <div class="rv-target__head">
                            <h3 class="rp-card__title">{{ $L('target.title', ['months' => $t['months']]) }}</h3>
                            <strong @class(['rv-target__rate', 'is-done' => $t['rate'] >= 100])>{{ $pct($t['rate']) }}</strong>
                        </div>
                        <div class="rv-progress" role="progressbar" aria-valuenow="{{ min(100, $t['rate'] ?? 0) }}" aria-valuemin="0" aria-valuemax="100">
                            <span style="width: {{ min(100, $t['rate'] ?? 0) }}%"></span>
                        </div>
                        <dl class="rv-target__stats">
                            <div><dt>{{ $L('target.actual') }}</dt><dd>{{ $money($t['actual']) }}</dd></div>
                            <div><dt>{{ $L('target.goal') }}</dt><dd>{{ $money($t['target']) }}</dd></div>
                            <div><dt>{{ $L('target.gap') }}</dt><dd>{{ $t['actual'] >= $t['target'] ? $L('target.reached') : $money($t['target'] - $t['actual']) }}</dd></div>
                        </dl>
                        <p class="rp-card__sub rv-target__source">{{ $L('target.source') }}</p>
                    </section>
                @endif

                <div class="rp-grid">
                    @include('livewire.reports.partials.chart-card', ['id' => 'rv-trend', 'spec' => $o['charts']['trend'], 'wide' => true,
                        'title' => $L('overview.trend', ['period' => $L('period.'.$this->period)]), 'sub' => $L('overview.trend_sub')])
                    @include('livewire.reports.partials.chart-card', ['id' => 'rv-methods', 'spec' => $o['charts']['methods'], 'wide' => true,
                        'title' => $L('overview.methods', ['period' => $L('period.'.$this->period)]), 'sub' => $L('overview.methods_sub')])
                </div>
                @break

            {{-- ══ 2. THANH TOÁN ═══════════════════════════════════════════ --}}
            @case('payments')
                @php
                    $p = $this->payments;
                    $kpis = [
                        ['on_time', 'check-circle', $pct($p['on_time_rate']), $L('kpi.on_time_hint', ['count' => $num($c['on_time']), 'total' => $num($c['invoices'])]), null],
                        ['days', 'clock', $p['days']['median'] === null ? '—' : $L('days', ['value' => $dec($p['days']['median'])]),
                            $L('kpi.days_hint', ['count' => $num($p['days']['count']), 'avg' => $p['days']['avg'] === null ? '—' : $dec($p['days']['avg'])]), null],
                        ['transfer', 'wallet', $pct(RevenueReport::pct($c['transfer'], $c['collected'])), $L('kpi.transfer_hint', ['amount' => $money($c['transfer']), 'count' => $num($c['transfer_count'])]), null],
                        ['cash_queue', 'cash', $num($p['cash_queue']['count']), $L('kpi.cash_queue_hint', ['amount' => $money($p['cash_queue']['amount'])]), null],
                        ['transfer_queue', 'alert', $num(count($p['transfers'])), $L('kpi.transfer_queue_hint', ['amount' => $money(array_sum(array_column($p['transfers'], 'payment_amount')))]), null],
                    ];
                @endphp

                @include('livewire.reports.partials.rv-kpis', ['kpis' => $kpis, 'prevLabel' => null])

                <div class="rp-grid">
                    @include('livewire.reports.partials.chart-card', ['id' => 'rv-pay-methods', 'spec' => $p['charts']['methods'], 'height' => 280,
                        'title' => $L('payments.methods'), 'sub' => $L('payments.methods_sub')])
                    @include('livewire.reports.partials.chart-card', ['id' => 'rv-pay-types', 'spec' => $p['charts']['types'], 'height' => 280,
                        'title' => $L('payments.types'), 'sub' => $L('payments.types_sub')])
                </div>

                <section class="vc-card rp-card">
                    <h3 class="rp-card__title">{{ $L('payments.punctuality') }}</h3>
                    <p class="rp-card__sub">{{ $L('payments.punctuality_sub') }}</p>
                    <div class="table-responsive">
                        <table class="rp-table">
                            <thead>
                                <tr>
                                    <th>{{ $L('payments.method') }}</th>
                                    <th class="rp-num">{{ $L('payments.invoices') }}</th>
                                    <th class="rp-num">{{ $L('metric.collected') }}</th>
                                    <th class="rp-num">{{ $L('payments.on_time_rate') }}</th>
                                    <th class="rp-num">{{ $L('payments.late_days') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (['BankTransfer' => 'transfer', 'Cash' => 'cash'] as $code => $key)
                                    @php $row = $p['punctuality'][$code] ?? ['total' => 0, 'on_time' => 0, 'late_days' => null]; @endphp
                                    <tr wire:key="punct-{{ $code }}">
                                        <th scope="row">{{ __('invoices.method.'.$code) }}</th>
                                        <td class="rp-num">{{ $num($row['total']) }}</td>
                                        <td class="rp-num">{{ $money($c[$key]) }}</td>
                                        <td class="rp-num">
                                            @php $rate = RevenueReport::pct($row['on_time'], $row['total']); @endphp
                                            <span class="rp-bar" style="--w: {{ min(100, $rate ?? 0) }}%">{{ $pct($rate) }}</span>
                                        </td>
                                        <td class="rp-num">{{ $row['late_days'] === null ? '—' : $L('days', ['value' => $dec($row['late_days'])]) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="table-responsive rv-types">
                        <table class="rp-table">
                            <thead>
                                <tr>
                                    <th>{{ $L('payments.type') }}</th>
                                    <th class="rp-num">{{ $L('payments.orders') }}</th>
                                    <th class="rp-num">{{ $L('metric.revenue') }}</th>
                                    <th class="rp-num">{{ $L('structure.share') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ([SalesOrder::PAYMENT_FULL, SalesOrder::PAYMENT_INSTALLMENT, 'none'] as $key)
                                    @continue(! isset($p['types'][$key]))
                                    <tr wire:key="type-{{ $key }}">
                                        <th scope="row">{{ $L('payment_type.'.$key) }}</th>
                                        <td class="rp-num">{{ $num($p['types'][$key]['orders']) }}</td>
                                        <td class="rp-num">{{ $money($p['types'][$key]['revenue']) }}</td>
                                        <td class="rp-num">{{ $pct(RevenueReport::pct($p['types'][$key]['revenue'], $r['revenue'])) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
                @break

            {{-- ══ 3. CÔNG NỢ (tại thời điểm xem) ══════════════════════════ --}}
            @case('debt')
                @php
                    $d = $this->debt;
                    $dd = $this->debtDetail;
                    $kpis = [
                        ['debt', 'hourglass', $money($d['remaining']), $L('kpi.debt_hint', ['count' => $num($d['orders'])]), null],
                        ['overdue', 'alert', $money($d['overdue']), $L('kpi.overdue_count', ['count' => $num(array_sum(array_column(array_slice($d['aging'], 1), 'count')))]), null],
                        ['pending', 'clock', $money($d['pending']), $L('kpi.pending_hint', ['count' => $num($d['aging']['not_due']['count'])]), null],
                        ['uninvoiced', 'file', $money($d['uninvoiced']), $L('kpi.uninvoiced_hint'), null],
                    ];
                @endphp

                <p class="rp-hint">{{ $L('debt.note') }}</p>
                @include('livewire.reports.partials.rv-kpis', ['kpis' => $kpis, 'prevLabel' => null])

                <div class="rp-grid">
                    @include('livewire.reports.partials.chart-card', ['id' => 'rv-aging', 'spec' => $dd['chart'], 'height' => 260,
                        'title' => $L('debt.aging'), 'sub' => $L('debt.aging_sub')])

                    <section class="vc-card rp-card">
                        <h3 class="rp-card__title">{{ $L('debt.aging_table') }}</h3>
                        <p class="rp-card__sub">{{ $L('debt.aging_table_sub') }}</p>
                        <table class="rp-table">
                            <thead>
                                <tr>
                                    <th>{{ $L('debt.group') }}</th>
                                    <th class="rp-num">{{ $L('payments.invoices') }}</th>
                                    <th class="rp-num">{{ $L('debt.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($d['aging'] as $key => $row)
                                    <tr wire:key="aging-{{ $key }}">
                                        <th scope="row"><span class="rv-dot" style="--c: {{ Revenue::COLORS[$key] }}"></span>{{ $L('aging.'.$key) }}</th>
                                        <td class="rp-num">{{ $num($row['count']) }}</td>
                                        <td class="rp-num">{{ $money($row['amount']) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="rp-row--total">
                                    <th scope="row">{{ $L('debt.uninvoiced') }}</th>
                                    <td class="rp-num">—</td>
                                    <td class="rp-num">{{ $money($d['uninvoiced']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </div>

                {{-- Bảng chi tiết từng đơn / hóa đơn: Kế toán, Sale Leader, Salesperson (Giám đốc, Sale Admin chỉ xem tổng quan) --}}
                @if ($this->roleView()['debt_lists'])
                {{-- Đơn còn nợ: quá hạn trước, rồi còn lại cao nhất – bấm để xem hóa đơn của đơn --}}
                <section class="vc-card rp-card">
                    <h3 class="rp-card__title">{{ $L('debt.orders') }}</h3>
                    <p class="rp-card__sub">{{ $L('debt.orders_sub', ['count' => Revenue::DEBT_ROWS]) }}</p>
                    @if ($dd['orders'] === [])
                        <p class="rp-empty rp-empty--inline">{{ $L('debt.none') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="rp-table">
                                <thead>
                                    <tr>
                                        <th>{{ $L('debt.order') }}</th>
                                        <th>{{ $L('debt.customer') }}</th>
                                        @if ($this->canFilterEmployee)<th>{{ $L('debt.employee') }}</th>@endif
                                        <th class="rp-num">{{ $L('debt.total') }}</th>
                                        <th class="rp-num">{{ $L('debt.remaining') }}</th>
                                        <th class="rp-num">{{ $L('debt.unpaid') }}</th>
                                        <th class="rp-num">{{ $L('debt.uninvoiced') }}</th>
                                        <th class="rp-num">{{ $L('debt.late') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($dd['orders'] as $row)
                                        <tr wire:key="debt-{{ $row['order_id'] }}">
                                            <th scope="row"><a href="{{ $this->orderInvoicesUrl($row['order_id']) }}" class="vc-mono">{{ $row['order_id'] }}</a></th>
                                            <td>{{ $row['customer'] }}<span class="rp-sub">{{ $row['phone'] }}</span></td>
                                            @if ($this->canFilterEmployee)<td>{{ $row['employee'] }}</td>@endif
                                            <td class="rp-num">{{ $money($row['total_amount']) }}</td>
                                            <td class="rp-num"><strong>{{ $money($row['remaining_amount']) }}</strong></td>
                                            <td class="rp-num">{{ $money($row['unpaid']) }}<span class="rp-sub">{{ $L('debt.invoice_count', ['count' => $row['invoices']]) }}</span></td>
                                            <td class="rp-num">{{ (float) $row['uninvoiced'] > 0 ? $money($row['uninvoiced']) : '—' }}</td>
                                            <td class="rp-num">
                                                @if ($row['late'] > 0)
                                                    <span class="rv-late">{{ $L('late_days', ['days' => $row['late']]) }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="vc-card rp-card">
                    <h3 class="rp-card__title">{{ $L('debt.overdue_invoices') }}</h3>
                    <p class="rp-card__sub">{{ $L('debt.overdue_invoices_sub') }}</p>
                    @if ($dd['invoices'] === [])
                        <p class="rp-empty rp-empty--inline">{{ $L('debt.no_overdue') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="rp-table">
                                <thead>
                                    <tr>
                                        <th>{{ $L('debt.invoice') }}</th>
                                        <th>{{ $L('debt.customer') }}</th>
                                        @if ($this->canFilterEmployee)<th>{{ $L('debt.employee') }}</th>@endif
                                        <th>{{ $L('payments.method') }}</th>
                                        <th class="rp-num">{{ $L('debt.amount') }}</th>
                                        <th class="rp-num">{{ $L('debt.due') }}</th>
                                        <th class="rp-num">{{ $L('debt.late') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($dd['invoices'] as $row)
                                        <tr wire:key="od-{{ $row['invoice_id'] }}">
                                            <th scope="row">
                                                <a href="{{ $this->invoiceUrl($row['invoice_id']) }}" class="vc-mono">{{ $row['invoice_id'] }}</a>
                                                <span class="rp-sub">{{ $row['order_id'] }} · {{ __('invoices.installment', ['number' => $row['payment_installment']]) }}</span>
                                            </th>
                                            <td>{{ $row['customer'] }}<span class="rp-sub">{{ $row['phone'] }}</span></td>
                                            @if ($this->canFilterEmployee)<td>{{ $row['employee'] }}</td>@endif
                                            <td>{{ $row['payment_method'] ? __('invoices.method.'.$row['payment_method']) : '—' }}</td>
                                            <td class="rp-num">{{ $money($row['payment_amount']) }}</td>
                                            <td class="rp-num">{{ $date($row['due_date']) }}</td>
                                            <td class="rp-num"><span class="rv-late">{{ $L('late_days', ['days' => $row['late']]) }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
                @endif
                @break

            {{-- ══ 4. TỶ LỆ THU THEO THÁNG TẠO ĐƠN ═════════════════════════ --}}
            @case('cohort')
                @php $rows = $this->cohort; @endphp
                <section class="vc-card rp-card">
                    <h3 class="rp-card__title">{{ $L('cohort.title') }}</h3>
                    <p class="rp-card__sub">{{ $L('cohort.sub') }}</p>
                    @if ($rows === [])
                        <p class="rp-empty rp-empty--inline">{{ __('reports.no_data') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="rp-table rv-cohort">
                                <thead>
                                    <tr>
                                        <th>{{ $L('cohort.month') }}</th>
                                        <th class="rp-num">{{ $L('payments.orders') }}</th>
                                        <th class="rp-num">{{ $L('metric.revenue') }}</th>
                                        @foreach (Revenue::COHORT_HORIZONS as $n)
                                            <th class="rp-num">{{ $L('cohort.after', ['days' => $n]) }}</th>
                                        @endforeach
                                        <th class="rp-num">{{ $L('cohort.now') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr wire:key="cohort-{{ $row['month'] }}">
                                            <th scope="row">{{ substr($row['month'], 5, 2).'/'.substr($row['month'], 0, 4) }}</th>
                                            <td class="rp-num">{{ $num($row['orders']) }}</td>
                                            <td class="rp-num">{{ $money($row['revenue']) }}</td>
                                            @foreach (array_merge($row['rates'], ['now' => $row['now']]) as $h => $rate)
                                                {{-- Màu nền theo mức thu: càng đậm càng thu được nhiều --}}
                                                <td class="rp-num rv-cohort__cell" @if ($rate !== null) style="--a: {{ round(min(100, $rate) / 100, 2) }}" @endif
                                                    @if ($rate === null) title="{{ $L('cohort.too_new') }}" @endif>{{ $pct($rate) }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
                @break

            {{-- ══ 5. THEO NHÂN VIÊN ═══════════════════════════════════════ --}}
            @case('employees')
                @php $rows = $this->sortedEmployees(); @endphp
                <section class="vc-card rp-card">
                    <h3 class="rp-card__title">{{ $L('staff.title') }}</h3>
                    <p class="rp-card__sub">{{ $L('staff.sub') }}</p>
                    @if ($rows === [])
                        <p class="rp-empty rp-empty--inline">{{ __('reports.no_data') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="rp-table">
                                <thead>
                                    <tr>
                                        <th class="rp-num">#</th>
                                        <th>{{ $L('structure.employee') }}</th>
                                        <th class="rp-num">{{ $L('payments.orders') }}</th>
                                        @foreach (Revenue::EMPLOYEE_SORTS as $col)
                                            <th class="rp-num">
                                                <button type="button" wire:click="sortEmployees('{{ $col }}')" @class(['rv-sort', 'is-active' => $employeeSort === $col])
                                                    aria-pressed="{{ $employeeSort === $col ? 'true' : 'false' }}">
                                                    {{ $L('staff.'.$col) }}{{ $employeeSort === $col ? ($col === 'on_time' ? ' ▲' : ' ▼') : '' }}
                                                </button>
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $i => $row)
                                        <tr wire:key="staff-{{ $row['id'] }}">
                                            <td class="rp-num">{{ $i + 1 }}</td>
                                            <th scope="row">{{ $row['name'] }}</th>
                                            <td class="rp-num">{{ $num($row['orders']) }}</td>
                                            <td class="rp-num">{{ $money($row['collected']) }}</td>
                                            <td class="rp-num">{{ $money($row['revenue']) }}</td>
                                            <td class="rp-num">{{ $row['debt'] > 0 ? $money($row['debt']) : '—' }}</td>
                                            <td class="rp-num">
                                                {{ $pct($row['on_time']) }}<span class="rp-sub">{{ $L('staff.paid_count', ['count' => $row['paid_invoices']]) }}</span>
                                            </td>
                                            <td class="rp-num">
                                                @if ($row['overdue_count'] > 0)
                                                    <a href="{{ $this->listUrl('invoices', ['deadline' => 'overdue', 'employee' => $row['id']]) }}" class="rv-late">{{ $money($row['overdue_amount']) }}</a>
                                                    <span class="rp-sub">{{ $L('debt.invoice_count', ['count' => $row['overdue_count']]) }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
                @break

            {{-- ══ 6. CƠ CẤU DOANH THU ═════════════════════════════════════ --}}
            @case('structure')
                @php
                    $st = $this->structure;
                    $kpis = [
                        ['orders', 'layers', $num($r['orders']), $L('kpi.orders_hint', ['count' => $num($r['completed'])]), $delta($r['orders'], $prev['revenue']['orders'] ?? null)],
                        ['avg_order', 'tag', $money($r['orders'] ? $r['revenue'] / $r['orders'] : 0), $L('kpi.avg_order_hint'), null],
                        ['discount', 'percent', $money($r['discount']), $L('kpi.discount_hint', ['rate' => $pct(RevenueReport::pct($r['discount'], $r['revenue'] + $r['discount']))]), null],
                        ['courses', 'book', $num(count($st['courses'])), $L('kpi.courses_hint'), null],
                    ];
                @endphp

                @include('livewire.reports.partials.rv-kpis', ['kpis' => $kpis, 'prevLabel' => $prev['label'] ?? null])

                <div class="rp-grid">
                    @include('livewire.reports.partials.chart-card', ['id' => 'rv-courses', 'spec' => $st['chart'], 'height' => max(220, count($st['chart']['labels']) * 44 + 40),
                        'title' => $L('structure.courses'), 'sub' => $L('structure.courses_sub')])

                    <section class="vc-card rp-card">
                        <h3 class="rp-card__title">{{ $L('structure.course_table') }}</h3>
                        <p class="rp-card__sub">{{ $L('structure.course_table_sub') }}</p>
                        <table class="rp-table">
                            <thead>
                                <tr>
                                    <th>{{ $L('structure.course') }}</th>
                                    <th class="rp-num">{{ $L('structure.quantity') }}</th>
                                    <th class="rp-num">{{ $L('metric.revenue') }}</th>
                                    <th class="rp-num">{{ $L('structure.share') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($st['courses'] as $row)
                                    <tr wire:key="course-{{ $row['id'] }}">
                                        <th scope="row">{{ $row['name'] }}</th>
                                        <td class="rp-num">{{ $num($row['quantity']) }}</td>
                                        <td class="rp-num">{{ $money($row['revenue']) }}</td>
                                        <td class="rp-num">
                                            @php $share = RevenueReport::pct($row['revenue'], $r['revenue']); @endphp
                                            <span class="rp-bar" style="--w: {{ min(100, $share ?? 0) }}%">{{ $pct($share) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </section>
                </div>

                {{-- Theo chi nhánh / đội / nhân viên: doanh thu (ngày tạo đơn) và thực thu (ngày thanh toán) trong kỳ --}}
                @foreach (['branches' => 'branch', 'teams' => 'team', 'employees' => 'employee'] as $set => $key)
                    @continue($st[$set] === [])
                    <section class="vc-card rp-card">
                        <h3 class="rp-card__title">{{ $L('structure.by_'.$key) }}</h3>
                        <p class="rp-card__sub">{{ $L('structure.by_sub') }}</p>
                        <div class="table-responsive">
                            <table class="rp-table">
                                <thead>
                                    <tr>
                                        <th>{{ $L('structure.'.$key) }}</th>
                                        <th class="rp-num">{{ $L('payments.orders') }}</th>
                                        <th class="rp-num">{{ $L('metric.revenue') }}</th>
                                        <th class="rp-num">{{ $L('structure.share') }}</th>
                                        <th class="rp-num">{{ $L('metric.collected') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($st[$set] as $row)
                                        <tr wire:key="{{ $key }}-{{ $row['id'] ?? 'none' }}">
                                            <th scope="row">{{ $row['name'] ?? $L('structure.no_team') }}</th>
                                            <td class="rp-num">{{ $num($row['orders']) }}</td>
                                            <td class="rp-num">{{ $money($row['revenue']) }}</td>
                                            <td class="rp-num">
                                                @php $share = RevenueReport::pct($row['revenue'], $r['revenue']); @endphp
                                                <span class="rp-bar" style="--w: {{ min(100, $share ?? 0) }}%">{{ $pct($share) }}</span>
                                            </td>
                                            <td class="rp-num">{{ $money($row['collected']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach
                @break
        @endswitch

    @endif

    {{-- Popup xuất Excel: chọn kỳ, tiêu chí, sheet, cột, xem trước (trait ReportExport) --}}
    @if ($this->canExport)
        @include('livewire.reports.partials.report-export')
    @endif
</div>
