{{--
    Lịch sử chăm sóc của một Opportunity - dùng chung cho chi tiết Opportunity và chi tiết học viên.
    $cares: các lần liên hệ, mới nhất lên đầu (kèm activity, employee).
    Tách theo hoạt động (Liên hệ ban đầu - Xếp lại lịch - Chốt đăng ký), trong mỗi nhóm đánh số "Lần N" theo thời gian.
--}}
@foreach ($cares->groupBy('activity_id')->sortKeys() as $activityId => $group)
    <section class="od-care-group" wire:key="care-group-{{ $activityId }}">
        <h3 class="od-care-group__title">
            {{ $group->first()->activity?->activity_name ?? $activityId }}
            <span class="od-tab__count">{{ $group->count() }}</span>
        </h3>

        <div class="od-care-group__list">
            @foreach ($group as $care)
                <div class="od-timeline__item od-res--{{ $care->result }}" wire:key="care-{{ $care->result_id }}">
                    <span class="od-timeline__dot"></span>
                    <div class="od-timeline__body">
                        <div class="od-timeline__head">
                            <strong>{{ __('opportunities.care_no', ['no' => $group->count() - $loop->index]) }}</strong>
                            <span class="vc-badge od-res--{{ $care->result }}">
                                <span class="vc-badge__dot"></span>{{ __('opportunities.care_result.'.$care->result) }}
                            </span>
                        </div>
                        @if ($care->notes)
                            <p class="od-timeline__note">{{ $care->notes }}</p>
                        @endif
                        <div class="od-timeline__by">
                            <span class="od-muted">{{ $care->performed_at?->format('d/m/Y H:i') }}</span>
                            {{-- Nhân viên thực hiện lần liên hệ --}}
                            <span class="od-by"><x-icon name="user" />{{ $care->employee?->full_name ?? '—' }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endforeach
@if ($cares->isEmpty())
    <p class="od-empty">{{ __('opportunities.no_care') }}</p>
@endif
