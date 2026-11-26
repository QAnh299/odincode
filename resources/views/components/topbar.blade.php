@props(['title' => null])

<header class="odin-topbar">
    <div class="odin-topbar__left">
        <button class="odin-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas"
            data-bs-target="#odinSidebar" aria-controls="odinSidebar" aria-label="{{ __('menu.open') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                aria-hidden="true">
                <path d="M3 6h18M3 12h18M3 18h18" />
            </svg>
        </button>

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
        @if (auth()->user()?->role_name === \App\Models\Role::SALESPERSON)
            <livewire:task-calendar />
        @endif

        <livewire:notification-bell />

        <span class="odin-topbar__divider d-none d-sm-block" aria-hidden="true"></span>

        <x-account-menu />
    </div>
</header>
