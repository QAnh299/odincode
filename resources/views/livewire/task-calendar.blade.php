{{-- Lịch việc cần làm (App\Livewire\TaskCalendar). wire:ignore.self giữ trạng thái mở của dropdown Bootstrap khi Livewire cập nhật lại --}}
@php
    $selected = $day !== '' ? \Carbon\CarbonImmutable::parse($day) : null;
    $monthStart = \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month);
    $todoTotal = $this->items->where('done', false)->count();
@endphp

<div class="dropdown">
    <button class="odin-icon-btn position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
        aria-expanded="false" aria-label="{{ __('calendar.title') }}" title="{{ __('calendar.title') }}" wire:ignore.self>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
            stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="4" width="18" height="18" rx="2" />
            <path d="M16 2v4M8 2v4M3 10h18" />
        </svg>
        @if ($todayCount > 0)
            <span class="odin-bell-badge">{{ $todayCount > 99 ? '99+' : $todayCount }}</span>
        @endif
    </button>

    <div class="dropdown-menu dropdown-menu-end odin-cal" wire:ignore.self>
        <div class="odin-cal__panel">
        <div class="odin-cal__month">
            <div class="odin-cal__head">
                <button type="button" class="odin-cal__nav" wire:click="previousMonth" aria-label="{{ __('calendar.previous') }}">‹</button>
                <div class="odin-cal__heading">
                    <strong>{{ __('calendar.month', ['month' => $monthStart->month, 'year' => $monthStart->year]) }}</strong>
                    <span>{{ __('calendar.month_summary', ['total' => $this->items->count(), 'todo' => $todoTotal]) }}</span>
                </div>
                <button type="button" class="odin-cal__nav" wire:click="nextMonth" aria-label="{{ __('calendar.next') }}">›</button>
            </div>

            <div class="odin-cal__grid">
                @foreach (range(1, 7) as $weekday)
                    <span class="odin-cal__weekday">{{ __('calendar.weekday_short.'.$weekday) }}</span>
                @endforeach

                @foreach ($this->cells as $cell)
                    @if (! $cell['inMonth'])
                        <span class="odin-cal__day is-outside">{{ $cell['number'] }}</span>
                    @else
                        @php
                            $state = match (true) {
                                $cell['overdue'] > 0 => 'overdue',
                                $cell['todo'] > 0    => 'todo',
                                $cell['done'] > 0    => 'done',
                                default              => '',
                            };
                            $count = $cell['todo'] ?: $cell['done'];
                        @endphp
                        <button type="button" wire:click="selectDay('{{ $cell['date'] }}')" wire:key="cal-{{ $cell['date'] }}"
                            class="odin-cal__day {{ $cell['today'] ? 'is-today' : '' }} {{ $cell['date'] === $day ? 'is-selected' : '' }}"
                            aria-label="{{ $cell['date'] }}">
                            {{ $cell['number'] }}
                            @if ($count)
                                <span class="odin-cal__count is-{{ $state }}">{{ $count }}</span>
                            @endif
                        </button>
                    @endif
                @endforeach
            </div>

            <div class="odin-cal__legend">
                @foreach (['todo', 'overdue', 'done'] as $state)
                    <span><i class="is-{{ $state }}"></i>{{ __('calendar.legend.'.$state) }}</span>
                @endforeach
            </div>
        </div>

        @if ($selected)
            <div class="odin-cal__list">
                <div class="odin-cal__list-inner">
                <div class="odin-cal__list-head">
                    <div>
                        <strong>{{ __('calendar.weekday.'.$selected->dayOfWeekIso) }}, {{ $selected->format('d/m/Y') }}</strong>
                        <span>{{ __('calendar.day_summary', ['total' => $dayTotal, 'done' => $dayDone]) }}</span>
                    </div>
                    <button type="button" class="odin-cal__close" wire:click="closeDay" aria-label="{{ __('calendar.close') }}">×</button>
                </div>

                <div class="odin-cal__items">
                    @forelse ($dayItems as $item)
                        <div class="odin-cal__item {{ $item['done'] ? 'is-done' : '' }}" wire:key="item-{{ $item['key'] }}">
                            <span class="odin-cal__check {{ $item['done'] ? 'is-on' : '' }}" aria-hidden="true">{{ $item['done'] ? '✓' : '' }}</span>
                            <div class="odin-cal__item-body">
                                <div class="odin-cal__item-top">
                                    @if ($item['time'])
                                        <strong>{{ $item['time'] }}</strong>
                                    @endif
                                    <span class="odin-cal__tag is-{{ $item['kind'] }}">{{ $item['label'] }}</span>
                                    @if ($item['overdue'])
                                        <span class="odin-cal__tag is-late">{{ __('calendar.overdue') }}</span>
                                    @endif
                                </div>
                                <div class="odin-cal__name">{{ $item['name'] }}</div>
                                @if ($item['detail'])
                                    <div class="odin-cal__detail">{{ $item['detail'] }}</div>
                                @endif
                            </div>
                            <a href="{{ $item['url'] }}" class="odin-cal__open">{{ __('calendar.open') }}</a>
                        </div>
                    @empty
                        <p class="odin-cal__empty">{{ __('calendar.empty') }}</p>
                    @endforelse
                </div>

                @if ($dayPages > 1)
                    <div class="odin-cal__pager">
                        <button type="button" class="odin-cal__nav" wire:click="previousDayPage" @disabled($page <= 1) aria-label="{{ __('calendar.previous_page') }}">‹</button>
                        <span>{{ __('calendar.page', ['page' => $page, 'pages' => $dayPages]) }}</span>
                        <button type="button" class="odin-cal__nav" wire:click="nextDayPage" @disabled($page >= $dayPages) aria-label="{{ __('calendar.next_page') }}">›</button>
                    </div>
                @endif
                </div>
            </div>
        @endif
        </div>
    </div>
</div>
