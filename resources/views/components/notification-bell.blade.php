@props(['notifications' => [], 'count' => 0])

{{-- Chuông thông báo. Dữ liệu thông báo sẽ truyền vào qua $notifications khi có bảng/chức năng thông báo:
     mỗi phần tử gồm ['title' => ..., 'time' => ..., 'url' => ...] --}}
<div class="dropdown">
    <button class="odin-icon-btn position-relative" type="button" data-bs-toggle="dropdown"
        data-bs-auto-close="outside" aria-expanded="false" aria-label="{{ __('topbar.notifications') }}"
        title="{{ __('topbar.notifications') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
            stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
        </svg>
        @if ($count > 0)
            <span class="odin-bell-badge">{{ $count > 99 ? '99+' : $count }}</span>
        @endif
    </button>

    <div class="dropdown-menu dropdown-menu-end odin-notify-menu">
        <div class="odin-notify-menu__header">
            <span>{{ __('topbar.notifications') }}</span>
        </div>

        @forelse ($notifications as $notification)
            <a href="{{ $notification['url'] ?? '#' }}" class="dropdown-item odin-notify-item">
                <span class="odin-notify-item__title">{{ $notification['title'] }}</span>
                <span class="odin-notify-item__time">{{ $notification['time'] ?? '' }}</span>
            </a>
        @empty
            <div class="odin-notify-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                    <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                </svg>
                <span>{{ __('topbar.no_notifications') }}</span>
            </div>
        @endforelse
    </div>
</div>
