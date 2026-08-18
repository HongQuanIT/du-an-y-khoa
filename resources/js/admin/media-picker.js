/**
 * Media picker + image slot (Alpine) for admin CMS / library.
 *
 * @param {typeof import('alpinejs').default} Alpine
 */
export function registerMediaPicker(Alpine) {
    Alpine.data('mediaPicker', () => ({
        open: false,
        tab: 'library',
        q: '',
        items: [],
        selected: null,
        page: 1,
        lastPage: 1,
        loading: false,
        dragging: false,
        error: '',
        uploads: [],
        accept: 'image',
        callback: null,
        externalUrl: '',
        externalAlt: '',
        importLocal: false,
        submittingUrl: false,
        externalPreviewError: false,

        get listUrl() {
            return document.querySelector('meta[name="media-items-url"]')?.content || '';
        },

        get uploadUrl() {
            return document.querySelector('meta[name="media-upload-url"]')?.content || '';
        },

        get fromUrl() {
            return document.querySelector('meta[name="media-from-url"]')?.content || '';
        },

        get csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        openFromEvent(event) {
            const detail = event.detail || {};
            this.accept = detail.accept || 'image';
            this.callback = detail.onSelect || window.__mediaPickerOnSelect || null;
            this.tab = detail.mode === 'upload' || detail.mode === 'url' ? detail.mode : 'library';
            this.selected = null;
            this.error = '';
            this.externalUrl = '';
            this.externalAlt = '';
            this.importLocal = false;
            this.externalPreviewError = false;
            this.open = true;
            this.load(true);
        },

        close() {
            this.open = false;
            this.callback = null;
            window.__mediaPickerOnSelect = null;
        },

        confirm() {
            if (!this.selected) return;
            if (typeof this.callback === 'function') {
                this.callback(this.selected);
                this.close();
                return;
            }
            const template = document.querySelector('meta[name="media-show-url-template"]')?.content;
            if (template && this.selected.id) {
                window.location = template.replace('__ID__', String(this.selected.id));
                return;
            }
            this.close();
        },

        async load(reset = false) {
            if (!this.listUrl) return;
            if (reset) {
                this.page = 1;
                this.items = [];
            }
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    page: String(this.page),
                    q: this.q,
                    type: this.accept === 'all' ? 'all' : this.accept,
                    ready: '1',
                });
                const response = await fetch(`${this.listUrl}?${params}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) throw new Error('Không tải được thư viện.');
                const json = await response.json();
                const rows = json.data || [];
                this.items = reset ? rows : this.items.concat(rows);
                this.lastPage = json.meta?.last_page || 1;
            } catch (e) {
                this.error = e.message || 'Lỗi tải thư viện.';
            } finally {
                this.loading = false;
            }
        },

        loadMore() {
            if (this.page >= this.lastPage) return;
            this.page += 1;
            this.load(false);
        },

        async uploadFiles(fileList) {
            const files = Array.from(fileList || []);
            this.error = '';
            for (const file of files) {
                await this.uploadOne(file);
            }
        },

        async uploadOne(file) {
            const row = { name: file.name, status: 'Đang tải…' };
            this.uploads = [row, ...this.uploads];

            const body = new FormData();
            body.append('file', file);

            try {
                const response = await fetch(this.uploadUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrf,
                        Accept: 'application/json',
                    },
                    body,
                });
                const json = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const message = json.message || json.errors?.file?.[0] || 'Tải lên thất bại.';
                    throw new Error(message);
                }
                const item = json.data;
                row.status = item.ready ? 'Sẵn sàng' : 'Đang xử lý';
                if (item) {
                    this.selected = item;
                    this.items = [item, ...this.items.filter((rowItem) => rowItem.id !== item.id)];
                    if (typeof this.callback === 'function') {
                        this.confirm();
                    } else {
                        this.tab = 'library';
                    }
                }
            } catch (e) {
                row.status = e.message || 'Lỗi';
                this.error = row.status;
            }
        },

        async submitExternalUrl() {
            if (!this.fromUrl || !this.externalUrl) return;
            this.error = '';
            this.submittingUrl = true;
            try {
                const response = await fetch(this.fromUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrf,
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        url: this.externalUrl,
                        alt: this.externalAlt,
                        import: this.importLocal,
                    }),
                });
                const json = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(json.message || json.errors?.url?.[0] || 'Không thêm được URL.');
                }
                const item = json.data;
                this.selected = item;
                this.items = [item, ...this.items.filter((rowItem) => rowItem.id !== item.id)];
                if (typeof this.callback === 'function') {
                    this.confirm();
                } else {
                    this.tab = 'library';
                }
            } catch (e) {
                this.error = e.message || 'Lỗi';
            } finally {
                this.submittingUrl = false;
            }
        },
    }));

    Alpine.data('mediaImageSlot', (initial = {}) => ({
        mediaId: initial.mediaId || '',
        url: initial.url || '',
        alt: initial.alt || '',
        accept: initial.accept || 'image',
        dragging: false,

        openPicker(mode = 'library') {
            window.__mediaPickerOnSelect = (item) => this.apply(item);
            window.dispatchEvent(new CustomEvent('media-picker:open', {
                detail: { accept: this.accept, mode },
            }));
        },

        apply(item) {
            this.mediaId = item.id;
            this.url = item.url || item.thumb_url || '';
            if (!this.alt && item.alt) {
                this.alt = item.alt;
            }
        },

        clear() {
            this.mediaId = '';
            this.url = '';
        },

        onDrop(event) {
            this.dragging = false;
            const file = event.dataTransfer?.files?.[0];
            if (!file) return;
            this.uploadDirect(file);
        },

        async uploadDirect(file) {
            const uploadUrl = document.querySelector('meta[name="media-upload-url"]')?.content;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            if (!uploadUrl) {
                this.openPicker('upload');
                return;
            }

            const body = new FormData();
            body.append('file', file);
            const response = await fetch(uploadUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body,
            });
            const json = await response.json().catch(() => ({}));
            if (response.ok && json.data) {
                this.apply(json.data);
            }
        },
    }));
}
