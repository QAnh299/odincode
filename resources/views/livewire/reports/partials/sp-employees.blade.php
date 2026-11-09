{{-- Xếp hạng salesperson: $empRows, $empTotals, $rowsShown, $sortKey. Dùng chung giữa sales-performance và sales-team-performance; nhận biến của view cha qua @include ($sp, $money, $num, $pct, $formats...) --}}
<h2 class="rp-section">{{ $employeeTitle ?? $sp('employee_section') }}</h2>
<p class="rp-section__sub">
    {{ $sp('employee_section_sub', ['metric' => mb_strtolower($sp('metric.'.$sortKey))]) }}
    <span class="rp-legend"><span class="rp-cmp--up">●</span> {{ $sp('legend_up') }}</span>
    <span class="rp-legend"><span class="rp-cmp--down">●</span> {{ $sp('legend_down') }}</span>
</p>

<section class="vc-card rp-card">
    @include('livewire.reports.partials.sp-top', ['model' => 'employeeTop', 'shown' => count($rowsShown), 'total' => count($empRows),
        'teamModel' => 'employeeTeam', 'teamOptions' => $this->employeeTeamOptions])
    <div class="rp-scroll">
        <table class="rp-table rp-table--grouped">
            <thead>
                <tr>
                    <th class="rp-pin rp-pin--rank" rowspan="2">{{ $sp('rank') }}</th>
                    <th class="rp-pin rp-pin--2" rowspan="2">{{ $sp('employee') }}</th>
                    @foreach (\App\Livewire\Reports\SalesPerformance::COLUMN_GROUPS as $group => $cols)
                        <th class="rp-group rp-group--{{ $group }}" colspan="{{ count($cols) }}">{{ $sp('group.'.$group) }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach (\App\Livewire\Reports\SalesPerformance::COLUMN_GROUPS as $group => $cols)
                        @foreach (array_keys($cols) as $i => $key)
                            <th class="rp-num {{ $key === $sortKey ? 'is-sorted' : '' }} {{ $i === 0 ? 'rp-group-start' : '' }}">
                                <button type="button" class="rp-sort" wire:click="$set('sort', '{{ $key }}')">
                                    {{ $sp('metric.'.$key) }}{{ $key === $sortKey ? (\App\Livewire\Reports\SalesPerformance::SORTS[$key] === 'desc' ? ' ↓' : ' ↑') : '' }}
                                </button>
                            </th>
                        @endforeach
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rowsShown as $row)
                    <tr wire:key="emp-{{ $row['id'] }}" class="{{ $row['active'] ? '' : 'rp-row--idle' }}">
                        <td class="rp-pin rp-pin--rank">
                            @if ($row['rank'])
                                <span class="rp-rank rp-rank--{{ $row['rank'] }}">{{ $row['rank'] }}</span>
                            @else
                                -
                            @endif
                        </td>
                        <th scope="row" class="rp-pin rp-pin--2">
                            {{ $row['name'] }}{{ $row['resigned'] ? ' ('.$sp('resigned').')' : '' }}
                            @if ($row['team'])
                                <span class="rp-sub">{{ $row['team'] }}</span>
                            @endif
                        </th>
                        @foreach (\App\Livewire\Reports\SalesPerformance::COLUMN_GROUPS as $cols)
                            @foreach (array_keys($cols) as $i => $key)
                                <td class="rp-num {{ $row['compare'][$key] ? 'rp-cmp--'.$row['compare'][$key] : '' }} {{ $key === $sortKey ? 'is-sorted' : '' }} {{ $i === 0 ? 'rp-group-start' : '' }}">
                                    {{ $formats[$key]($row[$key]) }}
                                    @if ($key === 'revenue' && $row['share'] !== null && $row['revenue'] > 0)
                                        <span class="rp-sub">{{ $sp('share', ['rate' => $pct($row['share'])]) }}</span>
                                    @elseif ($key === 'close_rate' && $row['won'] + $row['lost'] > 0)
                                        <span class="rp-sub">{{ $sp('won_lost', ['won' => $row['won'], 'lost' => $row['lost']]) }}</span>
                                    @elseif ($key === 'open' && $row['open'] > 0)
                                        <span class="rp-sub">{{ $money($row['open_value']) }}</span>
                                    @elseif ($key === 'activity' && $row['activity'] > 0)
                                        <span class="rp-sub">{{ $sp('activity_split', ['care' => $row['care'], 'appts' => $row['appts']]) }}</span>
                                    @endif
                                </td>
                            @endforeach
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            @if (count($empRows) > 1)
                <tfoot>
                    <tr>
                        <td class="rp-pin rp-pin--rank"></td>
                        <th scope="row" class="rp-pin rp-pin--2">{{ $sp('total') }}</th>
                        @foreach (\App\Livewire\Reports\SalesPerformance::COLUMN_GROUPS as $cols)
                            @foreach (array_keys($cols) as $i => $key)
                                <td class="rp-num {{ $i === 0 ? 'rp-group-start' : '' }}">{{ $formats[$key]($empTotals[$key]) }}</td>
                            @endforeach
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</section>
