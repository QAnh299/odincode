{{--
    Các ô nhập lịch hẹn mới (dùng trong form Tạo lịch hẹn và form ghi nhận liên hệ thành công).
    Mỗi loại được tick (Test / Tư vấn) có thời gian và cơ sở riêng.
    Tham số: $allowedTypes – các loại được chọn.
--}}
<div class="vc-field od-create__wide">
    <span class="vc-field__label">{{ __('opportunities.appointment_type') }} <span class="ld-req">*</span></span>
    <div class="od-checks">
        @foreach (\App\Models\Appointment::TYPES as $type)
            @php $allowed = in_array($type, $allowedTypes, true); @endphp
            <label class="od-check {{ $allowed ? '' : 'is-disabled' }}">
                <input type="checkbox" wire:model.live="newTypes" value="{{ $type }}" @disabled(! $allowed)>
                <span class="od-type od-type--{{ $type }}">{{ __('opportunities.appointment_type_name.'.$type) }}</span>
            </label>
        @endforeach
    </div>
    @error('newTypes') <span class="ld-error">{{ $message }}</span> @enderror
    @error('newTypes.*') <span class="ld-error">{{ $message }}</span> @enderror
</div>

{{-- Thời gian + cơ sở cho từng loại đã tick --}}
@foreach (\App\Models\Appointment::TYPES as $type)
    @if (in_array($type, $newTypes, true) && in_array($type, $allowedTypes, true))
        <div class="od-slot od-create__wide" wire:key="slot-{{ $type }}">
            <span class="od-slot__type od-type od-type--{{ $type }}">{{ __('opportunities.appointment_type_name.'.$type) }}</span>

            <label class="vc-field">
                <span class="vc-field__label">{{ __('opportunities.appointment_time') }} <span class="ld-req">*</span></span>
                <input type="datetime-local" wire:model="newTimes.{{ $type }}" min="{{ now()->format('Y-m-d\TH:i') }}"
                    class="vc-control @error('newTimes.'.$type) is-invalid @enderror">
                @error('newTimes.'.$type) <span class="ld-error">{{ $message }}</span> @enderror
            </label>

            <label class="vc-field">
                <span class="vc-field__label">{{ __('opportunities.location') }} <span class="ld-req">*</span></span>
                <select wire:model="newLocations.{{ $type }}" class="vc-control @error('newLocations.'.$type) is-invalid @enderror">
                    @foreach ($this->branchOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                @error('newLocations.'.$type) <span class="ld-error">{{ $message }}</span> @enderror
            </label>
        </div>
    @endif
@endforeach

<label class="vc-field od-create__wide">
    <span class="vc-field__label">{{ __('opportunities.apt_notes') }}</span>
    <textarea wire:model="newNotes" rows="2" maxlength="1000" class="vc-control od-textarea"
        placeholder="{{ __('opportunities.apt_notes_placeholder') }}"></textarea>
    @error('newNotes') <span class="ld-error">{{ $message }}</span> @enderror
</label>
