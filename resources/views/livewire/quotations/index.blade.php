@php
    use App\Livewire\Quotations\Index;
    use App\Models\Quotation;

    $counts = $this->counts;
    $activeStatuses = $this->activeStatuses();
    $showEmployee = $this->canFilterEmployee;
    $pending = $this->hasPendingChanges();

    // Chip bộ lọc đang áp dụng. Trạng thái đã thể hiện ở tab nên chỉ hiện chip khi lọc nhiều trạng thái (từ URL)
    $chips = $this->chips;
    if (count($activeStatuses) <= 1) {
        unset($chips['statuses']);
    }

    // Số bộ lọc trong panel đang áp dụng (huy hiệu trên nút "Bộ lọc")
    $panelCount = count(array_intersect_key($this->chips, array_flip(['deadline', 'order', 'created', 'expiry', 'team', 'employee'])));

    // Tab trạng thái: [trạng thái, icon]
    $tabs = [
        ['', 'layers'],
        [Quotation::STATUS_DRAFT, 'edit'],
        [Quotation::STATUS_CONFIRMED, 'check-circle'],
        [Quotation::STATUS_REJECTED, 'x'],
    ];

    $money = fn (float $amount) => number_format($amount, 0, ',', '.').' ₫';
@endphp

<div class="qt">
    {{-- CSS riêng của trang báo giá: resources/css/quotation.css (chỉ nạp ở trang này) --}}
    @assets
        @vite('resources/css/quotation.css')
    @endassets

    {{-- Tiêu đề hiển thị ở thanh trên cùng; giữ thẻ h1 ẩn cho trình đọc màn hình.
         Không có nút Thêm mới: báo giá được lập từ cơ hội (HĐ1). --}}
    <h1 class="visually-hidden">{{ __('quotations.title') }}</h1>

    {{-- ── Khối dính khi cuộn: tab trạng thái + thanh công cụ + chip bộ lọc ── --}}
    <div class="qt-sticky">
        {{-- Tab trạng thái (bấm để lọc ngay; số đếm theo mọi bộ lọc trừ trạng thái) --}}
        <nav class="qt-tabs" aria-label="{{ __('quotations.status_label') }}">
            @foreach ($tabs as [$key, $icon])
                @php $current = $key === '' ? $activeStatuses === [] : $activeStatuses === [$key]; @endphp
                <button type="button" wire:click="filterStatus('{{ $key }}')"
                    class="qt-tab qt-tone--{{ $key ? strtolower($key) : 'all' }} {{ $current ? 'is-current' : '' }}"
                    aria-pressed="{{ $current ? 'true' : 'false' }}">
                    <x-icon :name="$icon" />
                    <span class="qt-tab__label">{{ $key ? __('quotations.status.'.$key) : __('quotations.all') }}</span>
                    <span class="qt-tab__count">{{ $counts[$key ?: 'all'] }}</span>
                </button>
            @endforeach
        </nav>

        {{-- Thanh công cụ: tìm kiếm (cập nhật ngay) + nút Bộ lọc + Xoá bộ lọc --}}
        <div class="qt-toolbar" x-data="{ open: false }" x-on:keydown.escape.window="open = false"
            x-on:quotation-filters-applied.window="open = false">
            <div class="qt-search">
                <x-icon name="search" />
                <input type="search" wire:model.live.debounce.350ms="search" placeholder="{{ __('quotations.search_placeholder') }}"
                    aria-label="{{ __('quotations.search') }}">
                <span class="qt-search__spin" wire:loading.delay wire:target="search">
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                </span>

                {{-- Hướng dẫn cú pháp tìm kiếm (thay cho dòng gợi ý luôn hiện) --}}
                <span class="qt-hint" x-data="{ tip: false }" x-on:click.outside="tip = false">
                    <button type="button" class="qt-hint__btn" x-on:click="tip = ! tip" :aria-expanded="tip.toString()"
                        title="{{ __('quotations.search_hint') }}">
                        <x-icon name="info" />
                        <span class="visually-hidden">{{ __('quotations.search_help_title') }}</span>
                    </button>
                    <span class="qt-hint__pop" x-show="tip" x-cloak x-transition.opacity role="tooltip">
                        <strong>{{ __('quotations.search_help_title') }}</strong>
                        <span class="qt-hint__list">
                            @foreach (__('quotations.search_help') as $line)
                                <span class="qt-hint__item">{!! $line !!}</span>
                            @endforeach
                        </span>
                    </span>
                </span>
            </div>

            <div class="qt-filter" x-on:click.outside="if (window.innerWidth >= 768) open = false">
                <button type="button" class="qt-btn qt-btn--filter" :class="{ 'is-open': open }" x-on:click="open = ! open"
                    :aria-expanded="open.toString()" aria-controls="qt-panel">
                    <x-icon name="filter" />
                    <span>{{ __('quotations.filters') }}</span>
                    @if ($panelCount)
                        <span class="qt-btn__badge">{{ $panelCount }}</span>
                    @endif
                    {{-- Chấm báo: đã đổi bộ lọc trong panel nhưng chưa bấm Áp dụng --}}
                    <span class="qt-btn__dot" title="{{ __('quotations.pending') }}"
                        @unless ($pending) wire:dirty wire:target="form" @endunless></span>
                </button>

                {{-- Panel bộ lọc: thả xuống trên màn rộng, drawer toàn màn hình trên màn hẹp --}}
                <div class="qt-panel" id="qt-panel" x-show="open" x-cloak x-transition.opacity role="dialog"
                    aria-label="{{ __('quotations.filters') }}">
                    <form wire:submit="applyFilters" class="qt-panel__form">
                        <div class="qt-panel__head">
                            <span class="qt-panel__title"><x-icon name="filter" />{{ __('quotations.filters') }}</span>
                            <button type="button" class="qt-panel__close" x-on:click="open = false">
                                <x-icon name="x" />
                                <span class="visually-hidden">{{ __('quotations.close') }}</span>
                            </button>
                        </div>

                        <div class="qt-panel__body">
                            <label class="qt-field">
                                <span class="qt-field__label">{{ __('quotations.deadline_label') }}</span>
                                <select wire:model="form.deadline" class="qt-control">
                                    <option value="">{{ __('quotations.all') }}</option>
                                    @foreach (Index::DEADLINES as $option)
                                        <option value="{{ $option }}">{{ __('quotations.deadline.'.$option) }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="qt-field">
                                <span class="qt-field__label">{{ __('quotations.order') }}</span>
                                <select wire:model="form.orderState" class="qt-control">
                                    <option value="">{{ __('quotations.all') }}</option>
                                    @foreach (Index::ORDER_STATES as $option)
                                        <option value="{{ $option }}">{{ __('quotations.order_state.'.$option) }}</option>
                                    @endforeach
                                </select>
                            </label>

                            {{-- Khoảng ngày: cặp từ / đến trong một nhóm (không có thư viện date range picker) --}}
                            <fieldset class="qt-field">
                                <legend class="qt-field__label">{{ __('quotations.created_range') }}</legend>
                                <div class="qt-range">
                                    <input type="date" wire:model="form.createdFrom" class="qt-control" max="{{ $form['createdTo'] }}"
                                        aria-label="{{ __('quotations.created_from') }}" title="{{ __('quotations.created_from') }}">
                                    <span class="qt-range__sep" aria-hidden="true">-</span>
                                    <input type="date" wire:model="form.createdTo" min="{{ $form['createdFrom'] }}"
                                        @class(['qt-control', 'is-invalid' => $errors->has('form.createdTo')])
                                        aria-label="{{ __('quotations.created_to') }}" title="{{ __('quotations.created_to') }}">
                                </div>
                                @error('form.createdTo')
                                    <span class="qt-field__error">{{ $message }}</span>
                                @enderror
                            </fieldset>

                            <fieldset class="qt-field">
                                <legend class="qt-field__label">{{ __('quotations.expiry_range') }}</legend>
                                <div class="qt-range">
                                    <input type="date" wire:model="form.expiryFrom" class="qt-control" max="{{ $form['expiryTo'] }}"
                                        aria-label="{{ __('quotations.expiry_from') }}" title="{{ __('quotations.expiry_from') }}">
                                    <span class="qt-range__sep" aria-hidden="true">-</span>
                                    <input type="date" wire:model="form.expiryTo" min="{{ $form['expiryFrom'] }}"
                                        @class(['qt-control', 'is-invalid' => $errors->has('form.expiryTo')])
                                        aria-label="{{ __('quotations.expiry_to') }}" title="{{ __('quotations.expiry_to') }}">
                                </div>
                                @error('form.expiryTo')
                                    <span class="qt-field__error">{{ $message }}</span>
                                @enderror
                            </fieldset>

                            @if ($this->canFilterTeam)
                                <label class="qt-field">
                                    <span class="qt-field__label">{{ __('quotations.team') }}</span>
                                    {{-- .live chỉ để thu hẹp danh sách nhân viên theo đội, danh sách báo giá chưa đổi --}}
                                    <select wire:model.live="form.team" class="qt-control">
                                        <option value="">{{ __('quotations.all_teams') }}</option>
                                        @foreach ($this->teams as $option)
                                            <option value="{{ $option->team_id }}">{{ $option->team_name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif

                            @if ($showEmployee)
                                <label class="qt-field">
                                    <span class="qt-field__label">{{ __('quotations.employee') }}</span>
                                    <select wire:model="form.employee" class="qt-control">
                                        <option value="">{{ __('quotations.all_employees') }}</option>
                                        @foreach ($this->formEmployeeOptions as $option)
                                            <option value="{{ $option->employee_id }}" wire:key="emp-{{ $option->employee_id }}">{{ $option->full_name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                        </div>

                        <div class="qt-panel__foot">
                            {{-- Nhắc khi bộ lọc trên form khác bộ lọc đang áp dụng (wire:dirty: đổi phía trình duyệt chưa gửi) --}}
                            <span class="qt-pending" role="status" @unless ($pending) wire:dirty wire:target="form" @endunless>
                                <x-icon name="info" />{{ __('quotations.pending') }}
                            </span>
                            <button type="button" class="qt-btn qt-btn--ghost" wire:click="resetForm">
                                <x-icon name="refresh" />{{ __('quotations.reset_form') }}
                            </button>
                            <button type="submit" class="qt-btn qt-btn--primary">
                                <span wire:loading.remove wire:target="applyFilters"><x-icon name="check" /></span>
                                <span wire:loading wire:target="applyFilters" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                {{ __('quotations.apply') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Chỉ hiện khi có bộ lọc / từ khóa đang áp dụng --}}
            @if ($this->chips !== [])
                <button type="button" class="qt-btn qt-btn--ghost qt-btn--clear" wire:click="resetFilters">
                    <x-icon name="refresh" /><span>{{ __('quotations.reset') }}</span>
                </button>
            @endif
        </div>

        {{-- Bộ lọc đang áp dụng (hiện cả khi panel đã đóng) --}}
        @if ($chips !== [])
            <div class="qt-chips" aria-label="{{ __('quotations.active_filters') }}">
                <span class="qt-chips__title">{{ __('quotations.active_filters') }}:</span>
                @foreach ($chips as $key => $label)
                    <span class="qt-chip" wire:key="chip-{{ $key }}">
                        <span class="qt-chip__text">{{ $label }}</span>
                        <button type="button" class="qt-chip__remove" wire:click="clearFilter('{{ $key }}')"
                            title="{{ __('quotations.remove_filter') }}">
                            <x-icon name="x" />
                            <span class="visually-hidden">{{ __('quotations.remove_filter') }}: {{ $label }}</span>
                        </button>
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ── Kết quả ──────────────────────────────────────────────────── --}}
    <section class="qt-card qt-results">
        <div class="qt-results__bar">
            <span>
                {!! trans_choice('quotations.result_count', $quotations->total(), ['count' => '<strong>'.$quotations->total().'</strong>']) !!}
            </span>
            <span class="qt-results__tools">
                <span class="qt-results__loading" wire:loading.delay wire:target.except="form.team">
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    {{ __('quotations.loading') }}
                </span>
                <label class="qt-sort">
                    <x-icon name="sort" />
                    <span class="visually-hidden">{{ __('quotations.sort_by') }}</span>
                    <select wire:model.live="sort" class="qt-control qt-control--sm" aria-label="{{ __('quotations.sort_by') }}">
                        @foreach (Index::SORTS as $option)
                            <option value="{{ $option }}">{{ __('quotations.sort.'.$option) }}</option>
                        @endforeach
                    </select>
                </label>
            </span>
        </div>

        @if ($quotations->isEmpty())
            <div class="qt-empty">
                <span class="qt-empty__icon"><x-icon name="inbox" /></span>
                @if ($this->hasAnyQuotation)
                    <p class="qt-empty__title">{{ __('quotations.empty') }}</p>
                    <p class="qt-empty__sub">{{ __('quotations.empty_sub') }}</p>
                    <button type="button" class="qt-btn qt-btn--primary" wire:click="resetFilters">
                        <x-icon name="refresh" />{{ __('quotations.reset_all') }}
                    </button>
                @else
                    <p class="qt-empty__title">{{ __('quotations.empty_none') }}</p>
                    <p class="qt-empty__sub">{{ __('quotations.empty_none_sub') }}</p>
                @endif
            </div>
        @else
            <div class="qt-table-wrap" wire:loading.class="is-loading" wire:target.except="form.team">
                <table class="qt-table">
                    <thead>
                        <tr>
                            <th>{{ __('quotations.code') }}</th>
                            <th>{{ __('quotations.customer') }}</th>
                            @if ($showEmployee)
                                <th>{{ __('quotations.employee') }}</th>
                            @endif
                            <th>{{ __('quotations.created_at') }}</th>
                            <th>{{ __('quotations.expiry_date') }}</th>
                            <th class="text-lg-end">{{ __('quotations.total') }}</th>
                            <th>{{ __('quotations.status_label') }}</th>
                            <th>{{ __('quotations.order') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quotations as $quotation)
                            @php
                                $isDraft = $quotation->status === Quotation::STATUS_DRAFT;
                                $daysLeft = $isDraft ? $quotation->daysUntilExpiry() : null;
                                $tone = in_array($quotation->status, Quotation::STATUSES, true) ? strtolower($quotation->status) : 'all';
                                $url = route($this->routePrefix.'.quotations.show', $quotation);
                            @endphp
                            {{-- Bấm cả dòng mở trang chi tiết (giống danh sách khóa học); dùng bàn phím: Tab tới mã báo giá rồi Enter --}}
                            <tr wire:key="quotation-{{ $quotation->quotation_id }}" class="qt-row-link" onclick="window.location='{{ $url }}'">
                                <td data-label="{{ __('quotations.code') }}">
                                    <a href="{{ $url }}" class="qt-code" onclick="event.stopPropagation()">
                                        <span class="qt-code__icon"><x-icon name="file" /></span>
                                        <span class="qt-code__id">{{ $this->highlight($quotation->quotation_id) }}</span>
                                    </a>
                                </td>
                                <td data-label="{{ __('quotations.customer') }}">
                                    <span class="qt-stack">
                                        <span class="qt-stack__main">
                                            @if ($quotation->opportunity?->lead)
                                                {{ $this->highlight($quotation->opportunity->lead->full_name) }}
                                            @else
                                                —
                                            @endif
                                        </span>
                                        <span class="qt-stack__sub">{{ $quotation->opportunity_id }}</span>
                                    </span>
                                </td>
                                @if ($showEmployee)
                                    <td data-label="{{ __('quotations.employee') }}">
                                        {{ $quotation->employee?->full_name ?? '—' }}
                                    </td>
                                @endif
                                <td data-label="{{ __('quotations.created_at') }}">
                                    {{ $quotation->created_at?->format('d/m/Y') }}
                                </td>
                                <td data-label="{{ __('quotations.expiry_date') }}">
                                    <span class="qt-stack">
                                        <span class="qt-stack__main">{{ $quotation->expiry_date?->format('d/m/Y') }}</span>
                                        @if ($isDraft)
                                            @if ($daysLeft < 0)
                                                <span class="qt-expiry qt-expiry--over">
                                                    <x-icon name="alert" />{{ __('quotations.overdue') }}
                                                </span>
                                            @else
                                                <span class="qt-expiry">
                                                    <x-icon name="hourglass" />{{ __('quotations.days_left', ['days' => $daysLeft]) }}
                                                </span>
                                            @endif
                                        @endif
                                    </span>
                                </td>
                                <td data-label="{{ __('quotations.total') }}" class="text-lg-end">
                                    {{-- computed_total: cùng biểu thức SQL dùng để sắp xếp theo tổng tiền --}}
                                    <span class="qt-amount">{{ $money((float) $quotation->computed_total) }}</span>
                                </td>
                                <td data-label="{{ __('quotations.status_label') }}">
                                    <span class="qt-badge qt-tone--{{ $tone }}">
                                        <span class="qt-badge__dot"></span>{{ __('quotations.status.'.$quotation->status) }}
                                    </span>
                                </td>
                                <td data-label="{{ __('quotations.order') }}">
                                    @if ($quotation->order)
                                        <a href="{{ route($this->routePrefix.'.orders.show', $quotation->order) }}" class="qt-mono qt-link" onclick="event.stopPropagation()">{{ $this->highlight($quotation->order->order_id) }}</a>
                                    @else
                                        <span class="qt-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($quotations->hasPages())
                <div class="qt-pagination">{{ $quotations->links() }}</div>
            @endif
        @endif
    </section>
</div>
