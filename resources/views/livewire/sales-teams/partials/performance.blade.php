{{-- Kết quả kinh doanh trên trang chi tiết đội (livewire/sales-teams/show): 4 ô chỉ số tổng quan của đội,
     so với chỉ tiêu tháng và tháng trước. Xem chi tiết (từng thành viên, phễu, xu hướng…) ở báo cáo
     Phân tích hiệu quả Sales – nút "Xem báo cáo chi tiết". Cách tính giống báo cáo đó (SalesPerformanceReport). --}}
@php
    use App\Livewire\SalesTeams\Show;

    $period = $this->period;

    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
    $number = fn ($value) => number_format((int) $value, 0, ',', '.');
    $percent = fn (?float $value) => $value === null ? '—' : rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',').'%';
@endphp

<section class="ed-card ed-perf" aria-labelledby="ed-perf-title">
    <div class="ed-card__head">
        <h2 class="ed-card__title mb-0" id="ed-perf-title">{{ __('employees.perf.title') }}</h2>

        <div class="ed-perf__tools">
            <select wire:model.live="month" class="form-select form-select-sm ed-perf__period" aria-label="{{ __('employees.perf.period') }}">
                @foreach ($this->months as $option)
                    <option value="{{ $loop->first ? '' : $option }}" @selected($period === $option)>
                        {{ $this->periodLabel($option) }}
                    </option>
                @endforeach
                <option value="{{ Show::ALL_TIME }}" @selected($period === Show::ALL_TIME)>{{ __('employees.perf.all_time') }}</option>
            </select>
            @if ($this->reportUrl)
                <a href="{{ $this->reportUrl }}" class="ed-btn" wire:navigate>
                    <x-icon name="trending" />{{ __('employees.perf.open_report') }}
                </a>
            @endif
        </div>
    </div>

    @php
        $perf = $this->performance;
        $prev = $this->previousPerformance;
        $target = $this->target;

        // So với tháng trước: % tăng / giảm (null = không so sánh được)
        $delta = function (string $key) use ($perf, $prev) {
            if ($prev === null) {
                return null;
            }
            $before = (float) $prev[$key];
            $now = (float) $perf[$key];

            return $before > 0 ? (int) round(($now - $before) / $before * 100) : ($now > 0 ? 'new' : null);
        };

        // Ô chỉ số: [key, icon, giá trị hiển thị, khoá chỉ tiêu, dòng phụ]
        $kpis = [
            ['revenue', 'wallet', $money($perf['revenue']), 'revenue',
                __('employees.perf.paid', ['amount' => $money($perf['paid'])])],
            ['won', 'check-circle', $number($perf['won']), null,
                __('employees.perf.win_rate', ['rate' => $percent($perf['close_rate']), 'lost' => $number($perf['lost'])])],
            ['students', 'user-check', $number($perf['students']), 'students', null],
            ['assigned', 'inbox', $number($perf['assigned']), null,
                __('employees.perf.processed', ['rate' => $percent($perf['processed_rate'])])],
        ];
    @endphp

    <div class="ed-kpis" wire:loading.class="is-loading" wire:target="month">
        @foreach ($kpis as [$key, $icon, $display, $targetKey, $hint])
            @php
                $goal = $targetKey ? ($target[$targetKey] ?? null) : null;
                $reached = $goal ? (int) round($perf[$key] / $goal * 100) : null;
                $change = $delta($key);
            @endphp
            <div class="ed-kpi ed-kpi--{{ $key }}" wire:key="kpi-{{ $key }}">
                <div class="ed-kpi__top">
                    <span class="ed-kpi__icon" aria-hidden="true"><x-icon :name="$icon" /></span>
                    <span class="ed-kpi__label">{{ __('employees.perf.kpi.'.$key) }}</span>
                    @if ($change !== null)
                        <span class="ed-delta {{ $change === 'new' || $change > 0 ? 'is-up' : ($change < 0 ? 'is-down' : '') }}"
                            title="{{ $change === 'new' ? __('employees.perf.delta_new') : __('employees.perf.delta', ['value' => ($change > 0 ? '+' : '').$change]) }}">
                            {{ $change === 'new' ? __('employees.perf.delta_new_short') : ($change > 0 ? '+' : '').$change.'%' }}
                        </span>
                    @endif
                </div>

                <span class="ed-kpi__value">{{ $display }}</span>

                @if ($goal)
                    <div class="ed-kpi__target">
                        <div class="ed-bar {{ $reached >= 100 ? 'is-done' : '' }}" role="progressbar"
                            aria-valuenow="{{ min($reached, 100) }}" aria-valuemin="0" aria-valuemax="100"
                            aria-label="{{ __('employees.perf.target_reached', ['rate' => $reached]) }}">
                            <span style="width: {{ min($reached, 100) }}%"></span>
                        </div>
                        <span class="ed-kpi__hint">
                            {{ __('employees.perf.target', [
                                'rate'   => $reached,
                                'target' => $key === 'revenue' ? $money($goal) : $number($goal),
                            ]) }}
                        </span>
                    </div>
                @endif

                @if ($hint)
                    <span class="ed-kpi__hint">{{ $hint }}</span>
                @endif
            </div>
        @endforeach
    </div>

    @if ($prev !== null)
        <p class="ed-note">{{ __('employees.perf.delta_note') }}</p>
    @endif
</section>
