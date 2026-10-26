{{--
    Danh sách lịch hẹn của một Opportunity - dùng chung cho chi tiết Opportunity và chi tiết học viên.
    $appointments: nhóm theo mã lịch hẹn, mới nhất lên đầu - mỗi nhóm ['id', 'no', 'rows' (Test trước, Tư vấn sau)].
    $place: địa điểm (cơ sở của khách). Dòng có can_mark (chỉ ở chi tiết Opportunity) hiện nút Khách đã đến / không đến.
--}}
@php
    $label = fn (string $key, string $value) => \Illuminate\Support\Facades\Lang::has("$key.$value") ? __("$key.$value") : $value;
@endphp

@foreach ($appointments as $group)
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
                        @if ($row->relationLoaded('employee') && $row->employee)
                            <li title="{{ __('opportunities.apt_staff') }}"><x-icon name="user" />{{ $row->employee->full_name }}</li>
                        @endif
                    </ul>

                    @if ($row->notes)
                        <p class="od-apt__note">{{ $row->notes }}</p>
                    @endif

                    @if ($row->can_mark && $this->attendKey === $row->appointment_id.'-'.$row->appointment_type)
                        {{-- Khách đã đến: ghi kết quả buổi hẹn (lưu nối vào ghi chú lịch hẹn) --}}
                        <form class="od-attend" wire:submit="saveAttended">
                            <label class="vc-field">
                                <span class="vc-field__label">{{ __('opportunities.apt_result') }} <span class="ld-req">*</span></span>
                                <textarea wire:model="attendResult" rows="2" maxlength="1000"
                                    class="vc-control od-textarea @error('attendResult') is-invalid @enderror"
                                    placeholder="{{ __('opportunities.apt_result_placeholder.'.$row->appointment_type) }}"></textarea>
                                @error('attendResult') <span class="ld-error">{{ $message }}</span> @enderror
                            </label>
                            <div class="od-apt__actions">
                                <button type="button" class="vc-btn vc-btn--ghost" wire:click="closeForm">{{ __('leads.cancel') }}</button>
                                <button type="submit" class="vc-btn od-btn--success" wire:loading.attr="disabled" wire:target="saveAttended">
                                    <x-icon name="check" />{{ __('opportunities.mark_attended') }}
                                </button>
                            </div>
                        </form>
                    @elseif ($row->can_mark)
                        <div class="od-apt__actions">
                            <button type="button" class="vc-btn od-btn--success"
                                wire:click="startAttended('{{ $row->appointment_id }}', '{{ $row->appointment_type }}')">
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
@endforeach
@if ($appointments->isEmpty())
    <p class="od-empty">{{ __('opportunities.no_appointments') }}</p>
@endif
