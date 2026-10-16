{{--
    Danh sách lịch hẹn của một Opportunity - dùng chung cho chi tiết Opportunity và chi tiết học viên.
    $appointments: nhóm theo mã lịch hẹn, mới nhất lên đầu - mỗi nhóm ['id', 'no', 'rows' (Test trước, Tư vấn sau)].
    $place: địa điểm (cơ sở của khách). Dòng có can_mark (chỉ ở chi tiết Opportunity) hiện nút Khách đã đến / không đến.
--}}
@php
    $label = fn (string $key, string $value) => \Illuminate\Support\Facades\Lang::has("$key.$value") ? __("$key.$value") : $value;
@endphp

@forelse ($appointments as $group)
    @php
        $statuses = $group['rows']->pluck('status')->unique();
        $tone = $statuses->count() === 1 ? $statuses->first() : 'Mixed';
    @endphp
    <article class="od-apt od-apt--{{ $tone }}" wire:key="apt-{{ $group['id'] }}">
        <div class="od-apt__head">
            <strong>{{ __('opportunities.appointment_no', ['no' => $group['no']]) }}</strong>
            <span class="od-muted">{{ $group['id'] }}</span>
        </div>
        @if ($place)
            <p class="od-place od-place--card"><x-icon name="tag" />{{ $place }}</p>
        @endif

        {{-- Từng loại: Test / Tư vấn - thời gian, kết quả riêng --}}
        <ul class="od-apt__rows">
            @foreach ($group['rows'] as $row)
                <li class="od-apt__row" wire:key="apt-{{ $group['id'] }}-{{ $row->appointment_type }}">
                    <div class="od-apt__type">
                        <span class="od-type od-type--{{ $row->appointment_type }}">
                            {{ $label('opportunities.appointment_type_name', $row->appointment_type) }}
                        </span>
                        <span class="vc-badge od-apt--{{ $row->status }}">
                            <span class="vc-badge__dot"></span>{{ $label('opportunities.appointment_status', $row->status) }}
                        </span>
                    </div>

                    <ul class="od-apt__meta">
                        <li><x-icon name="calendar" />{{ $row->scheduled_time->format('d/m/Y H:i') }}</li>
                    </ul>

                    @if ($row->notes)
                        <p class="od-apt__note">{{ $row->notes }}</p>
                    @endif

                    @if ($row->can_mark)
                        <div class="od-apt__actions">
                            <button type="button" class="vc-btn od-btn--success"
                                wire:click="markAppointment('{{ $row->appointment_id }}', '{{ $row->appointment_type }}', 'Success')"
                                wire:confirm="{{ __('opportunities.confirm_attended') }}">
                                <x-icon name="check" />{{ __('opportunities.mark_attended') }}
                            </button>
                            <button type="button" class="vc-btn od-btn--danger"
                                wire:click="markAppointment('{{ $row->appointment_id }}', '{{ $row->appointment_type }}', 'Failed')"
                                wire:confirm="{{ __('opportunities.confirm_absent') }}">
                                <x-icon name="x" />{{ __('opportunities.mark_absent') }}
                            </button>
                        </div>
                    @elseif ($row->status === 'Scheduled')
                        <p class="od-muted od-apt__wait"><x-icon name="clock" />{{ __('opportunities.apt_wait_hint') }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </article>
@empty
    <p class="od-empty">{{ __('opportunities.no_appointments') }}</p>
@endforelse
