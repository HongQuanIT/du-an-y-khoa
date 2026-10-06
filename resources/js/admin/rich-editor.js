import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const Delta = Quill.import('delta');
const BaseImage = Quill.import('formats/image');

class PositionedImage extends BaseImage {
    static formats(domNode) {
        const formats = super.formats(domNode);
        const alignment = domNode.getAttribute('data-align');
        if (['center', 'right'].includes(alignment)) {
            formats.imageAlign = alignment;
        }

        return formats;
    }

    format(name, value) {
        if (name === 'imageAlign') {
            if (['center', 'right'].includes(value)) {
                this.domNode.setAttribute('data-align', value);
            } else {
                this.domNode.removeAttribute('data-align');
            }
            return;
        }

        super.format(name, value);
    }
}

Quill.register(PositionedImage, true);

// Expose Quill globally so inline x-init scripts (e.g. inside x-for) can use it.
window.Quill = Quill;

/**
 * Quill toolbar buttons must be type=button inside <form>, otherwise the browser
 * treats them as submit. Quill 2 already sets type=button; keep as defense in depth.
 * Also prevent mousedown default so the editor selection is not collapsed before format.
 *
 * @param {import('quill').default} quill
 */
function pinQuillToolbarButtons(quill) {
    const toolbar = quill?.getModule?.('toolbar');
    const container = toolbar?.container;
    if (! container) {
        return;
    }

    container.querySelectorAll('button').forEach((button) => {
        button.setAttribute('type', 'button');
    });

    if (! container.dataset.medlearnToolbarPinned) {
        container.dataset.medlearnToolbarPinned = '1';
        // Preserve selection when clicking toolbar (Quill also does this; keep as fallback).
        container.addEventListener('mousedown', (event) => {
            const target = event.target;
            if (! (target instanceof Element)) {
                return;
            }
            if (target.closest('button, .ql-picker-label')) {
                event.preventDefault();
            }
        });
    }
}

window.pinQuillToolbarButtons = pinQuillToolbarButtons;

const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

async function uploadRichEditorImage(uploadUrl, file) {
    if (! ACCEPTED_IMAGE_TYPES.includes(file.type)) {
        throw new Error('Chỉ chấp nhận ảnh JPG, PNG, GIF hoặc WebP.');
    }
    if (file.size > MAX_IMAGE_BYTES) {
        throw new Error('Ảnh không được vượt quá 5MB.');
    }

    const body = new FormData();
    body.append('image', file);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
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
        const payload = await response.json().catch(() => null);
        throw new Error(payload?.message || 'Không thể tải ảnh lên.');
    }

    const payload = await response.json();
    if (! payload?.url) {
        throw new Error('Máy chủ không trả về đường dẫn ảnh.');
    }

    return payload;
}

/**
 * Add upload by picker/drop/paste and an accessible resize control to a Quill editor.
 * Width is stored on the image element so it survives HTML save/restore and Quill reloads.
 */
function enhanceQuillImages(quill, { uploadUrl = '', onChange = () => {} } = {}) {
    if (! quill?.root || quill.root.dataset.medlearnImagesEnhanced === '1') {
        return;
    }

    const root = quill.root;
    const shell = quill.container.closest('.admin-rich-editor') || quill.container.parentElement;
    root.dataset.medlearnImagesEnhanced = '1';
    shell?.classList.add('rich-editor-image-shell');

    let selectedImage = null;
    let lastRange = null;
    let uploading = false;

    const resizer = document.createElement('div');
    resizer.className = 'rich-editor-image-resizer';
    resizer.hidden = true;
    resizer.innerHTML = `
        <span class="rich-editor-image-size" aria-live="polite"></span>
        <div class="rich-editor-image-actions" role="toolbar" aria-label="Căn chỉnh ảnh">
            <button type="button" class="rich-editor-image-reset" title="Đặt lại kích thước ảnh">Đặt lại</button>
            <button type="button" class="rich-editor-image-align" data-image-align="left" aria-label="Căn ảnh sang trái" title="Căn trái">⇤</button>
            <button type="button" class="rich-editor-image-align" data-image-align="center" aria-label="Căn ảnh vào giữa" title="Căn giữa">↔</button>
            <button type="button" class="rich-editor-image-align" data-image-align="right" aria-label="Căn ảnh sang phải" title="Căn phải">⇥</button>
        </div>
        <button type="button" class="rich-editor-image-handle is-top-left" data-resize-direction="left" aria-label="Kéo góc trên trái để thay đổi kích thước ảnh" title="Kéo để thay đổi kích thước"></button>
        <button type="button" class="rich-editor-image-handle is-top-right" data-resize-direction="right" aria-label="Kéo góc trên phải để thay đổi kích thước ảnh" title="Kéo để thay đổi kích thước"></button>
        <button type="button" class="rich-editor-image-handle is-bottom-left" data-resize-direction="left" aria-label="Kéo góc dưới trái để thay đổi kích thước ảnh" title="Kéo để thay đổi kích thước"></button>
        <button type="button" class="rich-editor-image-handle is-bottom-right" data-resize-direction="right" aria-label="Kéo góc dưới phải để thay đổi kích thước ảnh" title="Kéo để thay đổi kích thước"></button>
    `;
    shell?.appendChild(resizer);

    const sizeLabel = resizer.querySelector('.rich-editor-image-size');
    const resetButton = resizer.querySelector('.rich-editor-image-reset');
    const alignButtons = [...resizer.querySelectorAll('[data-image-align]')];
    const handles = [...resizer.querySelectorAll('.rich-editor-image-handle')];

    const sync = () => {
        quill.update('user');
        onChange();
    };

    const positionResizer = () => {
        if (! selectedImage || ! selectedImage.isConnected || ! shell) {
            resizer.hidden = true;
            return;
        }

        const imageRect = selectedImage.getBoundingClientRect();
        const shellRect = shell.getBoundingClientRect();
        resizer.style.left = `${imageRect.left - shellRect.left + shell.scrollLeft}px`;
        resizer.style.top = `${imageRect.top - shellRect.top + shell.scrollTop}px`;
        resizer.style.width = `${imageRect.width}px`;
        resizer.style.height = `${imageRect.height}px`;
        const width = Math.round(imageRect.width);
        const height = Math.round(imageRect.height);
        sizeLabel.textContent = `${width} × ${height} px`;
        sizeLabel.setAttribute('aria-label', `Rộng ${width} pixel, cao ${height} pixel`);
        const currentAlign = selectedImage.dataset.align || 'left';
        alignButtons.forEach((button) => {
            const active = button.dataset.imageAlign === currentAlign;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        resizer.hidden = false;
    };

    const positionSizeLabelNearPointer = (event) => {
        sizeLabel.classList.add('is-following-pointer');
        sizeLabel.style.left = `${event.clientX + 12}px`;
        sizeLabel.style.top = `${event.clientY + 12}px`;
    };

    const resetSizeLabelPosition = () => {
        sizeLabel.classList.remove('is-following-pointer');
        sizeLabel.style.left = '';
        sizeLabel.style.top = '';
    };

    const selectImage = (image) => {
        if (selectedImage) {
            selectedImage.classList.remove('is-selected-for-resize');
        }
        selectedImage = image instanceof HTMLImageElement ? image : null;
        if (selectedImage) {
            selectedImage.classList.add('is-selected-for-resize');
            const imageBlot = Quill.find(selectedImage);
            if (imageBlot) {
                quill.setSelection(quill.getIndex(imageBlot), 1, 'silent');
            }
        }
        positionResizer();
    };

    const applyAlignment = (alignment) => {
        if (! selectedImage || ! ['left', 'center', 'right'].includes(alignment)) return;
        const imageBlot = Quill.find(selectedImage);
        if (! imageBlot) return;
        const imageIndex = quill.getIndex(imageBlot);
        quill.formatText(imageIndex, 1, 'imageAlign', alignment === 'left' ? false : alignment, 'user');
        sync();
        requestAnimationFrame(positionResizer);
    };

    alignButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            applyAlignment(button.dataset.imageAlign || 'left');
        });
    });

    const applyWidth = (width, shouldSync = true) => {
        if (! selectedImage) return;
        const maxWidth = Math.max(80, root.clientWidth);
        const nextWidth = Math.min(maxWidth, Math.max(80, Math.round(width)));
        selectedImage.setAttribute('width', String(nextWidth));
        selectedImage.removeAttribute('height');
        selectedImage.style.width = '';
        selectedImage.style.height = '';
        positionResizer();
        if (shouldSync) {
            sync();
        }
    };

    resetButton?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (! selectedImage) return;
        selectedImage.removeAttribute('width');
        selectedImage.removeAttribute('height');
        selectedImage.style.width = '';
        selectedImage.style.height = '';
        positionResizer();
        sync();
    });

    handles.forEach((handle) => handle.addEventListener('pointerdown', (event) => {
        if (! selectedImage) return;
        event.preventDefault();
        event.stopPropagation();
        const startX = event.clientX;
        const startWidth = selectedImage.getBoundingClientRect().width;
        const direction = handle.dataset.resizeDirection === 'left' ? -1 : 1;
        handle.setPointerCapture?.(event.pointerId);

        const move = (moveEvent) => {
            applyWidth(startWidth + ((moveEvent.clientX - startX) * direction), false);
            positionSizeLabelNearPointer(moveEvent);
        };
        const finish = () => {
            handle.removeEventListener('pointermove', move);
            handle.removeEventListener('pointerup', finish);
            handle.removeEventListener('pointercancel', finish);
            resetSizeLabelPosition();
            sync();
        };
        handle.addEventListener('pointermove', move);
        handle.addEventListener('pointerup', finish);
        handle.addEventListener('pointercancel', finish);
    }));

    handles.forEach((handle) => handle.addEventListener('keydown', (event) => {
        if (! selectedImage || ! ['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
        event.preventDefault();
        const direction = event.key === 'ArrowRight' ? 1 : -1;
        applyWidth(selectedImage.getBoundingClientRect().width + (direction * (event.shiftKey ? 50 : 10)));
    }));

    const insertionIndex = () => {
        const range = quill.getSelection() || lastRange;
        return range?.index ?? Math.max(0, quill.getLength() - 1);
    };

    const insertFile = async (file, index = insertionIndex()) => {
        if (! uploadUrl || ! file || uploading) return;
        uploading = true;
        shell?.classList.add('is-uploading-image');
        try {
            const payload = await uploadRichEditorImage(uploadUrl, file);
            quill.insertEmbed(index, 'image', payload.url, 'user');
            quill.setSelection(index + 1, 0, 'silent');
            requestAnimationFrame(() => {
                const [leaf] = quill.getLeaf(index);
                const image = leaf?.domNode instanceof HTMLImageElement ? leaf.domNode : null;
                selectImage(image);
                if (image) {
                    // New uploads start at half of the editor width; users can
                    // resize them freely afterwards from any corner.
                    applyWidth(root.clientWidth * 0.5, false);
                }
                sync();
            });
        } catch (error) {
            console.error(error);
            window.alert(error?.message || 'Không tải được ảnh. Vui lòng thử lại.');
        } finally {
            uploading = false;
            shell?.classList.remove('is-uploading-image');
        }
    };

    const chooseImage = () => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/png,image/jpeg,image/gif,image/webp';
        input.addEventListener('change', () => insertFile(input.files?.[0]));
        input.click();
    };

    quill.on('selection-change', (range) => {
        if (range) lastRange = range;
    });
    quill.getModule('toolbar')?.addHandler('image', chooseImage);

    root.addEventListener('click', (event) => {
        selectImage(event.target instanceof HTMLImageElement ? event.target : null);
    });
    root.addEventListener('dragover', (event) => {
        if ([...(event.dataTransfer?.items || [])].some((item) => item.type.startsWith('image/'))) {
            event.preventDefault();
            root.classList.add('is-dragging-image');
        }
    });
    root.addEventListener('dragleave', () => root.classList.remove('is-dragging-image'));
    root.addEventListener('drop', (event) => {
        root.classList.remove('is-dragging-image');
        const file = [...(event.dataTransfer?.files || [])].find((item) => item.type.startsWith('image/'));
        if (! file) return;
        event.preventDefault();
        event.stopPropagation();
        insertFile(file);
    });
    root.addEventListener('paste', (event) => {
        const file = [...(event.clipboardData?.items || [])]
            .find((item) => item.type.startsWith('image/'))
            ?.getAsFile();
        if (! file) return;
        event.preventDefault();
        insertFile(file);
    });
    root.addEventListener('scroll', positionResizer, { passive: true });
    window.addEventListener('resize', positionResizer, { passive: true });
}

window.enhanceQuillImages = enhanceQuillImages;

function annotateInstructorRichImages(root = document) {
    const selector = [
        '.instructor-image-preview img',
        '[data-testid="reviewer-question-preview"] .question-rich-content img',
    ].join(', ');

    root.querySelectorAll?.(selector).forEach((image) => {
        if (image.dataset.medlearnDimensions === '1') return;
        image.dataset.medlearnDimensions = '1';

        const wrapper = document.createElement('span');
        const isReviewerPreview = image.closest('[data-testid="reviewer-question-preview"]');
        wrapper.className = isReviewerPreview
            ? 'reviewer-image-preview-wrap'
            : 'instructor-image-preview-wrap';
        if (['center', 'right'].includes(image.dataset.align)) {
            wrapper.classList.add(`is-${image.dataset.align}`);
        }
        image.parentNode?.insertBefore(wrapper, image);
        wrapper.appendChild(image);

        const label = document.createElement('span');
        label.className = 'instructor-image-dimensions';
        label.setAttribute('aria-live', 'polite');
        wrapper.appendChild(label);

        const update = () => {
            const rect = image.getBoundingClientRect();
            if (rect.width === 0 || rect.height === 0) return;
            const width = Math.round(rect.width);
            const height = Math.round(rect.height);
            label.textContent = `${width} × ${height} px`;
            label.setAttribute('aria-label', `Kích thước ảnh: rộng ${width} pixel, cao ${height} pixel`);
        };

        image.addEventListener('load', update, { once: true });
        if (image.complete) update();
        if (typeof ResizeObserver === 'function') {
            new ResizeObserver(update).observe(image);
        }
    });
}

window.annotateInstructorRichImages = annotateInstructorRichImages;
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => annotateInstructorRichImages(), { once: true });
} else {
    requestAnimationFrame(() => annotateInstructorRichImages());
}

/**
 * Register Alpine rich-text editors (Quill) used on admin / editor question forms.
 *
 * @param {typeof import('alpinejs').default} Alpine
 */
export function registerRichEditor(Alpine) {
    Alpine.data('richEditor', (initialHtml = '', uploadUrl = '') => ({
        lastRange: null,

        editor() {
            return this.$refs.surface?.__quill || null;
        },

        init() {
            if (! this.$refs.surface) {
                console.error('richEditor: missing surface ref');
                return;
            }

            // Avoid double-init (Alpine morph / Livewire / HMR).
            if (this.$refs.surface.__quill || this.$refs.surface.classList.contains('ql-container')) {
                return;
            }

            const toolbar = [
                [{ header: [2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link', 'image'],
                ['clean'],
            ];

            // Quill 2 + Alpine Proxy: never store the instance on reactive `this`.
            const quill = new Quill(this.$refs.surface, {
                theme: 'snow',
                placeholder: this.$refs.surface?.dataset?.placeholder || '',
                modules: { toolbar },
            });
            this.$refs.surface.__quill = quill;
            pinQuillToolbarButtons(quill);

            if (initialHtml && initialHtml.trim() !== '') {
                const paste = quill.clipboard.convert({ html: initialHtml, text: '' });
                quill.setContents(paste, 'silent');
            }

            this.lastRange = null;
            quill.on('selection-change', (range) => {
                if (range) {
                    this.lastRange = range;
                }
            });

            this.syncInput();
            quill.on('text-change', () => this.syncInput());

            const editorEl = quill.root;
            if (editorEl) {
                editorEl.addEventListener('compositionstart', () => {
                    editorEl.classList.remove('ql-blank');
                });
                editorEl.addEventListener('compositionend', () => {
                    const isEmpty = quill.getLength() <= 1;
                    editorEl.classList.toggle('ql-blank', isEmpty);
                });
            }

            const toolbarModule = quill.getModule('toolbar');
            enhanceQuillImages(quill, {
                uploadUrl,
                onChange: () => this.syncInput(),
            });

            this.$el.closest('form')?.addEventListener('submit', () => this.syncInput());
        },

        syncInput() {
            const quill = this.editor();
            if (! this.$refs.input || ! quill) {
                return;
            }

            let html = quill.root.innerHTML.trim();
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
                const quill = this.editor();
                if (! file || ! quill) {
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
                    const range = quill.getSelection(true) || { index: quill.getLength(), length: 0 };
                    quill.insertEmbed(range.index, 'image', data.url, 'user');
                    quill.setSelection(range.index + 1, 0, 'silent');
                    this.syncInput();
                } catch (error) {
                    console.error(error);
                    window.alert('Không tải được ảnh. Thử lại với file ≤ 5MB (jpg/png/gif/webp).');
                }
            };
        },

        insertLabTable() {
            const quill = this.editor();
            if (! quill) return;

            let range = quill.getSelection() || this.lastRange;
            let index = range ? range.index : 0;
            if (quill.getLength() <= 1 || index < 0) {
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
            const paste = quill.clipboard.convert({ html, text: '' });
            quill.updateContents(new Delta().retain(index).concat(paste), 'user');
            this.syncInput();
        },

        createTable(rows = 3, cols = 2) {
            const quill = this.editor();
            if (! quill) return;

            let range = quill.getSelection() || this.lastRange;
            let index = range ? range.index : 0;
            if (quill.getLength() <= 1 || index < 0) {
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

            const paste = quill.clipboard.convert({ html: tableHtml, text: '' });
            quill.updateContents(new Delta().retain(index).concat(paste), 'user');
            this.syncInput();
        },

        async promptCreateTable() {
            const input = await window.appPrompt({
                title: 'Tạo bảng',
                message: 'Nhập số hàng và số cột cho bảng mới.',
                label: 'Định dạng (ví dụ: 3x2, 4x3, 5x2)',
                value: '3x2',
            });
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
