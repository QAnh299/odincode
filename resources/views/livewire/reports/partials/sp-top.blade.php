{{-- Thanh công cụ trên bảng hiệu quả: $model = thuộc tính Livewire số dòng (teamTop / employeeTop), $shown / $total = số dòng đang hiện / tổng;
     tuỳ chọn $teamModel + $teamOptions = ô chọn đội (chỉ hiện khi có đội để chọn). Ô Top chỉ hiện khi bảng có nhiều hơn 5 dòng. --}}
@php
    $teamOptions = $teamOptions ?? collect();
@endphp
@if ($total > 5 || $teamOptions->isNotEmpty())
    <div class="rp-toolbar">
        <div class="rp-toolbar__group">
            @if ($teamOptions->isNotEmpty())
                <label class="rp-toolbar__field">
                    <span>{{ __('reports.sales_performance.employee_team') }}</span>
                    <select wire:model.live="{{ $teamModel }}" class="vc-control">
                        <option value="">{{ __('reports.sales_performance.all_teams') }}</option>
                        @foreach ($teamOptions as $option)
                            <option value="{{ $option->team_id }}">{{ $option->team_name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if ($total > 5)
                <label class="rp-toolbar__field">
                    <span>{{ __('reports.sales_performance.show') }}</span>
                    <select wire:model.live="{{ $model }}" class="vc-control">
                        @foreach (\App\Livewire\Reports\SalesPerformance::TOPS as $option)
                            <option value="{{ $option }}">{{ __('reports.sales_performance.top.'.$option) }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>
        <span class="rp-toolbar__count">{{ __('reports.sales_performance.showing', ['shown' => $shown, 'total' => $total]) }}</span>
    </div>
@endif
