{{-- Popup xác nhận đăng xuất. Mở bằng: data-bs-toggle="modal" data-bs-target="#logoutModal" --}}
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content odin-logout-modal">
            <div class="modal-body text-center p-4">
                <div class="odin-logout-modal__icon mx-auto mb-3">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
                    </svg>
                </div>
                <h2 class="h5 fw-bold mb-2" id="logoutModalLabel">{{ __('auth.logout_title') }}</h2>
                <p class="text-secondary mb-4">{{ __('auth.logout_confirm') }}</p>

                <form method="POST" action="{{ route('logout') }}" class="d-flex gap-2">
                    @csrf
                    <button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">
                        {{ __('auth.cancel') }}
                    </button>
                    <button type="submit" class="btn btn-danger flex-fill">{{ __('auth.logout') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
