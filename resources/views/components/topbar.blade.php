@props(['title' => null])

{{-- Menu ngang phía trên (layouts/app.blade.php) --}}
<header class="odin-topbar">
    <div class="odin-topbar__left">
        {{-- Màn nhỏ: mở menu trái dạng offcanvas --}}
        <button class="odin-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas"
            data-bs-target="#odinSidebar" aria-controls="odinSidebar" aria-label="{{ __('menu.open') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                aria-hidden="true">
                <path d="M3 6h18M3 12h18M3 18h18" />
            </svg>
        </button>

        {{-- Màn ≥ lg: thu gọn / mở rộng menu trái, xử lý trong resources/js/sidebar.js --}}
        <button class="odin-icon-btn d-none d-lg-inline-flex" type="button" data-odin-sidebar-toggle
            aria-expanded="true" aria-label="{{ __('menu.toggle') }}" title="{{ __('menu.toggle') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="3" width="18" height="18" rx="2" />
                <path d="M9 3v18" />
            </svg>
        </button>

        @if ($title)
            <h1 class="odin-topbar__title">{{ $title }}</h1>
        @endif
    </div>

    <div class="odin-topbar__right">
        <x-theme-picker />

        <x-language-switcher />

        {{-- Salesperson: nhắc lịch hẹn hôm nay / ngày mai và lịch đã qua giờ chưa cập nhật (App\Services\AppointmentReminders)
             + cơ hội không liên lạc được đã tới lúc liên hệ lại (App\Services\CareReminders)
             + báo giá Nháp sắp hết hạn / quá hạn chờ khách phản hồi (App\Services\QuotationReminders), xếp theo thời điểm
             Sale Leader: Lead Sale Admin vừa phân cho đội, chờ phân cho Salesperson (App\Services\LeadAssignmentNotices) --}}
        @php
            $employee = auth()->user()?->employee;
            $bell = match ($employee?->role_name) {
                \App\Models\Role::SALESPERSON => [
                    'items'    => \App\Services\InvoiceNotices::for($employee)->sortByDesc('at')
                        ->concat(\App\Services\AppointmentReminders::for($employee)
                            ->concat(\App\Services\CareReminders::for($employee))
                            ->concat(\App\Services\QuotationReminders::for($employee))
                            ->sortBy('at'))
                        ->values(),
                    'subtitle' => __('quotations.reminder.header'),
                    'footer'   => ['url' => route('salesperson.appointments'), 'label' => __('appointments.view_all')],
                ],
                \App\Models\Role::SALE_LEADER => [
                    'items'    => $leadNotices = \App\Services\LeadAssignmentNotices::for($employee),
                    'subtitle' => null,
                    'footer'   => $leadNotices->isNotEmpty()
                        ? ['url' => route('sale_leader.leads', ['distribute' => 1]), 'label' => __('leads.notice.action')]
                        : null,
                ],
                // Kế toán: hóa đơn tiền mặt chờ duyệt + hóa đơn vừa thanh toán (App\Services\InvoiceNotices)
                \App\Models\Role::ACCOUNTANT => [
                    'items'    => \App\Services\InvoiceNotices::for($employee),
                    'subtitle' => __('invoices.notice.header', ['count' => \App\Services\InvoiceNotices::cashQueueCount()]),
                    'footer'   => ['url' => route('accountant.invoices', ['cash_pending' => 1]), 'label' => __('invoices.notice.view_queue')],
                ],
                default => ['items' => collect(), 'subtitle' => null, 'footer' => null],
            };
        @endphp
        <x-notification-bell :notifications="$bell['items']" :count="$bell['items']->count()"
            :subtitle="$bell['subtitle']" :footer="$bell['footer']" />

        <span class="odin-topbar__divider d-none d-sm-block" aria-hidden="true"></span>

        <x-account-menu />
    </div>
</header>
