<div id="system-dialog" hidden class="fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div data-system-dialog-backdrop class="absolute inset-0 bg-on-surface/40"></div>
    <section data-system-dialog-panel class="relative w-full max-w-md rounded-2xl border border-outline-variant bg-surface p-5 shadow-2xl"
        role="alertdialog" aria-modal="true" aria-labelledby="system-dialog-title">
        <h2 id="system-dialog-title" data-system-dialog-title class="font-headline-sm font-bold text-on-surface"></h2>
        <p data-system-dialog-message class="mt-2 whitespace-pre-line text-sm leading-6 text-on-surface-variant"></p>
        <label data-system-dialog-input-wrap class="mt-4 hidden">
            <span data-system-dialog-input-label class="mb-1.5 block text-sm font-semibold text-on-surface"></span>
            <input data-system-dialog-input type="text" class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
        </label>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" data-system-dialog-cancel
                class="inline-flex h-10 items-center justify-center rounded-xl border border-outline-variant px-4 font-semibold text-on-surface-variant hover:bg-surface-container-low">
                Hủy
            </button>
            <button type="button" data-system-dialog-confirm
                class="inline-flex h-10 items-center justify-center rounded-xl bg-primary px-4 font-semibold text-on-primary hover:bg-primary/90">
                Xác nhận
            </button>
        </div>
    </section>
</div>

<script>
    (() => {
        if (window.__medlearnSystemDialogsReady) return;
        window.__medlearnSystemDialogsReady = true;

        const dialog = document.getElementById('system-dialog');
        const title = dialog?.querySelector('[data-system-dialog-title]');
        const message = dialog?.querySelector('[data-system-dialog-message]');
        const inputWrap = dialog?.querySelector('[data-system-dialog-input-wrap]');
        const inputLabel = dialog?.querySelector('[data-system-dialog-input-label]');
        const input = dialog?.querySelector('[data-system-dialog-input]');
        const cancel = dialog?.querySelector('[data-system-dialog-cancel]');
        const confirm = dialog?.querySelector('[data-system-dialog-confirm]');
        const backdrop = dialog?.querySelector('[data-system-dialog-backdrop]');
        let resolveDialog = null;
        let previousFocus = null;
        let mode = null;
        let pendingSubmit = null;

        const close = (value = false) => {
            if (! dialog || dialog.hidden) return;

            dialog.hidden = true;
            const resolve = resolveDialog;
            resolveDialog = null;
            mode = null;
            resolve?.(value);
            previousFocus?.focus?.();
        };

        const open = ({ titleText, messageText, type = 'confirm', inputLabelText = '', inputValue = '' }) => new Promise((resolve) => {
            if (! dialog) {
                resolve(type === 'notice' ? undefined : false);
                return;
            }

            previousFocus = document.activeElement;
            resolveDialog = resolve;
            mode = type;
            title.textContent = titleText;
            message.textContent = messageText;
            inputWrap.classList.toggle('hidden', type !== 'prompt');
            inputLabel.textContent = inputLabelText;
            input.value = inputValue;
            cancel.classList.toggle('hidden', type === 'notice');
            confirm.textContent = type === 'notice' ? 'Đã hiểu' : 'Xác nhận';
            dialog.hidden = false;
            requestAnimationFrame(() => (type === 'prompt' ? input : confirm)?.focus());
        });

        window.appConfirm = ({ title: titleText = 'Xác nhận thao tác?', message: messageText = 'Bạn có chắc chắn muốn tiếp tục?' } = {}) => open({
            titleText,
            messageText,
        });

        window.appNotify = (messageText, titleText = 'Có lỗi xảy ra') => open({
            titleText,
            messageText: String(messageText || 'Vui lòng thử lại.'),
            type: 'notice',
        });

        window.appPrompt = ({ title: titleText, message: messageText = '', label = '', value = '' } = {}) => open({
            titleText: titleText || 'Nhập thông tin',
            messageText,
            type: 'prompt',
            inputLabelText: label,
            inputValue: value,
        });

        cancel?.addEventListener('click', () => close(false));
        backdrop?.addEventListener('click', () => close(false));
        confirm?.addEventListener('click', () => close(mode === 'prompt' ? input.value : true));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && ! dialog?.hidden) close(false);
            if (event.key === 'Enter' && mode === 'prompt' && document.activeElement === input) close(input.value);
        });

        document.addEventListener('submit', (event) => {
            pendingSubmit = { form: event.target, submitter: event.submitter };
            queueMicrotask(() => { pendingSubmit = null; });
        }, true);

        window.confirm = (messageText) => {
            const active = document.activeElement;
            const form = pendingSubmit?.form || active?.form || active?.closest?.('form');
            const submitter = pendingSubmit?.submitter || (active?.form === form ? active : null);

            if (! form) {
                window.appNotify(String(messageText), 'Cần xác nhận thao tác');
                return false;
            }

            if (form.dataset.systemConfirmBypass === 'true') {
                delete form.dataset.systemConfirmBypass;
                return true;
            }

            window.appConfirm({ message: String(messageText) }).then((accepted) => {
                if (! accepted) return;

                form.dataset.systemConfirmBypass = 'true';
                form.requestSubmit(submitter instanceof HTMLElement ? submitter : undefined);
            });

            return false;
        };

        window.alert = (messageText) => window.appNotify(String(messageText));
    })();
</script>
