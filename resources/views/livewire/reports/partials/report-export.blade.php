{{-- Popup xuất báo cáo dùng chung (trait App\Livewire\Reports\Concerns\ReportExport). Cần lead.css (.ld-modal) + report.css (.lx).
     3 cột: bộ lọc (năm, tháng, tiêu chí của component) · xem trước sheet đang chọn (đúng bố cục file, phóng to / thu nhỏ) · cột hiển thị.
     Đưa ra <body> (@teleport) để lớp mờ phủ toàn trang; gốc mang class "vc" để giữ biến màu. --}}
@php
    $X = fn ($key, $replace = []) => $this->exportLabel($key, $replace);
    $U = fn ($key, $replace = []) => __('reports.export_ui.'.$key, $replace);
    $P = fn ($key, $replace = []) => __('reports.export_period.'.$key, $replace);
    $tab = $this->activeExportTab();
    $catalog = $this->exportColumnCatalog();
    $locked = $this::EXPORT_LOCKED[$tab];
    $columnsOn = count(array_filter(array_keys($catalog[$tab]), fn ($c) => $this->exportColumnOn($tab, $c)));
    $sheetsOn = count(array_filter($this::EXPORT_SHEETS, fn ($s) => $this->exportSheetOn($s)));
    $pv = $exportOpen ? $this->exportPreview : null;
@endphp

@if ($exportOpen)
    @teleport('body')
    <div class="ld-modal vc rp-portal" wire:key="report-export" x-data x-on:keydown.escape.window="$wire.closeExport()">
        <div class="ld-modal__backdrop" wire:click="closeExport"></div>
        <form class="ld-modal__dialog lx" role="dialog" aria-modal="true" aria-labelledby="lx-title" wire:submit="export">
            <header class="lx__head">
                <h2 class="lx__title" id="lx-title">{{ $X('dialog_title') }}</h2>
                <button type="button" class="ld-modal__close" wire:click="closeExport" aria-label="{{ $P('cancel') }}">
                    <x-icon name="x" />
                </button>
            </header>

            <div class="lx__body">
                {{-- ── Bộ lọc ───────────────────────────────────────────── --}}
                <aside class="lx__pane lx__filters">
                    <h3 class="lx__pane-title">{{ $U('filters') }}</h3>
                    <label class="vc-field">
                        <span class="vc-field__label">{{ $P('year') }}</span>
                        <select wire:model.live="exportYear" class="vc-control">
                            @foreach ($this->exportYears() as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                        @error('exportYear') <span class="ld-error">{{ $message }}</span> @enderror
                    </label>
                    {{-- Kỳ: cả năm / quý / tháng của năm đã chọn (năm nay thì đến quý / tháng hiện tại) --}}
                    <label class="vc-field">
                        <span class="vc-field__label">{{ $U('period') }}</span>
                        <select wire:model.live="exportPick" class="vc-control">
                            <option value="year">{{ $U('month_all') }}</option>
                            <optgroup label="{{ $U('quarters') }}">
                                @foreach ($this->exportQuarters() as $quarter)
                                    <option value="q{{ $quarter }}">{{ $U('quarter_option', ['quarter' => ['I', 'II', 'III', 'IV'][$quarter - 1]]) }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="{{ $U('months') }}">
                                @foreach ($this->exportMonths() as $month)
                                    <option value="m{{ $month }}">{{ $P('month_option', ['month' => $month]) }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                        @error('exportType') <span class="ld-error">{{ $message }}</span> @enderror
                    </label>
                    {{-- Tiêu chí riêng của popup ('' = tất cả; độc lập với bộ lọc trên màn hình) --}}
                    @foreach ($this->exportFilterFields() as $field)
                        <label class="vc-field" wire:key="xf-{{ $field['model'] }}">
                            <span class="vc-field__label">{{ $field['label'] }}</span>
                            <select wire:model.live="{{ $field['model'] }}" class="vc-control">
                                <option value="">{{ $field['all'] }}</option>
                                @foreach ($field['options'] as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </aside>

                {{-- ── Xem trước ────────────────────────────────────────── --}}
                <section class="lx__pane lx__preview" x-data="{ zoom: 100 }">
                    <div class="lx__toolbar">
                        <h3 class="lx__pane-title">{{ $U('preview') }}</h3>
                        <div class="lx__zoom">
                            <button type="button" class="lx__icon-btn" x-on:click="zoom = Math.max(50, zoom - 10)" aria-label="{{ $U('zoom_out') }}">−</button>
                            <span x-text="zoom + '%'">100%</span>
                            <button type="button" class="lx__icon-btn" x-on:click="zoom = Math.min(150, zoom + 10)" aria-label="{{ $U('zoom_in') }}">+</button>
                        </div>
                    </div>

                    <nav class="lx__tabs" role="tablist">
                        @foreach ($this::EXPORT_SHEETS as $sheet)
                            <button type="button" role="tab" wire:click="$set('exportTab', '{{ $sheet }}')"
                                class="lx__tab {{ $tab === $sheet ? 'is-active' : '' }} {{ $this->exportSheetOn($sheet) ? '' : 'is-off' }}"
                                aria-selected="{{ $tab === $sheet ? 'true' : 'false' }}">
                                {{ $X('sheet.'.$sheet) }}
                                @unless ($this->exportSheetOn($sheet))
                                    <span class="lx__tab-off">{{ $U('sheet_off') }}</span>
                                @endunless
                            </button>
                        @endforeach
                    </nav>

                    <div class="lx__canvas" wire:loading.class="is-loading" wire:target.except="export">
                        @if (! $pv)
                            <p class="rp-empty rp-empty--inline">{{ $errors->first('exportType') ?: $errors->first('exportYear') ?: __('reports.no_data') }}</p>
                        @else
                            @php
                                $doc = $pv['document'];
                                $sh = $pv['sheet'];
                                $cols = $sh['columns'];
                                $fmt = function ($type, $value) {
                                    if ($value === null || $value === '') {
                                        return '';
                                    }
                                    return match ($type) {
                                        'int', 'money' => number_format((float) $value, 0, ',', '.'),
                                        'decimal'      => number_format((float) $value, 1, ',', '.'),
                                        'percent'      => number_format((float) $value, 1, ',', '.').'%',
                                        default        => $value,
                                    };
                                };
                            @endphp
                            <div class="lx-paper" x-bind:style="'zoom:' + zoom / 100">
                                <div class="lx-paper__pair">
                                    <div>
                                        <div>{{ __('reports.document.company_1') }}</div>
                                        <div>{{ __('reports.document.company_2') }}</div>
                                        <div class="is-bold">{{ __('reports.document.brand') }}</div>
                                        <div class="lx-paper__rule"></div>
                                    </div>
                                    <div>
                                        <div class="is-bold">{{ __('reports.document.nation') }}</div>
                                        <div class="is-bold">{{ __('reports.document.motto') }}</div>
                                        <div class="lx-paper__rule"></div>
                                    </div>
                                </div>
                                <div class="lx-paper__pair">
                                    <div>{{ __('reports.document.number', ['code' => $doc['number']]) }}</div>
                                    <div class="is-italic">{{ __('reports.document.place_date', ['day' => $doc['date']->format('d'), 'month' => $doc['date']->format('m'), 'year' => $doc['date']->format('Y')]) }}</div>
                                </div>

                                <h4 class="lx-paper__title">{{ mb_strtoupper($sh['title']) }}</h4>
                                <div class="lx-paper__period">{{ mb_strtoupper($sh['period']) }}</div>
                                @foreach ($sh['scope'] as $line)
                                    <div class="lx-paper__scope">{{ $line }}</div>
                                @endforeach
                                @if (! empty($sh['unit']))
                                    <div class="lx-paper__unit">{{ $sh['unit'] }}</div>
                                @endif

                                <table class="lx-paper__table">
                                    <thead>
                                        <tr>
                                            @foreach ($cols as [$label])
                                                <th>{{ $label }}</th>
                                            @endforeach
                                        </tr>
                                        <tr class="lx-paper__index">
                                            @foreach ($cols as $i => $col)
                                                <td>({{ $i + 1 }})</td>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($sh['rows'] as [$kind, $values])
                                            <tr class="is-{{ $kind }}">
                                                @foreach ($cols as $i => [, $type])
                                                    <td class="{{ in_array($type, ['label', 'text'], true) ? '' : ($type === 'stt' ? 'is-center' : 'is-num') }}">{{ $fmt($type, $values[$i] ?? null) }}</td>
                                                @endforeach
                                            </tr>
                                        @empty
                                            <tr><td colspan="{{ count($cols) }}" class="is-center is-italic">{{ __('reports.no_data') }}</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                                @if ($pv['items'] > $pv['shown'])
                                    <p class="lx-paper__more">{{ $U('preview_more', ['count' => number_format($pv['items'] - $pv['shown'], 0, ',', '.')]) }}</p>
                                @endif
                                @foreach ($sh['notes'] ?? [] as $note)
                                    <p class="lx-paper__note">{{ $note }}</p>
                                @endforeach

                                <div class="lx-paper__pair lx-paper__sign">
                                    <div><div class="is-bold">{{ __('reports.document.preparer') }}</div><div class="is-italic">{{ __('reports.document.sign_hint') }}</div><div class="lx-paper__name">{{ $doc['preparer'] }}</div></div>
                                    <div><div class="is-bold">{{ __('reports.document.approver') }}</div><div class="is-italic">{{ __('reports.document.sign_hint') }}</div><div class="lx-paper__name">{{ $doc['approver'] }}</div></div>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                {{-- ── Cột hiển thị (sheet đang xem) ───────────────────── --}}
                <aside class="lx__pane lx__columns">
                    <div class="lx__toolbar">
                        <h3 class="lx__pane-title">{{ $U('columns') }}</h3>
                        <span class="lx__count">{{ $U('columns_count', ['on' => $columnsOn, 'total' => count($catalog[$tab])]) }}</span>
                    </div>
                    <label class="lx__check lx__check--sheet">
                        <input type="checkbox" wire:model.live="exportSheets.{{ $tab }}">
                        <span>{{ $U('sheet_include') }}</span>
                    </label>
                    <div class="lx__quick {{ $this->exportSheetOn($tab) ? '' : 'is-off' }}">
                        <button type="button" wire:click="defaultExportColumns">{{ $U('columns_default') }}</button>
                        <span aria-hidden="true">·</span>
                        <button type="button" wire:click="allExportColumns">{{ $U('columns_all') }}</button>
                    </div>
                    <div class="lx__column-list {{ $this->exportSheetOn($tab) ? '' : 'is-off' }}">
                        @foreach ($catalog[$tab] as $column => $type)
                            <label class="lx__check" wire:key="col-{{ $tab }}-{{ $column }}">
                                @if ($column === $locked)
                                    <input type="checkbox" checked disabled>
                                @else
                                    <input type="checkbox" wire:model.live="exportColumns.{{ $tab }}.{{ $column }}">
                                @endif
                                <span>{{ $X($tab.'.'.$column) }}@if ($column === $locked) <em class="lx__locked">{{ $U('column_locked') }}</em>@endif</span>
                            </label>
                        @endforeach
                    </div>
                </aside>
            </div>

            <footer class="ld-modal__foot lx__foot">
                <span class="lx__note">
                    @if ($pv)
                        {{ $U('preview_rows', ['shown' => $pv['shown'], 'total' => number_format($pv['items'], 0, ',', '.')]) }} ·
                    @endif
                    {{ $U('sheets_count', ['count' => $sheetsOn]) }}
                    @error('exportSheets') <span class="ld-error">{{ $message }}</span> @enderror
                </span>
                <button type="button" class="vc-btn vc-btn--ghost" wire:click="closeExport">{{ $P('cancel') }}</button>
                <button type="submit" class="vc-btn vc-btn--primary" wire:loading.attr="disabled" wire:target="export" @disabled(! $sheetsOn)>
                    <span wire:loading.remove wire:target="export"><x-icon name="file" /></span>
                    <span class="spinner-border spinner-border-sm" wire:loading wire:target="export" aria-hidden="true"></span>
                    {{ $U('submit') }}
                </button>
            </footer>
        </form>
    </div>
    @endteleport
@endif
