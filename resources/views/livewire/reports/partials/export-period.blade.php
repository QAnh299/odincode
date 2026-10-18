{{-- Popup chọn thời gian xuất báo cáo (App\Livewire\Reports\Concerns\ExportPeriod). Cần lead.css (.ld-modal). --}}
@php
    $P = fn ($key, $replace = []) => __('reports.export_period.'.$key, $replace);
@endphp

@if ($exportOpen)
    <div class="ld-modal" wire:key="export-period" x-data x-on:keydown.escape.window="$wire.closeExport()">
        <div class="ld-modal__backdrop" wire:click="closeExport"></div>
        <form class="ld-modal__dialog rp-export-modal" role="dialog" aria-modal="true" aria-labelledby="rp-export-title" wire:submit="export">
            <header class="ld-modal__head">
                <span class="ld-modal__icon"><x-icon name="file" /></span>
                <div>
                    <h2 class="ld-modal__title" id="rp-export-title">{{ $P('title') }}</h2>
                    <p class="ld-modal__sub">{{ $P('sub') }}</p>
                </div>
                <button type="button" class="ld-modal__close" wire:click="closeExport" aria-label="{{ $P('cancel') }}">
                    <x-icon name="x" />
                </button>
            </header>

            <div class="ld-modal__body ld-form">
                <div class="vc-field ld-form__wide">
                    <span class="vc-field__label">{{ $P('type') }}</span>
                    <div class="rp-export-types" role="radiogroup">
                        @foreach ($this::EXPORT_TYPES as $type)
                            <label class="rp-export-type {{ $exportType === $type ? 'is-active' : '' }}">
                                <input type="radio" wire:model.live="exportType" value="{{ $type }}">
                                {{ $P('type_'.$type) }}
                            </label>
                        @endforeach
                    </div>
                    @error('exportType') <span class="ld-error">{{ $message }}</span> @enderror
                </div>

                <label class="vc-field {{ $exportType === 'year' ? 'ld-form__wide' : '' }}">
                    <span class="vc-field__label">{{ $P('year') }}</span>
                    <select wire:model.live="exportYear" class="vc-control">
                        @foreach ($this->exportYears() as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                    @error('exportYear') <span class="ld-error">{{ $message }}</span> @enderror
                </label>

                @if ($exportType === 'month')
                    <label class="vc-field">
                        <span class="vc-field__label">{{ $P('month') }}</span>
                        <select wire:model.live="exportMonth" class="vc-control">
                            @foreach (range(1, 12) as $month)
                                <option value="{{ $month }}">{{ $P('month_option', ['month' => $month]) }}</option>
                            @endforeach
                        </select>
                    </label>
                @elseif ($exportType === 'quarter')
                    <label class="vc-field">
                        <span class="vc-field__label">{{ $P('quarter') }}</span>
                        <select wire:model.live="exportQuarter" class="vc-control">
                            @foreach (['I', 'II', 'III', 'IV'] as $i => $roman)
                                <option value="{{ $i + 1 }}">{{ $P('quarter_option', ['quarter' => $roman, 'from' => $i * 3 + 1, 'to' => $i * 3 + 3]) }}</option>
                            @endforeach
                        </select>
                    </label>
                @elseif ($exportType === 'span')
                    <label class="vc-field">
                        <span class="vc-field__label">{{ $P('span') }}</span>
                        <select wire:model.live="exportSpan" class="vc-control">
                            @foreach ($this::EXPORT_SPANS as $span)
                                <option value="{{ $span }}">{{ $P('span_option', ['months' => $span]) }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <p class="ld-form__hint ld-form__wide">{{ $P('hint') }}</p>
            </div>

            <footer class="ld-modal__foot">
                <button type="button" class="vc-btn vc-btn--ghost" wire:click="closeExport">{{ $P('cancel') }}</button>
                <button type="submit" class="vc-btn vc-btn--primary" wire:loading.attr="disabled" wire:target="export">
                    <span wire:loading.remove wire:target="export"><x-icon name="file" /></span>
                    <span class="spinner-border spinner-border-sm" wire:loading wire:target="export" aria-hidden="true"></span>
                    {{ $P('submit') }}
                </button>
            </footer>
        </form>
    </div>
@endif
