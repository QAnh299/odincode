@php
    $money   = fn ($value) => number_format((float) $value, 0, ',', '.').' ₫';
    $C       = fn ($key, $replace = []) => __('opportunities.create.'.$key, $replace);
    $sel     = $this->selected;
    $check   = $sel ? $sel['check'] : null;
    $lead    = $sel ? $sel['lead'] : null;
    $profile = $sel ? $sel['profile'] : null;
    $blocked = $check && $check['state'] === 'blocked';
    // Chữ viết tắt trên avatar: chữ đầu của từ đầu và từ cuối trong tên
    $words    = $lead ? preg_split('/\s+/u', trim($lead->full_name), -1, PREG_SPLIT_NO_EMPTY) : [];
    $initials = mb_strtoupper(mb_substr($words[0] ?? '', 0, 1).(count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));
@endphp

<div class="vc ok-create">
    @assets
        @vite(['resources/css/voucher.css', 'resources/css/opportunity.css'])
    @endassets

    {{-- ── Breadcrumb ────────────────────────────────────────────── --}}
    <nav class="vc-crumb" aria-label="breadcrumb">
        <a href="{{ $this->backUrl() }}" class="odin-back" wire:navigate>
            <x-icon name="arrow-left" />{{ $C('back') }}
        </a>
        <span class="vc-crumb__path">
            @if ($fromStudent)
                <a href="{{ route('salesperson.students') }}" wire:navigate>{{ __('menu.students') }}</a>
                <x-icon name="chevron" />
                <a href="{{ $this->backUrl() }}" wire:navigate>{{ $fromStudent }}</a>
            @else
                <a href="{{ route('salesperson.opportunities') }}" wire:navigate>{{ __('opportunities.title') }}</a>
            @endif
            <x-icon name="chevron" />
            <span aria-current="page">{{ $C('title') }}</span>
        </span>
    </nav>

    {{-- ── Lỗi chung (chặn khi confirm) ──────────────────────────── --}}
    @error('general')
        <div class="ok-create__alert ok-create__alert--danger" role="alert">
            <x-icon name="alert" />
            <span>{{ $message }}</span>
        </div>
    @enderror

    @if (! $sel)
        {{-- ══ Chưa chọn khách: chỉ hiện mục Tìm kiếm ═══════════════ --}}
        <section class="vc-card ok-create__search-panel" wire:key="search-panel">
            <h2 class="ok-create__card-title">{{ $C('search_title') }}</h2>
            <div class="vc-search">
                <x-icon name="search" />
                <input type="search"
                    wire:model.live.debounce.350ms="search"
                    placeholder="{{ $C('search_placeholder') }}"
                    aria-label="{{ $C('search_label') }}"
                    autofocus>
                <span class="vc-search__spin" wire:loading.delay wire:target="search">
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                </span>
            </div>

            @php $results = $this->results; @endphp

            @if (trim($search) === '')
                <div class="ok-create__idle">
                    <x-icon name="search" />
                    <p class="ok-create__idle-hint">{{ $C('search_placeholder') }}</p>
                </div>
            @elseif ($results->isEmpty())
                <div class="ok-create__idle">
                    <p class="ok-create__empty">{{ $C('no_results') }}</p>
                    <p class="ok-create__empty-hint">{{ $C('no_results_hint') }}</p>
                </div>
            @else
                <p class="ok-create__count">{{ $C('result_count', ['count' => $results->count()]) }}</p>

                <div class="vc-table-wrap">
                    <table class="vc-table">
                        <thead>
                            <tr>
                                <th>{{ $C('col_customer') }}</th>
                                <th>{{ $C('col_phone') }}</th>
                                <th>{{ $C('col_status') }}</th>
                                <th>{{ $C('col_action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results as $row)
                                @php
                                    $rowState = $row['check']['state'];      // ok | warning | blocked
                                    $b        = $row['check']['blocks'][0] ?? null;
                                @endphp
                                <tr wire:key="row-{{ $row['lead_id'] }}">
                                    <td><span class="ok-create__name">{{ $row['full_name'] }}</span></td>
                                    <td class="text-nowrap">{{ $row['phone'] ?? '-' }}</td>
                                    <td>
                                        @if ($rowState === 'blocked')
                                            <span class="ok-create__badge ok-create__badge--blocked">
                                                @if ($b && $b['type'] === 'own_in_progress')
                                                    {{ $C('status_own_in_progress') }}
                                                @elseif ($b && $b['type'] === 'other_in_progress')
                                                    {{ $C('status_other_in_progress', ['name' => $b['owner_name']]) }}
                                                @else
                                                    {{ $C('status_in_queue') }}
                                                @endif
                                            </span>
                                        @elseif ($rowState === 'warning')
                                            <span class="ok-create__badge ok-create__badge--warning">{{ $C('status_warning') }}</span>
                                        @else
                                            <span class="ok-create__badge ok-create__badge--ok">{{ $C('status_ok') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="vc-btn vc-btn--ghost ok-create__btn-sm"
                                            wire:click="selectLead('{{ $row['lead_id'] }}')">
                                            {{ $C('btn_select') }}
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @else
        {{-- ══ Đã chọn khách: thông tin khách (trái) + cơ hội sẽ tạo (phải) ══ --}}
        <div class="ok-create__layout" wire:key="selected-{{ $lead->lead_id }}">
            {{-- ── Cột trái: khách đang chọn + thông tin liên hệ (sửa được) ── --}}
            <div class="ok-create__col">
                <section class="ok-create__box ok-create__customer">
                    <span class="ok-create__avatar" aria-hidden="true">{{ $initials }}</span>
                    <div class="ok-create__customer-main">
                        <h2 class="ok-create__customer-name">{{ $lead->full_name }}</h2>
                        @if ($profile['student'])
                            <div class="ok-create__tags">
                                <span class="ok-create__tag">{{ $profile['student']->student_id }}</span>
                                <span class="ok-create__tag ok-create__tag--{{ strtolower($profile['student']->status) }}">
                                    {{ __('opportunities.student_status.'.$profile['student']->status) }}
                                </span>
                            </div>
                        @endif
                    </div>
                    {{-- Vào từ trang học viên thì khách cố định, không đổi được --}}
                    @unless ($lockedLead)
                        <button type="button" class="vc-btn vc-btn--ghost ok-create__change" wire:click="clearSelection">
                            <x-icon name="swap" />{{ $C('btn_change') }}
                        </button>
                    @endunless
                </section>

                <section class="ok-create__box ok-create__contact-box">
                    <h3 class="ok-create__section-title">{{ $C('contact_title') }}</h3>

                    {{-- Form sửa liên hệ (ẩn khi khách bị chặn) --}}
                    @unless ($blocked)
                        <div class="ok-create__contact">
                            <label class="vc-field">
                                <span class="vc-field__label">{{ $C('contact_phone') }}</span>
                                <span class="ok-create__input">
                                    <x-icon name="phone" />
                                    <input type="text" wire:model="contactPhone" maxlength="10" inputmode="numeric"
                                        class="vc-control @error('contactPhone') is-invalid @enderror">
                                </span>
                                @error('contactPhone') <span class="ld-error">{{ $message }}</span> @enderror
                            </label>

                            <label class="vc-field">
                                <span class="vc-field__label">{{ $C('contact_email') }}</span>
                                <span class="ok-create__input">
                                    <x-icon name="mail" />
                                    <input type="email" wire:model="contactEmail" maxlength="254"
                                        class="vc-control @error('contactEmail') is-invalid @enderror">
                                </span>
                                @error('contactEmail') <span class="ld-error">{{ $message }}</span> @enderror
                            </label>

                            <label class="vc-field ok-create__contact-wide">
                                <span class="vc-field__label">
                                    {{ $C('contact_source_url') }} <span class="ok-create__label-hint">{{ $C('source_url_hint') }}</span>
                                </span>
                                <span class="ok-create__input">
                                    <x-icon name="link" />
                                    <input type="url" wire:model="contactSourceUrl" maxlength="2000"
                                        class="vc-control @error('contactSourceUrl') is-invalid @enderror">
                                </span>
                                @error('contactSourceUrl') <span class="ld-error">{{ $message }}</span> @enderror
                            </label>
                        </div>
                    @endunless

                    {{-- Lưu ý: chặn / cảnh báo --}}
                    @if ($check['blocks'] || $check['warnings'])
                        <div class="ok-create__notes {{ $blocked ? 'is-blocked' : '' }}">
                            <x-icon name="info" />
                            <div>
                                <p class="ok-create__notes-title">{{ $C('notes_title') }}</p>
                                @foreach ($check['blocks'] as $block)
                                    <p class="is-blocked" role="alert">
                                        @if ($block['type'] === 'own_in_progress')
                                            {{ $C('blocked_own_in_progress', ['opp' => $block['opportunity_id']]) }}
                                            <a href="{{ route('salesperson.opportunities.show', $block['opportunity_id']) }}" class="ok-create__link" wire:navigate>
                                                {{ $C('view_active_opp') }}
                                            </a>
                                        @elseif ($block['type'] === 'other_in_progress')
                                            {{ $C('blocked_other_in_progress', [
                                                'name'  => $block['owner_name'],
                                                'phone' => $block['owner_phone'] ?? $C('no_phone'),
                                            ]) }}
                                        @else
                                            {{ $C('blocked_in_queue') }}
                                        @endif
                                    </p>
                                @endforeach

                                @foreach ($check['warnings'] as $warning)
                                    @if ($warning['type'] === 'studying')
                                        <p>{{ $C('warning_studying_line', ['courses' => implode(', ', $warning['courses'])]) }}</p>
                                    @elseif ($warning['type'] === 'debt')
                                        @foreach ($warning['items'] as $item)
                                            <p>{{ $C('warning_debt_line', ['amount' => $money($item['remaining']), 'course' => $item['course']]) }}</p>
                                        @endforeach
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>
            </div>

            {{-- ── Cột phải: cơ hội sẽ được tạo ────────────────────── --}}
            <aside class="ok-create__box ok-create__summary">
                <h3 class="ok-create__section-title ok-create__summary-head">{{ $C('summary_title') }}</h3>

                <dl class="ok-create__summary-list">
                    <div>
                        <dt>{{ $C('summary_status') }}</dt>
                        <dd><span class="ok-create__pill">{{ __('opportunities.status_name.'.\App\Models\Opportunity::STATUS_IN_PROGRESS) }}</span></dd>
                    </div>
                    <div>
                        <dt>{{ $C('summary_stage') }}</dt>
                        <dd>{{ $this->firstStageName }}</dd>
                    </div>
                    <div>
                        <dt>{{ $C('summary_owner') }}</dt>
                        <dd>{{ $C('summary_owner_you') }}</dd>
                    </div>
                    <div>
                        <dt>{{ $C('summary_date') }}</dt>
                        <dd>{{ now()->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt>{{ $C('summary_link') }}</dt>
                        <dd>{{ $lead->full_name }}{{ $profile['student'] ? ' · '.$profile['student']->student_id : '' }}</dd>
                    </div>
                </dl>

                <footer class="ok-create__summary-foot">
                    @if ($blocked)
                        <p class="ok-create__summary-blocked">{{ $C('summary_blocked') }}</p>
                    @endif

                    <button type="button"
                        class="vc-btn vc-btn--primary ok-create__submit"
                        wire:click="confirm"
                        wire:loading.attr="disabled"
                        wire:target="confirm"
                        @disabled($blocked)>
                        <span wire:loading.remove wire:target="confirm"><x-icon name="plus" />{{ $C('btn_new') }}</span>
                        <span wire:loading wire:target="confirm">{{ $C('btn_confirming') }}</span>
                    </button>
                </footer>
            </aside>
        </div>
    @endif
</div>
