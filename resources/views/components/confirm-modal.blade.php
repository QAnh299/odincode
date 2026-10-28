{{--
    Popup xác nhận dùng chung (thay hộp thoại confirm của trình duyệt). Mở bằng sự kiện trình duyệt:
        x-on:click="$dispatch('odin-confirm', { title: '...', message: '...', ok: '...', tone: 'primary' | 'danger', run: () => $wire.hanhDong() })"
    Chọn nút đồng ý → gọi run(); Hủy, bấm ra ngoài hoặc Esc → đóng, không làm gì.
--}}
<div x-data="{ open: false, title: '', message: '', ok: '', tone: 'primary', run: null }"
    x-on:odin-confirm.window="title = $event.detail.title ?? ''; message = $event.detail.message ?? ''; ok = $event.detail.ok ?? @js(__('actions.confirm')); tone = $event.detail.tone ?? 'primary'; run = $event.detail.run ?? null; open = true; $nextTick(() => $refs.ok.focus())"
    x-on:keydown.escape.window="open = false">
    <div class="modal odin-confirm" x-show="open" x-cloak x-transition.opacity tabindex="-1" x-on:click.self="open = false"
        role="alertdialog" aria-modal="true" aria-labelledby="odinConfirmTitle" aria-describedby="odinConfirmMessage">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content odin-logout-modal">
                <div class="modal-body text-center p-4">
                    <div class="odin-confirm__icon mx-auto mb-3" :class="'odin-confirm__icon--' + tone">
                        <svg x-show="tone !== 'danger'" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" /><path d="M12 16v-4M12 8h.01" />
                        </svg>
                        <svg x-show="tone === 'danger'" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01" />
                        </svg>
                    </div>
                    <h2 class="h5 fw-bold mb-2" id="odinConfirmTitle" x-text="title"></h2>
                    <p class="text-secondary mb-4" id="odinConfirmMessage" x-text="message"></p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light flex-fill" x-on:click="open = false">{{ __('auth.cancel') }}</button>
                        <button type="button" class="btn flex-fill odin-confirm__ok" :class="'odin-confirm__ok--' + tone" x-ref="ok"
                            x-on:click="open = false; run && run()" x-text="ok"></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show" x-show="open" x-cloak></div>
</div>
