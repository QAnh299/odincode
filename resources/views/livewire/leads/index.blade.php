@php
    use App\Models\Lead;
    use App\Models\Role;

    $stats = $this->stats;

    // Thẻ thống kê bấm được: [key trạng thái, icon]
    $cards = [
        ['', 'layers'],
        [Lead::STATUS_NEW, 'inbox'],
        [Lead::STATUS_CONVERTED, 'check-circle'],
    ];
@endphp

<div class="vc ld">
    {{-- Dùng lại giao diện trang voucher (resources/css/voucher.css) + phần riêng resources/css/lead.css --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/lead.css'])
    @endassets

    {{-- ── Tiêu đề + nút thao tác (chỉ hiển thị, chưa có chức năng) ─── --}}
    <header class="vc-head">
        <div>
            <h1 class="vc-head__title">{{ __('leads.title') }}</h1>
            <p class="vc-head__sub">
                @if ($this->fixedBranch)
                    {{ __('leads.subtitle_branch', ['branch' => $this->fixedBranch->branch_name]) }}
                @else
                    {{ __('leads.subtitle') }}
                @endif
            </p>
        </div>

        @if ($this->canManage)
            <div class="ld-actions">
                <button type="button" class="vc-btn vc-btn--ghost">
                    <x-icon name="plus" />{{ __('leads.add') }}
                </button>
                <button type="button" class="vc-btn vc-btn--ghost">
                    <x-icon name="upload" />{{ __('leads.import') }}
                </button>
                <button type="button" class="vc-btn vc-btn--primary">
                    <x-icon name="share" />
                    {{ $this->routePrefix === Role::SALE_ADMIN ? __('leads.distribute_team') : __('leads.distribute_salesperson') }}
                </button>
            </div>
        @endif
    </header>

    {{-- ── Thống kê theo bộ lọc (bấm thẻ trạng thái để lọc) ─────────── --}}
    <div class="vc-summary ld-summary">
        @foreach ($cards as [$key, $icon])
            <button type="button" wire:click="filterStatus('{{ $key }}')"
                class="vc-summary__item ld-st--{{ $key ?: 'all' }} {{ $status === $key ? 'is-current' : '' }}"
                aria-pressed="{{ $status === $key ? 'true' : 'false' }}">
                <span class="vc-summary__icon"><x-icon :name="$icon" /></span>
                <span class="vc-summary__text">
                    <span class="vc-summary__label">{{ __('leads.stat.'.($key ?: 'all')) }}</span>
                    <span class="vc-summary__value">{{ number_format($stats[$key ?: 'all'], 0, ',', '.') }}</span>
                </span>
            </button>
        @endforeach

        <div class="vc-summary__item ld-summary__static ld-st--rate">
            <span class="vc-summary__icon"><x-icon name="trending" /></span>
            <span class="vc-summary__text">
                <span class="vc-summary__label">{{ __('leads.stat.rate') }}</span>
                <span class="vc-summary__value">{{ number_format($stats['rate'], 1, ',', '.') }}%</span>
            </span>
        </div>
    </div>

    {{-- ── Bộ lọc ───────────────────────────────────────────────────── --}}
    <section class="vc-card ld-filters" aria-label="{{ __('leads.filters') }}">
        <label class="vc-field ld-filters__search">
            <span class="vc-field__label">{{ __('leads.search') }}</span>
            <span class="vc-search">
                <x-icon name="search" />
                <input type="search" wire:model.live.debounce.350ms="search"
                    placeholder="{{ __('leads.search_placeholder') }}">
                <span class="vc-search__spin" wire:loading.delay wire:target="search">
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                </span>
            </span>
        </label>

        <label class="vc-field">
            <span class="vc-field__label">{{ __('leads.status') }}</span>
            <select wire:model.live="status" class="vc-control">
                <option value="">{{ __('leads.all') }}</option>
                @foreach (Lead::STATUSES as $option)
                    <option value="{{ $option }}">{{ __('leads.status_name.'.$option) }}</option>
                @endforeach
            </select>
        </label>

        <label class="vc-field">
            <span class="vc-field__label">{{ __('leads.source') }}</span>
            <select wire:model.live="source" class="vc-control">
                <option value="">{{ __('leads.all') }}</option>
                @foreach ($this->sources as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </label>

        <label class="vc-field">
            <span class="vc-field__label">{{ __('leads.contact_method') }}</span>
            <select wire:model.live="method" class="vc-control">
                <option value="">{{ __('leads.all') }}</option>
                @foreach (Lead::CONTACT_METHODS as $option)
                    <option value="{{ $option }}">{{ __('leads.method.'.$option) }}</option>
                @endforeach
            </select>
        </label>

        @if ($this->canFilterBranch)
            <label class="vc-field">
                <span class="vc-field__label">{{ __('leads.branch') }}</span>
                <select wire:model.live="branch" class="vc-control">
                    <option value="">{{ __('leads.all_branches') }}</option>
                    @foreach ($this->branches as $option)
                        <option value="{{ $option->branch_id }}">{{ $option->branch_name }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        <label class="vc-field">
            <span class="vc-field__label">{{ __('leads.from') }}</span>
            <input type="date" wire:model.live="from" class="vc-control" max="{{ $to ?: '' }}">
        </label>

        <label class="vc-field">
            <span class="vc-field__label">{{ __('leads.to') }}</span>
            <input type="date" wire:model.live="to" class="vc-control" min="{{ $from ?: '' }}">
        </label>

        <div class="vc-field vc-field--action">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                <x-icon name="refresh" />{{ __('leads.reset') }}
            </button>
        </div>
    </section>

    {{-- ── Danh sách ────────────────────────────────────────────────── --}}
    <section class="vc-card vc-results">
        <div class="vc-results__bar">
            <span>
                {!! __('leads.result_count', ['count' => '<strong>'.$leads->total().'</strong>']) !!}
            </span>
            <span class="vc-results__loading" wire:loading.delay>
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                {{ __('leads.loading') }}
            </span>
        </div>

        @if ($leads->isEmpty())
            <div class="vc-empty">
                <span class="vc-empty__icon"><x-icon name="inbox" /></span>
                <p class="vc-empty__title">{{ __('leads.empty') }}</p>
                <p class="vc-empty__sub">{{ __('leads.empty_sub') }}</p>
                @if ($this->hasFilters())
                    <button type="button" class="vc-btn vc-btn--primary" wire:click="resetFilters">
                        <x-icon name="refresh" />{{ __('leads.reset') }}
                    </button>
                @endif
            </div>
        @else
            <div class="vc-table-wrap" wire:loading.class="is-loading">
                <table class="vc-table vc-table--static">
                    <thead>
                        <tr>
                            <th>{{ __('leads.code') }}</th>
                            <th>{{ __('leads.customer') }}</th>
                            <th>{{ __('leads.phone') }}</th>
                            <th>{{ __('leads.source') }}</th>
                            <th>{{ __('leads.contact_method') }}</th>
                            @if (! $this->fixedBranch)
                                <th>{{ __('leads.branch') }}</th>
                            @endif
                            <th>{{ __('leads.created_at') }}</th>
                            <th>{{ __('leads.owner') }}</th>
                            <th>{{ __('leads.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($leads as $lead)
                            @php $owner = $lead->latestOpportunity?->employee; @endphp
                            <tr wire:key="lead-{{ $lead->lead_id }}">
                                <td data-label="{{ __('leads.code') }}">
                                    <span class="vc-mono">{{ $lead->lead_id }}</span>
                                </td>
                                <td data-label="{{ __('leads.customer') }}">
                                    <span class="ld-name">{{ $lead->full_name }}</span>
                                    @if ($lead->email)
                                        <span class="ld-sub">{{ $lead->email }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ __('leads.phone') }}" class="ld-nowrap">{{ $lead->phone }}</td>
                                <td data-label="{{ __('leads.source') }}">
                                    @if ($lead->source_url)
                                        <a href="{{ $lead->source_url }}" target="_blank" rel="noopener noreferrer">{{ $lead->source_name }}</a>
                                    @else
                                        {{ $lead->source_name }}
                                    @endif
                                </td>
                                <td data-label="{{ __('leads.contact_method') }}">
                                    <span class="ld-method ld-method--{{ $lead->contact_method }}">{{ __('leads.method.'.$lead->contact_method) }}</span>
                                </td>
                                @if (! $this->fixedBranch)
                                    <td data-label="{{ __('leads.branch') }}">{{ $lead->branch?->branch_name ?? '—' }}</td>
                                @endif
                                <td data-label="{{ __('leads.created_at') }}" class="ld-nowrap">
                                    {{ $lead->created_at?->format('d/m/Y H:i') }}
                                </td>
                                <td data-label="{{ __('leads.owner') }}">
                                    @if ($owner)
                                        {{ $owner->full_name }}
                                    @else
                                        <span class="ld-sub">{{ __('leads.unassigned') }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ __('leads.status') }}">
                                    <span class="vc-badge ld-st--{{ $lead->status }}">
                                        <span class="vc-badge__dot"></span>{{ __('leads.status_name.'.$lead->status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($leads->hasPages())
                <div class="vc-pagination">{{ $leads->links() }}</div>
            @endif
        @endif
    </section>
</div>
