@php
    use App\Livewire\Leads\Index as LeadIndex;
    use App\Models\Lead;
    use App\Models\Role;

    $stats = $this->stats;

    // Thẻ thống kê bấm được để lọc: Tổng, Chờ phân (đội / Salesperson theo vai trò), Đã phân, Khách cũ quay lại
    $cards = ['', $this->pendingKey, LeadIndex::ASSIGNED, LeadIndex::RETURNING];
    $activeCard = $this->activeCard();

    $loadingTargets = 'search, status, source, method, branch, from, to, filterCard, resetFilters, gotoPage, nextPage, previousPage';
@endphp

<div class="vc ld">
    {{-- Dùng lại giao diện trang voucher (resources/css/voucher.css) + phần riêng resources/css/lead.css --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/lead.css'])
    @endassets

    {{-- ── Tiêu đề + nút thao tác ───────────────────────────────────── --}}
    <header class="vc-head ldl-head">
        <div>
            <h1 class="visually-hidden">{{ __('leads.title') }}</h1>
            @if ($this->fixedBranch)
                <p class="vc-head__sub">{{ __('leads.subtitle_branch', ['branch' => $this->fixedBranch->branch_name]) }}</p>
            @endif
        </div>

        @if ($this->canManage)
            <div class="ld-actions">
                {{-- Thêm / Import Lead: chỉ Sale Admin --}}
                @if ($this->canCreate)
                    <button type="button" class="vc-btn vc-btn--ghost" wire:click="openImport">
                        <x-icon name="download" />{{ __('leads.import') }}
                    </button>
                    <button type="button" class="vc-btn vc-btn--ghost" wire:click="openCreate">
                        <x-icon name="plus" />{{ __('leads.add') }}
                    </button>
                @endif
                <button type="button" class="vc-btn vc-btn--primary" wire:click="$dispatchTo('leads.distribute', 'open-distribute')">
                    <x-icon name="share" />
                    {{ $this->routePrefix === Role::SALE_ADMIN ? __('leads.distribute_team') : __('leads.distribute_salesperson') }}
                </button>
            </div>
        @endif
    </header>

    {{-- ── Thông báo lỗi / cảnh báo; thành công hiện bằng toast ──────── --}}
    @include('livewire.leads.partials.toast')

    @if ($notice && $notice['type'] !== 'success')
        <div class="ld-notice ld-notice--{{ $notice['type'] }}" role="status" wire:key="notice-{{ md5(json_encode($notice)) }}">
            <x-icon :name="$notice['type'] === 'success' ? 'check-circle' : 'alert'" />
            <span class="ld-notice__text">{{ $notice['text'] }}</span>
            <button type="button" class="ld-notice__close" wire:click="dismissNotice" aria-label="{{ __('leads.close') }}">
                <x-icon name="x" />
            </button>
        </div>
    @endif

    {{-- ── Thống kê theo bộ lọc (bấm thẻ để lọc, bấm lại để bỏ lọc) ──── --}}
    <div class="ldl-stats">
        @foreach ($cards as $key)
            <button type="button" wire:click="filterCard('{{ $key }}')" wire:key="card-{{ $key ?: 'all' }}"
                class="ldl-stat ldl-stat--{{ $key ?: 'all' }} {{ $activeCard === $key ? 'is-current' : '' }}"
                aria-pressed="{{ $activeCard === $key ? 'true' : 'false' }}">
                <span class="ldl-stat__label">{{ __('leads.card.'.($key ?: 'all')) }}</span>
                <span class="ldl-stat__value">{{ number_format($stats[$key ?: 'all'], 0, ',', '.') }}</span>
            </button>
        @endforeach
    </div>

    {{-- ── Bộ lọc ───────────────────────────────────────────────────── --}}
    <section class="ldl-filters {{ $this->canFilterBranch ? 'ldl-filters--branch' : '' }}" aria-label="{{ __('leads.filters') }}">
        <label class="ldl-search">
            <span class="visually-hidden">{{ __('leads.search') }}</span>
            <x-icon name="search" />
            <input type="search" wire:model.live.debounce.350ms="search" class="ldl-control"
                placeholder="{{ __('leads.search_placeholder') }}">
            <span class="ldl-search__spin" wire:loading.delay wire:target="search">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            </span>
        </label>

        <select wire:model.live="status" class="ldl-control" aria-label="{{ __('leads.status') }}">
            <option value="">{{ __('leads.all_statuses') }}</option>
            @foreach (Lead::STATUSES as $option)
                <option value="{{ $option }}">{{ __('leads.status_name.'.$option) }}</option>
            @endforeach
        </select>

        <select wire:model.live="source" class="ldl-control" aria-label="{{ __('leads.source') }}">
            <option value="">{{ __('leads.all_sources') }}</option>
            @foreach ($this->sources as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </select>

        <select wire:model.live="method" class="ldl-control" aria-label="{{ __('leads.contact_method') }}">
            <option value="">{{ __('leads.all_methods') }}</option>
            @foreach (Lead::CONTACT_METHODS as $option)
                <option value="{{ $option }}">{{ __('leads.method.'.$option) }}</option>
            @endforeach
        </select>

        @if ($this->canFilterBranch)
            <select wire:model.live="branch" class="ldl-control" aria-label="{{ __('leads.branch') }}">
                <option value="">{{ __('leads.all_branches') }}</option>
                @foreach ($this->branches as $option)
                    <option value="{{ $option->branch_id }}">{{ $option->branch_name }}</option>
                @endforeach
            </select>
        @endif

        {{-- Khoảng ngày tạo: bấm 2 ngày trên lịch --}}
        <x-date-range from="from" to="to" class="ldl-range" />

        @if ($this->hasFilters())
            <button type="button" class="ldl-reset" wire:click="resetFilters" title="{{ __('leads.reset') }}" aria-label="{{ __('leads.reset') }}">
                <x-icon name="refresh" />
            </button>
        @endif
    </section>

    {{-- ── Danh sách ────────────────────────────────────────────────── --}}
    <section class="ldl-card">
        <span class="ldl-loading" wire:loading.delay wire:target="{{ $loadingTargets }}">
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>{{ __('leads.loading') }}
        </span>

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
            <div class="ldl-table-wrap" wire:loading.class="is-loading" wire:target="{{ $loadingTargets }}">
                <table class="ldl-table">
                    <thead>
                        <tr>
                            <th>{{ __('leads.customer') }}</th>
                            <th>{{ __('leads.col_phone') }}</th>
                            <th>{{ __('leads.source') }}</th>
                            <th>{{ __('leads.col_method') }}</th>
                            <th>{{ __('leads.created_at') }}</th>
                            <th>{{ __('leads.col_owner') }}</th>
                            <th>{{ __('leads.status') }}</th>
                            <th class="ldl-table__actions">{{ __('leads.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($leads as $lead)
                            @php
                                $latest = $lead->latestOpportunity;
                                // Khách cũ được đưa về hàng chờ: Lead 'Mới' mà cơ hội gần nhất đã kết thúc → chưa ai phụ trách
                                $returning = $lead->status === Lead::STATUS_NEW && $latest && $latest->status !== \App\Models\Opportunity::STATUS_IN_PROGRESS;
                                $owner = $returning ? null : $latest?->employee;
                                $url = route($this->routePrefix.'.leads.show', $lead);
                                $assignable = $this->canManage && $this->canAssign($lead);
                                // Chấm trạng thái: chờ phân (cam), đang được chăm sóc (xanh lá), đã chuyển đổi (xanh dương)
                                $tone = match (true) {
                                    $lead->status === Lead::STATUS_CONVERTED => 'converted',
                                    ! $owner || ($owner->ledTeam && $latest->stage_id === \App\Models\Stage::UNTOUCHED) => 'waiting',
                                    default => 'active',
                                };
                            @endphp
                            <tr wire:key="lead-{{ $lead->lead_id }}" onclick="window.location='{{ $url }}'">
                                <td data-label="{{ __('leads.customer') }}">
                                    <span class="ldl-name">
                                        {{ $lead->full_name }}
                                        @if ($returning)
                                            <span class="ldl-tag" title="{{ __('leads.returning', ['name' => $latest->employee?->full_name ?? '-']) }}">{{ __('leads.returning_tag') }}</span>
                                        @endif
                                    </span>
                                    <span class="ldl-code">
                                        {{ $lead->lead_id }}
                                        @if (! $this->fixedBranch && $lead->branch)
                                            <span class="ldl-code__branch">· {{ $lead->branch->branch_name }}</span>
                                        @endif
                                    </span>
                                </td>
                                <td data-label="{{ __('leads.col_phone') }}" class="ldl-nowrap">{{ $lead->phone ?: '-' }}</td>
                                <td data-label="{{ __('leads.source') }}">
                                    @if ($lead->sourceUrlIsLink())
                                        <a href="{{ $lead->source_url }}" target="_blank" rel="noopener noreferrer" class="ldl-link"
                                            onclick="event.stopPropagation()">{{ $lead->source_name }}</a>
                                    @else
                                        {{ $lead->source_name }}
                                    @endif
                                </td>
                                <td data-label="{{ __('leads.col_method') }}">{{ __('leads.method.'.$lead->contact_method) }}</td>
                                <td data-label="{{ __('leads.created_at') }}" class="ldl-nowrap"
                                    title="{{ $lead->created_at?->format('d/m/Y H:i') }}">{{ $lead->created_at?->format('d/m/Y') }}</td>
                                <td data-label="{{ __('leads.col_owner') }}">
                                    @if ($owner)
                                        {{ $owner->full_name }}
                                        {{-- Trưởng nhóm đang giữ ở stage đầu = Lead của đội chờ phân cho Salesperson --}}
                                        @if ($owner->ledTeam && $latest->stage_id === \App\Models\Stage::UNTOUCHED)
                                            <span class="ldl-sub">{{ __('leads.pending_team', ['team' => $owner->ledTeam->team_name]) }}</span>
                                        @endif
                                    @else
                                        <span class="ldl-muted">{{ __('leads.not_assigned') }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ __('leads.status') }}">
                                    <span class="ldl-status ldl-status--{{ $tone }}">
                                        <span class="ldl-status__dot"></span>{{ __('leads.tone.'.$tone) }}
                                    </span>
                                </td>
                                <td data-label="{{ __('leads.actions') }}" class="ldl-table__actions">
                                    @if ($assignable)
                                        <button type="button" class="ldl-icon-btn ldl-icon-btn--assign" onclick="event.stopPropagation()"
                                            wire:click="$dispatchTo('leads.assign-one', 'open-assign-one', { leadId: '{{ $lead->lead_id }}' })"
                                            title="{{ __('leads.assign_one.button') }}" aria-label="{{ __('leads.assign_one.button') }} {{ $lead->lead_id }}">
                                            <x-icon name="user-plus" />
                                        </button>
                                    @endif
                                    <a href="{{ $url }}" class="ldl-icon-btn" onclick="event.stopPropagation()"
                                        title="{{ __('leads.view') }}" aria-label="{{ __('leads.view') }} {{ $lead->lead_id }}">
                                        <x-icon name="eye" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <footer class="ldl-foot">
                <span class="ldl-foot__count">
                    {{ __('leads.showing', ['shown' => $leads->count(), 'total' => number_format($leads->total(), 0, ',', '.')]) }}
                </span>
                @if ($leads->hasPages())
                    @php
                        $current = $leads->currentPage();
                        $last = $leads->lastPage();
                        $first = max(1, min($current - 2, $last - 4));
                    @endphp
                    <nav class="ldl-pager" aria-label="{{ __('leads.pagination') }}">
                        <button type="button" class="ldl-pager__btn" wire:click="previousPage" @disabled($leads->onFirstPage())>
                            {{ __('leads.prev') }}
                        </button>
                        @foreach (range($first, min($last, $first + 4)) as $page)
                            <button type="button" class="ldl-pager__btn ldl-pager__num {{ $page === $current ? 'is-active' : '' }}"
                                wire:click="gotoPage({{ $page }})" @if ($page === $current) aria-current="page" @endif>{{ $page }}</button>
                        @endforeach
                        <button type="button" class="ldl-pager__btn" wire:click="nextPage" @disabled(! $leads->hasMorePages())>
                            {{ __('leads.next') }}
                        </button>
                    </nav>
                @endif
            </footer>
        @endif
    </section>

    {{-- ── Popup Phân bổ Lead (Sale Admin → đội, Sale Leader → Salesperson) ── --}}
    @if ($this->canManage)
        <livewire:leads.distribute :auto-open="request()->boolean('distribute')" />
        {{-- Phân một Lead cụ thể (nút ở cột Thao tác) --}}
        <livewire:leads.assign-one />
    @endif

    {{-- ── Popup Thêm Lead ──────────────────────────────────────────── --}}
    @if ($modal === 'create' && $this->canCreate)
        <div class="ld-modal" wire:key="modal-create" x-data x-on:keydown.escape.window="$wire.closeModal()">
            <div class="ld-modal__backdrop" wire:click="closeModal"></div>
            <form class="ld-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="ld-create-title"
                wire:submit="saveLead">
                <header class="ld-modal__head ld-modal__head--center">
                    <span class="ld-modal__icon"><x-icon name="user" /></span>
                    <h2 class="ld-modal__title" id="ld-create-title">{{ __('leads.form.title') }}</h2>
                    <button type="button" class="ld-modal__close" wire:click="closeModal" aria-label="{{ __('leads.close') }}">
                        <x-icon name="x" />
                    </button>
                </header>

                <div class="ld-modal__body ld-form">
                    @php
                        // SĐT, Link nguồn bắt buộc tuỳ kênh liên hệ (không phụ thuộc Nguồn Lead)
                        $method = $form['contact_method'] ?? '';
                    @endphp

                    {{-- Khách cũ khớp liên hệ (LeadDuplicateService): chọn cùng người / người học khác --}}
                    @if ($duplicate)
                        @php
                            $dupCase = $duplicate['case'];
                            // Hỏi "cùng người hay người khác" khi chưa xác định được Lead cùng người
                            $askSame = in_array($dupCase, [\App\Services\LeadDuplicateService::OTHER_PERSON, \App\Services\LeadDuplicateService::AMBIGUOUS], true);
                            // Tông màu: chặn (đang chờ / đang tư vấn) = cam, đưa về hàng chờ = xanh lá, cần chọn = xanh dương
                            $dupTone = match ($dupCase) {
                                \App\Services\LeadDuplicateService::REOPEN => 'ok',
                                \App\Services\LeadDuplicateService::IN_QUEUE, \App\Services\LeadDuplicateService::IN_PROGRESS => 'warn',
                                default => 'info',
                            };
                            $dupIcon = ['ok' => 'refresh', 'warn' => 'alert', 'info' => 'users'][$dupTone];
                            $stateTone = ['in_queue' => 'warn', 'in_queue_team' => 'warn', 'in_progress' => 'info', 'reopen' => 'ok'];
                            // Một Lead: nhãn trạng thái lên hàng tiêu đề; nhiều Lead: nhãn nằm trong từng dòng
                            $single = count($duplicate['leads']) === 1 ? $duplicate['leads'][0] : null;
                            $statePill = fn (array $item) => '<span class="ld-dup__state ld-dup__state--'.($stateTone[$item['state']] ?? 'info').'">'
                                .'<span class="ld-dup__dot"></span>'
                                // Có người phụ trách (đang tư vấn) → "Đang tư vấn bởi …"
                                .e($item['owner'] ? __('leads.duplicate.state_by.'.$item['state'], ['name' => $item['owner']]) : __('leads.duplicate.state.'.$item['state']))
                                .'</span>';
                        @endphp
                        <section class="ld-dup ld-dup--{{ $dupTone }} ld-form__wide" wire:key="dup-{{ $dupCase }}-{{ $duplicate['lead'] }}"
                            aria-live="polite">
                            <header class="ld-dup__head">
                                <x-icon :name="$dupIcon" class="ld-dup__icon" />
                                <p class="ld-dup__title">{{ __('leads.duplicate.title.'.$dupTone) }}</p>
                                @if ($single)
                                    {!! $statePill($single) !!}
                                @endif
                            </header>

                            {{-- Đang chờ phân / đang tư vấn: nhãn trạng thái đã đủ ý nên không lặp lại câu thông báo --}}
                            @unless (in_array($dupCase, [\App\Services\LeadDuplicateService::IN_QUEUE, \App\Services\LeadDuplicateService::IN_PROGRESS], true))
                                <p class="ld-dup__message">{{ $duplicate['message'] }}</p>
                            @endunless

                            <ul class="ld-dup__list" role="list">
                                @foreach ($duplicate['leads'] as $item)
                                    @php
                                        // Bảng cho chọn cập nhật: đã xác định cùng người + còn trường được cập nhật; còn lại chỉ để đối chiếu
                                        $editableCount = collect($item['changes'])->where('editable', true)->count();
                                        $canUpdate = $duplicate['updatable'] && $editableCount > 0;
                                        $chosen = $canUpdate ? count(array_intersect($updateFields, array_keys(array_filter($item['changes'], fn ($c) => $c['editable'])))) : 0;
                                    @endphp
                                    <li class="ld-dup__entry" wire:key="dup-lead-{{ $item['lead_id'] }}">
                                        <div class="ld-dup__item">
                                            <span class="ld-dup__avatar" aria-hidden="true">
                                                {{ mb_strtoupper(mb_substr(\Illuminate\Support\Str::afterLast(trim($item['learner']), ' '), 0, 1)) }}
                                            </span>
                                            <div class="ld-dup__info">
                                                <div class="ld-dup__name">
                                                    <strong>{{ $item['name'] }}</strong>
                                                    <a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="ld-dup__code"
                                                        title="{{ __('leads.view') }}">{{ $item['lead_id'] }}<x-icon name="eye" /></a>
                                                </div>
                                                <div class="ld-dup__meta">
                                                    @if ($item['learner'] !== $item['name'])
                                                        <span><x-icon name="user" />{{ __('leads.duplicate.learner', ['name' => $item['learner']]) }}</span>
                                                    @endif
                                                    @if ($item['contact'])
                                                        <span><x-icon name="phone" />{{ $item['contact'] }}</span>
                                                    @endif
                                                </div>
                                                @unless ($single)
                                                    {!! $statePill($item) !!}
                                                @endunless
                                            </div>
                                            @if ($askSame)
                                                <button type="button" class="vc-btn vc-btn--ghost ld-dup__btn" wire:click="sameAs('{{ $item['lead_id'] }}')"
                                                    wire:loading.attr="disabled" wire:target="sameAs,createAsOther">
                                                    <x-icon name="user-check" />{{ __('leads.duplicate.same_person') }}
                                                </button>
                                            @elseif ($dupCase === \App\Services\LeadDuplicateService::REOPEN)
                                                <button type="button" class="vc-btn vc-btn--primary ld-dup__btn" wire:click="sameAs('{{ $item['lead_id'] }}')"
                                                    wire:loading.attr="disabled" wire:target="sameAs,createAsOther">
                                                    <x-icon name="refresh" />{{ __('leads.duplicate.reopen_button', ['name' => $item['learner']]) }}
                                                </button>
                                            @endif
                                        </div>

                                        {{-- So sánh thông tin vừa nhập với Lead này --}}
                                        <div class="ld-upd {{ $canUpdate ? 'is-updatable' : '' }}">
                                            <div class="ld-upd__head">
                                                <p class="ld-upd__title">
                                                    <x-icon :name="$canUpdate ? 'edit' : 'layers'" />{{ __('leads.duplicate.'.($canUpdate ? 'changes_title' : 'compare_title')) }}
                                                </p>
                                            </div>

                                            @if ($item['changes'])
                                                <div class="ld-upd__table" role="table">
                                                    <div class="ld-upd__row ld-upd__row--head" role="row">
                                                        <span role="columnheader"></span>
                                                        <span role="columnheader">{{ __('leads.duplicate.col_field') }}</span>
                                                        <span role="columnheader">{{ __('leads.duplicate.col_old') }}</span>
                                                        <span role="columnheader">{{ __('leads.duplicate.col_new') }}</span>
                                                    </div>
                                                    @foreach ($item['changes'] as $field => $change)
                                                        @php
                                                            $editable = $canUpdate && $change['editable'];
                                                            $on = $editable && in_array($field, $updateFields, true);
                                                            $rowTag = $editable ? 'label' : 'div';
                                                        @endphp
                                                        <{{ $rowTag }} class="ld-upd__row {{ $editable ? 'is-editable' : '' }} {{ $duplicate['updatable'] && ! $editable ? 'is-locked' : '' }} {{ $on ? 'is-on' : '' }}"
                                                            role="row" wire:key="upd-{{ $item['lead_id'] }}-{{ $field }}">
                                                            <span role="cell">
                                                                @if ($editable)
                                                                    <input type="checkbox" wire:model.live="updateFields" value="{{ $field }}">
                                                                    <span class="ld-upd__check" aria-hidden="true"><x-icon name="check" /></span>
                                                                @elseif ($duplicate['updatable'])
                                                                    <span class="ld-upd__lock" title="{{ __('leads.duplicate.locked') }}"><x-icon name="shield" /></span>
                                                                @endif
                                                            </span>
                                                            <span role="cell" class="ld-upd__field">{{ $change['label'] }}</span>
                                                            <span role="cell" class="ld-upd__old">{{ $change['old_text'] }}</span>
                                                            <span role="cell" class="ld-upd__new">{{ $change['new_text'] }}</span>
                                                        </{{ $rowTag }}>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="ld-upd__same"><x-icon name="check-circle" />{{ __('leads.duplicate.compare_same') }}</p>
                                            @endif

                                            {{-- Vì sao có trường không cập nhật được (cùng lời giải thích với bảng xem trước khi import) --}}
                                            @if ($item['contacted'] && $item['changes'])
                                                <p class="ld-imp__locked"><x-icon name="shield" />{{ __('leads.import_review.locked.contacted') }}</p>
                                            @elseif ($duplicate['updatable'] && collect($item['changes'])->contains('editable', false))
                                                <p class="ld-imp__locked">
                                                    <x-icon name="shield" />{{ __('leads.import_review.locked.'.($item['returning'] ? 'returning' : 'branch')) }}
                                                </p>
                                            @endif

                                            @error('duplicate')
                                                <p class="ld-error">{{ $message }}</p>
                                            @enderror

                                            @if ($canUpdate)
                                                <div class="ld-upd__foot">
                                                    <span class="ld-upd__note">{{ __('leads.duplicate.changes_count', ['count' => $chosen, 'total' => $editableCount]) }}</span>
                                                    <button type="button" class="vc-btn vc-btn--primary ld-dup__btn" wire:click="updateExisting"
                                                        wire:loading.attr="disabled" wire:target="updateExisting" @disabled(! $chosen)>
                                                        <x-icon name="check" />{{ __('leads.duplicate.update_button', ['count' => $chosen]) }}
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>

                            {{-- Đã bấm "Cùng người" thì không hỏi "người học khác" nữa --}}
                            @unless ($duplicate['confirmed'])
                                <footer class="ld-dup__other">
                                    <div class="ld-dup__other-text">
                                        <strong>{{ __('leads.duplicate.other_question') }}</strong>
                                        <span>{{ __('leads.duplicate.other_example') }}</span>
                                    </div>
                                    <button type="button" class="vc-btn vc-btn--ghost ld-dup__btn" wire:click="createAsOther"
                                        wire:loading.attr="disabled" wire:target="sameAs,createAsOther">
                                        <x-icon name="plus" />{{ __('leads.duplicate.other_button') }}
                                    </button>
                                </footer>
                            @endunless
                        </section>
                    @endif

                    <label class="vc-field">
                        <span class="vc-field__label">
                            {{ __('leads.field.full_name') }}<span class="ld-req">*</span>
                        </span>
                        <input type="text" wire:model="form.full_name" placeholder="{{ __('leads.form.placeholder_name') }}"
                            class="vc-control @error('full_name') is-invalid @enderror" autofocus>
                        @error('full_name')<span class="ld-error">{{ $message }}</span>@enderror
                    </label>

                    <label class="vc-field">
                        <span class="vc-field__label">{{ __('leads.field.contact_method') }}<span class="ld-req">*</span></span>
                        <select wire:model.live="form.contact_method" class="vc-control @error('contact_method') is-invalid @enderror">
                            <option value="">{{ __('leads.form.choose_method') }}</option>
                            @foreach (Lead::CONTACT_METHODS as $option)
                                <option value="{{ $option }}">{{ __('leads.method.'.$option) }}</option>
                            @endforeach
                        </select>
                        @error('contact_method')<span class="ld-error">{{ $message }}</span>@enderror
                    </label>

                    <label class="vc-field">
                        <span class="vc-field__label">
                            {{ __('leads.field.phone') }}@if (in_array($method, Lead::METHODS_PHONE_REQUIRED, true))<span class="ld-req">*</span>@endif
                        </span>
                        <input type="tel" wire:model="form.phone" placeholder="{{ __('leads.form.placeholder_phone') }}"
                            class="vc-control @error('phone') is-invalid @enderror">
                        @error('phone')<span class="ld-error">{{ $message }}</span>@enderror
                    </label>

                    <label class="vc-field">
                        <span class="vc-field__label">
                            {{ __('leads.field.email') }}
                        </span>
                        <input type="email" wire:model="form.email" placeholder="{{ __('leads.form.placeholder_email') }}"
                            class="vc-control @error('email') is-invalid @enderror">
                        @error('email')<span class="ld-error">{{ $message }}</span>@enderror
                    </label>

                    <label class="vc-field">
                        <span class="vc-field__label">{{ __('leads.field.source_name') }}<span class="ld-req">*</span></span>
                        <select wire:model="form.source_name" class="vc-control @error('source_name') is-invalid @enderror">
                            <option value="">{{ __('leads.form.choose_source') }}</option>
                            @foreach (Lead::SOURCES as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                        @error('source_name')<span class="ld-error">{{ $message }}</span>@enderror
                    </label>

                    <label class="vc-field">
                        <span class="vc-field__label">{{ __('leads.branch') }}<span class="ld-req">*</span></span>
                        <select wire:model="form.branch_id" class="vc-control @error('branch_id') is-invalid @enderror">
                            <option value="">{{ __('leads.form.choose_branch') }}</option>
                            @foreach ($this->branches as $option)
                                <option value="{{ $option->branch_id }}">{{ $option->branch_name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')<span class="ld-error">{{ $message }}</span>@enderror
                    </label>

                    {{-- Bắt buộc khi liên hệ qua Facebook: Salesperson cần link trang cá nhân/bài viết để nhắn tin --}}
                    <label class="vc-field ld-form__wide">
                        <span class="vc-field__label">
                            {{ __('leads.field.source_url') }}@if (in_array($method, Lead::METHODS_LINK_REQUIRED, true))<span class="ld-req">*</span>@endif
                        </span>
                        <input type="text" wire:model="form.source_url" placeholder="{{ __('leads.form.placeholder_url') }}"
                            class="vc-control @error('source_url') is-invalid @enderror">
                        @error('source_url')<span class="ld-error">{{ $message }}</span>@enderror
                    </label>

                    <p class="ld-form__hint ld-form__wide">{{ __('leads.form.required_hint') }}</p>
                </div>

                <footer class="ld-modal__foot">
                    <button type="button" class="vc-btn vc-btn--ghost" wire:click="closeModal">{{ __('leads.cancel') }}</button>
                    {{-- Đang hiện khách cũ trùng: chọn ở khung phía trên; sửa dữ liệu rồi bấm Lưu để kiểm tra lại --}}
                    <button type="submit" class="vc-btn {{ $duplicate ? 'vc-btn--ghost' : 'vc-btn--primary' }}" wire:loading.attr="disabled" wire:target="saveLead">
                        <span wire:loading.remove wire:target="saveLead"><x-icon name="check" /></span>
                        <span class="spinner-border spinner-border-sm" wire:loading wire:target="saveLead" aria-hidden="true"></span>
                        {{ $duplicate ? __('leads.duplicate.recheck') : __('leads.form.save') }}
                    </button>
                </footer>
            </form>
        </div>
    @endif

    {{-- ── Popup Import Excel: tải file → xem lại từng dòng → xác nhận ── --}}
    @if ($modal === 'import' && $this->canCreate)
        @php $preview = ! $importResult ? $this->preview : null; @endphp
        <div class="ld-modal" wire:key="modal-import" x-data x-on:keydown.escape.window="$wire.closeModal()">
            <div class="ld-modal__backdrop" wire:click="closeModal"></div>
            <div class="ld-modal__dialog {{ $preview ? 'ld-modal__dialog--wide ld-imp' : '' }}" role="dialog" aria-modal="true" aria-labelledby="ld-import-title">
                {{-- Tiêu đề căn giữa theo icon (giống popup Thêm Lead) --}}
                <header class="ld-modal__head ld-modal__head--center">
                    <span class="ld-modal__icon"><x-icon name="upload" /></span>
                    <div>
                        <h2 class="ld-modal__title" id="ld-import-title">{{ __('leads.excel.title') }}</h2>
                        @if ($preview)
                            <p class="ld-modal__sub">{{ __('leads.import_review.sub') }}</p>
                        @endif
                    </div>
                    <button type="button" class="ld-modal__close" wire:click="closeModal" aria-label="{{ __('leads.close') }}">
                        <x-icon name="x" />
                    </button>
                </header>

                <div class="ld-modal__body">
                    @if ($importResult)
                        {{-- Kết quả import --}}
                        @php
                            $tone = $importResult['failed'] === 0 && $importResult['skipped'] === 0 ? 'success' : ($importResult['success'] === 0 ? 'danger' : 'warning');
                        @endphp
                        <div class="ld-result ld-result--{{ $tone }}">
                            <p class="ld-result__title">
                                <x-icon :name="$tone === 'success' ? 'check-circle' : 'alert'" />{{ $notice['text'] ?? __('leads.excel.result_title') }}
                            </p>
                            <div class="ld-result__stats ld-result__stats--4">
                                <div><span>{{ __('leads.excel.result_total') }}</span><strong>{{ $importResult['total'] }}</strong></div>
                                <div class="is-ok"><span>{{ __('leads.excel.result_success') }}</span><strong>{{ $importResult['success'] }}</strong></div>
                                <div><span>{{ __('leads.import_review.result_skipped') }}</span><strong>{{ $importResult['skipped'] }}</strong></div>
                                <div class="is-fail"><span>{{ __('leads.excel.result_failed') }}</span><strong>{{ $importResult['failed'] }}</strong></div>
                            </div>
                        </div>
                        @if ($importError)
                            <p class="ld-error">{{ $importError }}</p>
                        @endif
                    @elseif ($preview)
                        {{-- Bảng xem trước: chọn cách xử lý từng dòng --}}
                        @php
                            $kindTone = [
                                'new' => 'ok', 'invalid' => 'danger', 'file_dup' => 'warn', 'file_family' => 'info',
                                'other_person' => 'info', 'in_queue' => 'warn', 'in_progress' => 'warn', 'reopen' => 'ok', 'ambiguous' => 'warn',
                            ];
                            $stateTone = ['in_queue' => 'warn', 'in_queue_team' => 'warn', 'in_progress' => 'info', 'reopen' => 'ok'];
                            $leadList = fn (array $row) => collect($row['leads'])->map(fn ($l) => $l['lead_id'].' - '.$l['name'])->implode('; ');
                            $kindText = function (array $row) use ($leadList) {
                                $first = $row['leads'][0] ?? null;

                                return match ($row['kind']) {
                                    'file_dup', 'file_family' => __('leads.import_review.kind.'.$row['kind'], ['line' => $row['ref_line']]),
                                    'other_person', 'ambiguous' => __('leads.import_review.kind.'.$row['kind'], ['leads' => $leadList($row)]),
                                    'in_queue', 'in_progress', 'reopen' => __('leads.import_review.kind.'.($first['state'] ?? $row['kind']), [
                                        'lead' => $first['lead_id'].' - '.$first['name'], 'owner' => $first['owner'] ?? '-',
                                    ]),
                                    default => __('leads.import_review.kind.'.$row['kind']),
                                };
                            };
                            $actionText = fn (string $action, array $row) => str_starts_with($action, \App\Services\LeadImportService::ACTION_SAME)
                                ? __('leads.import_review.action.same', ['id' => substr($action, strlen(\App\Services\LeadImportService::ACTION_SAME))])
                                : __('leads.import_review.action.'.($action === 'create' && $row['kind'] !== 'new' ? 'create_other' : $action));
                            $filters = ['review' => 'alert', 'new' => 'user', 'invalid' => 'x', 'all' => 'layers'];
                            $summary = $this->previewSummary();
                        @endphp

                        <div class="ld-imp__filters" role="tablist">
                            @foreach ($filters as $key => $icon)
                                <button type="button" role="tab" wire:click="setPreviewFilter('{{ $key }}')"
                                    class="ld-imp__filter ld-imp__filter--{{ $key }} {{ $previewFilter === $key ? 'is-current' : '' }}"
                                    aria-selected="{{ $previewFilter === $key ? 'true' : 'false' }}">
                                    <x-icon :name="$icon" />
                                    <span>{{ __('leads.import_review.filter.'.$key) }}</span>
                                    <strong>{{ $this->previewCount($key) }}</strong>
                                </button>
                            @endforeach
                        </div>

                        @php $pageRows = $this->previewPageRows(); @endphp
                        @if (! $pageRows)
                            <p class="ld-imp__empty"><x-icon name="check-circle" />{{ __('leads.import_review.empty_filter') }}</p>
                        @else
                            <ul class="ld-imp__list" role="list">
                                @foreach ($pageRows as $row)
                                    @php
                                        $line = $row['line'];
                                        $action = $this->decisionFor($row);
                                        $willUpdate = $action === 'update' || str_starts_with($action, \App\Services\LeadImportService::ACTION_SAME);
                                        $open = $previewOpen === $line;
                                    @endphp
                                    <li class="ld-imp__row ld-imp__row--{{ $kindTone[$row['kind']] ?? 'info' }} {{ $action === 'skip' ? 'is-skip' : '' }}" wire:key="imp-{{ $line }}">
                                        <div class="ld-imp__main">
                                            <span class="ld-imp__line">{{ __('leads.import_review.line', ['line' => $line]) }}</span>
                                            <div class="ld-imp__who">
                                                <strong>{{ $row['data']['full_name'] ?: '-' }}</strong>
                                                <span>{{ collect([$row['data']['phone'], $row['data']['email']])->filter()->implode(' · ') ?: '-' }}</span>
                                            </div>
                                            <div class="ld-imp__kind">
                                                <span class="ld-imp__badge"><span class="ld-dup__dot"></span>{{ $kindText($row) }}</span>
                                                @if ($row['errors'])
                                                    <ul class="ld-imp__errors">
                                                        @foreach ($row['errors'] as $error)
                                                            <li>{{ $error }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </div>
                                            <div class="ld-imp__action">
                                                @if (count($row['options']) > 1)
                                                    <select class="vc-control ld-imp__select" wire:model.live="decisions.{{ $line }}"
                                                        aria-label="{{ __('leads.import_review.col_action') }} - {{ __('leads.import_review.line', ['line' => $line]) }}">
                                                        @foreach ($row['options'] as $option)
                                                            <option value="{{ $option }}">{{ $actionText($option, $row) }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <span class="ld-imp__fixed">{{ $actionText($row['options'][0], $row) }}</span>
                                                @endif
                                                @if ($row['leads'])
                                                    <button type="button" class="ld-imp__toggle {{ $open ? 'is-open' : '' }}" wire:click="togglePreviewRow({{ $line }})"
                                                        aria-expanded="{{ $open ? 'true' : 'false' }}">
                                                        <x-icon name="layers" />{{ __('leads.import_review.compare') }}<x-icon name="chevron" />
                                                    </button>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- So sánh với Lead cũ (chỉ để xem; cập nhật theo cách xử lý đã chọn) --}}
                                        @if ($open)
                                            <div class="ld-imp__detail">
                                                @foreach ($row['leads'] as $lead)
                                                    <div class="ld-imp__lead" wire:key="imp-{{ $line }}-{{ $lead['lead_id'] }}">
                                                        <div class="ld-imp__lead-head">
                                                            <strong>{{ $lead['name'] }}</strong>
                                                            <span class="ld-dup__code">{{ $lead['lead_id'] }}</span>
                                                            <span class="ld-dup__state ld-dup__state--{{ $stateTone[$lead['state']] ?? 'info' }}">
                                                                <span class="ld-dup__dot"></span>{{ $lead['owner']
                                                                    ? __('leads.duplicate.state_by.'.$lead['state'], ['name' => $lead['owner']])
                                                                    : __('leads.duplicate.state.'.$lead['state']) }}
                                                            </span>
                                                        </div>
                                                        @if ($lead['changes'])
                                                            <div class="ld-upd__table" role="table">
                                                                <div class="ld-upd__row ld-upd__row--head" role="row">
                                                                    <span role="columnheader"></span>
                                                                    <span role="columnheader">{{ __('leads.duplicate.col_field') }}</span>
                                                                    <span role="columnheader">{{ __('leads.duplicate.col_old') }}</span>
                                                                    <span role="columnheader">{{ __('leads.duplicate.col_new') }}</span>
                                                                </div>
                                                                @php
                                                                    // Đã xác định cùng người (Lead đang chờ phân / đang tư vấn) → trường không cập nhật được hiện icon khoá
                                                                    $sure = in_array($row['kind'], ['in_queue', 'in_progress'], true);
                                                                    $locked = $sure && collect($lead['changes'])->contains('editable', false);
                                                                @endphp
                                                                @foreach ($lead['changes'] as $field => $change)
                                                                    @php $applies = $willUpdate && $change['editable']; @endphp
                                                                    <div class="ld-upd__row {{ $applies ? 'is-on' : '' }} {{ $sure && ! $change['editable'] ? 'is-locked' : '' }}" role="row">
                                                                        <span role="cell">
                                                                            @if ($applies)
                                                                                <span class="ld-upd__check" title="{{ __('leads.import_review.will_update') }}"><x-icon name="check" /></span>
                                                                            @elseif ($sure && ! $change['editable'])
                                                                                <span class="ld-upd__lock" title="{{ __('leads.duplicate.locked') }}"><x-icon name="shield" /></span>
                                                                            @endif
                                                                        </span>
                                                                        <span role="cell" class="ld-upd__field">{{ $change['label'] }}</span>
                                                                        <span role="cell" class="ld-upd__old">{{ $change['old'] }}</span>
                                                                        <span role="cell" class="ld-upd__new">{{ $change['new'] }}</span>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                            @if ($locked)
                                                                {{-- Vì sao có trường không cập nhật được --}}
                                                                <p class="ld-imp__locked">
                                                                    <x-icon name="shield" />{{ __('leads.import_review.locked.'.match (true) {
                                                                        $lead['contacted'] => 'contacted',
                                                                        $lead['returning'] => 'returning',
                                                                        default            => 'branch',
                                                                    }) }}
                                                                </p>
                                                            @endif
                                                        @else
                                                            <p class="ld-upd__same"><x-icon name="check-circle" />{{ __('leads.duplicate.compare_same') }}</p>
                                                        @endif
                                                    </div>
                                                @endforeach
                                                <p class="ld-imp__hint">{{ __('leads.import_review.compare_hint') }}</p>
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>

                            @if ($this->previewPages() > 1)
                                <nav class="ld-imp__pager" aria-label="{{ __('leads.import_review.pages') }}">
                                    <button type="button" class="vc-btn vc-btn--ghost" wire:click="gotoPreviewPage({{ $previewPage - 1 }})" @disabled($previewPage <= 1)>
                                        <x-icon name="arrow-left" />
                                    </button>
                                    <span>{{ __('leads.import_review.page', ['page' => $previewPage, 'pages' => $this->previewPages()]) }}</span>
                                    <button type="button" class="vc-btn vc-btn--ghost ld-imp__next" wire:click="gotoPreviewPage({{ $previewPage + 1 }})" @disabled($previewPage >= $this->previewPages())>
                                        <x-icon name="arrow-left" />
                                    </button>
                                </nav>
                            @endif
                        @endif

                        @if ($importError)
                            <div class="ld-notice ld-notice--danger ld-notice--inline">
                                <x-icon name="alert" /><span class="ld-notice__text">{{ $importError }}</span>
                            </div>
                        @endif
                    @else
                        <ol class="ld-steps">
                            <li class="ld-step">
                                <span class="ld-step__num">1</span>
                                <div class="ld-step__body">
                                    <p class="ld-step__title">{{ __('leads.excel.step_template') }}</p>
                                    <p class="ld-step__sub">{{ __('leads.excel.step_template_sub') }}</p>
                                    <button type="button" class="vc-btn vc-btn--ghost" wire:click="downloadTemplate"
                                        wire:loading.attr="disabled" wire:target="downloadTemplate">
                                        <x-icon name="file" />{{ __('leads.excel.download_template') }}
                                    </button>
                                </div>
                            </li>
                            <li class="ld-step">
                                <span class="ld-step__num">2</span>
                                <div class="ld-step__body">
                                    <p class="ld-step__title">{{ __('leads.excel.step_upload') }}</p>
                                    <p class="ld-step__sub">{{ __('leads.excel.step_upload_sub', ['max' => \App\Services\LeadImportService::MAX_ROWS]) }}</p>

                                    <label class="ld-drop @error('file') is-invalid @enderror {{ $file ? 'has-file' : '' }}">
                                        <input type="file" wire:model="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                                        <x-icon name="upload" />
                                        <span class="ld-drop__text">
                                            @if ($file && ! $errors->has('file'))
                                                <strong>{{ $file->getClientOriginalName() }}</strong>
                                            @else
                                                {{ __('leads.excel.choose_file') }}
                                            @endif
                                        </span>
                                        <span class="ld-drop__loading" wire:loading wire:target="file">
                                            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                            {{ __('leads.excel.uploading') }}
                                        </span>
                                    </label>
                                    @error('file')<span class="ld-error">{{ $message }}</span>@enderror
                                    @if ($importError)
                                        <div class="ld-notice ld-notice--danger ld-notice--inline">
                                            <x-icon name="alert" /><span class="ld-notice__text">{{ $importError }}</span>
                                        </div>
                                    @endif
                                </div>
                            </li>
                            <li class="ld-step">
                                <span class="ld-step__num">3</span>
                                <div class="ld-step__body">
                                    <p class="ld-step__title">{{ __('leads.import_review.step_review') }}</p>
                                    <p class="ld-step__sub">{{ __('leads.import_review.step_review_sub') }}</p>
                                </div>
                            </li>
                        </ol>
                    @endif
                </div>

                <footer class="ld-modal__foot {{ $preview ? 'ld-imp__foot' : '' }}">
                    @if ($importResult)
                        <button type="button" class="vc-btn vc-btn--ghost" wire:click="openImport">
                            <x-icon name="refresh" />{{ __('leads.excel.import_another') }}
                        </button>
                        <button type="button" class="vc-btn vc-btn--primary" wire:click="downloadResult">
                            <x-icon name="file" />{{ __('leads.excel.download_result') }}
                        </button>
                    @elseif ($preview)
                        <p class="ld-imp__summary">
                            {!! __('leads.import_review.summary', [
                                'create' => '<strong>'.$summary['create'].'</strong>',
                                'update' => '<strong>'.$summary['update'].'</strong>',
                                'reopen' => '<strong>'.$summary['reopen'].'</strong>',
                                'skip'   => '<strong>'.$summary['skip'].'</strong>',
                            ]) !!}
                        </p>
                        <button type="button" class="vc-btn vc-btn--ghost" wire:click="cancelPreview">
                            <x-icon name="arrow-left" />{{ __('leads.import_review.choose_other') }}
                        </button>
                        <button type="button" class="vc-btn vc-btn--primary" wire:click="confirmImport"
                            wire:loading.attr="disabled" wire:target="confirmImport"
                            @disabled($summary['create'] + $summary['update'] + $summary['reopen'] === 0)>
                            <span wire:loading.remove wire:target="confirmImport"><x-icon name="check" /></span>
                            <span class="spinner-border spinner-border-sm" wire:loading wire:target="confirmImport" aria-hidden="true"></span>
                            {{ __('leads.import_review.confirm') }}
                        </button>
                    @else
                        <button type="button" class="vc-btn vc-btn--ghost" wire:click="closeModal">{{ __('leads.cancel') }}</button>
                        <button type="button" class="vc-btn vc-btn--primary" wire:click="checkFile"
                            wire:loading.attr="disabled" wire:target="file,checkFile"
                            @disabled(! $file || $errors->has('file'))>
                            <span wire:loading.remove wire:target="checkFile"><x-icon name="search" /></span>
                            <span class="spinner-border spinner-border-sm" wire:loading wire:target="checkFile" aria-hidden="true"></span>
                            <span wire:loading.remove wire:target="checkFile">{{ __('leads.import_review.check') }}</span>
                            <span wire:loading wire:target="checkFile">{{ __('leads.excel.processing') }}</span>
                        </button>
                    @endif
                </footer>
            </div>
        </div>
    @endif
</div>
