<div wire:ignore x-data="{
        open: false, loading: false, html: '', params: {}, pages: 1, request: 0,
        show(params) {
            if (params.url) {
                window.location.assign(params.url);
                return;
            }
            this.params = params;
            this.pages = 1;
            this.html = '';
            this.open = true;
            this.load();
        },
        more() {
            this.pages++;
            this.load();
        },
        async load() {
            const request = ++this.request;
            this.loading = true;
            try {
                const html = await $wire.drillHtml(this.params, this.pages);
                if (request === this.request) this.html = html;
            } finally {
                if (request === this.request) this.loading = false;
            }
        },
    }" x-on:report-drill.window="show($event.detail)" x-on:keydown.escape.window="open = false">
    <template x-teleport="body">
        <div class="ld-modal vc rp-portal" x-show="open" style="display: none">
            <div class="ld-modal__backdrop" x-on:click="open = false"></div>
            <div class="ld-modal__dialog dr" role="dialog" aria-modal="true" aria-label="{{ __('reports.drill.hint') }}">
                <p class="rp-empty rp-empty--inline" x-show="loading && ! html">{{ __('reports.loading') }}</p>
                <div class="dr__content" x-html="html"></div>
            </div>
        </div>
    </template>
</div>
