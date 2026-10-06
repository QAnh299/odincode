@php
    $employee = auth()->user()?->employee;
    $name = $employee?->full_name ?? auth()->user()?->username ?? '';

    // Chữ cái đầu của tên (từ cuối trong họ tên tiếng Việt)
    $parts = preg_split('/\s+/u', trim($name));
    $initial = mb_strtoupper(mb_substr(end($parts) ?: '?', 0, 1));
@endphp

{{-- Tài khoản đang đăng nhập (menu ngang phía trên) --}}
<div class="dropdown">
    <button class="odin-account-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="odin-avatar" aria-hidden="true">{{ $initial }}</span>
        <span class="odin-account-btn__text d-none d-md-flex">
            <span class="odin-account-btn__name">{{ $name }}</span>
            @if ($employee?->role_name)
                <span class="odin-account-btn__role">{{ __('roles.'.$employee->role_name) }}</span>
            @endif
        </span>
        <svg class="odin-account-btn__chevron d-none d-md-block" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m6 9 6 6 6-6" />
        </svg>
    </button>

    <ul class="dropdown-menu dropdown-menu-end odin-account-menu">
        <li class="odin-account-menu__header">
            <span class="odin-avatar odin-avatar--lg" aria-hidden="true">{{ $initial }}</span>
            <span class="d-flex flex-column min-w-0">
                <span class="fw-semibold text-truncate">{{ $name }}</span>
                @if ($employee?->email)
                    <span class="small text-secondary text-truncate">{{ $employee->email }}</span>
                @endif
            </span>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            {{-- Chưa có trang: thay '#' bằng route khi làm chức năng --}}
            <a class="dropdown-item odin-account-menu__item" href="#">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                </svg>
                {{ __('topbar.profile') }}
            </a>
        </li>
        <li>
            <a class="dropdown-item odin-account-menu__item" href="#">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="11" width="18" height="11" rx="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                {{ __('topbar.change_password') }}
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <button type="button" class="dropdown-item odin-account-menu__item text-danger" data-bs-toggle="modal"
                data-bs-target="#logoutModal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
                </svg>
                {{ __('auth.logout') }}
            </button>
        </li>
    </ul>
</div>
