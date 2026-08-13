@php
    $themePreference = auth()->check()
        ? (auth()->user()->theme ?? 'system')
        : null;
@endphp
{{-- Chạy sớm trong <head> để tránh flash theme sai trước khi CSS/Alpine load. --}}
<script>
    (function () {
        try {
            var pref = @json($themePreference);
            if (pref === null) {
                pref = localStorage.getItem('theme') || 'system';
            }
            var mode = pref === 'system'
                ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : (pref === 'dark' ? 'dark' : 'light');
            var root = document.documentElement;
            root.classList.remove('light', 'dark');
            root.classList.add(mode);
        } catch (e) {}
    })();
</script>
@if (auth()->check())
    <script>
        window.__userThemePreference = @json(auth()->user()->theme ?? 'system');
        window.__userThemeSaveUrl = @json(route('settings.appearance'));
    </script>
@endif
