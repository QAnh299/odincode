@php
    use App\Models\Course;
    use App\Models\Quotation;
    use Illuminate\Support\Facades\Lang;

    $course = $this->course;
    $stats = $this->stats;
    $state = $course->state();

    // Màu theo trạng thái (class tiện ích Bootstrap 5.3) – luôn đi kèm chữ, không chỉ dựa vào màu
    $tones = [
        Quotation::STATUS_DRAFT     => 'secondary',
        Quotation::STATUS_CONFIRMED => 'success',
        Quotation::STATUS_REJECTED  => 'danger',
    ];
    $courseTone = $state === Course::STATE_ACTIVE ? 'success' : 'secondary';

    $statusLabel = fn (string $status) => Lang::has('courses.quotation_status.'.$status)
        ? __('courses.quotation_status.'.$status) : $status;
    $toneOf = fn (string $status) => $tones[collect(Quotation::STATUSES)
        ->first(fn ($s) => strcasecmp($s, $status) === 0)] ?? 'secondary';

    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
    $number = fn ($value) => number_format((int) $value, 0, ',', '.');

    // Tỷ lệ chốt: "100%", "66,7%"; chưa có báo giá đã kết thúc thì "—"
    $rate = $stats['close_rate'];
    $rateText = $rate === null ? '—'
        : rtrim(rtrim(number_format($rate, 1, ',', '.'), '0'), ',').'%';

    // Thanh trạng thái: tổng các báo giá có trạng thái hợp lệ (tổng 0 thì thanh rỗng)
    $barTotal = array_sum($stats['by_status']);
    $percentOf = fn (int $count) => $barTotal > 0 ? (int) round($count / $barTotal * 100) : 0;
    $barLabel = collect($stats['by_status'])
        ->map(fn ($count, $status) => $statusLabel($status).': '.$count)
        ->implode(', ');

    $listUrl = route($this->routePrefix.'.courses');
@endphp

<div class="cd">
    {{-- CSS riêng của trang khóa học: resources/css/course.css (chỉ nạp ở trang này) --}}
    @assets
        @vite('resources/css/course.css')
    @endassets

    {{-- ── Đầu trang ────────────────────────────────────────────────── --}}
    <header class="cd-head">
        <a href="{{ $listUrl }}" class="btn btn-sm btn-outline-secondary cd-back">
            <x-icon name="arrow-left" />{{ __('courses.back_to_list') }}
        </a>

        <div class="cd-head__title">
            <div>
                <h1 class="h3 mb-1">{{ $course->course_name }}</h1>
                <span class="cd-code">{{ $course->course_id }}</span>
            </div>
            <span class="badge rounded-pill cd-badge bg-{{ $courseTone }}-subtle text-{{ $courseTone }}-emphasis border border-{{ $courseTone }}-subtle">
                <span class="cd-dot bg-{{ $courseTone }}" aria-hidden="true"></span>{{ __('courses.state.'.$state) }}
            </span>
        </div>
    </header>

    <div class="cd-grid {{ $this->canFilterEmployee ? 'cd-grid--scoped' : '' }}"
        wire:loading.class="cd-is-loading" wire:target="team, employee, clearScopeFilters">

        @if ($this->canFilterEmployee)
            @php
                $team = $this->activeTeam;
                $employeeOptions = $this->employeeOptions;
            @endphp
            <section class="card cd-card cd-scope cd-area-scope" aria-label="{{ __('courses.scope_filter') }}">
                <div class="card-body">
                    @if ($this->canFilterTeam)
                        <label class="cd-scope__field">
                            <span class="form-label">{{ __('courses.team') }}</span>
                            <select wire:model.live="team" class="form-select">
                                <option value="">{{ __('courses.all_teams') }}</option>
                                @foreach ($this->teams as $option)
                                    <option value="{{ $option->team_id }}">{{ $option->team_name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @else
                        {{-- Sale Leader: đội cố định là đội của mình --}}
                        <div class="cd-scope__field">
                            <span class="form-label">{{ __('courses.team') }}</span>
                            <span class="cd-scope__fixed">{{ $team?->team_name ?? __('courses.no_team') }}</span>
                        </div>
                    @endif

                    <label class="cd-scope__field">
                        <span class="form-label">{{ __('courses.employee') }}</span>
                        <select wire:model.live="employee" class="form-select" @disabled($team === null)>
                            <option value="">{{ $team === null ? __('courses.choose_team_first') : __('courses.all_employees') }}</option>
                            @foreach ($employeeOptions as $option)
                                <option value="{{ $option->employee_id }}">
                                    {{ $option->full_name }}{{ $option->isActive() ? '' : ' ('.__('courses.resigned').')' }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    {{-- Sale Admin: bỏ được cả Đội lẫn Nhân viên; Sale Leader: chỉ bỏ chọn Nhân viên --}}
                    @if (($this->canFilterTeam && $team !== null) || $this->activeEmployee !== null)
                        <button type="button" class="btn btn-outline-secondary cd-scope__clear" wire:click="clearScopeFilters">
                            <x-icon name="x" />{{ __('courses.clear_scope') }}
                        </button>
                    @endif

                    <span class="cd-loading text-body-secondary" wire:loading.delay wire:target="team, employee, clearScopeFilters">
                        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                        {{ __('courses.loading') }}
                    </span>
                </div>
            </section>
        @endif

        {{-- Nội dung khóa học --}}
        <section class="card cd-card cd-area-content">
            <div class="card-body">
                <h2 class="cd-card__title">{{ __('courses.content') }}</h2>

                {{-- Ảnh khóa học (public/images/courses/{mã}.jpg) bên trái, tổng quan bên phải --}}
                @php $image = $course->imageUrl(); @endphp
                <div class="cd-overview {{ $image ? 'has-image' : '' }}">
                    @if ($image)
                        <img src="{{ $image }}" alt="{{ $course->course_name }}" class="cd-cover" width="500" height="333">
                    @endif

                    <div class="cd-overview__text">
                        <h3 class="cd-label">{{ __('courses.overview_heading') }}</h3>
                        @if (filled($course->description))
                            {{-- Mỗi dòng trong mô tả là một đoạn; nội dung luôn được escape --}}
                            <div class="cd-desc">
                                @foreach (preg_split('/\R+/', trim($course->description)) as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach
                            </div>
                        @else
                            <p class="cd-desc text-body-secondary fst-italic">{{ __('courses.no_description') }}</p>
                        @endif
                    </div>
                </div>

                <dl class="cd-facts">
                    <div class="cd-fact">
                        <dt><x-icon name="clock" />{{ __('courses.duration') }}</dt>
                        <dd>{{ $course->duration ?: '—' }}</dd>
                    </div>
                    <div class="cd-fact">
                        <dt><x-icon name="wallet" />{{ __('courses.price') }}</dt>
                        <dd>{{ $course->displayPrice() }}</dd>
                    </div>
                    <div class="cd-fact">
                        <dt><x-icon name="percent" />{{ __('courses.vat') }}</dt>
                        <dd>{{ $course->displayVat() }}</dd>
                    </div>
                </dl>

                <p class="cd-note mb-0">
                    <x-icon name="calendar" />
                    {{ __('courses.created_on', ['date' => $course->created_at?->format('d/m/Y') ?? '—']) }}
                </p>
            </div>
        </section>

        {{-- Thống kê --}}
        <section class="card cd-card cd-area-stats">
            <div class="card-body">
                <h2 class="cd-card__title">{{ __('courses.stats') }}</h2>

                <div class="cd-stats">
                    <div class="cd-stat">
                        <span class="cd-stat__label">{{ __('courses.quotations_created') }}</span>
                        <span class="cd-stat__value">{{ $number($stats['total']) }}</span>
                    </div>
                    <div class="cd-stat">
                        <span class="cd-stat__label">{{ __('courses.seats_confirmed') }}</span>
                        <span class="cd-stat__value">{{ $number($stats['seats_confirmed']) }}</span>
                    </div>
                    <div class="cd-stat" title="{{ __('courses.close_rate_hint') }}">
                        <span class="cd-stat__label">{{ __('courses.close_rate') }}</span>
                        <span class="cd-stat__value">{{ $rateText }}</span>
                        <span class="cd-stat__hint">{{ __('courses.close_rate_hint') }}</span>
                    </div>
                    <div class="cd-stat">
                        <span class="cd-stat__label">{{ __('courses.revenue_confirmed') }}</span>
                        <span class="cd-stat__value cd-stat__value--money">{{ $money($stats['revenue_confirmed']) }}</span>
                        <span class="cd-stat__hint">{{ __('courses.before_voucher') }}</span>
                    </div>
                </div>

                @if ($this->scopeNote !== null)
                    <p class="cd-note cd-scope-note mb-0"><x-icon name="shield" />{{ $this->scopeNote }}</p>
                @endif
            </div>
        </section>

        {{-- Báo giá theo trạng thái (bấm để lọc danh sách bên dưới) --}}
        <section class="card cd-card cd-area-status">
            <div class="card-body">
                <h2 class="cd-card__title">{{ __('courses.by_status') }}</h2>

                <div class="cd-bar {{ $barTotal === 0 ? 'is-empty' : '' }}" role="img"
                    aria-label="{{ __('courses.by_status') }}: {{ $barLabel }}">
                    @foreach ($stats['by_status'] as $status => $count)
                        @if ($count > 0)
                            <span class="cd-bar__seg bg-{{ $tones[$status] }}" style="flex-grow: {{ $count }}"
                                title="{{ $statusLabel($status) }}: {{ $count }}"></span>
                        @endif
                    @endforeach
                </div>

                <div class="cd-legend" role="group" aria-label="{{ __('courses.filter_by_status') }}">
                    <button type="button" wire:click="filterStatus"
                        class="cd-legend__item {{ $activeStatus === null ? 'is-active' : '' }}"
                        aria-pressed="{{ $activeStatus === null ? 'true' : 'false' }}">
                        <span class="cd-legend__swatch cd-legend__swatch--all" aria-hidden="true"></span>
                        <span class="cd-legend__name">{{ __('courses.all') }}</span>
                        <span class="cd-legend__count">{{ $number($stats['total']) }}</span>
                    </button>

                    @foreach ($stats['by_status'] as $status => $count)
                        <button type="button" wire:click="filterStatus('{{ $status }}')"
                            wire:key="legend-{{ $status }}"
                            class="cd-legend__item {{ $activeStatus === $status ? 'is-active' : '' }}"
                            aria-pressed="{{ $activeStatus === $status ? 'true' : 'false' }}">
                            <span class="cd-legend__swatch bg-{{ $tones[$status] }}" aria-hidden="true"></span>
                            <span class="cd-legend__name">{{ $statusLabel($status) }}</span>
                            <span class="cd-legend__count">
                                {{ $number($count) }}
                                <small class="text-body-secondary">({{ $percentOf($count) }}%)</small>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Báo giá có khóa học này (cột trái, ngay dưới nội dung) --}}
        <section class="card cd-card cd-area-list">
            <div class="card-body pb-0">
                <div class="cd-list-head">
                    <h2 class="cd-card__title mb-0">
                        {{ __('courses.quotations_title') }}
                        @if ($activeStatus !== null)
                            <span class="text-body-secondary fw-normal">· {{ $statusLabel($activeStatus) }}</span>
                        @endif
                        <span class="text-body-secondary fw-normal">({{ $number($quotations->total()) }})</span>
                    </h2>
                    <span class="cd-loading text-body-secondary" wire:loading.delay
                        wire:target="filterStatus, status, gotoPage, nextPage, previousPage">
                        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                        {{ __('courses.loading') }}
                    </span>
                </div>
            </div>

            <div wire:loading.class="opacity-50" wire:target="filterStatus, status, gotoPage, nextPage, previousPage"
                class="cd-list">
                @if ($quotations->isEmpty())
                    <div class="cd-empty">
                        <span class="cd-empty__icon"><x-icon name="inbox" /></span>
                        <p class="mb-2">
                            {{ $activeStatus === null || $stats['total'] === 0
                                ? __('courses.no_quotations')
                                : __('courses.no_quotations_status') }}
                        </p>
                        @if ($activeStatus !== null)
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="filterStatus">
                                {{ __('courses.clear_filter') }}
                            </button>
                        @endif
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 cd-table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('courses.quotation_id') }}</th>
                                    @if ($this->canFilterEmployee)
                                        <th scope="col">{{ __('courses.employee') }}</th>
                                    @endif
                                    <th scope="col">{{ __('courses.status') }}</th>
                                    <th scope="col">{{ __('courses.quotation_created_at') }}</th>
                                    <th scope="col" class="text-end">{{ __('courses.quantity') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($quotations as $line)
                                    @php $tone = $toneOf($line->quotation_status); @endphp
                                    <tr wire:key="quotation-{{ $line->quotation_id }}">
                                        {{-- Chưa có trang chi tiết báo giá → hiện mã dạng chữ, không tạo link --}}
                                        <td><span class="cd-code">{{ $line->quotation_id }}</span></td>
                                        @if ($this->canFilterEmployee)
                                            <td>{{ $line->employee_name ?? '—' }}</td>
                                        @endif
                                        <td>
                                            <span class="badge rounded-pill cd-badge bg-{{ $tone }}-subtle text-{{ $tone }}-emphasis border border-{{ $tone }}-subtle">
                                                <span class="cd-dot bg-{{ $tone }}" aria-hidden="true"></span>{{ $statusLabel($line->quotation_status) }}
                                            </span>
                                        </td>
                                        <td>{{ $line->quotation_created_at?->format('d/m/Y') ?? '—' }}</td>
                                        <td class="text-end fw-semibold">{{ $number($line->quantity) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($quotations->hasPages())
                        <div class="cd-pagination">{{ $quotations->links() }}</div>
                    @endif
                @endif
            </div>
        </section>
    </div>
</div>
