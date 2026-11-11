{{--
    Ô chọn khoảng ngày: bấm 2 ngày trên cùng một lịch → [từ ngày, đến ngày] (Y-m-d).
    Ghi thẳng vào 2 thuộc tính của component Livewire đang chứa nó (resources/js/date-range.js).
    Dùng: <x-date-range from="from" to="to" />
--}}
@props(['from' => 'from', 'to' => 'to', 'align' => 'end'])

<div {{ $attributes->class(['odin-dr', 'odin-dr--'.$align]) }}
    x-data="dateRange({ fromProp: @js($from), toProp: @js($to), months: @js(__('date_range.months')) })"
    x-on:keydown.escape="close()" x-on:click.outside="close()">
    <button type="button" class="odin-dr__trigger" x-on:click="toggle()" :class="{ 'is-open': open, 'has-value': label }"
        aria-haspopup="dialog" :aria-expanded="open">
        <x-icon name="calendar" />
        <span class="odin-dr__text" x-text="label || @js(__('date_range.placeholder'))">{{ __('date_range.placeholder') }}</span>
    </button>

    <div class="odin-dr__panel" x-show="open" x-cloak x-transition.opacity.duration.120ms role="dialog"
        aria-label="{{ __('date_range.placeholder') }}">
        <ul class="odin-dr__presets" role="list">
            @foreach (__('date_range.preset') as $key => $text)
                <li><button type="button" x-on:click="preset(@js($key))">{{ $text }}</button></li>
            @endforeach
        </ul>

        <div class="odin-dr__cal">
            <div class="odin-dr__nav">
                <button type="button" class="odin-dr__arrow odin-dr__arrow--prev" x-on:click="shift(-1)" aria-label="{{ __('date_range.prev') }}">
                    <x-icon name="chevron" />
                </button>
                <span class="odin-dr__month" x-text="monthLabel"></span>
                <button type="button" class="odin-dr__arrow" x-on:click="shift(1)" aria-label="{{ __('date_range.next') }}">
                    <x-icon name="chevron" />
                </button>
            </div>

            <div class="odin-dr__grid">
                @foreach (__('date_range.weekdays') as $weekday)
                    <span class="odin-dr__wd">{{ $weekday }}</span>
                @endforeach
                <template x-for="day in days" :key="day.iso">
                    <button type="button" class="odin-dr__day" :class="dayClass(day)" x-text="day.n"
                        x-on:click="pick(day.iso)" x-on:mouseenter="hover = day.iso"></button>
                </template>
            </div>

            <div class="odin-dr__foot">
                <span class="odin-dr__hint" x-text="start ? @js(__('date_range.hint_end')) : @js(__('date_range.hint_start'))"></span>
                <button type="button" class="odin-dr__clear" x-show="from || to" x-on:click="clear()">{{ __('date_range.clear') }}</button>
            </div>
        </div>
    </div>
</div>
