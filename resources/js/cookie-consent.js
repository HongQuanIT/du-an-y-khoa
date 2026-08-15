/**
 * Cookie consent: localStorage + first-party cookie (1 year).
 * Values: "accepted" | "rejected"
 *
 * Persistence is intentionally usable without Alpine / module order quirks:
 * accept/reject write storage directly; readers check localStorage then cookie.
 */
const STORAGE_KEY = 'cookie_consent';
const COOKIE_NAME = 'cookie_consent';
const MAX_AGE_SECONDS = 365 * 24 * 60 * 60;
const ACCEPTED = 'accepted';
const REJECTED = 'rejected';

function isValid(value) {
    return value === ACCEPTED || value === REJECTED;
}

function readCookie(name) {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}=([^;]*)`));

    if (! match) {
        return null;
    }

    try {
        return decodeURIComponent(match[1]);
    } catch {
        return match[1];
    }
}

function writeCookie(name, value, maxAge) {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${name}=${encodeURIComponent(value)}; Path=/; Max-Age=${maxAge}; SameSite=Lax${secure}`;
}

function clearCookie(name) {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${name}=; Path=/; Max-Age=0; SameSite=Lax${secure}`;
}

function readStorage() {
    try {
        return window.localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

function writeStorage(value) {
    try {
        window.localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // Private mode / quota — cookie still persists choice.
    }
}

function clearStorage() {
    try {
        window.localStorage.removeItem(STORAGE_KEY);
    } catch {
        // ignore
    }
}

export function getConsent() {
    const fromStorage = readStorage();
    if (isValid(fromStorage)) {
        return fromStorage;
    }

    const fromCookie = readCookie(COOKIE_NAME);
    if (isValid(fromCookie)) {
        writeStorage(fromCookie);

        return fromCookie;
    }

    return null;
}

export function setConsent(value) {
    if (! isValid(value)) {
        return false;
    }

    writeStorage(value);
    writeCookie(COOKIE_NAME, value, MAX_AGE_SECONDS);

    // Verify at least one store stuck (Safari private quirks, etc.).
    const persisted = getConsent() === value;
    window.dispatchEvent(new CustomEvent('cookie-consent:changed', { detail: { value } }));

    return persisted;
}

export function clearConsent() {
    clearStorage();
    clearCookie(COOKIE_NAME);
    window.dispatchEvent(new CustomEvent('cookie-consent:changed', { detail: { value: null } }));
}

export function hasChoice() {
    return getConsent() !== null;
}

export function allowsAnalytics() {
    return getConsent() === ACCEPTED;
}

export function openSettings() {
    window.dispatchEvent(new CustomEvent('cookie-consent:open'));
}

function syncFromEitherSide() {
    const stored = readStorage();
    const cookied = readCookie(COOKIE_NAME);

    if (isValid(stored) && ! isValid(cookied)) {
        writeCookie(COOKIE_NAME, stored, MAX_AGE_SECONDS);
    } else if (isValid(cookied) && ! isValid(stored)) {
        writeStorage(cookied);
    }
}

function bindOpenTriggers() {
    if (window.__cookieConsentTriggersBound) {
        return;
    }
    window.__cookieConsentTriggersBound = true;

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (! (target instanceof Element)) {
            return;
        }

        const link = target.closest('a[href="#cookie-settings"]');
        if (! link) {
            return;
        }

        event.preventDefault();
        openSettings();
    });

    window.addEventListener('hashchange', () => {
        if (window.location.hash === '#cookie-settings') {
            openSettings();
        }
    });

    // Do not auto-open on stale hash at boot — inline banner script owns first paint.
    // Footer click / hashchange still open via handlers above + openSettings().
}

export function bootstrapCookieConsent() {
    syncFromEitherSide();

    window.CookieConsent = {
        STORAGE_KEY,
        COOKIE_NAME,
        ACCEPTED,
        REJECTED,
        get: getConsent,
        set: setConsent,
        clear: clearConsent,
        hasChoice,
        allowsAnalytics,
        openSettings,
    };

    bindOpenTriggers();
}

/**
 * Alpine factory — also used inline from the Blade component.
 */
export function cookieBannerState() {
    return {
        show: false,
        init() {
            this.refresh();

            this._onOpen = () => {
                this.show = true;
            };

            window.addEventListener('cookie-consent:open', this._onOpen);
        },
        destroy() {
            if (this._onOpen) {
                window.removeEventListener('cookie-consent:open', this._onOpen);
            }
        },
        refresh() {
            const choice = window.CookieConsent?.get?.() ?? getConsent();
            this.show = ! isValid(choice);
        },
        accept() {
            const api = window.CookieConsent;
            if (api) {
                api.set(api.ACCEPTED);
            } else {
                setConsent(ACCEPTED);
            }
            this.show = false;
        },
        reject() {
            const api = window.CookieConsent;
            if (api) {
                api.set(api.REJECTED);
            } else {
                setConsent(REJECTED);
            }
            this.show = false;
        },
    };
}

/**
 * @param {import('alpinejs').Alpine} Alpine
 */
export function registerCookieBannerAlpine(Alpine) {
    if (Alpine.data && ! window.__cookieBannerAlpineRegistered) {
        window.__cookieBannerAlpineRegistered = true;
        Alpine.data('cookieBanner', cookieBannerState);
    }
}
