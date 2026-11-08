@php
    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
    // Avatar thẻ: chữ cái đầu của tên (từ cuối trong họ tên)
    $initial = fn (?string $name) => mb_strtoupper(mb_substr(\Illuminate\Support\Str::afterLast(trim((string) $name), ' '), 0, 1)) ?: '?';
    // Biểu tượng theo việc cần xử lý
    $urgencyIcons = ['overdue_appointment' => 'clock', 'needs_quotation' => 'alert', 'decision_due' => 'hourglass', 'recontact' => 'refresh', 'not_contacted' => 'phone'];
    $activeView = $this->activeView();
    $activeSort = $this->activeSort();
@endphp

<div class="vc ok" x-data="{ filtersOpen: @js($this->hasFilters()) }">
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/opportunity.css'])
    @endassets

    <h1 class="visually-hidden">{{ __('opportunities.title') }}</h1>
    @if ($this->routePrefix === 'sale_leader')
        <p class="vc-head__sub ok-subtitle">
            {{ $this->activeTeam ? __('opportunities.subtitle_team', ['team' => $this->activeTeam->team_name]) : __('opportunities.subtitle_no_team') }}
        </p>
    @endif

    {{-- ── Thanh công cụ: kiểu xem, sắp xếp, tổng số | tìm kiếm, cần xử lý, bộ lọc ── --}}
    <section class="vc-card ok-toolbar">
        <div class="ok-toolbar__row">
            <div class="ok-toolbar__info">
                <div class="ok-toolbar__links">
                    <div class="ok-sort" role="group">
                        <span>{{ __('opportunities.view_by') }}:</span>
                        @foreach (\App\Livewire\Opportunities\Kanban::VIEWS as $view)
                            <button type="button" wire:click="setView('{{ $view }}')" class="ok-sort__btn {{ $activeView === $view ? 'is-active' : '' }}"
                                aria-pressed="{{ $activeView === $view ? 'true' : 'false' }}">
                                {{ __('opportunities.view_'.$view) }}
                            </button>
                        @endforeach
                    </div>

                    <div class="ok-sort" role="group">
                        <span>{{ __('opportunities.sort_by') }}:</span>
                        @foreach (\App\Livewire\Opportunities\Kanban::SORTS as $sort)
                            <button type="button" wire:click="setSort('{{ $sort }}')" class="ok-sort__btn {{ $activeSort === $sort ? 'is-active' : '' }}"
                                aria-pressed="{{ $activeSort === $sort ? 'true' : 'false' }}">
                                {{ __('opportunities.sort.'.$sort) }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <p class="ok-total">
                    {!! __('opportunities.result_count', ['count' => '<strong>'.$total.'</strong>']) !!}
                    · <strong>{{ $money($totalValue) }}</strong>
                </p>
            </div>

            <div class="ok-toolbar__tools">
                    {{-- Nút Tạo cơ hội: chỉ Salesperson, đặt trước ô tìm kiếm --}}
                    @if ($this->canCreateOpportunity())
                        <a href="{{ route('salesperson.opportunities.create') }}"
                            class="vc-btn vc-btn--primary ok-tool-btn--create"
                            wire:navigate
                            title="{{ __('opportunities.create.btn_new') }}"
                            aria-label="{{ __('opportunities.create.btn_new') }}">
                            <x-icon name="plus" />
                            <span class="ok-tool-btn--create__label">{{ __('opportunities.create.btn_new') }}</span>
                        </a>
                    @endif

                <label class="vc-search ok-search">
                    <x-icon name="search" />
                    <input type="search" wire:model.live.debounce.350ms="search" placeholder="{{ __('opportunities.search_placeholder') }}"
                        aria-label="{{ __('opportunities.search_placeholder') }}">
                    <span class="vc-search__spin" wire:loading.delay wire:target="search">
                        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    </span>
                </label>

                {{-- Chỉ hiện cơ hội cần xử lý: số việc nằm ở chấm đỏ góc nút --}}
                @php $urgentLabel = __('opportunities.urgent_filter', ['count' => $urgentCount]); @endphp
                <button type="button" wire:click="toggleUrgent" class="ok-tool-btn {{ $urgent ? 'is-active' : '' }}"
                    title="{{ $urgentLabel }}" aria-label="{{ $urgentLabel }}" aria-pressed="{{ $urgent ? 'true' : 'false' }}">
                    <x-icon name="alert" />
                    @if ($urgentCount > 0)
                        <span class="ok-tool-btn__dot" aria-hidden="true">{{ $urgentCount > 99 ? '99+' : $urgentCount }}</span>
                    @endif
                </button>

                <button type="button" class="ok-tool-btn {{ $this->hasFilters() ? 'is-active' : '' }}"
                    x-on:click="filtersOpen = ! filtersOpen" x-bind:aria-expanded="filtersOpen"
                    title="{{ __('opportunities.filters') }}" aria-label="{{ __('opportunities.filters') }}">
                    <x-icon name="filter" />
                </button>
            </div>
        </div>

        {{-- ── Bộ lọc mở rộng ───────────────────────────────────────── --}}
        @php $activePreset = $this->activePreset(); @endphp
        <div class="ok-filter" x-show="filtersOpen" x-cloak aria-label="{{ __('opportunities.filter_title') }}">
            <div class="vc-field">
                <span class="vc-field__label">{{ __('opportunities.conversion_date') }}</span>
                <div class="ok-presets" role="group">
                    @foreach (['', ...\App\Livewire\Opportunities\Kanban::PRESETS] as $preset)
                        <button type="button" wire:click="applyPreset('{{ $preset }}')"
                            class="ok-preset {{ $activePreset === $preset ? 'is-active' : '' }}"
                            aria-pressed="{{ $activePreset === $preset ? 'true' : 'false' }}">
                            {{ __('opportunities.preset.'.($preset ?: 'all')) }}
                        </button>
                    @endforeach
                </div>
            </div>

            <label class="vc-field">
                <span class="vc-field__label">{{ __('opportunities.from') }}</span>
                <input type="date" wire:model.live="from" class="vc-control" max="{{ $to ?: '' }}">
            </label>

            <label class="vc-field">
                <span class="vc-field__label">{{ __('opportunities.to') }}</span>
                <input type="date" wire:model.live="to" class="vc-control" min="{{ $from ?: '' }}">
            </label>

            {{-- Đội (Giám đốc) → Salesperson (Sale Leader, Giám đốc) --}}
            @if ($this->canFilterTeam)
                <label class="vc-field ok-filter__select">
                    <span class="vc-field__label">{{ __('opportunities.team') }}</span>
                    <select wire:model.live="team" class="vc-control">
                        <option value="">{{ __('opportunities.all_teams') }}</option>
                        @foreach ($this->teams as $option)
                            <option value="{{ $option->team_id }}">{{ $option->team_name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            @unless ($this->isSalesperson())
                <label class="vc-field ok-filter__select">
                    <span class="vc-field__label">{{ __('opportunities.salesperson') }}</span>
                    <select wire:model.live="employee" class="vc-control" @disabled(! $this->activeTeam)>
                        <option value="">{{ $this->activeTeam ? __('opportunities.all_salespeople') : __('opportunities.choose_team_first') }}</option>
                        @foreach ($this->employeeOptions as $option)
                            <option value="{{ $option->employee_id }}">
                                {{ $option->full_name }}{{ $option->status === 'Resigned' ? ' ('.__('opportunities.resigned').')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>
            @endunless

            <div class="vc-field vc-field--action">
                <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                    <x-icon name="refresh" />{{ __('opportunities.reset') }}
                </button>
            </div>
        </div>
    </section>

    @if ($activeView === 'list')
        {{-- ── Danh sách: toàn bộ cơ hội, theo cùng bộ lọc và cách sắp xếp ── --}}
        @php $listLimit = $this->limitFor('list'); @endphp
        <section class="vc-card ok-list" wire:key="view-list">
            {{-- Bảng luôn hiện kể cả khi rỗng, để vẫn đổi được bộ lọc trên cột Giai đoạn --}}
            <div>
                <div class="vc-table-wrap" wire:loading.class="is-loading">
                    <table class="vc-table">
                        <thead>
                            <tr>
                                <th>{{ __('opportunities.list.customer') }}</th>
                                <th>{{ __('opportunities.list.phone') }}</th>
                                <th class="ok-th-filter">
                                    <select wire:model.live="stageFilter" class="ok-th-select {{ $stageFilter !== '' ? 'is-active' : '' }}"
                                        aria-label="{{ __('opportunities.stage') }}">
                                        <option value="">{{ __('opportunities.list.stage_all') }}</option>
                                        @foreach ($this->stages as $option)
                                            <option value="{{ $option->stage_id }}">{{ $option->stage_name }}</option>
                                        @endforeach
                                    </select>
                                </th>
                                <th>{{ __('opportunities.expected_value') }}</th>
                                @unless ($this->isSalesperson())
                                    <th>{{ __('opportunities.owner') }}</th>
                                @endunless
                                <th>{{ __('opportunities.list.source') }}</th>
                                <th>{{ __('opportunities.conversion_date') }}</th>
                                <th>{{ __('opportunities.list.todo') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($opportunities->take($listLimit) as $opp)
                                @php
                                    $url = route($this->routePrefix.'.opportunities.show', $opp);
                                    $stage = $this->stages->firstWhere('stage_id', $opp->stage_id);
                                @endphp
                                <tr wire:key="row-{{ $opp->opportunity_id }}" onclick="window.location='{{ $url }}'">
                                    <td data-label="{{ __('opportunities.list.customer') }}">
                                        <span class="ok-list__customer">
                                            <span class="ok-card__avatar" aria-hidden="true">{{ $initial($opp->lead?->full_name) }}</span>
                                            <span>
                                                <a href="{{ $url }}" class="ok-list__name" onclick="event.stopPropagation()">{{ $opp->lead?->full_name ?? '-' }}</a>
                                                <span class="vc-mono ok-list__code">{{ $opp->opportunity_id }}</span>
                                            </span>
                                        </span>
                                    </td>
                                    <td data-label="{{ __('opportunities.list.phone') }}" class="text-nowrap">{{ $opp->lead?->phone ?: '-' }}</td>
                                    <td data-label="{{ __('opportunities.stage') }}">
                                        <span class="ok-stage ok-col--{{ $stage?->sort_order }}">{{ $stage?->stage_name ?? '-' }}</span>
                                        @if (in_array($opp->status, [\App\Models\Opportunity::STATUS_LOST, \App\Models\Opportunity::STATUS_ARCHIVED], true))
                                            <span class="vc-badge ok-status ok-status--{{ $opp->status }}">
                                                <span class="vc-badge__dot"></span>{{ __('opportunities.status_name.'.$opp->status) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td data-label="{{ __('opportunities.expected_value') }}" class="text-nowrap fw-semibold">{{ $opp->displayValue() ?? '-' }}</td>
                                    @unless ($this->isSalesperson())
                                        <td data-label="{{ __('opportunities.owner') }}">{{ $opp->employee?->full_name ?? '-' }}</td>
                                    @endunless
                                    <td data-label="{{ __('opportunities.list.source') }}">{{ $opp->lead?->source_name ?? '-' }}</td>
                                    <td data-label="{{ __('opportunities.conversion_date') }}" class="text-nowrap">{{ $opp->conversion_date?->format('d/m/Y') }}</td>
                                    <td data-label="{{ __('opportunities.list.todo') }}">
                                        @if ($opp->urgency)
                                            <span class="ok-list__todo">
                                                <x-icon :name="$urgencyIcons[$opp->urgency]" />{{ __('opportunities.urgency.'.$opp->urgency, ['days' => $opp->urgency_days]) }}
                                            </span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($opportunities->isEmpty())
                    <p class="ok-col__empty ok-list__empty">{{ __('opportunities.empty_stage') }}</p>
                @endif

                @if ($opportunities->count() > $listLimit)
                    <button type="button" class="ok-col__more" wire:click="showMore('list')">
                        {{ __('opportunities.show_more', ['count' => $opportunities->count() - $listLimit]) }}
                    </button>
                @endif
            </div>
        </section>
    @else
        {{-- ── Bảng Kanban: mỗi cột một Stage ───────────────────────────── --}}
        <div class="ok-board" wire:key="view-kanban" wire:loading.class="is-loading">
            @foreach ($this->stages as $stage)
                @php
                    $cards = $columns->get($stage->stage_id, collect());
                    $limit = $this->limitFor($stage->stage_id);
                @endphp

                @continue($stage->stage_id === \App\Models\Stage::ARCHIVED)

                {{-- Màu đầu cột theo thứ tự stage: .ok-col--1 … .ok-col--5 (opportunity.css) --}}
                <section class="ok-col ok-col--{{ $stage->sort_order }}" wire:key="stage-{{ $stage->stage_id }}" aria-labelledby="stage-{{ $stage->stage_id }}">
                    <header class="ok-col__head" title="{{ $stage->description }}">
                        <div class="ok-col__title">
                            <h2 id="stage-{{ $stage->stage_id }}">{{ $stage->stage_name }}</h2>
                        </div>

                        <span class="ok-col__sum">
                            {{ $money($cards->sum('expected_value')) }} · {{ __('opportunities.column_count', ['count' => $cards->count()]) }}
                        </span>
                    </header>

                    <div class="ok-col__body">
                        @foreach ($cards->take($limit) as $opp)
                            <a href="{{ route($this->routePrefix.'.opportunities.show', $opp) }}"
                                class="ok-card {{ $opp->urgency ? 'ok-card--attention' : '' }}" wire:key="opp-{{ $opp->opportunity_id }}">
                                <span class="ok-card__head">
                                    <span class="ok-card__avatar" aria-hidden="true">{{ $initial($opp->lead?->full_name) }}</span>
                                    <span class="ok-card__name">{{ $opp->lead?->full_name ?? '-' }}</span>
                                </span>

                                {{-- Việc cần xử lý: biểu tượng góc phải trên, rê chuột hiện nội dung --}}
                                @if ($opp->urgency)
                                    @php $urgencyText = __('opportunities.urgency.'.$opp->urgency, ['days' => $opp->urgency_days]); @endphp
                                    <span class="ok-card__alert" title="{{ $urgencyText }}" aria-label="{{ $urgencyText }}">
                                        <x-icon :name="$urgencyIcons[$opp->urgency]" />
                                    </span>
                                @endif

                                <span class="ok-card__row">
                                    <span class="ok-card__info"><x-icon name="phone" />{{ $opp->lead?->phone ?: '-' }}</span>
                                    <span class="ok-card__date" title="{{ __('opportunities.conversion_date') }}: {{ $opp->conversion_date?->format('d/m/Y') }}">
                                        {{ $opp->conversion_date?->diffForHumans() }}
                                    </span>
                                </span>

                                <span class="ok-card__row">
                                    <span class="ok-card__info"><x-icon name="wallet" />{{ $opp->displayValue() ?? '-' }}</span>
                                    {{-- Leader / Giám đốc: người phụ trách --}}
                                    @unless ($this->isSalesperson())
                                        <span class="ok-card__owner" title="{{ __('opportunities.owner') }}">{{ $opp->employee?->full_name ?? '-' }}</span>
                                    @endunless
                                </span>

                                <span class="ok-card__foot">
                                    <span class="ok-card__tags">
                                        {{-- Stage Lưu trữ gồm 2 trạng thái: Từ chối (Lost) / Lưu trữ (Archived) --}}
                                        @if (in_array($opp->status, [\App\Models\Opportunity::STATUS_LOST, \App\Models\Opportunity::STATUS_ARCHIVED], true))
                                            <span class="vc-badge ok-status ok-status--{{ $opp->status }}">
                                                <span class="vc-badge__dot"></span>{{ __('opportunities.status_name.'.$opp->status) }}
                                            </span>
                                        @endif
                                        @if ($opp->lead)
                                            <span class="ok-tag" title="{{ __('leads.method.'.$opp->lead->contact_method) }}">{{ $opp->lead->source_name }}</span>
                                        @endif
                                    </span>
                                    <span class="vc-mono">{{ $opp->opportunity_id }}</span>
                                </span>
                            </a>
                        @endforeach
                        {{-- @foreach + @if thay cho @forelse/@empty: Livewire không chèn dấu đóng khối cho @forelse --}}
                        @if ($cards->isEmpty())
                            <p class="ok-col__empty">{{ __('opportunities.empty_stage') }}</p>
                        @endif

                        {{-- Mỗi cột hiện PAGE_SIZE thẻ, bấm để tải thêm --}}
                        @if ($cards->count() > $limit)
                            <button type="button" class="ok-col__more" wire:click="showMore('{{ $stage->stage_id }}')">
                                {{ __('opportunities.show_more', ['count' => $cards->count() - $limit]) }}
                            </button>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
