@php
    $languages = [
        'vi' => __('general.vietnamese'),
        'en' => __('general.english'),
    ];
@endphp

<div class="dropdown">
    <button class="btn btn-sm lang-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round" class="me-1" aria-hidden="true">
            <circle cx="12" cy="12" r="10" />
            <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
        </svg>
        {{ $languages[app()->getLocale()] ?? $languages['vi'] }}
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        @foreach ($languages as $code => $label)
            <li>
                <a class="dropdown-item {{ app()->getLocale() === $code ? 'active' : '' }}"
                    href="{{ route('lang.switch', $code) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>
</div>
