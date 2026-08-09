import Quill from 'quill';
import 'quill/dist/quill.snow.css';

/**
 * Register Alpine rich-text editors (Quill) used on admin question forms.
 *
 * @param {typeof import('alpinejs').default} Alpine
 */
export function registerRichEditor(Alpine) {
    Alpine.data('richEditor', (initialHtml = '', uploadUrl = '') => ({
        quill: null,

        init() {
            const toolbar = [
                [{ header: [2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link', 'image'],
                ['clean'],
            ];

            this.quill = new Quill(this.$refs.surface, {
                theme: 'snow',
                placeholder: this.$refs.surface?.dataset?.placeholder || '',
                modules: { toolbar },
            });

            if (initialHtml && initialHtml.trim() !== '') {
                this.quill.root.innerHTML = initialHtml;
            }

            this.syncInput();
            this.quill.on('text-change', () => this.syncInput());

            const toolbarModule = this.quill.getModule('toolbar');
            toolbarModule.addHandler('image', () => this.uploadImage());

            this.$el.closest('form')?.addEventListener('submit', () => this.syncInput());
        },

        syncInput() {
            if (! this.$refs.input || ! this.quill) {
                return;
            }

            let html = this.quill.root.innerHTML.trim();
            if (html === '<p><br></p>' || html === '<p></p>') {
                html = '';
            }

            this.$refs.input.value = html;
        },

        async uploadImage() {
            if (! uploadUrl) {
                return;
            }

            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/png,image/jpeg,image/gif,image/webp';
            input.click();

            input.onchange = async () => {
                const file = input.files?.[0];
                if (! file || ! this.quill) {
                    return;
                }

                const body = new FormData();
                body.append('image', file);

                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                try {
                    const response = await fetch(uploadUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            Accept: 'application/json',
                        },
                        body,
                        credentials: 'same-origin',
                    });

                    if (! response.ok) {
                        throw new Error('Upload failed');
                    }

                    const data = await response.json();
                    const range = this.quill.getSelection(true) || { index: this.quill.getLength(), length: 0 };
                    this.quill.insertEmbed(range.index, 'image', data.url, 'user');
                    this.quill.setSelection(range.index + 1, 0, 'silent');
                    this.syncInput();
                } catch (error) {
                    console.error(error);
                    window.alert('Không tải được ảnh. Thử lại với file ≤ 5MB (jpg/png/gif/webp).');
                }
            };
        },
    }));
}
