{{-- Việc cần xử lý: $alertRows, $flaggedTotal. Dùng chung giữa sales-performance và sales-team-performance; nhận biến của view cha qua @include ($sp, $money, $num, $pct, $formats...) --}}
<h2 class="rp-section">{{ $alertTitle ?? $sp('alert_section') }}</h2>
<p class="rp-section__sub">{{ $sp('alert_section_sub', ['days' => \App\Services\SalesPerformanceReport::STALE_DAYS]) }}</p>

<section class="vc-card rp-card">
    @if (! $alertRows)
        <p class="rp-empty rp-empty--inline rp-empty--ok">{{ $sp('alert_none') }}</p>
    @else
        <div class="rp-scroll">
            <table class="rp-table">
                <thead>
                    <tr>
                        <th class="rp-pin">{{ $sp('employee') }}</th>
                        @foreach (\App\Services\SalesPerformanceReport::ALERTS as $alert)
                            <th class="rp-num">{{ $sp('alert.'.$alert, ['days' => \App\Services\SalesPerformanceReport::STALE_DAYS]) }}</th>
                        @endforeach
                        <th class="rp-num">{{ $sp('alert.flagged') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($alertRows as $row)
                        <tr wire:key="al-{{ $row['id'] }}">
                            <th scope="row" class="rp-pin">
                                {{ $row['name'] }}
                                @if ($row['team'])
                                    <span class="rp-sub">{{ $row['team'] }}</span>
                                @endif
                            </th>
                            @foreach (\App\Services\SalesPerformanceReport::ALERTS as $alert)
                                <td class="rp-num">
                                    @if ($row['alerts'][$alert])
                                        <span class="rp-flag rp-flag--{{ $alert }}">{{ $row['alerts'][$alert] }}</span>
                                    @else
                                        <span class="rp-sub">0</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="rp-num"><strong>{{ $row['alerts']['flagged'] }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
                @if (count($alertRows) > 1)
                    <tfoot>
                        <tr>
                            <th scope="row" class="rp-pin">{{ $sp('total') }}</th>
                            @foreach (\App\Services\SalesPerformanceReport::ALERTS as $alert)
                                <td class="rp-num">{{ array_sum(array_map(fn ($r) => $r['alerts'][$alert], $alertRows)) }}</td>
                            @endforeach
                            <td class="rp-num"><strong>{{ $flaggedTotal }}</strong></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @endif
</section>
