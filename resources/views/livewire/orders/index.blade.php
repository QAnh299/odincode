@php
    use App\Livewire\Orders\Index;
    use App\Models\SalesOrder;
    use Illuminate\Support\Facades\Route;

    $counts = $this->counts;
    $activeStatuses = $this->activeStatuses();
    $showEmployee = $this->canFilterEmployee;
    $pending = $this->hasPendingChanges();
    $chips = $this->chips;

    // Số bộ lọc trong panel đang áp dụng (huy hiệu trên nút "Bộ lọc"); từ khóa nằm ở ô tìm kiếm nên không tính
    $panelCount = count(array_diff_key($chips, ['search' => true]));

    // Thẻ thống kê trạng thái: [trạng thái, icon]
    $tabs = [
        ['', 'layers'],
        [SalesOrder::STATUS_IN_PROGRESS, 'clock'],
        [SalesOrder::STATUS_COMPLETED, 'check-circle'],
    ];

    // Link sang trang khác chỉ khi route của vai trò đó tồn tại
    $quotationRoute = $this->routePrefix.'.quotations.show';
    $hasQuotationRoute = Route::has($quotationRoute);
    $invoiceRoute = $this->routePrefix.'.invoices';
    $hasInvoiceRoute = Route::has($invoiceRoute);

    $tone = fn (?string $status) => in_array($status, SalesOrder::STATUSES, true) ? ($status === SalesOrder::STATUS_COMPLETED ? 'completed' : 'progress') : 'all';
    $money = fn (float $amount) => number_format($amount, 0, ',', '.').' ₫';
@endphp

<div class="od">
    {{-- CSS riêng của trang đơn hàng: resources/css/order.css (chỉ nạp ở trang này) --}}
    @assets
        @vite('resources/css/order.css')
    @endassets

    {{-- Tiêu đề hiển thị ở thanh trên cùng; giữ thẻ h1 ẩn cho trình đọc màn hình.
         Không có nút Thêm mới: đơn hàng được tạo khi xác nhận báo giá (HĐ16 – HĐ17). --}}
    <h1 class="visually-hidden">{{ __('orders.title') }}</h1>

    {{-- ── Khối dính khi cuộn: thẻ trạng thái + tổng tiền + thanh công cụ + chip bộ lọc ── --}}
    <div class="od-sticky">
        <p class="od-subtitle">{{ __('orders.subtitle') }}</p>

        {{-- Thẻ trạng thái (bấm để lọc ngay, bấm lại bỏ lọc; số đếm theo mọi bộ lọc trừ trạng thái) --}}
        <nav class="od-tabs" aria-label="{{ __('orders.status_label') }}">
            @foreach ($tabs as [$key, $icon])
                @php $current = $key === '' ? $activeStatuses === [] : $activeStatuses === [$key]; @endphp
                <button type="button" wire:click="filterStatus('{{ $key }}')"
                    class="od-tab od-tone--{{ $tone($key) }} {{ $current ? 'is-current' : '' }}"
                    aria-pressed="{{ $current ? 'true' : 'false' }}">
                    <x-icon :name="$icon" />
                    <span class="od-tab__label">{{ $key ? __('orders.status.'.$key) : __('orders.all') }}</span>
                    <span class="od-tab__count">{{ $counts[$key ?: 'all'] }}</span>
                </button>
            @endforeach
        </nav>

        {{-- Thanh công cụ: tìm kiếm (cập nhật ngay) + nút Bộ lọc + Xoá bộ lọc --}}
        <div class="od-toolbar" x-data="{ open: false }" x-on:keydown.escape.window="open = false"
            x-on:order-filters-applied.window="open = false">
            <div class="od-search">
                <x-icon name="search" />
                <input type="search" wire:model.live.debounce.350ms="search" placeholder="{{ __('orders.search_placeholder') }}"
                    aria-label="{{ __('orders.search') }}" maxlength="500">
                <span class="od-search__spin" wire:loading.delay wire:target="search">
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                </span>

                {{-- Hướng dẫn cú pháp tìm kiếm --}}
                <span class="od-hint" x-data="{ tip: false }" x-on:click.outside="tip = false">
                    <button type="button" class="od-hint__btn" x-on:click="tip = ! tip" :aria-expanded="tip.toString()"
                        title="{{ __('orders.search_help_title') }}">
                        <x-icon name="info" />
                        <span class="visually-hidden">{{ __('orders.search_help_title') }}</span>
                    </button>
                    <span class="od-hint__pop" x-show="tip" x-cloak x-transition.opacity role="tooltip">
                        <strong>{{ __('orders.search_help_title') }}</strong>
                        <span class="od-hint__list">
                            {{-- Chuỗi cố định trong lang (có thẻ <code>/<strong>), không chứa dữ liệu người dùng --}}
                            @foreach (__('orders.search_help') as $line)
                                <span class="od-hint__item">{!! $line !!}</span>
                            @endforeach
                        </span>
                    </span>
                </span>
            </div>

            <div class="od-filter" x-on:click.outside="if (window.innerWidth >= 768) open = false">
                <button type="button" class="od-btn od-btn--filter" :class="{ 'is-open': open }" x-on:click="open = ! open"
                    :aria-expanded="open.toString()" aria-controls="od-panel">
                    <x-icon name="filter" />
                    <span>{{ __('orders.filters') }}</span>
                    @if ($panelCount)
                        <span class="od-btn__badge">{{ $panelCount }}</span>
                    @endif
                    {{-- Chấm báo: đã đổi bộ lọc trong panel nhưng chưa bấm Áp dụng --}}
                    <span class="od-btn__dot" title="{{ __('orders.pending') }}"
                        @unless ($pending) wire:dirty wire:target="form" @endunless></span>
                </button>

                {{-- Panel bộ lọc: thả xuống trên màn rộng, drawer toàn màn hình trên màn hẹp --}}
                <div class="od-panel" id="od-panel" x-show="open" x-cloak x-transition.opacity role="dialog"
                    aria-label="{{ __('orders.filters') }}">
                    <form wire:submit="applyFilters" class="od-panel__form">
                        <div class="od-panel__head">
                            <span class="od-panel__title"><x-icon name="filter" />{{ __('orders.filters') }}</span>
                            <button type="button" class="od-panel__close" x-on:click="open = false">
                                <x-icon name="x" />
                                <span class="visually-hidden">{{ __('orders.close') }}</span>
                            </button>
                        </div>

                        <div class="od-panel__body">
                            {{-- Trạng thái (chọn nhiều, đồng bộ với thẻ thống kê) --}}
                            <fieldset class="od-field">
                                <legend class="od-field__label">{{ __('orders.status_label') }}</legend>
                                <div class="od-checks">
                                    @foreach (SalesOrder::STATUSES as $option)
                                        <label class="od-check od-tone--{{ $tone($option) }}" wire:key="st-{{ $option }}">
                                            <input type="checkbox" wire:model="form.statuses" value="{{ $option }}">
                                            <span>{{ __('orders.status.'.$option) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>

                            {{-- Hình thức (chọn nhiều, gồm "Chưa xác định" = NULL / rỗng) --}}
                            <fieldset class="od-field">
                                <legend class="od-field__label">{{ __('orders.payment_type_label') }}</legend>
                                <div class="od-checks">
                                    @foreach ([...SalesOrder::PAYMENT_TYPES, Index::PAYMENT_NONE] as $option)
                                        <label class="od-check" wire:key="pt-{{ $option }}">
                                            <input type="checkbox" wire:model="form.paymentTypes" value="{{ $option }}">
                                            <span>{{ __('orders.payment_type.'.$option) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>

                            <label class="od-field">
                                <span class="od-field__label">{{ __('orders.progress_label') }}</span>
                                <select wire:model="form.progress" class="od-control">
                                    <option value="">{{ __('orders.all') }}</option>
                                    @foreach (Index::PROGRESSES as $option)
                                        <option value="{{ $option }}">{{ __('orders.progress.'.$option) }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="od-field">
                                <span class="od-field__label">{{ __('orders.invoice_label') }}</span>
                                <select wire:model="form.invoiceState" class="od-control">
                                    <option value="">{{ __('orders.all') }}</option>
                                    @foreach (Index::INVOICE_STATES as $option)
                                        <option value="{{ $option }}">{{ __('orders.invoice_state.'.$option) }}</option>
                                    @endforeach
                                </select>
                            </label>

                            {{-- Khoảng tổng tiền: ô chữ để báo lỗi khi nhập số âm / chữ (cho phép 3.000.000) --}}
                            <fieldset class="od-field">
                                <legend class="od-field__label">{{ __('orders.amount_range') }}</legend>
                                <div class="od-range">
                                    <input type="text" inputmode="numeric" wire:model="form.amountFrom" maxlength="25" placeholder="0"
                                        @class(['od-control', 'is-invalid' => $errors->has('form.amountFrom')])
                                        aria-label="{{ __('orders.amount_from') }}" title="{{ __('orders.amount_from') }}">
                                    <span class="od-range__sep" aria-hidden="true">–</span>
                                    <input type="text" inputmode="numeric" wire:model="form.amountTo" maxlength="25"
                                        @class(['od-control', 'is-invalid' => $errors->has('form.amountTo')])
                                        aria-label="{{ __('orders.amount_to') }}" title="{{ __('orders.amount_to') }}">
                                </div>
                                @error('form.amountFrom')
                                    <span class="od-field__error">{{ $message }}</span>
                                @enderror
                                @error('form.amountTo')
                                    <span class="od-field__error">{{ $message }}</span>
                                @enderror
                            </fieldset>

                            {{-- Khoảng ngày tạo đơn (không có thư viện date range picker) --}}
                            <fieldset class="od-field">
                                <legend class="od-field__label">{{ __('orders.created_range') }}</legend>
                                <div class="od-range">
                                    <input type="date" wire:model="form.createdFrom" class="od-control" max="{{ $form['createdTo'] }}"
                                        aria-label="{{ __('orders.created_from') }}" title="{{ __('orders.created_from') }}">
                                    <span class="od-range__sep" aria-hidden="true">–</span>
                                    <input type="date" wire:model="form.createdTo" min="{{ $form['createdFrom'] }}"
                                        @class(['od-control', 'is-invalid' => $errors->has('form.createdTo')])
                                        aria-label="{{ __('orders.created_to') }}" title="{{ __('orders.created_to') }}">
                                </div>
                                @error('form.createdTo')
                                    <span class="od-field__error">{{ $message }}</span>
                                @enderror
                            </fieldset>

                            @if ($this->canFilterTeam)
                                <label class="od-field">
                                    <span class="od-field__label">{{ __('orders.team') }}</span>
                                    {{-- .live chỉ để thu hẹp danh sách nhân viên theo đội, danh sách đơn chưa đổi --}}
                                    <select wire:model.live="form.team" class="od-control">
                                        <option value="">{{ __('orders.all_teams') }}</option>
                                        @foreach ($this->teams as $option)
                                            <option value="{{ $option->team_id }}">{{ $option->team_name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif

                            @if ($showEmployee)
                                <label class="od-field">
                                    <span class="od-field__label">{{ __('orders.employee') }}</span>
                                    <select wire:model="form.employee" class="od-control">
                                        <option value="">{{ __('orders.all_employees') }}</option>
                                        @foreach ($this->formEmployeeOptions as $option)
                                            <option value="{{ $option->employee_id }}" wire:key="emp-{{ $option->employee_id }}">{{ $option->full_name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                        </div>

                        <div class="od-panel__foot">
                            {{-- Nhắc khi bộ lọc trên form khác bộ lọc đang áp dụng (wire:dirty: đổi phía trình duyệt chưa gửi) --}}
                            <span class="od-pending" role="status" @unless ($pending) wire:dirty wire:target="form" @endunless>
                                <x-icon name="info" />{{ __('orders.pending') }}
                            </span>
                            <button type="button" class="od-btn od-btn--ghost" wire:click="resetForm">
                                <x-icon name="refresh" />{{ __('orders.reset_form') }}
                            </button>
                            <button type="submit" class="od-btn od-btn--primary">
                                <span wire:loading.remove wire:target="applyFilters"><x-icon name="check" /></span>
                                <span wire:loading wire:target="applyFilters" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                {{ __('orders.apply') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Chỉ hiện khi có bộ lọc / từ khóa đang áp dụng --}}
            @if ($chips !== [])
                <button type="button" class="od-btn od-btn--ghost od-btn--clear" wire:click="resetFilters">
                    <x-icon name="refresh" /><span>{{ __('orders.reset') }}</span>
                </button>
            @endif
        </div>

        {{-- Dòng gợi ý cú pháp tìm kiếm --}}
        <p class="od-hintline"><x-icon name="info" />{{ __('orders.search_hint') }}</p>

        {{-- Bộ lọc đang áp dụng (hiện cả khi panel đã đóng) --}}
        @if ($chips !== [])
            <div class="od-chips" aria-label="{{ __('orders.active_filters') }}">
                <span class="od-chips__title">{{ __('orders.active_filters') }}:</span>
                @foreach ($chips as $key => $label)
                    <span class="od-chip" wire:key="chip-{{ $key }}">
                        <span class="od-chip__text">{{ $label }}</span>
                        <button type="button" class="od-chip__remove" wire:click="clearFilter('{{ $key }}')"
                            title="{{ __('orders.remove_filter') }}">
                            <x-icon name="x" />
                            <span class="visually-hidden">{{ __('orders.remove_filter') }}: {{ $label }}</span>
                        </button>
                    </span>
                @endforeach
                <button type="button" class="od-chips__clear" wire:click="resetFilters">{{ __('orders.reset_all') }}</button>
            </div>
        @endif
    </div>

    {{-- ── Kết quả ──────────────────────────────────────────────────── --}}
    <section class="od-card od-results">
        <div class="od-results__bar">
            <span>
                {!! trans_choice('orders.result_count', $orders->total(), ['count' => '<strong>'.$orders->total().'</strong>']) !!}
            </span>
            <span class="od-results__tools">
                <span class="od-results__loading" wire:loading.delay wire:target.except="form.team">
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    {{ __('orders.loading') }}
                </span>
                <label class="od-sort">
                    <x-icon name="sort" />
                    <span class="visually-hidden">{{ __('orders.sort_by') }}</span>
                    <select wire:model.live="sort" class="od-control od-control--sm" aria-label="{{ __('orders.sort_by') }}">
                        @foreach (Index::SORTS as $option)
                            <option value="{{ $option }}">{{ __('orders.sort.'.$option) }}</option>
                        @endforeach
                    </select>
                </label>
            </span>
        </div>

        @if ($orders->isEmpty())
            <div class="od-empty">
                <span class="od-empty__icon"><x-icon name="inbox" /></span>
                @if ($this->hasAnyOrder)
                    <p class="od-empty__title">{{ __('orders.empty') }}</p>
                    <p class="od-empty__sub">{{ __('orders.empty_sub') }}</p>
                    <button type="button" class="od-btn od-btn--primary" wire:click="resetFilters">
                        <x-icon name="refresh" />{{ __('orders.reset_all') }}
                    </button>
                @else
                    <p class="od-empty__title">{{ __('orders.empty_none') }}</p>
                    <p class="od-empty__sub">{{ __('orders.empty_none_sub') }}</p>
                @endif
            </div>
        @else
            <div class="od-table-wrap" wire:loading.class="is-loading" wire:target.except="form.team">
                <table class="od-table">
                    <thead>
                        <tr>
                            <th>{{ __('orders.code') }}</th>
                            <th>{{ __('orders.customer') }}</th>
                            <th>{{ __('orders.quotation') }}</th>
                            <th>{{ __('orders.payment_type_col') }}</th>
                            <th class="text-lg-end">{{ __('orders.total') }}</th>
                            <th>{{ __('orders.paid') }}</th>
                            <th class="text-lg-end">{{ __('orders.remaining') }}</th>
                            <th>{{ __('orders.invoices') }}</th>
                            <th>{{ __('orders.status_label') }}</th>
                            @if ($showEmployee)
                                <th>{{ __('orders.employee') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            @php
                                $quotation = $order->quotation;
                                $lead = $quotation?->opportunity?->lead;
                                $percent = $order->progressPercent();
                                $paymentType = in_array($order->payment_type, SalesOrder::PAYMENT_TYPES, true) ? $order->payment_type : Index::PAYMENT_NONE;
                            @endphp
                            {{-- Chưa có trang chi tiết đơn hàng: khi có route {vai trò}.orders.show thì gắn link vào mã đơn
                                 và cho bấm cả dòng (giống danh sách báo giá) --}}
                            <tr wire:key="order-{{ $order->order_id }}">
                                <td data-label="{{ __('orders.code') }}">
                                    <span class="od-code">
                                        <span class="od-code__icon"><x-icon name="file" /></span>
                                        <span class="od-stack">
                                            <span class="od-code__id">{{ $this->highlight($order->order_id) }}</span>
                                            <span class="od-stack__sub">{{ $order->created_at?->format('d/m/Y') }}</span>
                                        </span>
                                    </span>
                                </td>
                                <td data-label="{{ __('orders.customer') }}">
                                    <span class="od-stack">
                                        <span class="od-stack__main">{{ $lead ? $this->highlight($lead->full_name) : '—' }}</span>
                                        <span class="od-stack__sub od-stack__sub--mono">{{ $quotation?->opportunity_id ?? '—' }}</span>
                                    </span>
                                </td>
                                <td data-label="{{ __('orders.quotation') }}">
                                    @if ($hasQuotationRoute)
                                        <a href="{{ route($quotationRoute, $order->quotation_id) }}" class="od-mono od-link"
                                            title="{{ __('orders.view_quotation', ['code' => $order->quotation_id]) }}">{{ $this->highlight($order->quotation_id) }}</a>
                                    @else
                                        <span class="od-mono">{{ $this->highlight($order->quotation_id) }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ __('orders.payment_type_col') }}">
                                    <span @class(['od-type', 'od-type--none' => $paymentType === Index::PAYMENT_NONE])>{{ __('orders.payment_type.'.$paymentType) }}</span>
                                </td>
                                <td data-label="{{ __('orders.total') }}" class="text-lg-end">
                                    <span class="od-amount">{{ $money((float) $order->total_amount) }}</span>
                                </td>
                                <td data-label="{{ __('orders.paid') }}">
                                    <span class="od-progress">
                                        <span class="od-progress__text">
                                            <span class="od-progress__value">{{ $money((float) $order->paid_amount) }}</span>
                                            <span class="od-progress__pct">{{ $percent }}%</span>
                                        </span>
                                        <span class="od-progress__bar" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"
                                            aria-label="{{ __('orders.paid') }}">
                                            <span class="od-progress__fill" style="width: {{ $percent }}%"></span>
                                        </span>
                                    </span>
                                </td>
                                <td data-label="{{ __('orders.remaining') }}" class="text-lg-end">
                                    <span @class(['od-remaining', 'is-zero' => (float) $order->remaining_amount <= 0])>{{ $money((float) $order->remaining_amount) }}</span>
                                </td>
                                <td data-label="{{ __('orders.invoices') }}">
                                    <span class="od-stack od-invoices">
                                        @php $invoiceText = trans_choice('orders.invoice_count', $order->invoices_count, ['count' => $order->invoices_count]); @endphp
                                        @if ($hasInvoiceRoute)
                                            <a href="{{ route($invoiceRoute, ['q' => $order->order_id]) }}" class="od-link"
                                                title="{{ __('orders.view_invoices', ['code' => $order->order_id]) }}">{{ $invoiceText }}</a>
                                        @else
                                            <span>{{ $invoiceText }}</span>
                                        @endif
                                        @if ($order->pending_invoices_count > 0 || $order->overdue_invoices_count > 0)
                                            <span class="od-tags">
                                                @if ($order->pending_invoices_count > 0)
                                                    <span class="od-tag od-tag--pending">{{ __('orders.invoices_pending', ['count' => $order->pending_invoices_count]) }}</span>
                                                @endif
                                                @if ($order->overdue_invoices_count > 0)
                                                    <span class="od-tag od-tag--overdue">{{ __('orders.invoices_overdue', ['count' => $order->overdue_invoices_count]) }}</span>
                                                @endif
                                            </span>
                                        @endif
                                    </span>
                                </td>
                                <td data-label="{{ __('orders.status_label') }}">
                                    {{-- Giá trị ngoài SalesOrder::STATUSES (dữ liệu cũ) thì hiện nguyên giá trị --}}
                                    <span class="od-badge od-tone--{{ $tone($order->status) }}">
                                        <span class="od-badge__dot"></span>{{ in_array($order->status, SalesOrder::STATUSES, true) ? __('orders.status.'.$order->status) : $order->status }}
                                    </span>
                                </td>
                                @if ($showEmployee)
                                    <td data-label="{{ __('orders.employee') }}">
                                        {{ $quotation?->employee?->full_name ?? '—' }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($orders->hasPages())
                <div class="od-pagination">{{ $orders->links() }}</div>
            @endif
        @endif
    </section>
</div>
