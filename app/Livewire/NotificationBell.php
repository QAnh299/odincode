<?php

namespace App\Livewire;

use App\Models\Role;
use App\Services\AppointmentReminders;
use App\Services\CareReminders;
use App\Services\InvoiceNotices;
use App\Services\LeadAssignmentNotices;
use App\Services\QuotationReminders;
use App\Services\TeamAppointmentAlerts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
{
    public string $tab = 'all';

    public const HISTORY_DAYS = 7;

    public const HISTORY_LIMIT = 30;

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

    public function markAllRead(): void
    {
        $this->saveRead([...$this->readKeys(), ...$this->inTab($this->items())->pluck('key')->all()]);
    }

    public function open(string $key): void
    {
        $item = $this->items()->firstWhere('key', $key);

        if ($item) {
            $this->markRead($key);
        } else {
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

        $all = $live
            ->map(fn (array $item) => $item + ['read' => in_array($item['key'], $read, true), 'done' => false])
            ->sortBy(fn (array $item) => (($item['kind'] ?? null) === 'transfer' ? 0 : 2) + ($item['read'] ? 1 : 0))
            ->concat(collect($history)
                ->filter(fn (array $item) => $item['done_at'] !== null)
                ->sortByDesc('done_at')
                ->map(fn (array $item, string $key) => $item + ['key' => $key, 'read' => true, 'done' => true]))
            ->values();
        $groups = $this->groups();

        if (! array_key_exists($this->tab, $groups)) {
            $this->tab = 'all';
        }

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
                'lead'        => ['icon' => 'users', 'footer' => null],
                'appointment' => ['icon' => 'calendar', 'footer' => ['url' => route('sale_leader.opportunities', ['urgent' => 1]), 'label' => __('topbar.notify_footer.team_appointment')]],
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
            Role::SALE_LEADER => $tag(TeamAppointmentAlerts::for($employee), 'appointment')
                ->concat($tag(LeadAssignmentNotices::for($employee), 'lead')),

            Role::ACCOUNTANT  => InvoiceNotices::for($employee)->map(fn (array $item) => $item + ['group' => $item['kind'] ?? 'paid']),
            default           => collect(),
        };

        return $items
            ->map(fn (array $item) => $item + ['key' => sha1($item['url'].'|'.$item['at']->timestamp)])
            ->values();
    }

    private function extras(): array
    {
        $groupFooter = $this->groups()[$this->tab]['footer'] ?? null;

        return match (auth()->user()?->employee?->role_name) {
            Role::SALESPERSON => [
                'subtitle' => null,
                'footer'   => $groupFooter ?? ['url' => route('salesperson.appointments'), 'label' => __('appointments.view_all')],
            ],
            Role::ACCOUNTANT => [
                'subtitle' => __('invoices.notice.header', ['count' => InvoiceNotices::cashQueueCount(auth()->user()->employee)]),
                'footer'   => $groupFooter ?? ['url' => route('accountant.invoices', ['cash_pending' => 1]), 'label' => __('invoices.notice.view_queue')],
            ],
            Role::SALE_LEADER => ['subtitle' => null, 'footer' => $groupFooter],
            default => ['subtitle' => null, 'footer' => null],
        };
    }

    private function syncHistory(Collection $live): array
    {
        $history = $this->history();
        $before = $history;
        $now = now()->timestamp;
        $liveKeys = $live->pluck('key')->flip();
        $liveUrls = $live->pluck('url')->flip();

        foreach ($live as $item) {
            if (($item['history'] ?? true) === false) {
                continue;
            }

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
