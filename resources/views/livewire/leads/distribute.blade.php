@php
    use App\Models\Lead;
    use App\Services\LeadDistributionService;

    $max = LeadDistributionService::MAX_PER_SALESPERSON;
    $top = LeadDistributionService::TOP;
    $kind = $this->forTeams ? 'team' : 'person';
    $isAuto = $step === 'form' && $mode === 'auto';
    $isManual = $step === 'form' && $mode === 'manual';
    $isOverflow = $step === 'overflow';
    $ranking = $this->needsRanking;
    $showList = $mode && ! ($this->forTeams && ! $branch) && $this->targets->isNotEmpty();

    $incoming = $this->incoming;
    $incomingTotal = array_sum($incoming);
    $receivers = count(array_filter($incoming));
    $available = $this->available;

    // Giá trị chỉ số hiệu suất theo tiêu chí đang chọn
    $metric = fn (?array $perf) => match ($criterion) {
        'close_rate' => isset($perf['close_rate']) ? number_format($perf['close_rate'], 1, ',', '.').'%' : '—',
        'students'   => $perf['students'] ?? 0,
        default      => number_format((float) ($perf['revenue'] ?? 0), 0, ',', '.').' ₫',
    };

    // Chữ cái đại diện + màu avatar cố định theo mã
    $initial = fn (string $name) => mb_strtoupper(mb_substr(\Illuminate\Support\Str::afterLast(trim($name), ' '), 0, 1));
    $hues = [152, 200, 262, 24, 330, 45];
    $hue = fn (string $id) => $hues[crc32($id) % count($hues)];
    $fmt = fn ($n) => number_format($n, 0, ',', '.');
@endphp

<div>
    @if ($open)
        <div class="ld-modal" wire:key="modal-distribute" x-data x-on:keydown.escape.window="$wire.close()">
            <div class="ld-modal__backdrop" wire:click="close"></div>
            <form class="ld-modal__dialog ld-modal__dialog--wide ld-dist" role="dialog" aria-modal="true" aria-labelledby="ld-dist-title"
                wire:submit="{{ $isOverflow ? 'confirmOverflow' : 'submit' }}">
                <header class="ld-modal__head ld-dist__head">
                    <span class="ld-modal__icon"><x-icon name="share" /></span>
                    <div>
                        <h2 class="ld-modal__title" id="ld-dist-title">{{ __('leads.distribute.title_'.$kind) }}</h2>
                        @if ($isOverflow)
                            <p class="ld-modal__sub">{{ __('leads.distribute.overflow_sub', ['max' => $max]) }}</p>
                        @elseif (! $this->forTeams && $this->team)
                            <p class="ld-modal__sub">{{ $this->team->team_name }}</p>
                        @endif
                    </div>
                    <button type="button" class="ld-modal__close" wire:click="close" aria-label="{{ __('leads.close') }}">
                        <x-icon name="x" />
                    </button>
                </header>

                <div class="ld-modal__body ld-dist__body">
                    {{-- ── Thiết lập: phương thức + tiêu chí Lead ─────────────── --}}
                    @if (! $isOverflow)
                        <section class="ld-dist__panel">
                            <div class="ld-dist__grid {{ $mode ? '' : 'is-single' }}">
                                <label class="vc-field">
                                    <span class="vc-field__label">{{ __('leads.distribute.mode') }}<span class="ld-req">*</span></span>
                                    <select wire:model.live="mode" class="vc-control @error('mode') is-invalid @enderror">
                                        <option value="">{{ __('leads.distribute.choose_mode') }}</option>
                                        <option value="auto">{{ __('leads.distribute.mode_auto') }}</option>
                                        <option value="manual">{{ __('leads.distribute.mode_manual') }}</option>
                                    </select>
                                    @error('mode')<span class="ld-error">{{ $message }}</span>@enderror
                                </label>

                                @if ($mode)
                                    @if ($this->forTeams)
                                        <label class="vc-field">
                                            <span class="vc-field__label">{{ __('leads.branch') }}<span class="ld-req">*</span></span>
                                            <select wire:model.live="branch" class="vc-control @error('branch') is-invalid @enderror">
                                                <option value="">{{ __('leads.form.choose_branch') }}</option>
                                                @foreach ($this->branches as $option)
                                                    <option value="{{ $option->branch_id }}">{{ $option->branch_name }}</option>
                                                @endforeach
                                            </select>
                                            @error('branch')<span class="ld-error">{{ $message }}</span>@enderror
                                        </label>
                                    @endif

                                    <label class="vc-field">
                                        <span class="vc-field__label">{{ __('leads.field.source_name') }}</span>
                                        <select wire:model.live="source" class="vc-control">
                                            <option value="">{{ __('leads.all') }}</option>
                                            @foreach (Lead::SOURCES as $option)
                                                <option value="{{ $option }}">{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <label class="vc-field">
                                        <span class="vc-field__label">{{ __('leads.field.contact_method') }}</span>
                                        <select wire:model.live="method" class="vc-control">
                                            <option value="">{{ __('leads.all') }}</option>
                                            @foreach (Lead::CONTACT_METHODS as $option)
                                                <option value="{{ $option }}">{{ __('leads.method.'.$option) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endif
                            </div>
                        </section>
                    @endif

                    @if (! $mode)
                        {{-- Chưa chọn phương thức --}}
                        <div class="ld-dist__state">
                            <span class="ld-dist__state-icon"><x-icon name="layers" /></span>
                            <p>{{ __('leads.distribute.pick_mode') }}</p>
                        </div>
                    @else
                        {{-- ── Chỉ số nhanh ─────────────────────────────────────── --}}
                        <div class="ld-dist__stats">
                            <div class="ld-dist__stat">
                                <span class="ld-dist__stat-icon"><x-icon name="inbox" /></span>
                                <span class="ld-dist__stat-text">
                                    <small>{{ __('leads.distribute.available') }}</small>
                                    <strong>{{ $fmt($available) }}</strong>
                                </span>
                            </div>

                            @if ($isOverflow)
                                <div class="ld-dist__stat is-warn">
                                    <span class="ld-dist__stat-icon"><x-icon name="alert" /></span>
                                    <span class="ld-dist__stat-text">
                                        <small>{{ __('leads.distribute.stat_overflow') }}</small>
                                        <strong>{{ $fmt(array_sum(array_map('intval', $overflowAmounts))) }}<em>/{{ $this->overflowRequired }}</em></strong>
                                    </span>
                                </div>
                            @else
                                <div class="ld-dist__stat {{ $isManual && $incomingTotal > $available ? 'is-danger' : 'is-accent' }}">
                                    <span class="ld-dist__stat-icon"><x-icon name="share" /></span>
                                    <span class="ld-dist__stat-text">
                                        <small>{{ __('leads.distribute.will_assign') }}</small>
                                        <strong>{{ $fmt($incomingTotal) }}</strong>
                                    </span>
                                    @if ($available > 0)
                                        <span class="ld-dist__stat-bar"><span style="width: {{ min(100, round($incomingTotal * 100 / $available)) }}%"></span></span>
                                    @endif
                                </div>
                            @endif

                            <div class="ld-dist__stat">
                                <span class="ld-dist__stat-icon"><x-icon name="{{ $this->forTeams ? 'layers' : 'users' }}" /></span>
                                <span class="ld-dist__stat-text">
                                    <small>{{ __('leads.distribute.stat_receivers_'.$kind) }}</small>
                                    <strong>{{ $receivers }}<em>/{{ $this->targets->count() }}</em></strong>
                                </span>
                            </div>
                        </div>

                        @if ($isOverflow)
                            <div class="ld-notice ld-notice--warning ld-notice--inline">
                                <x-icon name="alert" />
                                <span class="ld-notice__text">
                                    {{ __('leads.distribute.overflow_notice', ['count' => $overflow, 'required' => $this->overflowRequired, 'max' => $max]) }}
                                </span>
                            </div>
                        @endif

                        {{-- ── Lead dư → chọn tiêu chí hiệu suất ────────────────── --}}
                        @if ($showList && $ranking)
                            <div class="ld-dist__ranking">
                                <span class="ld-dist__ranking-icon"><x-icon name="trending" /></span>
                                <p class="ld-dist__ranking-text">
                                    @if ($isOverflow)
                                        {{ __('leads.distribute.ranking_overflow', ['month' => $month]) }}
                                    @elseif ($this->leftover['over'] > 0)
                                        {{ __('leads.distribute.ranking_over', ['count' => $this->leftover['over'], 'max' => $max, 'top' => $top]) }}
                                    @else
                                        {{ __('leads.distribute.ranking_remainder', ['count' => $this->leftover['remainder'], 'top' => $top]) }}
                                    @endif
                                </p>
                                <label class="ld-dist__ranking-select">
                                    <span class="visually-hidden">{{ __('leads.distribute.criterion', ['month' => $month]) }}</span>
                                    <select wire:model.live="criterion" class="vc-control" title="{{ __('leads.distribute.criterion', ['month' => $month]) }}">
                                        @foreach (LeadDistributionService::CRITERIA as $option)
                                            <option value="{{ $option }}">{{ __('leads.distribute.criteria.'.$option) }} · {{ $month }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                        @endif

                        {{-- ── Danh sách nhận Lead ──────────────────────────────── --}}
                        @if ($this->forTeams && ! $branch)
                            <div class="ld-dist__state">
                                <span class="ld-dist__state-icon"><x-icon name="filter" /></span>
                                <p>{{ __('leads.distribute.pick_branch') }}</p>
                            </div>
                        @elseif ($this->targets->isEmpty())
                            <div class="ld-dist__state">
                                <span class="ld-dist__state-icon"><x-icon name="users" /></span>
                                <p>{{ __('leads.distribute.no_target_'.$kind) }}</p>
                            </div>
                        @else
                            <div class="ld-dist__list-head">
                                <span>{{ __('leads.distribute.col_'.$kind) }} <em>{{ $this->targets->count() }}</em></span>
                                @if ($isAuto)
                                    @php $allOn = ! array_diff($this->targets->filter(fn ($r) => $r['capacity'] !== 0)->pluck('id')->all(), $selected); @endphp
                                    <button type="button" class="ld-dist__link" wire:click="toggleAll">
                                        <x-icon name="{{ $allOn ? 'x' : 'check' }}" />{{ __('leads.distribute.'.($allOn ? 'deselect_all' : 'select_all')) }}
                                    </button>
                                @elseif ($isManual)
                                    <span class="ld-dist__list-hint">{{ __('leads.distribute.col_amount') }}</span>
                                @else
                                    <span class="ld-dist__list-hint">{{ __('leads.distribute.col_extra') }}</span>
                                @endif
                            </div>

                            <ul class="ld-dist__list" role="list">
                                @foreach ($this->targets as $row)
                                    @php
                                        $id = $row['id'];
                                        $rank = $this->ranks[$id] ?? null;
                                        $isTop = $ranking && $rank !== null && $rank <= $top;
                                        $add = $incoming[$id] ?? 0;
                                        $full = ! $this->forTeams && $row['capacity'] === 0;
                                        $checked = $isAuto && in_array($id, $selected, true);
                                        $loadPct = $this->forTeams ? 0 : min(100, $row['load'] * 100 / $max);
                                        $addPct = $this->forTeams ? 0 : min(100 - $loadPct, $add * 100 / $max);
                                        $after = $this->forTeams ? 0 : $row['load'] + $add;
                                        $tone = $after > $max ? 'danger' : ($after >= $max * 0.8 ? 'warn' : 'ok');
                                    @endphp
                                    <li wire:key="dist-{{ $id }}" class="ld-dist__row {{ $checked || ($add && ! $isAuto) ? 'is-active' : '' }} {{ $full ? 'is-full' : '' }} {{ $isAuto ? 'is-selectable' : '' }}">
                                        @if ($isAuto)
                                            <input type="checkbox" class="ld-dist__check" wire:model.live="selected" value="{{ $id }}" id="dist-{{ $id }}" @disabled($full)>
                                        @endif

                                        <label class="ld-dist__who" @if ($isAuto) for="dist-{{ $id }}" @endif>
                                            <span class="ld-dist__avatar" style="--h: {{ $hue($id) }}">
                                                @if ($this->forTeams)
                                                    <x-icon name="users" />
                                                @else
                                                    {{ $initial($row['name']) }}
                                                @endif
                                                @if ($isTop)
                                                    <span class="ld-dist__medal" title="Top {{ $rank }}">{{ $rank }}</span>
                                                @endif
                                            </span>
                                            <span class="ld-dist__who-text">
                                                <span class="ld-dist__name">{{ $row['name'] }}</span>
                                                <span class="ld-dist__meta">
                                                    {{ $row['sub'] }}
                                                    @if ($ranking)
                                                        <span class="ld-dist__dot"></span>
                                                        <span class="ld-dist__perf">{{ $metric($row['perf']) }}</span>
                                                    @endif
                                                </span>
                                            </span>
                                        </label>

                                        @unless ($this->forTeams)
                                            <div class="ld-dist__load ld-dist__load--{{ $tone }}" title="{{ __('leads.distribute.col_load', ['max' => $max]) }}">
                                                <div class="ld-dist__load-top">
                                                    <span>{{ __('leads.distribute.load') }}</span>
                                                    <strong>
                                                        {{ $row['load'] }}@if ($add)<ins>+{{ $add }}</ins>@endif<em>/{{ $max }}</em>
                                                    </strong>
                                                </div>
                                                <div class="ld-dist__bar">
                                                    <span class="ld-dist__bar-now" style="width: {{ $loadPct }}%"></span>
                                                    <span class="ld-dist__bar-add" style="width: {{ $addPct }}%"></span>
                                                </div>
                                            </div>
                                        @endunless

                                        <div class="ld-dist__action">
                                            @if ($isAuto)
                                                @if ($add)
                                                    <span class="ld-dist__pill">+{{ $add }}</span>
                                                @elseif ($full)
                                                    <span class="ld-dist__pill is-muted">{{ __('leads.distribute.full') }}</span>
                                                @else
                                                    <span class="ld-dist__pill is-empty">0</span>
                                                @endif
                                            @else
                                                @php
                                                    $field = $isOverflow ? 'overflowAmounts' : 'amounts';
                                                    $room = $isOverflow ? $this->overflowRoom[$id] : null;
                                                @endphp
                                                <div class="ld-dist__stepper {{ $isOverflow && $room === 0 ? 'is-disabled' : '' }}">
                                                    <button type="button" wire:click="adjust('{{ $field }}', '{{ $id }}', -1)" aria-label="−1"
                                                        @disabled($isOverflow && $room === 0)>−</button>
                                                    <input type="number" min="0" step="1" inputmode="numeric" placeholder="0"
                                                        @if ($isOverflow) max="{{ $room }}" @endif
                                                        wire:model.live.debounce.300ms="{{ $field }}.{{ $id }}"
                                                        aria-label="{{ $row['name'] }}" @disabled($isOverflow && $room === 0)>
                                                    <button type="button" wire:click="adjust('{{ $field }}', '{{ $id }}', 1)" aria-label="+1"
                                                        @disabled($isOverflow && $room === 0)>+</button>
                                                </div>
                                                @if ($isOverflow)
                                                    <span class="ld-dist__room">{{ __('leads.distribute.room_left', ['count' => $room]) }}</span>
                                                @endif
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>

                            @if ($isAuto && $this->preview['unplaced'] > 0)
                                <p class="ld-form__hint">{{ __('leads.distribute.preview_unplaced', ['count' => $this->preview['unplaced'], 'max' => $max]) }}</p>
                            @endif
                        @endif
                    @endif

                    @error('targets')
                        <div class="ld-notice ld-notice--danger ld-notice--inline"><x-icon name="alert" /><span class="ld-notice__text">{{ $message }}</span></div>
                    @enderror
                    @error('overflow')
                        <div class="ld-notice ld-notice--danger ld-notice--inline"><x-icon name="alert" /><span class="ld-notice__text">{{ $message }}</span></div>
                    @enderror
                </div>

                <footer class="ld-modal__foot ld-dist__foot">
                    <p class="ld-dist__summary">
                        @if ($mode && $incomingTotal > 0)
                            {!! __('leads.distribute.summary_'.$kind, ['count' => '<strong>'.$fmt($incomingTotal).'</strong>', 'receivers' => '<strong>'.$receivers.'</strong>']) !!}
                        @endif
                    </p>
                    @if ($isOverflow)
                        <button type="button" class="vc-btn vc-btn--ghost" wire:click="backToForm">
                            <x-icon name="arrow-left" />{{ __('leads.distribute.back') }}
                        </button>
                    @else
                        <button type="button" class="vc-btn vc-btn--ghost" wire:click="close">{{ __('leads.cancel') }}</button>
                    @endif
                    <button type="submit" class="vc-btn vc-btn--primary" wire:loading.attr="disabled" wire:target="submit,confirmOverflow"
                        @disabled(! $mode)>
                        <span wire:loading.remove wire:target="submit,confirmOverflow"><x-icon name="check" /></span>
                        <span class="spinner-border spinner-border-sm" wire:loading wire:target="submit,confirmOverflow" aria-hidden="true"></span>
                        {{ __('leads.distribute.submit') }}
                    </button>
                </footer>
            </form>
        </div>
    @endif
</div>
