@props([
    // Mỗi cột: ['label' => nhãn trục X, 'title' => tiêu đề tooltip, 'value' => số, 'display' => giá trị hiển thị]
    'bars' => [],
    'title' => '',
    // Định dạng nhãn trục Y (VD: tiền rút gọn "12 tr"); mặc định số nguyên
    'format' => null,
    // Trục Y chỉ dùng số nguyên (VD: số lượt đăng ký)
    'integer' => false,
    // Thông báo khi mọi cột đều bằng 0
    'empty' => '',
])

@php
    // Biểu đồ cột một chuỗi, vẽ bằng HTML/CSS (resources/css/course.css, class .cs-chart).
    // Không cần chú thích (legend): tiêu đề biểu đồ đã nói rõ chuỗi số liệu.
    $format ??= fn ($v) => number_format($v, 0, ',', '.');
    $max = max(array_column($bars, 'value') ?: [0]);

    // Bước chia "đẹp" cho trục Y (1, 2, 2.5, 5 × 10^n), khoảng 4 vạch
    $step = 1;
    if ($max > 0) {
        $rough = $max / 4;
        $magnitude = 10 ** floor(log10($rough));
        $norm = $rough / $magnitude;
        $step = match (true) {
            $norm <= 1   => 1,
            $norm <= 2   => 2,
            $norm <= 2.5 => 2.5,
            $norm <= 5   => 5,
            default      => 10,
        } * $magnitude;

        if ($integer) {
            $step = max(1, (int) ceil($step));
        }
    }
    // Không có số liệu: chỉ vẽ đường gốc 0 + thông báo
    $count = $max > 0 ? (int) ceil($max / $step) : 0;
    $top = $max > 0 ? $step * $count : 1;
    $ticks = array_map(fn ($i) => $i * $step, range(0, $count));

    $dense = count($bars) > 8;
@endphp

<figure {{ $attributes->merge(['class' => 'cs-chart']) }}>
    <figcaption class="cs-chart__title">{{ $title }}</figcaption>

    <div class="cs-chart__body">
        {{-- Trục Y + lưới ngang --}}
        <div class="cs-chart__axis" aria-hidden="true">
            @foreach ($ticks as $tick)
                <span style="bottom: {{ $tick / $top * 100 }}%">{{ $format($tick) }}</span>
            @endforeach
        </div>

        <div class="cs-chart__plot">
            @foreach ($ticks as $tick)
                <i class="cs-chart__grid {{ $tick == 0 ? 'is-base' : '' }}" style="bottom: {{ $tick / $top * 100 }}%"
                    aria-hidden="true"></i>
            @endforeach

            @if ($max <= 0 && $empty !== '')
                <p class="cs-chart__empty">{{ $empty }}</p>
            @endif

            <ol class="cs-chart__bars {{ $dense ? 'is-dense' : '' }}">
                @foreach ($bars as $bar)
                    @php $height = $top > 0 ? $bar['value'] / $top * 100 : 0; @endphp
                    <li class="cs-chart__col" tabindex="0" aria-label="{{ $bar['title'] }}: {{ $bar['display'] }}">
                        <span class="cs-chart__bar" style="height: {{ $height }}%"></span>
                        <span class="cs-chart__tip" style="bottom: {{ $height }}%" aria-hidden="true">
                            <span class="cs-chart__tip-label">{{ $bar['title'] }}</span>
                            <strong>{{ $bar['display'] }}</strong>
                        </span>
                        <span class="cs-chart__label" aria-hidden="true">{{ $bar['label'] }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</figure>
