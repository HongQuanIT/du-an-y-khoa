const FULL_TOOLS = [
    { cmd: 'formatBlock', value: 'h2', label: 'H2', title: 'Tiêu đề 2' },
    { cmd: 'formatBlock', value: 'h3', label: 'H3', title: 'Tiêu đề 3' },
    { cmd: 'bold', icon: 'format_bold', title: 'Đậm' },
    { cmd: 'italic', icon: 'format_italic', title: 'Nghiêng' },
    { cmd: 'underline', icon: 'format_underlined', title: 'Gạch chân' },
    { cmd: 'insertOrderedList', icon: 'format_list_numbered', title: 'Danh sách số' },
    { cmd: 'insertUnorderedList', icon: 'format_list_bulleted', title: 'Danh sách chấm' },
    { cmd: 'createLink', icon: 'link', title: 'Liên kết' },
    { cmd: 'image', icon: 'image', title: 'Chèn ảnh' },
    { cmd: 'removeFormat', icon: 'format_clear', title: 'Xóa định dạng' },
];

const MINI_TOOLS = [
    { cmd: 'bold', icon: 'format_bold', title: 'Đậm' },
    { cmd: 'italic', icon: 'format_italic', title: 'Nghiêng' },
    { cmd: 'createLink', icon: 'link', title: 'Liên kết' },
    { cmd: 'image', icon: 'image', title: 'Chèn ảnh' },
    { cmd: 'removeFormat', icon: 'format_clear', title: 'Xóa định dạng' },
];

/**
 * Normalize empty editor HTML to an empty string for form submit.
 *
 * @param {string} html
 * @returns {string}
 */
function normalizeEditorHtml(html) {
    const trimmed = (html || '').trim();

    if (
        trimmed === ''
        || trimmed === '<br>'
        || trimmed === '<br/>'
        || trimmed === '<p><br></p>'
        || trimmed === '<p></p>'
        || trimmed === '<div><br></div>'
        || trimmed === '<div></div>'
    ) {
        return '';
    }

    return trimmed;
}

/**
 * @param {HTMLElement} surface
 */
function toggleBlankState(surface) {
    surface.classList.toggle('is-blank', normalizeEditorHtml(surface.innerHTML) === '');
}

/**
 * @param {HTMLElement} surface
 * @param {string} html
 */
function insertHtmlAtCursor(surface, html) {
    surface.focus();

    const selection = window.getSelection();
    if (! selection || selection.rangeCount === 0 || ! surface.contains(selection.anchorNode)) {
        surface.insertAdjacentHTML('beforeend', html);
        return;
    }

    const range = selection.getRangeAt(0);
    range.deleteContents();
    const fragment = range.createContextualFragment(html);
    range.insertNode(fragment);
    range.collapse(false);
    selection.removeAllRanges();
    selection.addRange(range);
}

/**
 * @param {HTMLElement} surface
 * @param {string} uploadUrl
 * @param {File} [file]
 */
async function uploadEditorImage(surface, uploadUrl, file = null) {
    if (! uploadUrl) {
        return;
    }

    if (! file) {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/png,image/jpeg,image/gif,image/webp';
        input.click();
        file = await new Promise((resolve) => {
            input.onchange = () => resolve(input.files?.[0] ?? null);
        });
    }

    if (! file) {
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
        if (! data.url) {
            throw new Error('Upload failed');
        }

        insertHtmlAtCursor(surface, `<img src="${data.url}" alt="">`);
    } catch (error) {
        console.error(error);
        window.alert('Không tải được ảnh. Thử lại với file ≤ 5MB (jpg/png/gif/webp).');
    }
}

/**
 * @param {HTMLElement} toolbar
 * @param {Array<{cmd: string, value?: string, icon?: string, label?: string, title: string}>} tools
 * @param {(cmd: string, value?: string) => void} onCommand
 */
function renderToolbar(toolbar, tools, onCommand) {
    toolbar.replaceChildren();
    toolbar.setAttribute('role', 'toolbar');

    tools.forEach((tool) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'admin-rich-toolbar__btn';
        button.title = tool.title;
        button.setAttribute('aria-label', tool.title);
        button.dataset.command = tool.cmd;
        if (tool.value) {
            button.dataset.value = tool.value;
        }

        if (tool.icon) {
            const icon = document.createElement('span');
            icon.className = 'material-symbols-outlined';
            icon.setAttribute('aria-hidden', 'true');
            icon.textContent = tool.icon;
            button.append(icon);
        } else {
            button.textContent = tool.label || tool.cmd;
        }

        button.addEventListener('click', () => onCommand(tool.cmd, tool.value));
        toolbar.append(button);
    });

    toolbar.addEventListener('mousedown', (event) => {
        event.preventDefault();
    });
}

/**
 * Native contenteditable editor — copy / paste / IME / format use the browser.
 *
 * @param {HTMLElement} host
 * @param {{
 *   html?: string,
 *   placeholder?: string,
 *   uploadUrl?: string,
 *   mini?: boolean,
 *   onChange?: (html: string) => void,
 * }} [options]
 */
export function mountAdminEditor(host, options = {}) {
    const uploadUrl = options.uploadUrl || '';
    const onChange = typeof options.onChange === 'function' ? options.onChange : null;

    let surface = host.querySelector('[data-editor-surface]');
    if (! surface) {
        surface = host;
    }

    let toolbar = host.querySelector('[data-editor-toolbar]');
    if (! toolbar) {
        toolbar = document.createElement('div');
        toolbar.setAttribute('data-editor-toolbar', '');
        toolbar.className = 'admin-rich-toolbar';
        surface.before(toolbar);
    }

    surface.contentEditable = 'true';
    surface.spellcheck = true;
    if (options.placeholder) {
        surface.dataset.placeholder = options.placeholder;
    }

    if (options.html && normalizeEditorHtml(surface.innerHTML) === '') {
        surface.innerHTML = options.html;
    }

    try {
        document.execCommand('defaultParagraphSeparator', false, 'p');
    } catch (_) {
        // Safari may ignore this; Enter still inserts a line break.
    }

    const emit = () => {
        toggleBlankState(surface);
        onChange?.(normalizeEditorHtml(surface.innerHTML));
    };

    const run = (cmd, value = null) => {
        surface.focus();

        if (cmd === 'createLink') {
            const url = window.prompt('Nhập URL:', 'https://');
            if (! url) {
                return;
            }
            document.execCommand('createLink', false, url);
        } else if (cmd === 'image') {
            uploadEditorImage(surface, uploadUrl).then(emit);
            return;
        } else if (cmd === 'formatBlock') {
            document.execCommand('formatBlock', false, value || 'p');
        } else {
            document.execCommand(cmd, false, value);
        }

        emit();
    };

    renderToolbar(toolbar, options.mini ? MINI_TOOLS : FULL_TOOLS, run);

    surface.addEventListener('input', emit);
    surface.addEventListener('blur', emit);

    surface.addEventListener('paste', (event) => {
        const items = event.clipboardData?.items;
        if (! items || ! uploadUrl) {
            return;
        }

        for (const item of items) {
            if (item.type.startsWith('image/')) {
                const file = item.getAsFile();
                if (file) {
                    event.preventDefault();
                    uploadEditorImage(surface, uploadUrl, file).then(emit);
                }
                return;
            }
        }
    });

    toggleBlankState(surface);
    emit();

    return {
        surface,
        getHtml: () => normalizeEditorHtml(surface.innerHTML),
        insertHtml(html) {
            insertHtmlAtCursor(surface, html);
            emit();
        },
    };
}

window.mountAdminEditor = mountAdminEditor;

/**
 * Register Alpine rich-text editors used on admin question forms.
 *
 * @param {typeof import('alpinejs').default} Alpine
 */
export function registerRichEditor(Alpine) {
    Alpine.data('questionImageUploader', (initialPath = '', initialUrl = '', uploadUrl = '', csrf = '') => ({
        imagePath: initialPath || '',
        imageUrl: initialUrl || '',
        previewObjectUrl: '',
        uploading: false,
        error: '',
        isDragging: false,

        chooseFile() {
            this.error = '';
            this.$refs.fileInput?.click();
        },

        async upload(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (! file) {
                return;
            }
            await this.doUpload(file);
        },

        handleDrop(event) {
            const file = event.dataTransfer?.files?.[0];
            if (! file || ! file.type.startsWith('image/')) {
                return;
            }
            this.doUpload(file);
        },

        handlePaste(event) {
            const active = document.activeElement;
            if (active && (
                active.tagName === 'INPUT'
                || active.tagName === 'TEXTAREA'
                || active.isContentEditable
                || active.closest?.('.admin-rich-surface')
                || active.closest?.('.admin-rich-editor')
            )) {
                return;
            }

            const tag = event.target?.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || event.target?.isContentEditable || event.target?.closest?.('.admin-rich-surface')) {
                return;
            }

            const items = event.clipboardData?.items;
            if (! items) {
                return;
            }

            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                if (item.type.indexOf('image/') !== -1) {
                    const file = item.getAsFile();
                    if (file) {
                        this.doUpload(file);
                        event.preventDefault();
                        return;
                    }
                }
            }
        },

        async doUpload(file) {
            this.error = '';
            this.uploading = true;

            if (this.previewObjectUrl) {
                URL.revokeObjectURL(this.previewObjectUrl);
            }
            this.previewObjectUrl = URL.createObjectURL(file);
            this.imageUrl = this.previewObjectUrl;

            try {
                const body = new FormData();
                body.append('image', file);

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
                    throw new Error('Không thể tải ảnh lên.');
                }

                const data = await response.json();
                this.imagePath = data.path || '';
                this.imageUrl = data.url || this.previewObjectUrl;
                this.$refs.pathInput.value = this.imagePath;
            } catch (error) {
                this.error = error?.message || 'Không thể tải ảnh lên.';
                this.imageUrl = this.previewObjectUrl;
            } finally {
                this.uploading = false;
            }
        },

        remove() {
            if (this.previewObjectUrl) {
                URL.revokeObjectURL(this.previewObjectUrl);
                this.previewObjectUrl = '';
            }
            this.imagePath = '';
            this.imageUrl = '';
            this.$refs.pathInput.value = '';
        },
    }));

    Alpine.data('richEditor', (initialHtml = '', uploadUrl = '') => ({
        editor: null,

        init() {
            this.editor = mountAdminEditor(this.$refs.shell, {
                html: initialHtml,
                placeholder: this.$refs.surface?.dataset?.placeholder || '',
                uploadUrl,
                onChange: (html) => {
                    if (this.$refs.input) {
                        this.$refs.input.value = html;
                    }
                },
            });

            this.syncInput();
            this.$el.closest('form')?.addEventListener('submit', () => this.syncInput());
        },

        syncInput() {
            if (! this.$refs.input || ! this.editor) {
                return;
            }

            this.$refs.input.value = this.editor.getHtml();
        },
    }));
}
