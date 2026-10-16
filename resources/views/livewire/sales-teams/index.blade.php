@php
    use App\Livewire\SalesTeams\Index;

    $summary = $this->summary;

    // Thẻ tổng quan (chỉ hiển thị, không lọc): [key, icon, tone]
    $cards = [
        ['teams', 'layers', 'all'],
        ['in_team', 'users', 'active'],
        ['without', 'user', 'inactive'],
    ];

    // Số avatar hiện tối đa trong cột Thành viên, còn lại gộp thành "+n"
    $maxAvatars = 5;
@endphp

<div class="vc em">
    {{-- Dùng lại giao diện trang voucher (voucher.css), popup + thông báo của trang Lead (lead.css)
         + phần riêng resources/css/employee.css --}}
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/lead.css', 'resources/css/employee.css'])
    @endassets

    {{-- ── Tiêu đề ──────────────────────────────────────────────────── --}}
    <header class="vc-head">
        <div>
            <h1 class="vc-head__title">{{ __('employees.teams.title') }}</h1>
            <p class="vc-head__sub">{{ __('employees.teams.subtitle') }}</p>
        </div>

        {{-- Tạo đội: chỉ Sale Admin --}}
        @if ($this->canManage)
            <div class="ld-actions">
                <button type="button" class="vc-btn vc-btn--primary" wire:click="openCreate">
                    <x-icon name="plus" />{{ __('employees.teams.form.create') }}
                </button>
            </div>
        @endif
    </header>

    {{-- ── Thông báo sau khi lưu ────────────────────────────────────── --}}
    @if ($notice)
        <div class="ld-notice ld-notice--{{ $notice['type'] }}" role="status" wire:key="notice-{{ md5(json_encode($notice)) }}">
            <x-icon :name="$notice['type'] === 'success' ? 'check-circle' : 'alert'" />
            <span class="ld-notice__text">{{ $notice['text'] }}</span>
            <button type="button" class="ld-notice__close" wire:click="dismissNotice" aria-label="{{ __('employees.teams.form.close') }}">
                <x-icon name="x" />
            </button>
        </div>
    @endif

    {{-- ── Tổng quan ────────────────────────────────────────────────── --}}
    <div class="vc-summary">
        @foreach ($cards as [$key, $icon, $tone])
            <div class="vc-summary__item em-summary-static vc-tone--{{ $tone }}">
                <span class="vc-summary__icon"><x-icon :name="$icon" /></span>
                <span class="vc-summary__text">
                    <span class="vc-summary__label">{{ __('employees.teams.summary.'.$key) }}</span>
                    <span class="vc-summary__value">{{ $summary[$key] }}</span>
                </span>
            </div>
        @endforeach
    </div>

    {{-- ── Tra cứu: tìm kiếm + sắp xếp ──────────────────────────────── --}}
    <section class="vc-card em-filters" aria-label="{{ __('employees.teams.lookup') }}">
        <label class="vc-field">
            <span class="vc-field__label">{{ __('employees.search') }}</span>
            <span class="vc-search">
                <x-icon name="search" />
                <input type="search" wire:model.live.debounce.350ms="search"
                    placeholder="{{ __('employees.teams.search_placeholder') }}">
                <span class="vc-search__spin" wire:loading.delay wire:target="search">
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                </span>
            </span>
        </label>

        <label class="vc-field">
            <span class="vc-field__label">{{ __('employees.sort_by') }}</span>
            <select wire:model.live="sort" class="vc-control">
                @foreach (Index::SORTS as $option)
                    <option value="{{ $option }}">{{ __('employees.teams.sort.'.$option) }}</option>
                @endforeach
            </select>
        </label>

        <div class="vc-field vc-field--action">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="resetFilters" @disabled(! $this->hasFilters())>
                <x-icon name="refresh" />{{ __('employees.reset') }}
            </button>
        </div>
    </section>

    {{-- ── Kết quả ──────────────────────────────────────────────────── --}}
    <section class="vc-card vc-results">
        <div class="vc-results__bar">
            <span>
                {!! __('employees.teams.result_count', ['count' => '<strong>'.$teams->total().'</strong>']) !!}
            </span>
            <span class="vc-results__loading" wire:loading.delay>
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                {{ __('employees.loading') }}
            </span>
        </div>

        @if ($teams->isEmpty())
            <div class="vc-empty">
                <span class="vc-empty__icon"><x-icon name="inbox" /></span>
                <p class="vc-empty__title">{{ __('employees.teams.empty') }}</p>
                <p class="vc-empty__sub">{{ __('employees.empty_sub') }}</p>
                @if ($this->hasFilters())
                    <button type="button" class="vc-btn vc-btn--primary" wire:click="resetFilters">
                        <x-icon name="refresh" />{{ __('employees.reset') }}
                    </button>
                @endif
            </div>
        @else
            <div class="vc-table-wrap" wire:loading.class="is-loading">
                <table class="vc-table vc-table--static">
                    <thead>
                        <tr>
                            <th>{{ __('employees.teams.team') }}</th>
                            <th>{{ __('employees.team_leader') }}</th>
                            <th>{{ __('employees.branch') }}</th>
                            <th>{{ __('employees.established_date') }}</th>
                            <th>{{ __('employees.members') }}</th>
                            <th class="text-lg-end">{{ __('employees.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teams as $team)
                            @php
                                $membersUrl = route($this->routePrefix.'.employees', ['team' => $team->team_id]);
                                $members = $team->employees;
                                // Chi nhánh của đội: theo trưởng nhóm, chưa có thì theo thành viên (xem SalesTeam::branchId)
                                $teamBranch = $team->leader?->branch ?? $members->first()?->branch;
                            @endphp
                            <tr wire:key="team-{{ $team->team_id }}">
                                <td data-label="{{ __('employees.teams.team') }}">
                                    <span class="em-person">
                                        <span class="em-team-icon" aria-hidden="true"><x-icon name="users" /></span>
                                        <span>
                                            <span class="em-person__name">{{ $team->team_name }}</span>
                                            <span class="em-person__id">{{ $team->team_id }}</span>
                                        </span>
                                    </span>
                                </td>
                                <td data-label="{{ __('employees.team_leader') }}">
                                    @if ($team->leader)
                                        <a href="{{ route($this->routePrefix.'.employees.show', $team->leader) }}" class="em-person">
                                            <span class="odin-avatar em-avatar" aria-hidden="true">{{ $team->leader->initial() }}</span>
                                            <span>
                                                <span class="em-person__name">{{ $team->leader->full_name }}</span>
                                                <span class="em-person__id">{{ $team->leader->employee_id }}</span>
                                            </span>
                                        </a>
                                    @elseif ($this->canManage)
                                        <button type="button" class="em-assign" wire:click="openLeader('{{ $team->team_id }}')">
                                            <x-icon name="user-check" />{{ __('employees.teams.form.assign_leader') }}
                                        </button>
                                    @else
                                        <span class="em-muted">{{ __('employees.teams.no_leader') }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ __('employees.branch') }}">
                                    {{ $teamBranch?->branch_name ?? '—' }}
                                </td>
                                <td data-label="{{ __('employees.established_date') }}">
                                    {{ $team->established_date?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td data-label="{{ __('employees.members') }}">
                                    <div class="em-stack">
                                        @foreach ($members->take($maxAvatars) as $member)
                                            <a href="{{ route($this->routePrefix.'.employees.show', $member) }}"
                                                class="odin-avatar em-avatar" title="{{ $member->full_name }}">
                                                {{ $member->initial() }}
                                                <span class="visually-hidden">{{ $member->full_name }}</span>
                                            </a>
                                        @endforeach
                                        @if ($members->count() > $maxAvatars)
                                            <span class="odin-avatar em-avatar em-avatar--more">+{{ $members->count() - $maxAvatars }}</span>
                                        @endif
                                        <span class="em-stack__count">
                                            {{ __('employees.teams.member_count', ['count' => $team->members_count]) }}
                                        </span>
                                    </div>
                                </td>
                                <td data-label="{{ __('employees.actions') }}" class="text-lg-end">
                                    <div class="em-row-actions">
                                        <a href="{{ $membersUrl }}" class="vc-icon-btn" title="{{ __('employees.teams.view_members') }}"
                                            aria-label="{{ __('employees.teams.view_members') }} {{ $team->team_name }}">
                                            <x-icon name="eye" />
                                        </a>
                                        {{-- Sale Admin: Chỉnh sửa đội, Gắn / đổi trưởng nhóm --}}
                                        @if ($this->canManage)
                                            <button type="button" class="vc-icon-btn" wire:click="openEdit('{{ $team->team_id }}')"
                                                title="{{ __('employees.teams.form.edit') }}">
                                                <x-icon name="edit" />
                                                <span class="visually-hidden">{{ __('employees.teams.form.edit') }} {{ $team->team_name }}</span>
                                            </button>
                                            <button type="button" class="vc-icon-btn" wire:click="openLeader('{{ $team->team_id }}')"
                                                title="{{ $team->leader ? __('employees.teams.form.change_leader') : __('employees.teams.form.assign_leader') }}">
                                                <x-icon name="user-check" />
                                                <span class="visually-hidden">{{ __('employees.teams.form.assign_leader') }} {{ $team->team_name }}</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($teams->hasPages())
                <div class="vc-pagination">{{ $teams->links() }}</div>
            @endif
        @endif
    </section>

    {{-- ── Popup Tạo đội / Chỉnh sửa đội / Gắn trưởng nhóm (chỉ Sale Admin) ── --}}
    @if (in_array($modal, ['create', 'edit', 'leader'], true) && $this->canManage)
        @php
            $editing = $this->editingTeam;
            $leaders = $this->leaderOptions;
            $currentLeader = $editing?->leader;
            $anchorBranch = $this->anchorBranch;
            [$titleKey, $subKey, $modalIcon] = match ($modal) {
                'create' => ['create', 'create_sub', 'plus'],
                'edit'   => ['edit', 'edit_sub', 'edit'],
                default  => [$currentLeader ? 'change_leader' : 'assign_leader', 'leader_sub', 'user-check'],
            };
        @endphp
        <div class="ld-modal" wire:key="modal-team-{{ $modal }}" x-data x-on:keydown.escape.window="$wire.closeModal()">
            <div class="ld-modal__backdrop" wire:click="closeModal"></div>
            <form class="ld-modal__dialog em-team-modal" role="dialog" aria-modal="true" aria-labelledby="em-team-title"
                wire:submit="save">
                <header class="ld-modal__head">
                    <span class="ld-modal__icon"><x-icon :name="$modalIcon" /></span>
                    <div>
                        <h2 class="ld-modal__title" id="em-team-title">
                            {{ __('employees.teams.form.'.$titleKey) }}
                            @if ($editing)
                                <span class="em-muted d-inline">· {{ $editing->team_name }} ({{ $editing->team_id }})</span>
                            @endif
                        </h2>
                        <p class="ld-modal__sub">{{ __('employees.teams.form.'.$subKey) }}</p>
                    </div>
                    <button type="button" class="ld-modal__close" wire:click="closeModal" aria-label="{{ __('employees.teams.form.close') }}">
                        <x-icon name="x" />
                    </button>
                </header>

                <div class="ld-modal__body ld-form">
                    @if ($modal !== 'leader')
                        <label class="vc-field ld-form__wide">
                            <span class="vc-field__label">{{ __('employees.teams.form.team_name') }}<span class="ld-req">*</span></span>
                            <input type="text" wire:model="form.team_name" maxlength="150" autofocus
                                placeholder="{{ __('employees.teams.form.placeholder_name') }}"
                                class="vc-control @error('team_name') is-invalid @enderror">
                            @error('team_name')<span class="ld-error">{{ $message }}</span>@enderror
                        </label>

                        <label class="vc-field">
                            <span class="vc-field__label">{{ __('employees.established_date') }}<span class="ld-req">*</span></span>
                            <input type="date" wire:model="form.established_date" max="{{ today()->toDateString() }}"
                                class="vc-control @error('established_date') is-invalid @enderror">
                            @error('established_date')<span class="ld-error">{{ $message }}</span>@enderror
                        </label>
                    @endif

                    @if ($modal === 'edit')
                        {{-- Trưởng nhóm đổi bằng popup riêng (Gắn trưởng nhóm) --}}
                        <div class="vc-field">
                            <span class="vc-field__label">{{ __('employees.team_leader') }}</span>
                            <span class="em-fixed">{{ $currentLeader?->full_name ?? __('employees.teams.no_leader') }}</span>
                        </div>

                        {{-- Chi nhánh của đội = chi nhánh của trưởng nhóm; chưa có trưởng nhóm thì theo thành viên được chọn --}}
                        <div class="vc-field">
                            <span class="vc-field__label">{{ __('employees.teams.form.team_branch') }}</span>
                            <span class="em-fixed">{{ $anchorBranch?->branch_name ?? __('employees.teams.form.branch_by_members') }}</span>
                        </div>

                        {{-- Thành viên: Salesperson đang làm việc; người đang ở đội khác bị khoá (mỗi người chỉ thuộc 1 đội) --}}
                        @php $memberOptions = $this->memberOptions; @endphp
                        <div class="vc-field ld-form__wide" x-data="{ q: '' }">
                            <span class="vc-field__label em-members-label">
                                {{ __('employees.teams.form.members') }}
                                {{-- Số người đã chọn đếm phía trình duyệt, không cần gửi lên server mỗi lần bấm --}}
                                <span class="em-muted d-inline"
                                    x-text="@js(__('employees.teams.form.selected', ['count' => '__N__'])).replace('__N__', ($wire.form.members || []).length)"></span>
                            </span>

                            @if ($memberOptions->isEmpty())
                                <p class="ld-form__hint mb-0">{{ __('employees.teams.form.no_salespeople') }}</p>
                            @else
                                <input type="search" x-model="q" class="vc-control em-pick__search"
                                    placeholder="{{ __('employees.teams.form.member_search') }}"
                                    aria-label="{{ __('employees.teams.form.member_search') }}">

                                <ul class="em-pick {{ $errors->has('members') || $errors->has('members.*') ? 'is-invalid' : '' }}" role="group"
                                    aria-label="{{ __('employees.teams.form.members') }}">
                                    @foreach ($memberOptions as $option)
                                        @php
                                            // Khoá: đang ở đội khác (mỗi người chỉ thuộc 1 đội) hoặc khác chi nhánh với đội
                                            $elsewhere = $option->team_id !== null && $option->team_id !== $editing?->team_id;
                                            $locked = ! $this->canPickMember($option);
                                            $haystack = mb_strtolower($option->full_name.' '.$option->employee_id.' '.$option->branch?->branch_name);
                                        @endphp
                                        <li wire:key="pick-{{ $option->employee_id }}" x-show="@js($haystack).includes(q.toLowerCase())">
                                            <label class="em-pick__item {{ $locked ? 'is-disabled' : '' }}">
                                                <input type="checkbox" class="form-check-input" wire:model="form.members"
                                                    value="{{ $option->employee_id }}" @disabled($locked)>
                                                <span class="odin-avatar em-avatar" aria-hidden="true">{{ $option->initial() }}</span>
                                                <span class="em-pick__text">
                                                    <span class="em-person__name">{{ $option->full_name }}</span>
                                                    <span class="em-person__id">
                                                        {{ $option->employee_id }} · {{ $option->branch?->branch_name ?? '—' }}
                                                        @if ($elsewhere)
                                                            · {{ __('employees.teams.form.in_team', ['team' => $option->team->team_name]) }}
                                                        @elseif ($option->team_id === null)
                                                            · {{ __('employees.no_team') }}
                                                        @endif
                                                    </span>
                                                </span>
                                            </label>
                                        </li>
                                    @endforeach
                                </ul>
                                @error('members')<span class="ld-error">{{ $message }}</span>@enderror
                                @error('members.*')<span class="ld-error">{{ $message }}</span>@enderror
                                <p class="ld-form__hint mb-0">{{ __('employees.teams.form.members_hint') }}</p>
                            @endif
                        </div>
                    @else
                        @if ($modal === 'leader')
                            {{-- Đội đã có thành viên: trưởng nhóm phải cùng chi nhánh với họ --}}
                            <div class="vc-field ld-form__wide">
                                <span class="vc-field__label">{{ __('employees.teams.form.team_branch') }}</span>
                                <span class="em-fixed">{{ $anchorBranch?->branch_name ?? __('employees.teams.form.branch_by_leader') }}</span>
                            </div>
                        @endif

                        <label class="vc-field {{ $modal === 'leader' ? 'ld-form__wide' : '' }}">
                            <span class="vc-field__label">
                                {{ __('employees.team_leader') }}@if ($modal === 'leader')<span class="ld-req">*</span>@endif
                            </span>
                            <select wire:model="form.team_leader_id" class="vc-control @error('team_leader_id') is-invalid @enderror"
                                @if ($modal === 'leader') autofocus @endif>
                                <option value="">
                                    {{ $modal === 'create' ? __('employees.teams.form.no_leader_yet') : __('employees.teams.form.choose_leader') }}
                                </option>
                                @foreach ($leaders as $option)
                                    <option value="{{ $option->employee_id }}">
                                        {{ $option->full_name }} ({{ $option->employee_id }}) · {{ $option->branch?->branch_name ?? '—' }}
                                        @if ($option->employee_id === $currentLeader?->employee_id)
                                            - {{ __('employees.teams.form.current') }}
                                        @elseif ($option->team)
                                            - {{ __('employees.teams.form.in_team', ['team' => $option->team->team_name]) }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('team_leader_id')<span class="ld-error">{{ $message }}</span>@enderror
                        </label>

                        <p class="ld-form__hint ld-form__wide">
                            @if ($leaders->isEmpty() || ($leaders->count() === 1 && $currentLeader))
                                {{ $anchorBranch
                                    ? __('employees.teams.form.no_candidates_branch', ['branch' => $anchorBranch->branch_name])
                                    : __('employees.teams.form.no_candidates') }}
                            @else
                                {{ $modal === 'create' ? __('employees.teams.form.create_branch_hint') : __('employees.teams.form.leader_hint') }}
                            @endif
                        </p>
                    @endif

                    @if ($modal !== 'leader')
                        <p class="ld-form__hint ld-form__wide">{{ __('employees.teams.form.required_hint') }}</p>
                    @endif
                </div>

                <footer class="ld-modal__foot">
                    <button type="button" class="vc-btn vc-btn--ghost" wire:click="closeModal">{{ __('employees.teams.form.cancel') }}</button>
                    <button type="submit" class="vc-btn vc-btn--primary" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save"><x-icon name="check" /></span>
                        <span class="spinner-border spinner-border-sm" wire:loading wire:target="save" aria-hidden="true"></span>
                        {{ __('employees.teams.form.save') }}
                    </button>
                </footer>
            </form>
        </div>
    @endif
</div>
