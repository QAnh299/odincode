@php
    $money = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
@endphp

<div class="vc ok">
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/opportunity.css'])
    @endassets

    {{-- ── Tiêu đề ──────────────────────────────────────────────────── --}}
    <header class="vc-head">
        <div>
            <h1 class="vc-head__title">{{ __('opportunities.title') }}</h1>
            <p class="vc-head__sub">
                @if ($this->isSalesperson())
                    {{ __('opportunities.subtitle') }}
                @elseif ($this->routePrefix === 'sale_leader')
                    {{ $this->activeTeam ? __('opportunities.subtitle_team', ['team' => $this->activeTeam->team_name]) : __('opportunities.subtitle_no_team') }}
                @else
                    {{ __('opportunities.subtitle_all') }}
                @endif
            </p>
        </div>
        <p class="ok-total">
            {!! __('opportunities.result_count', ['count' => '<strong>'.$total.'</strong>']) !!}
        </p>
    </header>

    @php $activePreset = $this->activePreset(); @endphp
    <section class="vc-card ok-filter" aria-label="{{ __('opportunities.filter_title') }}">
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

        {{-- Chỉ hiện cơ hội cần xử lý (việc gấp lên đầu cột) --}}
        <div class="vc-field">
            <span class="vc-field__label">{{ __('opportunities.priority') }}</span>
            <button type="button" wire:click="toggleUrgent" class="ok-preset ok-preset--urgent {{ $urgent ? 'is-active' : '' }}"
                aria-pressed="{{ $urgent ? 'true' : 'false' }}">
                <x-icon name="alert" />{{ __('opportunities.urgent_filter', ['count' => $urgentCount]) }}
            </button>
        </div>

        <div class="vc-field vc-field--action">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                <x-icon name="refresh" />{{ __('opportunities.reset') }}
            </button>
        </div>
    </section>

    {{-- ── Bảng Kanban: mỗi cột một Stage ───────────────────────────── --}}
    <div class="ok-board" wire:loading.class="is-loading">
        @php
            // Biểu tượng góc thẻ theo việc cần xử lý
            $urgencyIcons = ['overdue_appointment' => 'clock', 'needs_quotation' => 'alert', 'decision_due' => 'hourglass', 'not_contacted' => 'phone'];
        @endphp
        @foreach ($this->stages as $stage)
            @php
                $cards = $columns->get($stage->stage_id, collect());
                $isArchive = $stage->stage_id === \App\Models\Stage::ARCHIVED;
                $collapsed = $isArchive && ! $showArchived;
                $limit = $this->limitFor($stage->stage_id);
            @endphp

            {{-- Cột Lưu trữ thu gọn: dải hẹp ở mép phải (mũi tên, tên cột dựng đứng, số lượng), bấm để mở rộng --}}
            @if ($collapsed)
                <section class="ok-col ok-col--{{ $stage->sort_order }} is-collapsed" wire:key="stage-{{ $stage->stage_id }}">
                    {{-- Mở rộng xong thì cuộn bảng tới cột Lưu trữ (cột mới nằm ở mép phải, có thể ngoài khung nhìn) --}}
                    <button type="button" class="ok-col__rail" aria-expanded="false" title="{{ __('opportunities.show_archived') }}"
                        x-data
                        x-on:click="$wire.toggleArchived().then(() => $nextTick(() =>
                            document.getElementById('stage-{{ $stage->stage_id }}')
                                ?.closest('.ok-col')
                                ?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'end' })))">
                        <x-icon name="chevron" />
                        <span class="ok-col__rail-name">{{ $stage->stage_name }}</span>
                        <span class="ok-col__count">{{ $cards->count() }}</span>
                    </button>
                </section>
            @else

            {{-- Màu viền theo thứ tự stage: .ok-col--1 … .ok-col--5 (opportunity.css) --}}
            <section class="ok-col ok-col--{{ $stage->sort_order }}" wire:key="stage-{{ $stage->stage_id }}" aria-labelledby="stage-{{ $stage->stage_id }}">
                <header class="ok-col__head" title="{{ $stage->description }}">
                    <div class="ok-col__title">
                        <h2 id="stage-{{ $stage->stage_id }}">{{ $stage->stage_name }}</h2>
                        <span class="ok-col__count">{{ $cards->count() }}</span>
                        {{-- Cột Lưu trữ đang mở: mũi tên thu gọn lại sang phải --}}
                        @if ($isArchive)
                            <button type="button" class="ok-col__toggle" wire:click="toggleArchived"
                                title="{{ __('opportunities.hide_archived') }}" aria-expanded="true">
                                <x-icon name="chevron" />
                            </button>
                        @endif
                    </div>

                    <span class="ok-col__sum">{{ $money($cards->sum('expected_value')) }}</span>
                </header>

                <div class="ok-col__body">
                    @foreach ($cards->take($limit) as $opp)
                        <a href="{{ route($this->routePrefix.'.opportunities.show', $opp) }}"
                            class="ok-card {{ $opp->urgency ? 'ok-card--attention' : '' }}" wire:key="opp-{{ $opp->opportunity_id }}">
                            <span class="ok-card__name">{{ $opp->lead?->full_name ?? '—' }}</span>

                            {{-- Việc cần xử lý: biểu tượng góc phải trên, rê chuột hiện nội dung --}}
                            @if ($opp->urgency)
                                @php $urgencyText = __('opportunities.urgency.'.$opp->urgency, ['days' => $opp->urgency_days]); @endphp
                                <span class="ok-card__alert" title="{{ $urgencyText }}" aria-label="{{ $urgencyText }}">
                                    <x-icon :name="$urgencyIcons[$opp->urgency]" />
                                </span>
                            @endif

                            {{-- Stage Lưu trữ gồm 2 trạng thái: Từ chối (Lost) / Lưu trữ (Archived) --}}
                            @if (in_array($opp->status, [\App\Models\Opportunity::STATUS_LOST, \App\Models\Opportunity::STATUS_ARCHIVED], true))
                                <span class="vc-badge ok-status ok-status--{{ $opp->status }}">
                                    <span class="vc-badge__dot"></span>{{ __('opportunities.status_name.'.$opp->status) }}
                                </span>
                            @endif


                            {{-- Giá trị dự kiến: chỉ hiện khi đã có --}}
                            @if ($value = $opp->displayValue())
                                <span class="ok-card__value">{{ $value }}</span>
                            @endif

                            <ul class="ok-card__meta">
                                @if ($opp->lead?->phone)
                                    <li><x-icon name="phone" />{{ $opp->lead->phone }}</li>
                                @endif
                                @if ($opp->lead)
                                    <li>
                                        <x-icon name="message" />
                                        {{ __('leads.method.'.$opp->lead->contact_method) }} · {{ $opp->lead->source_name }}
                                    </li>
                                @endif
                            </ul>

                            {{-- Leader / Giám đốc: người phụ trách --}}
                            @unless ($this->isSalesperson())
                                <span class="ok-card__owner"><x-icon name="user" />{{ $opp->employee?->full_name ?? '—' }}</span>
                            @endunless

                            <div class="ok-card__foot">
                                <span class="vc-mono">{{ $opp->opportunity_id }}</span>
                                <span class="ok-card__date" title="{{ __('opportunities.conversion_date') }}">
                                    <x-icon name="calendar" />{{ $opp->conversion_date?->format('d/m/Y') }}
                                </span>
                            </div>
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
            @endif
        @endforeach
    </div>
</div>
