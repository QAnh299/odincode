{{-- Thông báo nhỏ góc trên bên phải, tự ẩn sau 4 giây. Mở bằng sự kiện lead-toast từ Livewire. --}}
<div class="ld-toast" role="status" wire:ignore style="display: none"
    x-data="{ text: '', show: false, timer: null, run: 0, duration: 4000 }"
    x-show="show"
    x-transition:enter-start="ld-toast--hidden" x-transition:leave-end="ld-toast--hidden"
    x-on:lead-toast.window="text = $event.detail.text; show = true; run++; clearTimeout(timer); timer = setTimeout(() => show = false, duration)">
    <span class="ld-toast__icon"><x-icon name="check-circle" /></span>
    <span class="ld-toast__text" x-text="text"></span>
    <button type="button" class="ld-toast__close" x-on:click="show = false" aria-label="{{ __('leads.close') }}">
        <x-icon name="x" />
    </button>
    {{-- Thanh đếm ngược: tạo lại phần tử để chạy lại từ đầu khi có thông báo mới --}}
    <template x-for="key in [run]" :key="key">
        <span class="ld-toast__bar" x-bind:style="{ animationDuration: duration + 'ms' }"></span>
    </template>
</div>
