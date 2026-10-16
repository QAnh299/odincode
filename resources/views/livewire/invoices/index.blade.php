@php
    use App\Livewire\Invoices\Index;
    use App\Models\Invoice;
    use App\Models\SalesOrder;

    $summary = $this->summary;
    $counts = $summary['counts'];
    $activeStatuses = $this->activeStatuses();
    $showEmployee = $this->canFilterEmployee;
    $pending = $this->hasPendingChanges();
    $chips = $this->chips;

    // Số bộ lọc trong panel đang áp dụng (huy hiệu trên nút "Bộ lọc"); từ khóa nằm ở ô tìm kiếm nên không tính
    $panelCount = count(array_diff_key($chips, ['search' => true]));

    // Thẻ thống kê trạng thái: [trạng thái, icon]
    $tabs = [
        ['', 'layers'],
        [Invoice::STATUS_UNPAID, 'hourglass'],
        [Invoice::STATUS_PAID, 'check-circle'],
        [Invoice::STATUS_OVERDUE, 'alert'],
    ];

    $tone = fn (?string $status) => in_array($status, Invoice::STATUSES, true) ? strtolower($status) : 'all';
    $money = fn (float $amount) => number_format($amount, 0, ',', '.').' ₫';
@endphp

<div class="iv">
    {{-- CSS riêng của trang hóa đơn: resources/css/invoice.css (chỉ nạp ở trang này) --}}
    @assets
        @vite('resources/css/invoice.css')
    @endassets

    {{-- Tiêu đề hiển thị ở thanh trên cùng; giữ thẻ h1 ẩn cho trình đọc màn hình.
         Không có nút "Tạo hóa đơn": hóa đơn được tạo từ đơn hàng (HĐ18). --}}
    <h1 class="visually-hidden">{{ __('invoices.title') }}</h1>

    {{-- ── Khối dính khi cuộn: thẻ trạng thái + tổng tiền + thanh công cụ + chip bộ lọc ── --}}
    <div class="iv-sticky">
        <p class="iv-subtitle">{{ __('invoices.subtitle') }}</p>

        <div class="iv-overview">
            {{-- Thẻ trạng thái (bấm để lọc ngay, bấm lại bỏ lọc; số đếm theo mọi bộ lọc trừ trạng thái) --}}
            <nav class="iv-tabs" aria-label="{{ __('invoices.status_label') }}">
                @foreach ($tabs as [$key, $icon])
                    @php $current = $key === '' ? $activeStatuses === [] : $activeStatuses === [$key]; @endphp
                    <button type="button" wire:click="filterStatus('{{ $key }}')"
                        class="iv-tab iv-tone--{{ $tone($key) }} {{ $current ? 'is-current' : '' }}"
                        aria-pressed="{{ $current ? 'true' : 'false' }}">
                        <x-icon :name="$icon" />
                        <span class="iv-tab__label">{{ $key ? __('invoices.status.'.$key) : __('invoices.all') }}</span>
                        <span class="iv-tab__count">{{ $counts[$key ?: 'all'] }}</span>
                    </button>
                @endforeach
            </nav>

            {{-- Tổng tiền trong phạm vi quyền + bộ lọc hiện tại (trừ trạng thái) --}}
            <div class="iv-totals" aria-label="{{ __('invoices.totals_label') }}" title="{{ __('invoices.totals_label') }}">
                <span class="iv-total iv-total--collected">
                    <x-icon name="wallet" />
                    <span class="iv-total__label">{{ __('invoices.collected') }}</span>
                    <strong class="iv-total__value">{{ $money($summary['collected']) }}</strong>
                </span>
                <span class="iv-totals__sep" aria-hidden="true">·</span>
                <span class="iv-total iv-total--pending">
                    <x-icon name="clock" />
                    <span class="iv-total__label">{{ __('invoices.outstanding') }}</span>
                    <strong class="iv-total__value">{{ $money($summary['pending']) }}</strong>
                </span>
            </div>
        </div>

        {{-- Thanh công cụ: tìm kiếm (cập nhật ngay) + nút Bộ lọc + Xoá bộ lọc --}}
        <div class="iv-toolbar" x-data="{ open: false }" x-on:keydown.escape.window="open = false"
            x-on:invoice-filters-applied.window="open = false">
            <div class="iv-search">
                <x-icon name="search" />
                <input type="search" wire:model.live.debounce.350ms="search" placeholder="{{ __('invoices.search_placeholder') }}"
                    aria-label="{{ __('invoices.search') }}" maxlength="500">
                <span class="iv-search__spin" wire:loading.delay wire:target="search">
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                </span>

                {{-- Hướng dẫn cú pháp tìm kiếm --}}
                <span class="iv-hint" x-data="{ tip: false }" x-on:click.outside="tip = false">
                    <button type="button" class="iv-hint__btn" x-on:click="tip = ! tip" :aria-expanded="tip.toString()"
                        title="{{ __('invoices.search_help_title') }}">
                        <x-icon name="info" />
                        <span class="visually-hidden">{{ __('invoices.search_help_title') }}</span>
                    </button>
                    <span class="iv-hint__pop" x-show="tip" x-cloak x-transition.opacity role="tooltip">
                        <strong>{{ __('invoices.search_help_title') }}</strong>
                        <span class="iv-hint__list">
                            {{-- Chuỗi cố định trong lang (có thẻ <code>/<strong>), không chứa dữ liệu người dùng --}}
                            @foreach (__('invoices.search_help') as $line)
                                <span class="iv-hint__item">{!! $line !!}</span>
                            @endforeach
                        </span>
                    </span>
                </span>
            </div>

            <div class="iv-filter" x-on:click.outside="if (window.innerWidth >= 768) open = false">
                <button type="button" class="iv-btn iv-btn--filter" :class="{ 'is-open': open }" x-on:click="open = ! open"
                    :aria-expanded="open.toString()" aria-controls="iv-panel">
                    <x-icon name="filter" />
                    <span>{{ __('invoices.filters') }}</span>
                    @if ($panelCount)
                        <span class="iv-btn__badge">{{ $panelCount }}</span>
                    @endif
                    {{-- Chấm báo: đã đổi bộ lọc trong panel nhưng chưa bấm Áp dụng --}}
                    <span class="iv-btn__dot" title="{{ __('invoices.pending') }}"
                        @unless ($pending) wire:dirty wire:target="form" @endunless></span>
                </button>

                {{-- Panel bộ lọc: thả xuống trên màn rộng, drawer toàn màn hình trên màn hẹp --}}
                <div class="iv-panel" id="iv-panel" x-show="open" x-cloak x-transition.opacity role="dialog"
                    aria-label="{{ __('invoices.filters') }}">
                    <form wire:submit="applyFilters" class="iv-panel__form">
                        <div class="iv-panel__head">
                            <span class="iv-panel__title"><x-icon name="filter" />{{ __('invoices.filters') }}</span>
                            <button type="button" class="iv-panel__close" x-on:click="open = false">
                                <x-icon name="x" />
                                <span class="visually-hidden">{{ __('invoices.close') }}</span>
                            </button>
                        </div>

                        <div class="iv-panel__body">
                            {{-- Trạng thái (chọn nhiều, đồng bộ với thẻ thống kê) --}}
                            <fieldset class="iv-field">
                                <legend class="iv-field__label">{{ __('invoices.status_label') }}</legend>
                                <div class="iv-checks">
                                    @foreach (Invoice::STATUSES as $option)
                                        <label class="iv-check iv-tone--{{ $tone($option) }}" wire:key="st-{{ $option }}">
                                            <input type="checkbox" wire:model="form.statuses" value="{{ $option }}">
                                            <span>{{ __('invoices.status.'.$option) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>

                            {{-- Phương thức (chọn nhiều, gồm "Chưa chọn" = NULL) --}}
                            <fieldset class="iv-field">
                                <legend class="iv-field__label">{{ __('invoices.method_label') }}</legend>
                                <div class="iv-checks">
                                    @foreach ([...Invoice::METHODS, Index::METHOD_NONE] as $option)
                                        <label class="iv-check" wire:key="mt-{{ $option }}">
                                            <input type="checkbox" wire:model="form.methods" value="{{ $option }}">
                                            <span>{{ __('invoices.method.'.$option) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>

                            <label class="iv-field">
                                <span class="iv-field__label">{{ __('invoices.deadline_label') }}</span>
                                <select wire:model="form.deadline" class="iv-control" title="{{ __('invoices.deadline_hint') }}">
                                    <option value="">{{ __('invoices.all') }}</option>
                                    @foreach (Index::DEADLINES as $option)
                                        <option value="{{ $option }}">{{ __('invoices.deadline.'.$option) }}</option>
                                    @endforeach
                                </select>
                                <span class="iv-field__help">{{ __('invoices.deadline_hint') }}</span>
                            </label>

                            <label class="iv-field">
                                <span class="iv-field__label">{{ __('invoices.payment_type_label') }}</span>
                                <select wire:model="form.paymentType" class="iv-control">
                                    <option value="">{{ __('invoices.all') }}</option>
                                    @foreach (SalesOrder::PAYMENT_TYPES as $option)
                                        <option value="{{ $option }}">{{ __('invoices.payment_type.'.$option) }}</option>
                                    @endforeach
                                </select>
                            </label>

                            {{-- Khoảng số tiền: ô chữ để báo lỗi khi nhập số âm / chữ (cho phép 3.000.000) --}}
                            <fieldset class="iv-field">
                                <legend class="iv-field__label">{{ __('invoices.amount_range') }}</legend>
                                <div class="iv-range">
                                    <input type="text" inputmode="numeric" wire:model="form.amountFrom" maxlength="25" placeholder="0"
                                        @class(['iv-control', 'is-invalid' => $errors->has('form.amountFrom')])
                                        aria-label="{{ __('invoices.amount_from') }}" title="{{ __('invoices.amount_from') }}">
                                    <span class="iv-range__sep" aria-hidden="true">-</span>
                                    <input type="text" inputmode="numeric" wire:model="form.amountTo" maxlength="25"
                                        @class(['iv-control', 'is-invalid' => $errors->has('form.amountTo')])
                                        aria-label="{{ __('invoices.amount_to') }}" title="{{ __('invoices.amount_to') }}">
                                </div>
                                @error('form.amountFrom')
                                    <span class="iv-field__error">{{ $message }}</span>
                                @enderror
                                @error('form.amountTo')
                                    <span class="iv-field__error">{{ $message }}</span>
                                @enderror
                            </fieldset>

                            {{-- Khoảng ngày: cặp từ / đến trong một nhóm (không có thư viện date range picker) --}}
                            @foreach (['issued' => ['issuedFrom', 'issuedTo'], 'due' => ['dueFrom', 'dueTo'], 'paid' => ['paidFrom', 'paidTo']] as $group => [$from, $to])
                                <fieldset class="iv-field" wire:key="range-{{ $group }}">
                                    <legend class="iv-field__label">{{ __('invoices.'.$group.'_range') }}</legend>
                                    <div class="iv-range">
                                        <input type="date" wire:model="form.{{ $from }}" class="iv-control" max="{{ $form[$to] }}"
                                            aria-label="{{ __('invoices.'.$group.'_from') }}" title="{{ __('invoices.'.$group.'_from') }}">
                                        <span class="iv-range__sep" aria-hidden="true">-</span>
                                        <input type="date" wire:model="form.{{ $to }}" min="{{ $form[$from] }}"
                                            @class(['iv-control', 'is-invalid' => $errors->has('form.'.$to)])
                                            aria-label="{{ __('invoices.'.$group.'_to') }}" title="{{ __('invoices.'.$group.'_to') }}">
                                    </div>
                                    @error('form.'.$to)
                                        <span class="iv-field__error">{{ $message }}</span>
                                    @enderror
                                </fieldset>
                            @endforeach

                            @if ($this->canFilterTeam)
                                <label class="iv-field">
                                    <span class="iv-field__label">{{ __('invoices.team') }}</span>
                                    {{-- .live chỉ để thu hẹp danh sách nhân viên theo đội, danh sách hóa đơn chưa đổi --}}
                                    <select wire:model.live="form.team" class="iv-control">
                                        <option value="">{{ __('invoices.all_teams') }}</option>
                                        @foreach ($this->teams as $option)
                                            <option value="{{ $option->team_id }}">{{ $option->team_name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif

                            @if ($showEmployee)
                                <label class="iv-field">
                                    <span class="iv-field__label">{{ __('invoices.employee') }}</span>
                                    <select wire:model="form.employee" class="iv-control">
                                        <option value="">{{ __('invoices.all_employees') }}</option>
                                        @foreach ($this->formEmployeeOptions as $option)
                                            <option value="{{ $option->employee_id }}" wire:key="emp-{{ $option->employee_id }}">{{ $option->full_name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif

                            {{-- Công tắc: hàng đợi duyệt tiền mặt của kế toán (Chờ thanh toán + Tiền mặt, HĐ30 - HĐ32) --}}
                            <label class="iv-switch iv-field--wide">
                                <input type="checkbox" role="switch" wire:model="form.cashPending">
                                <span class="iv-switch__track" aria-hidden="true"><span class="iv-switch__thumb"></span></span>
                                <span class="iv-switch__text">
                                    <span class="iv-switch__label">{{ __('invoices.cash_pending') }}</span>
                                    <span class="iv-field__help">{{ __('invoices.cash_pending_hint') }}</span>
                                </span>
                            </label>
                        </div>

                        <div class="iv-panel__foot">
                            {{-- Nhắc khi bộ lọc trên form khác bộ lọc đang áp dụng (wire:dirty: đổi phía trình duyệt chưa gửi) --}}
                            <span class="iv-pending" role="status" @unless ($pending) wire:dirty wire:target="form" @endunless>
                                <x-icon name="info" />{{ __('invoices.pending') }}
                            </span>
                            <button type="button" class="iv-btn iv-btn--ghost" wire:click="resetForm">
                                <x-icon name="refresh" />{{ __('invoices.reset_form') }}
                            </button>
                            <button type="submit" class="iv-btn iv-btn--primary">
                                <span wire:loading.remove wire:target="applyFilters"><x-icon name="check" /></span>
                                <span wire:loading wire:target="applyFilters" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                {{ __('invoices.apply') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Chỉ hiện khi có bộ lọc / từ khóa đang áp dụng --}}
            @if ($chips !== [])
                <button type="button" class="iv-btn iv-btn--ghost iv-btn--clear" wire:click="resetFilters">
                    <x-icon name="refresh" /><span>{{ __('invoices.reset') }}</span>
                </button>
            @endif
        </div>

        {{-- Dòng gợi ý cú pháp tìm kiếm --}}
        <p class="iv-hintline"><x-icon name="info" />{{ __('invoices.search_hint') }}</p>

        {{-- Bộ lọc đang áp dụng (hiện cả khi panel đã đóng) --}}
        @if ($chips !== [])
            <div class="iv-chips" aria-label="{{ __('invoices.active_filters') }}">
                <span class="iv-chips__title">{{ __('invoices.active_filters') }}:</span>
                @foreach ($chips as $key => $label)
                    <span class="iv-chip" wire:key="chip-{{ $key }}">
                        <span class="iv-chip__text">{{ $label }}</span>
                        <button type="button" class="iv-chip__remove" wire:click="clearFilter('{{ $key }}')"
                            title="{{ __('invoices.remove_filter') }}">
                            <x-icon name="x" />
                            <span class="visually-hidden">{{ __('invoices.remove_filter') }}: {{ $label }}</span>
                        </button>
                    </span>
                @endforeach
                <button type="button" class="iv-chips__clear" wire:click="resetFilters">{{ __('invoices.reset_all') }}</button>
            </div>
        @endif
    </div>

    {{-- ── Kết quả ──────────────────────────────────────────────────── --}}
    <section class="iv-card iv-results">
        <div class="iv-results__bar">
            <span>
                {!! trans_choice('invoices.result_count', $invoices->total(), ['count' => '<strong>'.$invoices->total().'</strong>']) !!}
            </span>
            <span class="iv-results__tools">
                <span class="iv-results__loading" wire:loading.delay wire:target.except="form.team">
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    {{ __('invoices.loading') }}
                </span>
                <label class="iv-sort">
                    <x-icon name="sort" />
                    <span class="visually-hidden">{{ __('invoices.sort_by') }}</span>
                    <select wire:model.live="sort" class="iv-control iv-control--sm" aria-label="{{ __('invoices.sort_by') }}">
                        @foreach (Index::SORTS as $option)
                            <option value="{{ $option }}">{{ __('invoices.sort.'.$option) }}</option>
                        @endforeach
                    </select>
                </label>
            </span>
        </div>

        @if ($invoices->isEmpty())
            <div class="iv-empty">
                <span class="iv-empty__icon"><x-icon name="inbox" /></span>
                @if ($this->hasAnyInvoice)
                    <p class="iv-empty__title">{{ __('invoices.empty') }}</p>
                    <p class="iv-empty__sub">{{ __('invoices.empty_sub') }}</p>
                    <button type="button" class="iv-btn iv-btn--primary" wire:click="resetFilters">
                        <x-icon name="refresh" />{{ __('invoices.reset_all') }}
                    </button>
                @else
                    <p class="iv-empty__title">{{ __('invoices.empty_none') }}</p>
                    <p class="iv-empty__sub">{{ __('invoices.empty_none_sub') }}</p>
                @endif
            </div>
        @else
            <div class="iv-table-wrap" wire:loading.class="is-loading" wire:target.except="form.team">
                <table class="iv-table">
                    <thead>
                        <tr>
                            <th>{{ __('invoices.code') }}</th>
                            <th>{{ __('invoices.customer') }}</th>
                            <th>{{ __('invoices.order') }}</th>
                            <th class="text-lg-end">{{ __('invoices.amount') }}</th>
                            <th>{{ __('invoices.issued_date') }}</th>
                            <th>{{ __('invoices.due_date') }}</th>
                            <th>{{ __('invoices.method_label') }}</th>
                            <th>{{ __('invoices.status_label') }}</th>
                            @if ($showEmployee)
                                <th>{{ __('invoices.employee') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            @php
                                $isPaid = $invoice->status === Invoice::STATUS_PAID;
                                $daysLeft = $isPaid ? null : $invoice->daysUntilDue();
                                $order = $invoice->order;
                                $lead = $order?->quotation?->opportunity?->lead;
                            @endphp
                            {{-- Chưa có trang chi tiết hóa đơn: khi có route {vai trò}.invoices.show thì gắn link vào mã hóa đơn
                                 và cho bấm cả dòng (giống danh sách báo giá) --}}
                            <tr wire:key="invoice-{{ $invoice->invoice_id }}">
                                <td data-label="{{ __('invoices.code') }}">
                                    <span class="iv-code">
                                        <span class="iv-code__icon"><x-icon name="file" /></span>
                                        <span class="iv-stack">
                                            <span class="iv-code__id">{{ $this->highlight($invoice->invoice_id) }}</span>
                                            <span class="iv-stack__sub">{{ __('invoices.installment', ['number' => $invoice->payment_installment]) }}</span>
                                        </span>
                                    </span>
                                </td>
                                <td data-label="{{ __('invoices.customer') }}">
                                    <span class="iv-stack">
                                        <span class="iv-stack__main">{{ $lead ? $this->highlight($lead->full_name) : '—' }}</span>
                                        <span class="iv-stack__sub iv-stack__sub--mono">{{ $order?->quotation?->opportunity_id ?? '—' }}</span>
                                    </span>
                                </td>
                                <td data-label="{{ __('invoices.order') }}">
                                    <span class="iv-stack">
                                        <span class="iv-mono">{{ $this->highlight($invoice->order_id) }}</span>
                                        <span class="iv-stack__sub">
                                            {{ $order && in_array($order->payment_type, SalesOrder::PAYMENT_TYPES, true) ? __('invoices.payment_type.'.$order->payment_type) : '—' }}
                                        </span>
                                    </span>
                                </td>
                                <td data-label="{{ __('invoices.amount') }}" class="text-lg-end">
                                    <span class="iv-amount">{{ $money((float) $invoice->payment_amount) }}</span>
                                </td>
                                <td data-label="{{ __('invoices.issued_date') }}">
                                    {{ $invoice->issued_date?->format('d/m/Y') }}
                                </td>
                                <td data-label="{{ __('invoices.due_date') }}">
                                    <span class="iv-stack">
                                        <span class="iv-stack__main">{{ $invoice->due_date?->format('d/m/Y') }}</span>
                                        {{-- Nhãn tính ra từ hạn thanh toán, không đổi dữ liệu; hóa đơn đã thanh toán không có dòng này --}}
                                        @if (! $isPaid)
                                            @if ($daysLeft < 0)
                                                <span class="iv-due iv-due--late">
                                                    <x-icon name="alert" />{{ __('invoices.days_late', ['days' => -$daysLeft]) }}
                                                </span>
                                            @else
                                                <span @class(['iv-due', 'iv-due--soon' => $daysLeft <= Index::SOON_DAYS])>
                                                    <x-icon name="hourglass" />{{ __('invoices.days_left', ['days' => $daysLeft]) }}
                                                </span>
                                            @endif
                                        @endif
                                    </span>
                                </td>
                                <td data-label="{{ __('invoices.method_label') }}">
                                    <span class="iv-stack">
                                        @if (in_array($invoice->payment_method, Invoice::METHODS, true))
                                            <span class="iv-method">
                                                <x-icon :name="$invoice->payment_method === Invoice::METHOD_CASH ? 'cash' : 'wallet'" />
                                                {{ __('invoices.method.'.$invoice->payment_method) }}
                                            </span>
                                        @else
                                            <span class="iv-muted">—</span>
                                        @endif
                                        @if ($invoice->accountant)
                                            <span class="iv-stack__sub">{{ __('invoices.accountant', ['name' => $invoice->accountant->full_name]) }}</span>
                                        @endif
                                    </span>
                                </td>
                                <td data-label="{{ __('invoices.status_label') }}">
                                    <span class="iv-stack">
                                        <span class="iv-badge iv-tone--{{ $tone($invoice->status) }}">
                                            {{-- Giá trị ngoài Invoice::STATUSES (dữ liệu cũ) thì hiện nguyên giá trị --}}
                                            <span class="iv-badge__dot"></span>{{ in_array($invoice->status, Invoice::STATUSES, true) ? __('invoices.status.'.$invoice->status) : $invoice->status }}
                                        </span>
                                        @if ($isPaid && $invoice->payment_date)
                                            <span class="iv-stack__sub">{{ $invoice->payment_date->format('d/m/Y H:i') }}</span>
                                        @endif
                                    </span>
                                </td>
                                @if ($showEmployee)
                                    <td data-label="{{ __('invoices.employee') }}">
                                        {{ $invoice->createdBy?->full_name ?? '—' }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($invoices->hasPages())
                <div class="iv-pagination">{{ $invoices->links() }}</div>
            @endif
        @endif
    </section>
</div>
