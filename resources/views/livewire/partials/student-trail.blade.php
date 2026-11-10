{{-- Breadcrumb khi đi từ trang Chi tiết học viên (trait App\Livewire\Concerns\StudentTrail).
     $trail: [['label', 'url'], ...] - mục cuối là trang hiện tại; $class: 'vc-crumb' | 'qt-crumb' theo khung của trang.
     Nút Quay lại về mục liền trước có link. --}}
@php
    $back = collect($trail)->slice(0, -1)->reverse()->first(fn ($item) => $item['url']);
@endphp
<nav class="{{ $class }}" aria-label="breadcrumb">
    @if ($back)
        <a href="{{ $back['url'] }}" class="odin-back" wire:navigate>
            <x-icon name="arrow-left" />{{ __('students.back_to', ['code' => $back['label']]) }}
        </a>
    @endif
    <span class="{{ $class }}__path">
        @foreach ($trail as $item)
            @if (! $loop->first)
                <x-icon name="chevron" />
            @endif
            @if ($loop->last)
                <span aria-current="page">{{ $item['label'] }}</span>
            @elseif ($item['url'])
                <a href="{{ $item['url'] }}" wire:navigate>{{ $item['label'] }}</a>
            @else
                <span>{{ $item['label'] }}</span>
            @endif
        @endforeach
    </span>
</nav>
