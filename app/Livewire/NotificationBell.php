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
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Chuông thông báo trên topbar. Thông báo suy ra từ dữ liệu (odin.sql không có bảng thông báo):
 *   - Salesperson: hóa đơn vừa thanh toán / quá hạn (InvoiceNotices) + nhắc lịch hẹn (AppointmentReminders)
 *     + cơ hội chưa liên hệ lần nào / tới lúc liên hệ lại (CareReminders) + báo giá sắp / quá hạn (QuotationReminders)
 *   - Sale Leader: Lead Sale Admin vừa phân cho đội (LeadAssignmentNotices)
 *   - Kế toán: hóa đơn tiền mặt chờ duyệt, chuyển khoản lệch, hóa đơn vừa thanh toán (InvoiceNotices)
 * Thông báo chia theo nhóm (tab Tất cả + từng nhóm của vai trò, mỗi tab đếm số chưa đọc); vai trò chỉ có
 * một nhóm thì không hiện tab. "Đánh dấu tất cả đã đọc" áp dụng cho tab đang mở.
 * Thứ tự: chưa đọc → đã xem nhưng chưa xử lý → đã xử lý. Thông báo suy ra từ dữ liệu nên khi đã cập nhật
 * (lịch hẹn có kết quả, đã liên hệ, báo giá đã xử lý…) nguồn không còn trả về; chuông giữ lại bản chụp trong lịch sử
 * (cache theo tài khoản) và hiện ở cuối danh sách với nhãn "Đã xử lý" trong HISTORY_DAYS ngày (tối đa HISTORY_LIMIT mục).
 * Thông báo được thay bằng thông báo mới cùng trang (VD nhắc lịch hẹn → quá giờ chưa cập nhật) thì không lưu lịch sử.
 * Trang khác dispatch 'notifications-refresh' sau khi thao tác để chuông cập nhật ngay, không chờ poll.
 * Trạng thái "đã đọc" lưu trong cache theo tài khoản (bảng cache là bảng hệ thống của Laravel);
 * badge đỏ chỉ đếm thông báo chưa đọc.
 */
class NotificationBell extends Component
{
    // Tab đang mở: 'all' hoặc một nhóm trong groups()
    public string $tab = 'all';

    // Giữ thông báo đã xử lý trong lịch sử bao lâu / tối đa bao nhiêu mục
    const HISTORY_DAYS = 7;

    const HISTORY_LIMIT = 30;

    /**
     * Trang khác vừa thao tác (cập nhật lịch hẹn, ghi nhận liên hệ…): render lại để chuyển thông báo đã xử lý xuống dưới.
     */
    #[On('notifications-refresh')]
    public function refresh(): void
    {
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, $this->groups()) ? $tab : 'all';
    }

    public function markRead(string $key): void
    {
        $this->saveRead([...$this->readKeys(), $key]);
    }

    /**
     * Đánh dấu đã đọc các thông báo của tab đang mở.
     */
    public function markAllRead(): void
    {
        $this->saveRead([...$this->readKeys(), ...$this->inTab($this->items())->pluck('key')->all()]);
    }

    /**
     * Bấm vào thông báo: đánh dấu đã đọc rồi mở trang liên quan.
     */
    public function open(string $key): void
    {
        $item = $this->items()->firstWhere('key', $key);

        if ($item) {
            $this->markRead($key);
        } else {
            // Thông báo đã xử lý (trong lịch sử): chỉ mở trang
            $item = $this->history()[$key] ?? null;
        }

        if ($item) {
            $this->redirect($item['url']);
        }
    }

    public function render()
    {
        $read = $this->readKeys();
        $live = $this->items();
        $history = $this->syncHistory($live);

        // Chưa đọc → đã xem chưa xử lý (sắp xếp ổn định, giữ thứ tự gốc trong từng phần) → đã xử lý (mới xử lý trước)
        $all = $live
            ->map(fn (array $item) => $item + ['read' => in_array($item['key'], $read, true), 'done' => false])
            ->sortBy(fn (array $item) => $item['read'] ? 1 : 0)
            ->concat(collect($history)
                ->filter(fn (array $item) => $item['done_at'] !== null)
                ->sortByDesc('done_at')
                ->map(fn (array $item, string $key) => $item + ['key' => $key, 'read' => true, 'done' => true]))
            ->values();
        $groups = $this->groups();

        if (! array_key_exists($this->tab, $groups)) {
            $this->tab = 'all';
        }

        // Tab: Tất cả + từng nhóm (chỉ khi vai trò có từ 2 nhóm trở lên)
        $tabs = count($groups) > 1
            ? collect(['all' => null] + $groups)->map(fn ($group, string $key) => [
                'label'  => __('topbar.notify_tab.'.$key),
                'icon'   => $group['icon'] ?? null,
                'total'  => ($key === 'all' ? $all : $all->where('group', $key))->count(),
            ])->all()
            : [];

        $items = $this->inTab($all);

        return view('livewire.notification-bell', [
            'items'  => $items,
            'tabs'   => $tabs,
            'groups' => $groups,
            'unread' => $all->where('read', false)->count(),
            'tabUnread' => $items->where('read', false)->count(),
            ...$this->extras(),
        ]);
    }

    /**
     * Nhóm thông báo theo vai trò: khoá => icon + link "xem tất cả" ở cuối menu khi đang mở tab đó.
     *
     * @return array<string, array{icon: string, footer: ?array{url: string, label: string}}>
     */
    private function groups(): array
    {
        return match (auth()->user()?->employee?->role_name) {
            Role::SALESPERSON => [
                'care'        => ['icon' => 'phone', 'footer' => ['url' => route('salesperson.opportunities'), 'label' => __('topbar.notify_footer.care')]],
                'appointment' => ['icon' => 'calendar', 'footer' => ['url' => route('salesperson.appointments'), 'label' => __('appointments.view_all')]],
                'quotation'   => ['icon' => 'file', 'footer' => ['url' => route('salesperson.quotations'), 'label' => __('topbar.notify_footer.quotation')]],
                'invoice'     => ['icon' => 'wallet', 'footer' => ['url' => route('salesperson.invoices'), 'label' => __('topbar.notify_footer.invoice')]],
            ],
            Role::ACCOUNTANT => [
                'cash'     => ['icon' => 'cash', 'footer' => ['url' => route('accountant.invoices', ['cash_pending' => 1]), 'label' => __('invoices.notice.view_queue')]],
                'transfer' => ['icon' => 'alert', 'footer' => ['url' => route('accountant.invoices'), 'label' => __('topbar.notify_footer.invoice')]],
                'paid'     => ['icon' => 'check-circle', 'footer' => ['url' => route('accountant.invoices'), 'label' => __('topbar.notify_footer.invoice')]],
            ],
            Role::SALE_LEADER => [
                'lead' => ['icon' => 'users', 'footer' => null],
            ],
            default => [],
        };
    }

    private function inTab(Collection $items): Collection
    {
        return $this->tab === 'all' ? $items : $items->where('group', $this->tab)->values();
    }

    private function items(): Collection
    {
        $employee = auth()->user()?->employee;
        $tag = fn (Collection $items, string $group) => $items->map(fn (array $item) => $item + ['group' => $group]);

        $items = match ($employee?->role_name) {
            Role::SALESPERSON => $tag(InvoiceNotices::for($employee), 'invoice')->sortByDesc('at')
                ->concat($tag(AppointmentReminders::for($employee), 'appointment')
                    ->concat($tag(CareReminders::for($employee), 'care'))
                    ->concat($tag(QuotationReminders::for($employee), 'quotation'))
                    ->sortBy('at')),
            Role::SALE_LEADER => $tag(LeadAssignmentNotices::for($employee), 'lead'),
            // Kế toán: nhóm theo loại thông báo hóa đơn (quá hạn thanh toán chỉ có ở Salesperson)
            Role::ACCOUNTANT  => InvoiceNotices::for($employee)->map(fn (array $item) => $item + ['group' => $item['kind'] ?? 'paid']),
            default           => collect(),
        };

        // Khóa nhận diện thông báo: không phụ thuộc ngôn ngữ hiển thị để đổi ngôn ngữ không làm thông báo "chưa đọc" lại
        return $items
            ->map(fn (array $item) => $item + ['key' => sha1($item['url'].'|'.$item['at']->timestamp)])
            ->values();
    }

    /**
     * Dòng mô tả dưới tiêu đề và link cuối menu theo vai trò / tab đang mở.
     */
    private function extras(): array
    {
        $groupFooter = $this->groups()[$this->tab]['footer'] ?? null;

        return match (auth()->user()?->employee?->role_name) {
            Role::SALESPERSON => [
                // Đã có tab phân nhóm nên không cần dòng mô tả
                'subtitle' => null,
                'footer'   => $groupFooter ?? ['url' => route('salesperson.appointments'), 'label' => __('appointments.view_all')],
            ],
            Role::ACCOUNTANT => [
                'subtitle' => __('invoices.notice.header', ['count' => InvoiceNotices::cashQueueCount(auth()->user()->employee)]),
                'footer'   => $groupFooter ?? ['url' => route('accountant.invoices', ['cash_pending' => 1]), 'label' => __('invoices.notice.view_queue')],
            ],
            default => ['subtitle' => null, 'footer' => null],
        };
    }

    /**
     * Đồng bộ lịch sử với thông báo đang có: cập nhật bản chụp của thông báo còn hiện; thông báo không còn
     * (đã xử lý) thì ghi thời điểm xử lý, trừ khi đã có thông báo mới cùng trang thay thế. Bỏ mục quá hạn giữ.
     *
     * @return array<string, array{title: string, time: ?string, url: string, group: string, done_at: ?int}>
     */
    private function syncHistory(Collection $live): array
    {
        $history = $this->history();
        $before = $history;
        $now = now()->timestamp;
        $liveKeys = $live->pluck('key')->flip();
        $liveUrls = $live->pluck('url')->flip();

        foreach ($live as $item) {
            $history[$item['key']] = [
                'title'   => $item['title'],
                'time'    => $item['time'] ?? null,
                'url'     => $item['url'],
                'group'   => $item['group'],
                'done_at' => null,
            ];
        }

        foreach ($history as $key => $item) {
            if ($liveKeys->has($key) || $item['done_at'] !== null) {
                continue;
            }

            if ($liveUrls->has($item['url'])) {
                unset($history[$key]);
            } else {
                $history[$key]['done_at'] = $now;
            }
        }

        // Bỏ mục đã xử lý quá HISTORY_DAYS ngày, giữ tối đa HISTORY_LIMIT mục mới nhất
        $done = collect($history)
            ->filter(fn (array $item) => $item['done_at'] !== null)
            ->filter(fn (array $item) => $item['done_at'] >= $now - self::HISTORY_DAYS * 86400)
            ->sortByDesc('done_at')
            ->take(self::HISTORY_LIMIT);
        $history = collect($history)->filter(fn (array $item) => $item['done_at'] === null)->all() + $done->all();

        if ($history != $before) {
            Cache::forever($this->historyKey(), $history);
        }

        return $history;
    }

    private function history(): array
    {
        return Cache::get($this->historyKey(), []);
    }

    private function historyKey(): string
    {
        return 'notifications.history.'.auth()->id();
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
