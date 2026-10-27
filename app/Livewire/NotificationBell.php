<?php

namespace App\Livewire;

use App\Models\Role;
use App\Services\AppointmentReminders;
use App\Services\CareReminders;
use App\Services\InvoiceNotices;
use App\Services\LeadAssignmentNotices;
use App\Services\QuotationReminders;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * Chuông thông báo trên topbar. Thông báo suy ra từ dữ liệu (odin.sql không có bảng thông báo):
 *   - Salesperson: hóa đơn vừa thanh toán (InvoiceNotices) + nhắc lịch hẹn (AppointmentReminders)
 *     + cơ hội tới lúc liên hệ lại (CareReminders) + báo giá sắp / quá hạn (QuotationReminders)
 *   - Sale Leader: Lead Sale Admin vừa phân cho đội (LeadAssignmentNotices)
 *   - Kế toán: hóa đơn tiền mặt chờ duyệt + hóa đơn vừa thanh toán (InvoiceNotices)
 * Trạng thái "đã đọc" lưu trong cache theo tài khoản (bảng cache là bảng hệ thống của Laravel);
 * badge đỏ chỉ đếm thông báo chưa đọc.
 */
class NotificationBell extends Component
{
    public function markRead(string $key): void
    {
        $this->saveRead([...$this->readKeys(), $key]);
    }

    public function markAllRead(): void
    {
        $this->saveRead($this->items()->pluck('key')->all());
    }

    /**
     * Bấm vào thông báo: đánh dấu đã đọc rồi mở trang liên quan.
     */
    public function open(string $key): void
    {
        $item = $this->items()->firstWhere('key', $key);
        if (! $item) {
            return;
        }

        $this->markRead($key);
        $this->redirect($item['url']);
    }

    public function render()
    {
        $items = $this->items();
        $read = $this->readKeys();
        $items = $items->map(fn (array $item) => $item + ['read' => in_array($item['key'], $read, true)]);

        return view('livewire.notification-bell', [
            'items'    => $items,
            'unread'   => $items->where('read', false)->count(),
            ...$this->extras(),
        ]);
    }

    private function items(): Collection
    {
        $employee = auth()->user()?->employee;

        $items = match ($employee?->role_name) {
            Role::SALESPERSON => InvoiceNotices::for($employee)->sortByDesc('at')
                ->concat(AppointmentReminders::for($employee)
                    ->concat(CareReminders::for($employee))
                    ->concat(QuotationReminders::for($employee))
                    ->sortBy('at')),
            Role::SALE_LEADER => LeadAssignmentNotices::for($employee),
            Role::ACCOUNTANT  => InvoiceNotices::for($employee),
            default           => collect(),
        };

        // Khóa nhận diện thông báo: không phụ thuộc ngôn ngữ hiển thị để đổi ngôn ngữ không làm thông báo "chưa đọc" lại
        return $items
            ->map(fn (array $item) => $item + ['key' => sha1($item['url'].'|'.$item['at']->timestamp)])
            ->values();
    }

    /**
     * Dòng mô tả dưới tiêu đề và link cuối menu theo vai trò.
     */
    private function extras(): array
    {
        return match (auth()->user()?->employee?->role_name) {
            Role::SALESPERSON => [
                'subtitle' => __('quotations.reminder.header'),
                'footer'   => ['url' => route('salesperson.appointments'), 'label' => __('appointments.view_all')],
            ],
            Role::ACCOUNTANT => [
                'subtitle' => __('invoices.notice.header', ['count' => InvoiceNotices::cashQueueCount()]),
                'footer'   => ['url' => route('accountant.invoices', ['cash_pending' => 1]), 'label' => __('invoices.notice.view_queue')],
            ],
            default => ['subtitle' => null, 'footer' => null],
        };
    }

    private function readKeys(): array
    {
        return Cache::get($this->cacheKey(), []);
    }

    /**
     * Chỉ giữ khóa của các thông báo còn hiển thị để cache không phình theo thời gian.
     */
    private function saveRead(array $keys): void
    {
        $visible = $this->items()->pluck('key');
        Cache::forever($this->cacheKey(), $visible->intersect($keys)->values()->all());
    }

    private function cacheKey(): string
    {
        return 'notifications.read.'.auth()->id();
    }
}
