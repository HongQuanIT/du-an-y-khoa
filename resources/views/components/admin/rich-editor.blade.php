@props([
    'name',
    'label',
    'value' => '',
    'required' => false,
    'placeholder' => '',
    'uploadUrl' => null,
])

@php
    $uploadUrl = $uploadUrl ?? route(
        request()->routeIs('editor.*') ? 'editor.rich-editor.images' : 'admin.editor.images'
    );
    $initialHtml = old($name, $value) ?? '';
@endphp

{{--
  Quill 2 must NOT live on Alpine reactive state (Proxy breaks blot.offset → selection crash).
  Keep the instance only on the DOM node: surface.__quill
--}}
<div class="space-y-1.5"
    data-testid="rich-editor-{{ $name }}"
    x-data="{
        uploadUrl: @js($uploadUrl),
        initialHtml: @js($initialHtml),
        placeholder: @js($placeholder),
        editor() {
            return this.$refs.surface?.__quill || null;
        },
        sync() {
            const quill = this.editor();
            if (! quill || ! this.$refs.input) return;
            let html = quill.root.innerHTML.trim();
            if (html === '<p><br></p>' || html === '<p></p>') html = '';
            this.$refs.input.value = html;
        },
        async uploadImage() {
            const quill = this.editor();
            if (! this.uploadUrl || ! quill) return;
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/png,image/jpeg,image/gif,image/webp';
            input.click();
            input.onchange = async () => {
                const file = input.files?.[0];
                if (! file) return;
                const body = new FormData();
                body.append('image', file);
                const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                try {
                    const res = await fetch(this.uploadUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                        body,
                        credentials: 'same-origin',
                    });
                    if (! res.ok) throw new Error('upload failed');
                    const data = await res.json();
                    const range = quill.getSelection(true) || { index: quill.getLength(), length: 0 };
                    quill.insertEmbed(range.index, 'image', data.url, 'user');
                    quill.setSelection(range.index + 1, 0, 'silent');
                    this.sync();
                } catch (e) {
                    console.error(e);
                    alert('Không tải được ảnh. Thử lại với file ≤ 5MB (jpg/png/gif/webp).');
                }
            };
        },
        initEditor() {
            if (! window.Quill) {
                console.error('Quill chưa sẵn sàng');
                return;
            }
            const surface = this.$refs.surface;
            if (! surface) return;
            if (surface.__quill) {
                this.sync();
                return;
            }

            // Local (non-reactive) reference — never assign Quill onto `this`.
            const quill = new window.Quill(surface, {
                theme: 'snow',
                placeholder: this.placeholder || '',
                modules: {
                    toolbar: [
                        [{ header: [2, 3, false] }],
                        ['bold', 'italic', 'underline'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['link', 'image'],
                        ['clean'],
                    ],
                },
            });
            surface.__quill = quill;

            if (typeof window.pinQuillToolbarButtons === 'function') {
                window.pinQuillToolbarButtons(quill);
            }
            if (this.initialHtml && String(this.initialHtml).trim() !== '') {
                const paste = quill.clipboard.convert({ html: this.initialHtml, text: '' });
                quill.setContents(paste, 'silent');
            }
            this.sync();
            quill.on('text-change', () => this.sync());
            const ed = quill.root;
            ed.addEventListener('compositionstart', () => ed.classList.remove('ql-blank'));
            ed.addEventListener('compositionend', () => ed.classList.toggle('ql-blank', quill.getLength() <= 1));
            quill.getModule('toolbar').addHandler('image', () => this.uploadImage());
            this.$el.closest('form')?.addEventListener('submit', () => this.sync());
        }
    }"
    x-init="$nextTick(() => initEditor())">
    <label class="mb-1 block font-label-sm text-label-sm text-on-surface-variant" for="{{ $name }}-editor">
        {{ $label }}@if ($required) *@endif
    </label>

    <div class="rounded-xl border border-outline-variant bg-surface admin-rich-editor">
        <div x-ref="surface" id="{{ $name }}-editor" data-placeholder="{{ $placeholder }}"
            class="min-h-[160px] bg-surface font-body-sm text-body-sm text-on-surface"></div>
    </div>

    <input type="hidden" name="{{ $name }}" x-ref="input" @if ($required) data-rich-required="1" @endif>
</div>
