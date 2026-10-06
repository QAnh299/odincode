<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' – ' : '' }}{{ config('app.name') }}</title>

    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    {{-- Áp màu giao diện + trạng thái thu gọn menu trước khi vẽ trang để không bị nháy --}}
    <script>
        try {
            var root = document.documentElement;
            var theme = JSON.parse(localStorage.getItem('odin.theme'));
            if (theme && theme.vars) {
                Object.keys(theme.vars).forEach(function (name) {
                    root.style.setProperty(name, theme.vars[name]);
                });
            }
            if (localStorage.getItem('odin.sidebar.collapsed') === '1') {
                root.classList.add('odin-sidebar-collapsed');
            }
        } catch (e) {}
    </script>

    @vite(['resources/css/app.css', 'resources/css/sidebar.css', 'resources/css/theme.css', 'resources/css/topbar.css',
        'resources/js/app.js'])
    @livewireStyles
</head>

<body>
    <div class="odin-shell">
        {{-- Menu dọc bên trái theo vai trò: resources/views/components/sidebar.blade.php --}}
        <x-sidebar />

        <div class="odin-main">
            {{-- Menu ngang phía trên: resources/views/components/topbar.blade.php --}}
            <x-topbar :title="$title ?? null" />

            <main class="container-fluid py-4 px-4">
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-logout-modal />

    @livewireScripts
</body>

</html>
