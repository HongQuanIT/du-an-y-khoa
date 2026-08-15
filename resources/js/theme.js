const STORAGE_KEY = 'theme';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export function resolveThemeMode(preference) {
    if (preference === 'system') {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    return preference === 'dark' ? 'dark' : 'light';
}

export function applyThemeMode(mode) {
    const lock = document.documentElement.dataset.themeLock;
    const resolved = (lock === 'light' || lock === 'dark') ? lock : (mode === 'dark' ? 'dark' : 'light');
    document.documentElement.classList.remove('light', 'dark');
    document.documentElement.classList.add(resolved);
}

export function applyTheme(preference) {
    applyThemeMode(resolveThemeMode(preference));
}

export function getStoredTheme() {
    if (typeof window.__forceTheme === 'string' && (window.__forceTheme === 'light' || window.__forceTheme === 'dark')) {
        return window.__forceTheme;
    }

    if (typeof window.__userThemePreference === 'string') {
        return window.__userThemePreference;
    }

    try {
        return localStorage.getItem(STORAGE_KEY) || 'system';
    } catch {
        return 'system';
    }
}

export async function persistTheme(preference) {
    try {
        localStorage.setItem(STORAGE_KEY, preference);
    } catch {
        // ignore private browsing
    }

    window.__userThemePreference = preference;

    if (typeof window.__userThemeSaveUrl !== 'string' || window.__userThemeSaveUrl === '') {
        return preference;
    }

    const response = await fetch(window.__userThemeSaveUrl, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ theme: preference }),
    });

    if (! response.ok) {
        const message = response.status === 419
            ? 'Phiên đăng nhập hết hạn — tải lại trang rồi thử lại.'
            : 'Không lưu được giao diện.';

        throw new Error(message);
    }

    return preference;
}

export async function setTheme(preference) {
    applyTheme(preference);

    try {
        await persistTheme(preference);
    } catch (error) {
        console.warn('[theme]', error instanceof Error ? error.message : error);
    }

    return preference;
}

export function initTheme() {
    const preference = getStoredTheme();
    applyTheme(preference);

    return preference;
}

export function bootstrapTheme() {
    initTheme();

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (typeof window.__forceTheme === 'string') {
            return;
        }

        if (getStoredTheme() === 'system') {
            applyTheme('system');
        }
    });
}

if (typeof window !== 'undefined') {
    window.MedlearnTheme = {
        resolveThemeMode,
        applyThemeMode,
        applyTheme,
        getStoredTheme,
        persistTheme,
        setTheme,
        initTheme,
        bootstrapTheme,
    };
}
