@php
    use Illuminate\Support\Facades\Route;

    $role = auth()->user()?->role_name;
    $items = config('sidebar.items');

    // Link của 1 mục: route name = "{vai trò}.{route}", chưa có route thì '#'
    $link = function (string $key) use ($items, $role) {
        $routeName = "$role.{$items[$key]['route']}";

        return [
            'key' => $key,
            'url' => Route::has($routeName) ? route($routeName) : '#',
            'active' => request()->routeIs($routeName, "$routeName.*"),
        ];
    };

    // Dựng menu theo config/sidebar.php: 'key' là mục thường, 'key' => [...] là mục có menu con
    $menu = [];
    foreach (config("sidebar.roles.$role", []) as $k => $v) {
        $key = is_int($k) ? $v : $k;
        $entry = $link($key);
        $entry['icon'] = $items[$key]['icon'] ?? null;
        $entry['children'] = is_int($k) ? [] : array_map($link, $v);

        if ($entry['children']) {
            $entry['active'] = in_array(true, array_column($entry['children'], 'active'), true);
        }

        $menu[] = $entry;
    }

    // Icon nét mảnh (24x24, stroke) cho từng mục menu
    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'lead'      => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>',
        'opportunity' => '<rect x="3" y="3" width="5" height="18" rx="1"/><rect x="10" y="3" width="5" height="12" rx="1"/><rect x="17" y="3" width="5" height="8" rx="1"/>',
        'employee'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'quotation' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
        'order'     => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        'invoice'   => '<path d="M4 2v20l3-2 3 2 3-2 3 2 3-2 1 .67V2l-1 .67L16 2l-3 2-3-2-3 2z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'student'   => '<path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
        'course'    => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
        'voucher'   => '<path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1.5"/>',
    ];
@endphp

<aside class="offcanvas-lg offcanvas-start odin-sidebar" tabindex="-1" id="odinSidebar"
    aria-label="{{ __('menu.main') }}">
    <div class="odin-sidebar__inner">
        <div class="odin-sidebar__brand">
            <a href="{{ route('home') }}" class="odin-sidebar__logo">
                {{-- Logo nền trong suốt, chữ trắng; khi thu gọn chỉ hiện phần biểu tượng --}}
                <img class="odin-sidebar__logo-full" src="{{ asset('images/logoodin-sidebar.png') }}"
                    alt="{{ config('app.name') }}">
                <img class="odin-sidebar__logo-mark" src="{{ asset('images/logoodin-mark.png') }}" alt="">
            </a>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas"
                data-bs-target="#odinSidebar" aria-label="{{ __('menu.close') }}"></button>
        </div>

        <nav class="odin-sidebar__nav">
            <ul class="odin-menu">
                @foreach ($menu as $entry)
                    @if ($entry['children'])
                        {{-- Mục lớn có menu con: bấm để mở/đóng, xử lý trong resources/js/sidebar.js --}}
                        <li class="odin-menu__group {{ $entry['active'] ? 'is-open' : '' }}">
                            <button type="button"
                                class="odin-menu__link odin-menu__toggle {{ $entry['active'] ? 'has-active' : '' }}"
                                data-odin-submenu-toggle aria-expanded="{{ $entry['active'] ? 'true' : 'false' }}"
                                aria-controls="odin-submenu-{{ $entry['key'] }}" title="{{ __("menu.{$entry['key']}") }}">
                                <svg class="odin-menu__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    {!! $icons[$entry['icon']] ?? '' !!}
                                </svg>
                                <span class="odin-menu__label">{{ __("menu.{$entry['key']}") }}</span>
                                <svg class="odin-menu__caret" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m6 9 6 6 6-6" />
                                </svg>
                            </button>

                            <div class="odin-submenu" id="odin-submenu-{{ $entry['key'] }}">
                                <ul class="odin-submenu__list">
                                    @foreach ($entry['children'] as $child)
                                        <li>
                                            <a href="{{ $child['url'] }}"
                                                class="odin-submenu__link {{ $child['active'] ? 'is-active' : '' }}"
                                                @if ($child['active']) aria-current="page" @endif>
                                                {{ __("menu.{$child['key']}") }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </li>
                    @else
                        <li>
                            <a href="{{ $entry['url'] }}"
                                class="odin-menu__link {{ $entry['active'] ? 'is-active' : '' }}"
                                title="{{ __("menu.{$entry['key']}") }}"
                                @if ($entry['active']) aria-current="page" @endif>
                                <svg class="odin-menu__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    {!! $icons[$entry['icon']] ?? '' !!}
                                </svg>
                                <span class="odin-menu__label">{{ __("menu.{$entry['key']}") }}</span>
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>

        <div class="odin-sidebar__footer">
            {{-- Đăng xuất: mở popup xác nhận (components/logout-modal.blade.php) --}}
            <button type="button" class="odin-menu__link odin-sidebar__action odin-sidebar__logout"
                data-bs-toggle="modal" data-bs-target="#logoutModal" title="{{ __('auth.logout') }}">
                <svg class="odin-menu__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
                </svg>
                <span class="odin-menu__label">{{ __('auth.logout') }}</span>
            </button>
        </div>
    </div>
</aside>
