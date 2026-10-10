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

    {{-- ── Tiêu đề + nút thao tác (Phân chia chưa có chức năng) ─────── --}}
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
                {{-- Thêm / Import Lead: chỉ Sale Admin --}}
                @if ($this->canCreate)
                    <button type="button" class="vc-btn vc-btn--ghost" wire:click="openCreate">
                        <x-icon name="plus" />{{ __('leads.add') }}
                    </button>
                    <button type="button" class="vc-btn vc-btn--ghost" wire:click="openImport">
                        <x-icon name="upload" />{{ __('leads.import') }}
                    </button>
                @endif
                <button type="button" class="vc-btn vc-btn--primary">
                    <x-icon name="share" />
                    {{ $this->routePrefix === Role::SALE_ADMIN ? __('leads.distribute_team') : __('leads.distribute_salesperson') }}
                </button>
            </div>
        @endif
    </header>

    {{-- ── Thông báo sau khi thêm / import ──────────────────────────── --}}
    @if ($notice)
        <div class="ld-notice ld-notice--{{ $notice['type'] }}" role="status" wire:key="notice-{{ md5(json_encode($notice)) }}">
            <x-icon :name="$notice['type'] === 'success' ? 'check-circle' : 'alert'" />
            <span class="ld-notice__text">{{ $notice['text'] }}</span>
            <button type="button" class="ld-notice__close" wire:click="dismissNotice" aria-label="{{ __('leads.close') }}">
                <x-icon name="x" />
            </button>
        </div>
    @endif

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

    {{-- ── Popup Thêm Lead ──────────────────────────────────────────── --}}
    @if ($modal === 'create' && $this->canCreate)
        <div class="ld-modal" wire:key="modal-create" x-data x-on:keydown.escape.window="$wire.closeModal()">
            <div class="ld-modal__backdrop" wire:click="closeModal"></div>
            <form class="ld-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="ld-create-title"
                wire:submit="saveLead">
                <header class="ld-modal__head">
                    <span class="ld-modal__icon"><x-icon name="user" /></span>
                    <div>
                        <h2 class="ld-modal__title" id="ld-create-title">{{ __('leads.form.title') }}</h2>
                        <p class="ld-modal__sub">{{ __('leads.form.sub') }}</p>
                    </div>
                    <button type="button" class="ld-modal__close" wire:click="closeModal" aria-label="{{ __('leads.close') }}">
                        <x-icon name="x" />
                    </button>
                </header>

                <div class="ld-modal__body ld-form">
                    @php
                        // [trường, kiểu input, placeholder, bắt buộc]
                        $inputs = [
                            ['full_name', 'text', 'placeholder_name', true],
                            ['phone', 'tel', 'placeholder_phone', true],
                            ['email', 'email', 'placeholder_email', false],
                            ['source_name', 'text', 'placeholder_source', true],
                            ['source_url', 'url', 'placeholder_url', false],
                        ];
                    @endphp

                    @foreach ($inputs as [$field, $type, $placeholder, $required])
                        <label class="vc-field {{ $field === 'source_url' ? 'ld-form__wide' : '' }}">
                            <span class="vc-field__label">
                                {{ __('leads.field.'.$field) }}@if ($required)<span class="ld-req">*</span>@endif
                            </span>
                            <input type="{{ $type }}" wire:model="form.{{ $field }}" placeholder="{{ __('leads.form.'.$placeholder) }}"
                                class="vc-control @error($field) is-invalid @enderror"
                                @if ($field === 'full_name') autofocus @endif>
                            @error($field)<span class="ld-error">{{ $message }}</span>@enderror
                        </label>
                    @endforeach

                    <label class="vc-field">
                        <span class="vc-field__label">{{ __('leads.field.contact_method') }}<span class="ld-req">*</span></span>
                        <select wire:model="form.contact_method" class="vc-control @error('contact_method') is-invalid @enderror">
                            <option value="">{{ __('leads.form.choose_method') }}</option>
                            @foreach (Lead::CONTACT_METHODS as $option)
                                <option value="{{ $option }}">{{ __('leads.method.'.$option) }}</option>
                            @endforeach
                        </select>
                        @error('contact_method')<span class="ld-error">{{ $message }}</span>@enderror
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

                    <p class="ld-form__hint ld-form__wide">{{ __('leads.form.required_hint') }}</p>
                </div>

                <footer class="ld-modal__foot">
                    <button type="button" class="vc-btn vc-btn--ghost" wire:click="closeModal">{{ __('leads.cancel') }}</button>
                    <button type="submit" class="vc-btn vc-btn--primary" wire:loading.attr="disabled" wire:target="saveLead">
                        <span wire:loading.remove wire:target="saveLead"><x-icon name="check" /></span>
                        <span class="spinner-border spinner-border-sm" wire:loading wire:target="saveLead" aria-hidden="true"></span>
                        {{ __('leads.form.save') }}
                    </button>
                </footer>
            </form>
        </div>
    @endif

    {{-- ── Popup Import Excel ───────────────────────────────────────── --}}
    @if ($modal === 'import' && $this->canCreate)
        <div class="ld-modal" wire:key="modal-import" x-data x-on:keydown.escape.window="$wire.closeModal()">
            <div class="ld-modal__backdrop" wire:click="closeModal"></div>
            <div class="ld-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="ld-import-title">
                <header class="ld-modal__head">
                    <span class="ld-modal__icon"><x-icon name="upload" /></span>
                    <div>
                        <h2 class="ld-modal__title" id="ld-import-title">{{ __('leads.excel.title') }}</h2>
                        <p class="ld-modal__sub">{{ __('leads.excel.sub') }}</p>
                    </div>
                    <button type="button" class="ld-modal__close" wire:click="closeModal" aria-label="{{ __('leads.close') }}">
                        <x-icon name="x" />
                    </button>
                </header>

                <div class="ld-modal__body">
                    @if ($importResult)
                        {{-- Kết quả import --}}
                        @php
                            $tone = $importResult['failed'] === 0 ? 'success' : ($importResult['success'] === 0 ? 'danger' : 'warning');
                        @endphp
                        <div class="ld-result ld-result--{{ $tone }}">
                            <p class="ld-result__title">
                                <x-icon :name="$tone === 'success' ? 'check-circle' : 'alert'" />{{ $notice['text'] ?? __('leads.excel.result_title') }}
                            </p>
                            <div class="ld-result__stats">
                                <div><span>{{ __('leads.excel.result_total') }}</span><strong>{{ $importResult['total'] }}</strong></div>
                                <div class="is-ok"><span>{{ __('leads.excel.result_success') }}</span><strong>{{ $importResult['success'] }}</strong></div>
                                <div class="is-fail"><span>{{ __('leads.excel.result_failed') }}</span><strong>{{ $importResult['failed'] }}</strong></div>
                            </div>
                        </div>
                        @if ($importError)
                            <p class="ld-error">{{ $importError }}</p>
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
                        </ol>
                    @endif
                </div>

                <footer class="ld-modal__foot">
                    @if ($importResult)
                        <button type="button" class="vc-btn vc-btn--ghost" wire:click="openImport">
                            <x-icon name="refresh" />{{ __('leads.excel.import_another') }}
                        </button>
                        <button type="button" class="vc-btn vc-btn--primary" wire:click="downloadResult">
                            <x-icon name="file" />{{ __('leads.excel.download_result') }}
                        </button>
                    @else
                        <button type="button" class="vc-btn vc-btn--ghost" wire:click="closeModal">{{ __('leads.cancel') }}</button>
                        <button type="button" class="vc-btn vc-btn--primary" wire:click="importFile"
                            wire:loading.attr="disabled" wire:target="file,importFile"
                            @disabled(! $file || $errors->has('file'))>
                            <span wire:loading.remove wire:target="importFile"><x-icon name="upload" /></span>
                            <span class="spinner-border spinner-border-sm" wire:loading wire:target="importFile" aria-hidden="true"></span>
                            <span wire:loading.remove wire:target="importFile">{{ __('leads.excel.submit') }}</span>
                            <span wire:loading wire:target="importFile">{{ __('leads.excel.processing') }}</span>
                        </button>
                    @endif
                </footer>
            </div>
        </div>
    @endif
</div>
