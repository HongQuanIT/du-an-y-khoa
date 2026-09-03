import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const Delta = Quill.import('delta');

// Patch Quill internals once so copy & paste NEVER crash across all editors
function patchQuillInternals() {
    try {
        const dummy = document.createElement('div');
        const tempQuill = new Quill(dummy);

        const SelectionProto = tempQuill.selection?.constructor?.prototype;
        if (SelectionProto && !SelectionProto.__medlearnPatched) {
            SelectionProto.__medlearnPatched = true;
            const originalNormalizedToRange = SelectionProto.normalizedToRange;

            SelectionProto.normalizedToRange = function(range) {
                if (!range || !range.start) {
                    const RangeClass = this.savedRange?.constructor || Quill.import('core/selection')?.Range || Object;
                    return new RangeClass(0, 0);
                }
                try {
                    const positions = [[range.start.node, range.start.offset]];
                    if (!range.native?.collapsed && range.end) {
                        positions.push([range.end.node, range.end.offset]);
                    }
                    const indexes = positions.map(position => {
                        const [node, offset] = position;
                        if (!node) return 0;
                        let blot = this.scroll.find(node, true);
                        let curr = node;
                        while (!blot && curr && curr !== this.root) {
                            curr = curr.parentNode;
                            if (curr) {
                                blot = this.scroll.find(curr, true);
                            }
                        }
                        if (!blot) {
                            blot = this.scroll.find(this.root, true);
                        }
                        if (!blot || typeof blot.offset !== 'function') {
                            return 0;
                        }
                        const index = blot.offset(this.scroll);
                        if (offset === 0) {
                            return index;
                        }
                        if (typeof blot.index === 'function') {
                            try {
                                return index + blot.index(node, offset);
                            } catch (_) {
                                return index;
                            }
                        }
                        if (typeof blot.length === 'function') {
                            return index + blot.length();
                        }
                        return index;
                    });
                    const scrollLen = this.scroll?.length?.() ?? 1;
                    const end = Math.min(Math.max(...indexes), Math.max(0, scrollLen - 1));
                    const start = Math.min(end, Math.max(0, ...indexes));
                    const RangeClass = this.savedRange?.constructor || Quill.import('core/selection')?.Range;
                    return new RangeClass(start, Math.max(0, end - start));
                } catch (err) {
                    console.warn('Quill normalizedToRange fallback:', err);
                    if (originalNormalizedToRange) {
                        try {
                            return originalNormalizedToRange.call(this, range);
                        } catch (_) {}
                    }
                    return this.savedRange || { index: 0, length: 0 };
                }
            };
        }

        const ClipboardProto = tempQuill.clipboard?.constructor?.prototype;
        if (ClipboardProto && !ClipboardProto.__medlearnPatched) {
            ClipboardProto.__medlearnPatched = true;

            ClipboardProto.onCaptureCopy = function(e, isCut = false) {
                if (e.defaultPrevented) return;
                try {
                    const rangeTuple = this.quill.selection.getRange();
                    const range = rangeTuple ? rangeTuple[0] : null;
                    if (!range || range.length === 0) {
                        // Let browser native copy handle it!
                        return;
                    }
                    const { html, text } = this.onCopy(range, isCut);
                    if (!text && !html) {
                        // Let browser native copy handle it!
                        return;
                    }
                    e.preventDefault();
                    if (text) e.clipboardData?.setData('text/plain', text);
                    if (html) e.clipboardData?.setData('text/html', html);
                    if (isCut) {
                        this.quill.deleteText(range.index, range.length, 'user');
                    }
                } catch (err) {
                    console.warn('Quill copy fallback to native browser copy:', err);
                }
            };

            ClipboardProto.onCapturePaste = function(e) {
                if (e.defaultPrevented || !this.quill.isEnabled()) return;
                try {
                    let range = null;
                    try {
                        range = this.quill.getSelection(true);
                    } catch (_) {}
                    if (!range) {
                        range = { index: Math.max(0, this.quill.getLength() - 1), length: 0 };
                    }
                    const html = e.clipboardData?.getData('text/html');
                    let text = e.clipboardData?.getData('text/plain');
                    if (!html && !text) {
                        const urlList = e.clipboardData?.getData('text/uri-list');
                        if (urlList) {
                            text = this.normalizeURIList ? this.normalizeURIList(urlList) : urlList;
                        }
                    }
                    const files = Array.from(e.clipboardData?.files || []);
                    if (!html && files.length > 0 && this.quill.uploader) {
                        e.preventDefault();
                        this.quill.uploader.upload(range, files);
                        return;
                    }
                    if (html && files.length > 0 && this.quill.uploader) {
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        if (doc.body.childElementCount === 1 && doc.body.firstElementChild?.tagName === 'IMG') {
                            e.preventDefault();
                            this.quill.uploader.upload(range, files);
                            return;
                        }
                    }
                    e.preventDefault();
                    this.onPaste(range, { html, text });
                } catch (err) {
                    console.warn('Quill paste fallback to plain text insert:', err);
                    try {
                        const text = e.clipboardData?.getData('text/plain');
                        if (text) {
                            e.preventDefault();
                            let range = null;
                            try { range = this.quill.getSelection(); } catch (_) {}
                            const idx = range ? range.index : Math.max(0, this.quill.getLength() - 1);
                            this.quill.insertText(idx, text, 'user');
                            this.quill.setSelection(idx + text.length, 0, 'silent');
                        }
                    } catch (fallbackErr) {
                        console.error('Quill fallback paste failed:', fallbackErr);
                    }
                }
            };
        }
    } catch (e) {
        console.warn('Failed to patch Quill internals:', e);
    }
}

patchQuillInternals();

// Expose Quill globally so inline x-init scripts (e.g. inside x-for) can use it
window.Quill = Quill;

/**
 * Register Alpine rich-text editors (Quill) used on admin question forms.
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
            if (!file) return;
            await this.doUpload(file);
        },

        handleDrop(event) {
            const file = event.dataTransfer?.files?.[0];
            if (!file || !file.type.startsWith('image/')) return;
            this.doUpload(file);
        },

        handlePaste(event) {
            const active = document.activeElement;
            if (active && (
                active.tagName === 'INPUT' ||
                active.tagName === 'TEXTAREA' ||
                active.isContentEditable ||
                active.closest?.('.ql-editor') ||
                active.closest?.('.admin-rich-editor')
            )) {
                return;
            }

            const tag = event.target?.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || event.target?.isContentEditable || event.target?.closest?.('.ql-editor')) {
                return;
            }

            const items = event.clipboardData?.items;
            if (!items) return;

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
                const paste = this.quill.clipboard.convert({ html: initialHtml, text: '' });
                this.quill.setContents(paste, 'silent');
            }

            this.lastRange = null;
            this.quill.on('selection-change', (range) => {
                if (range && range.length > 0) {
                    this.lastRange = range;
                }
            });

            this.syncInput();
            this.quill.on('text-change', () => this.syncInput());

            // Fix: Vietnamese IME fires compositionstart before text-change,
            // so ql-blank class is not removed until blur. Remove it immediately.
            const editorEl = this.$refs.surface?.querySelector('.ql-editor');
            if (editorEl) {
                editorEl.addEventListener('compositionstart', () => {
                    editorEl.classList.remove('ql-blank');
                });
                editorEl.addEventListener('compositionend', () => {
                    // Restore ql-blank if editor is actually empty after composition
                    const isEmpty = this.quill.getLength() <= 1;
                    editorEl.classList.toggle('ql-blank', isEmpty);
                });
            }

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

        insertLabTable() {
            if (! this.quill) return;

            let range = this.quill.getSelection() || this.lastRange;
            let index = range ? range.index : 0;
            if (this.quill.getLength() <= 1 || index < 0) {
                index = 0;
            }

            const html = `
<table style="width: 100%; max-width: 500px; margin: 16px auto; border-collapse: separate; border-spacing: 0; border: 1px solid #cbd5e1; border-radius: 12px; overflow: hidden; background: #ffffff; font-size: 0.875rem;">
  <thead>
    <tr style="background-color: #f8fafc; border-bottom: 1px solid #cbd5e1;">
      <th style="padding: 10px 16px; text-align: left; font-weight: 600; color: #0f172a; border-right: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1;">Xét nghiệm / Chỉ số</th>
      <th style="padding: 10px 16px; text-align: left; font-weight: 600; color: #0f172a; border-bottom: 1px solid #cbd5e1;">Kết quả</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td style="padding: 8px 16px; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; color: #1e293b;"><mark class="ql-hint" data-hint="true">Hemoglobin</mark></td>
      <td style="padding: 8px 16px; border-bottom: 1px solid #f1f5f9; color: #1e293b;"><mark class="ql-hint" data-hint="true">14.5 g/dL</mark></td>
    </tr>
    <tr>
      <td style="padding: 8px 16px; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; color: #1e293b;"><mark class="ql-hint" data-hint="true">Leu</mark>kocyte count</td>
      <td style="padding: 8px 16px; border-bottom: 1px solid #f1f5f9; color: #1e293b;">11,000/mm³</td>
    </tr>
    <tr>
      <td style="padding: 8px 16px 8px 32px; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; color: #475569;">Segmented neutrophils</td>
      <td style="padding: 8px 16px; border-bottom: 1px solid #f1f5f9; color: #1e293b;">54%</td>
    </tr>
    <tr>
      <td style="padding: 8px 16px 8px 32px; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; color: #475569;">Eosinophils</td>
      <td style="padding: 8px 16px; border-bottom: 1px solid #f1f5f9; color: #1e293b;"><mark class="ql-hint" data-hint="true">24%</mark></td>
    </tr>
    <tr>
      <td style="padding: 8px 16px 8px 32px; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; color: #475569;">Lymphocytes</td>
      <td style="padding: 8px 16px; border-bottom: 1px solid #f1f5f9; color: #1e293b;">19%</td>
    </tr>
    <tr>
      <td style="padding: 8px 16px 8px 32px; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; color: #475569;">Monocytes</td>
      <td style="padding: 8px 16px; border-bottom: 1px solid #f1f5f9; color: #1e293b;">3%</td>
    </tr>
    <tr>
      <td style="padding: 8px 16px; border-right: 1px solid #f1f5f9; color: #1e293b;">Platelet count</td>
      <td style="padding: 8px 16px; color: #1e293b;">235,000/mm³</td>
    </tr>
  </tbody>
</table><p><br></p>`;
            const paste = this.quill.clipboard.convert({ html, text: '' });
            this.quill.updateContents(new Delta().retain(index).concat(paste), 'user');
            this.syncInput();
        },

        createTable(rows = 3, cols = 2) {
            if (! this.quill) return;

            let range = this.quill.getSelection() || this.lastRange;
            let index = range ? range.index : 0;
            if (this.quill.getLength() <= 1 || index < 0) {
                index = 0;
            }

            let tableHtml = `<table style="width: 100%; max-width: 540px; margin: 16px auto; border-collapse: separate; border-spacing: 0; border: 1px solid #cbd5e1; border-radius: 12px; overflow: hidden; background: #ffffff; font-size: 0.875rem;"><thead><tr style="background-color: #f8fafc; border-bottom: 1px solid #cbd5e1;">`;

            for (let c = 1; c <= cols; c++) {
                const borderRight = (c < cols) ? 'border-right: 1px solid #cbd5e1;' : '';
                tableHtml += `<th style="padding: 10px 16px; text-align: left; font-weight: 600; color: #0f172a; border-bottom: 1px solid #cbd5e1; ${borderRight}">Tiêu đề ${c}</th>`;
            }
            tableHtml += `</tr></thead><tbody>`;

            for (let r = 1; r <= rows; r++) {
                const borderBottom = (r < rows) ? 'border-bottom: 1px solid #f1f5f9;' : '';
                tableHtml += `<tr>`;
                for (let c = 1; c <= cols; c++) {
                    const borderRight = (c < cols) ? 'border-right: 1px solid #f1f5f9;' : '';
                    tableHtml += `<td style="padding: 8px 16px; color: #1e293b; ${borderBottom} ${borderRight}">Nội dung ${r}.${c}</td>`;
                }
                tableHtml += `</tr>`;
            }
            tableHtml += `</tbody></table><p><br></p>`;

            const paste = this.quill.clipboard.convert({ html: tableHtml, text: '' });
            this.quill.updateContents(new Delta().retain(index).concat(paste), 'user');
            this.syncInput();
        },

        promptCreateTable() {
            const input = window.prompt('Nhập số hàng và số cột (ví dụ: 3x2, 4x3, 5x2):', '3x2');
            if (! input) return;

            const parts = input.toLowerCase().split('x').map(s => parseInt(s.trim(), 10));
            const rows = parts[0] || 3;
            const cols = parts[1] || 2;

            if (isNaN(rows) || isNaN(cols) || rows < 1 || cols < 1) {
                window.alert('Vui lòng nhập định dạng hợp lệ, ví dụ 3x2!');
                return;
            }

            this.createTable(rows, cols);
        },
    }));
}
