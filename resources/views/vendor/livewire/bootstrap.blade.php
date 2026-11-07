{{--
    Ghi đè view phân trang Bootstrap của Livewire (livewire::bootstrap) — dùng chung cho mọi danh sách.
    Phân trang gọn: tối đa 5 trang quanh trang đang xem (không có "…") + ô nhập số trang để nhảy nhanh.
    VD: ‹ 48 49 [50] 51 52 ›   Trang [50] / 394
--}}
@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';

$current = $paginator->currentPage();
$last = $paginator->lastPage();

// Cửa sổ 5 trang, giữ trang đang xem ở giữa khi có thể
$from = max(1, min($current - 2, $last - 4));
$pages = range($from, min($last, $from + 4));
@endphp

<div>
    @if ($paginator->hasPages())
        <nav class="d-flex justify-items-center justify-content-between">
            <div class="d-flex justify-content-between flex-fill d-sm-none">
                <ul class="pagination">
                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <li class="page-item disabled" aria-disabled="true">
                            <span class="page-link">@lang('pagination.previous')</span>
                        </li>
                    @else
                        <li class="page-item">
                            <button type="button" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}" class="page-link" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled">@lang('pagination.previous')</button>
                        </li>
                    @endif

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <li class="page-item">
                            <button type="button" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}" class="page-link" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled">@lang('pagination.next')</button>
                        </li>
                    @else
                        <li class="page-item disabled" aria-disabled="true">
                            <span class="page-link" aria-hidden="true">@lang('pagination.next')</span>
                        </li>
                    @endif
                </ul>
            </div>

            <div class="d-none flex-sm-fill d-sm-flex align-items-sm-center justify-content-sm-between">
                <div>
                    <p class="small text-muted">
                        {!! __('Showing') !!}
                        <span class="fw-semibold">{{ $paginator->firstItem() }}</span>
                        {!! __('to') !!}
                        <span class="fw-semibold">{{ $paginator->lastItem() }}</span>
                        {!! __('of') !!}
                        <span class="fw-semibold">{{ $paginator->total() }}</span>
                        {!! __('results') !!}
                    </p>
                </div>

                <div class="pg-bar">
                    <ul class="pagination">
                        {{-- Previous Page Link --}}
                        @if ($paginator->onFirstPage())
                            <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
                                <span class="page-link" aria-hidden="true">&lsaquo;</span>
                            </li>
                        @else
                            <li class="page-item">
                                <button type="button" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}" class="page-link" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" aria-label="@lang('pagination.previous')">&lsaquo;</button>
                            </li>
                        @endif

                        {{-- Số trang (gọn) --}}
                        @foreach ($pages as $page)
                            @if ($page == $current)
                                <li class="page-item active" wire:key="paginator-{{ $paginator->getPageName() }}-page-{{ $page }}" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                            @else
                                <li class="page-item" wire:key="paginator-{{ $paginator->getPageName() }}-page-{{ $page }}"><button type="button" class="page-link" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}">{{ $page }}</button></li>
                            @endif
                        @endforeach

                        {{-- Next Page Link --}}
                        @if ($paginator->hasMorePages())
                            <li class="page-item">
                                <button type="button" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}" class="page-link" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" aria-label="@lang('pagination.next')">&rsaquo;</button>
                            </li>
                        @else
                            <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.next')">
                                <span class="page-link" aria-hidden="true">&rsaquo;</span>
                            </li>
                        @endif
                    </ul>

                    {{-- Nhập số trang: Enter hoặc rời ô để chuyển; số ngoài khoảng tự đưa về 1 – trang cuối.
                         wire:key theo trang hiện tại để ô được dựng lại (đúng số) sau mỗi lần chuyển trang. --}}
                    <div class="pg-jump" wire:key="paginator-{{ $paginator->getPageName() }}-jump-{{ $current }}"
                        x-data="{
                            page: {{ $current }},
                            go() {
                                const n = Math.min(Math.max(parseInt(this.page) || {{ $current }}, 1), {{ $last }});
                                this.page = n;
                                if (n !== {{ $current }}) $wire.gotoPage(n, '{{ $paginator->getPageName() }}');
                            },
                        }">
                        <label for="pg-jump-{{ $paginator->getPageName() }}">@lang('pagination.page')</label>
                        <input type="number" id="pg-jump-{{ $paginator->getPageName() }}" min="1" max="{{ $last }}" inputmode="numeric"
                            x-model="page" x-on:keydown.enter.prevent="$el.blur()" x-on:change="go()" x-on:focus="$el.select()"
                            aria-label="@lang('pagination.go_to')">
                        <span>/ {{ $last }}</span>
                    </div>
                </div>
            </div>
        </nav>
    @endif
</div>
