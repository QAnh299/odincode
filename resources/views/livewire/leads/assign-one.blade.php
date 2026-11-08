@php
    $lead = $this->lead;
    $kind = $this->forTeams ? 'team' : 'person';
    $previous = $this->previous;
    $initial = fn (string $name) => mb_strtoupper(mb_substr(\Illuminate\Support\Str::afterLast(trim($name), ' '), 0, 1));
@endphp

<div>
    @if ($open && $lead)
        <div class="ld-modal" wire:key="modal-assign-one" x-data x-on:keydown.escape.window="$wire.close()">
            <div class="ld-modal__backdrop" wire:click="close"></div>
            <form class="ld-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="ld-assign-title" wire:submit="submit">
                <header class="ld-modal__head">
                    <span class="ld-modal__icon"><x-icon name="share" /></span>
                    <div>
                        <h2 class="ld-modal__title" id="ld-assign-title">{{ __('leads.assign_one.title_'.$kind) }}</h2>
                        <p class="ld-modal__sub">{{ __('leads.assign_one.sub_'.$kind) }}</p>
                    </div>
                    <button type="button" class="ld-modal__close" wire:click="close" aria-label="{{ __('leads.close') }}">
                        <x-icon name="x" />
                    </button>
                </header>

                <div class="ld-modal__body ld-pick">
                    {{-- Lead được phân --}}
                    <div class="ld-pick__lead">
                        <span class="ld-pick__avatar">{{ $initial($lead->full_name) }}</span>
                        <div class="ld-pick__lead-info">
                            <strong>{{ $lead->full_name }}</strong>
                            <span>
                                <span class="vc-mono">{{ $lead->lead_id }}</span>
                                · {{ $lead->branch?->branch_name ?? '-' }}
                            </span>
                        </div>
                    </div>

                    @if ($previous && $previous['reason'] === 'returning')
                        <p class="ld-pick__hint">
                            <x-icon name="user-check" />
                            <span>{!! __('leads.assign_one.previous', ['name' => '<strong>'.e($previous['name']).'</strong>']) !!}</span>
                        </p>
                    @endif

                    {{-- Người thân dùng chung SĐT / email: chọn để phân chung cùng đội / người với Lead này --}}
                    @if ($this->family->isNotEmpty())
                        @php
                            $selectable = $this->family->where('assignable', true);
                            $picked = $selectable->whereIn('lead_id', $withFamily)->count();
                        @endphp
                        <section class="ld-fam">
                            <header class="ld-fam__head">
                                <span class="ld-fam__icon"><x-icon name="users" /></span>
                                <div class="ld-fam__heading">
                                    <p class="ld-fam__title">{{ __('leads.assign_one.family_title') }}</p>
                                    <p class="ld-fam__sub">
                                        @if ($previous && $previous['reason'] === 'family')
                                            {!! __('leads.assign_one.family_tip_owner', ['name' => '<strong>'.e($previous['name']).'</strong>']) !!}
                                        @elseif ($selectable->isNotEmpty())
                                            {{ __('leads.assign_one.family_tip_together', ['id' => $lead->lead_id]) }}
                                        @else
                                            {{ __('leads.assign_one.family_tip_'.$kind) }}
                                        @endif
                                    </p>
                                </div>
                                @if ($selectable->isNotEmpty())
                                    <span class="ld-fam__count">{{ __('leads.assign_one.family_picked', ['count' => $picked, 'total' => $selectable->count()]) }}</span>
                                @endif
                            </header>

                            <ul class="ld-fam__list" role="list">
                                @foreach ($this->family as $relative)
                                    @php
                                        $selectable = $relative['assignable'];
                                        $on = $selectable && in_array($relative['lead_id'], $withFamily, true);
                                        // Chọn được → cả dòng là nhãn của ô tick; không chọn được → chỉ hiển thị
                                        $tag = $selectable ? 'label' : 'div';
                                    @endphp
                                    <li wire:key="family-{{ $relative['lead_id'] }}">
                                        <{{ $tag }} class="ld-fam__row {{ $selectable ? 'is-selectable' : '' }} {{ $on ? 'is-on' : '' }}">
                                            @if ($selectable)
                                                <input type="checkbox" wire:model.live="withFamily" value="{{ $relative['lead_id'] }}">
                                            @endif
                                            <span class="ld-fam__check {{ $selectable ? '' : 'is-disabled' }}" aria-hidden="true"><x-icon name="check" /></span>
                                            <span class="ld-fam__avatar">{{ $initial($relative['name']) }}</span>
                                            <span class="ld-fam__text">
                                                <span class="ld-fam__name">{{ $relative['name'] }}</span>
                                                <span class="ld-fam__code">{{ $relative['lead_id'] }}</span>
                                            </span>
                                            <span class="ld-fam__state ld-fam__state--{{ $relative['tone'] }}">
                                                <span class="ld-fam__dot"></span>{{ $relative['state'] }}
                                            </span>
                                        </{{ $tag }}>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif


                    {{-- Đội / Salesperson nhận Lead --}}
                    <fieldset class="ld-pick__group">
                        <legend class="vc-field__label">{{ __('leads.assign_one.choose_label_'.$kind) }}<span class="ld-req">*</span></legend>

                        @forelse ($this->targets as $row)
                            @php $isPrevious = $previous && $previous['owner'] === $row['id']; @endphp
                            <label class="ld-pick__option {{ $target === $row['id'] ? 'is-checked' : '' }}" wire:key="pick-{{ $row['id'] }}">
                                <input type="radio" wire:model.live="target" value="{{ $row['id'] }}">
                                <span class="ld-pick__option-avatar">
                                    @if ($this->forTeams)<x-icon name="users" />@else{{ $initial($row['name']) }}@endif
                                </span>
                                <span class="ld-pick__option-text">
                                    <span class="ld-pick__option-name">
                                        {{ $row['name'] }}
                                        @if ($isPrevious)
                                            <span class="ld-pick__tag">{{ __('leads.assign_one.tag_'.$previous['reason']) }}</span>
                                        @endif
                                    </span>
                                    <span class="ld-pick__option-sub">{{ $row['sub'] }}</span>
                                </span>
                                @if ($row['load'] !== null)
                                    <span class="ld-pick__load {{ $row['full'] ? 'is-full' : '' }}"
                                        title="{{ __('leads.distribute.col_load', ['max' => $max]) }}">
                                        {{ $row['load'] }}<em>/{{ $max }}</em>
                                    </span>
                                @endif
                                <span class="ld-pick__radio" aria-hidden="true"></span>
                            </label>
                        @empty
                            <p class="ld-form__hint">{{ __('leads.distribute.no_target_'.$kind) }}</p>
                        @endforelse
                    </fieldset>

                    @php $chosen = $this->targets->firstWhere('id', $target); @endphp
                    @if ($chosen && $chosen['full'])
                        <div class="ld-notice ld-notice--warning ld-notice--inline">
                            <x-icon name="alert" />
                            <span class="ld-notice__text">{{ __('leads.assign_one.over_limit', ['name' => $chosen['name'], 'max' => $max]) }}</span>
                        </div>
                    @endif

                    @error('target')
                        <div class="ld-notice ld-notice--danger ld-notice--inline"><x-icon name="alert" /><span class="ld-notice__text">{{ $message }}</span></div>
                    @enderror
                </div>

                <footer class="ld-modal__foot">
                    <button type="button" class="vc-btn vc-btn--ghost" wire:click="close">{{ __('leads.cancel') }}</button>
                    <button type="submit" class="vc-btn vc-btn--primary" wire:loading.attr="disabled" wire:target="submit" @disabled(! $target)>
                        <span wire:loading.remove wire:target="submit"><x-icon name="check" /></span>
                        <span class="spinner-border spinner-border-sm" wire:loading wire:target="submit" aria-hidden="true"></span>
                        @php $together = $this->family->where('assignable', true)->whereIn('lead_id', $withFamily)->count(); @endphp
                        {{ $together ? __('leads.assign_one.submit_many', ['count' => $together + 1]) : __('leads.distribute.submit') }}
                    </button>
                </footer>
            </form>
        </div>
    @endif
</div>
