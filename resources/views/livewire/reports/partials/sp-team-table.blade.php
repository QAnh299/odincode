    <section class="vc-card rp-card">
        @include('livewire.reports.partials.sp-top', ['model' => 'teamTop', 'shown' => count($teamShown), 'total' => count($teamRows)])
        <div class="rp-scroll">
            <table class="rp-table">
                <thead>
                    <tr>
                        <th class="rp-pin rp-pin--rank">{{ $sp('rank') }}</th>
                        <th class="rp-pin rp-pin--2">{{ $sp('team') }}</th>
                        <th>{{ $sp('target.revenue') }}</th>
                        <th>{{ $sp('target.students') }}</th>
                        <th class="rp-num">{{ $sp('metric.close_rate') }}</th>
                        <th class="rp-num">{{ $sp('metric.avg_order') }}</th>
                        <th class="rp-num">{{ $sp('metric.open') }} <span class="rp-sub">{{ $sp('now') }}</span></th>
                        <th class="rp-num">{{ $sp('metric.flagged') }} <span class="rp-sub">{{ $sp('now') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($teamShown as $row)
                        <tr wire:key="team-{{ $row['id'] }}">
                            <td class="rp-pin rp-pin--rank"><span class="rp-rank rp-rank--{{ $row['rank'] }}">{{ $row['rank'] }}</span></td>
                            <th scope="row" class="rp-pin rp-pin--2">
                                {{ $row['name'] }}
                                <span class="rp-sub">{{ $sp('members_count', ['count' => $row['members']]) }}</span>
                            </th>
                            @foreach (['revenue' => $money, 'students' => $num] as $metric => $fmt)
                                @php $rate = $row['attain'][$metric]; @endphp
                                <td>
                                    <div class="rp-goal {{ $rate !== null && $rate >= 100 ? 'is-done' : '' }}">
                                        <div class="rp-goal__top">
                                            <button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => $metric, 'team' => $row['id']])) }})"><strong>{{ $fmt($row[$metric]) }}</strong></button>
                                            @if ($rate !== null)
                                                <span class="rp-goal__pct">{{ $pct($rate) }}</span>
                                            @endif
                                        </div>
                                        @if ($rate !== null)
                                            <div class="rp-goal__bar"><span style="width: {{ min(100, $rate) }}%"></span></div>
                                            <span class="rp-sub">{{ $sp('of_target', ['target' => $fmt($row['target'][$metric])]) }}</span>
                                        @else
                                            <span class="rp-sub">{{ $sp('no_target') }}</span>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                            <td class="rp-num">
                                <button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'closed', 'team' => $row['id']])) }})">{{ $pct($row['close_rate']) }}</button>
                                <span class="rp-sub">{{ $sp('won_lost', ['won' => $row['won'], 'lost' => $row['lost']]) }}</span>
                            </td>
                            <td class="rp-num"><button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'revenue', 'team' => $row['id']])) }})">{{ $money($row['avg_order']) }}</button></td>
                            <td class="rp-num">
                                <button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'open', 'team' => $row['id']])) }})">{{ $num($row['open']) }}</button>
                                <span class="rp-sub">{{ $money($row['open_value']) }}</span>
                            </td>
                            <td class="rp-num">
                                @if ($row['flagged'])
                                    <button type="button" class="dr-cell" x-on:click="$dispatch('report-drill', {{ Js::from($this->drillTarget(['kind' => 'flagged', 'team' => $row['id']])) }})"><span class="rp-flag">{{ $row['flagged'] }}</span></button>
                                @else
                                    <span class="rp-ok">✓</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
