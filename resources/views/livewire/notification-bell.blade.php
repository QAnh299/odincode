{{-- Chuông thông báo (App\Livewire\NotificationBell). wire:ignore.self giữ trạng thái mở của dropdown Bootstrap khi Livewire cập nhật lại --}}
<div class="dropdown">
    <button class="odin-icon-btn position-relative" type="button" data-bs-toggle="dropdown"
        data-bs-auto-close="outside" aria-expanded="false" aria-label="{{ __('topbar.notifications') }}"
        title="{{ __('topbar.notifications') }}" wire:ignore.self>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
            stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
        </svg>
        @if ($unread > 0)
            <span class="odin-bell-badge">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </button>

    <div class="dropdown-menu dropdown-menu-end odin-notify-menu" wire:ignore.self>
        <div class="odin-notify-menu__header">
            <div class="odin-notify-menu__head-row">
                <span class="odin-notify-menu__title">
                    {{ __('topbar.notifications') }}
                    @if ($unread > 0)
                        <span class="odin-notify-menu__count">{{ __('topbar.unread_count', ['count' => $unread]) }}</span>
                    @endif
                </span>
                @if ($unread > 0)
                    <button type="button" class="odin-notify-menu__mark-all" wire:click="markAllRead">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M2 12.5l4.5 4.5L15 8.5" />
                            <path d="M10.5 15.5l1.5 1.5L22 7" />
                        </svg>
                        {{ __('topbar.mark_all_read') }}
                    </button>
                @endif
            </div>
            @if ($subtitle)
                <small class="odin-notify-menu__sub">{{ $subtitle }}</small>
            @endif
        </div>

        <div class="odin-notify-list">
            @forelse ($items as $notification)
                <div class="odin-notify-item {{ $notification['read'] ? 'is-read' : 'is-unread' }}"
                    wire:key="notify-{{ $notification['key'] }}">
                    <a href="{{ $notification['url'] }}" class="odin-notify-item__link"
                        wire:click.prevent="open('{{ $notification['key'] }}')">
                        <span class="odin-notify-item__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                            </svg>
                        </span>
                        <span class="odin-notify-item__body">
                            <span class="odin-notify-item__title">{{ $notification['title'] }}</span>
                            @if (! empty($notification['time']))
                                <span class="odin-notify-item__time">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="12" cy="12" r="9" />
                                        <path d="M12 7v5l3 2" />
                                    </svg>
                                    {{ $notification['time'] }}
                                </span>
                            @endif
                        </span>
                    </a>
                    @unless ($notification['read'])
                        <button type="button" class="odin-notify-item__mark" title="{{ __('topbar.mark_read') }}"
                            aria-label="{{ __('topbar.mark_read') }}"
                            wire:click="markRead('{{ $notification['key'] }}')">
                            <span class="odin-notify-item__dot" aria-hidden="true"></span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M5 12.5l4.5 4.5L19 7.5" />
                            </svg>
                        </button>
                    @endunless
                </div>
            @empty
                <div class="odin-notify-empty">
                    <span class="odin-notify-empty__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                        </svg>
                    </span>
                    <span>{{ __('topbar.no_notifications') }}</span>
                </div>
            @endforelse
        </div>

        @if ($footer)
            <a href="{{ $footer['url'] }}" class="odin-notify-menu__footer">{{ $footer['label'] }}</a>
        @endif
    </div>
</div>
