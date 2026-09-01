/**
 * Realtime notification bell + toast over Reverb (private-user.{id}).
 */
export function bootNotifications() {
    const root = document.querySelector('[data-notification-bell]');
    if (!root) {
        return;
    }

    const userId = root.dataset.userId;
    if (!userId) {
        return;
    }

    let unread = Number(
        document.querySelectorAll('[data-notification-list] [data-notification-id].bg-primary-fixed\\/15, [data-notification-list] .bg-primary-fixed\\/15')
            .length,
    );

    // Prefer badge visibility from SSR.
    const badge = root.querySelector('[data-notification-badge]');
    if (badge && !badge.classList.contains('hidden')) {
        unread = Math.max(unread, 1);
    }

    const readAllForm = root.querySelector('[data-notification-read-all]');
    const list = root.querySelector('[data-notification-list]');
    const empty = root.querySelector('[data-notification-empty]');

    const ensureToastHost = () => {
        let host = document.querySelector('[data-notification-toasts]');
        if (!host) {
            host = document.createElement('div');
            host.setAttribute('data-notification-toasts', '');
            host.className =
                'pointer-events-none fixed top-16 right-4 sm:right-6 z-[70] flex w-[min(100vw-2rem,360px)] sm:w-[380px] flex-col gap-2';
            document.body.appendChild(host);
        }
        return host;
    };

    const setUnread = (count) => {
        unread = Math.max(0, count);
        if (badge) {
            badge.classList.toggle('hidden', unread < 1);
        }
        if (readAllForm) {
            readAllForm.classList.toggle('hidden', unread < 1);
        }
    };

    const showToast = (payload) => {
        const host = ensureToastHost();
        const toast = document.createElement('div');
        toast.className =
            'pointer-events-auto rounded-2xl border border-outline-variant bg-surface p-4 shadow-xl ring-1 ring-black/5 transition duration-300';
        
        const actionHtml = payload.action_url
            ? `<a href="${escapeHtml(payload.action_url)}" class="inline-flex items-center gap-1 rounded-lg bg-primary px-3 py-1 text-xs font-bold text-white shadow-sm hover:bg-primary-container hover:text-on-primary-container transition-colors">
                <span>Xem ngay</span>
                <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
               </a>`
            : '';

        toast.innerHTML = `
            <div class="flex items-start justify-between gap-2 pb-1.5">
                <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                    <span class="size-1.5 rounded-full bg-primary animate-ping"></span>
                    Thông báo mới
                </span>
                <button type="button" class="rounded-lg p-1 text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors" data-toast-close aria-label="Đóng">
                    <span class="material-symbols-outlined text-[16px]">close</span>
                </button>
            </div>
            <p class="font-label-md text-label-md font-bold text-on-surface">${escapeHtml(payload.title || 'Thông báo')}</p>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant line-clamp-2">${escapeHtml(payload.body || '')}</p>
            ${actionHtml ? `<div class="mt-2.5 flex justify-end">${actionHtml}</div>` : ''}
        `;

        toast.querySelector('[data-toast-close]')?.addEventListener('click', () => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-6px)';
            window.setTimeout(() => toast.remove(), 200);
        });

        host.prepend(toast);
        window.setTimeout(() => {
            if (toast.isConnected) {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-6px)';
                window.setTimeout(() => toast.remove(), 250);
            }
        }, 7000);
    };

    const prependItem = (payload) => {
        if (!list) {
            return;
        }
        empty?.remove();

        const row = document.createElement('div');
        row.dataset.notificationId = String(payload.id);
        row.className = 'border-b border-outline-variant/60 bg-primary-fixed/15 px-4 py-3 last:border-0';
        row.innerHTML = `
            <p class="font-label-md text-label-md font-semibold text-on-surface">${escapeHtml(payload.title || '')}</p>
            <p class="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">${escapeHtml(payload.body || '')}</p>
            <div class="mt-2">
                <span class="font-label-sm text-label-sm text-on-surface-variant">Vừa xong</span>
            </div>
        `;
        list.prepend(row);

        while (list.children.length > 8) {
            list.lastElementChild?.remove();
        }
    };

    const onCreated = (payload) => {
        setUnread(unread + 1);
        prependItem(payload);
        showToast(payload);
    };

    let subscribed = false;
    const subscribe = () => {
        if (subscribed || !window.Echo) {
            return;
        }
        subscribed = true;
        window.Echo.private(`user.${userId}`).listen('.notification.created', onCreated);
    };

    const start = () => {
        if (!window.enableMedlearnRealtime) {
            return;
        }
        window.enableMedlearnRealtime().then(subscribe).catch((error) => {
            console.warn('[notifications] realtime unavailable', error);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}
