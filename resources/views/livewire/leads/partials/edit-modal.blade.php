{{--
    Popup chỉnh sửa thông tin Lead (trait App\Livewire\Leads\Concerns\EditsLead).
    Chỉ hiện các trường được sửa ($this->leadEditableFields()); SĐT, Link nguồn bắt buộc tuỳ kênh liên hệ.
    Tham số: $lead - Lead đang sửa; $branches - chi nhánh để chọn (khi được sửa chi nhánh).
--}}
@php
    $fields = $this->leadEditableFields();
    $method = $leadForm['contact_method'] ?? $lead->contact_method;
@endphp

<div class="ld-modal" wire:key="modal-edit-lead" x-data x-on:keydown.escape.window="$wire.cancelEditLead()">
    <div class="ld-modal__backdrop" wire:click="cancelEditLead"></div>
    <form class="ld-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="ld-edit-title" wire:submit="saveLeadEdit">
        <header class="ld-modal__head">
            <span class="ld-modal__icon"><x-icon name="edit" /></span>
            <div>
                <h2 class="ld-modal__title" id="ld-edit-title">{{ __('leads.edit_title') }}</h2>
                <p class="ld-modal__sub">{{ $lead->lead_id }}</p>
            </div>
            <button type="button" class="ld-modal__close" wire:click="cancelEditLead" aria-label="{{ __('leads.close') }}">
                <x-icon name="x" />
            </button>
        </header>

        <div class="ld-modal__body ld-form">
            <label class="vc-field">
                <span class="vc-field__label">{{ __('leads.field.full_name') }}<span class="ld-req">*</span></span>
                <input type="text" wire:model="leadForm.full_name" class="vc-control @error('full_name') is-invalid @enderror" autofocus>
                @error('full_name')<span class="ld-error">{{ $message }}</span>@enderror
            </label>

            @if (in_array('contact_method', $fields, true))
                <label class="vc-field">
                    <span class="vc-field__label">{{ __('leads.field.contact_method') }}<span class="ld-req">*</span></span>
                    <select wire:model.live="leadForm.contact_method" class="vc-control @error('contact_method') is-invalid @enderror">
                        @foreach (\App\Models\Lead::CONTACT_METHODS as $option)
                            <option value="{{ $option }}">{{ __('leads.method.'.$option) }}</option>
                        @endforeach
                    </select>
                    @error('contact_method')<span class="ld-error">{{ $message }}</span>@enderror
                </label>
            @endif

            <label class="vc-field">
                <span class="vc-field__label">
                    {{ __('leads.field.phone') }}@if (in_array($method, \App\Models\Lead::METHODS_PHONE_REQUIRED, true))<span class="ld-req">*</span>@endif
                </span>
                <input type="tel" wire:model="leadForm.phone" placeholder="{{ __('leads.form.placeholder_phone') }}"
                    class="vc-control @error('phone') is-invalid @enderror">
                @error('phone')<span class="ld-error">{{ $message }}</span>@enderror
            </label>

            <label class="vc-field">
                <span class="vc-field__label">{{ __('leads.field.email') }}</span>
                <input type="email" wire:model="leadForm.email" placeholder="{{ __('leads.form.placeholder_email') }}"
                    class="vc-control @error('email') is-invalid @enderror">
                @error('email')<span class="ld-error">{{ $message }}</span>@enderror
            </label>

            @if (in_array('source_name', $fields, true))
                <label class="vc-field">
                    <span class="vc-field__label">{{ __('leads.field.source_name') }}<span class="ld-req">*</span></span>
                    <select wire:model="leadForm.source_name" class="vc-control @error('source_name') is-invalid @enderror">
                        @foreach (\App\Models\Lead::SOURCES as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                    @error('source_name')<span class="ld-error">{{ $message }}</span>@enderror
                </label>
            @endif

            @if (in_array('branch_id', $fields, true))
                <label class="vc-field">
                    <span class="vc-field__label">{{ __('leads.branch') }}<span class="ld-req">*</span></span>
                    <select wire:model="leadForm.branch_id" class="vc-control @error('branch_id') is-invalid @enderror">
                        @foreach ($branches as $option)
                            <option value="{{ $option->branch_id }}">{{ $option->branch_name }}</option>
                        @endforeach
                    </select>
                    @error('branch_id')<span class="ld-error">{{ $message }}</span>@enderror
                </label>
            @endif

            <label class="vc-field ld-form__wide">
                <span class="vc-field__label">
                    {{ __('leads.field.source_url') }}@if (in_array($method, \App\Models\Lead::METHODS_LINK_REQUIRED, true))<span class="ld-req">*</span>@endif
                </span>
                <input type="text" wire:model="leadForm.source_url" placeholder="{{ __('leads.form.placeholder_url') }}"
                    class="vc-control @error('source_url') is-invalid @enderror">
                @error('source_url')<span class="ld-error">{{ $message }}</span>@enderror
            </label>
        </div>

        <footer class="ld-modal__foot">
            <button type="button" class="vc-btn vc-btn--ghost" wire:click="cancelEditLead">{{ __('leads.cancel') }}</button>
            <button type="submit" class="vc-btn vc-btn--primary" wire:loading.attr="disabled" wire:target="saveLeadEdit">
                <x-icon name="check" />{{ __('leads.edit_save') }}
            </button>
        </footer>
    </form>
</div>
