@php
    $lead = $this->lead;
    $listUrl = route($this->routePrefix.'.leads');
    $opportunities = $this->opportunities;
@endphp

<div class="vc st">
    {{-- voucher.css (khung, bảng, nhãn) + lead.css (nhãn trạng thái, kênh liên hệ) + student.css (đầu trang, khối thông tin) --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/lead.css', 'resources/css/student.css'])
    @endassets

    {{-- ── Điều hướng ───────────────────────────────────────────────── --}}
    <nav class="vc-crumb" aria-label="breadcrumb">
        <a href="{{ $listUrl }}" class="vc-crumb__back"><x-icon name="arrow-left" />{{ __('leads.back') }}</a>
        <span class="vc-crumb__path">
            <a href="{{ $listUrl }}">{{ __('leads.title') }}</a>
            <x-icon name="chevron" />
            <span aria-current="page">{{ $lead->lead_id }}</span>
        </span>
    </nav>

    {{-- ── Đầu trang ────────────────────────────────────────────────── --}}
    <section class="vc-card st-head">
        <div class="st-head__main">
            <span class="st-avatar">{{ mb_strtoupper(mb_substr(trim(\Illuminate\Support\Str::afterLast($lead->full_name, ' ')), 0, 1)) }}</span>
            <div>
                <span class="vc-mono">{{ $lead->lead_id }}</span>
                <h1 class="st-head__name">{{ $lead->full_name }}</h1>
                <span class="vc-badge vc-badge--lg ld-st--{{ $lead->status }}">
                    <span class="vc-badge__dot"></span>{{ __('leads.status_name.'.$lead->status) }}
                </span>
            </div>
        </div>
    </section>

    {{-- ── Thông tin liên hệ ────────────────────────────────────────── --}}
    <section class="vc-card">
        <h2 class="st-title">{{ __('leads.info') }}</h2>
        <dl class="st-info ld-info">
            <dt>{{ __('leads.phone') }}</dt><dd>{{ $lead->phone ?: '—' }}</dd>
            <dt>{{ __('leads.field.email') }}</dt><dd>{{ $lead->email ?: '—' }}</dd>
            <dt>{{ __('leads.contact_method') }}</dt>
            <dd><span class="ld-method ld-method--{{ $lead->contact_method }}">{{ __('leads.method.'.$lead->contact_method) }}</span></dd>
            <dt>{{ __('leads.source') }}</dt><dd>{{ $lead->source_name }}</dd>
            <dt>{{ __('leads.field.source_url') }}</dt>
            <dd>
                @if ($lead->sourceUrlIsLink())
                    <a href="{{ $lead->source_url }}" target="_blank" rel="noopener noreferrer">{{ $lead->source_url }}</a>
                @else
                    {{ $lead->source_url ?: '—' }}
                @endif
            </dd>
            <dt>{{ __('leads.branch') }}</dt><dd>{{ $lead->branch?->branch_name ?? '—' }}</dd>
            <dt>{{ __('leads.created_at') }}</dt><dd>{{ $lead->created_at?->format('d/m/Y H:i') }}</dd>
        </dl>
    </section>

    {{-- ── Cơ hội tạo từ Lead ───────────────────────────────────────── --}}
    <section class="vc-card vc-results">
        <h2 class="st-title st-title--bar">{{ __('leads.opportunities_title') }}</h2>
        @if ($opportunities->isEmpty())
            <p class="st-empty">{{ __('leads.no_opportunities') }}</p>
        @else
            <div class="vc-table-wrap">
                <table class="vc-table vc-table--static">
                    <thead>
                        <tr>
                            <th>{{ __('leads.opportunity') }}</th>
                            <th>{{ __('leads.conversion_date') }}</th>
                            <th>{{ __('leads.owner') }}</th>
                            <th>{{ __('leads.stage') }}</th>
                            <th>{{ __('leads.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($opportunities as $opportunity)
                            <tr wire:key="opp-{{ $opportunity->opportunity_id }}">
                                <td data-label="{{ __('leads.opportunity') }}">
                                    @if ($opportunity->url)
                                        <a href="{{ $opportunity->url }}" class="vc-mono">{{ $opportunity->opportunity_id }}</a>
                                    @else
                                        <span class="vc-mono">{{ $opportunity->opportunity_id }}</span>
                                    @endif
                                    @if ($opportunity->student)
                                        <span class="st-sub">{{ __('leads.became_student', ['code' => $opportunity->student->student_id]) }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ __('leads.conversion_date') }}" class="st-nowrap">{{ $opportunity->conversion_date?->format('d/m/Y H:i') }}</td>
                                <td data-label="{{ __('leads.owner') }}">{{ $opportunity->employee?->full_name ?? '—' }}</td>
                                <td data-label="{{ __('leads.stage') }}">{{ $opportunity->stage?->stage_name ?? '—' }}</td>
                                <td data-label="{{ __('leads.status') }}">{{ __('opportunities.status_name.'.$opportunity->status) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
