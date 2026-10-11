@php
    use App\Models\Quotation;
    use Illuminate\Support\Facades\Lang;
    use Illuminate\Support\Facades\Route;

    $quotation = $this->quotation;
    $prefix = $this->routePrefix;
    $listUrl = route($prefix.'.quotations');

    $status = $quotation->status;
    $tone = in_array($status, Quotation::STATUSES, true) ? strtolower($status) : 'all';
    $isDraft = $status === Quotation::STATUS_DRAFT;
    $isRejected = $status === Quotation::STATUS_REJECTED;
    $daysLeft = $isDraft ? $quotation->daysUntilExpiry() : null;
    $isExpired = $quotation->isExpired();

    $subtotal = $quotation->subtotal();
    $discount = $quotation->discountAmount();
    $total = $quotation->totalAmount();

    $voucher = $quotation->voucher;
    $order = $quotation->order;
    $invoiceCount = $order?->invoices->count() ?? 0;
    $opportunity = $quotation->opportunity;
    $lead = $opportunity?->lead;

    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
    $percent = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').'%';
    $dash = fn ($value) => filled($value) ? $value : '—';

    // Nhãn từ lang; giá trị chưa có bản dịch (dữ liệu cũ) thì hiện nguyên văn
    $label = fn (string $group, ?string $value) => $value === null ? '—'
        : (Lang::has('quotations.'.$group.'.'.$value) ? __('quotations.'.$group.'.'.$value) : $value);

    // Link chỉ tạo khi vai trò hiện tại có route tương ứng
    $opportunityUrl = $opportunity && Route::has($prefix.'.opportunities.show')
        ? route($prefix.'.opportunities.show', $opportunity) : null;
    $voucherUrl = $voucher && Route::has($prefix.'.vouchers.show')
        ? route($prefix.'.vouchers.show', $voucher) : null;

    // Tiến trình suy ra từ dữ liệu có sẵn: [key, icon, đã tới?, nội dung]
    $paid = (float) ($order?->paid_amount ?? 0);
    $steps = [
        ['quotation', 'file', true, __('quotations.status.'.$status)],
        ['order', 'layers', $order !== null, $order?->order_id ?? __('quotations.detail.none_yet')],
        ['invoice', 'ticket', $invoiceCount > 0, $invoiceCount > 0 ? trans_choice('quotations.detail.invoice_count', $invoiceCount, ['count' => $invoiceCount]) : __('quotations.detail.none_yet')],
        ['payment', 'wallet', $paid > 0, $order ? __('quotations.detail.paid_of', ['paid' => $money($paid), 'total' => $money($order->total_amount)]) : __('quotations.detail.none_yet')],
    ];
@endphp

<div class="qt qt-detail">
    {{-- CSS riêng của trang báo giá: resources/css/quotation.css (dùng chung với trang danh sách) --}}
    @assets
        @vite('resources/css/quotation.css')
    @endassets

    {{-- ── Điều hướng ───────────────────────────────────────────────── --}}
    <nav class="qt-crumb" aria-label="breadcrumb">
        <a href="{{ $listUrl }}" class="qt-crumb__back">
            <x-icon name="arrow-left" />{{ __('quotations.detail.back') }}
        </a>
        <span class="qt-crumb__path">
            <a href="{{ $listUrl }}">{{ __('quotations.title') }}</a>
            <x-icon name="chevron" />
            <span aria-current="page">{{ $quotation->quotation_id }}</span>
        </span>
    </nav>

    {{-- ── Đầu trang: mã + trạng thái + hạn ────────────────────────── --}}
    <header class="qt-detail-head">
        <h1 class="qt-detail-head__title">
            <span class="qt-detail-head__icon"><x-icon name="file" /></span>
            <span class="qt-code__id">{{ $quotation->quotation_id }}</span>
        </h1>
        <span class="qt-badge qt-tone--{{ $tone }}">
            <span class="qt-badge__dot"></span>{{ $label('status', $status) }}
        </span>
        <span class="qt-detail-head__meta">
            <x-icon name="calendar" />{{ __('quotations.detail.expires_on', ['date' => $quotation->expiry_date?->format('d/m/Y') ?? '—']) }}
        </span>
        @if ($isDraft)
            @if ($isExpired)
                <span class="qt-expiry qt-expiry--over"><x-icon name="alert" />{{ __('quotations.overdue') }}</span>
            @else
                <span class="qt-expiry"><x-icon name="hourglass" />{{ __('quotations.days_left', ['days' => $daysLeft]) }}</span>
            @endif
        @endif
    </header>

    {{-- ── Thanh thao tác (chỉ SP lập báo giá + báo giá Nháp). CHƯA có chức năng:
         ở bước sau bỏ disabled và gắn wire:click="{{ action }}" theo $button['action'] ── --}}
    @if ($this->actions !== [])
        <section class="qt-actions" aria-label="{{ __('quotations.detail.actions') }}">
            <div class="qt-actions__buttons">
                @foreach ($this->actions as $button)
                    <button type="button" class="qt-btn qt-btn--{{ $button['tone'] }}" disabled
                        title="{{ __('quotations.detail.coming_soon') }}" data-action="{{ $button['action'] }}">
                        <x-icon :name="$button['icon']" />{{ __('quotations.detail.'.$button['key']) }}
                    </button>
                @endforeach
            </div>
            @if ($isExpired)
                <p class="qt-actions__warning"><x-icon name="alert" />{{ __('quotations.detail.expired_warning') }}</p>
            @endif
        </section>
    @endif

    {{-- ── Tiến trình: Báo giá → Đơn hàng → Hóa đơn → Thanh toán ────── --}}
    <section class="qt-card qt-progress" aria-label="{{ __('quotations.detail.progress') }}">
        <ol class="qt-progress__steps">
            @foreach ($steps as $i => [$key, $icon, $reached, $text])
                @php $muted = ! $reached || ($isRejected && $key !== 'quotation'); @endphp
                <li @class(['qt-progress__step', 'is-done' => ! $muted, 'is-muted' => $muted,
                    'qt-tone--'.$tone => $key === 'quotation'])>
                    <span class="qt-progress__dot"><x-icon :name="$icon" /></span>
                    <span class="qt-progress__text">
                        <span class="qt-progress__label">{{ $i + 1 }}. {{ __('quotations.detail.step_'.$key) }}</span>
                        <span class="qt-progress__value">{{ $text }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
        @if ($isRejected)
            <p class="qt-progress__note"><x-icon name="info" />{{ __('quotations.detail.rejected_note') }}</p>
        @endif
    </section>

    <div class="qt-detail-grid">
        {{-- ── Cột trái: thông tin chung, khóa học + tổng tiền, voucher, đơn hàng ── --}}
        <div class="qt-detail-main">
            <section class="qt-card qt-panel-card">
                <h2 class="qt-section-title">{{ __('quotations.detail.general') }}</h2>
                <dl class="qt-info">
                    <div class="qt-info__row">
                        <dt>{{ __('quotations.code') }}</dt>
                        <dd><span class="qt-mono">{{ $quotation->quotation_id }}</span></dd>
                    </div>
                    <div class="qt-info__row">
                        <dt>{{ __('quotations.status_label') }}</dt>
                        <dd>
                            <span class="qt-badge qt-tone--{{ $tone }}"><span class="qt-badge__dot"></span>{{ $label('status', $status) }}</span>
                        </dd>
                    </div>
                    <div class="qt-info__row">
                        <dt>{{ __('quotations.detail.created_at') }}</dt>
                        <dd>{{ $quotation->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                    </div>
                    <div class="qt-info__row">
                        <dt>{{ __('quotations.detail.updated_at') }}</dt>
                        <dd>{{ $quotation->updated_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                    </div>
                    <div class="qt-info__row">
                        <dt>{{ __('quotations.detail.expiry_date') }}</dt>
                        <dd>
                            {{ $quotation->expiry_date?->format('d/m/Y') ?? '—' }}
                            @if ($isDraft)
                                @if ($isExpired)
                                    <span class="qt-expiry qt-expiry--over"><x-icon name="alert" />{{ __('quotations.overdue') }}</span>
                                @else
                                    <span class="qt-expiry"><x-icon name="hourglass" />{{ __('quotations.days_left', ['days' => $daysLeft]) }}</span>
                                @endif
                            @endif
                        </dd>
                    </div>
                    <div class="qt-info__row">
                        <dt>{{ __('quotations.detail.employee') }}</dt>
                        <dd>
                            {{ $dash($quotation->employee?->full_name) }}
                            @if ($quotation->employee?->team)
                                <span class="qt-muted">· {{ $quotation->employee->team->team_name }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="qt-info__row qt-info__row--wide">
                        <dt>{{ __('quotations.detail.notes') }}</dt>
                        {{-- Giữ xuống dòng bằng CSS (white-space: pre-line), nội dung vẫn được escape --}}
                        <dd class="qt-notes">{{ filled($quotation->notes) ? $quotation->notes : __('quotations.detail.no_notes') }}</dd>
                    </div>
                </dl>
            </section>

            <section class="qt-card qt-results">
                <div class="qt-section-head">
                    <h2 class="qt-section-title">{{ __('quotations.detail.courses') }}</h2>
                </div>

                @if ($quotation->details->isEmpty())
                    <p class="qt-detail-empty">{{ __('quotations.detail.no_courses') }}</p>
                @else
                    <div class="qt-table-wrap">
                        <table class="qt-table qt-table--lines">
                            <thead>
                                <tr>
                                    <th>{{ __('quotations.detail.no') }}</th>
                                    <th>{{ __('quotations.detail.course_id') }}</th>
                                    <th>{{ __('quotations.detail.course_name') }}</th>
                                    <th class="text-lg-end">{{ __('quotations.detail.unit_price') }}</th>
                                    <th class="text-lg-center">{{ __('quotations.detail.quantity') }}</th>
                                    <th class="text-lg-end">{{ __('quotations.detail.vat') }}</th>
                                    <th class="text-lg-end">{{ __('quotations.detail.line_total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($quotation->details as $i => $line)
                                    <tr wire:key="line-{{ $line->course_id }}">
                                        <td data-label="{{ __('quotations.detail.no') }}">{{ $i + 1 }}</td>
                                        <td data-label="{{ __('quotations.detail.course_id') }}"><span class="qt-mono">{{ $line->course_id }}</span></td>
                                        <td data-label="{{ __('quotations.detail.course_name') }}">{{ $dash($line->course?->course_name) }}</td>
                                        <td data-label="{{ __('quotations.detail.unit_price') }}" class="text-lg-end">{{ $money($line->unit_price) }}</td>
                                        <td data-label="{{ __('quotations.detail.quantity') }}" class="text-lg-center">{{ $line->quantity }}</td>
                                        <td data-label="{{ __('quotations.detail.vat') }}" class="text-lg-end">{{ $percent($line->VAT) }}</td>
                                        {{-- line_total lấy đúng giá trị đã lưu, không tính lại --}}
                                        <td data-label="{{ __('quotations.detail.line_total') }}" class="text-lg-end"><strong>{{ $money($line->line_total) }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Tổng tiền: tạm tính − giảm giá voucher = tổng cộng (không lưu trong CSDL) --}}
                <dl class="qt-totals">
                    <div class="qt-totals__row">
                        <dt>{{ __('quotations.detail.subtotal') }}</dt>
                        <dd>{{ $money($subtotal) }}</dd>
                    </div>
                    @if ($voucher)
                        <div class="qt-totals__row qt-totals__row--discount">
                            <dt>{{ __('quotations.detail.discount', ['code' => $voucher->voucher_id, 'value' => $voucher->displayValue()]) }}</dt>
                            <dd>−{{ $money($discount) }}</dd>
                        </div>
                    @endif
                    <div class="qt-totals__row qt-totals__row--grand">
                        <dt>{{ __('quotations.detail.grand_total') }}</dt>
                        <dd>{{ $money($total) }}</dd>
                    </div>
                </dl>
            </section>

            @if ($voucher)
                <section class="qt-card qt-panel-card">
                    <h2 class="qt-section-title">{{ __('quotations.detail.voucher') }}</h2>
                    <dl class="qt-info">
                        <div class="qt-info__row">
                            <dt>{{ __('quotations.detail.voucher_code') }}</dt>
                            <dd>
                                @if ($voucherUrl)
                                    <a href="{{ $voucherUrl }}" class="qt-link qt-mono">{{ $voucher->voucher_id }}</a>
                                @else
                                    <span class="qt-mono">{{ $voucher->voucher_id }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="qt-info__row">
                            <dt>{{ __('quotations.detail.voucher_type') }}</dt>
                            <dd>{{ Lang::has('vouchers.type.'.$voucher->discount_type) ? __('vouchers.type.'.$voucher->discount_type) : $voucher->discount_type }}</dd>
                        </div>
                        <div class="qt-info__row">
                            <dt>{{ __('quotations.detail.voucher_value') }}</dt>
                            <dd><strong>{{ $voucher->displayValue() }}</strong></dd>
                        </div>
                        <div class="qt-info__row">
                            <dt>{{ __('quotations.detail.voucher_validity') }}</dt>
                            <dd>{{ $voucher->start_date?->format('d/m/Y') }} – {{ $voucher->end_date?->format('d/m/Y') }}</dd>
                        </div>
                        <div class="qt-info__row">
                            <dt>{{ __('quotations.status_label') }}</dt>
                            <dd>{{ __('vouchers.state.'.$voucher->state()) }}</dd>
                        </div>
                    </dl>
                    @unless ($voucher->isValid())
                        <p class="qt-detail-note"><x-icon name="info" />{{ __('quotations.detail.voucher_invalid') }}</p>
                    @endunless
                </section>
            @endif

            @if ($order)
                {{-- Chưa có trang chi tiết đơn hàng: sau này gắn link route($prefix.'.orders.show', $order) vào mã đơn --}}
                <section class="qt-card qt-panel-card">
                    <h2 class="qt-section-title">{{ __('quotations.detail.order') }}</h2>
                    <dl class="qt-info">
                        <div class="qt-info__row">
                            <dt>{{ __('quotations.detail.order_id') }}</dt>
                            <dd><span class="qt-mono">{{ $order->order_id }}</span></dd>
                        </div>
                        <div class="qt-info__row">
                            <dt>{{ __('quotations.detail.order_created') }}</dt>
                            <dd>{{ $order->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="qt-info__row">
                            <dt>{{ __('quotations.detail.order_status') }}</dt>
                            <dd>{{ $label('order_status', $order->status) }}</dd>
                        </div>
                        <div class="qt-info__row">
                            <dt>{{ __('quotations.detail.payment_type') }}</dt>
                            <dd>{{ filled($order->payment_type) ? $label('payment_type', $order->payment_type) : __('quotations.payment_type.none') }}</dd>
                        </div>
                        <div class="qt-info__row">
                            <dt>{{ __('quotations.detail.order_total') }}</dt>
                            <dd><strong>{{ $money($order->total_amount) }}</strong></dd>
                        </div>
                        <div class="qt-info__row">
                            <dt>{{ __('quotations.detail.paid') }}</dt>
                            <dd>{{ $money($order->paid_amount) }}</dd>
                        </div>
                        <div class="qt-info__row">
                            <dt>{{ __('quotations.detail.remaining') }}</dt>
                            <dd>{{ $money($order->remaining_amount) }}</dd>
                        </div>
                        <div class="qt-info__row">
                            <dt>{{ __('quotations.detail.invoices') }}</dt>
                            <dd>{{ $invoiceCount }}</dd>
                        </div>
                    </dl>
                </section>
            @endif
        </div>

        {{-- ── Cột phải: khách hàng + cơ hội ───────────────────────────── --}}
        <aside class="qt-detail-side">
            <section class="qt-card qt-panel-card">
                <h2 class="qt-section-title">{{ __('quotations.detail.customer') }}</h2>
                <dl class="qt-info qt-info--stack">
                    <div class="qt-info__row">
                        <dt>{{ __('quotations.detail.full_name') }}</dt>
                        <dd><strong>{{ $dash($lead?->full_name) }}</strong></dd>
                    </div>
                    <div class="qt-info__row">
                        <dt>{{ __('quotations.detail.email') }}</dt>
                        <dd>{{ $dash($lead?->email) }}</dd>
                    </div>
                    <div class="qt-info__row">
                        <dt>{{ __('quotations.detail.phone') }}</dt>
                        <dd>{{ $dash($lead?->phone) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="qt-card qt-panel-card">
                <h2 class="qt-section-title">{{ __('quotations.detail.opportunity') }}</h2>
                <dl class="qt-info qt-info--stack">
                    <div class="qt-info__row">
                        <dt>{{ __('quotations.detail.opportunity_id') }}</dt>
                        <dd>
                            @if ($opportunityUrl)
                                <a href="{{ $opportunityUrl }}" class="qt-link qt-mono">{{ $quotation->opportunity_id }}</a>
                            @else
                                <span class="qt-mono">{{ $quotation->opportunity_id }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="qt-info__row">
                        <dt>{{ __('quotations.detail.stage') }}</dt>
                        <dd>{{ $dash($opportunity?->stage?->stage_name) }}</dd>
                    </div>
                    <div class="qt-info__row">
                        <dt>{{ __('quotations.detail.opportunity_state') }}</dt>
                        <dd>{{ $label('opportunity_status', $opportunity?->status) }}</dd>
                    </div>
                </dl>
            </section>
        </aside>
    </div>
</div>
