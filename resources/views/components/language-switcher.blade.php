@php
    $current = app()->getLocale() === 'en' ? 'en' : 'vi';
    $next = $current === 'vi' ? 'en' : 'vi';
    $nextLabel = $next === 'vi' ? __('general.vietnamese') : __('general.english');
@endphp

<a class="btn btn-sm lang-btn" href="{{ route('lang.switch', $next) }}" hreflang="{{ $next }}"
    title="{{ $nextLabel }}" aria-label="{{ $nextLabel }}">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
        stroke-linecap="round" stroke-linejoin="round" class="me-1" aria-hidden="true">
        <circle cx="12" cy="12" r="10" />
        <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
    </svg>
    {{ strtoupper($current) }}
</a>
