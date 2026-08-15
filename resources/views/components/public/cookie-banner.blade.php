{{--
  Vanilla JS + explicit style.display (Tailwind `flex` overrides HTML [hidden]).
--}}
<div
    id="cookie-consent-banner"
    role="dialog"
    aria-labelledby="cookie-banner-title"
    aria-describedby="cookie-banner-desc"
    style="display: none"
    class="fixed bottom-6 left-1/2 z-[100] w-[calc(100%-32px)] max-w-xl -translate-x-1/2"
>
    <div class="glass flex flex-col items-center gap-6 rounded-2xl border border-border p-6 shadow-2xl md:flex-row">
        <div class="flex-1 space-y-1">
            <div id="cookie-banner-title" class="font-label-md text-label-md text-on-surface">Chúng tôi sử dụng Cookies</div>
            <p id="cookie-banner-desc" class="font-body-sm text-body-sm text-text-secondary">
                Để mang lại trải nghiệm học tập tốt nhất,
                {{ config('app.name') }} sử dụng cookie để phân tích lưu lượng và cá nhân hóa nội dung.
                Xem
                <a href="{{ route('landing.privacy') }}" class="text-primary underline-offset-2 hover:underline">Chính sách bảo mật</a>.
            </p>
        </div>
        <div class="flex w-full gap-3 md:w-auto">
            <button type="button" data-cookie-consent="accepted"
                class="flex-1 rounded-xl bg-primary px-6 py-2 font-label-md text-label-md text-white transition-opacity hover:opacity-90 md:flex-none">
                Chấp nhận
            </button>
            <button type="button" data-cookie-consent="rejected"
                class="flex-1 rounded-xl border border-border px-6 py-2 font-label-md text-label-md transition-colors hover:bg-surface-container-low md:flex-none">
                Từ chối
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var KEY = 'cookie_consent';
    var MAX_AGE = 31536000;
    var banner = document.getElementById('cookie-consent-banner');
    if (!banner) return;

    function readStorage() {
        try { return localStorage.getItem(KEY); } catch (e) { return null; }
    }

    function writeStorage(value) {
        try { localStorage.setItem(KEY, value); } catch (e) {}
    }

    function readCookie() {
        var match = document.cookie.match(/(?:^|; )cookie_consent=([^;]*)/);
        if (!match) return null;
        try { return decodeURIComponent(match[1]); } catch (e) { return match[1]; }
    }

    function writeCookie(value) {
        var secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = KEY + '=' + encodeURIComponent(value)
            + '; Path=/; Max-Age=' + MAX_AGE + '; SameSite=Lax' + secure;
    }

    function isValid(value) {
        return value === 'accepted' || value === 'rejected';
    }

    function getConsent() {
        var stored = readStorage();
        if (isValid(stored)) return stored;
        var cookied = readCookie();
        if (isValid(cookied)) {
            writeStorage(cookied);
            return cookied;
        }
        return null;
    }

    function setConsent(value) {
        if (!isValid(value)) return;
        writeStorage(value);
        writeCookie(value);
        window.dispatchEvent(new CustomEvent('cookie-consent:changed', { detail: { value: value } }));
    }

    function clearCookieSettingsHash() {
        if (location.hash === '#cookie-settings') {
            history.replaceState(null, '', location.pathname + location.search);
        }
    }

    function showBanner() {
        banner.style.display = 'block';
        banner.setAttribute('aria-hidden', 'false');
    }

    function hideBanner() {
        banner.style.display = 'none';
        banner.setAttribute('aria-hidden', 'true');
    }

    function refresh() {
        if (location.hash === '#cookie-settings' && getConsent()) {
            clearCookieSettingsHash();
        }

        if (location.hash === '#cookie-settings') {
            showBanner();
            return;
        }

        if (getConsent()) hideBanner();
        else showBanner();
    }

    banner.querySelectorAll('[data-cookie-consent]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setConsent(btn.getAttribute('data-cookie-consent'));
            clearCookieSettingsHash();
            hideBanner();
        });
    });

    window.addEventListener('cookie-consent:open', showBanner);
    window.addEventListener('hashchange', function () {
        if (location.hash === '#cookie-settings') showBanner();
    });

    window.__cookieBanner = {
        get: getConsent,
        set: setConsent,
        show: showBanner,
        hide: hideBanner,
        refresh: refresh,
    };

    refresh();
})();
</script>
