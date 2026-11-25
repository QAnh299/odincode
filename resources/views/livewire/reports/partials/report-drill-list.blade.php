{{-- Nội dung popup danh sách ($drill - ReportDrill::drill); nạp qua x-html trong report-drill nên chỉ dùng Alpine, không dùng wire:* --}}
@php
    $D = fn ($key, $replace = []) => __('reports.drill.'.$key, $replace);
    $numeric = fn ($type) => in_array($type, ['money', 'int', 'decimal'], true);
    $cell = fn ($type, $v) => match (true) {
        $v === null || $v === '' => '-',
        $type === 'money'   => number_format((float) $v, 0, ',', '.').' ₫',
        $type === 'int'     => number_format((float) $v, 0, ',', '.'),
        $type === 'decimal' => number_format((float) $v, 1, ',', '.'),
        $type === 'date'    => \Carbon\Carbon::parse($v)->format('d/m/Y H:i'),
        default             => $v,
    };
    $shown = count($drill['rows']);
@endphp
            <header class="ld-modal__head">
                <div>
                    <h2 class="ld-modal__title" id="dr-title">{{ $drill['title'] }} ({{ number_format($drill['total'], 0, ',', '.') }})</h2>
                    <p class="ld-modal__sub">{{ $drill['scope'] }}</p>
                </div>
                <button type="button" class="ld-modal__close" x-on:click="open = false" aria-label="{{ $D('close') }}">
                    <x-icon name="x" />
                </button>
            </header>
            <div class="dr__body">
                @if (! $shown)
                    <p class="rp-empty rp-empty--inline">{{ __('reports.no_data') }}</p>
                @else
                    <table class="rp-table dr__table">
                        <thead>
                            <tr>
                                <th class="rp-num">#</th>
                                @foreach ($drill['columns'] as $column => $type)
                                    <th class="{{ $numeric($type) ? 'rp-num' : '' }}">{{ $D('col.'.$column) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($drill['rows'] as $i => $row)
                                <tr>
                                    <td class="rp-num">{{ $i + 1 }}</td>
                                    @foreach ($drill['columns'] as $column => $type)
                                        <td class="{{ $numeric($type) ? 'rp-num' : '' }}">
                                            @if ($loop->first && $row['_url'])
                                                <a href="{{ $row['_url'] }}" target="_blank" rel="noopener" title="{{ $D('open') }}">{{ $cell($type, $row[$column] ?? null) }}</a>
                                            @else
                                                {{ $cell($type, $row[$column] ?? null) }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        @if ($drill['sums'])
                            <tfoot>
                                <tr>
                                    <td></td>
                                    @foreach ($drill['columns'] as $column => $type)
                                        <td class="{{ $numeric($type) ? 'rp-num' : '' }}">
                                            {{ $loop->first ? $D('total') : (isset($drill['sums'][$column]) ? $cell('money', $drill['sums'][$column]) : '') }}
                                        </td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                @endif
            </div>
            <footer class="dr__foot">
                <span>{{ $D('showing', ['shown' => number_format($shown, 0, ',', '.'), 'total' => number_format($drill['total'], 0, ',', '.')]) }}</span>
                @if ($shown < $drill['total'])
                    <button type="button" class="vc-btn vc-btn--ghost" x-on:click="more()" x-bind:disabled="loading">{{ $D('more') }}</button>
                @endif
                <button type="button" class="vc-btn vc-btn--primary" x-on:click="open = false">{{ $D('close') }}</button>
            </footer>
