@props([
    'force' => null, // 'light' | 'dark' | null (theo user/system)
])

@php
    $themePreference = auth()->check()
        ? (auth()->user()->theme ?? 'system')
        : null;
    $force = in_array($force, ['light', 'dark'], true) ? $force : null;
@endphp
{{-- Chạy sớm trong <head> để tránh flash theme sai trước khi CSS/Alpine load. --}}
<script>
    (function () {
        try {
            var force = @json($force);
            if (force) {
                window.__forceTheme = force;
            }
            var pref = force;
            if (pref === null) {
                pref = @json($themePreference);
            }
            if (pref === null) {
                pref = localStorage.getItem('theme') || 'system';
            }
            var mode = force
                ? force
                : (pref === 'system'
                    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : (pref === 'dark' ? 'dark' : 'light'));
            var root = document.documentElement;
            root.classList.remove('light', 'dark');
            root.classList.add(mode);
            if (force) {
                root.dataset.themeLock = force;
                // Khóa lại sau khi app.js bootstrapTheme chạy (tránh bị OS/admin dark ghi đè).
                var relock = function () {
                    root.classList.remove('light', 'dark');
                    root.classList.add(force);
                };
                document.addEventListener('DOMContentLoaded', relock);
                window.addEventListener('load', relock);
            }
        } catch (e) {}
    })();
</script>
@if (auth()->check() && $force === null)
    <script>
        window.__userThemePreference = @json(auth()->user()->theme ?? 'system');
        window.__userThemeSaveUrl = @json(route('settings.appearance'));
    </script>
@endif
